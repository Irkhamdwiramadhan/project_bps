<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil ID dari URL
$survey_id = $_GET['id'] ?? null;
$user_role = $_SESSION['user_role'] ?? '';

// Validasi ID
if (!$survey_id || !is_numeric($survey_id)) {
    echo "<div class='content-wrapper'><div class='card p-6 text-center text-red-500 font-semibold'>ID Survei tidak valid.</div></div>";
    include '../includes/footer.php';
    exit;
}

// Data yang akan ditampilkan
$survey_detail = null;
$grouped_data = [];
$stmt_survey = null;
$stmt_mitra = null;

try {
    // 1. Ambil data survei berdasarkan ID
    $sql_survey = "SELECT id, nama_survei, singkatan_survei, satuan, seksi_terdahulu, nama_tim_sekarang FROM surveys WHERE id = ?";
    $stmt_survey = $koneksi->prepare($sql_survey);
    $stmt_survey->bind_param("i", $survey_id);
    $stmt_survey->execute();
    $result_survey = $stmt_survey->get_result();
    $survey_detail = $result_survey->fetch_assoc();

    // Jika survei tidak ditemukan
    if (!$survey_detail) {
        echo "<div class='content-wrapper'><div class='card p-6 text-center text-red-500 font-semibold'>Survei tidak ditemukan.</div></div>";
        include '../includes/footer.php';
        exit;
    }

    // 2. Ambil data mitra yang terlibat dan kelompokkan berdasarkan periode
    $sql_mitra = "SELECT
                    ms.id AS mitra_survey_id,
                    ms.periode_jenis,
                    ms.periode_nilai,
                    m.nama_lengkap,
                    m.nik
                FROM mitra_surveys ms
                JOIN mitra m ON ms.mitra_id = m.id
                WHERE ms.survei_id = ?
                ORDER BY ms.periode_jenis, ms.periode_nilai";
    $stmt_mitra = $koneksi->prepare($sql_mitra);
    $stmt_mitra->bind_param("i", $survey_id);
    $stmt_mitra->execute();
    $result_mitra = $stmt_mitra->get_result();
    
    if ($result_mitra->num_rows > 0) {
        while ($row = $result_mitra->fetch_assoc()) {
            $periode_key = $row['periode_jenis'] . ' - ' . $row['periode_nilai'];
            if (!isset($grouped_data[$periode_key])) {
                $grouped_data[$periode_key] = [];
            }
            $grouped_data[$periode_key][] = $row;
        }
    }

} catch (Exception $e) {
    echo "<div class='content-wrapper'><div class='card p-6 text-center text-red-500 font-semibold'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    include '../includes/footer.php';
    exit;
} finally {
    // Tutup statement hanya jika sudah disiapkan
    if ($stmt_survey instanceof mysqli_stmt) {
        $stmt_survey->close();
    }
    if ($stmt_mitra instanceof mysqli_stmt) {
        $stmt_mitra->close();
    }
    // Pastikan koneksi selalu ditutup
    if ($koneksi) {
        $koneksi->close();
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #eef2f5;
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
    .detail-item {
        display: flex;
        flex-direction: column;
    }
    .detail-item label {
        font-weight: 500;
        color: #4b5563;
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }
    .detail-item p {
        font-weight: 600;
        font-size: 1.1rem;
        color: #111827;
        margin-top: 0;
    }
    .list-container {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    .list-item {
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background-color: #f9fafb;
        display: flex;
        align-items: center;
        width: 100%;
        justify-content: space-between;
    }
    .list-item:hover {
        background-color: #f3f4f6;
    }
    .list-item p {
        margin: 0;
    }
    .btn-delete {
        background-color: #ef4444;
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-weight: 500;
        transition: background-color 0.2s;
    }
    .btn-delete:hover {
        background-color: #dc2626;
    }
        .btn-back {
        display: inline-flex;
        align-items: center;
        /* Mengurangi padding untuk ukuran yang lebih kecil */
        padding: 0.5rem; 
        border-radius: 9999px;
        background-color: #e5e7eb;
        color: #4b5563;
        transition: background-color 0.2s, color 0.2s;
    }
    .btn-back:hover {
        background-color: #d1d5db;
        color: #111827;
    }
    .btn-back svg {
        /* Mengurangi ukuran SVG ikon */
        height: 1rem;
        width: 1rem;
    }
    .table-container {
        margin-top: 1.5rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    thead th {
        background-color: #e2e8f0;
        color: #4a5568;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem;
        text-align: left;
    }
    tbody td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center mb-6">
            <a href="jenis_surveys.php" class="btn-back mr-4">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($survey_detail['nama_survei']) ?></h1>
        </div>
        
        <div class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 card">
                <h2 class="col-span-1 md:col-span-2 text-xl font-semibold text-gray-800 mb-2">Informasi Survei</h2>
                <div class="detail-item">
                    <label>Singkatan Survei</label>
                    <p><?= htmlspecialchars($survey_detail['singkatan_survei']) ?></p>
                </div>
                <div class="detail-item">
                    <label>Satuan</label>
                    <p><?= htmlspecialchars($survey_detail['satuan']) ?></p>
                </div>
                <div class="detail-item">
                    <label>Seksi Terdahulu</label>
                    <p><?= htmlspecialchars($survey_detail['seksi_terdahulu']) ?></p>
                </div>
                <div class="detail-item">
                    <label>Nama Tim Sekarang</label>
                    <p><?= htmlspecialchars($survey_detail['nama_tim_sekarang']) ?></p>
                </div>
            </div>

            <div class="card">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Mitra yang Terlibat</h2>
                
                <?php if (count($grouped_data) > 0) : ?>
                    <?php foreach ($grouped_data as $periode => $mitra_list) : ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4"><?= htmlspecialchars($periode) ?></h3>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Mitra</th>
                                            <th>NIK</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; ?>
                                        <?php foreach ($mitra_list as $mitra) : ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($mitra['nama_lengkap']) ?></td>
                                                <td><?= htmlspecialchars($mitra['nik']) ?></td>
                                                <td>
                                                    
                                                        <a href="../proses/delete_kegiatan.php?id=<?= htmlspecialchars($mitra['mitra_survey_id']) ?>"
                                                           class="btn-delete"
                                                           onclick="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini?');">Hapus</a>
                                                    
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="text-center text-gray-500 italic">Belum ada mitra yang terdaftar untuk survei ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php';
?>