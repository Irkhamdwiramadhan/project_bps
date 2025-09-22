<?php
session_start();
include '../includes/koneksi.php';

// Pastikan request menggunakan metode GET dan ada parameter 'id'
if ($_SERVER["REQUEST_METHOD"] !== "GET" || !isset($_GET['id'])) {
    header('Location: ../pages/penilaian_mitra.php?status=error&message=' . urlencode('Permintaan tidak valid.'));
    exit;
}

try {
    // Tangkap ID dari URL dan validasi
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id === false) {
        throw new Exception("ID penilaian tidak valid.");
    }

    // Siapkan kueri DELETE
    $sql_delete = "DELETE FROM mitra_penilaian_kinerja WHERE id = ?";
    $stmt_delete = $koneksi->prepare($sql_delete);
    
    if (!$stmt_delete) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }
    
    $stmt_delete->bind_param("i", $id);
    
    if ($stmt_delete->execute()) {
        $stmt_delete->close();
        header('Location: ../pages/penilaian_mitra.php?status=success&message=' . urlencode('Penilaian berhasil dihapus.'));
        exit;
    } else {
        throw new Exception("Gagal menghapus penilaian: " . $stmt_delete->error);
    }

} catch (Exception $e) {
    header('Location: ../pages/penilaian_mitra.php?status=error&message=' . urlencode($e->getMessage()));
    exit;
}
?>