<?php
session_start();
include '../includes/koneksi.php';

// ===================================================================
// BAGIAN 1: LOGIKA PHP (HAK AKSES & PENGAMBILAN DATA)
// ===================================================================
// Cek hak akses...
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: ../login.php'); exit; }
$user_roles = $_SESSION['user_role'] ?? []; $allowed_roles_for_action = ['super_admin', 'ketua_tim']; $has_access_for_action = false; foreach ($user_roles as $role) { if (in_array($role, $allowed_roles_for_action)) { $has_access_for_action = true; break; } }
if (!$has_access_for_action) { $_SESSION['error_message'] = "Anda tidak memiliki izin."; header('Location: kegiatan_tim.php'); exit; }

// Ambil ID dari URL dan validasi
$id_kegiatan = $_GET['id'] ?? null;
if (!$id_kegiatan || !is_numeric($id_kegiatan)) { $_SESSION['error_message'] = "ID Kegiatan tidak valid."; header('Location: kegiatan_tim.php'); exit; }

// Ambil data utama kegiatan yang akan diedit (termasuk kolom 'realisasi')
$stmt = $koneksi->prepare("SELECT * FROM kegiatan WHERE id = ?");
$stmt->bind_param("i", $id_kegiatan);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { $_SESSION['error_message'] = "Data kegiatan tidak ditemukan."; header('Location: kegiatan_tim.php'); exit; }
$kegiatan = $result->fetch_assoc();
$stmt->close();

