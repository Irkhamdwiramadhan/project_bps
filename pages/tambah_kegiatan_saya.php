<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../../login.php');
    exit;
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .content-wrapper { min-height: 100vh; margin-left: 250px; padding: 30px; transition: 0.3s; }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }
    
    .card { 
        background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
        border: 1px solid #e2e8f0; max-width: 600px; margin: 0 auto; 
    }
    .card-header { padding: 20px 30px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .card-body { padding: 30px; }
    
    .form-label { font-weight: 600; color: #374151; margin-bottom: 8px; display: block; font-size: 0.9rem; }
    .form-control, .form-select { 
        width: 100%; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; 
        font-size: 0.95rem; transition: 0.2s; 
    }
    
    /* Style khusus input tanggal multi */
    input.flatpickr-input { background-color: #fff !important; cursor: pointer; }

    .btn-save { 
        background-color: #2563eb; color: white; padding: 12px 25px; border-radius: 8px; 
        font-weight: 600; border: none; cursor: pointer; width: 100%; margin-top: 20px; 
    }
    .btn-save:hover { background-color: #1d4ed8; }
    .btn-back { color: #64748b; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h2 style="margin:0; font-size:1.5rem; font-weight:800; color:#1e293b;">Lapor Kegiatan</h2>
            <a href="kegiatan_saya.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <div class="card-body">

            <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
                <div class="alert alert-danger">
                    <strong>Gagal:</strong> <?= htmlspecialchars($_GET['message']) ?>
                </div>
            <?php endif; ?>

            <form action="../proses/proses_tambah_kegiatan_saya.php" method="POST" id="formLaporan">
                
                <div class="mb-3" style="margin-bottom: 20px;">
                    <label class="form-label">Tanggal Kegiatan (Bisa Pilih Banyak) <span class="text-danger">*</span></label>
                    <div style="position: relative;">
                        <input type="text" name="tanggal_multi" id="tanggal_multi" class="form-control" placeholder="Pilih tanggal-tanggal kegiatan..." required>
                        <small style="color:#6b7280; font-size:0.8rem;">* Klik tanggal satu per satu untuk memilih beberapa hari.</small>
                    </div>
                </div>

                <div class="mb-3" style="margin-bottom: 20px;">
                    <label class="form-label">Jenis Kegiatan <span class="text-danger">*</span></label>
                    <select name="jenis_kegiatan" id="jenis_kegiatan" class="form-select" required>
                        <option value="">-- Pilih Jenis --</option>
                        <option value="Cuti">Cuti</option>
                        <option value="DL">DL (Dinas Luar)</option>
                        <option value="Translok">Translok</option>
                        <option value="Tugas Belajar">Tugas Belajar</option>
                        <option value="Rapat">Rapat</option>
                        <option value="Pelatihan">Pelatihan</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi Kegiatan <span class="text-danger">*</span></label>
                    <textarea name="uraian" id="uraian" class="form-control" rows="4" placeholder="Jelaskan detail kegiatan yang dilakukan..." required></textarea>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-paper-plane me-2"></i> Simpan Laporan
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script> 

<script>
    // 1. Inisialisasi Flatpickr
    flatpickr("#tanggal_multi", {
        mode: "multiple", 
        dateFormat: "Y-m-d",
        locale: "id", 
        conjunction: ", ", 
        defaultDate: "today"
    });

    // 2. Validasi Form sebelum Submit
    document.getElementById('formLaporan').addEventListener('submit', function(event) {
        let tanggal = document.getElementById('tanggal_multi').value;
        let jenis = document.getElementById('jenis_kegiatan').value;
        let uraian = document.getElementById('uraian').value.trim();

        if (tanggal === "" || jenis === "" || uraian === "") {
            event.preventDefault(); // Stop kirim data
            
            // Tampilkan Notifikasi Error
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap!',
                text: 'Harap isi Tanggal, Jenis Kegiatan, dan Deskripsi sebelum menyimpan.',
                confirmButtonColor: '#2563eb'
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>