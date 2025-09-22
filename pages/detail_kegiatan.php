<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan ada ID mitra yang dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: kegiatan.php?status=error&message=ID_mitra_tidak_ditemukan');
    exit;
}

$mitra_id = $_GET['id'];
$user_role = $_SESSION['user_role'] ?? '';

try {
    // 1. Ambil data dasar mitra
    $sql_mitra = "SELECT nama_lengkap, nama_kecamatan, domisili_sama FROM mitra WHERE id = ?";
    $stmt_mitra = $koneksi->prepare($sql_mitra);
    $stmt_mitra->bind_param("i", $mitra_id);
    $stmt_mitra->execute();
    $result_mitra = $stmt_mitra->get_result();
    $mitra = $result_mitra->fetch_assoc();

    // Jika mitra tidak ditemukan, alihkan kembali
    if (!$mitra) {
        header('Location: kegiatan.php?status=error&message=Data_mitra_tidak_ditemukan');
        exit;
    }

    // 2. Ambil data kegiatan (survei) yang diikuti mitra
    $sql_surveys_diikuti = "SELECT
                                ms.id,
                                s.nama_survei,
                                s.singkatan_survei,
                                s.satuan,
                                ms.survey_ke_berapa,
                                ms.periode_jenis,
                                ms.periode_nilai
                            FROM
                                mitra_surveys AS ms
                            JOIN
                                surveys AS s ON ms.survei_id = s.id
                            WHERE
                                ms.mitra_id = ?
                            ORDER BY
                                ms.survey_ke_berapa ASC";
    $stmt_surveys = $koneksi->prepare($sql_surveys_diikuti);
    $stmt_surveys->bind_param("i", $mitra_id);
    $stmt_surveys->execute();
    $result_surveys = $stmt_surveys->get_result();
    $surveys_diikuti = [];
    if ($result_surveys) {
        while ($row = $result_surveys->fetch_assoc()) {
            $surveys_diikuti[] = $row;
        }
    }
    $jumlah_survei_diikuti = count($surveys_diikuti);
    $status_partisipasi = ($jumlah_survei_diikuti > 0) ? 'Sudah Ikut Kegiatan' : 'Belum Ikut Kegiatan';

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #f0f4f8; /* Latar belakang abu-abu yang lebih lembut */
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
        padding: 2.5rem; /* Tambah padding */
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); /* Shadow yang lebih lembut */
    }
    .badge-green {
        background-color: #d1f7e3;
        color: #28a745;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
    }
    .badge-red {
        background-color: #fce8e8;
        color: #dc3545;
        font-weight: 600;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
    }
    
    /* CSS Tambahan untuk Tombol Kembali */
    .btn-back {
        display: inline-flex;
        align-items: center;
        padding: 0.75rem; /* Ukuran padding lebih besar */
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
        height: 1.25rem;
        width: 1.25rem;
    }

    /* CSS Tambahan untuk Tabel Kegiatan */
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem; /* Ukuran font sedikit lebih besar */
    }
    thead th {
        background-color: #e2e8f0; /* Latar belakang header tabel */
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
    .btn-delete {
        background-color: #ef4444;
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-weight: 500;
    }
    .btn-delete:hover {
        background-color: #dc2626;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center mb-8">
            <a href="kegiatan.php" class="btn-back mr-4">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Detail Mitra</h1>
        </div>

        <div class="card space-y-8">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Informasi Mitra</h2>
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <p class="text-gray-500 w-40">Nama Mitra:</p>
                        <p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($mitra['nama_lengkap']) ?></p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <p class="text-gray-500 w-40">Kecamatan:</p>
                        <p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($mitra['nama_kecamatan']) ?></p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <p class="text-gray-500 w-40">Status Partisipasi:</p>
                        <span class="badge <?= ($status_partisipasi == 'Sudah Ikut Kegiatan') ? 'badge-green' : 'badge-red' ?>">
                            <?= htmlspecialchars($status_partisipasi) ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <p class="text-gray-500 w-40">Jumlah Survei:</p>
                        <p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($jumlah_survei_diikuti) ?></p>
                    </div>
                </div>
            </div>

            <?php if ($jumlah_survei_diikuti > 0) : ?>
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Daftar Kegiatan</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Kegiatan ke-</th>
                                    <th>Nama Survei</th>
                                    <th>Singkatan</th>
                                    <th>Satuan</th>
                                    <th>Jenis Periode</th>
                                    <th>Nilai Periode</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach ($surveys_diikuti as $survei) : ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= htmlspecialchars($survei['nama_survei']) ?></td>
                                        <td><?= htmlspecialchars($survei['singkatan_survei']) ?></td>
                                        <td><?= htmlspecialchars($survei['satuan']) ?></td>
                                        <td><?= htmlspecialchars($survei['periode_jenis']) ?></td>
                                        <td><?= htmlspecialchars($survei['periode_nilai']) ?></td>
                                        <td>
                                            <?php if ($user_role === 'admin_mitra' || $user_role === 'super_admin'): ?>
                                            <a href="../proses/delete_kegiatan.php?id=<?= htmlspecialchars($survei['id']) ?>"
                                               class="btn-delete"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini?');">Hapus</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php $counter++; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else : ?>
                <div class="text-center text-gray-500 py-4">
                    <p>Mitra ini belum terdaftar dalam kegiatan survei apa pun.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($stmt_mitra instanceof mysqli_stmt) {
    $stmt_mitra->close();
}
if ($stmt_surveys instanceof mysqli_stmt) {
    $stmt_surveys->close();
}
$koneksi->close();
include '../includes/footer.php';
?>