<?php
// tambah_tim.php

session_start();
include '../includes/koneksi.php';
include '../includes/header.php'; // Asumsikan header.php memuat CSS Bootstrap & Select2
include '../includes/sidebar.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Logika Hak Akses (RBAC) - sama seperti halaman daftar
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_simpedu'];
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}

// Jika tidak punya hak akses, tendang kembali ke halaman daftar
if (!$has_access_for_action) {
    // Opsional: set pesan error
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk menambah data tim.";
    header('Location: halaman_tim.php');
    exit;
}

// Ambil data untuk dropdowns
// 1. Pegawai Aktif (untuk Ketua dan Anggota)
$pegawai_list = [];
$sql_pegawai = "SELECT id, nama FROM pegawai WHERE is_active = 1 ORDER BY nama ASC";
$result_pegawai = $koneksi->query($sql_pegawai);
if ($result_pegawai->num_rows > 0) {
    while($row = $result_pegawai->fetch_assoc()) {
        $pegawai_list[] = $row;
    }
}

// 2. Mitra Aktif (untuk Anggota)
$mitra_list = [];
// Sesuaikan query ini dengan struktur tabel mitra Anda, misal ada kolom 'status'
$sql_mitra = "SELECT id, nama_lengkap FROM mitra";
$result_mitra = $koneksi->query($sql_mitra);
if ($result_mitra->num_rows > 0) {
    while($row = $result_mitra->fetch_assoc()) {
        $mitra_list[] = $row;
    }
}
?>

<main class="main-content">
    <div class="header-content" style="display: flex; align-items: center; gap: 10px; padding: 15px 20px;">
        <a href="halaman_tim.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h2>Tambah Tim Baru</h2>
    </div>

    <div class="card" style="margin: 15px;">
        <div class="card-body">
            <form action="../proses/proses_tambah_tim.php" method="POST">
                
                <div class="mb-3">
                    <label for="nama_tim" class="form-label">Nama Tim</label>
                    <input type="text" class="form-control" id="nama_tim" name="nama_tim" required>
                </div>

                <div class="mb-3">
                    <label for="ketua_tim_id" class="form-label">Ketua Tim</label>
                    <select class="form-select select2" id="ketua_tim_id" name="ketua_tim_id" required>
                        <option value="">-- Pilih Ketua Tim --</option>
                        <?php foreach ($pegawai_list as $pegawai): ?>
                            <option value="<?= $pegawai['id'] ?>"><?= htmlspecialchars($pegawai['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="anggota" class="form-label">Anggota Tim</label>
                    <select class="form-select select2" id="anggota" name="anggota[]" multiple="multiple">
                        <optgroup label="Pegawai">
                            <?php foreach ($pegawai_list as $pegawai): ?>
                                <option value="pegawai-<?= $pegawai['id'] ?>"><?= htmlspecialchars($pegawai['nama']) ?> (Pegawai)</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Mitra">
                            <?php foreach ($mitra_list as $mitra): ?>
                                <option value="mitra-<?= $mitra['id'] ?>"><?= htmlspecialchars($mitra['nama_lengkap']) ?> (Mitra)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Simpan Tim</button>
            </form>
        </div>
    </div>
</main>

<?php
// Pastikan footer.php memuat script untuk jQuery dan Select2
include '../includes/footer.php'; 
?>

<script>
    // Tunggu sampai dokumen siap
    $(document).ready(function() {
        // Inisialisasi Select2 pada elemen dengan class 'select2'
        $('.select2').select2({
            theme: 'bootstrap-5', // Gunakan tema Bootstrap 5 agar tampilan konsisten
            placeholder: 'Pilih dari daftar...'
        });
    });
</script>