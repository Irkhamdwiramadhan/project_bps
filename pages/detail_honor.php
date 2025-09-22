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
$nama_mitra = "Mitra"; // Nilai default

try {
    // Kueri utama yang diperbarui untuk menggunakan bulan_pembayaran dan tahun_pembayaran
    $sql_detail = "SELECT
                        s.nama_survei,
                        hm.honor_per_satuan,
                        hm.jumlah_satuan,
                        hm.total_honor,
                        hm.tanggal_input
                    FROM
                        honor_mitra AS hm
                    JOIN
                        surveys AS s ON hm.survei_id = s.id
                    WHERE
                        hm.mitra_id = ? AND hm.bulan_pembayaran = ? AND hm.tahun_pembayaran = ?
                    ORDER BY
                        hm.tanggal_input DESC";

    $stmt_detail = $koneksi->prepare($sql_detail);
    if (!$stmt_detail) {
        throw new Exception("Gagal menyiapkan statement detail: " . $koneksi->error);
    }
    $stmt_detail->bind_param("sss", $mitra_id, $bulan_filter, $tahun_filter);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();

    $detail_list = [];
    $grand_total = 0;
    
    if ($result_detail->num_rows > 0) {
        while ($row = $result_detail->fetch_assoc()) {
            $detail_list[] = $row;
            $grand_total += $row['total_honor'];
        }
    }
    
    // Query untuk mendapatkan nama mitra
    $sql_nama_mitra = "SELECT nama_lengkap FROM mitra WHERE id = ?";
    $stmt_nama_mitra = $koneksi->prepare($sql_nama_mitra);
    if (!$stmt_nama_mitra) {
        throw new Exception("Gagal menyiapkan statement nama mitra: " . $koneksi->error);
    }
    $stmt_nama_mitra->bind_param("s", $mitra_id);
    $stmt_nama_mitra->execute();
    $result_nama = $stmt_nama_mitra->get_result();
    if ($result_nama->num_rows > 0) {
        $row_nama = $result_nama->fetch_assoc();
        $nama_mitra = $row_nama['nama_lengkap'];
    }
    $stmt_nama_mitra->close();

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
        .content-wrapper {
            margin-left: 16rem;
            padding-top: 2rem;
        }
    }
    .card {
        background-color: #ffffff;
        border-radius: 1rem;
        padding: 2.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }
    thead th {
        background-color: #e2e8f0;
        color: #4a5568;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.5rem;
        text-align: left;
    }
    tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    tbody tr:last-child td {
        border-bottom: none;
    }
    tbody tr:hover {
        background-color: #f9fafb;
    }
    .total-row {
        font-weight: 700;
        background-color: #f0f4f8;
    }
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background-color: #4f46e5;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.2s;
    }
    .back-button:hover {
        background-color: #4338ca;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Detail Honor</h1>
        <h2 class="text-xl text-gray-600 mb-6">
            Histori Honor <span class="font-semibold"><?= htmlspecialchars($nama_mitra) ?></span> - Bulan <span class="font-semibold"><?= htmlspecialchars($bulan_nama) ?> <?= htmlspecialchars($tahun_filter) ?></span>
        </h2>
        
        <div class="card space-y-8">
            <a href="rekap_honor.php?bulan=<?= htmlspecialchars($bulan_filter) ?>&tahun=<?= htmlspecialchars($tahun_filter) ?>" class="back-button">
                &larr; Kembali
            </a>

            <?php if (count($detail_list) > 0) : ?>
                <div class="table-container mt-6">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kegiatan</th>
                                <th>Jumlah Satuan</th>
                                <th>Honor per Satuan</th>
                                <th>Total Honor</th>
                                <th>Tanggal Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($detail_list as $detail) : ?>
                                <tr>
                                    <td><?= $counter ?></td>
                                    <td><?= htmlspecialchars($detail['nama_survei']) ?></td>
                                    <td><?= number_format($detail['jumlah_satuan'], 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format($detail['honor_per_satuan'], 0, ',', '.') ?></td>
                                    <td>Rp <?= number_format($detail['total_honor'], 0, ',', '.') ?></td>
                                    <td><?= date('d-m-Y H:i', strtotime($detail['tanggal_input'])) ?></td>
                                </tr>
                            <?php $counter++; ?>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="4">Total Honor Bulan Ini:</td>
                                <td colspan="2">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="text-center text-gray-500 py-4">
                    <p>Tidak ada data honor untuk mitra ini pada bulan dan tahun yang dipilih.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>