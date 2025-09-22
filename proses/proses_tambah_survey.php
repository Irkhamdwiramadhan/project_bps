<?php
// Sertakan file koneksi database
include '../includes/koneksi.php';

// Pastikan request method adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/tambah_survey.php?status=error&message=Invalid_request_method');
    exit;
}

// Ambil data dari formulir
$nama_survei = $_POST['nama_survei'] ?? '';
$singkatan_survei = $_POST['singkatan_survei'] ?? '';
$satuan = $_POST['satuan'] ?? '';
$seksi_terdahulu = $_POST['seksi_terdahulu'] ?? '';
$nama_tim_sekarang = $_POST['nama_tim_sekarang'] ?? '';

// Validasi sederhana
if (empty($nama_survei)) {
    header('Location: ../pages/tambah_survey.php?status=error&message=Nama_survei_tidak_boleh_kosong');
    exit;
}

// Persiapkan pernyataan SQL menggunakan prepared statement untuk mencegah SQL injection
$sql = "INSERT INTO surveys (nama_survei, singkatan_survei, satuan, seksi_terdahulu, nama_tim_sekarang) VALUES (?, ?, ?, ?, ?)";

if ($stmt = $koneksi->prepare($sql)) {
    // Bind parameter
    $stmt->bind_param("sssss", $nama_survei, $singkatan_survei, $satuan, $seksi_terdahulu, $nama_tim_sekarang);
    
    // Eksekusi pernyataan
    if ($stmt->execute()) {
        header('Location: ../pages/jenis_surveys.php?status=success&message=Survei_berhasil_ditambahkan');
    } else {
        header('Location: ../pages/tambah_survey.php?status=error&message=Gagal_menambahkan_survei');
    }
    
    // Tutup pernyataan
    $stmt->close();
} else {
    header('Location: ../pages/tambah_survey.php?status=error&message=Error_persiapan_query');
}

// Tutup koneksi
$koneksi->close();
?>