<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil semua parameter dari URL sebelumnya
$pegawai_id = $_GET['pegawai_id'] ?? '';
$periode = $_GET['periode'] ?? 'harian';
$tanggal_filter = $_GET['tanggal_filter'] ?? '';
$tanggal_awal = $_GET['tanggal_awal'] ?? '';
$tanggal_akhir = $_GET['tanggal_akhir'] ?? '';

// Memastikan hanya super_admin dan admin_koperasi yang bisa mengakses halaman ini
if (!isset($_SESSION['user_role']) || (!in_array('super_admin', $_SESSION['user_role']) && !in_array('admin_koperasi', $_SESSION['user_role']))) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}
?>
<style>
    body { font-family: 'Roboto', sans-serif; background-color: #f0f2f5; }
    .main-content { padding: 2rem; }
    .form-container { max-width: 500px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
    h2 { font-size: 1.8rem; color: #333; margin-bottom: 20px; }
    .form-group { margin-bottom: 20px; text-align: left; }
    label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
    input[type="text"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
    .btn-submit { display: block; width: 100%; padding: 12px; background-color: #007bff; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; }
    .btn-submit:hover { background-color: #0056b3; }
</style>

<div class="main-content">
    <div class="form-container">
        <h2>Masukkan Nama Bendahara</h2>
        <form action="nota_transaksi.php" method="GET">
            <div class="form-group">
                <label for="bendahara_name">Nama Bendahara Koperasi:</label>
                <input type="text" id="bendahara_name" name="bendahara_name" placeholder="Contoh: Adi Prima" required>
            </div>
            
            <input type="hidden" name="pegawai_id" value="<?= htmlspecialchars($pegawai_id) ?>">
            <input type="hidden" name="periode" value="<?= htmlspecialchars($periode) ?>">
            <input type="hidden" name="tanggal_filter" value="<?= htmlspecialchars($tanggal_filter) ?>">
            <input type="hidden" name="tanggal_awal" value="<?= htmlspecialchars($tanggal_awal) ?>">
            <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($tanggal_akhir) ?>">

            <button type="submit" class="btn-submit">Lanjutkan ke Nota</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>