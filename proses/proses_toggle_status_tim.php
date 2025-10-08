<?php
// ../proses/proses_toggle_status_tim.php

session_start();
include '../includes/koneksi.php';

// Pastikan request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak sah.");
}

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu'];
$has_access = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles)) {
        $has_access = true;
        break;
    }
}

if (!$has_access) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk mengubah status tim.";
    header('Location: ../pages/halaman_tim.php');
    exit;
}

// Ambil data dari form
$tim_id = (int) ($_POST['tim_id'] ?? 0);
if ($tim_id <= 0) {
    $_SESSION['error_message'] = "ID tim tidak valid.";
    header('Location: ../pages/halaman_tim.php');
    exit;
}

// Ambil status saat ini
$result = $koneksi->query("SELECT is_active FROM tim WHERE id = $tim_id LIMIT 1");
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Tim tidak ditemukan.";
    header('Location: ../pages/halaman_tim.php');
    exit;
}
$row = $result->fetch_assoc();
$new_status = $row['is_active'] ? 0 : 1; // toggle

// Update status
$stmt = $koneksi->prepare("UPDATE tim SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $new_status, $tim_id);
if ($stmt->execute()) {
    $_SESSION['success_message'] = $new_status ? "Tim berhasil diaktifkan." : "Tim berhasil dinonaktifkan.";
} else {
    $_SESSION['error_message'] = "Gagal mengubah status tim: " . $stmt->error;
}
$stmt->close();

// Redirect kembali ke halaman daftar tim
header('Location: ../pages/halaman_tim.php');
exit;
?>
