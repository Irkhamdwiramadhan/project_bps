<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Validasi hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

// Ambil daftar tahun
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_item ORDER BY tahun DESC");
$daftar_tahun_anggaran = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun_anggaran[] = $row['tahun'];
    }
}
?>

<style>
:root {
    --primary-blue: #0A2E5D;
    --light-blue-bg: #f8f9fa;
}
.main-content { padding: 30px; background-color: var(--light-blue-bg); }
.section-title { font-size: 1.8rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 25px; }
.card {
    background: #fff; border: none; border-radius: 12px;
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.07); padding: 30px;
}
.form-label { font-weight: 600; color: #343a40; }
.btn-primary {
    background: var(--primary-blue); border: none; padding: 12px 25px;
    font-weight: 600; transition: all 0.2s;
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(10, 46, 93, 0.3); }

/* Gaya Selector Upload */
.upload-type-selector { display: flex; gap: 15px; margin-bottom: 25px; }
.upload-type-label {
    flex: 1; text-align: center; padding: 20px;
    border: 2px solid #dee2e6; border-radius: 8px; cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.upload-type-label i { font-size: 2rem; display: block; margin-bottom: 10px; }
.upload-type-label span { font-size: 1.1rem; font-weight: 600; }
.upload-type-input { display: none; }
.upload-type-input:checked + .upload-type-label {
    background-color: var(--primary-blue); color: #fff;
    border-color: var(--primary-blue);
    box-shadow: 0 4px 15px rgba(10, 46, 93, 0.2);
}

/* --- TAMBAHAN CSS UNTUK LOADING ANIMATION --- */
#loadingOverlay {
    display: none; /* Hidden default */
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(255, 255, 255, 0.95);
    z-index: 9999; /* Paling atas */
    justify-content: center;
    align-items: center;
    flex-direction: column;
    backdrop-filter: blur(5px);
}

.loading-icon-container {
    position: relative;
    width: 100px;
    height: 100px;
    margin-bottom: 20px;
}

.loading-icon-container i {
    font-size: 4rem;
    color: var(--primary-blue);
    animation: bounce 2s infinite;
}

.loading-text h3 { color: var(--primary-blue); font-weight: 700; margin-bottom: 10px; }
.loading-text p { color: #666; font-size: 1rem; margin: 0; }
.loading-spinner {
    width: 50px; height: 50px;
    border: 5px solid #e9ecef;
    border-top: 5px solid var(--primary-blue);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
    40% {transform: translateY(-20px);}
    60% {transform: translateY(-10px);}
}
</style>

<div id="loadingOverlay">
    <div class="text-center">
        <div class="loading-icon-container">
            <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="loading-text">
            <h3>Sedang Memproses Data...</h3>
            <div class="loading-spinner"></div>
            <p class="font-weight-bold">Mohon Tunggu, Proses ini memakan waktu 1-2 menit.</p>
            <p class="text-danger"><small>JANGAN MENUTUP ATAU ME-REFRESH HALAMAN INI</small></p>
        </div>
    </div>
</div>

<main class="main-content">
  <div class="container">
    <h2 class="section-title">Pusat Upload Data</h2>

    <?php if (isset($_SESSION['flash_message'])) : ?>
        <div class="alert alert-<?php echo $_SESSION['flash_message_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_message_type']);
        endif; 
    ?>

    <div class="card">
      <form id="uploadForm" action="" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label class="form-label">1. Pilih Jenis Data:</label>
            <div class="upload-type-selector">
                <input type="radio" class="upload-type-input" id="upload_anggaran" name="upload_type" value="anggaran" checked>
                <label class="upload-type-label" for="upload_anggaran">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Anggaran Utama</span>
                </label>

                <input type="radio" class="upload-type-input" id="upload_realisasi" name="upload_type" value="realisasi">
                <label class="upload-type-label" for="upload_realisasi">
                    <i class="fas fa-receipt"></i>
                    <span>Realisasi Bulanan</span>
                </label>
            </div>
        </div>

        <hr>
        <label class="form-label">2. Lengkapi Detail & Pilih File:</label>
        
        <div id="form-anggaran-fields">
            <div class="form-group">
                <label for="tahun_anggaran">Tahun Anggaran Baru</label>
                <input type="number" class="form-control" id="tahun_anggaran" name="tahun_anggaran" required min="2000" max="2100" value="<?= date('Y') ?>">
                <small class="form-text text-muted">Anda bisa mengisi tahun mana saja.</small>
            </div>
        </div>

        <div id="form-realisasi-fields" style="display: none;">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="tahun_realisasi">Tahun Anggaran</label>
                <select class="form-control" id="tahun_realisasi" name="tahun_realisasi">
                  <?php if (!empty($daftar_tahun_anggaran)): ?>
                    <?php foreach ($daftar_tahun_anggaran as $th): ?>
                        <option value="<?= $th ?>"><?= $th ?></option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>Belum ada data anggaran</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label for="bulan">Bulan Realisasi</label>
                <select class="form-control" id="bulan" name="bulan">
                  <?php for ($i = 1; $i <= 12; $i++): ?>
                      <option value="<?= $i ?>"><?= DateTime::createFromFormat('!m', $i)->format('F') ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
        </div>

        <div class="form-group mt-3">
          <label for="file_excel">File Excel</label>
          <div class="custom-file">
            <input type="file" class="custom-file-input" id="file_excel" name="file_excel" required accept=".xls, .xlsx">
            <label class="custom-file-label" for="file_excel">Pilih file...</label>
          </div>
        </div>
        
        <hr>
        <button type="submit" class="btn btn-primary btn-lg" id="btnSubmit">
          <i class="fas fa-upload mr-2"></i>Upload dan Proses
        </button>

      </form>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const uploadTypeRadios = document.querySelectorAll('input[name="upload_type"]');
    const anggaranFields = document.getElementById('form-anggaran-fields');
    const realisasiFields = document.getElementById('form-realisasi-fields');
    
    // Input elements
    const tahunAnggaranInput = document.getElementById('tahun_anggaran');
    const tahunRealisasiSelect = document.getElementById('tahun_realisasi');
    const bulanSelect = document.getElementById('bulan');
    const fileInput = document.getElementById('file_excel');
    
    // Loading elements
    const loadingOverlay = document.getElementById('loadingOverlay');
    const btnSubmit = document.getElementById('btnSubmit');

    function toggleFormFields() {
        const selectedType = document.querySelector('input[name="upload_type"]:checked').value;

        if (selectedType === 'realisasi') {
            uploadForm.action = '../proses/proses_realisasi.php';
            anggaranFields.style.display = 'none';
            realisasiFields.style.display = 'block';
            
            tahunAnggaranInput.name = ''; 
            tahunRealisasiSelect.name = 'tahun';
            bulanSelect.required = true;
            tahunRealisasiSelect.required = true;

        } else { 
            uploadForm.action = '../proses/proses_tambah_data_master.php';
            anggaranFields.style.display = 'block';
            realisasiFields.style.display = 'none';
            
            tahunAnggaranInput.name = 'tahun';
            tahunRealisasiSelect.name = '';
            bulanSelect.required = false;
            tahunRealisasiSelect.required = false;
        }
    }

    uploadTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleFormFields);
    });

    toggleFormFields();

    // Nama File
    document.querySelector('.custom-file-input').addEventListener('change', function(e) {
        var fileName = e.target.files[0].name;
        e.target.nextElementSibling.innerText = fileName;
    });

    // --- LOGIKA UNTUK MEMUNCULKAN LOADING ANIMASI ---
    uploadForm.addEventListener('submit', function(e) {
        // Cek apakah file sudah dipilih
        if (fileInput.files.length === 0) {
            e.preventDefault(); // Jangan submit jika kosong (biarkan validasi HTML jalan)
            alert('Silakan pilih file Excel terlebih dahulu.');
            return;
        }

        // Jika valid, munculkan overlay
        loadingOverlay.style.display = 'flex';
        
        // Opsional: Ubah teks tombol agar user tahu sedang diproses
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mengupload...';
        btnSubmit.disabled = true; // Cegah klik ganda
        
        // Form akan lanjut submit secara normal ke backend
    });
});
</script>

<?php include '../includes/footer.php'; ?>