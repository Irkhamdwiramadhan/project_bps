<?php
// edit_kegiatan.php

session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// ===================================================================
// BAGIAN 1: LOGIKA PHP (HAK AKSES & PENGAMBILAN DATA)
// ===================================================================

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_simpedu'];
$has_access_for_action = count(array_intersect($allowed_roles_for_action, (array)$user_roles)) > 0;

if (!$has_access_for_action) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header('Location: kegiatan_tim.php');
    exit;
}

// 1. Ambil ID dari URL dan validasi
$id_kegiatan = $_GET['id'] ?? null;
if (!$id_kegiatan || !is_numeric($id_kegiatan)) {
    $_SESSION['error_message'] = "ID Kegiatan tidak valid.";
    header('Location: kegiatan_tim.php');
    exit;
}

// 2. Ambil data kegiatan yang akan diedit dari database
$stmt = $koneksi->prepare("SELECT * FROM kegiatan WHERE id = ?");
$stmt->bind_param("i", $id_kegiatan);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Data kegiatan tidak ditemukan.";
    header('Location: kegiatan_tim.php');
    exit;
}
$kegiatan = $result->fetch_assoc();
$stmt->close();

// 3. Ambil data tim untuk dropdown
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
    /* Menggunakan CSS yang sama persis dengan halaman "Tambah Kegiatan" untuk konsistensi */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    :root {
        --primary-color: #4A90E2;
        --warning-color: #10153fff;
        --background-color: #f4f7f9;
        --card-bg-color: #ffffff;
        --text-color: #333;
        --label-color: #475569;
        --border-color: #e2e8f0;
        --shadow-color: rgba(0, 0, 0, 0.05);
        --icon-color: #94a3b8;
    }
    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--background-color);
    }
    .main-content { min-height: 100vh; }
    .header-content {
        background-color: var(--card-bg-color); padding: 20px 30px;
        border-bottom: 1px solid var(--border-color);
    }
    .card {
        border: none; border-radius: 16px;
        box-shadow: 0 8px 16px var(--shadow-color);
        overflow: hidden;
    }
    .card-header {
        background: linear-gradient(90deg, var(--warning-color), #1b1c46ff);
        color: white; padding: 1.5rem; border-bottom: none;
    }
    .card-header h2 { margin: 0; font-weight: 600; }
    .form-section-title {
        font-weight: 600; color: var(--warning-color); margin-top: 1.5rem;
        margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-color);
    }
    .form-label { font-weight: 500; color: var(--label-color); margin-bottom: 0.5rem; }
    .input-group-text { background-color: #31567cff; border-color: var(--border-color); color: var(--icon-color); }
    .form-control, .form-select {
        border-radius: 8px !important; padding: 0.75rem 1rem; border-color: var(--border-color);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--warning-color);
        box-shadow: 0 0 0 3px rgba(18, 28, 117, 0.2);
    }
    .btn-warning {
        background-color: var(--warning-color); border-color: var(--warning-color);
        padding: 0.75rem 1.5rem; font-weight: 600; border-radius: 8px;
        transition: all 0.2s ease-in-out; color: white;
    }
    .btn-warning:hover {
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
                <h2><i class="bi bi-pencil-fill me-3"></i>Edit Kegiatan</h2>
            </div>
            <div class="card-body p-4">
                <form action="../proses/proses_edit_kegiatan_tim.php" method="POST">
                    <input type="hidden" name="id" value="<?= $kegiatan['id'] ?>">

                    <h5 class="form-section-title">Informasi Utama</h5>
                    
                    <div class="mb-3">
                        <label for="nama_kegiatan" class="form-label">Nama Kegiatan</label>
                        <div class="input-group">
                            
                            <textarea class="form-control" id="nama_kegiatan" name="nama_kegiatan" rows="3" required><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></textarea>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="tim_id" class="form-label">Tim Penanggung Jawab</label>
                         <div class="input-group">
        
                            <select class="form-select" id="tim_id" name="tim_id" required>
                                <option value="">-- Pilih Tim --</option>
                                <?php foreach ($tim_list as $tim): ?>
                                    <?php $selected = ($tim['id'] == $kegiatan['tim_id']) ? 'selected' : ''; ?>
                                    <option value="<?= $tim['id'] ?>" <?= $selected ?>><?= htmlspecialchars($tim['nama_tim']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h5 class="form-section-title">Detail Target & Waktu</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="target" class="form-label">Target</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" id="target" name="target" value="<?= htmlspecialchars($kegiatan['target']) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="realisasi" class="form-label">Realisasi</label>
                            <div class="input-group">
         
                                <input type="number" step="0.01" class="form-control" id="realisasi" name="realisasi" value="<?= htmlspecialchars($kegiatan['realisasi']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="satuan" class="form-label">Satuan</label>
                            <div class="input-group">

                                <input type="text" class="form-control" id="satuan" name="satuan" value="<?= htmlspecialchars($kegiatan['satuan']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="batas_waktu" class="form-label">Batas Waktu</habel>
                            <div class="input-group">
           
                                <input type="date" class="form-control" id="batas_waktu" name="batas_waktu" value="<?= $kegiatan['batas_waktu'] ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="tgl_realisasi" class="form-label">Tanggal Realisasi (Opsional)</label>
                            <div class="input-group">
           
                                <input type="date" class="form-control" id="tgl_realisasi" name="tgl_realisasi" value="<?= $kegiatan['tgl_realisasi'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                        <div class="input-group">
     
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="2"><?= htmlspecialchars($kegiatan['keterangan']) ?></textarea>
                        </div>
                    </div>
                    
                    <hr class="my-4">

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-warning px-4">
                            <i class="bi bi-save-fill me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>