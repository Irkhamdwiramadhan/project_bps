<?php
session_start();
include '../includes/koneksi.php';

// -------------------------------------------------------------------------
// AJAX Handler (VERSI SAPU JAGAT: Cek Anggota Tim, Pegawai, DAN Mitra)
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'get_anggota') {
    header('Content-Type: application/json');
    
    $tim_id = (int)$_GET['tim_id'];
    $anggota_list = [];
    
    // Query ini mengambil Anggota Tim Aktif (Standard)
    // Value yang diambil adalah at.id (ID Anggota Tim)
    $sql_anggota = "
        SELECT 
            at.id as id, 
            COALESCE(p.nama, m.nama_lengkap) as nama_lengkap
        FROM anggota_tim at
        LEFT JOIN pegawai p ON at.member_id = p.id
        LEFT JOIN mitra m ON at.member_id = m.id
        WHERE at.tim_id = ?
        ORDER BY nama_lengkap ASC";
                    
    $stmt = $koneksi->prepare($sql_anggota);
    $stmt->bind_param("i", $tim_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) { while ($row = $result->fetch_assoc()) { $anggota_list[] = $row; } }
    echo json_encode($anggota_list);
    exit;
}

// -------------------------------------------------------------------------
// Bagian Utama Halaman Edit
// -------------------------------------------------------------------------
include '../includes/header.php'; 
include '../includes/sidebar.php';

$id_kegiatan = $_GET['id'] ?? null;
if (!$id_kegiatan) { header('Location: kegiatan_tim.php'); exit; }

// 1. Ambil Data Kegiatan
$stmt = $koneksi->prepare("SELECT * FROM kegiatan WHERE id = ?");
$stmt->bind_param("i", $id_kegiatan);
$stmt->execute();
$kegiatan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$kegiatan) { echo "Kegiatan tidak ditemukan."; exit; }

// 2. Ambil Detail Anggota Terlibat (ID: 175, 188, dll)
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

// 3. Ambil Daftar Tim (Hanya Aktif)
$tim_list = [];
$result_tim = $koneksi->query("SELECT id, nama_tim FROM tim WHERE is_active = 1 ORDER BY nama_tim ASC");
if ($result_tim) { while($row = $result_tim->fetch_assoc()) { $tim_list[] = $row; } }

// 4. Ambil Daftar Anggota Tim (LOGIKA "LANGSUNG KE ID PEGAWAI/MITRA")
$anggota_tim_saat_ini = [];
$tim_id_kegiatan = $kegiatan['tim_id'];

