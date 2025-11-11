<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil parameter dari URL
$mitra_id = $_GET['mitra_id'] ?? null;
$bulan_filter = $_GET['bulan'] ?? null;
$tahun_filter = $_GET['tahun'] ?? null;

// Validasi parameter
if (empty($mitra_id) || empty($bulan_filter) || empty($tahun_filter)) {
    header("Location: rekap_honor.php?status=error&message=" . urlencode("Parameter tidak lengkap."));
    exit;
}

// Array untuk nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$bulan_nama = $nama_bulan[$bulan_filter] ?? 'Tidak Ditemukan';
$nama_mitra = "Mitra"; // Default

try {
    // 🔹 Ambil data detail honor dengan pencocokan fleksibel master_item
  $sql_detail = "
    SELECT DISTINCT
        mk.nama AS nama_kegiatan,
        COALESCE(mi.nama_item, '-') AS nama_item,
        COALESCE(mi.satuan, '-') AS satuan,
        hm.jumlah_satuan,
        hm.honor_per_satuan,
        hm.total_honor,
        hm.bulan_pembayaran,
        hm.tahun_pembayaran,
        hm.tanggal_input
    FROM honor_mitra hm
    LEFT JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    LEFT JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode
    LEFT JOIN master_item mi ON hm.item_kode_unik LIKE CONCAT(mi.kode_unik, '%')
    WHERE hm.mitra_id = ? 
      AND hm.bulan_pembayaran = ? 
      AND hm.tahun_pembayaran = ?
    ORDER BY hm.tanggal_input DESC
";


    $stmt_detail = $koneksi->prepare($sql_detail);
    if (!$stmt_detail) {
        throw new Exception("Gagal menyiapkan statement detail: " . $koneksi->error);
    }
    $stmt_detail->bind_param("sss", $mitra_id, $bulan_filter, $tahun_filter);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();

    $detail_list = [];
    $grand_total = 0;
    while ($row = $result_detail->fetch_assoc()) {
        $detail_list[] = $row;
        $grand_total += $row['total_honor'];
    }

    // 🔹 Ambil nama mitra
    $sql_mitra = "SELECT nama_lengkap FROM mitra WHERE id = ?";
    $stmt_mitra = $koneksi->prepare($sql_mitra);
    $stmt_mitra->bind_param("s", $mitra_id);
    $stmt_mitra->execute();
    $result_mitra = $stmt_mitra->get_result();
    if ($result_mitra->num_rows > 0) {
        $nama_mitra = $result_mitra->fetch_assoc()['nama_lengkap'];
    }
    $stmt_mitra->close();

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $detail_list = [];
    $grand_total = 0;
}

$koneksi->close();
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

body {
    font-family: 'Poppins', sans-serif;
    background: #f0f4f8;
}
.content-wrapper {
    padding: 1rem;
    transition: margin-left 0.3s ease;
}
@media (min-width: 640px) {
    .content-wrapper { margin-left: 16rem; padding-top: 2rem; }
}
.card {
    background: #fff;
    border-radius: 1rem;
    padding: 2.5rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.table-container { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
thead th {
    background: #e2e8f0; color: #4a5568; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem;
    text-align: left;
}
tbody td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}
tbody tr:hover { background: #f9fafb; }
.total-row { font-weight: 700; background: #f0f4f8; }
.back-button {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: #4f46e5; color: #fff; padding: 0.75rem 1.5rem;
    border-radius: 0.5rem; font-weight: 600; text-decoration: none;
    transition: background 0.2s;
}
.back-button:hover { background: #4338ca; }
</style>

<div class="content-wrapper">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Detail Honor Mitra</h1>
        <h2 class="text-xl text-gray-600 mb-6">
            <span class="font-semibold"><?= htmlspecialchars($nama_mitra) ?></span> —
            Bulan <span class="font-semibold"><?= htmlspecialchars($bulan_nama) ?> <?= htmlspecialchars($tahun_filter) ?></span>
        </h2>

        <div class="card space-y-8">
            <a href="rekap_honor.php?bulan=<?= htmlspecialchars($bulan_filter) ?>&tahun=<?= htmlspecialchars($tahun_filter) ?>" class="back-button">
                &larr; Kembali
            </a>

            <?php if (count($detail_list) > 0): ?>
                <div class="table-container mt-6">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kegiatan</th>
                                <th>Nama Item</th>
                                <th>Satuan</th>
                                <th>Jumlah Satuan</th>
                                <th>Honor per Satuan</th>
                                <th>Total Honor</th>
                                <th>Tanggal Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($detail_list as $row): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_kegiatan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['nama_item'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
                                    <td><?= number_format($row['jumlah_satuan'] ?? 0, 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format($row['honor_per_satuan'] ?? 0, 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format($row['total_honor'] ?? 0, 0, ',', '.') ?></td>
                                    <td><?= date('d-m-Y', strtotime($row['tanggal_input'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="6">Total Honor Bulan Ini:</td>
                                <td colspan="2">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500 py-4">
                    <p>Tidak ada data honor untuk mitra ini pada bulan dan tahun yang dipilih.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
