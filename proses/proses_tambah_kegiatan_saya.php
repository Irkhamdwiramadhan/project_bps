<?php
session_start();
include '../includes/koneksi.php';

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $id_pegawai = $_SESSION['user_id'];
    $tanggal = $_POST['tanggal'];
    $jenis = $_POST['jenis_kegiatan'];
    $uraian = trim($_POST['uraian']);
    
    // SET DEFAULT JAM (Karena form jam dihapus)
    // Anda bisa mengubah ini menjadi 00:00:00 jika lebih suka kosong
    $jam_mulai = "07:30:00"; 
    $jam_selesai = "16:00:00";

    if (empty($tanggal) || empty($jenis) || empty($uraian)) {
        header("Location: ../pages/tambah_kegiatan_saya.php?status=error&message=" . urlencode("Semua kolom wajib diisi."));
        exit;
    }

    $query = "INSERT INTO kegiatan_harian (pegawai_id, tanggal, jam_mulai, jam_selesai, jenis_kegiatan, uraian) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("isssss", $id_pegawai, $tanggal, $jam_mulai, $jam_selesai, $jenis, $uraian);

    if ($stmt->execute()) {
        header("Location: ../pages/kegiatan_saya.php?status=success");
    } else {
        header("Location: ../pages/tambah_kegiatan_saya.php?status=error&message=" . urlencode($stmt->error));
    }
    
    $stmt->close();
    $koneksi->close();
} else {
    header("Location: ../pages/tambah_kegiatan_saya.php");
    exit;
}
?>