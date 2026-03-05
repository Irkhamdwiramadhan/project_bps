<?php
// Pastikan sesi sudah dimulai dan pengguna adalah admin
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil data pegawai untuk dropdown
$query_pegawai = "SELECT id, nama FROM pegawai ORDER BY nama ASC";
$result_pegawai = mysqli_query($koneksi, $query_pegawai);
$pegawai_list = mysqli_fetch_all($result_pegawai, MYSQLI_ASSOC);

// =================================================================
// LOGIKA FILTER
// =================================================================
$filter_berdasarkan = isset($_GET['filter_berdasarkan']) ? $_GET['filter_berdasarkan'] : 'pegawai_semua';
$pegawai_id = '';

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
// LOGIKA KUERI DINAMIS
// =================================================================
$params = [];
$types = '';
$where_clause = " WHERE 1=1";

// 1. Filter Pegawai
if (!empty($pegawai_id)) {
    $where_clause .= " AND s.pegawai_id = ?";
    $params[] = $pegawai_id;
    $types .= 'i';
}

// 2. Filter Periode
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

// PENYUSUNAN QUERY
if ($filter_berdasarkan === 'produk_semua') {
    // Mode Rekap Produk
    $sql_rekap = "SELECT 
                    pr.name AS nama_produk, 
                    SUM(si.qty) AS total_jumlah,
                    SUM(si.qty * si.price) AS total_pendapatan,
                    MIN(DATE(s.date)) as tanggal_awal_produk,
                    MAX(DATE(s.date)) as tanggal_akhir_produk
                  FROM sales_items si
                  JOIN products pr ON si.product_id = pr.id
                  JOIN sales s ON si.sale_id = s.id
                  $where_clause
                  GROUP BY pr.id, pr.name 
                  ORDER BY total_jumlah DESC";
} else {
    // Mode Rekap Transaksi (Pegawai)
    $sql_rekap = "SELECT s.id, s.date, s.total, p.nama AS nama_pegawai,
                         si.qty, si.price, pr.name AS nama_produk,
                         (si.qty * si.price) as subtotal
                  FROM sales s
                  JOIN pegawai p ON s.pegawai_id = p.id
                  JOIN sales_items si ON s.id = si.sale_id
                  LEFT JOIN products pr ON si.product_id = pr.id
                  $where_clause
                  ORDER BY s.date DESC";
}

// Eksekusi
$stmt = $koneksi->prepare($sql_rekap);
$transaksi_data = [];
$total_penjualan = 0;

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_rekap = $stmt->get_result();
    
    while ($row = $result_rekap->fetch_assoc()) {
        $transaksi_data[] = $row;
        if ($filter_berdasarkan === 'produk_semua') {
            $total_penjualan += $row['total_pendapatan'];
        } else {
            $total_penjualan += ($row['qty'] * $row['price']);
        }
    }
    $stmt->close();
}
?>

