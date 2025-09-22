<?php
include '../includes/koneksi.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Ambil nama file foto sebelum data dihapus
    $sql_foto = "SELECT foto FROM pegawai WHERE id = $id";
    $result_foto = $koneksi->query($sql_foto);
    $data_foto = $result_foto->fetch_assoc();
    $nama_foto = $data_foto['foto'];

    // Hapus data dari database
    $sql_hapus = "DELETE FROM pegawai WHERE id = $id";

    if ($koneksi->query($sql_hapus) === TRUE) {
        // Hapus file foto dari folder
        if ($nama_foto && file_exists("../assets/img/pegawai/$nama_foto")) {
            unlink("../assets/img/pegawai/$nama_foto");
        }
        header("Location: ../pages/pegawai.php");
    } else {
        echo "Error: " . $sql_hapus . "<br>" . $koneksi->error;
    }
} else {
    echo "ID pegawai tidak ditemukan.";
}

$koneksi->close();
?>