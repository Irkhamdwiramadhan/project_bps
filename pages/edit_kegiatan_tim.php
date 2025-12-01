<?php
session_start();
include '../includes/koneksi.php';

// ... (Bagian Cek Login & Role sama seperti sebelumnya) ...
// ... (Pastikan Anda menyalin bagian Cek Login dari kode sebelumnya) ...

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: ../login.php'); exit; }
$user_roles = $_SESSION['user_role'] ?? []; 
$allowed = ['super_admin', 'ketua_tim'];
$access = false; 
foreach ($user_roles as $r) { if(in_array($r, $allowed)) $access = true; }
if (!$access) { header('Location: kegiatan_tim.php'); exit; }

$id_kegiatan = $_GET['id'] ?? null;
// ... (Validasi ID sama) ...

// Ambil Data Kegiatan
$stmt = $koneksi->prepare("SELECT * FROM kegiatan WHERE id = ?");
$stmt->bind_param("i", $id_kegiatan);
$stmt->execute();
$kegiatan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// REVISI: Ambil target DAN realisasi per anggota
$kegiatan_anggota = [];
$sql_anggota_terlibat = "SELECT ka.anggota_id, ka.target_anggota, ka.realisasi_anggota FROM kegiatan_anggota ka WHERE ka.kegiatan_id = ?";
$stmt_anggota = $koneksi->prepare($sql_anggota_terlibat);
$stmt_anggota->bind_param("i", $id_kegiatan);
$stmt_anggota->execute();
$result_anggota = $stmt_anggota->get_result();
while ($row = $result_anggota->fetch_assoc()) {
    $kegiatan_anggota[] = $row;
}
$stmt_anggota->close();

// ... (Ambil Tim & Anggota Tim sama seperti sebelumnya) ...
$tim_list = [];
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
if ($result_tim->num_rows > 0) { while($row = $result_tim->fetch_assoc()) { $tim_list[] = $row; } }

$anggota_tim_saat_ini = [];
$tim_id_kegiatan = $kegiatan['tim_id'];
$sql_anggota_tim = "(SELECT at.id, p.nama AS nama_lengkap FROM anggota_tim AS at JOIN pegawai AS p ON at.member_id = p.id WHERE at.member_type = 'pegawai' AND at.tim_id = ?) UNION ALL (SELECT at.id, m.nama_lengkap FROM anggota_tim AS at JOIN mitra AS m ON at.member_id = m.id WHERE at.member_type = 'mitra' AND at.tim_id = ?) ORDER BY nama_lengkap ASC";
$stmt_anggota_tim = $koneksi->prepare($sql_anggota_tim);
$stmt_anggota_tim->bind_param("ii", $tim_id_kegiatan, $tim_id_kegiatan);
$stmt_anggota_tim->execute();
$res_at = $stmt_anggota_tim->get_result();
if ($res_at) { while($r = $res_at->fetch_assoc()) { $anggota_tim_saat_ini[] = $r; } }

// AJAX Handler (Sama)
if (isset($_GET['action']) && $_GET['action'] == 'get_anggota') {
    // ... (Copy paste logika AJAX yang sama dari file sebelumnya) ...
    // Saya singkat disini untuk fokus ke perubahan UI
    header('Content-Type: application/json');
    $tid = (int)$_GET['tim_id'];
    $al = [];
    $sql = "(SELECT at.id, p.nama AS nama_lengkap FROM anggota_tim AS at JOIN pegawai AS p ON at.member_id = p.id WHERE at.member_type = 'pegawai' AND at.tim_id = ?) UNION ALL (SELECT at.id, m.nama_lengkap FROM anggota_tim AS at JOIN mitra AS m ON at.member_id = m.id WHERE at.member_type = 'mitra' AND at.tim_id = ?) ORDER BY nama_lengkap ASC";
    $st = $koneksi->prepare($sql); $st->bind_param("ii", $tid, $tid); $st->execute(); $rs = $st->get_result();
    while($r = $rs->fetch_assoc()) $al[] = $r;
    echo json_encode($al); exit;
}

include '../includes/header.php'; include '../includes/sidebar.php';
?>

