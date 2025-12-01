<?php
session_start();
include '../includes/koneksi.php';

// AJAX Handler untuk mengambil anggota
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

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: ../login.php'); exit; }
$user_roles = $_SESSION['user_role'] ?? []; $allowed_roles_for_action = ['super_admin', 'ketua_tim']; $has_access_for_action = false; foreach ($user_roles as $role) { if (in_array($role, $allowed_roles_for_action)) { $has_access_for_action = true; break; } }
if (!$has_access_for_action) { $_SESSION['error_message'] = "Anda tidak memiliki izin."; header('Location: kegiatan_tim.php'); exit; }

// Ambil data Tim
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
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

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
            margin: 0;
            padding: 0;
        }

        .form-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background-color: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            background-color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Elements */
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-main);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.2s;
            background-color: #fff;
            box-sizing: border-box;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .form-control[readonly] {
            background-color: #f9fafb;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* Section Layout */
        .form-section {
            margin-bottom: 2rem;
        }
        
        .row {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .col-half { flex: 1; min-width: 300px; }
        .col-full { width: 100%; }
        .col-third { flex: 1; min-width: 200px; }

        /* Anggota Section */
        #anggota-container {
            background-color: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 1rem;
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

        .anggota-row:last-child { margin-bottom: 0; }

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
            transition: 0.2s;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-icon-danger:hover { background-color: #fecaca; }

        #anggota-placeholder {
            background-color: #f8fafc;
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        .d-none { display: none !important; }
    </style>
</head>
<body>

<div class="form-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="bi bi-journal-plus"></i> Tambah Kegiatan</h2>
            <a href="kegiatan_tim.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <div class="card-body">
            <form action="../proses/proses_tambah_kegiatan_tim.php" method="POST">

                <div class="form-section">
                    <div class="form-group mb-3">
                        <label for="nama_kegiatan" class="form-label">Nama Kegiatan</label>
                        <input type="text" class="form-control" id="nama_kegiatan" name="nama_kegiatan" placeholder="Contoh: Survei Ekonomi Nasional" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-half">
                            <label for="tim_id" class="form-label">Tim Penanggung Jawab</label>
                            <select class="form-select" id="tim_id" name="tim_id" required>
                                <option value="" selected>-- Pilih Tim --</option>
                                <?php foreach ($tim_list as $tim): ?>
                                    <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-half">
                            <label for="satuan" class="form-label">Satuan</label>
                            <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Contoh: Dokumen / Responden" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-half">
                            <label for="batas_waktu" class="form-label">Batas Waktu</label>
                            <input type="date" class="form-control" id="batas_waktu" name="batas_waktu" required>
                        </div>
                        <div class="col-half">
                            <label for="target" class="form-label">Target Total (Otomatis)</label>
                            <input type="number" step="0.01" class="form-control" id="target" name="target" value="0.00" readonly required>
                        </div>
                    </div>
                </div>

                <hr style="margin: 2rem 0; border-color: #e5e7eb;">

                <div class="form-section">
                    <label class="form-label" style="margin-bottom: 1rem;">Anggota Tim & Target Individu</label>
                    
                    <div id="anggota-placeholder">
                        <i class="bi bi-people" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                        <p style="margin: 0;">Silakan pilih <strong>Tim Penanggung Jawab</strong> terlebih dahulu.</p>
                    </div>

                    <div id="anggota-area" class="d-none">
                        <div id="anggota-container">
                            </div>
                        <button type="button" id="tambah-anggota" class="btn btn-success mt-2">
                            <i class="bi bi-plus-lg"></i> Tambah Anggota
                        </button>
                    </div>
                </div>

                <hr style="margin: 2rem 0; border-color: #e5e7eb;">

                <div class="form-section">
                    <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan catatan jika perlu..."></textarea>
                </div>

                <div style="text-align: right; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Simpan Kegiatan
                    </button>
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
            <select class="form-select" name="anggota_id[]" required style="flex: 2;">
                ${optionsHTML}
            </select>
            <input type="number" step="0.01" class="form-control" name="target_anggota[]" placeholder="Target" value="1.00" required style="flex: 1;">
            
            <button type="button" class="btn btn-icon-danger remove-anggota" title="Hapus">
                <i class="bi bi-trash"></i>
            </button>
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
            anggotaContainer.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 1rem;">Memuat anggota...</p>';

            fetch(`?action=get_anggota&tim_id=${timId}`)
                .then(res => res.ok ? res.json() : Promise.reject('Fetch error'))
                .then(data => {
                    anggotaContainer.innerHTML = '';
                    currentTeamMembers = data;
                    if (data.length) addAnggotaRow();
                    else anggotaContainer.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 1rem;">Tidak ada anggota di tim ini.</p>';
                })
                .catch(() => {
                    anggotaContainer.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 1rem;">Gagal memuat data anggota.</p>';
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

    function escapeHTML(str) { return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
});
</script>

</body>
</html>