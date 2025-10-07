<?php
// ../proses/proses_tambah_tim.php

session_start();
include '../includes/koneksi.php';

// 1. Keamanan: Pastikan metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak sah.");
}

// 2. Keamanan: Cek hak akses lagi di sisi server
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_simpedu'];
$has_access_for_action = false;
foreach ($user_roles as $role) {
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

// 3. Ambil dan bersihkan data dari form
$nama_tim = $koneksi->real_escape_string(trim($_POST['nama_tim']));
$ketua_tim_id = (int) $_POST['ketua_tim_id'];
// Ambil anggota sebagai array, jika tidak ada maka default array kosong
$anggota_list = $_POST['anggota'] ?? [];

// 4. Validasi Sederhana
if (empty($nama_tim) || empty($ketua_tim_id)) {
    $_SESSION['error_message'] = "Nama Tim dan Ketua Tim wajib diisi.";
    header('Location: ../tim/tambah_tim.php'); // Kembali ke form
    exit;
}

// 5. Proses Database menggunakan Transaksi
$koneksi->begin_transaction();

try {
    // Langkah 1: Simpan data ke tabel 'tim'
    $stmt_tim = $koneksi->prepare("INSERT INTO tim (nama_tim, ketua_tim_id) VALUES (?, ?)");
    $stmt_tim->bind_param("si", $nama_tim, $ketua_tim_id);
    
    if (!$stmt_tim->execute()) {
        throw new Exception("Gagal menyimpan data tim utama: " . $stmt_tim->error);
    }

    // Ambil ID dari tim yang baru saja dibuat
    $new_tim_id = $koneksi->insert_id;
    $stmt_tim->close();

    // Langkah 2: Simpan data anggota ke tabel 'anggota_tim' jika ada
    if (!empty($anggota_list)) {
        $stmt_anggota = $koneksi->prepare("INSERT INTO anggota_tim (tim_id, member_id, member_type) VALUES (?, ?, ?)");
        
        foreach ($anggota_list as $anggota_value) {
            // Pecah value 'tipe-id' (contoh: 'pegawai-12')
            list($member_type, $member_id) = explode('-', $anggota_value, 2);
            $member_id = (int) $member_id;

            // Bind parameter dan eksekusi untuk setiap anggota
            $stmt_anggota->bind_param("iis", $new_tim_id, $member_id, $member_type);
            if (!$stmt_anggota->execute()) {
                throw new Exception("Gagal menyimpan data anggota: " . $stmt_anggota->error);
            }
        }
        $stmt_anggota->close();
    }

    // Jika semua proses berhasil, commit transaksi
    $koneksi->commit();
    $_SESSION['success_message'] = "Tim baru berhasil ditambahkan!";

} catch (Exception $e) {
    // Jika terjadi error di salah satu langkah, batalkan semua perubahan
    $koneksi->rollback();
    // Simpan pesan error untuk ditampilkan
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
}

// 6. Alihkan kembali ke halaman utama
header('Location: ../pages/halaman_tim.php');
exit;
?>