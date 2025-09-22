<?php
// Masukkan file koneksi database
include '../includes/koneksi.php';

// Pastikan parameter 'id' ada dan merupakan angka
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_calon = intval($_GET['id']);
    
    // Ambil parameter triwulan dan tahun untuk redirect kembali
    $filter_triwulan = isset($_GET['triwulan']) ? $_GET['triwulan'] : '';
    $filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';

    // Siapkan query untuk menghapus data menggunakan prepared statement
    $sql_delete = "DELETE FROM calon_triwulan WHERE id = ?";
    $stmt = $koneksi->prepare($sql_delete);
    
    // Bind parameter dan eksekusi
    $stmt->bind_param("i", $id_calon);

    if ($stmt->execute()) {
        // Jika berhasil, redirect kembali ke halaman calon_berprestasi
        $redirect_url = "../pages/calon_berprestasi.php?status=success_delete";
        // Tambahkan filter jika ada
        if ($filter_triwulan && $filter_tahun) {
            $redirect_url .= "&triwulan=" . urlencode($filter_triwulan) . "&tahun=" . urlencode($filter_tahun);
        }
        header("Location: " . $redirect_url);
    } else {
        // Jika gagal, redirect dengan status error
        $redirect_url = "../pages/calon_berprestasi.php?status=error&message=" . urlencode($koneksi->error);
        if ($filter_triwulan && $filter_tahun) {
            $redirect_url .= "&triwulan=" . urlencode($filter_triwulan) . "&tahun=" . urlencode($filter_tahun);
        }
        header("Location: " . $redirect_url);
    }
    
    // Tutup statement
    $stmt->close();
} else {
    // Jika parameter tidak valid, redirect dengan pesan error
    header("Location: ../pages/calon_berprestasi.php?status=error&message=ID calon tidak valid.");
}

// Tutup koneksi
mysqli_close($koneksi);
exit();
?>
