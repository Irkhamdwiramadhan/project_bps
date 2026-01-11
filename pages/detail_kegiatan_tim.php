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

// 1. AMBIL DETAIL KEGIATAN UTAMA
$sql_detail = "SELECT k.*, t.nama_tim 
               FROM kegiatan k 
               JOIN tim t ON k.tim_id = t.id 
               WHERE k.id = ?";
$stmt = $koneksi->prepare($sql_detail);
$stmt->bind_param("i", $kegiatan_id);
$stmt->execute();
$detail_kegiatan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$detail_kegiatan) {
    echo "<script>alert('Kegiatan tidak ditemukan!'); window.location='kegiatan_tim.php';</script>";
    exit;
}

$tim_id = $detail_kegiatan['tim_id'];

// 2. AMBIL ANGGOTA YANG TERLIBAT (LOGIKA UNIVERSAL / SAPU JAGAT)
// Query ini mampu membaca ID Anggota Tim (at.id) MAUPUN ID Pegawai/Mitra langsung
$sql_terlibat = "
    SELECT 
        ka.anggota_id,
        ka.target_anggota,
        ka.realisasi_anggota,
        -- Ambil Nama (Cek via Relasi dulu, kalau null cek via Direct ID)
        COALESCE(p_rel.nama, m_rel.nama_lengkap, p_direct.nama, m_direct.nama_lengkap, 'Tanpa Nama') as nama_lengkap,
        -- Ambil ID Asli Orangnya (untuk filter 'Tidak Terlibat' nanti)
        COALESCE(p_rel.id, p_direct.id) as pegawai_id,
        COALESCE(m_rel.id, m_direct.id) as mitra_id,
        CASE 
            WHEN p_rel.id IS NOT NULL OR p_direct.id IS NOT NULL THEN 'pegawai'
            ELSE 'mitra'
        END as tipe_anggota
    FROM kegiatan_anggota ka
    
    -- JALUR 1: Cek via tabel anggota_tim (Old Logic)
    LEFT JOIN anggota_tim at ON ka.anggota_id = at.id
    LEFT JOIN pegawai p_rel ON at.member_id = p_rel.id AND at.member_type = 'pegawai'
    LEFT JOIN mitra m_rel ON at.member_id = m_rel.id AND at.member_type = 'mitra'
    
    -- JALUR 2: Cek via Pegawai Langsung (New Logic)
    LEFT JOIN pegawai p_direct ON ka.anggota_id = p_direct.id
    
    -- JALUR 3: Cek via Mitra Langsung (New Logic)
    LEFT JOIN mitra m_direct ON ka.anggota_id = m_direct.id
    
    WHERE ka.kegiatan_id = ?
    ORDER BY nama_lengkap ASC
";

$stmt_terlibat = $koneksi->prepare($sql_terlibat);
$stmt_terlibat->bind_param("i", $kegiatan_id);
$stmt_terlibat->execute();
$res_terlibat = $stmt_terlibat->get_result();

$stmt_terlibat->execute();
$res_terlibat = $stmt_terlibat->get_result();

$list_terlibat = [];
$terlibat_keys = []; // Array penanda siapa saja yang SUDAH punya target

while ($row = $res_terlibat->fetch_assoc()) {
    
    // REVISI LOGIKA: 
    // Hanya masukkan ke daftar "Terlibat" jika targetnya LEBIH DARI 0
    if ($row['target_anggota'] > 0) {
        $list_terlibat[] = $row;
        
        // Simpan kuncinya agar tidak muncul di daftar kanan
        if ($row['tipe_anggota'] == 'pegawai' && $row['pegawai_id']) {
            $terlibat_keys[] = 'pegawai_' . $row['pegawai_id'];
        } elseif ($row['tipe_anggota'] == 'mitra' && $row['mitra_id']) {
            $terlibat_keys[] = 'mitra_' . $row['mitra_id'];
        }
    }
    // Jika target == 0, kita abaikan di sini. 
    // Karena kuncinya tidak disimpan ke $terlibat_keys, 
    // dia otomatis akan dianggap "Belum Terlibat" di logika Step 3 bawah nanti.
}
$stmt_terlibat->close();

// 3. AMBIL SEMUA ANGGOTA TIM AKTIF (UNTUK MENCARI SIAPA YANG BELUM TERLIBAT)
// Logic ini akan otomatis menangkap orang yang tidak ada di DB 
// ATAU orang yang ada di DB tapi targetnya 0 (karena ID mereka tidak ada di $terlibat_keys)
$sql_all_members = "
    SELECT 
        at.member_type, 
        at.member_id, 
        COALESCE(p.nama, m.nama_lengkap) as nama_lengkap
    FROM anggota_tim at
    LEFT JOIN pegawai p ON at.member_id = p.id AND at.member_type = 'pegawai'
    LEFT JOIN mitra m ON at.member_id = m.id AND at.member_type = 'mitra'
    WHERE at.tim_id = ?
    ORDER BY nama_lengkap ASC
