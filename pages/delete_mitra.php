<?php
session_start();
include '../includes/koneksi.php';


// Periksa apakah ID mitra diberikan
if (!isset($_GET['id'])) {
    header("Location: mitra.php?status=error&message=" . urlencode("ID mitra tidak ditemukan."));
    exit;
}

$mitra_id = $_GET['id'];

// Mulai transaksi
$koneksi->begin_transaction();

try {
    // 1. Hapus semua data honor yang terkait dengan mitra ini terlebih dahulu
    $sql_delete_honor = "DELETE FROM honor_mitra WHERE mitra_id = ?";
    $stmt_honor = $koneksi->prepare($sql_delete_honor);
    if (!$stmt_honor) {
        throw new Exception("Gagal menyiapkan statement hapus honor: " . $koneksi->error);
    }
    $stmt_honor->bind_param("i", $mitra_id);
    if (!$stmt_honor->execute()) {
        throw new Exception("Gagal menghapus data honor: " . $stmt_honor->error);
    }
    $stmt_honor->close();

    // 2. Hapus semua data survei yang terkait dengan mitra ini
    $sql_delete_surveys = "DELETE FROM mitra_surveys WHERE mitra_id = ?";
    $stmt_surveys = $koneksi->prepare($sql_delete_surveys);
    if (!$stmt_surveys) {
        throw new Exception("Gagal menyiapkan statement hapus survei: " . $koneksi->error);
    }
    $stmt_surveys->bind_param("i", $mitra_id);
    if (!$stmt_surveys->execute()) {
        throw new Exception("Gagal menghapus data survei mitra: " . $stmt_surveys->error);
    }
    $stmt_surveys->close();

    // 3. Hapus data mitra dari tabel utama
    $sql_delete_mitra = "DELETE FROM mitra WHERE id = ?";
    $stmt_mitra = $koneksi->prepare($sql_delete_mitra);
    if (!$stmt_mitra) {
        throw new Exception("Gagal menyiapkan statement hapus mitra: " . $koneksi->error);
    }
    $stmt_mitra->bind_param("i", $mitra_id);
    if (!$stmt_mitra->execute()) {
        throw new Exception("Gagal menghapus data mitra: " . $stmt_mitra->error);
    }
    $stmt_mitra->close();

    // Jika semua berhasil, commit transaksi
    $koneksi->commit();

    header("Location: mitra.php?status=success&message=" . urlencode("Mitra berhasil dihapus."));
    exit;

} catch (Exception $e) {
    // Jika ada error, rollback transaksi
    $koneksi->rollback();
    header("Location: mitra.php?status=error&message=" . urlencode("Gagal menghapus mitra: " . $e->getMessage()));
    exit;
} finally {
    // Tutup koneksi
    $koneksi->close();
}
?>