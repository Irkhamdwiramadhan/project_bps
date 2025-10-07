<?php
// tambah_kegiatan_tim.php

session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// ===================================================================
// BAGIAN 1: LOGIKA PHP & HAK AKSES
// ===================================================================

// Pastikan pengguna sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Logika Hak Akses (RBAC)
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_simpedu'];
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}

// Jika tidak punya hak akses, alihkan kembali
if (!$has_access_for_action) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk menambah data kegiatan.";
    header('Location: kegiatan_tim.php');
    exit;
}

// Ambil data tim untuk dropdown
$tim_list = [];
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
if ($result_tim->num_rows > 0) {
    while($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}

?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    :root {
        --primary-color: #081b31ff;
        --success-color: #0a2952ff;
        --background-color: #f4f7f9;
        --card-bg-color: #ffffff;
        --text-color: #333;
        --header-text-color: #4A4A4A;
        --label-color: #475569;
        --border-color: #e2e8f0;
        --shadow-color: rgba(0, 0, 0, 0.05);
        --icon-color: #94a3b8;
    }
    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--background-color);
    }
    .main-content {
        min-height: 100vh;
    }
    .header-content {
        background-color: var(--card-bg-color);
        padding: 20px 30px;
        border-bottom: 1px solid var(--border-color);
    }
    .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 16px var(--shadow-color);
        overflow: hidden; /* Agar card-header menyatu dengan baik */
    }
    .card-header {
        background: linear-gradient(90deg, var(--primary-color), #72839bff);
        color: white;
        padding: 1.5rem;
        border-bottom: none;
    }
    .card-header h2 {
        margin: 0;
        font-weight: 600;
    }
    .form-section-title {
        font-weight: 600;
        color: var(--primary-color);
        margin-top: 1.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--border-color);
    }
    .form-label {
        font-weight: 500;
        color: var(--label-color);
        margin-bottom: 0.5rem;
    }
    .input-group-text {
        background-color: #f8fafc;
        border-color: var(--border-color);
        color: var(--icon-color);
    }
    .form-control, .form-select {
        border-radius: 8px !important;
        padding: 0.75rem 1rem;
        border-color: var(--border-color);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
    }
    .form-control:focus + .input-group-text,
    .form-select:focus + .input-group-text {
        border-color: var(--primary-color);
    }
    .btn-primary {
        background-color: var(--success-color);
        border-color: var(--success-color);
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.2s ease-in-out;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>

<main class="main-content">
    <div class="header-content">
        <a href="kegiatan_tim.php" class="btn btn-light shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar Kegiatan
        </a>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-pencil-square me-3"></i>Formulir Tambah Kegiatan</h2>
            </div>
            <div class="card-body p-4">
                <form action="../proses/proses_tambah_kegiatan_tim.php" method="POST">
                    
                    <h5 class="form-section-title">Informasi Utama</h5>
                    
                    <div class="mb-3">
                        <label for="nama_kegiatan" class="form-label">Nama Kegiatan</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                            <textarea class="form-control" id="nama_kegiatan" name="nama_kegiatan" rows="3" placeholder="Jelaskan nama kegiatan secara rinci" required></textarea>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="tim_id" class="form-label">Tim Penanggung Jawab</label>
                         <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-people-fill"></i></span>
                            <select class="form-select" id="tim_id" name="tim_id" required>
                                <option value="">-- Pilih Tim --</option>
                                <?php foreach ($tim_list as $tim): ?>
                                    <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h5 class="form-section-title">Detail Target & Waktu</h5>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="target" class="form-label">Target</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-bullseye"></i></span>
                                <input type="number" step="0.01" class="form-control" id="target" name="target" value="1.00" required>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="realisasi" class="form-label">Realisasi Awal</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-check2-circle"></i></span>
                                <input type="number" step="0.01" class="form-control" id="realisasi" name="realisasi" value="0.00" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="satuan" class="form-label">Satuan</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-box"></i></span>
                                <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Contoh: Laporan, Paket" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="batas_waktu" class="form-label">Batas Waktu</habel>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" class="form-control" id="batas_waktu" name="batas_waktu" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="tgl_realisasi" class="form-label">Tanggal Realisasi (Opsional)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                                <input type="date" class="form-control" id="tgl_realisasi" name="tgl_realisasi">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                        <div class="input-group">
                             <span class="input-group-text"><i class="bi bi-info-circle"></i></span>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2" placeholder="Tambahkan catatan jika perlu"></textarea>
                        </div>
                    </div>
                    
                    <hr class="my-4">

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save-fill me-2"></i>Simpan Kegiatan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php
include '../includes/footer.php'; 
?>