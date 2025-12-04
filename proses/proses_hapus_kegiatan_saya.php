<?php
session_start();
include '../includes/koneksi.php';

// 1. Cek Login & Parameter
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_GET['id'])) {
    header('Location: ../pages/kegiatan_saya.php');
    exit;
}

$id_kegiatan = intval($_GET['id']);
$id_user = $_SESSION['user_id'];
$user_roles = $_SESSION['user_role'] ?? [];

// Cek apakah user adalah Admin
$is_admin = in_array('super_admin', $user_roles) || in_array('admin_pegawai', $user_roles);

// 2. Logika Hapus Aman
if ($is_admin) {
    // Jika Admin, hapus langsung tanpa cek kepemilikan
    $stmt = $koneksi->prepare("DELETE FROM kegiatan_harian WHERE id = ?");
    $stmt->bind_param("i", $id_kegiatan);
} else {
    // Jika User Biasa, HANYA hapus jika pegawai_id cocok (Milik sendiri)
    $stmt = $koneksi->prepare("DELETE FROM kegiatan_harian WHERE id = ? AND pegawai_id = ?");
    $stmt->bind_param("ii", $id_kegiatan, $id_user);
}

if ($stmt->execute()) {
    // Cek apakah ada baris yang terhapus
    if ($stmt->affected_rows > 0) {
        header("Location: ../pages/kegiatan_saya.php?status=success&message=" . urlencode("Kegiatan berhasil dihapus."));
    } else {
        // Jika 0 affected rows, berarti ID tidak ditemukan atau bukan milik user
        header("Location: ../pages/kegiatan_saya.php?status=error&message=" . urlencode("Gagal menghapus. Data tidak ditemukan atau Anda tidak memiliki akses."));
    }
} else {
    header("Location: ../pages/kegiatan_saya.php?status=error&message=" . urlencode("Database Error: " . $stmt->error));
}

$stmt->close();
$koneksi->close();
?>