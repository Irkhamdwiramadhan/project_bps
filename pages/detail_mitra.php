<?php
session_start();
include '../includes/koneksi.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: mitra.php?status=error&message=ID_mitra_tidak_ditemukan');
    exit;
}

$id_mitra = $_GET['id'];

$sql = "SELECT * FROM mitra WHERE id = ?";
$stmt = $koneksi->prepare($sql);

if ($stmt === false) {
    header('Location: mitra.php?status=error&message=Kesalahan_menyiapkan_query');
    exit;
}

$stmt->bind_param("i", $id_mitra);
$stmt->execute();
$result = $stmt->get_result();
$mitra = $result->fetch_assoc();

if (!$mitra) {
    header('Location: mitra.php?status=error&message=Data_mitra_tidak_ditemukan');
    exit;
}

$stmt->close();
$koneksi->close();

include '../includes/header.php';
include '../includes/sidebar.php';
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

    .detail-card {
        max-width: 1000px;
        margin: auto;
        background-color: #ffffff;
        border-radius: 1.5rem;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1),
                    0 4px 10px -2px rgba(0, 0, 0, 0.05);
        padding: 3rem;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .section-container {
        padding: 1.5rem;
        border-radius: 0.75rem;
        margin-bottom: 2rem;
    }

    .section-container.data-pribadi {
        background-color: #f6f8fb;
    }

    .section-container.alamat-info {
        background-color: #f6fafc;
    }

    .section-container.pengalaman-survei {
        background-color: #f6f8fb;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 0.5rem;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 1.5rem;
    }

    @media (min-width: 768px) {
        .detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1024px) {
        .detail-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .detail-group {
        display: flex;
        flex-direction: column;
    }

    .label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .value {
        font-size: 1rem;
        font-weight: 400;
        color: #1f2937;
        margin-top: 0.25rem;
    }

    .photo-preview {
        width: 180px;
        height: 180px;
        object-fit: cover;
        border-radius: 1rem;
        border: 3px solid #d1d5db;
        box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.1);
        margin-bottom: 1rem;
    }

    .btn-gradient {
        padding: 0.75rem 1.5rem;
        border-radius: 9999px;
        font-weight: 600;
        color: #ffffff;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                    0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .btn-primary {
        background: linear-gradient(to right, #3b82f6, #2563eb);
    }

    .btn-primary:hover {
        background: linear-gradient(to right, #2563eb, #1d4ed8);
        transform: translateY(-2px);
    }
</style>

<div class="content-wrapper">
    <div class="detail-card">
        <div class="flex flex-col items-center text-center mb-10">
            <?php if (!empty($mitra['foto'])) : ?>
                <img src="../uploads/<?= htmlspecialchars($mitra['foto']); ?>" alt="Foto Profil Mitra" class="photo-preview">
            <?php else : ?>
                <div class="photo-preview flex items-center justify-center bg-gray-200 text-gray-500">
                    <i class="fas fa-user-circle text-6xl"></i>
                </div>
            <?php endif; ?>
            <h1 class="text-4xl font-bold mt-4 text-gray-800"><?= htmlspecialchars($mitra['nama_lengkap']); ?></h1>
            <p class="text-lg text-gray-500 mt-1"><?= htmlspecialchars($mitra['nik']); ?></p>
        </div>

        <div class="section-container data-pribadi">
            <h2 class="section-title">Data Pribadi</h2>
            <div class="detail-grid">
                <div class="detail-group">
                    <p class="label">ID Mitra:</p>
                    <p class="value"><?= htmlspecialchars($mitra['id_mitra'] ?? '-'); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Tanggal Lahir:</p>
                    <p class="value"><?= htmlspecialchars($mitra['tanggal_lahir']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Jenis Kelamin:</p>
                    <p class="value"><?= htmlspecialchars($mitra['jenis_kelamin']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Agama:</p>
                    <p class="value"><?= htmlspecialchars($mitra['agama']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Status Perkawinan:</p>
                    <p class="value"><?= htmlspecialchars($mitra['status_perkawinan']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Pendidikan Terakhir:</p>
                    <p class="value"><?= htmlspecialchars($mitra['pendidikan']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Pekerjaan Utama:</p>
                    <p class="value"><?= htmlspecialchars($mitra['pekerjaan']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Nomor Telepon:</p>
                    <p class="value"><?= htmlspecialchars($mitra['no_telp']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Email:</p>
                    <p class="value"><?= htmlspecialchars($mitra['email']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">NPWP:</p>
                    <p class="value"><?= htmlspecialchars($mitra['npwp']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Nomor Rekening:</p>
                    <p class="value"><?= htmlspecialchars($mitra['norek'] ?? '-'); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Bank:</p>
                    <p class="value"><?= htmlspecialchars($mitra['bank'] ?? '-'); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Tahun:</p>
                    <p class="value"><?= htmlspecialchars($mitra['tahun']); ?></p>
                </div>
            </div>
        </div>

        <div class="section-container alamat-info">
            <h2 class="section-title">Informasi Alamat</h2>
            <div class="detail-grid">
                <div class="detail-group">
                    <p class="label">Provinsi:</p>
                    <p class="value"><?= htmlspecialchars($mitra['alamat_provinsi']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Kabupaten:</p>
                    <p class="value"><?= htmlspecialchars($mitra['alamat_kabupaten']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Kecamatan:</p>
                    <p class="value"><?= htmlspecialchars($mitra['nama_kecamatan']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Desa:</p>
                    <p class="value"><?= htmlspecialchars($mitra['alamat_desa']); ?></p>
                </div>
                <div class="detail-group md:col-span-2 lg:col-span-3">
                    <p class="label">Detail Alamat:</p>
                    <p class="value"><?= nl2br(htmlspecialchars($mitra['alamat_detail'])); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Alamat domisili sama dengan KTP:</p>
                    <p class="value"><?= ($mitra['domisili_sama'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                </div>
            </div>
        </div>

        <div class="section-container pengalaman-survei">
            <h2 class="section-title">Pengalaman Survei BPS</h2>
            <div class="detail-grid">
                <div class="detail-group">
                    <p class="label">Pernah mengikuti pendataan BPS?:</p>
                    <p class="value"><?= htmlspecialchars($mitra['mengikuti_pendataan_bps']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Posisi:</p>
                    <p class="value"><?= htmlspecialchars($mitra['posisi']); ?></p>
                </div>
                <div class="detail-group">
                    <p class="label">Kecamatan (Survei):</p>
                    <p class="value"><?= htmlspecialchars($mitra['nama_kecamatan']); ?></p>
                </div>
                <div class="detail-group md:col-span-2 lg:col-span-3">
                    <p class="label">Kategori Survei yang Diikuti:</p>
                    <div class="flex flex-wrap gap-4 mt-2">
                        <p class="value"><span class="font-semibold">SP:</span> <?= ($mitra['sp'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                        <p class="value"><span class="font-semibold">ST:</span> <?= ($mitra['st'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                        <p class="value"><span class="font-semibold">SE:</span> <?= ($mitra['se'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                        <p class="value"><span class="font-semibold">Susenas:</span> <?= ($mitra['susenas'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                        <p class="value"><span class="font-semibold">Sakernas:</span> <?= ($mitra['sakernas'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                        <p class="value"><span class="font-semibold">SBH:</span> <?= ($mitra['sbh'] == 1) ? 'Ya' : 'Tidak'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10 flex justify-end space-x-6">
            <button type="button" onclick="window.location.href='mitra.php'" class="btn-gradient btn-primary">Kembali ke Daftar Mitra</button>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
