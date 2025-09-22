<?php
// Memastikan koneksi terpasang
include '../includes/koneksi.php';

// Cek apakah parameter id dan status ada
if (isset($_GET['id']) && isset($_GET['status'])) {
    // Ambil dan bersihkan input dari URL
    $id = $_GET['id'];
    $status = $_GET['status'];

    // Validasi input status, pastikan hanya 0 atau 1
    if ($status === '0' || $status === '1') {
        // Gunakan prepared statement untuk keamanan
        $stmt = $koneksi->prepare("UPDATE pegawai SET is_active = ? WHERE id = ?");
        
        // Periksa jika prepared statement berhasil
        if ($stmt) {
            $stmt->bind_param("ii", $status, $id);
            $stmt->execute();
            
            // Periksa jika ada baris yang terpengaruh
            if ($stmt->affected_rows > 0) {
                // Beri pesan sukses jika diperlukan
                // echo "Status berhasil diperbarui.";
            } else {
                // Beri pesan jika tidak ada baris yang terpengaruh
                // echo "Tidak ada data yang diperbarui. ID mungkin tidak valid.";
            }

            $stmt->close();
        } else {
            // Error jika prepared statement gagal
            // echo "Error saat menyiapkan statement: " . $koneksi->error;
        }
    } else {
        // Jika nilai status tidak valid
        // echo "Nilai status tidak valid.";
    }

    // Alihkan kembali ke halaman data pegawai
    header("Location: ../pages/pegawai.php");
    exit();

} else {
    // Pesan jika ID atau status tidak ditemukan
    // echo "ID atau status pegawai tidak ditemukan.";
}

$koneksi->close();
?>