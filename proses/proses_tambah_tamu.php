<?php
session_start();
include '../includes/koneksi.php';

// Cek login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Cek apakah request berasal dari form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Akses tidak sah.";
    header('Location: ../pages/tambah_tamu.php');
    exit;
}

// Ambil data dari form
$tanggal = $_POST['tanggal'] ?? '';
$nama = trim($_POST['nama'] ?? '');
$asal = trim($_POST['asal'] ?? '');
$keperluan = trim($_POST['keperluan'] ?? '');
$jam_datang = $_POST['jam_datang'] ?? '';
$jam_pulang = $_POST['jam_pulang'] ?? '';
$petugas = trim($_POST['petugas'] ?? '');
$foto_path = null;

// Validasi input wajib
if (empty($tanggal) || empty($nama) || empty($asal) || empty($keperluan) || empty($jam_datang) || empty($petugas)) {
    $_SESSION['error_message'] = "Semua kolom wajib diisi kecuali jam pulang dan foto.";
    header('Location: ../pages/tambah_tamu.php');
    exit;
}

// Upload foto jika ada
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
    $upload_dir = '../uploads/tamu/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_info = pathinfo($_FILES['foto']['name']);
    $file_ext = strtolower($file_info['extension']);
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_ext, $allowed_ext)) {
        $new_file_name = 'tamu_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
            $foto_path = 'uploads/tamu/' . $new_file_name;
        } else {
            $_SESSION['error_message'] = "Gagal mengupload foto.";
            header('Location: ../pages/tambah_tamu.php');
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Format file foto tidak valid. Gunakan JPG, JPEG, PNG, atau GIF.";
        header('Location: ../pages/tambah_tamu.php');
        exit;
    }
}

// Simpan ke database
$stmt = $koneksi->prepare("
    INSERT INTO tamu (tanggal, nama, asal, keperluan, jam_datang, jam_pulang, petugas, foto)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssssss", $tanggal, $nama, $asal, $keperluan, $jam_datang, $jam_pulang, $petugas, $foto_path);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Data tamu berhasil disimpan!";
    header('Location: ../pages/tamu.php');
} else {
    $_SESSION['error_message'] = "Gagal menyimpan data tamu: " . $stmt->error;
    header('Location: ../pages/tambah_tamu.php');
}

$stmt->close();
$koneksi->close();
exit;
?>
