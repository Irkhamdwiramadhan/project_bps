<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    body { background: #f0f2f5; font-family: 'Inter', 'Poppins', sans-serif; }
    
    .content-wrapper { 
        margin-left: 250px; padding: 40px; 
        display: flex; justify-content: center; align-items: center; 
        min-height: 100vh; 
    }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }
    
    /* Card Modern Styling */
    .modern-card {
        background: #ffffff; 
        border-radius: 20px; 
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        width: 100%; max-width: 1000px; 
        display: flex; overflow: hidden;
        border: 1px solid #eef2f6;
    }
    
    /* Panel Kiri (Visual) */
    .left-panel {
        flex: 1; 
        background: linear-gradient(150deg, #059669 0%, #064e3b 100%);
        padding: 50px; 
        color: white; 
        display: flex; flex-direction: column; justify-content: center;
        position: relative; 
        min-width: 320px;
        text-align: center;
    }
    .left-panel::after {
        content: ''; position: absolute; bottom: -50px; left: -50px;
        width: 200px; height: 200px; background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    .left-panel h2 { font-weight: 800; font-size: 2.2rem; margin-bottom: 15px; letter-spacing: -0.5px; }
    .left-panel p { opacity: 0.85; line-height: 1.6; font-size: 1rem; margin-bottom: 30px; }
    .left-icon { font-size: 4rem; margin-bottom: 20px; opacity: 0.9; }

    /* Panel Kanan (Form) */
    .right-panel { 
        flex: 2; padding: 50px; 
        background-color: #fff;
    }
    
    .form-header {
        margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #f3f4f6;
        display: flex; justify-content: space-between; align-items: center;
    }
    .form-header h4 { font-weight: 700; color: #111827; margin: 0; }
    
    .form-label { 
        font-weight: 600; font-size: 0.85rem; color: #374151; 
        margin-bottom: 8px; display: block; 
    }
    
    .form-control, .form-select { 
        border-radius: 12px; padding: 12px 16px; 
        border: 1px solid #e5e7eb; background: #f9fafb;
        font-size: 0.95rem; transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus { 
        background: #fff; border-color: #059669; 
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); 
        outline: none;
    }
    
    /* Tombol Simpan */
    .btn-save {
        background: linear-gradient(to right, #059669, #10b981); 
        color: white; width: 100%; padding: 14px; 
        border-radius: 12px; font-weight: 700; border: none; 
        transition: 0.3s; margin-top: 10px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(16, 185, 129, 0.4); }

    /* Checkbox Kustom untuk Waktu Selesai */
    .time-option {
        display: flex; align-items: center; gap: 10px; margin-top: 8px;
    }
    .form-check-input:checked { background-color: #059669; border-color: #059669; }

    @media (max-width: 991px) { 
        .modern-card { flex-direction: column; } 
        .content-wrapper { margin-left: 0; padding: 20px; } 
        .left-panel { padding: 40px 20px; min-height: 200px; }
        .right-panel { padding: 30px 20px; }
    }
</style>

<div class="content-wrapper">
    <div class="modern-card">
        
   

        <!-- KANAN: Formulir -->
        <div class="right-panel">
            <div class="form-header">
                <h4>Formulir Kegiatan</h4>
                <a href="kegiatan_pegawai.php" class="text-muted text-decoration-none small fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>

            <form action="../proses/proses_tambah_kegiatan_pegawai.php" method="POST">
                
                <!-- Baris 1: Tanggal & Jenis -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Hari / Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jenis Aktivitas</label>
                        <!-- Datalist untuk fleksibilitas -->
                        <input class="form-control" list="jenisOptions" name="jenis_aktivitas" placeholder="Pilih atau ketik sendiri..." required autocomplete="off">
                        <datalist id="jenisOptions">
                            <option value="Perjalanan Dinas">
                            <option value="Transportasi Lokal">
                            <option value="Rapat / Meeting">
                            <option value="Cuti">
                            <option value="Lainnya">
                        </datalist>
                    </div>
                </div>

                <!-- Nama Aktivitas -->
                <div class="mb-4">
                    <label class="form-label">Uraian Kegiatan</label>
                    <input type="text" name="aktivitas" class="form-control" placeholder="Contoh: Melakukan Survei Harga di Pasar..." required>
                </div>

                <!-- Waktu -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Waktu Mulai</label>
                        <input type="time" name="waktu_mulai" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Waktu Berakhir</label>
                        
                        <!-- Input Group untuk Waktu -->
                        <div class="position-relative">
                            <!-- Input Time Biasa -->
                            <input type="time" name="waktu_selesai" id="input_waktu_selesai" class="form-control" required>
                            
                            <!-- Input Text Hidden untuk nilai 'Selesai' -->
                            <input type="text" id="input_text_selesai" class="form-control" value="Selesai" readonly style="display: none; background-color: #e5e7eb; color: #059669; font-weight: bold; text-align: center;">
                        </div>

                        <!-- Checkbox Opsi Selesai -->
                        <div class="time-option">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="check_selesai" onchange="toggleWaktuSelesai()">
                                <label class="form-check-label small text-muted" for="check_selesai">
                                    Sampai Selesai
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tempat & Tim -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Tempat / Lokasi</label>
                        <input type="text" name="tempat" class="form-control" placeholder="Nama tempat/lokasi" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tim Kerja</label>
                        <input type="text" name="nama_tim" class="form-control" placeholder="Nama Tim (Manual)" required>
                    </div>
                </div>

                <!-- Peserta -->
                <div class="row mb-4">
                    <div class="col-md-9">
                        <label class="form-label">Daftar Peserta</label>
                        <textarea name="nama_peserta" class="form-control" rows="2" placeholder="Tulis nama peserta dipisahkan koma..." required></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Jumlah peserta</label>
                        <input type="number" name="jumlah_peserta" class="form-control" value="1" min="1" required style="text-align: center;">
                    </div>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save me-2"></i> Simpan Kegiatan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Fungsi untuk mengubah input waktu menjadi teks "Selesai"
    function toggleWaktuSelesai() {
        const checkBox = document.getElementById('check_selesai');
        const timeInput = document.getElementById('input_waktu_selesai');
        const textInput = document.getElementById('input_text_selesai');

        if (checkBox.checked) {
            // Jika dicentang: Sembunyikan time, Tampilkan text "Selesai", Nonaktifkan time agar tidak terkirim
            timeInput.style.display = 'none';
            timeInput.disabled = true;
            
            textInput.style.display = 'block';
            textInput.disabled = false;
            textInput.name = 'waktu_selesai'; // Ambil alih nama field
        } else {
            // Jika tidak dicentang: Kembalikan ke input time
            timeInput.style.display = 'block';
            timeInput.disabled = false;
            
            textInput.style.display = 'none';
            textInput.disabled = true;
            textInput.name = ''; // Lepas nama field
        }
    }
</script>

<?php include '../includes/footer.php'; ?>