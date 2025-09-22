<?php
session_start();
include '../includes/koneksi.php';

// Pastikan user punya hak akses
if (!isset($_SESSION['user_role']) || !in_array('super_admin', $_SESSION['user_role'])) {
    die("Akses Ditolak. Anda tidak memiliki izin untuk menghapus data.");
}

// Ambil parameter
if (isset($_GET['id_item']) && isset($_GET['tahun'])) {
    $id_item = (int) $_GET['id_item'];
    $tahun   = (int) $_GET['tahun'];

    // Cek apakah item ada
    $cek = $koneksi->prepare("SELECT id FROM master_item WHERE id = ?");
    $cek->bind_param("i", $id_item);
    $cek->execute();
    $cek->store_result();

    if ($cek->num_rows > 0) {
        // Hapus item
        $stmt = $koneksi->prepare("DELETE FROM master_item WHERE id = ?");
        $stmt->bind_param("i", $id_item);
        $stmt->execute();
    }

    $cek->close();
}

// Redirect kembali ke halaman master_data.php
header("Location: ../pages/master_data.php?tahun=" . urlencode($tahun));
exit;
