<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek form mana yang harus aktif setelah redirect (jika ada error/sukses)
$active_form = ''; 
if (isset($_SESSION['tab_target'])) {
    $active_form = $_SESSION['tab_target']; // 'kegiatan' atau 'realisasi'
    unset($_SESSION['tab_target']);
}
?>

<style>
    /* Style Khusus untuk Kartu Pilihan */
    .selection-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        height: 100%;
    }
    .selection-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    /* State Aktif (Saat dipilih) */
    .selection-card.active-kegiatan {
        border-color: #0d6efd; /* Biru Bootstrap */
        background-color: #f8fbff;
    }
    .selection-card.active-realisasi {
        border-color: #198754; /* Hijau Bootstrap */
        background-color: #f0fdf4;
    }

    .icon-large {
        font-size: 3rem;
        margin-bottom: 15px;
        display: block;
    }

    /* Animasi Form Muncul */
    .upload-section {
        display: none; /* Default Hidden */
        animation: fadeIn 0.5s;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="main-content">
    <div class="p-4">
        
        <h3 class="mb-4 font-weight-bold text-gray-800">Import Data Excel</h3>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['import_errors']) && count($_SESSION['import_errors']) > 0): ?>
            <div class="alert alert-warning border-warning mb-4 shadow-sm">
                <h5 class="alert-heading"><i class="bi bi-exclamation-circle"></i> Gagal pada baris berikut:</h5>
                <ul class="mb-0 small" style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($_SESSION['import_errors'] as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['import_errors']); ?>
        <?php endif; ?>


        <div class="row g-4 mb-4">
            
            <div class="col-md-6">
                <div class="card shadow-sm selection-card h-100 text-center py-4" id="card-kegiatan" onclick="showForm('kegiatan')">
                    <div class="card-body">
                        <i class="bi bi-file-earmark-plus-fill icon-large text-primary"></i>
                        <h4 class="card-title text-primary fw-bold">Import Kegiatan Baru</h4>
                        <p class="card-text text-muted">Upload file Excel berisi daftar kegiatan baru beserta tim dan anggotanya.</p>
                        <button class="btn btn-outline-primary mt-2">Pilih Menu Ini</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm selection-card h-100 text-center py-4" id="card-realisasi" onclick="showForm('realisasi')">
                    <div class="card-body">
                        <i class="bi bi-graph-up-arrow icon-large text-success"></i>
                        <h4 class="card-title text-success fw-bold">Update Realisasi</h4>
                        <p class="card-text text-muted">Upload file Excel untuk memperbarui angka capaian/realisasi anggota.</p>
                        <button class="btn btn-outline-success mt-2">Pilih Menu Ini</button>
                    </div>
                </div>
            </div>
        </div>


        <div id="form-kegiatan" class="upload-section">
            <div class="card shadow border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-upload me-2"></i> Form Upload Kegiatan Baru</h5>
                </div>
                <div class="card-body bg-light">
                    <div class="alert alert-info border-info d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Aturan Main:</strong>
                            <ul class="mb-0 small ps-3">
                                <li>Nama Tim harus <b>SAMA PERSIS</b> dengan database dan <b>AKTIF</b>.</li>
                                <li>Anggota <b>WAJIB</b> terdaftar di Tim tersebut.</li>
                                <li>Format Tanggal: <b>YYYY-MM-DD</b>.</li>
                            </ul>
                        </div>
                    </div>

                    <form action="../proses/proses_import_lengkap.php" method="POST" enctype="multipart/form-data" class="mt-3 p-3 bg-white rounded border">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Pilih File Excel (.xlsx)</label>
                            <input type="file" name="file_excel" class="form-control form-control-lg" required accept=".xlsx, .xls">
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="../assets/template_lengkap.xlsx" class="text-decoration-none fw-bold">
                                <i class="bi bi-download me-1"></i> Download Template Kegiatan
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-cloud-upload-fill me-2"></i> Proses Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="form-realisasi" class="upload-section">
            <div class="card shadow border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i> Form Update Realisasi</h5>
                </div>
                <div class="card-body bg-light">
                    <div class="alert alert-success border-success bg-opacity-10 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                        <div>
                            <strong>Cara Kerja:</strong>
                            <span class="small d-block">Sistem akan mencari Kegiatan & Anggota yang cocok, lalu <b>MENIMPA</b> nilai realisasi di database dengan angka di Excel.</span>
                        </div>
                    </div>

                    <form action="../proses/proses_import_realisasi.php" method="POST" enctype="multipart/form-data" class="mt-3 p-3 bg-white rounded border">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Pilih File Excel (.xlsx)</label>
                            <input type="file" name="file_excel" class="form-control form-control-lg" required accept=".xlsx, .xls">
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="../assets/template_realisasi.xlsx" class="text-decoration-none fw-bold text-success">
                                <i class="bi bi-download me-1"></i> Download Template Realisasi
                            </a>
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-cloud-upload-fill me-2"></i> Proses Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Fungsi untuk menampilkan form yang dipilih
    function showForm(type) {
        // 1. Reset tampilan kartu
        document.getElementById('card-kegiatan').classList.remove('active-kegiatan');
        document.getElementById('card-realisasi').classList.remove('active-realisasi');
        
        // 2. Sembunyikan semua form
        document.getElementById('form-kegiatan').style.display = 'none';
        document.getElementById('form-realisasi').style.display = 'none';

        // 3. Aktifkan kartu & form yang dipilih
        if (type === 'kegiatan') {
            document.getElementById('card-kegiatan').classList.add('active-kegiatan');
            document.getElementById('form-kegiatan').style.display = 'block';
        } else if (type === 'realisasi') {
            document.getElementById('card-realisasi').classList.add('active-realisasi');
            document.getElementById('form-realisasi').style.display = 'block';
        }
    }

    // Cek apakah ada session PHP yang meminta tab tertentu dibuka otomatis
    // (Misal setelah submit ada error, biar user gak bingung formnya ketutup)
    document.addEventListener("DOMContentLoaded", function() {
        var activeForm = "<?= $active_form ?>";
        if (activeForm === 'realisasi') {
            showForm('realisasi');
        } else if (activeForm === 'kegiatan') {
            showForm('kegiatan');
        }
    });
</script>