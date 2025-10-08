<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin'])) {
    die("Akses tidak sah.");
}

// 1. Ambil data, termasuk 'tujuan_keluar'
$pegawai_id = (int)$_POST['pegawai_id'];
$tanggal_laporan = $_POST['tanggal_laporan'];
$jam_laporan = $_POST['jam_laporan'];
$tujuan_keluar = trim($_POST['tujuan_keluar']); // <-- PERUBAHAN: Data baru diambil
$link_gps = trim($_POST['link_gps']);
$foto_path_db = null;

// 2. Validasi, tambahkan 'tujuan_keluar'
if (empty($pegawai_id) || empty($tanggal_laporan) || empty($jam_laporan) || empty($tujuan_keluar) || empty($link_gps)) {
    $_SESSION['error_message'] = "Semua kolom wajib diisi.";
    header('Location: ../pages/laporan_keluar.php');
    exit;
}

// 3. Proses Upload Foto (tidak ada perubahan di sini)
if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
    $upload_dir = '../uploads/laporan_keluar/';
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0775, true); }
    $file_info = pathinfo($_FILES['foto']['name']);
    $file_ext = strtolower($file_info['extension']);
    $allowed_ext = ['jpg', 'jpeg', 'png'];

    if (in_array($file_ext, $allowed_ext)) {
        $new_file_name = 'laporan_' . $pegawai_id . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
            $foto_path_db = 'uploads/laporan_keluar/' . $new_file_name;
        } else {
            $_SESSION['error_message'] = "Gagal mengupload file foto.";
            header('Location: ../pages/laporan_keluar.php');
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Format file foto tidak valid. Hanya JPG, JPEG, dan PNG yang diizinkan.";
        header('Location: ../pages/laporan_keluar.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Foto dokumentasi wajib diupload.";
    header('Location: ../pages/laporan_keluar.php');
    exit;
}

// 4. Simpan ke database, tambahkan 'tujuan_keluar'
$stmt = $koneksi->prepare(
    // PERUBAHAN: Query INSERT diperbarui
    "INSERT INTO laporan_keluar (pegawai_id, tanggal_laporan, jam_laporan, tujuan_keluar, foto, link_gps) 
     VALUES (?, ?, ?, ?, ?, ?)"
);
// PERUBAHAN: bind_param diperbarui
$stmt->bind_param("isssss", $pegawai_id, $tanggal_laporan, $jam_laporan, $tujuan_keluar, $foto_path_db, $link_gps);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Laporan berhasil dikirim!";
    header('Location: ../pages/laporan_keluar_list.php'); // Arahkan ke dashboard
} else {
    $_SESSION['error_message'] = "Gagal menyimpan laporan: " . $stmt->error;
    header('Location: ../pages/laporan_keluar.php');
}

$stmt->close();
$koneksi->close();
exit;
?>