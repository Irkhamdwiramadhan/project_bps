<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Validasi hak akses, hanya untuk admin
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

// Ambil daftar tahun yang SUDAH ADA data anggarannya, untuk filter di form Realisasi
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

/* Gaya untuk pilihan jenis upload yang modern */
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
</style>

<main class="main-content">
  <div class="container">
    <h2 class="section-title">Pusat Upload Data</h2>

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
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="fas fa-upload mr-2"></i>Upload dan Proses
        </button>

      </form>
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
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const uploadTypeRadios = document.querySelectorAll('input[name="upload_type"]');
    
    const anggaranFields = document.getElementById('form-anggaran-fields');
    const realisasiFields = document.getElementById('form-realisasi-fields');
    
    const tahunAnggaranInput = document.getElementById('tahun_anggaran');
    const tahunRealisasiSelect = document.getElementById('tahun_realisasi');
    const bulanSelect = document.getElementById('bulan');

    function toggleFormFields() {
        const selectedType = document.querySelector('input[name="upload_type"]:checked').value;

        if (selectedType === 'realisasi') {
            // Tampilkan form Realisasi
            uploadForm.action = '../proses/proses_realisasi.php';
            anggaranFields.style.display = 'none';
            realisasiFields.style.display = 'block';
            
            // Atur input mana yang wajib diisi
            tahunAnggaranInput.name = ''; // Kosongkan nama agar tidak terkirim
            tahunRealisasiSelect.name = 'tahun';
            bulanSelect.required = true;
            tahunRealisasiSelect.required = true;

        } else { // Anggaran
            // Tampilkan form Anggaran
            uploadForm.action = '../proses/proses_tambah_data_master.php';
            anggaranFields.style.display = 'block';
            realisasiFields.style.display = 'none';
            
            // Atur input mana yang wajib diisi
            tahunAnggaranInput.name = 'tahun';
            tahunRealisasiSelect.name = '';
            bulanSelect.required = false;
            tahunRealisasiSelect.required = false;
        }
    }

    // Tambahkan listener ke setiap radio button
    uploadTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleFormFields);
    });

    // Panggil fungsi saat halaman pertama kali dimuat
    toggleFormFields();

    // Skrip untuk menampilkan nama file
    document.querySelector('.custom-file-input').addEventListener('change', function(e) {
        var fileName = e.target.files[0].name;
        e.target.nextElementSibling.innerText = fileName;
    });
});
</script>

<?php include '../includes/footer.php'; ?>