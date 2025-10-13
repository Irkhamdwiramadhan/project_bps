<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan hanya pegawai yang sudah login yang bisa mengakses
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Mengambil data pengguna dari session sesuai permintaan Anda
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? '';

// Sesuaikan nama variabel agar cocok dengan sisa kode di halaman ini
$pegawai_id = $user_id;
$nama_pegawai = $user_name;

// Siapkan data otomatis untuk form
$tanggal_sekarang = date('Y-m-d');
$jam_sekarang = date('H:i');
?>

<style>
    /* Anda bisa menggunakan CSS dari halaman tambah/edit kegiatan untuk konsistensi */
    :root { --primary-color: #324057ff; --background-color: #f9fafb; --card-bg: #ffffff; --border-color: #d1d5db; --text-dark: #1f2937; --text-medium: #6b7280; }
    body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); }
    .form-container { max-width: 800px; margin: 2rem auto; }
    .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
    .card-header h2 { margin: 0; font-weight: 600; }
    .card-body { padding: 2rem; }
    .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; }
</style>

<main class="main-content">
    <div class="form-container">
        <div class="card">
                <div class="header-content" style="display: flex; align-items: center; gap: 10px; padding: 15px 20px;">
        <a href="laporan_keluar_list.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h2>Tambah Laporan</h2>
    </div>
            <div class="card-body">
                <form action="../proses/proses_laporan_keluar.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="pegawai_id" value="<?= $pegawai_id ?>">

                    <div class="mb-3">
                        <label for="nama_pegawai" class="form-label">Nama Pegawai</label>
                        <input type="text" class="form-control" id="nama_pegawai" value="<?= htmlspecialchars($nama_pegawai) ?>" readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_laporan" class="form-label">Tanggal Laporan</label>
                            <input type="date" class="form-control" id="tanggal_laporan" name="tanggal_laporan" value="<?= $tanggal_sekarang ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="jam_laporan" class="form-label">Jam Laporan</label> <br>
                            <input type="time" class="form-control" id="jam_laporan" name="jam_laporan" value="<?= $jam_sekarang ?>">
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="tujuan_keluar" class="form-label">Tujuan Keluar Kantor (opsional)</label>
                        <input type="text" class="form-control" id="tujuan_keluar" name="tujuan_keluar" rows="3" placeholder="Contoh: Mengantar dokumen ke Dinas, bertemu klien..."></input text="text">
                    </div>
                    
                    <div class="mb-3">
                        <label for="foto" class="form-label">Upload Foto Dokumentasi</label>
                        <input class="form-control" type="file" id="foto" name="foto" accept="image/*">
                    </div>

                    <div class="mb-4">
                        <label for="link_gps" class="form-label">Link Lokasi GPS</label>
                        <input type="text" class="form-control" id="link_gps" name="link_gps" placeholder="Contoh: https://maps.google.com/...">
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Kirim Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>