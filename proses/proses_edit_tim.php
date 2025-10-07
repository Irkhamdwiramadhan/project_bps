<?php
// ../proses/proses_edit_tim.php

session_start();
include '../includes/koneksi.php';

// Keamanan: Pastikan metode request adalah POST dan pengguna memiliki akses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak sah.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu'];
if (!array_intersect($allowed_roles, $user_roles)) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../tim/halaman_tim.php');
    exit;
}

// Ambil dan bersihkan data dari form
$tim_id = (int)$_POST['tim_id'];
$nama_tim = $koneksi->real_escape_string(trim($_POST['nama_tim']));
$ketua_tim_id = (int)$_POST['ketua_tim_id'];
$anggota_list = $_POST['anggota'] ?? [];

// Validasi
if (empty($tim_id) || empty($nama_tim) || empty($ketua_tim_id)) {
    $_SESSION['error_message'] = "Data tidak lengkap. Semua field wajib diisi.";
    header('Location: ../tim/edit_tim.php?id=' . $tim_id);
    exit;
}

// Proses Database menggunakan Transaksi
$koneksi->begin_transaction();

try {
    // Langkah 1: Update data di tabel 'tim'
    $stmt_update_tim = $koneksi->prepare("UPDATE tim SET nama_tim = ?, ketua_tim_id = ? WHERE id = ?");
    $stmt_update_tim->bind_param("sii", $nama_tim, $ketua_tim_id, $tim_id);
    if (!$stmt_update_tim->execute()) {
        throw new Exception("Gagal mengupdate data tim: " . $stmt_update_tim->error);
    }
    $stmt_update_tim->close();

    // Langkah 2: Hapus semua anggota lama dari tim ini
    $stmt_delete_anggota = $koneksi->prepare("DELETE FROM anggota_tim WHERE tim_id = ?");
    $stmt_delete_anggota->bind_param("i", $tim_id);
    if (!$stmt_delete_anggota->execute()) {
        throw new Exception("Gagal menghapus anggota lama: " . $stmt_delete_anggota->error);
    }
    $stmt_delete_anggota->close();

    // Langkah 3: Masukkan kembali daftar anggota yang baru (jika ada)
    if (!empty($anggota_list)) {
        $stmt_insert_anggota = $koneksi->prepare("INSERT INTO anggota_tim (tim_id, member_id, member_type) VALUES (?, ?, ?)");
        
        foreach ($anggota_list as $anggota_value) {
            list($member_type, $member_id) = explode('-', $anggota_value, 2);
            $member_id = (int)$member_id;

            $stmt_insert_anggota->bind_param("iis", $tim_id, $member_id, $member_type);
            if (!$stmt_insert_anggota->execute()) {
                throw new Exception("Gagal menyimpan anggota baru: " . $stmt_insert_anggota->error);
            }
        }
        $stmt_insert_anggota->close();
    }

    // Jika semua berhasil, commit
    $koneksi->commit();
    $_SESSION['success_message'] = "Data tim berhasil diperbarui!";

} catch (Exception $e) {
    // Jika ada error, batalkan semua
    $koneksi->rollback();
    $_SESSION['error_message'] = "Terjadi kesalahan saat memperbarui data: " . $e->getMessage();
}

// Alihkan kembali ke halaman utama
header('Location: ../pages/halaman_tim.php');
exit;
?>