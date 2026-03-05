<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// --- LOGIKA: AMBIL DATA REFERENSI TIM & ANGGOTA ---
// Separator menggunakan '||' agar aman saat di-explode nanti (koma bisa rancu dengan nama orang)
$sql_ref = "
    SELECT 
        t.nama_tim,
        GROUP_CONCAT(DISTINCT COALESCE(p.nama, m.nama_lengkap) ORDER BY COALESCE(p.nama, m.nama_lengkap) SEPARATOR '||') as daftar_anggota
    FROM tim t
    LEFT JOIN anggota_tim at ON t.id = at.tim_id
    LEFT JOIN pegawai p ON at.member_id = p.id AND at.member_type = 'pegawai'
    LEFT JOIN mitra m ON at.member_id = m.id AND at.member_type = 'mitra'
    WHERE t.is_active = 1
    GROUP BY t.id
    ORDER BY t.nama_tim ASC
";
$res_ref = $koneksi->query($sql_ref);
?>

<style>
    /* Styling Card Pilihan Mode */
    .mode-card {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid #e2e8f0;
    }
    .mode-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
    }
    /* State Aktif */
    .mode-card.active {
        border-color: #0d6efd; 
        background-color: #eff6ff;
        color: #0d6efd;
    }
    .mode-card.active-success {
        border-color: #198754; 
        background-color: #f0fdf4;
        color: #198754;
    }
    
    /* Animasi Form */
    .import-section {
        display: none;
        animation: slideDown 0.4s ease-out;
    }
    .import-section.active-section {
        display: block;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Notifikasi Mencolok */
    .alert-custom {
        border-left: 6px solid;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
    }
</style>

<div class="main-content">
    <div class="p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0 fw-bold text-dark">Import Data Massal</h3>
                <p class="text-muted mb-0">Kelola import kegiatan dan realisasi tim.</p>
            </div>
            <a href="kegiatan_tim.php" class="btn btn-secondary shadow-sm">
                <i class="bi bi-arrow-left me-2"></i>Kembali
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show mb-4" role="alert" style="border-left-color: #198754;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="alert-heading fw-bold mb-0">BERHASIL!</h5>
                        <div><?= $_SESSION['success']; ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show mb-4" role="alert" style="border-left-color: #dc3545;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-octagon-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="alert-heading fw-bold mb-0">GAGAL!</h5>
                        <div><?= $_SESSION['error']; ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['import_errors']) && count($_SESSION['import_errors']) > 0): ?>
            <div class="alert alert-warning alert-custom border-warning mb-4" style="border-left-color: #ffc107;">
                <div class="d-flex">
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                    <div class="w-100">
                        <h5 class="alert-heading fw-bold">Data Ditolak! Perbaiki Excel Anda:</h5>
                        <ul class="mb-0 small text-dark bg-white p-3 rounded border mt-2" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($_SESSION['import_errors'] as $msg): ?>
                                <li class="text-danger border-bottom py-1"><?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['import_errors']); ?>
        <?php endif; ?>


        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-primary fw-bold"><i class="bi bi-table me-2"></i>Referensi Nama Tim & Anggota</h6>
                <input type="text" id="searchTim" class="form-control form-control-sm w-25" placeholder="Cari nama...">
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-bordered mb-0 align-top" id="tableReferensi">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 35%;" class="align-middle">Nama Tim</th>
                                <th class="align-middle">Daftar Anggota (1 Baris = 1 Nama)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_ref->num_rows > 0): ?>
                                <?php while($row = $res_ref->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-dark bg-light"><?= htmlspecialchars($row['nama_tim']) ?></td>
                                    <td class="text-secondary small">
                                        <?php if($row['daftar_anggota']): ?>
                                            <?php 
                                                $anggota_list = explode('||', $row['daftar_anggota']);
                                                foreach($anggota_list as $nama): 
                                            ?>
                                                <div class="border-bottom py-1">
                                                    <i class="bi bi-person me-1 text-muted"></i> <?= htmlspecialchars(trim($nama)) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Kosong</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center py-3">Data tim belum tersedia.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="card p-3 text-center mode-card h-100 d-flex flex-row align-items-center justify-content-center gap-3" onclick="switchMode('kegiatan', this)">
                    <i class="bi bi-folder-plus fs-2"></i>
                    <div class="text-start">
                        <h6 class="mb-0 fw-bold">Import Kegiatan</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Buat kegiatan & target baru</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card p-3 text-center mode-card h-100 d-flex flex-row align-items-center justify-content-center gap-3" onclick="switchMode('realisasi', this)">
                    <i class="bi bi-graph-up-arrow fs-2"></i>
                    <div class="text-start">
                        <h6 class="mb-0 fw-bold">Import Realisasi</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Update capaian anggota</small>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-kegiatan" class="import-section">
            <div class="card border-primary shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-folder-plus me-2"></i> Form Import Kegiatan Baru
                </div>
                <div class="card-body bg-light">
                    <form action="../proses/proses_import_lengkap.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">File Excel Kegiatan (.xlsx)</label>
                            <input type="file" name="file_excel" class="form-control" required accept=".xlsx, .xls">
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="../assets/template_upload_kegiatan.xlsx" class="text-decoration-none small fw-bold">
                                <i class="bi bi-download me-1"></i> Download Template
                            </a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">Upload & Proses</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="section-realisasi" class="import-section">
            <div class="card border-success shadow-sm">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-graph-up-arrow me-2"></i> Form Update Realisasi
                </div>
                <div class="card-body bg-light">
                    <form action="../proses/proses_import_realisasi.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">File Excel Realisasi (.xlsx)</label>
                            <input type="file" name="file_excel" class="form-control" required accept=".xlsx, .xls">
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="../assets/template_realisasi.xlsx" class="text-decoration-none small fw-bold text-success">
                                <i class="bi bi-download me-1"></i> Download Template
                            </a>
                            <button type="submit" class="btn btn-success px-4 fw-bold">Update Realisasi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function switchMode(mode, element) {
    // Reset Style
    document.querySelectorAll('.mode-card').forEach(el => {
        el.classList.remove('active', 'active-success');
    });
    document.querySelectorAll('.import-section').forEach(el => {
        el.classList.remove('active-section');
    });

    // Set Active
    if(mode === 'kegiatan') {
        element.classList.add('active');
        document.getElementById('section-kegiatan').classList.add('active-section');
    } else {
        element.classList.add('active-success');
        document.getElementById('section-realisasi').classList.add('active-section');
    }
}

// Search Filter
document.getElementById('searchTim').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let rows = document.getElementById('tableReferensi').getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        let tdTim = rows[i].getElementsByTagName('td')[0];
        let tdAnggota = rows[i].getElementsByTagName('td')[1];
        if (tdTim || tdAnggota) {
            let textValue = (tdTim.textContent || tdTim.innerText) + (tdAnggota.textContent || tdAnggota.innerText);
            if (textValue.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }       
    }
});

// Auto-scroll jika ada error/success
window.onload = function() {
    if(document.querySelector('.alert')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
</script>

<?php include '../includes/footer.php'; ?>