<?php
session_start();
include '../includes/koneksi.php';

// Pastikan hanya request POST dari user yang login
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['loggedin'])) {
    header('Location: ../login.php');
    exit;
}

// 1. Ambil data dari form
$pegawai_id       = isset($_POST['pegawai_id']) ? (int)$_POST['pegawai_id'] : 0;
$tanggal_laporan  = $_POST['tanggal_laporan'] ?? '';
$jam_laporan      = $_POST['jam_laporan'] ?? '';
$tujuan_keluar    = trim($_POST['tujuan_keluar'] ?? '');
$link_gps         = trim($_POST['link_gps'] ?? '');
$foto_path_db     = null;

// 2. Validasi minimal data wajib (GPS tidak wajib)
if (empty($pegawai_id) || empty($tanggal_laporan) || empty($jam_laporan)) {
    $_SESSION['error_message'] = "Kolom wajib (tanggal, jam, dan foto) belum lengkap.";
    header('Location: ../pages/laporan_keluar.php');
    exit;
}

// 3. Proses Upload Foto (wajib)
if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
    $upload_dir = '../uploads/laporan_keluar/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    $file_info   = pathinfo($_FILES['foto']['name']);
    $file_ext    = strtolower($file_info['extension']);
    $allowed_ext = ['jpg', 'jpeg', 'png'];

    if (in_array($file_ext, $allowed_ext)) {
        $new_file_name = 'laporan_' . $pegawai_id . '_' . time() . '.' . $file_ext;
        $destination   = $upload_dir . $new_file_name;

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

// 4. Simpan ke database (link_gps bisa kosong/null)
$stmt = $koneksi->prepare("
    INSERT INTO laporan_keluar (pegawai_id, tanggal_laporan, jam_laporan, tujuan_keluar, foto, link_gps)
    VALUES (?, ?, ?, ?, ?, ?)
");

$link_gps_value = !empty($link_gps) ? $link_gps : null; // jika kosong, simpan NULL
$stmt->bind_param("isssss", $pegawai_id, $tanggal_laporan, $jam_laporan, $tujuan_keluar, $foto_path_db, $link_gps_value);

// 5. Eksekusi query dan feedback ke user
if ($stmt->execute()) {
    $_SESSION['success_message'] = "Laporan berhasil dikirim!";
    header('Location: ../pages/laporan_keluar_list.php');
} else {
    $_SESSION['error_message'] = "Gagal menyimpan laporan: " . $stmt->error;
    header('Location: ../pages/laporan_keluar.php');
}

$stmt->close();
$koneksi->close();
exit;
?>
