<?php
// ../proses/proses_hapus_kegiatan.php

session_start();
include '../includes/koneksi.php';

// ===================================================================
// 1. KEAMANAN & VALIDASI
// ===================================================================

// Cek hak akses (RBAC), hanya role tertentu yang bisa menghapus
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'ketua_tim'];
if (count(array_intersect($allowed_roles, (array)$user_roles)) === 0) {
    // Jika tidak punya akses, set pesan error dan redirect
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// Ambil ID kegiatan dari URL dan pastikan valid
$id_kegiatan = $_GET['id'] ?? null;
if (!$id_kegiatan || !is_numeric($id_kegiatan)) {
    $_SESSION['error_message'] = "Permintaan tidak valid atau ID kegiatan tidak ditemukan.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// ===================================================================
// 2. PROSES HAPUS DATA
// ===================================================================

// Gunakan prepared statement untuk mencegah SQL Injection
$stmt = $koneksi->prepare("DELETE FROM kegiatan WHERE id = ?");

// Bind parameter 'i' untuk integer
$stmt->bind_param("i", $id_kegiatan);

// Eksekusi query
if ($stmt->execute()) {
    // Periksa apakah ada baris yang benar-benar terhapus
    if ($stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "Kegiatan berhasil dihapus!";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus: Kegiatan tidak ditemukan.";
    }
} else {
    // Jika terjadi error pada database
    $_SESSION['error_message'] = "Gagal menghapus data: " . $stmt->error;
}

// ===================================================================
// 3. TUTUP KONEKSI DAN REDIRECT
// ===================================================================

$stmt->close();
$koneksi->close();

// Alihkan pengguna kembali ke halaman daftar kegiatan
header('Location: ../pages/kegiatan_tim.php');
exit;
?>