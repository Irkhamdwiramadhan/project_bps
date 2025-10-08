<?php
// ../proses/proses_tambah_tim.php

session_start();
include '../includes/koneksi.php';

// Pastikan request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak sah.");
}

// RBAC: cek role
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_simpedu'];
$has_access_for_action = false;
foreach ((array)$user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}
if (!$has_access_for_action) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../tim/halaman_tim.php');
    exit;
}

// Ambil data dari form (sesuaikan name di form: 'deskripsi')
$nama_tim = trim($_POST['nama_tim'] ?? '');
$ketua_tim_id = (int) ($_POST['ketua_tim_id'] ?? 0);
$deskripsi = trim($_POST['deskripsi'] ?? '');
$anggota_list = $_POST['anggota'] ?? [];

// Validasi
if ($nama_tim === '' || $ketua_tim_id <= 0) {
    $_SESSION['error_message'] = "Nama Tim dan Ketua Tim wajib diisi.";
    header('Location: ../tim/tambah_tim.php');
    exit;
}

// Mulai transaksi
$koneksi->begin_transaction();
try {
    // Pastikan kolom di DB bernama 'deskripsi' (bukan deskripsi_tim).
    $stmt_tim = $koneksi->prepare("INSERT INTO tim (nama_tim, deskripsi, ketua_tim_id) VALUES (?, ?, ?)");
    if (!$stmt_tim) {
        throw new Exception("Prepare gagal: " . $koneksi->error);
    }
    $stmt_tim->bind_param("ssi", $nama_tim, $deskripsi, $ketua_tim_id);
    if (!$stmt_tim->execute()) {
        throw new Exception("Eksekusi gagal (tim): " . $stmt_tim->error);
    }
    $new_tim_id = $koneksi->insert_id;
    $stmt_tim->close();

    // Simpan anggota (jika ada)
    if (!empty($anggota_list)) {
        $stmt_anggota = $koneksi->prepare("INSERT INTO anggota_tim (tim_id, member_id, member_type) VALUES (?, ?, ?)");
        if (!$stmt_anggota) {
            throw new Exception("Prepare anggota gagal: " . $koneksi->error);
        }

        foreach ($anggota_list as $anggota_value) {
            // ekspektasi format "pegawai-12" atau "mitra-5"
            $parts = explode('-', $anggota_value, 2);
            if (count($parts) !== 2) continue;
            $member_type = $parts[0];
            $member_id = (int) $parts[1];
            if ($member_id <= 0) continue;

            $stmt_anggota->bind_param("iis", $new_tim_id, $member_id, $member_type);
            if (!$stmt_anggota->execute()) {
                throw new Exception("Eksekusi gagal (anggota): " . $stmt_anggota->error);
            }
        }
        $stmt_anggota->close();
    }

    // Commit jika semua OK
    $koneksi->commit();
    $_SESSION['success_message'] = "Tim baru berhasil ditambahkan!";
} catch (Exception $e) {
    // Rollback dan simpan pesan error ke log + session
    $koneksi->rollback();
    error_log("proses_tambah_tim error: " . $e->getMessage()); // cek error log server
    $_SESSION['error_message'] = "Terjadi kesalahan saat menyimpan tim. " . $e->getMessage();
}

// Redirect kembali ke halaman daftar tim
header('Location: ../pages/halaman_tim.php');
exit;
?>
