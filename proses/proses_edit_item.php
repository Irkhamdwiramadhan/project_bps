<?php
session_start();
include '../includes/koneksi.php';

// =========================================================================
// KEAMANAN: Validasi Awal
// =========================================================================

// 1. Pastikan skrip diakses melalui metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Akses ditolak.");
}

// 2. Cek hak akses pengguna
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];
$has_access = !empty(array_intersect($user_roles, $allowed_roles_for_action));

if (!$has_access) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melakukan tindakan ini.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../manajemen_anggaran.php");
    exit();
}

// 3. Validasi semua input yang diterima dari form
$id_item = filter_input(INPUT_POST, 'id_item', FILTER_VALIDATE_INT);
$nama_item = trim($_POST['nama_item'] ?? '');
$volume = filter_input(INPUT_POST, 'volume', FILTER_VALIDATE_INT);
$satuan = trim($_POST['satuan'] ?? '');
$harga_raw = $_POST['harga'] ?? '0';
$pagu_raw = $_POST['pagu'] ?? '0';
$tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);

if (empty($id_item) || empty($nama_item) || $volume === false || empty($satuan) || empty($tahun)) {
    $_SESSION['flash_message'] = "Semua kolom wajib diisi dan harus dalam format yang benar.";
    $_SESSION['flash_message_type'] = "danger";
    // Redirect kembali ke form edit jika ada data yang tidak valid
    header("Location: ../edit_item.php?id=" . $id_item);
    exit();
}

// =========================================================================
// LOGIKA INTI: Proses Update Data
// =========================================================================

try {
    // Bersihkan dan proses nilai harga dan pagu (dari format ribuan)
    $harga_bersih = (int)preg_replace('/[^0-9]/', '', $harga_raw);
    $harga_final = $harga_bersih * 1000;

    $pagu_bersih = (int)preg_replace('/[^0-9]/', '', $pagu_raw);
    $pagu_final = $pagu_bersih * 1000;
    
    // Siapkan query UPDATE menggunakan prepared statement
    $sql = "UPDATE master_item SET nama_item = ?, satuan = ?, volume = ?, harga = ?, pagu = ? WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception("Gagal mempersiapkan query: " . $koneksi->error);
    }

    // Bind parameter: s (string), i (integer)
    $stmt->bind_param("ssiiii", $nama_item, $satuan, $volume, $harga_final, $pagu_final, $id_item);
    
    // Eksekusi query
    if ($stmt->execute()) {
        $_SESSION['flash_message'] = "Item anggaran berhasil diperbarui.";
        $_SESSION['flash_message_type'] = "success";
    } else {
        throw new Exception("Gagal mengeksekusi query: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    $_SESSION['flash_message'] = "Terjadi kesalahan saat memperbarui data. Pesan: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
}

// Arahkan pengguna kembali ke halaman utama dengan filter tahun yang sesuai
header("Location: ../manajemen_anggaran.php?tahun=" . $tahun);
exit();
?>