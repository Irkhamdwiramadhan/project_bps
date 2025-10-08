<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Keamanan: Pastikan user login dan ada ID yang valid
if (!isset($_SESSION['loggedin']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: kegiatan_tim.php');
    exit;
}

$kegiatan_id = (int)$_GET['id'];
$detail_kegiatan = null;
$anggota_terlibat = [];
$anggota_tidak_terlibat = [];

// 1. Ambil detail kegiatan dasar dan nama tim
$stmt_detail = $koneksi->prepare("SELECT k.*, t.nama_tim FROM kegiatan k JOIN tim t ON k.tim_id = t.id WHERE k.id = ?");
$stmt_detail->bind_param("i", $kegiatan_id);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$detail_kegiatan = $result_detail->fetch_assoc();
$stmt_detail->close();

// Jika kegiatan tidak ditemukan, kembali ke halaman utama
if (!$detail_kegiatan) {
    $_SESSION['error_message'] = "Data kegiatan tidak ditemukan.";
    header('Location: kegiatan_tim.php');
    exit;
}
$tim_id = $detail_kegiatan['tim_id'];

// 2. Ambil anggota yang TERLIBAT
$sql_terlibat = "(SELECT at.id, p.nama AS nama_lengkap FROM kegiatan_anggota ka JOIN anggota_tim at ON ka.anggota_id = at.id JOIN pegawai p ON at.member_id = p.id WHERE ka.kegiatan_id = ? AND at.member_type = 'pegawai') UNION ALL (SELECT at.id, m.nama_lengkap FROM kegiatan_anggota ka JOIN anggota_tim at ON ka.anggota_id = at.id JOIN mitra m ON at.member_id = m.id WHERE ka.kegiatan_id = ? AND at.member_type = 'mitra')";
$stmt_terlibat = $koneksi->prepare($sql_terlibat);
$stmt_terlibat->bind_param("ii", $kegiatan_id, $kegiatan_id);
$stmt_terlibat->execute();
$result_terlibat = $stmt_terlibat->get_result();
$anggota_terlibat_ids = [];
while ($row = $result_terlibat->fetch_assoc()) {
    $anggota_terlibat[] = $row;
    $anggota_terlibat_ids[] = (int)$row['id'];
}
$stmt_terlibat->close();

// 3. Ambil anggota tim yang TIDAK TERLIBAT
$id_placeholder = !empty($anggota_terlibat_ids) ? implode(',', array_fill(0, count($anggota_terlibat_ids), '?')) : 'NULL';
$sql_tidak_terlibat = "(SELECT at.id, p.nama AS nama_lengkap FROM anggota_tim at JOIN pegawai p ON at.member_id = p.id WHERE at.tim_id = ? AND at.member_type = 'pegawai' AND at.id NOT IN ($id_placeholder)) UNION ALL (SELECT at.id, m.nama_lengkap FROM anggota_tim at JOIN mitra m ON at.member_id = m.id WHERE at.tim_id = ? AND at.member_type = 'mitra' AND at.id NOT IN ($id_placeholder))";
$stmt_tidak_terlibat = $koneksi->prepare($sql_tidak_terlibat);
if (!empty($anggota_terlibat_ids)) {
    $params = array_merge([$tim_id], $anggota_terlibat_ids, [$tim_id], $anggota_terlibat_ids);
    $types = str_repeat('i', count($params));
    $stmt_tidak_terlibat->bind_param($types, ...$params);
} else {
    $stmt_tidak_terlibat->bind_param("ii", $tim_id, $tim_id);
}
$stmt_tidak_terlibat->execute();
$result_tidak_terlibat = $stmt_tidak_terlibat->get_result();
while ($row = $result_tidak_terlibat->fetch_assoc()) {
    $anggota_tidak_terlibat[] = $row;
}
$stmt_tidak_terlibat->close();
?>

<style>
    /* Sedikit style tambahan untuk halaman detail */
    .detail-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .detail-header {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1.5rem;
    }
    .detail-header h3 { margin-bottom: 0.25rem; font-weight: 600; }
    .detail-header .badge { font-size: 1rem; }
    .list-group-item { border-left: none; border-right: none; }
    .list-group-item:first-child { border-top-left-radius: 0; border-top-right-radius: 0; }
</style>

<main class="main-content">
    <div class="header-content">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4">DETAIL KEGIATAN</h2>
            <a href="kegiatan_tim.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar
            </a>
        </div>
    </div>

    <div class="p-4">
        <div class="card detail-card">
            <div class="detail-header">
                <h3><?= htmlspecialchars($detail_kegiatan['nama_kegiatan']) ?></h3>
                <span class="badge bg-primary-subtle border border-primary-subtle text-primary-emphasis rounded-pill">
                    Tim: <?= htmlspecialchars($detail_kegiatan['nama_tim']) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Anggota yang Terlibat (<?= count($anggota_terlibat) ?>)</h5>
                        <?php if (count($anggota_terlibat) > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($anggota_terlibat as $anggota): ?>
                                    <li class="list-group-item"><?= htmlspecialchars($anggota['nama_lengkap']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">Tidak ada anggota yang ditugaskan.</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mt-4 mt-md-0">
                        <h5 class="mb-3"><i class="bi bi-person-dash-fill text-secondary me-2"></i>Anggota Tim Lainnya (<?= count($anggota_tidak_terlibat) ?>)</h5>
                        <?php if (count($anggota_tidak_terlibat) > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($anggota_tidak_terlibat as $anggota): ?>
                                    <li class="list-group-item"><?= htmlspecialchars($anggota['nama_lengkap']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">Semua anggota tim sudah terlibat.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>