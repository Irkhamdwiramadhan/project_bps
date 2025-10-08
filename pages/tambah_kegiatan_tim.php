<?php
session_start();
include '../includes/koneksi.php';

if (isset($_GET['action']) && $_GET['action'] == 'get_anggota') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); echo json_encode(['error' => 'Akses ditolak']); exit; }
    if (!isset($_GET['tim_id']) || !is_numeric($_GET['tim_id'])) { echo json_encode([]); exit; }

    $tim_id = (int)$_GET['tim_id'];
    $anggota_list = [];
    $sql_anggota = "(SELECT at.id, p.nama AS nama_lengkap FROM anggota_tim AS at JOIN pegawai AS p ON at.member_id = p.id WHERE at.member_type = 'pegawai' AND at.tim_id = ?) UNION ALL (SELECT at.id, m.nama_lengkap FROM anggota_tim AS at JOIN mitra AS m ON at.member_id = m.id WHERE at.member_type = 'mitra' AND at.tim_id = ?) ORDER BY nama_lengkap ASC";
    
    $stmt = $koneksi->prepare($sql_anggota);
    $stmt->bind_param("ii", $tim_id, $tim_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) { while ($row = $result->fetch_assoc()) { $anggota_list[] = $row; } }
    $stmt->close();
    $koneksi->close();
    echo json_encode($anggota_list);
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: ../login.php'); exit; }
$user_roles = $_SESSION['user_role'] ?? []; $allowed_roles_for_action = ['super_admin', 'admin_simpedu']; $has_access_for_action = false; foreach ($user_roles as $role) { if (in_array($role, $allowed_roles_for_action)) { $has_access_for_action = true; break; } }
if (!$has_access_for_action) { $_SESSION['error_message'] = "Anda tidak memiliki izin."; header('Location: kegiatan_tim.php'); exit; }

$tim_list = [];
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
if ($result_tim->num_rows > 0) { while($row = $result_tim->fetch_assoc()) { $tim_list[] = $row; } }
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Kegiatan Baru</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

:root {
    --primary-color: #324057ff;
    --success-color: #4b4b50ff;
    --danger-color: #ef4444;
    --background-color: #f9fafb;
    --card-bg: #ffffff;
    --border-color: #d1d5db;
    --text-dark: #1f2937;
    --text-medium: #6b7280;
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: var(--background-color);
    color: var(--text-dark);
    margin: 0;
    padding: 0;
}

.form-container {
    max-width: 900px;
    margin: 2rem auto;
}

.card {
    background-color: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.card-header h2 {
    margin: 0;
    font-weight: 600;
    font-size: 1.5rem;
}

.card-header p {
    margin: 0.25rem 0 0 0;
    color: var(--text-medium);
    font-size: 0.9rem;
}

.card-body {
    padding: 2rem;
}

.form-floating {
    position: relative;
}

.form-floating > .form-control,
.form-floating > .form-select {
    width: 100%;
    padding: 1rem 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background-color: var(--background-color);
    color: var(--text-dark);
}

.form-floating > label {
    position: absolute;
    top: 0;
    left: 0.75rem;
    padding: 1rem 0;
    pointer-events: none;
    color: var(--text-medium);
    transition: 0.2s;
}

.form-floating > .form-control:focus,
.form-floating > .form-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
}

.form-floating > .form-control:focus + label,
.form-floating > .form-control:not(:placeholder-shown) + label,
.form-floating > .form-select:focus + label,
.form-floating > .form-select:not([value=""]) + label {
    transform: translateY(-50%) scale(0.85);
    background-color: var(--card-bg);
    padding: 0 0.25rem;
    left: 0.5rem;
    top: -0.5rem;
}

.anggota-row .input-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.5rem;
}

.anggota-row .input-group .form-select,
.anggota-row .input-group .form-control {
    flex: 1;
}

.anggota-row .input-group .btn-danger {
    flex: 0 0 auto;
    background-color: var(--danger-color);
    border-color: var(--danger-color);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn {
    padding: 0.65rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: 0.2s;
}

.btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; }
.btn-success { background-color: var(--success-color); border-color: var(--success-color); color: #fff; }
.btn-secondary { background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-medium); }

hr { border: none; border-top: 1px solid var(--border-color); margin: 2rem 0; }

.text-muted { color: var(--text-medium); }

</style>
</head>
<body>

