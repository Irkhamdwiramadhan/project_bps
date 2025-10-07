<?php
// =================================================================
// 1. PENGATURAN AWAL & DEBUGGING
// =================================================================
session_start();

// Aktifkan pelaporan error untuk debugging. Ini akan menampilkan masalah yang mungkin tersembunyi.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Sertakan file koneksi
include '../includes/koneksi.php';

// =================================================================
// 2. VALIDASI KONEKSI DAN HAK AKSES
// =================================================================

// Langkah Kritis 1: Pastikan koneksi benar-benar berhasil.
if (!$koneksi || $koneksi->connect_error) {
    // Hentikan script jika koneksi gagal dan tampilkan pesan yang jelas.
    die("<h1>Koneksi ke Database Gagal!</h1><p>Pastikan informasi di file '/includes/koneksi.php' sudah benar. Error: " . ($koneksi ? $koneksi->connect_error : mysqli_connect_error()) . "</p>");
}

// Memastikan hanya super_admin dan admin_koperasi yang bisa mengakses
if (!isset($_SESSION['user_role']) || (!in_array('super_admin', $_SESSION['user_role']) && !in_array('admin_koperasi', $_SESSION['user_role']))) {
    header("Location: /login.php"); // Ganti dengan halaman login Anda
    exit;
}

// =================================================================
// 3. PENGAMBILAN DATA DARI DATABASE
// =================================================================

// Inisialisasi variabel dengan nilai default yang jelas
$nama_bendahara = 'Data Belum Diatur';
$terakhir_diperbarui = 'N/A';
$pesan_diagnostik = '';

// PENTING: Pastikan nilai ini SAMA PERSIS dengan yang ada di kolom 'pengaturan_nama' di database Anda.
$pengaturan_key = 'nama_bendahara';

// Siapkan query yang aman untuk mengambil data
$stmt = $koneksi->prepare("SELECT pengaturan_nilai, updated_at FROM pengaturan WHERE pengaturan_nama = ?");

if ($stmt) {
    $stmt->bind_param("s", $pengaturan_key);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $nama_bendahara = $data['pengaturan_nilai'];
            
            if ($data['updated_at']) {
                $terakhir_diperbarui = date('d F Y, H:i:s', strtotime($data['updated_at']));
            }

        } else {
            // Jika query berhasil tapi tidak ada baris yang ditemukan, berikan pesan ini.
            $pesan_diagnostik = "Koneksi berhasil, tetapi baris dengan `pengaturan_nama` = '" . htmlspecialchars($pengaturan_key) . "' tidak ditemukan. Periksa kembali isi tabel 'pengaturan' di phpMyAdmin, pastikan tidak ada spasi atau salah ketik.";
        }
    } else {
        $pesan_diagnostik = "Query gagal dieksekusi: " . htmlspecialchars($stmt->error);
    }
    $stmt->close();
} else {
    $pesan_diagnostik = "Query gagal dipersiapkan: " . htmlspecialchars($koneksi->error);
}

// Sertakan header dan sidebar setelah semua logika PHP selesai
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    /* Style tidak berubah, sudah bagus */
    .main-content { padding: 2rem; }
    .table-container { max-width: 800px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    h2 { font-size: 1.8rem; color: #333; margin-bottom: 25px; text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 1rem; }
    .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; }
    .data-table th { background-color: #f8f9fa; font-weight: 500; color: #555; }
    .btn-edit { display: inline-block; padding: 8px 15px; background-color: #007bff; color: #fff; border-radius: 6px; text-decoration: none; transition: background-color 0.3s ease; }
    .btn-edit:hover { background-color: #0056b3; }
    .alert-warning { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 1rem; text-align: center; color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; }
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
    <div class="table-container">
        
        <div class="table-title-header">
            <h2>Data Bendahara</h2>
            <a href="rekap_transaksi.php" class="btn-kembali">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <?php if ($pesan_diagnostik): ?>
            <div class="alert alert-warning"><?= $pesan_diagnostik ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Bendahara</th>
                    <th>Terakhir Diperbarui</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($nama_bendahara) ?></td>
                    <td><?= htmlspecialchars($terakhir_diperbarui) ?></td>
                    <td>
                        <a href="pengaturan_bendahara.php" class="btn-edit">
                            <i class="fas fa-pencil-alt"></i> Edit
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>