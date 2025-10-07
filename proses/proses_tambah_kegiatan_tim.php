<?php
// ../proses/proses_tambah_kegiatan.php

session_start();
include '../includes/koneksi.php';

// Keamanan: Pastikan metode request adalah POST dan pengguna memiliki akses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Hentikan eksekusi jika bukan metode POST
    http_response_code(405); // Method Not Allowed
    die("Akses tidak sah.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu'];
if (!array_intersect($allowed_roles, $user_roles)) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// ===================================================================
// PENGAMBILAN & PEMBERSIHAN DATA (TERMASUK KOLOM BARU)
// ===================================================================
$nama_kegiatan = $koneksi->real_escape_string(trim($_POST['nama_kegiatan']));
$tim_id = (int)$_POST['tim_id'];
$target = (float)$_POST['target'];
$realisasi = (float)$_POST['realisasi']; // <-- DATA BARU
$satuan = $koneksi->real_escape_string(trim($_POST['satuan']));
$batas_waktu = $_POST['batas_waktu'];
$tgl_realisasi = $_POST['tgl_realisasi']; // <-- DATA BARU (OPSIONAL)
$keterangan = $koneksi->real_escape_string(trim($_POST['keterangan']));

// ===================================================================
// VALIDASI DATA
// ===================================================================
// Periksa field wajib diisi. `realisasi` bisa 0, jadi periksa dengan `isset`.
if (empty($nama_kegiatan) || empty($tim_id) || !isset($_POST['target']) || !isset($_POST['realisasi']) || empty($satuan) || empty($batas_waktu)) {
    $_SESSION['error_message'] = "Semua field wajib diisi dengan benar.";
    header('Location: ../pages/tambah_kegiatan_tim.php'); // Kembali ke form
    exit;
}

// Penanganan untuk tanggal realisasi yang opsional
// Jika kosong, set nilainya ke NULL agar valid di database
if (empty($tgl_realisasi)) {
    $tgl_realisasi = NULL;
}

// ===================================================================
// PROSES SIMPAN KE DATABASE (DENGAN QUERY YANG DIPERBARUI)
// ===================================================================
$stmt = $koneksi->prepare(
    // Query diupdate dengan kolom 'realisasi' dan 'tgl_realisasi'
    "INSERT INTO kegiatan (nama_kegiatan, tim_id, target, realisasi, satuan, batas_waktu, tgl_realisasi, keterangan) 
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

// bind_param diupdate sesuai jumlah dan tipe data kolom baru
// Tipe data: s=string, i=integer, d=double (untuk float/decimal)
// urutan: nama_kegiatan(s), tim_id(i), target(d), realisasi(d), satuan(s), batas_waktu(s), tgl_realisasi(s), keterangan(s)
$stmt->bind_param("siddssss", $nama_kegiatan, $tim_id, $target, $realisasi, $satuan, $batas_waktu, $tgl_realisasi, $keterangan);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Kegiatan baru berhasil ditambahkan!";
} else {
    // Pesan error lebih spesifik untuk debugging
    $_SESSION['error_message'] = "Gagal menyimpan data: " . $stmt->error;
}

$stmt->close();
$koneksi->close();

// Alihkan kembali ke halaman utama kegiatan
header('Location: ../pages/kegiatan_tim.php');
exit;
?>