<style>
    

        :root {

            --primary: #4361ee;

            --primary-hover: #3a56d4;

            --secondary: #6c757d;

            --success: #10b981;

            --danger: #ef4444;

            --bg-body: #f3f4f6;

            --bg-card: #ffffff;

            --border: #e5e7eb;

            --text-main: #111827;

            --text-muted: #6b7280;

        }



        body {

            font-family: 'Inter', sans-serif;

            background-color: var(--bg-body);

            color: var(--text-main);

        }



        .form-container {

            max-width: 950px;

            margin: 2rem auto;

            padding: 0 1rem;

        }



        .card {

            background-color: var(--bg-card);

            border-radius: 16px;

            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);

            border: 1px solid var(--border);

            overflow: hidden;

        }



        .card-header {

            background-color: #fff;

            padding: 1.5rem 2rem;

            border-bottom: 1px solid var(--border);

            display: flex;

            justify-content: space-between;

            align-items: center;

        }



        .card-header h2 {

            font-size: 1.25rem;

            font-weight: 700;

            color: var(--primary);

            margin: 0;

            display: flex;

            align-items: center;

            gap: 10px;

        }



        .card-body {

            padding: 2rem;

        }



        /* Form Styles */

        .form-label {

            font-weight: 500;

            font-size: 0.9rem;

            color: var(--text-main);

            margin-bottom: 0.5rem;

            display: block;

        }



        .form-control, .form-select {

            width: 100%;

            padding: 0.6rem 1rem;

            font-size: 0.95rem;

            border: 1px solid var(--border);

            border-radius: 8px;

            transition: all 0.2s;

            background-color: #fff;

        }



        .form-control:focus, .form-select:focus {

            border-color: var(--primary);

            outline: none;

            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);

        }



        .form-control[readonly] {

            background-color: #f9fafb;

            color: var(--text-muted);

            cursor: not-allowed;

        }



        /* Section Grouping */

        .form-section {

            margin-bottom: 2rem;

            padding-bottom: 2rem;

            border-bottom: 1px dashed var(--border);

        }

        

        .form-section:last-child {

            border-bottom: none;

            margin-bottom: 0;

            padding-bottom: 0;

        }



        .section-title {

            font-size: 0.85rem;

            text-transform: uppercase;

            letter-spacing: 0.05em;

            color: var(--text-muted);

            font-weight: 700;

            margin-bottom: 1rem;

        }



        /* Members List Styling */

        #anggota-container {

            background-color: #f8fafc;

            border: 1px solid var(--border);

            border-radius: 8px;

            padding: 1rem;

            max-height: 400px;

            overflow-y: auto;

        }



        .anggota-row {

            display: flex;

            gap: 10px;

            margin-bottom: 10px;

            align-items: center;

            background: white;

            padding: 8px;

            border-radius: 6px;

            border: 1px solid var(--border);

        }



        .anggota-row:last-child {

            margin-bottom: 0;

        }



        /* Buttons */

        .btn {

            padding: 0.6rem 1.25rem;

            border-radius: 8px;

            font-weight: 600;

            font-size: 0.9rem;

            cursor: pointer;

            border: none;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            text-decoration: none;

            transition: all 0.2s;

        }



        .btn-primary { background-color: var(--primary); color: white; }

        .btn-primary:hover { background-color: var(--primary-hover); }



        .btn-secondary { background-color: white; color: var(--text-main); border: 1px solid var(--border); }

        .btn-secondary:hover { background-color: #f3f4f6; }



        .btn-success { background-color: var(--success); color: white; width: 100%; justify-content: center; }

        .btn-success:hover { filter: brightness(90%); }



        .btn-icon-danger {

            background-color: #fee2e2;

            color: var(--danger);

            padding: 8px;

            border-radius: 6px;

        }

        .btn-icon-danger:hover { background-color: #fecaca; }

        

        /* Alert Styles */

        .alert {

            padding: 1rem;

            margin-bottom: 1.5rem;

            border-radius: 8px;

            font-size: 0.9rem;

            display: flex;

            align-items: center;

            gap: 10px;

        }

        .alert-danger {

            background-color: #fee2e2;

            color: #b91c1c;

            border: 1px solid #fecaca;

        }

        .alert-success {

            background-color: #d1fae5;

            color: #047857;

            border: 1px solid #a7f3d0;

        }



        /* Responsive */

        @media (min-width: 768px) {

            .row { display: flex; gap: 1.5rem; }

            .col-half { flex: 1; }

            .col-third { flex: 1; }

        }

    /* Tambahan CSS agar kolom input rapi */
    .anggota-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; padding: 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; }
    .anggota-select { flex: 2; }
    .anggota-input { flex: 1; } /* Untuk Target & Realisasi */
</style>

<div class="form-container">
    <div class="card">
        <div class="card-header">
            <h2>Edit Kegiatan (Detail Anggota)</h2>
        </div>
        <div class="card-body">
            <form action="../proses/proses_edit_kegiatan_tim.php" method="POST">
                <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">
                
                <div class="form-section">
                    <div class="row mb-3">
                        <div class="col-half">
                            <label class="form-label">Nama Kegiatan</label>
                            <input type="text" class="form-control" name="nama_kegiatan" value="<?= htmlspecialchars($kegiatan['nama_kegiatan']) ?>" required>
                        </div>
                         <div class="col-half">
                            <label class="form-label">Satuan</label>
                            <input type="text" class="form-control" name="satuan" value="<?= htmlspecialchars($kegiatan['satuan']) ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-half">
                             <label class="form-label">Tim</label>
                             <select class="form-select" id="tim_id" name="tim_id">
                                 <?php foreach ($tim_list as $tim): ?>
                                    <option value="<?= $tim['id'] ?>" <?= ($tim['id'] == $kegiatan['tim_id']) ? 'selected' : '' ?>><?= $tim['nama_tim'] ?></option>
                                 <?php endforeach; ?>
                             </select>
                        </div>
                        <div class="col-half">
                            <label class="form-label">Batas Waktu</label>
                            <input type="date" class="form-control" name="batas_waktu" value="<?= $kegiatan['batas_waktu'] ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section" style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                    <div class="row">
                        <div class="col-half">
                            <label class="form-label">Total Target (Otomatis)</label>
                            <input type="number" class="form-control" id="target_total" name="target" value="<?= $kegiatan['target'] ?>" readonly style="font-weight:bold;">
                        </div>
                        <div class="col-half">
                            <label class="form-label">Total Realisasi (Otomatis)</label>
                            <input type="number" class="form-control" id="realisasi_total" name="realisasi" value="<?= $kegiatan['realisasi'] ?>" readonly style="font-weight:bold; color: #10b981;">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">Rincian Target & Realisasi Anggota</div>
                    <div id="header-row" style="display:flex; gap:10px; margin-bottom:5px; font-size:0.85rem; font-weight:bold; color:#6b7280; padding:0 10px;">
                        <div style="flex:2;">Nama Anggota</div>
                        <div style="flex:1;">Target</div>
                        <div style="flex:1;">Realisasi</div>
                        <div style="width:40px;"></div>
                    </div>
                    <div id="anggota-container"></div>
                    <button type="button" id="tambah-anggota" class="btn btn-success mt-3">+ Tambah Anggota</button>
                </div>

                <div class="form-section">
                    <label class="form-label">Keterangan</label>
                    <textarea class="form-control" name="keterangan"><?= htmlspecialchars($kegiatan['keterangan']) ?></textarea>
                </div>

                <div style="text-align: right; margin-top: 2rem;">
                    <!-- tombol kembali -->
                    <a href="kegiatan_tim.php" class="btn btn-secondary mr-2"><i class="bi bi-arrow-left"></i> Kembali</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const initialAnggota = <?= json_encode($kegiatan_anggota) ?>;
let currentTeamMembers = <?= json_encode($anggota_tim_saat_ini) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('anggota-container');
    const totalTargetInput = document.getElementById('target_total');
    const totalRealisasiInput = document.getElementById('realisasi_total');
    const timSelect = document.getElementById('tim_id');

    // REVISI: Hitung Total Target DAN Total Realisasi
    const updateTotals = () => {
        let tTarget = 0;
        let tRealisasi = 0;
        
        container.querySelectorAll('.anggota-row').forEach(row => {
            const valTarget = parseFloat(row.querySelector('.input-target').value) || 0;
            const valReal = parseFloat(row.querySelector('.input-realisasi').value) || 0;
            tTarget += valTarget;
            tRealisasi += valReal;
        });
        
        totalTargetInput.value = tTarget.toFixed(2);
        totalRealisasiInput.value = tRealisasi.toFixed(2);
    };

    const addRow = (id = '', target = 0, realisasi = 0) => {
        const div = document.createElement('div');
        div.className = 'anggota-row';
        
        let opts = '<option value="">-- Pilih --</option>';
        currentTeamMembers.forEach(m => {
            opts += `<option value="${m.id}" ${m.id == id ? 'selected' : ''}>${m.nama_lengkap}</option>`;
        });

        div.innerHTML = `
            <select class="form-select anggota-select" name="anggota_id[]" required>${opts}</select>
            
            <input type="number" step="0.01" class="form-control anggota-input input-target" 
                   name="target_anggota[]" value="${target}" placeholder="Target" required>
            
            <input type="number" step="0.01" class="form-control anggota-input input-realisasi" 
                   name="realisasi_anggota[]" value="${realisasi}" placeholder="Realisasi" required>
            
            <button type="button" class="btn btn-icon-danger remove-row"><i class="bi bi-trash"></i></button>
        `;
        container.appendChild(div);
    };

    // Init Data
    if(initialAnggota.length) {
        initialAnggota.forEach(a => addRow(a.anggota_id, a.target_anggota, a.realisasi_anggota || 0));
    } else {
        addRow();
    }
    updateTotals();

    // Event Listeners
    document.getElementById('tambah-anggota').addEventListener('click', () => { addRow(); updateTotals(); });
    
    container.addEventListener('click', e => {
        if(e.target.closest('.remove-row')) {
            if(container.children.length > 1) {
                e.target.closest('.anggota-row').remove();
                updateTotals();
            } else { alert('Minimal 1 anggota'); }
        }
    });

    // REVISI: Listen perubahan di kedua input (target & realisasi)
    container.addEventListener('input', e => {
        if(e.target.matches('.input-target') || e.target.matches('.input-realisasi')) {
            updateTotals();
        }
    });
    
    // Logic Ganti Tim (Sama seperti sebelumnya, muat ulang anggota)
    timSelect.addEventListener('change', function() {
        const tid = this.value;
        fetch(`?action=get_anggota&tim_id=${tid}`).then(r=>r.json()).then(d=>{
            currentTeamMembers = d;
            container.innerHTML=''; addRow(); updateTotals();
        });
    });
});
</script>
</body>
</html>