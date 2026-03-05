<?php
// Pastikan sesi sudah dimulai
session_start();
include '../includes/koneksi.php';

// Validasi Akses
if (!isset($_SESSION['user_role']) || (!in_array('super_admin', $_SESSION['user_role']) && !in_array('admin_koperasi', $_SESSION['user_role']))) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

// =================================================================
// 1. TANGKAP FILTER (Sesuai dengan Rekap Transaksi)
// =================================================================
$filter_berdasarkan = isset($_GET['filter_berdasarkan']) ? $_GET['filter_berdasarkan'] : 'pegawai_semua';
$pegawai_id = '';

// Deteksi ID Pegawai dari string filter
if (strpos($filter_berdasarkan, 'pegawai_') === 0) {
    $pegawai_val = str_replace('pegawai_', '', $filter_berdasarkan);
    if ($pegawai_val !== 'semua') {
        $pegawai_id = intval($pegawai_val);
    }
}

$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';
$tanggal_filter = isset($_GET['tanggal_filter']) ? $_GET['tanggal_filter'] : date('Y-m-d');
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

// =================================================================
// 2. BANGUN QUERY
// =================================================================
$params = [];
$types = '';
$where_clause = " WHERE 1=1";

// A. Filter Pegawai
if (!empty($pegawai_id)) {
    $where_clause .= " AND s.pegawai_id = ?";
    $params[] = $pegawai_id;
    $types .= 'i';
}

// B. Filter Tanggal
switch ($periode) {
    case 'harian':
        if (!empty($tanggal_filter)) {
            $where_clause .= " AND DATE(s.date) = ?";
            $params[] = $tanggal_filter;
            $types .= 's';
        }
        break;
    case 'mingguan':
    case 'bulanan':
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $where_clause .= " AND DATE(s.date) BETWEEN ? AND ?";
            $params[] = $tanggal_awal;
            $params[] = $tanggal_akhir;
            $types .= 'ss';
        }
        break;
}

// C. Tentukan Mode Query (Produk vs Transaksi)
if ($filter_berdasarkan === 'produk_semua') {
    // === MODE REKAP PRODUK ===
    $judul_nota = "Rekap Penjualan Produk";
    $sql_rekap = "SELECT 
                    pr.name AS nama_produk, 
                    SUM(si.qty) AS qty, 
                    SUM(si.qty * si.price) AS subtotal,
                    MIN(s.date) as date -- Ambil tanggal awal saja untuk display
                  FROM sales_items si
                  JOIN products pr ON si.product_id = pr.id
                  JOIN sales s ON si.sale_id = s.id
                  $where_clause
                  GROUP BY pr.id, pr.name 
                  ORDER BY qty DESC";
} else {
    // === MODE REKAP TRANSAKSI ===
    $judul_nota = "Rekap Transaksi";
    $sql_rekap = "SELECT s.id, s.date, p.nama AS nama_pegawai,
                         si.qty, si.price, pr.name AS nama_produk,
                         (si.qty * si.price) as subtotal
                  FROM sales s
                  JOIN pegawai p ON s.pegawai_id = p.id
                  JOIN sales_items si ON s.id = si.sale_id
                  LEFT JOIN products pr ON si.product_id = pr.id
                  $where_clause
                  ORDER BY s.date ASC";
}

// Eksekusi
$stmt = $koneksi->prepare($sql_rekap);
$transaksi_data = [];
$total_penjualan = 0;
$total_qty = 0;

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_rekap = $stmt->get_result();
    
    while ($row = $result_rekap->fetch_assoc()) {
        $transaksi_data[] = $row;
        $total_qty += $row['qty'];
        $total_penjualan += $row['subtotal'];
    }
    $stmt->close();
}

