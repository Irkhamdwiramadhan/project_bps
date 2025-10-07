<?php
// ../proses/proses_edit_kegiatan.php

session_start();
include '../includes/koneksi.php';

// Keamanan: Pastikan metode request adalah POST dan pengguna memiliki akses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Akses tidak sah.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu'];
if (count(array_intersect($allowed_roles, (array)$user_roles)) === 0) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// Ambil dan bersihkan data dari form
$id = (int)$_POST['id'];
$nama_kegiatan = $koneksi->real_escape_string(trim($_POST['nama_kegiatan']));
$tim_id = (int)$_POST['tim_id'];
$target = (float)$_POST['target'];
$realisasi = (float)$_POST['realisasi'];
$satuan = $koneksi->real_escape_string(trim($_POST['satuan']));
$batas_waktu = $_POST['batas_waktu'];
$tgl_realisasi = $_POST['tgl_realisasi'];
$keterangan = $koneksi->real_escape_string(trim($_POST['keterangan']));

// Validasi Sederhana
if (empty($id) || empty($nama_kegiatan) || empty($tim_id) || empty($satuan) || empty($batas_waktu)) {
    $_SESSION['error_message'] = "Semua field wajib diisi.";
    // Arahkan kembali ke halaman edit dengan ID yang sama
    header('Location: ../pages/edit_kegiatan.php?id=' . $id);
    exit;
}

// Jika tanggal realisasi kosong, set ke NULL
if (empty($tgl_realisasi)) {
    $tgl_realisasi = NULL;
}

// Proses update ke database menggunakan prepared statement
$stmt = $koneksi->prepare(
    "UPDATE kegiatan SET 
        nama_kegiatan = ?, 
        tim_id = ?, 
        target = ?, 
        realisasi = ?, 
        satuan = ?, 
        batas_waktu = ?, 
        tgl_realisasi = ?, 
        keterangan = ?
     WHERE id = ?"
);

// Bind parameter sesuai urutan placeholder (?)
// Tipe data: s=string, i=integer, d=double
$stmt->bind_param("siddssssi", $nama_kegiatan, $tim_id, $target, $realisasi, $satuan, $batas_waktu, $tgl_realisasi, $keterangan, $id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Data kegiatan berhasil diperbarui!";
} else {
    $_SESSION['error_message'] = "Gagal memperbarui data: " . $stmt->error;
}

$stmt->close();
$koneksi->close();

// Alihkan kembali ke halaman utama kegiatan
header('Location: ../pages/kegiatan_tim.php');
exit;
?>