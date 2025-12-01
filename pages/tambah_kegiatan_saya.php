<?php
session_start();
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../../login.php');
    exit;
}
?>

<style>
    /* Tema Warna: Indigo Productivity */
    :root { 
        --primary-indigo: #4338ca; 
        --light-indigo: #6366f1; 
        --bg-color: #eef2ff;
    }
    
    body { background: var(--bg-color); font-family: 'Poppins', sans-serif; }
    
    .content-wrapper { 
        margin-left: 250px; padding: 40px; 
        display: flex; justify-content: center; align-items: center; 
        min-height: 100vh; 
    }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }

    /* Card Modern */
    .modern-card {
        background: #fff; border-radius: 24px; 
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        width: 100%; max-width: 1100px; 
        display: flex; overflow: hidden;
    }

    /* Panel Kiri (Visual) */
    .left-panel {
        flex: 1; 
        background: linear-gradient(135deg, var(--primary-indigo) 0%, var(--light-indigo) 100%);
        padding: 50px; color: white; 
        display: flex; flex-direction: column; justify-content: center;
        position: relative; min-width: 320px; text-align: center;
    }
    /* Dekorasi Lingkaran */
    .left-panel::before { content:''; position:absolute; top:-60px; left:-60px; width:250px; height:250px; background:rgba(255,255,255,0.1); border-radius:50%; }
    .left-panel::after { content:''; position:absolute; bottom:-40px; right:-40px; width:180px; height:180px; background:rgba(255,255,255,0.08); border-radius:50%; }
    
    .left-panel h2 { font-weight: 800; font-size: 2.2rem; margin-bottom: 15px; }
    .left-panel p { opacity: 0.9; font-size: 1rem; line-height: 1.6; }
    .illustration { font-size: 4.5rem; margin-bottom: 25px; opacity: 0.95; }

    /* Panel Kanan (Form) */
    .right-panel { flex: 1.5; padding: 50px; background: #fff; }
    
    .form-header { 
        display: flex; justify-content: space-between; align-items: center; 
        margin-bottom: 30px; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px; 
    }
    .form-title { font-size: 1.4rem; font-weight: 700; color: #1e1b4b; }
    
    /* Styling Input */
    .form-label { font-weight: 600; font-size: 0.85rem; color: #4b5563; margin-bottom: 6px; display: block; }
    .form-control, .form-select { 
        border-radius: 12px; padding: 12px 16px; 
        border: 1px solid #e0e7ff; background: #fafafe; 
        font-size: 0.95rem; transition: 0.3s;
    }
    .form-control:focus { 
        background: #fff; border-color: var(--light-indigo); 
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15); outline: none; 
    }
    
    textarea.form-control { resize: none; }

    /* Tombol Simpan */
    .btn-save {
        background: var(--primary-indigo); color: white; width: 100%; 
        padding: 14px; border-radius: 12px; font-weight: 700; 
        border: none; transition: 0.3s; margin-top: 25px;
        box-shadow: 0 4px 15px rgba(67, 56, 202, 0.3);
    }
    .btn-save:hover { background: #3730a3; transform: translateY(-2px); }

    /* Checkbox Waktu Selesai */
    .time-check-wrapper { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
    
    @media (max-width: 991px) { 
        .modern-card { flex-direction: column; } 
        .content-wrapper { margin-left: 0; padding: 20px; } 
        .left-panel { padding: 40px 20px; min-height: 220px; }
        .right-panel { padding: 30px 20px; }
    }
</style>

<div class="content-wrapper">
    <div class="modern-card">
        
       

        <!-- KANAN: Form -->
        <div class="right-panel">
            <div class="form-header">
                <div class="form-title">Isi Kegiatan Saya</div>
                <a href="kegiatan_saya.php" class="text-muted text-decoration-none small fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>

            <form action="../proses/proses_tambah_kegiatan_saya.php" method="POST">
                
                <!-- Tanggal & Jenis -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Hari / Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Kegiatan</label>
                        <input class="form-control" list="jenisOptions" name="jenis_kegiatan" placeholder="Pilih atau ketik..." required>
                        <datalist id="jenisOptions">
                            <option value="Sabtu - Minggu">
                            <option value="Libur Nasional">
                            <option value="Cuti Bersama">
                            <option value="Cuti Pribadi">
                            <option value="Izin Tidak Masuk">
                            <option value="Sakit">
                            <option value="Tanpa Keterangan">
                            <option value="Rapat Dinas">
                            <option value="Upacara">
                            <option value="Pelatihan">
                            <option value="Dinas Luar">
                            <option value="Masuk Kerja">
                              <option value="Tugas Belajar">
                            <option value="KSA">
                            <option value="susenas">
                            <option value="sakernas">

                        </datalist>
                    </div>
                </div>

                

                <button type="submit" class="btn-save">
                    <i class="fas fa-save me-2"></i> Simpan Log Harian
                </button>

            </form>
        </div>
    </div>
</div>

<script>
    function toggleSelesai() {
        const check = document.getElementById('check_selesai');
        const timeInput = document.getElementById('input_jam_selesai');
        const textInput = document.getElementById('text_jam_selesai');

        if (check.checked) {
            timeInput.style.display = 'none';
            timeInput.disabled = true;
            textInput.style.display = 'block';
            textInput.disabled = false;
            textInput.name = 'jam_selesai';
        } else {
            timeInput.style.display = 'block';
            timeInput.disabled = false;
            textInput.style.display = 'none';
            textInput.disabled = true;
            textInput.name = '';
        }
    }
</script>

<?php include '../includes/footer.php'; ?>