<?php

// Set zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

include '../includes/koneksi.php';

// Cek apakah metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Metode tidak diizinkan.";
    exit;
}

// Ambil data dari form
$pegawaiId       = isset($_POST['pegawai_id']) ? (int)$_POST['pegawai_id'] : 0;
// Ambil total penjualan dari input tersembunyi
$totalPenjualan  = isset($_POST['total_penjualan']) ? (float)$_POST['total_penjualan'] : 0; 
// Ambil array produk yang sekarang sudah dikirimkan dengan benar dari tambah_penjualan.php
$items           = isset($_POST['produk']) ? $_POST['produk'] : []; 

// Ambil tanggal transaksi dari form (jika ada) atau gunakan waktu sekarang
$tanggalTransaksi = date('Y-m-d H:i:s'); 

// Validasi minimal: Cek apakah pegawai dan produk sudah dipilih
if ($pegawaiId <= 0) {
    echo "Gagal menyimpan penjualan: Pegawai belum dipilih.";
    exit;
}
if (empty($items)) {
    echo "Gagal menyimpan penjualan: Belum ada produk yang dipilih.";
    exit;
}

// Mulai transaksi database untuk memastikan semua operasi berhasil atau tidak sama sekali
mysqli_begin_transaction($koneksi);

try {
    // 1) Insert data ke tabel sales
    $sqlSales = "INSERT INTO sales (pegawai_id, date, total) VALUES (?, ?, ?)";
    $stmtSales = mysqli_prepare($koneksi, $sqlSales);
    if (!$stmtSales) {
        throw new Exception("Gagal menyiapkan query sales: " . mysqli_error($koneksi));
    }
    mysqli_stmt_bind_param($stmtSales, "isd", $pegawaiId, $tanggalTransaksi, $totalPenjualan);
    if (!mysqli_stmt_execute($stmtSales)) {
        throw new Exception("Gagal insert sales: " . mysqli_error($koneksi));
    }
    $saleId = mysqli_insert_id($koneksi);

    // Siapkan prepared statement untuk mengambil harga/promo dan stok produk
    $sqlGetPrice = "SELECT price, is_promo, promo_price, stock FROM products WHERE id = ? FOR UPDATE";
    $stmtGetPrice = mysqli_prepare($koneksi, $sqlGetPrice);
    if (!$stmtGetPrice) {
        throw new Exception("Gagal menyiapkan query harga produk: " . mysqli_error($koneksi));
    }

    // Siapkan prepared statement untuk insert data ke sales_items
    $sqlItems = "INSERT INTO sales_items (sale_id, product_id, qty, price) VALUES (?, ?, ?, ?)";
    $stmtItems = mysqli_prepare($koneksi, $sqlItems);
    if (!$stmtItems) {
        throw new Exception("Gagal menyiapkan query sales_items: " . mysqli_error($koneksi));
    }

    // Siapkan prepared statement untuk update stok produk
    $sqlUpdateStock = "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?";
    $stmtUpdateStock = mysqli_prepare($koneksi, $sqlUpdateStock);
    if (!$stmtUpdateStock) {
        throw new Exception("Gagal menyiapkan query update stok: " . mysqli_error($koneksi));
    }

    // 2) Loop setiap item yang dikirimkan dari form
    foreach ($items as $productId => $data) {
        $productId = (int)$productId;
        $qty       = isset($data['qty']) ? (int)$data['qty'] : 0;

        if ($productId <= 0 || $qty <= 0) {
            throw new Exception("Data item tidak valid.");
        }

        // Ambil harga dan stok terkini (mengunci baris produk untuk mencegah race condition)
        mysqli_stmt_bind_param($stmtGetPrice, "i", $productId);
        mysqli_stmt_execute($stmtGetPrice);
        $res = mysqli_stmt_get_result($stmtGetPrice);
        $prod = mysqli_fetch_assoc($res);

        if (!$prod) {
            throw new Exception("Produk ID {$productId} tidak ditemukan.");
        }

        $price       = (float)$prod['price'];
        $promoActive = (int)$prod['is_promo'] === 1;
        $promoPrice  = isset($prod['promo_price']) ? (float)$prod['promo_price'] : 0.0;
        $stockNow    = (int)$prod['stock'];

        // Cek apakah stok cukup
        if ($stockNow < $qty) {
            throw new Exception("Stok produk ID {$productId} tidak cukup. Sisa: {$stockNow}, diminta: {$qty}.");
        }

        // Tentukan harga akhir setelah mempertimbangkan promo
        $finalPrice = ($promoActive && $promoPrice > 0) ? $promoPrice : $price;

        // Insert item ke tabel sales_items
        mysqli_stmt_bind_param($stmtItems, "iiid", $saleId, $productId, $qty, $finalPrice);
        if (!mysqli_stmt_execute($stmtItems)) {
            throw new Exception("Gagal insert item untuk produk {$productId}: " . mysqli_error($koneksi));
        }

        // Update stok produk
        mysqli_stmt_bind_param($stmtUpdateStock, "iii", $qty, $productId, $qty);
        if (!mysqli_stmt_execute($stmtUpdateStock) || mysqli_stmt_affected_rows($stmtUpdateStock) === 0) {
            throw new Exception("Gagal mengurangi stok produk {$productId} (kemungkinan stok tidak cukup): " . mysqli_error($koneksi));
        }
    }

    // 3) Jika semua operasi berhasil, commit transaksi
    mysqli_commit($koneksi);
    
    // Redirect ke halaman history penjualan dengan status sukses
    header("Location: ../pages/history_penjualan.php?status=success");
    exit;

} catch (Exception $e) {
    // Jika ada error, rollback transaksi
    mysqli_rollback($koneksi);
    
    // Tampilkan pesan error yang jelas
    echo "Gagal menyimpan penjualan: " . $e->getMessage();
}
?>