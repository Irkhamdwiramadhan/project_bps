<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'admin_tu', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// Ambil daftar tahun unik untuk form pemilihan
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_item ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
// Jika tidak ada data, gunakan tahun sekarang sebagai default
if (empty($daftar_tahun)) {
    $daftar_tahun[] = date('Y');
}
?>

<style>
:root {
    --primary-blue: #0A2E5D;
    --secondary-blue: #4A90E2;
    --light-bg: #F7F9FC;
    --border-color: #E0E7FF;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --primary-blue-light: #e7eff8;
}

.main-content {
    background-color: var(--light-bg);
}

.form-container-modern {
    max-width: 600px;
    margin: 40px auto;
}

.card-download {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.card-header-custom {
    background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
    color: white;
    padding: 25px;
    text-align: center;
}
.card-header-custom i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    opacity: 0.8;
}
.card-header-custom h4 {
    font-weight: 700;
    margin: 0;
}

.card-body-custom {
    padding: 30px 40px;
}

.form-step {
    position: relative;
    padding-left: 50px;
    margin-bottom: 25px;
}
.form-step::before {
    content: attr(data-step);
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 35px;
    height: 35px;
    background-color: var(--primary-blue-light);
    color: var(--primary-blue);
    border-radius: 50%;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.form-step label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    display: block;
}

/* Kustomisasi Dropdown */
select.form-control {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%234A90E2' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
    padding: 0.75rem 1rem;
    border-color: var(--border-color);
    box-shadow: none;
    transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
}
select.form-control:focus {
    border-color: var(--secondary-blue);
    box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
}

/* Kustomisasi Tombol Download */
.btn-download {
    background: var(--primary-blue);
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    padding: 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(10, 46, 93, 0.2);
}
.btn-download:hover {
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 7px 20px rgba(10, 46, 93, 0.3);
}
.btn-download:disabled {
    background-color: #bdc3c7;
    box-shadow: none;
    transform: none;
    cursor: not-allowed;
}
</style>

<main class="main-content">
<div class="container-fluid">
    <div class="form-container-modern">
        <div class="card card-download">
            <div class="card-header-custom">
                <i class="fas fa-cloud-download-alt"></i>
                <h4>Menu Unduh Laporan</h4>
            </div>
            <div class="card-body card-body-custom">
                <form id="cetakForm" action="" method="GET" target="_blank">
                    
                    <div class="form-step" data-step="1">
                        <label for="laporan">Jenis Laporan</label>
                        <select class="form-control" id="laporan" name="laporan" required>
                            <option value="">-- Pilih Jenis Laporan --</option>
                            <option value="rpd">1. Laporan RPD</option>
                            <option value="realisasi_saja">2. Laporan Realisasi Saja</option>
                            <option value="realisasi_gabungan">3. Laporan Realisasi vs RPD</option>
                        </select>
                    </div>

                    <div class="form-step" data-step="2">
                        <label for="tahun">Tahun Anggaran</label>
                        <select class="form-control" id="tahun" name="tahun" required>
                            <?php foreach ($daftar_tahun as $thn) : ?>
                                <option value="<?= htmlspecialchars($thn) ?>" <?= ($thn == date('Y')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($thn) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-step" data-step="3">
                        <label for="format">Format Laporan</label>
                        <select class="form-control" id="format" name="format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                    
                    <div class="mt-4 pt-3">
                        <button type="submit" id="downloadBtn" class="btn btn-download btn-block">
                            <i class="fas fa-download mr-2"></i>Unduh Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cetakForm = document.getElementById('cetakForm');
    const laporanSelect = document.getElementById('laporan');
    const formatSelect = document.getElementById('format');
    const downloadBtn = document.getElementById('downloadBtn');

    // Pemetaan dari value laporan ke nama file dasar
    const fileMap = {
        'rpd': 'cetak_rpd',
        'realisasi_saja': 'cetak_hanya_realisasi',
        'realisasi_gabungan': 'cetak_realisasi'
    };

    function updateFormAction() {
        const jenisLaporan = laporanSelect.value;
        const format = formatSelect.value;
        
        if (jenisLaporan && format) {
            const baseFileName = fileMap[jenisLaporan];
            const formatSuffix = (format === 'excel') ? '_excel' : '_pdf';
            const fullActionPath = `../proses/${baseFileName}${formatSuffix}.php`;
            
            cetakForm.action = fullActionPath;
            downloadBtn.disabled = false;
        } else {
            cetakForm.action = '';
            downloadBtn.disabled = true;
        }
    }

    // Panggil fungsi saat ada perubahan pada salah satu dropdown
    laporanSelect.addEventListener('change', updateFormAction);
    formatSelect.addEventListener('change', updateFormAction);
    
    // Panggil fungsi saat halaman pertama kali dimuat untuk inisialisasi
    updateFormAction();
});
</script>

<?php include '../includes/footer.php'; ?>