<div class="form-container">
    <div class="card">
        <div class="card-header">
            <h2>Tambah Kegiatan Baru</h2>
            <p>Isi formulir di bawah ini untuk menambahkan kegiatan baru.</p>
        </div>
        <div class="card-body">
            <form action="../proses/proses_tambah_kegiatan_tim.php" method="POST">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nama_kegiatan" name="nama_kegiatan" placeholder="" required>
                    <label for="nama_kegiatan">Nama Kegiatan</label>
                </div>
                <br>
                
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="form-floating">
                            <select class="form-select" id="tim_id" name="tim_id" required>
                                <option value="" selected>-- Pilih Tim --</option>
                                <?php foreach ($tim_list as $tim): ?>
                                    <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                            </div>
                        </div>
                        <br>
                        <div class="mb-3">
                            <label class="form-label d-block mb-2">Anggota Tim & Target Individu</label>
                            <div id="anggota-placeholder" class="text-center text-muted p-4 border rounded">
                                <p class="mb-0">Pilih anggota tim yang terlibat dan atur jumlah satuan yang diikuti.</p>
                            </div>
                            <div id="anggota-area" class="d-none">
                                <div id="anggota-container"></div>
                                <button type="button" id="tambah-anggota" class="btn btn-success w-100 mt-2">
                                    Tambah Anggota
                                </button>
                            </div>
                        </div>
                        <br>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="satuan" name="satuan" placeholder="" required>
                            <label for="satuan">Nama Satuan</label>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="form-floating">
                            <label for="batas_waktu">Batas Waktu</label>
                            <br>
                            <br>
                            <input type="date" class="form-control" id="batas_waktu" name="batas_waktu" placeholder="Batas Waktu" required>
                            
                        </div>
                    </div>
                   
                    <div class="col-md-6">
                        <div class="form-floating">
                            <label for="target">Target Keseluruhan</label>
                            <br>
                            <br>
                            <input type="number" step="0.01" class="form-control" id="target" name="target" value="0.00" placeholder="Target Keseluruhan" readonly required>
                        </div>
                    </div>
                </div>

                <hr>


                <div class="form-floating mb-3">
                    <textarea class="form-control" id="keterangan" name="keterangan" placeholder="" style="height: 80px"></textarea>
                    <label for="keterangan">Keterangan (Opsional)</label>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="kegiatan_tim.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Kegiatan</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const timSelect = document.getElementById('tim_id');
    const anggotaContainer = document.getElementById('anggota-container');
    const addButton = document.getElementById('tambah-anggota');
    const anggotaPlaceholder = document.getElementById('anggota-placeholder');
    const anggotaArea = document.getElementById('anggota-area');
    const targetKeseluruhanInput = document.getElementById('target');
    let currentTeamMembers = [];

    const updateTotalTarget = () => {
        let total = 0;
        anggotaContainer.querySelectorAll('input[name="target_anggota[]"]').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        targetKeseluruhanInput.value = total.toFixed(2);
    };

    const addAnggotaRow = () => {
        const row = document.createElement('div');
        row.className = 'anggota-row';

        let optionsHTML = '<option value="" selected>-- Pilih Anggota --</option>';
        currentTeamMembers.forEach(anggota => {
            optionsHTML += `<option value="${anggota.id}">${escapeHTML(anggota.nama_lengkap)}</option>`;
        });

        row.innerHTML = `
            <div class="input-group">
                <select class="form-select" name="anggota_id[]" required>
                    ${optionsHTML}
                </select>
                <input type="number" step="0.01" class="form-control" name="target_anggota[]" placeholder="Target" value="1.00" required>
                
                <button type="button" class="btn btn-danger remove-anggota" title="Hapus">
                    &times;
                </button>
            </div>
        `;
        anggotaContainer.appendChild(row);
        updateTotalTarget();
    };

    timSelect.addEventListener('change', function() {
        const timId = this.value;
        anggotaContainer.innerHTML = '';
        currentTeamMembers = [];
        updateTotalTarget();

        if (timId) {
            anggotaPlaceholder.classList.add('d-none');
            anggotaArea.classList.remove('d-none');
            anggotaContainer.innerHTML = '<p class="text-center text-muted small py-3">Memuat anggota...</p>';

            fetch(`?action=get_anggota&tim_id=${timId}`)
                .then(res => res.ok ? res.json() : Promise.reject('Fetch error'))
                .then(data => {
                    anggotaContainer.innerHTML = '';
                    currentTeamMembers = data;
                    if (data.length) addAnggotaRow();
                    else anggotaContainer.innerHTML = '<p class="text-center text-muted small py-3">Tidak ada anggota di tim ini.</p>';
                })
                .catch(() => {
                    anggotaContainer.innerHTML = '<p class="text-center text-danger small py-3">Gagal memuat data anggota.</p>';
                });
        } else {
            anggotaPlaceholder.classList.remove('d-none');
            anggotaArea.classList.add('d-none');
        }
    });

    addButton.addEventListener('click', addAnggotaRow);
    anggotaContainer.addEventListener('click', e => {
        if (e.target.closest('.remove-anggota')) {
            e.target.closest('.anggota-row').remove();
            updateTotalTarget();
        }
    });
    anggotaContainer.addEventListener('input', e => {
        if (e.target.matches('input[name="target_anggota[]"]')) updateTotalTarget();
    });

    function escapeHTML(str) { return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
});
</script>

</body>
</html>
