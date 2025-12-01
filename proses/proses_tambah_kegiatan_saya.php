<?php
session_start();
include '../includes/koneksi.php';



// Ambil ID Pegawai dari Session
// Sesuaikan 'user_id' dengan nama session ID user Anda (misal 'id' atau 'id_pegawai')
$pegawai_id = $_SESSION['user_id'] ?? 0;

// 1. Ambil Data
$tanggal        = $_POST['tanggal'];
$jenis_kegiatan = trim($_POST['jenis_kegiatan']);


// 2. Validasi
if ($pegawai_id == 0) {
    die("Error: Session Pegawai hilang. Silakan login ulang.");
}



// 3. Insert Data
$query = "INSERT INTO kegiatan_harian 
          (pegawai_id, tanggal, jenis_kegiatan) 
          VALUES (?, ?, ?)";

$stmt = $koneksi->prepare($query);

$stmt->bind_param("iss", 
    $pegawai_id,
    $tanggal,
    $jenis_kegiatan,
  
);

if ($stmt->execute()) {
    $_SESSION['success'] = "Log harian berhasil disimpan!";
    header('Location: ../pages/kegiatan_saya.php');
} else {
    echo "Error Database: " . $stmt->error;
}

$stmt->close();
$koneksi->close();
?>