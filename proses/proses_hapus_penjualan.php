<?php
// Tampilkan semua error PHP untuk tujuan debugging
// Hapus baris ini setelah masalah teratasi
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/koneksi.php';

// Pastikan parameter 'id' ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "ID penjualan tidak valid.";
    exit();
}

$sale_id = intval($_GET['id']);

// Pastikan koneksi database berhasil
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Mulai transaksi untuk memastikan integritas data
$koneksi->begin_transaction();

try {
    // Hapus item-item penjualan terlebih dahulu dari tabel sales_items
    // Pastikan nama tabel dan kolom di bawah ini sudah sesuai dengan database Anda
    $stmt_items = $koneksi->prepare("DELETE FROM sales_items WHERE sale_id = ?");
    if (!$stmt_items) {
        throw new Exception("Gagal menyiapkan statement untuk sales_items: " . $koneksi->error);
    }
    $stmt_items->bind_param("i", $sale_id);
    $stmt_items->execute();

    // Setelah item dihapus, hapus data penjualan dari tabel sales
    // Pastikan nama tabel dan kolom di bawah ini sudah sesuai dengan database Anda
    $stmt_sales = $koneksi->prepare("DELETE FROM sales WHERE id = ?");
    if (!$stmt_sales) {
        throw new Exception("Gagal menyiapkan statement untuk sales: " . $koneksi->error);
    }
    $stmt_sales->bind_param("i", $sale_id);
    $stmt_sales->execute();

    // Commit transaksi jika semua berhasil
    $koneksi->commit();
    
    // Redirect kembali ke halaman riwayat penjualan dengan pesan sukses
    header("Location: ../pages/history_penjualan.php?status=success");
    exit();

} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    $koneksi->rollback();
    
    // Tampilkan pesan error agar Anda bisa melacak masalahnya
    echo "Terjadi kesalahan saat menghapus data: " . $e->getMessage();
    // Atau bisa juga redirect dengan pesan error
    // header("Location: ../pages/history_penjualan.php?status=error&message=" . urlencode($e->getMessage()));
    exit();
}

$koneksi->close();
?>