// Ambil nama bendahara
$stmt_bendahara = $koneksi->prepare("SELECT pengaturan_nilai FROM pengaturan WHERE pengaturan_nama = 'nama_bendahara'");
$stmt_bendahara->execute();
$data_bendahara = $stmt_bendahara->get_result()->fetch_assoc();
$nama_bendahara = $data_bendahara['pengaturan_nilai'] ?? 'Bendahara Belum Diatur'; 
$stmt_bendahara->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi Modern</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
        }
        .nota-container {
            max-width: 700px;
            margin: 0 auto;
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .header-nota {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 30px;
        }
        .header-nota img {
            width: 80px;
            height: 80px;
            margin-bottom: 10px;
        }
        .header-nota h1 {
            color: #333;
            font-size: 2.2rem;
            margin: 0;
            font-weight: 700;
        }
        .header-nota p {
            margin: 5px 0 0;
            color: #777;
            font-size: 0.9rem;
        }
        .details-transaksi {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        .details-transaksi div p {
            margin: 5px 0;
        }
        .details-transaksi .label {
            color: #555;
            font-weight: 500;
        }
        .details-transaksi .value {
            color: #333;
            font-weight: 700;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .product-table th, .product-table td {
            text-align: left;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .product-table thead th {
            font-weight: 500;
            color: #777;
            font-size: 0.9rem;
        }
        .product-table tbody tr:last-child td {
            border-bottom: none;
        }
        .product-table .item-name {
            font-weight: 500;
        }
        .product-table .qty, .product-table .price, .product-table .subtotal, .product-table .date {
            text-align: right;
        }
        .total-summary {
            text-align: right;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        .total-summary p {
            margin: 8px 0;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .total-summary .total-label {
            font-weight: 500;
            color: #555;
            flex-basis: 150px;
            text-align: left;
        }
        .total-summary .total-value {
            font-weight: 700;
            color: #333;
            font-size: 1.1rem;
        }
        .total-summary .total-value.grand-total {
            font-size: 1.5rem;
            color: #007bff;
        }
        .footer-nota {
            text-align: center;
            margin-top: 40px;
            font-size: 0.85rem;
            color: #999;
        }
        .footer-nota p {
            margin: 5px 0;
        }
        .print-button {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-align: center;
        }
        .print-button:hover {
            background-color: #0056b3;
        }
        @media print {
            body { background-color: #fff; }
            .nota-container { box-shadow: none; border: none; padding: 0; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="nota-container">
        <div class="header-nota">
            <img src="../assets/img/logo/logo_koperasi.png" alt="Logo Koperasi">
            <h1>B-S Mart</h1>
            <p>Koperasi Bina Sejati - BPS Kabupaten Tegal</p>
        </div>
        
        <div class="details-transaksi">
            <div>
                <?php 
                // Menentukan tampilan tanggal periode
                $tgl_display = '';
                if ($periode === 'harian') {
                    $tgl_display = date('d F Y', strtotime($tanggal_filter));
                } else {
                    $tgl_display = date('d/m/Y', strtotime($tanggal_awal)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir));
                }
                ?>
                <p><span class="label">Periode:</span> <span class="value"><?= $tgl_display ?></span></p>
                <p><span class="label">Jenis Laporan:</span> <span class="value"><?= $judul_nota ?></span></p>
            </div>
            <div>
                <?php if (!empty($pegawai_id) && !empty($transaksi_data)): ?>
                    <p><span class="label">Pembeli:</span> <span class="value"><?= htmlspecialchars($transaksi_data[0]['nama_pegawai'] ?? '-') ?></span></p>
                <?php elseif ($filter_berdasarkan === 'pegawai_semua'): ?>
                    <p><span class="label">Filter:</span> <span class="value">Semua Pegawai</span></p>
                <?php else: ?>
                    <p><span class="label">Filter:</span> <span class="value">Semua Produk</span></p>
                <?php endif; ?>
                <p><span class="label">Total Qty:</span> <span class="value"><?= number_format($total_qty, 0, ',', '.') ?></span></p>
            </div>
        </div>

        <table class="product-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Item</th>
                    
                    <?php if ($filter_berdasarkan !== 'produk_semua'): ?>
                        <?php if (empty($pegawai_id)): ?>
                            <th style="width: 20%;">Pembeli</th>
                        <?php endif; ?>
                        <th style="width: 15%;" class="date">Waktu</th>
                    <?php endif; ?>

                    <th style="width: 10%;" class="qty">Qty</th>
                    
                    <?php if ($filter_berdasarkan !== 'produk_semua'): ?>
                        <th style="width: 15%;" class="price">Harga</th>
                    <?php endif; ?>
                    
                    <th style="width: 15%;" class="subtotal">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksi_data)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 20px;">Tidak ada data ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($transaksi_data as $item): ?>
                    <tr>
                        <td class="item-name"><?= htmlspecialchars($item['nama_produk']) ?></td>
                        
                        <?php if ($filter_berdasarkan !== 'produk_semua'): ?>
                            <?php if (empty($pegawai_id)): ?>
                                <td><?= htmlspecialchars($item['nama_pegawai']) ?></td>
                            <?php endif; ?>
                            <td class="date"><?= date('d/m/y H:i', strtotime($item['date'])) ?></td>
                        <?php endif; ?>

                        <td class="qty"><?= $item['qty'] ?></td>
                        
                        <?php if ($filter_berdasarkan !== 'produk_semua'): ?>
                            <td class="price">Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                        <?php endif; ?>
                        
                        <td class="subtotal">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

       
        <div class="total-summary">

            <p>

                <span class="total-label">Subtotal:</span>

                <span class="total-value">Rp <?= number_format($total_penjualan, 0, ',', '.') ?></span>

            </p>

            <p>

                <span class="total-label">Diskon:</span>

                <span class="total-value">Rp 0</span>

            </p>

            <p>

                <span class="total-label" style="font-size: 1.2rem; color: #000;">Total Tagihan:</span>

                <span class="total-value grand-total">Rp <?= number_format($total_penjualan, 0, ',', '.') ?></span>

            </p>

        </div>

        

        <div class="footer-nota">

            <p>Terima kasih telah berbelanja di B-S Mart!</p>

            <div style="margin-top: 15px;">

                <p style="font-size: 0.9rem; font-weight: 500; color: #555;">Hormat Kami,</p>

                <p style="font-size: 1.1rem; margin-top: 5px; color: #333; font-weight: 700;"><?= htmlspecialchars($nama_bendahara) ?></p>

                <p style="font-size: 0.8rem; color: #888;">(Bendahara Koperasi)</p>

            </div>

        </div>

        <button onclick="window.print()" class="print-button"><i class="fas fa-print"></i> Cetak Nota</button>
    </div>
</body>
</html>