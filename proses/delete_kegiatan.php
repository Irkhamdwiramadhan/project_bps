<?php
// Pastikan sesi dimulai
session_start();

// Sertakan file koneksi database
include '../includes/koneksi.php';

// Pastikan ID kegiatan dikirim melalui parameter GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Jika ID tidak ditemukan, arahkan kembali dengan pesan error
    header('Location: ../pages/kegiatan.php?status=error&message=' . urlencode('ID kegiatan tidak ditemukan.'));
    exit;
}

// Ambil ID kegiatan dari URL
$id_kegiatan = $_GET['id'];

try {
    // Siapkan kueri SQL untuk menghapus data dari tabel mitra_surveys
    // Gunakan prepared statement untuk keamanan
    $sql = "DELETE FROM mitra_surveys WHERE id = ?";
    $stmt = $koneksi->prepare($sql);

    // Periksa jika prepared statement gagal
    if ($stmt === false) {
        throw new Exception('Gagal menyiapkan statement: ' . $koneksi->error);
    }

    // Ikat parameter ID ke kueri
    $stmt->bind_param("i", $id_kegiatan);

    // Jalankan kueri
    if ($stmt->execute()) {
        // Jika berhasil, alihkan ke halaman sebelumnya atau halaman detail mitra
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=success&message=' . urlencode('Kegiatan mitra berhasil dihapus.'));
    } else {
        // Jika gagal, lemparkan Exception
        throw new Exception('Gagal menghapus kegiatan mitra: ' . $stmt->error);
    }

    // Tutup statement
    $stmt->close();

} catch (Exception $e) {
    // Tangani kesalahan dan alihkan dengan pesan error
    header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode($e->getMessage()));
    exit;
} finally {
    // Tutup koneksi database di semua kasus (baik berhasil atau gagal)
    $koneksi->close();
}
?>