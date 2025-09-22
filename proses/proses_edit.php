<?php
include '../includes/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $ttl = $_POST['ttl'];
    $pendidikan = $_POST['pendidikan'];
    $email = $_POST['email'];
    $nip = $_POST['nip'];
    $gender = $_POST['gender'];
    $sebagai = $_POST['sebagai'];
    $seksi = $_POST['seksi'];
    $foto_lama = $_POST['foto_lama'];
    $jabatan = $_POST['jabatan'];
    $alamat = $_POST['alamat'];

    // Menambahkan variabel untuk kolom baru
    $kecamatan = $_POST['kecamatan'] ?? '';
    $inisial = $_POST['inisial'] ?? '';
    $no_urut = $_POST['no_urut'] ?? 0;
    $nip_bps = $_POST['nip_bps'] ?? '';
    $gol_akhir = $_POST['gol_akhir'] ?? '';
    $status = $_POST['status'] ?? '';
    $tmt_cpns = $_POST['tmt_cpns'] ?? NULL;

    $nama_foto = $foto_lama;
    if ($_FILES['foto']['name']) {
        if ($foto_lama && file_exists("../assets/img/pegawai/$foto_lama")) {
            unlink("../assets/img/pegawai/$foto_lama");
        }
        $target_dir = "../assets/img/pegawai/";
        $nama_foto = basename($_FILES["foto"]["name"]);
        $target_file = $target_dir . $nama_foto;
        move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file);
    }

    $sql = "UPDATE pegawai SET
        nama = ?,
        ttl = ?,
        pendidikan = ?,
        email = ?,
        nip = ?,
        gender = ?,
        sebagai = ?,
        seksi = ?,
        jabatan = ?,
        alamat = ?,
        foto = ?,
        kecamatan = ?,
        inisial = ?,
        no_urut = ?,
        nip_bps = ?,
        gol_akhir = ?,
        status = ?,
        tmt_cpns = ?
    WHERE id = ?";

    $stmt = $koneksi->prepare($sql);
    // Perbaikan: 17x 's' untuk string, 2x 'i' untuk integer
    $stmt->bind_param("ssssssssssssssssssi",
        $nama, $ttl, $pendidikan, $email, $nip, $gender, $sebagai, $seksi, $jabatan, $alamat, $nama_foto,
        $kecamatan, $inisial, $no_urut, $nip_bps, $gol_akhir, $status, $tmt_cpns, $id
    );

    if ($stmt->execute()) {
        header("Location: ../pages/pegawai.php");
    } else {
        error_log("Error saat mengupdate data: " . $stmt->error);
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

$koneksi->close();
?>