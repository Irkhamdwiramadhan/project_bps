<?php
// Pastikan sesi sudah dimulai dan pengguna adalah admin
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Validasi hak akses
if (!isset($_SESSION['user_role']) || !in_array('super_admin', $_SESSION['user_role'])) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}
?>

<style>
/* Gaya CSS untuk tampilan formulir */
.main-content {
    padding: 40px;
    background-color: #f7f9fc;
}
.container {
    max-width: 800px;
    margin: auto;
}
.card {
    background-color: #ffffff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}
.form-group label {
    font-weight: 600;
}
.btn-upload {
    background-color: #23286bff;
    border-color: #232464ff;
    color: white;
}
.btn-upload:hover {
    background-color: #1e275aff;
    border-color: #352261ff;
}
.btn-back {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}
.btn-back:hover {
    background-color: #5a6268;
    border-color: #545b62;
}
</style>

<main class="main-content">
    <div class="container">
        <div class="card">
            <h2 class="mb-4">Tambah Data Anggaran (Unggah Excel)</h2>
            <p>Unduh Template Excel dibawah sebagai contoh format excel yang harus di input, Mohon untuk menyamakan nama kolom sesuai dengan tempate</p>
            <a href="../assets/Dipaku_new.xlsx" download class="btn-custom btn-primary mb-6">Unduh Template Excel</a>
            <p>Silakan unggah file Excel yang berisi data anggaran dengan struktur yang sudah ditentukan.</p>
            <form action="../proses/proses_tambah_data_master.php" method="POST" enctype="multipart/form-data">
                
                <div class="form-group mb-3">
                    <label for="tahun">Tahun Anggaran:</label>
                    <input type="number" class="form-control" id="tahun" name="tahun" required min="2000" max="2100" value="<?= date('Y') ?>">
                </div>

                <div class="form-group mb-4">
                    <label for="file_excel">Pilih File Excel (.xlsx atau .xls):</label>
                    <input type="file" class="form-control-file" id="file_excel" name="file_excel" accept=".xlsx, .xls" required>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="javascript:history.back()" class="btn btn-back">Kembali</a>
                    <button type="submit" class="btn btn-upload">Unggah dan Proses Data</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>