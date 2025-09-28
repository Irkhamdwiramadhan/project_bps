<?php
session_start();
// Pastikan path ke file koneksi sudah benar
include '../includes/koneksi.php';

// =========================================================================
// KEAMANAN: Validasi Awal
// =========================================================================

// 1. Pastikan skrip diakses melalui metode POST untuk mencegah eksekusi via URL
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Sebaiknya tidak memberikan informasi detail, cukup hentikan skrip
    die("Akses tidak diizinkan.");
}

// 2. Cek hak akses pengguna dari session
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];
$has_access = !empty(array_intersect($user_roles, $allowed_roles_for_action));

if (!$has_access) {
    // Siapkan pesan error dan redirect jika tidak punya akses
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melakukan tindakan ini.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../manajemen_anggaran.php"); // Pastikan nama file halaman utama benar
    exit();
}

// 3. Validasi input tahun yang diterima dari form
if (!isset($_POST['tahun']) || !filter_var($_POST['tahun'], FILTER_VALIDATE_INT)) {
    $_SESSION['flash_message'] = "Tahun anggaran yang dikirim tidak valid.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../manajemen_anggaran.php");
    exit();
}
$tahun_anggaran = (int)$_POST['tahun'];

// =========================================================================
// LOGIKA INTI: Proses Penghapusan Data
// =========================================================================

// Urutan penghapusan dari tabel anak ke tabel induk untuk menghindari error foreign key
$tables_to_delete = [
    'master_item',
    'master_akun',
    'master_sub_komponen',
    'master_komponen',
    'master_sub_output',
    'master_output',
    'master_kegiatan',
    'master_program'
];

// Gunakan transaksi untuk memastikan semua data terhapus atau tidak sama sekali
$koneksi->begin_transaction();

try {
    // Loop melalui setiap tabel untuk dihapus
    foreach ($tables_to_delete as $table) {
        // REVISI: Menghapus baris 'dummy_table' dan memastikan query dibuat di dalam loop
        $query = "DELETE FROM {$table} WHERE tahun = ?";
        $stmt = $koneksi->prepare($query);
        
        // Cek jika prepare statement gagal
        if ($stmt === false) {
            // Throw exception akan menghentikan proses dan masuk ke blok catch
            throw new Exception("Gagal mempersiapkan query untuk tabel: {$table}. Pesan: " . $koneksi->error);
        }
        
        $stmt->bind_param("i", $tahun_anggaran);
        $stmt->execute();
        
        // Cek jika eksekusi statement gagal
        if ($stmt->error) {
            throw new Exception("Error saat menghapus dari tabel {$table}: " . $stmt->error);
        }
        
        // Tutup statement setelah selesai digunakan
        $stmt->close();
    }

    // Jika semua perintah delete berhasil, simpan perubahan secara permanen
    $koneksi->commit();
    $_SESSION['flash_message'] = "Seluruh data anggaran untuk tahun {$tahun_anggaran} berhasil dihapus.";
    $_SESSION['flash_message_type'] = "success";

} catch (Exception $e) {
    // Jika terjadi satu saja error, batalkan semua perubahan yang sudah terjadi
    $koneksi->rollback();
    $_SESSION['flash_message'] = "Gagal menghapus data anggaran. Pesan: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
}

// REVISI: Pastikan path redirect sudah benar sesuai struktur folder Anda
// Arahkan pengguna kembali ke halaman utama setelah proses selesai
header("Location: ../pages/master_data.php?tahun=" . $tahun_anggaran);
exit();
?>