if (!empty($tim_id_kegiatan)) {
    
    // Kumpulkan ID yang tersimpan (175, 188, 173)
    $saved_ids = [];
    foreach ($kegiatan_anggota as $ka) {
        $saved_ids[] = $ka['anggota_id'];
    }
    $saved_ids_string = empty($saved_ids) ? "0" : implode(",", $saved_ids);

    // QUERY GABUNGAN (UNION)
    // 1. Ambil Anggota Tim Resmi (via tabel anggota_tim)
    // 2. Ambil LANGSUNG dari Pegawai (jika 175 adalah ID Pegawai)
    // 3. Ambil LANGSUNG dari Mitra (jika 175 adalah ID Mitra)
    
    $sql_anggota_tim = "
        SELECT DISTINCT * FROM (
            -- Opsi 1: Cek di Anggota Tim (Normal Flow)
            SELECT 
                at.id as id, 
                COALESCE(p.nama, m.nama_lengkap) as nama_lengkap
            FROM anggota_tim at
            LEFT JOIN pegawai p ON at.member_id = p.id
            LEFT JOIN mitra m ON at.member_id = m.id
            WHERE at.tim_id = $tim_id_kegiatan
            
            UNION
            
            -- Opsi 2: Cek Langsung ID Pegawai (FORCE LOOKUP PEGAWAI)
            -- Ini menjawab request: 'langsung ke id pegawai saja'
            -- Jika 175 ada di tabel pegawai, ambil namanya!
            SELECT 
                p.id as id, 
                p.nama as nama_lengkap 
            FROM pegawai p 
            WHERE p.id IN ($saved_ids_string)
            
            UNION
            
            -- Opsi 3: Cek Langsung ID Mitra (FORCE LOOKUP MITRA)
            SELECT 
                m.id as id, 
                m.nama_lengkap 
            FROM mitra m 
            WHERE m.id IN ($saved_ids_string)
            
        ) AS gabungan
        WHERE nama_lengkap IS NOT NULL
        ORDER BY nama_lengkap ASC";

    // Kita eksekusi query langsung (karena parameter sudah di-inject integer aman)
    $res_at = $koneksi->query($sql_anggota_tim);
    
    if ($res_at) { 
        while($r = $res_at->fetch_assoc()) { 
             $anggota_tim_saat_ini[] = $r; 
        } 
    }
}
?>
<style>
    /* ... Copy style dari kode sebelumnya ... */
    :root { --primary: #4361ee; --bg-body: #f3f4f6; --bg-card: #ffffff; --border: #e5e7eb; --text-main: #111827; }
    body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--text-main); }
    .form-container { max-width: 950px; margin: 2rem auto; padding: 0 1rem; }
    .card { background-color: var(--bg-card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid var(--border); overflow: hidden; }
    .card-header { background-color: #fff; padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .card-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin: 0; }
    .card-body { padding: 2rem; }
    .form-label { font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem; display: block; }
    .form-control, .form-select { width: 100%; padding: 0.6rem 1rem; font-size: 0.95rem; border: 1px solid var(--border); border-radius: 8px; }
    .form-section { margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px dashed var(--border); }
    .form-section:last-child { border-bottom: none; }
    .section-title { font-size: 0.85rem; text-transform: uppercase; font-weight: 700; color: #6b7280; margin-bottom: 1rem; }
    .btn { padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-secondary { background-color: white; border: 1px solid var(--border); color: var(--text-main); }
    .btn-success { background-color: #10b981; color: white; }
    .btn-icon-danger { background-color: #fee2e2; color: #ef4444; padding: 8px; border-radius: 6px; }
    
    .row { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .col-half { flex: 1; min-width: 250px; }

    #anggota-container { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-top: 10px; }
    .anggota-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: white; padding: 10px; border-radius: 6px; border: 1px solid var(--border); }
    .anggota-select { flex: 2; }
    .anggota-input { flex: 1; }
</style>

<div class="form-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="bi bi-pencil-square"></i> Edit Kegiatan</h2>
            <a href="kegiatan_tim.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>
        <div class="card-body">
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" style="background:#fee2e2; color:#b91c1c; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                    <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form action="../proses/proses_edit_kegiatan_tim.php" method="POST">
                <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">
                
                <div class="form-section">
                    <div class="section-title">Informasi Dasar</div>
                    <div class="row">
                        <div class="col-half">
                            <label class="form-label">Nama Kegiatan</label>
                            <input type="text" class="form-control" name="nama_kegiatan" value="<?= htmlspecialchars($kegiatan['nama_kegiatan']) ?>" required>
                        </div>
                         <div class="col-half">
                            <label class="form-label">Satuan</label>
                            <input type="text" class="form-control" name="satuan" value="<?= htmlspecialchars($kegiatan['satuan']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-half">
                            <label class="form-label">Tim Penanggung Jawab</label>
                            <select class="form-select" id="tim_id" name="tim_id" required>
                                 <?php foreach ($tim_list as $tim): ?>
                                    <option value="<?= $tim['id'] ?>" <?= ($tim['id'] == $kegiatan['tim_id']) ? 'selected' : '' ?>>
                                        <?= $tim['nama_tim'] ?>
                                    </option>
                                 <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-half">
                            <label class="form-label">Batas Waktu</label>
                            <input type="date" class="form-control" name="batas_waktu" value="<?= $kegiatan['batas_waktu'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section" style="background: #f8fafc; padding: 15px; border-radius: 8px;">
                    <div class="row" style="margin-bottom:0;">
                        <div class="col-half">
                            <label class="form-label">Total Target (Otomatis)</label>
                            <input type="number" step="0.01" class="form-control" id="target_total" name="target" value="<?= $kegiatan['target'] ?>" readonly style="font-weight:bold; background:#e2e8f0;">
                        </div>
                        <div class="col-half">
                            <label class="form-label">Total Realisasi (Otomatis)</label>
                            <input type="number" step="0.01" class="form-control" id="realisasi_total" name="realisasi" value="<?= $kegiatan['realisasi'] ?>" readonly style="font-weight:bold; color: #10b981; background:#e2e8f0;">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">Rincian Target & Realisasi Anggota</div>
                    
                    <div style="display:flex; gap:10px; margin-bottom:5px; font-size:0.85rem; font-weight:bold; color:#6b7280; padding:0 10px;">
                        <div style="flex:2;">Nama Anggota</div>
                        <div style="flex:1;">Target</div>
                        <div style="flex:1;">Realisasi</div>
                        <div style="width:40px;"></div>
                    </div>
                    
                    <div id="anggota-container"></div>
                    
                    <button type="button" id="tambah-anggota" class="btn btn-success mt-3">
                        <i class="bi bi-plus-lg"></i> Tambah Anggota
                    </button>
                </div>

                <div class="form-section">
                    <label class="form-label">Keterangan</label>
                    <textarea class="form-control" name="keterangan" rows="3"><?= htmlspecialchars($kegiatan['keterangan']) ?></textarea>
                </div>

                <div style="text-align: right; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Ambil data dari PHP
const initialAnggota = <?= json_encode($kegiatan_anggota) ?>;
let currentTeamMembers = <?= json_encode($anggota_tim_saat_ini) ?>;

console.log("Data Tersimpan (Dari DB):", initialAnggota);
console.log("Data Dropdown (Dari PHP):", currentTeamMembers);

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('anggota-container');
    const totalTargetInput = document.getElementById('target_total');
    const totalRealisasiInput = document.getElementById('realisasi_total');
    const timSelect = document.getElementById('tim_id');

    // 1. Fungsi Hitung Total
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

    // 2. Fungsi Tambah Baris
    const addRow = (savedId = null, target = 0, realisasi = 0) => {
        const div = document.createElement('div');
        div.className = 'anggota-row';
        
        // Buat Opsi Dropdown
        let opts = '<option value="">-- Pilih Anggota --</option>';
        let idFound = false;

        currentTeamMembers.forEach(m => {
            // Render semua opsi anggota
            opts += `<option value="${m.id}">${m.nama_lengkap}</option>`;
            
            // Cek apakah ID yang tersimpan ada di daftar anggota ini
            if (savedId !== null && String(m.id) === String(savedId)) {
                idFound = true;
            }
        });

        div.innerHTML = `
            <select class="form-select anggota-select" name="anggota_id[]" required>
                ${opts}
            </select>
            
            <input type="number" step="0.01" class="form-control anggota-input input-target" 
                   name="target_anggota[]" value="${target}" placeholder="Target" required>
            
            <input type="number" step="0.01" class="form-control anggota-input input-realisasi" 
                   name="realisasi_anggota[]" value="${realisasi}" placeholder="Realisasi" required>
            
            <button type="button" class="btn btn-icon-danger remove-row" title="Hapus Baris"><i class="bi bi-trash"></i></button>
        `;
        
        container.appendChild(div);

        // --- PAKSA PILIH NILAI (FIX SELECTED) ---
        if (savedId !== null) {
            const selectEl = div.querySelector('.anggota-select');
            
            if (idFound) {
                // Jika ID ditemukan di list, pilih dia
                selectEl.value = savedId;
            } else {
                // [PENTING] Jika ID tersimpan TIDAK ADA di list anggota tim saat ini
                // (Mungkin pegawainya sudah pindah tim atau data lama error)
                // Kita tambahkan opsi manual agar tidak blank/kosong
                console.warn(`ID Anggota ${savedId} tidak ditemukan di Tim ini. Menambahkan opsi sementara.`);
                const tempOption = new Option(`[ID: ${savedId}] Data Tidak Dikenal`, savedId, true, true);
                selectEl.add(tempOption, undefined); // Tambahkan opsi fallback
                selectEl.value = savedId;
            }
        }
    };

    // 3. RENDER DATA SAAT LOAD
    let rowCount = 0;

    if (initialAnggota && initialAnggota.length > 0) {
        initialAnggota.forEach(item => {
            let t = parseFloat(item.target_anggota) || 0;
            let r = parseFloat(item.realisasi_anggota) || 0;

            // Hanya tampilkan jika Target > 0 ATAU Realisasi > 0
            if (t > 0 || r > 0) {
                // item.anggota_id harus sesuai dengan m.id di currentTeamMembers
                addRow(item.anggota_id, t, r);
                rowCount++;
            }
        });
    }

    // Jika kosong setelah difilter, tampilkan pesan
    if (rowCount === 0) {
        container.innerHTML = '<p style="text-align:center; color:#999; font-style:italic; padding:10px;">Belum ada anggota yang memiliki target.</p>';
    }
    
    updateTotals(); 

    // --- EVENT LISTENERS ---

    // Tombol Tambah
    document.getElementById('tambah-anggota').addEventListener('click', () => { 
        if(container.innerHTML.includes('Belum ada')) container.innerHTML = '';
        addRow(); 
        updateTotals(); 
    });
    
    // Hapus Baris
    container.addEventListener('click', e => {
        if (e.target.closest('.remove-row')) {
            e.target.closest('.anggota-row').remove();
            updateTotals();
        }
    });

    // Hitung Otomatis
    container.addEventListener('input', e => {
        if (e.target.matches('.input-target') || e.target.matches('.input-realisasi')) {
            updateTotals();
        }
    });
    
    // Ganti Tim
    timSelect.addEventListener('change', function() {
        const tid = this.value;
        if (!tid) return;
        container.innerHTML = '<div style="padding:10px; text-align:center;">Memuat ulang anggota...</div>';
        fetch(`?action=get_anggota&tim_id=${tid}`)
            .then(r => r.json())
            .then(data => {
                currentTeamMembers = data; 
                container.innerHTML = ''; 
                if (data.length > 0) addRow(); 
                else container.innerHTML = '<div class="alert alert-warning">Tim ini kosong.</div>';
                updateTotals();
            });
    });
});
</script>