";

$stmt_all = $koneksi->prepare($sql_all_members);
$stmt_all->bind_param("i", $tim_id);
$stmt_all->execute();
$res_all = $stmt_all->get_result();

$list_tidak_terlibat = [];

while ($row = $res_all->fetch_assoc()) {
    // Buat kunci unik
    $key = strtolower($row['member_type']) . '_' . $row['member_id'];
    
    // Cek apakah orang ini sudah punya target > 0?
    if (!in_array($key, $terlibat_keys)) {
        // Jika TIDAK, masukkan ke daftar "Belum Ditugaskan"
        $list_tidak_terlibat[] = $row;
    }
}
$stmt_all->close();
?>

<style>
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
    .detail-header h3 { margin-bottom: 0.25rem; font-weight: 600; color: #1e293b; }
    .badge-tim { background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; font-size: 0.9rem; padding: 0.4em 0.8em; }
    
    .list-group-item { 
        border-left: none; border-right: none; 
        padding: 1rem 1.25rem;
        display: flex; justify-content: space-between; align-items: center;
    }
    .list-group-item:first-child { border-top: none; }
    
    .stat-badge { font-size: 0.8rem; padding: 0.3em 0.6em; border-radius: 6px; margin-left: 5px; }
    .bg-target { background-color: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
    .bg-realisasi { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    
    .avatar-placeholder {
        width: 32px; height: 32px; background-color: #e2e8f0; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        margin-right: 10px; font-weight: bold; color: #64748b; font-size: 0.8rem;
    }
</style>

<main class="main-content">
    <div class="header-content mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h4 font-weight-bold text-gray-800">Detail Kegiatan</h2>
                <p class="text-muted mb-0">Informasi lengkap dan pembagian tugas.</p>
            </div>
            <a href="kegiatan_tim.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <div class="p-0">
        <div class="card detail-card">
            <div class="detail-header">
                <h3><?= htmlspecialchars($detail_kegiatan['nama_kegiatan']) ?></h3>
                <div class="mt-2">
                    <span class="badge rounded-pill badge-tim">
                        <i class="bi bi-people-fill me-1"></i> Tim: <?= htmlspecialchars($detail_kegiatan['nama_tim']) ?>
                    </span>
                    <span class="badge rounded-pill bg-light text-dark border ms-2">
                        <i class="bi bi-calendar-event me-1"></i> Batas: <?= date('d M Y', strtotime($detail_kegiatan['batas_waktu'])) ?>
                    </span>
                </div>
                <?php if(!empty($detail_kegiatan['keterangan'])): ?>
                    <p class="mt-3 mb-0 text-muted small"><i class="bi bi-info-circle me-1"></i> <?= htmlspecialchars($detail_kegiatan['keterangan']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-7">
                        <h5 class="mb-3 d-flex align-items-center text-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Anggota Terlibat (<?= count($list_terlibat) ?>)
                        </h5>
                        <div class="card border-0 shadow-sm">
                            <?php if (count($list_terlibat) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($list_terlibat as $anggota): 
                                        $inisial = strtoupper(substr($anggota['nama_lengkap'], 0, 1));
                                    ?>
                                        <li class="list-group-item">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-placeholder"><?= $inisial ?></div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($anggota['nama_lengkap']) ?></div>
                                                    <div class="small text-muted"><?= ucfirst($anggota['tipe_anggota']) ?></div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="d-block mb-1">
                                                    <span class="stat-badge bg-target">Target: <b><?= $anggota['target_anggota'] ?></b></span>
                                                </div>
                                                <div class="d-block">
                                                    <span class="stat-badge bg-realisasi">Real: <b><?= $anggota['realisasi_anggota'] ?></b></span>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted bg-light rounded">
                                    <i class="bi bi-exclamation-circle mb-2 d-block fs-4"></i>
                                    Belum ada anggota yang ditugaskan.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <h5 class="mb-3 d-flex align-items-center text-secondary">
                            <i class="bi bi-person-dash-fill me-2"></i>
                            Belum Ditugaskan (<?= count($list_tidak_terlibat) ?>)
                        </h5>
                        <div class="card border-0 shadow-sm bg-light">
                            <?php if (count($list_tidak_terlibat) > 0): ?>
                                <ul class="list-group list-group-flush bg-transparent">
                                    <?php foreach ($list_tidak_terlibat as $anggota): ?>
                                        <li class="list-group-item bg-transparent">
                                            <span class="text-secondary"><?= htmlspecialchars($anggota['nama_lengkap']) ?></span>
                                            <a href="edit_kegiatan_tim.php?id=<?= $kegiatan_id ?>" class="btn btn-sm btn-outline-primary py-0" style="font-size: 0.75rem;">
                                                <i class="bi bi-plus"></i> Assign
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="bi bi-check-all mb-2 d-block fs-4 text-success"></i>
                                    Semua anggota tim sudah terlibat!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>