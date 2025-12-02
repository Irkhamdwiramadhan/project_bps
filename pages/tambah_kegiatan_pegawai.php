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

// Ambil Data Tim dari Database
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .content-wrapper { min-height: 100vh; margin-left: 250px; padding: 30px; transition: 0.3s; }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }
    
    .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; max-width: 800px; margin: 0 auto; }
    .card-header { padding: 20px 30px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .card-body { padding: 30px; }
    
    .form-label { font-weight: 600; color: #374151; margin-bottom: 8px; display: block; font-size: 0.9rem; }
    .form-control, .form-select { width: 100%; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; transition: 0.2s; }
    .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
    .form-control[disabled] { background-color: #f1f5f9; cursor: not-allowed; }
    .form-control.is-invalid, .form-select.is-invalid { border-color: #dc2626; background-color: #fef2f2; }

    .btn-save { background-color: #2563eb; color: white; padding: 12px 25px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; width: 100%; margin-top: 20px; transition: 0.2s; }
    .btn-save:hover { background-color: #1d4ed8; }
    .btn-save:disabled { background-color: #94a3b8; cursor: not-allowed; }
    .btn-back { color: #64748b; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .checkbox-wrapper { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 0.9rem; color: #64748b; cursor: pointer; }
    .checkbox-wrapper input { cursor: pointer; width: 16px; height: 16px; accent-color: #2563eb; }

    /* Layout Flex Responsive */
    .row-flex { display: flex; gap: 20px; margin-bottom: 20px; }
    .col-flex-1 { flex: 1; }
    .col-flex-half { flex: 0.5; }

    /* RESPONSIVE MEDIA QUERIES */
    @media (max-width: 768px) {
        .content-wrapper { margin-left: 0; padding: 15px; }
        .card-header { padding: 15px 20px; }
        .card-body { padding: 20px; }
        
        /* Stack form rows vertically on mobile */
        .row-flex { flex-direction: column; gap: 15px; }
        .col-flex-1, .col-flex-half { width: 100%; flex: none; }
        
        /* Adjust font sizes */
        .card-header h2 { font-size: 1.25rem !important; }
        .btn-back { font-size: 0.9rem; }
    }
</style>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h2 style="margin:0; font-size:1.5rem; font-weight:800; color:#1e293b;">Ajukan Kegiatan</h2>
            <a href="kegiatan_pegawai.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>
        <div class="card-body">

            <?php if (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
                <div class="alert alert-danger">
                    <strong><i class="fas fa-exclamation-circle"></i> Gagal:</strong> 
                    <?= htmlspecialchars($_GET['message']) ?>
                </div>
            <?php endif; ?>

            <form action="../proses/proses_tambah_kegiatan_pegawai.php" method="POST" id="formKegiatan">
                
                <div class="row-flex">
                    <div class="col-flex-1">
                        <label class="form-label">Nama Aktivitas / Kegiatan</label>
                        <input type="text" name="aktivitas" class="form-control" placeholder="Contoh: Rapat Evaluasi Tim" required>
                    </div>
                    <div class="col-flex-1">
                        <label class="form-label">Jenis Aktivitas (Menentukan Lokasi)</label>
                        <select name="jenis_aktivitas" id="jenis_aktivitas" class="form-select" required onchange="toggleLokasi()">
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Pertemuan Dalam Kantor">Pertemuan Dalam Kantor</option>
                            <option value="Pertemuan Luar Kantor">Pertemuan Luar Kantor</option>
                            <option value="Aktivitas Lainnya">Aktivitas Lainnya (WFH, Cuti, dll)</option>
                        </select>
                    </div>
                </div>

                <div class="row-flex">
                    <div class="col-flex-1">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-flex-half">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="waktu_mulai" id="waktu_mulai" class="form-control" required>
                    </div>
                    <div class="col-flex-half">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="waktu_selesai" id="waktu_selesai" class="form-control" required>
                        
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="is_selesai" id="is_selesai" value="1" onclick="toggleJamSelesai()">
                            Sampai Selesai
                        </label>
                    </div>
                </div>

                <hr style="border-top:1px dashed #cbd5e1; margin: 25px 0;">

                <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <label class="form-label" style="color:#2563eb;">Lokasi Kegiatan</label>
                    
                    <div id="input-internal" style="display:none;">
                        <label class="form-label">Pilih Ruangan (Cek Bentrok)</label>
                        <select name="tempat_internal" id="tempat_internal" class="form-select">
                            <option value="">-- Pilih Ruangan --</option>
                            <option value="Ruang Rapat Utama">Ruang Rapat Utama</option>
                            <option value="Ruang Rapat Kecil">Ruang Rapat Kecil</option>
                            <option value="Aula BPS">Aula BPS</option>
                            <option value="Ruang Teknis">Ruang Teknis</option>
                            <option value="Ruang Garda">Ruang Garda</option>
                            <option value="Parkiran BPS">Parkiran BPS</option>
                        </select>
                        <div id="room-feedback" style="font-size: 0.85rem; margin-top: 5px;"></div>
                        <small style="color:#64748b; display:block; margin-top:5px;">* Sistem otomatis menolak jika ruangan terpakai di jam yang sama.</small>
                    </div>

                    <div id="input-external" style="display:none;">
                        <label class="form-label">Nama Tempat / Lokasi</label>
                        <input type="text" name="tempat_external" id="tempat_external" class="form-control" placeholder="Tuliskan lokasi lengkap...">
                    </div>
                    
                    <div id="lokasi-placeholder" style="color:#64748b; font-style:italic;">
                        Silakan pilih <strong>Jenis Aktivitas</strong> terlebih dahulu untuk menentukan lokasi.
                    </div>
                </div>

                <div class="row-flex">
                    <div class="col-flex-1">
                        <label class="form-label">Tim Kerja</label>
                        <select name="tim_kerja_id" class="form-select">
                            <option value="">-- Pilih Tim (Opsional) --</option>
                            <?php foreach ($tim_list as $tim): ?>
                                <option value="<?= htmlspecialchars($tim['nama_tim']) ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-flex-half">
                        <label class="form-label">Jumlah Peserta</label>
                        <input type="number" name="jumlah_peserta" class="form-control" min="1" placeholder="Jml">
                    </div>
                </div>

                 <div style="margin-top: 0px;">
                    <label class="form-label">Daftar Peserta</label>
                    <textarea name="peserta_ids" class="form-control" rows="2" placeholder="Tulis nama peserta lain jika ada..."></textarea>
                </div>

                <button type="submit" id="btn-submit" class="btn-save">
                    <i class="fas fa-paper-plane me-2"></i> Ajukan Kegiatan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // --- 1. Logic Tampilan Lokasi ---
    function toggleLokasi() {
        const jenis = document.getElementById('jenis_aktivitas').value;
        const divInternal = document.getElementById('input-internal');
        const divExternal = document.getElementById('input-external');
        const divPlaceholder = document.getElementById('lokasi-placeholder');
        
        const inputInternal = document.getElementById('tempat_internal');
        const inputExternal = document.getElementById('tempat_external');

        // Reset tampilan
        divInternal.style.display = 'none';
        divExternal.style.display = 'none';
        divPlaceholder.style.display = 'none';
        inputInternal.required = false;
        inputExternal.required = false;
        
        // Reset error state saat ganti jenis
        resetValidationState();

        if (jenis === 'Pertemuan Dalam Kantor') {
            divInternal.style.display = 'block';
            inputInternal.required = true;
        } 
        else if (jenis === 'Pertemuan Luar Kantor' || jenis === 'Aktivitas Lainnya') {
            divExternal.style.display = 'block';
            inputExternal.required = true; 
        } 
        else {
            divPlaceholder.style.display = 'block';
        }
    }

    // Logic Checkbox Sampai Selesai
    function toggleJamSelesai() {
        const checkbox = document.getElementById('is_selesai');
        const inputSelesai = document.getElementById('waktu_selesai');

        if (checkbox.checked) {
            inputSelesai.value = ''; 
            inputSelesai.disabled = true; 
            inputSelesai.required = false;
            checkRoomAvailability(); // Trigger cek ulang
        } else {
            inputSelesai.disabled = false;
            inputSelesai.required = true;
        }
    }

    // --- 2. Logic Cek Ruangan Real-time (AJAX) ---
    const tanggalInp = document.getElementById('tanggal');
    const mulaiInp = document.getElementById('waktu_mulai');
    const selesaiInp = document.getElementById('waktu_selesai');
    const ruanganInp = document.getElementById('tempat_internal');
    const isSelesaiCb = document.getElementById('is_selesai');
    const btnSubmit = document.getElementById('btn-submit');
    const feedbackDiv = document.getElementById('room-feedback');

    [tanggalInp, mulaiInp, selesaiInp, ruanganInp].forEach(el => {
        el.addEventListener('change', checkRoomAvailability);
    });

    function checkRoomAvailability() {
        const jenis = document.getElementById('jenis_aktivitas').value;
        if (jenis !== 'Pertemuan Dalam Kantor') return;

        const tgl = tanggalInp.value;
        const mulai = mulaiInp.value;
        const ruangan = ruanganInp.value;
        
        let selesai = selesaiInp.value;
        if (isSelesaiCb.checked) selesai = '23:59'; 

        if (!tgl || !mulai || !selesai || !ruangan) {
            resetValidationState();
            return;
        }
        
        if (mulai >= selesai) {
            showError("Jam selesai harus lebih besar dari jam mulai.");
            return;
        }

        feedbackDiv.innerHTML = '<span style="color:#2563eb;"><i class="fas fa-spinner fa-spin"></i> Mengecek ketersediaan...</span>';
        btnSubmit.disabled = true;

        fetch('ajax_cek_ruangan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tanggal: tgl,
                mulai: mulai,
                selesai: selesai,
                ruangan: ruangan
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'bentrok') {
                const info = data.data;
                const jamStr = info.waktu_mulai.substring(0,5) + '-' + info.waktu_selesai.substring(0,5);
                
                showError(`<b>${ruangan} TERPAKAI!</b><br>Kegiatan: "${info.aktivitas}"<br>Jam: ${jamStr}`);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Ruangan Tidak Tersedia!',
                    html: `Ruangan <b>${ruangan}</b> sudah dibooking untuk:<br> "${info.aktivitas}" <br> pada jam <b>${jamStr}</b>`,
                    confirmButtonColor: '#2563eb'
                });

            } else {
                showSuccess(`✅ ${ruangan} Tersedia pada jam tersebut.`);
            }
        })
        .catch(err => {
            console.error(err);
            feedbackDiv.innerHTML = '<span style="color:red;">Gagal koneksi server.</span>';
        });
    }

    function showError(msg) {
        ruanganInp.classList.add('is-invalid');
        feedbackDiv.innerHTML = `<span style="color:#dc2626;">${msg}</span>`;
        btnSubmit.disabled = true;
    }

    function showSuccess(msg) {
        ruanganInp.classList.remove('is-invalid');
        ruanganInp.style.borderColor = '#10b981';
        feedbackDiv.innerHTML = `<span style="color:#10b981; font-weight:bold;">${msg}</span>`;
        btnSubmit.disabled = false;
    }

    function resetValidationState() {
        ruanganInp.classList.remove('is-invalid');
        ruanganInp.style.borderColor = '#cbd5e1';
        feedbackDiv.innerHTML = '';
        btnSubmit.disabled = false;
    }
</script>

<?php include '../includes/footer.php'; ?>