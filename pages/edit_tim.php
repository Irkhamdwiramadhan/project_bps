<?php
// edit_tim.php

session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu', 'ketua_tim'];
if (!array_intersect($allowed_roles, $user_roles)) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk mengakses halaman ini.";
    header('Location: halaman_tim.php');
    exit;
}

// Ambil ID tim dari URL, pastikan itu adalah angka
$tim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tim_id === 0) {
    $_SESSION['error_message'] = "ID Tim tidak valid.";
    header('Location: halaman_tim.php');
    exit;
}

// Ambil data tim yang akan diedit
$stmt = $koneksi->prepare("SELECT nama_tim, ketua_tim_id, deskripsi FROM tim WHERE id = ?");

$stmt->bind_param("i", $tim_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Data tim tidak ditemukan.";
    header('Location: halaman_tim.php');
    exit;
}
$tim = $result->fetch_assoc();
$stmt->close();

// Ambil daftar anggota tim yang sudah ada
$current_anggota = [];
$stmt_anggota = $koneksi->prepare("SELECT member_id, member_type FROM anggota_tim WHERE tim_id = ?");
$stmt_anggota->bind_param("i", $tim_id);
$stmt_anggota->execute();
$result_anggota = $stmt_anggota->get_result();
while($row = $result_anggota->fetch_assoc()) {
    // Format value: 'tipe-id' agar cocok dengan <option>
    $current_anggota[] = $row['member_type'] . '-' . $row['member_id'];
}
$stmt_anggota->close();


// Ambil data untuk dropdowns (sama seperti di halaman tambah)
$pegawai_list = $koneksi->query("SELECT id, nama FROM pegawai WHERE is_active = 1 ORDER BY nama ASC")->fetch_all(MYSQLI_ASSOC);
$mitra_list = $koneksi->query("SELECT id, nama_lengkap FROM mitra");

?>

<main class="main-content">
    <div class="header-content" style="display: flex; align-items: center; gap: 10px; padding: 15px 20px;">
        <a href="halaman_tim.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h2>Edit Tim</h2>
    </div>

    <div class="card" style="margin: 15px;">
        <div class="card-body">
            <form action="../proses/proses_edit_tim.php" method="POST">
                <input type="hidden" name="tim_id" value="<?= $tim_id ?>">

                <div class="mb-3">
                    <label for="nama_tim" class="form-label">Nama Tim</label>
                    <input type="text" class="form-control" id="nama_tim" name="nama_tim" value="<?= htmlspecialchars($tim['nama_tim']) ?>" required>
                </div>

                <!-- Tambahkan kolom deskripsi -->
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi Tim</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($tim['deskripsi'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="ketua_tim_id" class="form-label">Ketua Tim</label>
                    <select class="form-select select2" id="ketua_tim_id" name="ketua_tim_id" required>
                        <option value="">-- Pilih Ketua Tim --</option>
                        <?php foreach ($pegawai_list as $pegawai): ?>
                            <option value="<?= $pegawai['id'] ?>" <?= ($pegawai['id'] == $tim['ketua_tim_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pegawai['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="anggota" class="form-label">Anggota Tim</label>
                    <select class="form-select select2" id="anggota" name="anggota[]" multiple="multiple">
                        <optgroup label="Pegawai">
                            <?php foreach ($pegawai_list as $pegawai): 
                                $value = "pegawai-" . $pegawai['id'];
                                $selected = in_array($value, $current_anggota) ? 'selected' : '';
                            ?>
                                <option value="<?= $value ?>" <?= $selected ?>><?= htmlspecialchars($pegawai['nama']) ?> (Pegawai)</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Mitra">
                            <?php foreach ($mitra_list as $mitra): 
                                $value = "mitra-" . $mitra['id'];
                                $selected = in_array($value, $current_anggota) ? 'selected' : '';
                            ?>
                                <option value="<?= $value ?>" <?= $selected ?>><?= htmlspecialchars($mitra['nama_lengkap']) ?> (Mitra)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Update Tim</button>
            </form>
        </div>
    </div>
</main>


<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Pilih dari daftar...'
        });
    });
</script>