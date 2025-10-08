<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Keamanan: Pastikan user login dan ada ID yang valid di URL
if (!isset($_SESSION['loggedin']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: laporan_keluar_list.php');
    exit;
}

$laporan_id = (int)$_GET['id'];

// Ambil data detail laporan dari database menggunakan prepared statement
$stmt = $koneksi->prepare(
    "SELECT lk.*, p.nama AS nama_pegawai
     FROM laporan_keluar lk
     LEFT JOIN pegawai p ON lk.pegawai_id = p.id
     WHERE lk.id = ?"
);
$stmt->bind_param("i", $laporan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Data laporan tidak ditemukan.";
    header('Location: laporan_keluar_list.php');
    exit;
}
$laporan = $result->fetch_assoc();
$stmt->close();
$koneksi->close();
?>

<style>
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .detail-photo {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
    }
    dl dt { 
        font-weight: 600; 
        color: #475569; 
    }
    dl dd { 
        color: #1f2937; 
        margin-bottom: 1rem;
    }
</style>

<main class="main-content">
    <div class="header-content">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4">DETAIL LAPORAN KELUAR</h2>
            <a href="laporan_keluar_list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-7">
                        <h3>Laporan oleh: <?= htmlspecialchars($laporan['nama_pegawai'] ?? 'N/A') ?></h3>
                        <hr class="my-3">
                        <dl class="row">
                            <dt class="col-sm-4">Tanggal</dt>
                            <dd class="col-sm-8"><?= date('d F Y', strtotime($laporan['tanggal_laporan'])) ?></dd>

                            <dt class="col-sm-4">Jam</dt>
                            <dd class="col-sm-8"><?= date('H:i', strtotime($laporan['jam_laporan'])) ?> WIB</dd>

                            <dt class="col-sm-4">Tujuan</dt>
                            <dd class="col-sm-8" style="white-space: pre-wrap;"><?= htmlspecialchars($laporan['tujuan_keluar']) ?></dd>

                            <dt class="col-sm-4">Link GPS</dt>
                            <dd class="col-sm-8">
                                <a href="<?= htmlspecialchars($laporan['link_gps']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-geo-alt-fill me-2"></i>Lihat Lokasi di Peta
                                </a>
                            </dd>
                        </dl>
                    </div>

                    <div class="col-md-5 mt-3 mt-md-0">
                        <h5 class="mb-3">Foto Dokumentasi</h5>
                        <?php if (!empty($laporan['foto'])): ?>
                            <a href="../<?= htmlspecialchars($laporan['foto']) ?>" target="_blank">
                                <img src="../<?= htmlspecialchars($laporan['foto']) ?>" alt="Foto Laporan" class="detail-photo img-fluid">
                            </a>
                            <small class="text-muted d-block mt-2">Klik gambar untuk memperbesar.</small>
                        <?php else: ?>
                            <p class="text-muted">Tidak ada foto yang diupload.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>