// ... (Sisa logika PHP untuk mengambil data anggota, tim, dll, tetap sama) ...
$kegiatan_anggota = []; $sql_anggota_terlibat = "SELECT ka.anggota_id, ka.target_anggota FROM kegiatan_anggota ka WHERE ka.kegiatan_id = ?"; $stmt_anggota = $koneksi->prepare($sql_anggota_terlibat); $stmt_anggota->bind_param("i", $id_kegiatan); $stmt_anggota->execute(); $result_anggota = $stmt_anggota->get_result(); while($row = $result_anggota->fetch_assoc()) { $kegiatan_anggota[] = $row; } $stmt_anggota->close();
$tim_list = []; $sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC"; $result_tim = $koneksi->query($sql_tim); if ($result_tim->num_rows > 0) { while($row = $result_tim->fetch_assoc()) { $tim_list[] = $row; } }
$anggota_tim_saat_ini = []; $tim_id_kegiatan = $kegiatan['tim_id']; $sql_anggota_tim = "(SELECT at.id, p.nama AS nama_lengkap FROM anggota_tim AS at JOIN pegawai AS p ON at.member_id = p.id WHERE at.member_type = 'pegawai' AND at.tim_id = ?) UNION ALL (SELECT at.id, m.nama_lengkap FROM anggota_tim AS at JOIN mitra AS m ON at.member_id = m.id WHERE at.member_type = 'mitra' AND at.tim_id = ?) ORDER BY nama_lengkap ASC"; $stmt_anggota_tim = $koneksi->prepare($sql_anggota_tim); $stmt_anggota_tim->bind_param("ii", $tim_id_kegiatan, $tim_id_kegiatan); $stmt_anggota_tim->execute(); $result_anggota_tim = $stmt_anggota_tim->get_result(); if ($result_anggota_tim) { while ($row = $result_anggota_tim->fetch_assoc()) { $anggota_tim_saat_ini[] = $row; } } $stmt_anggota_tim->close();
if (isset($_GET['action']) && $_GET['action'] == 'get_anggota') { header('Content-Type: application/json'); if (!isset($_GET['tim_id']) || !is_numeric($_GET['tim_id'])) { echo json_encode([]); exit; } $tim_id = (int)$_GET['tim_id']; $anggota_list = []; $sql_anggota_ajax = "(SELECT at.id, p.nama AS nama_lengkap FROM anggota_tim AS at JOIN pegawai AS p ON at.member_id = p.id WHERE at.member_type = 'pegawai' AND at.tim_id = ?) UNION ALL (SELECT at.id, m.nama_lengkap FROM anggota_tim AS at JOIN mitra AS m ON at.member_id = m.id WHERE at.member_type = 'mitra' AND at.tim_id = ?) ORDER BY nama_lengkap ASC"; $stmt_ajax = $koneksi->prepare($sql_anggota_ajax); $stmt_ajax->bind_param("ii", $tim_id, $tim_id); $stmt_ajax->execute(); $result_ajax = $stmt_ajax->get_result(); if ($result_ajax) { while ($row = $result_ajax->fetch_assoc()) { $anggota_list[] = $row; } } $stmt_ajax->close(); $koneksi->close(); echo json_encode($anggota_list); exit; }

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kegiatan</title>
    <style>
        /* ... CSS Anda dari sebelumnya ... */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root { --primary-color: #324057ff; --success-color: #4b4b50ff; --danger-color: #ef4444; --background-color: #f9fafb; --card-bg: #ffffff; --border-color: #d1d5db; --text-dark: #1f2937; --text-medium: #6b7280; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-dark); }
        .form-container { max-width: 900px; margin: 2rem auto; } .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); background: linear-gradient(90deg, #324057ff, #4b4b50ff); color: white; }
        .card-header h2 { margin: 0; font-weight: 600; font-size: 1.5rem; } .card-header p { margin: 0.25rem 0 0 0; color: #e5e7eb; font-size: 0.9rem; }
        .card-body { padding: 2rem; } .form-control[readonly] { background-color: #e9ecef; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: 0.2s; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; }
        .btn-success { background-color: var(--success-color); border-color: var(--success-color); color: #fff; }
        .btn-secondary { background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-medium); }
        hr { border: none; border-top: 1px solid var(--border-color); margin: 2rem 0; }
    </style>
</head>
<body>
<div class="form-container">
    <div class="card">
        <div class="card-header">
            <h2>Edit Kegiatan</h2>
            <p>Ubah data kegiatan yang sudah ada di bawah ini.</p>
        </div>
        <div class="card-body">
            <form action="../proses/proses_edit_kegiatan_tim.php" method="POST">
                <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">

                <div class="form-floating mb-3">
                    <label for="nama_kegiatan">Nama Kegiatan</label>
                    <input type="text" class="form-control" id="nama_kegiatan" name="nama_kegiatan" placeholder="Nama Kegiatan" value="<?= htmlspecialchars($kegiatan['nama_kegiatan']) ?>" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <select class="form-select" id="tim_id" name="tim_id" required>
                                <?php foreach ($tim_list as $tim): ?>
                                    <option value="<?= $tim['id'] ?>" <?= ($tim['id'] == $kegiatan['tim_id']) ? 'selected' : '' ?>><?= htmlspecialchars($tim['nama_tim']) ?></option>
                                    <?php endforeach; ?>
                                    <label for="tim_id">Tim Penanggung Jawab</label>
                                </select>
                            </div>
                        </div>
                        <label class="form-label d-block mb-2">Anggota Tim & Target Individu</label>
                        <div class="mb-3">
                            <div id="anggota-container"></div>
                            <button type="button" id="tambah-anggota" class="btn btn-success w-100 mt-2"><i class="bi bi-plus-circle"></i> Tambah Anggota</button>
                        </div>
                        <hr>
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <label for="satuan">Nama Satuan</label>
                            <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Nama Satuan" value="<?= htmlspecialchars($kegiatan['satuan']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="form-floating">
                            <label for="realisasi">Realisasi</label>
                            <input type="number" step="0.01" class="form-control" id="realisasi" name="realisasi" placeholder="Realisasi" value="<?= htmlspecialchars($kegiatan['realisasi']) ?>" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-floating">
                            <label for="target">Target Keseluruhan</label>
                            <input type="number" step="0.01" class="form-control" id="target" name="target" placeholder="Target Keseluruhan" value="<?= htmlspecialchars($kegiatan['target']) ?>" readonly required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="form-floating">
                            <label for="batas_waktu">Batas Waktu</label>
                            <input type="date" class="form-control" id="batas_waktu" name="batas_waktu" placeholder="Batas Waktu" value="<?= $kegiatan['batas_waktu'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <label for="keterangan">Keterangan (Opsional)</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" placeholder="Keterangan (Opsional)" style="height: 80px"><?= htmlspecialchars($kegiatan['keterangan']) ?></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="kegiatan_tim.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const initialAnggota = <?= json_encode($kegiatan_anggota) ?>;
let currentTeamMembers = <?= json_encode($anggota_tim_saat_ini) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const timSelect = document.getElementById('tim_id');
    const anggotaContainer = document.getElementById('anggota-container');
    const addButton = document.getElementById('tambah-anggota');
    const targetKeseluruhanInput = document.getElementById('target');
    const realisasiInput = document.getElementById('realisasi'); // PENAMBAHAN: Referensi ke input realisasi

    // PENAMBAHAN: Fungsi untuk validasi realisasi
    const validateRealisasi = () => {
        const targetValue = parseFloat(targetKeseluruhanInput.value);
        let realisasiValue = parseFloat(realisasiInput.value);

        if (realisasiValue > targetValue) {
            realisasiInput.value = targetValue; // Otomatis set ke nilai maksimum jika lebih besar
        }
    };

    const updateTotalTarget = () => {
        let total = 0;
        anggotaContainer.querySelectorAll('input[name="target_anggota[]"]').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        targetKeseluruhanInput.value = total.toFixed(2);
        realisasiInput.max = total.toFixed(2); // PENAMBAHAN: Set atribut max pada input realisasi
        validateRealisasi(); // Validasi ulang realisasi setiap kali target berubah
    };

    const addAnggotaRow = (anggotaId = '', targetValue = '1.00') => {
        const row = document.createElement('div');
        row.className = 'anggota-row';
        let optionsHTML = '<option value="">-- Pilih Anggota --</option>';
        currentTeamMembers.forEach(anggota => {
            const selected = (anggota.id == anggotaId) ? 'selected' : '';
            optionsHTML += `<option value="${anggota.id}" ${selected}>${escapeHTML(anggota.nama_lengkap)}</option>`;
        });
        row.innerHTML = `<div class="input-group mb-2"><select class="form-select" name="anggota_id[]" required style="flex: 1 1 60%;">${optionsHTML}</select><input type="number" step="0.01" class="form-control" name="target_anggota[]" placeholder="Target" value="${targetValue}" required style="flex: 1 1 25%;"><button type="button" class="btn btn-danger remove-anggota" title="Hapus"><i class="bi bi-trash"></i></button></div>`;
        anggotaContainer.appendChild(row);
    };

    initialAnggota.forEach(anggota => {
        addAnggotaRow(anggota.anggota_id, anggota.target_anggota);
    });
    updateTotalTarget(); // Hitung & set total awal

    timSelect.addEventListener('change', function() {
        const timId = this.value;
        anggotaContainer.innerHTML = '';
        currentTeamMembers = [];
        updateTotalTarget();
        anggotaContainer.innerHTML = '<p class="text-center text-muted small py-3">Memuat anggota...</p>';
        fetch(`?action=get_anggota&tim_id=${timId}`)
            .then(res => res.json())
            .then(data => {
                anggotaContainer.innerHTML = '';
                currentTeamMembers = data;
                addAnggotaRow();
            });
    });

    addButton.addEventListener('click', () => addAnggotaRow());
    anggotaContainer.addEventListener('click', e => { if (e.target.closest('.remove-anggota')) { e.target.closest('.anggota-row').remove(); updateTotalTarget(); } });
    anggotaContainer.addEventListener('input', e => { if (e.target.matches('input[name="target_anggota[]"]')) updateTotalTarget(); });
    realisasiInput.addEventListener('input', validateRealisasi); // PENAMBAHAN: Panggil validasi saat nilai realisasi diubah
    function escapeHTML(str) { return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
});
</script>
</body>
</html>