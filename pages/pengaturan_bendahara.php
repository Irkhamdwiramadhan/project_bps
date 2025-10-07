<?php
session_start();
include '../includes/koneksi.php';

// Memastikan hanya super_admin dan admin_koperasi yang bisa mengakses
if (!isset($_SESSION['user_role']) || (!in_array('super_admin', $_SESSION['user_role']) && !in_array('admin_koperasi', $_SESSION['user_role']))) {
    // Redirect ke halaman login atau halaman lain jika tidak ada hak akses
    header("Location: /login.php"); // Sesuaikan dengan halaman login Anda
    exit;
}

// 1. PROSES SIMPAN DATA (HANYA JIKA FORM DI-SUBMIT)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bendahara_name'])) {
        $nama_bendahara_baru = trim($_POST['bendahara_name']);

        if (!empty($nama_bendahara_baru)) {
            $stmt = $koneksi->prepare("UPDATE pengaturan SET pengaturan_nilai = ? WHERE pengaturan_nama = 'nama_bendahara'");
            $stmt->bind_param("s", $nama_bendahara_baru);
            
            if ($stmt->execute()) {
                // Jika berhasil, redirect kembali ke halaman ini dengan pesan sukses
                header("Location: pengaturan_bendahara.php?status=sukses");
                exit;
            } else {
                // Jika gagal, redirect dengan pesan error
                header("Location: pengaturan_bendahara.php?status=gagal");
                exit;
            }
            $stmt->close();
        } else {
            // Jika nama kosong, redirect dengan pesan error
            header("Location: pengaturan_bendahara.php?status=kosong");
            exit;
        }
    }
}

// 2. AMBIL DATA DAN SIAPKAN PESAN UNTUK DITAMPILKAN
// =================================================================
$pesan_sukses = '';
$pesan_error = '';

// Cek parameter 'status' dari URL hasil redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses') {
        $pesan_sukses = "Nama bendahara berhasil diperbarui!";
    } elseif ($_GET['status'] == 'gagal') {
        $pesan_error = "Terjadi kesalahan. Data gagal diperbarui.";
    } elseif ($_GET['status'] == 'kosong') {
        $pesan_error = "Nama bendahara tidak boleh kosong!";
    }
}

// Ambil data terbaru dari database untuk ditampilkan di form
$nama_bendahara_sekarang = '';
$stmt_get = $koneksi->prepare("SELECT pengaturan_nilai FROM pengaturan WHERE pengaturan_nama = 'nama_bendahara'");
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($result->num_rows > 0) {
    $pengaturan = $result->fetch_assoc();
    $nama_bendahara_sekarang = $pengaturan['pengaturan_nilai'];
}
$stmt_get->close();

// Sertakan header dan sidebar SETELAH semua logika PHP selesai
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    /* Style Anda sudah bagus, tidak perlu diubah */
    body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; }
    .main-content { padding: 2rem; }
    .form-container { max-width: 500px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    h2 { font-size: 1.8rem; color: #333; margin-bottom: 20px; text-align: center; }
    .form-group { margin-bottom: 20px; text-align: left; }
    label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
    input[type="text"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
    .btn-submit { display: block; width: 100%; padding: 12px; background-color: #007bff; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; }
    .btn-submit:hover { background-color: #0056b3; }
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 1rem; text-align: center; }
    .alert-success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
    .alert-danger { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
    .table-title-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    .table-title-header h2 {
        margin: 0; /* Menghapus margin bawaan h2 */
        padding: 0;
        border: none;
    }
    .btn-kembali {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        background-color: #6c757d; /* Warna abu-abu sekunder */
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.3s ease;
    }
    .btn-kembali:hover {
        background-color: #5a6268;
    }
</style>

<div class="main-content">
    <div class="form-container">
        <a href="bendahara.php" class="btn-kembali">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h2>Pengaturan Nama Bendahara</h2>
        
        <?php if ($pesan_sukses): ?>
            <div class="alert alert-success"><?= htmlspecialchars($pesan_sukses) ?></div>
        <?php endif; ?>
        <?php if ($pesan_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($pesan_error) ?></div>
        <?php endif; ?>
        
        <form action="pengaturan_bendahara.php" method="POST">
            <div class="form-group">
                <label for="bendahara_name">Nama Bendahara Koperasi:</label>
                <input type="text" id="bendahara_name" name="bendahara_name" value="<?= htmlspecialchars($nama_bendahara_sekarang) ?>" required>
            </div>
            
            <button type="submit" class="btn-submit">Simpan Perubahan</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>