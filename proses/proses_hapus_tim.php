<?php
// File: proses/proses_hapus_tim.php

session_start();
include '../includes/koneksi.php';

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 2. Cek Hak Akses (Hanya Super Admin)
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin'];
$is_allowed = false;

foreach ((array)$user_roles as $role) {
    if (in_array($role, $allowed_roles)) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    // Jika bukan super admin, tolak akses
    header("Location: ../pages/halaman_tim.php?status=error&message=" . urlencode("Anda tidak memiliki izin untuk menghapus tim."));
    exit;
}

// 3. Validasi ID
if (isset($_GET['id'])) {
    $id_tim = intval($_GET['id']);

    // Mulai Transaksi Data (Opsional, agar aman jika ada tabel relasi)
    $koneksi->begin_transaction();

    try {
        // Opsi A: Jika ingin menghapus anggota tim terlebih dahulu (Clean up)
        // $sql_delete_members = "DELETE FROM anggota_tim WHERE tim_id = ?";
        // $stmt_members = $koneksi->prepare($sql_delete_members);
        // $stmt_members->bind_param("i", $id_tim);
        // $stmt_members->execute();
        // $stmt_members->close();

        // Opsi B: Langsung hapus Tim (Pastikan Database ON DELETE CASCADE atau hapus manual seperti opsi A jika error constraint)
        $sql = "DELETE FROM tim WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("i", $id_tim);
            
            if ($stmt->execute()) {
                // Commit jika berhasil
                $koneksi->commit();
                header("Location: ../pages/halaman_tim.php?status=success&message=" . urlencode("Tim berhasil dihapus."));
            } else {
                throw new Exception("Gagal mengeksekusi query: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Database error: " . $koneksi->error);
        }

    } catch (Exception $e) {
        // Rollback jika ada error
        $koneksi->rollback();
        
        // Cek apakah error karena constraint (misal masih ada anggota/kinerja)
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            $msg = "Gagal menghapus! Tim ini masih memiliki data terkait (anggota atau kinerja). Silakan hapus data terkait terlebih dahulu.";
        } else {
            $msg = "Terjadi kesalahan: " . $e->getMessage();
        }
        
        header("Location: ../pages/halaman_tim.php?status=error&message=" . urlencode($msg));
    }

} else {
    header("Location: ../pages/halaman_tim.php?status=error&message=" . urlencode("ID Tim tidak valid."));
}

$koneksi->close();
?>