<style>
    :root { --primary-color: #007bff; --secondary-color: #6c757d; --success-color: #28a745; --light-bg: #f7f9fc; --card-bg: #ffffff; --text-color: #343a40; --border-color: #dee2e6; }
    .main-content { padding: 40px; background-color: var(--light-bg); }
    .container { max-width: 1200px; margin: auto; }
    .header-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .section-title { color: var(--text-color); font-weight: 700; font-size: 2rem; margin: 0; }
    .print-button, .export-button { background-color: var(--primary-color); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .print-button:hover { background-color: #0056b3; }
    .export-button { background-color: var(--success-color); }
    .export-button:hover { background-color: #218838; }
    .filter-card, .table-card { background-color: var(--card-bg); padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); margin-bottom: 30px; }
    .filter-form { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; }
    .filter-group { display: flex; flex-direction: column; gap: 8px; }
    .filter-group-range { display: flex; gap: 15px; }
    .filter-form label { font-weight: 500; color: var(--secondary-color); }
    .form-control { border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; font-size: 0.9rem; transition: border-color 0.3s; }
    .form-control:focus { outline: none; border-color: var(--primary-color); }
    .filter-button { background-color: var(--success-color); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: background-color 0.3s ease; display: flex; align-items: center; gap: 8px; }
    .filter-button:hover { background-color: #218838; }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left; }
    .data-table th, .data-table td { padding: 15px; border-bottom: 1px solid var(--border-color); }
    .data-table th { background-color: var(--light-bg); font-weight: 600; color: var(--text-color); text-transform: uppercase; }
    .data-table tbody tr:hover { background-color: #f0f2f5; }
    .summary-box { background-color: #e9f5ff; border-left: 5px solid var(--primary-color); padding: 20px; border-radius: 8px; margin-top: 20px; font-size: 1.2rem; font-weight: 600; color: var(--primary-color); display: flex; justify-content: space-between; align-items: center; }
    .empty-state { text-align: center; padding: 50px 0; color: var(--secondary-color); }
    .empty-state i { font-size: 3rem; margin-bottom: 15px; }
    .table-header { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px; }
    .header-actions { display: flex; gap: 10px; }
    .manage-button { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background-color: #6c757d; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; text-decoration: none; transition: background-color 0.3s ease; }
    .manage-button:hover { background-color: #5a6268; }
</style>

<main class="main-content">
    <div class="container">
        <div class="header-container">
            <h2 class="section-title">Rekap Transaksi</h2>
            <div class="header-actions">
                <a href="bendahara.php" class="manage-button">
                    <i class="fas fa-user-cog"></i> Kelola Bendahara
                </a>
                
                <?php
                $url_params = array_filter([
                    'filter_berdasarkan' => $filter_berdasarkan, 
                    'periode' => $periode, 
                    'tanggal_filter' => ($periode === 'harian') ? $tanggal_filter : '', 
                    'tanggal_awal' => ($periode !== 'harian') ? $tanggal_awal : '', 
                    'tanggal_akhir' => ($periode !== 'harian') ? $tanggal_akhir : ''
                ]);
                $print_url = 'nota_transaksi.php?' . http_build_query($url_params);
                ?>
                <button onclick="window.open('<?= htmlspecialchars($print_url) ?>', '_blank')" class="print-button">
                    <i class="fas fa-print"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="filter-card">
            <form action="rekap_transaksi.php" method="GET" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label for="filter_berdasarkan">Filter Berdasarkan:</label>
                    <select name="filter_berdasarkan" id="filter_berdasarkan" class="form-control">
                        <optgroup label="Rekap Transaksi">
                            <option value="pegawai_semua" <?= $filter_berdasarkan == 'pegawai_semua' ? 'selected' : '' ?>>Semua Pegawai</option>
                            <?php foreach ($pegawai_list as $pegawai): ?>
                                <option value="pegawai_<?= $pegawai['id'] ?>" <?= $filter_berdasarkan == 'pegawai_'.$pegawai['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pegawai['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Rekap Produk">
                            <option value="produk_semua" <?= $filter_berdasarkan == 'produk_semua' ? 'selected' : '' ?>>Semua Produk Terjual</option>
                        </optgroup>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="periode">Periode:</label>
                    <select name="periode" id="periode" class="form-control">
                        <option value="harian" <?= $periode == 'harian' ? 'selected' : '' ?>>Harian</option>
                        <option value="mingguan" <?= $periode == 'mingguan' ? 'selected' : '' ?>>Mingguan</option>
                        <option value="bulanan" <?= $periode == 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                    </select>
                </div>
                <div class="filter-group" id="tanggalSingleGroup">
                    <label for="tanggal_filter">Tanggal:</label>
                    <input type="date" name="tanggal_filter" id="tanggal_filter" class="form-control" value="<?= htmlspecialchars($tanggal_filter) ?>">
                </div>
                <div class="filter-group-range" id="tanggalRangeGroup" style="display: none;">
                    <div class="filter-group"><label for="tanggal_awal">Tanggal Awal:</label><input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control" value="<?= htmlspecialchars($tanggal_awal) ?>"></div>
                    <div class="filter-group"><label for="tanggal_akhir">Tanggal Akhir:</label><input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>"></div>
                </div>
                <button type="submit" class="filter-button"><i class="fas fa-filter"></i> Tampilkan</button>
            </form>
        </div>

        <div class="table-card">
            <?php if (!empty($transaksi_data)): ?>
                
                <div class="table-header">
                    <?php
                        // Re-use the same parameters for consistency
                        $export_url = 'export_produk_excel.php?' . http_build_query($url_params);
                    ?>
                    <a href="<?= htmlspecialchars($export_url) ?>" class="export-button"><i class="fas fa-file-excel"></i> Download Excel</a>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <?php if ($filter_berdasarkan === 'produk_semua'): ?>
                                <tr>
                                    <th>Nama Produk</th>
                                    <th>Periode Terjual</th>
                                    <th style="text-align:center;">Jumlah Terjual</th>
                                    <th style="text-align:right;">Total Pendapatan</th>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th>No Struk</th>
                                    <th>Waktu</th>
                                    <th>Pegawai</th>
                                    <th>Produk</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th style="text-align:right;">Harga Satuan</th>
                                    <th style="text-align:right;">Subtotal</th>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php if ($filter_berdasarkan === 'produk_semua'): ?>
                                <?php foreach ($transaksi_data as $transaksi): ?>
                                <tr>
                                    <td><?= htmlspecialchars($transaksi['nama_produk']) ?></td>
                                    <td>
                                        <?php 
                                        if ($transaksi['tanggal_awal_produk'] == $transaksi['tanggal_akhir_produk']) {
                                            echo date('d/m/Y', strtotime($transaksi['tanggal_awal_produk']));
                                        } else {
                                            echo date('d/m', strtotime($transaksi['tanggal_awal_produk'])) . ' - ' . date('d/m/Y', strtotime($transaksi['tanggal_akhir_produk']));
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align:center;"><?= number_format($transaksi['total_jumlah'], 0, ',', '.') ?></td>
                                    <td style="text-align:right;">Rp <?= number_format($transaksi['total_pendapatan'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($transaksi_data as $transaksi): ?>
                                <tr>
                                    <td>#<?= $transaksi['id'] ?></td>
                                    <td><?= date('d/m H:i', strtotime($transaksi['date'])) ?></td>
                                    <td><?= htmlspecialchars($transaksi['nama_pegawai']) ?></td>
                                    <td><?= htmlspecialchars($transaksi['nama_produk']) ?></td>
                                    <td style="text-align:center;"><?= $transaksi['qty'] ?></td>
                                    <td style="text-align:right;">Rp <?= number_format($transaksi['price'], 0, ',', '.') ?></td>
                                    <td style="text-align:right;">Rp <?= number_format($transaksi['qty'] * $transaksi['price'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="summary-box">
                    <strong>Total Pendapatan (<?= ($filter_berdasarkan === 'produk_semua') ? 'Produk' : 'Transaksi' ?>):</strong> 
                    <span>Rp <?= number_format($total_penjualan, 0, ',', '.') ?></span>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i><p>Tidak ada data transaksi ditemukan untuk periode ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const periodeSelect = document.getElementById('periode');
        const tanggalSingleGroup = document.getElementById('tanggalSingleGroup');
        const tanggalRangeGroup = document.getElementById('tanggalRangeGroup');

        function toggleTanggalInputs() {
            if (periodeSelect.value === 'harian') {
                tanggalSingleGroup.style.display = 'block';
                tanggalRangeGroup.style.display = 'none';
            } else {
                tanggalSingleGroup.style.display = 'none';
                tanggalRangeGroup.style.display = 'flex';
            }
        }
        periodeSelect.addEventListener('change', toggleTanggalInputs);
        toggleTanggalInputs();
    });
</script>

<?php include '../includes/footer.php'; ?>