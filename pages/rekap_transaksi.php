<?php
// Pastikan sesi sudah dimulai dan pengguna adalah admin
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Validasi hak akses


// Ambil data pegawai untuk dropdown
$query_pegawai = "SELECT id, nama FROM pegawai ORDER BY nama ASC";
$result_pegawai = mysqli_query($koneksi, $query_pegawai);

$pegawai_id = isset($_GET['pegawai_id']) ? intval($_GET['pegawai_id']) : '';
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'harian';
$tanggal_filter = isset($_GET['tanggal_filter']) ? $_GET['tanggal_filter'] : date('Y-m-d');
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : '';
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

// Persiapan kueri SQL
// Kueri diperbarui untuk menggunakan tabel products dan sales_items
$sql_rekap = "SELECT s.id, s.date, s.total, p.nama AS nama_pegawai,
                     si.qty, si.price, pr.name AS nama_produk
              FROM sales s
              JOIN pegawai p ON s.pegawai_id = p.id
              JOIN sales_items si ON s.id = si.sale_id
              LEFT JOIN products pr ON si.product_id = pr.id
              WHERE 1=1";
$params = [];
$types = '';

if (!empty($pegawai_id)) {
    $sql_rekap .= " AND s.pegawai_id = ?";
    $params[] = $pegawai_id;
    $types .= 'i';
}

switch ($periode) {
    case 'harian':
        if (!empty($tanggal_filter)) {
            $sql_rekap .= " AND DATE(s.date) = ?";
            $params[] = $tanggal_filter;
            $types .= 's';
        }
        break;
    case 'mingguan':
    case 'bulanan':
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $sql_rekap .= " AND DATE(s.date) BETWEEN ? AND ?";
            $params[] = $tanggal_awal;
            $params[] = $tanggal_akhir;
            $types .= 'ss';
        }
        break;
}

$sql_rekap .= " ORDER BY s.date DESC";

$stmt = $koneksi->prepare($sql_rekap);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result_rekap = $stmt->get_result();
    $transaksi_data = $result_rekap->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $transaksi_data = [];
}

// Hitung total penjualan dari sales.total
$total_penjualan = 0;
if (!empty($transaksi_data)) {
    $sales_ids = array_unique(array_column($transaksi_data, 'id'));
    if (!empty($sales_ids)) {
        $sql_total = "SELECT SUM(total) as total_sum FROM sales WHERE id IN (" . implode(',', array_map('intval', $sales_ids)) . ")";
        $result_total = mysqli_query($koneksi, $sql_total);
        if ($result_total) {
            $total_row = mysqli_fetch_assoc($result_total);
            $total_penjualan = $total_row['total_sum'];
        }
    }
}
?>

<style>
    :root {
        --primary-color: #007bff;
        --secondary-color: #6c757d;
        --success-color: #28a745;
        --light-bg: #f7f9fc;
        --card-bg: #ffffff;
        --text-color: #343a40;
        --border-color: #dee2e6;
    }
    .main-content {
        padding: 40px;
        background-color: var(--light-bg);
    }
    .container {
        max-width: 1200px;
        margin: auto;
    }
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .section-title {
        color: var(--text-color);
        font-weight: 700;
        font-size: 2rem;
        margin: 0;
    }
    .print-button {
        background-color: var(--primary-color);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        transition: background-color 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .print-button:hover {
        background-color: #0056b3;
    }
    .filter-card, .table-card {
        background-color: var(--card-bg);
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .filter-group-range {
        display: flex;
        gap: 15px;
    }
    .filter-form label {
        font-weight: 500;
        color: var(--secondary-color);
    }
    .form-control {
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 10px;
        font-size: 0.9rem;
        transition: border-color 0.3s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    .filter-button {
        background-color: var(--success-color);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .filter-button:hover {
        background-color: #218838;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        text-align: left;
    }
    .data-table th, .data-table td {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    .data-table th {
        background-color: var(--light-bg);
        font-weight: 600;
        color: var(--text-color);
        text-transform: uppercase;
    }
    .data-table tbody tr:hover {
        background-color: #f0f2f5;
    }
    .summary-box {
        background-color: #e9f5ff;
        border-left: 5px solid var(--primary-color);
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .empty-state {
        text-align: center;
        padding: 50px 0;
        color: var(--secondary-color);
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
    }
</style>

<main class="main-content">
    <div class="container">
        <div class="header-container">
            <h2 class="section-title">Rekap Transaksi</h2>
            <?php
            // Membangun URL untuk tombol cetak
            $print_query_params = array_filter([
                'pegawai_id' => $pegawai_id,
                'periode' => $periode,
                'tanggal_filter' => ($periode === 'harian') ? $tanggal_filter : '',
                'tanggal_awal' => ($periode !== 'harian') ? $tanggal_awal : '',
                'tanggal_akhir' => ($periode !== 'harian') ? $tanggal_akhir : ''
            ]);
            $print_url = 'cetak_nota_bendahara.php?' . http_build_query($print_query_params);
            ?>
            <button onclick="window.open('<?= htmlspecialchars($print_url) ?>', '_blank')" class="print-button"><i class="fas fa-print"></i> Cetak Nota</button>
        </div>

        <div class="filter-card">
            <form action="rekap_transaksi.php" method="GET" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label for="pegawai_id">Pegawai:</label>
                    <select name="pegawai_id" id="pegawai_id" class="form-control">
                        <option value="">Semua Pegawai</option>
                        <?php while ($row = mysqli_fetch_assoc($result_pegawai)): ?>
                            <option value="<?= $row['id'] ?>" <?= $pegawai_id == $row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['nama']) ?></option>
                        <?php endwhile; ?>
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
                    <div class="filter-group">
                        <label for="tanggal_awal">Tanggal Awal:</label>
                        <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control" value="<?= htmlspecialchars($tanggal_awal) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="tanggal_akhir">Tanggal Akhir:</label>
                        <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                    </div>
                </div>
                <button type="submit" class="filter-button"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>

        <div class="table-card">
            <?php if (!empty($transaksi_data)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID Sales</th>
                                <th>Tanggal</th>
                                <th>Pegawai</th>
                                <th>Nama Produk</th>
                                <th>Jumlah</th>
                                <th>Harga Satuan</th>
                                <th>Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaksi_data as $transaksi): ?>
                            <tr>
                                <td><?= $transaksi['id'] ?></td>
                                <td><?= date('d-m-Y H:i:s', strtotime($transaksi['date'])) ?></td>
                                <td><?= htmlspecialchars($transaksi['nama_pegawai']) ?></td>
                                <td><?= htmlspecialchars($transaksi['nama_produk']) ?></td>
                                <td><?= $transaksi['qty'] ?></td>
                                <td>Rp <?= number_format($transaksi['price'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($transaksi['qty'] * $transaksi['price'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="summary-box">
                    <strong>Total Penjualan Keseluruhan:</strong> <span>Rp <?= number_format($total_penjualan, 0, ',', '.') ?></span>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>Tidak ada data transaksi ditemukan.</p>
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
            const periode = periodeSelect.value;
            if (periode === 'harian') {
                tanggalSingleGroup.style.display = 'block';
                tanggalRangeGroup.style.display = 'none';
            } else {
                tanggalSingleGroup.style.display = 'none';
                tanggalRangeGroup.style.display = 'flex';
            }
        }

        periodeSelect.addEventListener('change', toggleTanggalInputs);
        toggleTanggalInputs(); // Jalankan saat halaman pertama kali dimuat
    });
</script>
<?php include '../includes/footer.php'; ?>