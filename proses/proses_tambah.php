<?php
include '../includes/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $nama = $_POST['nama'];
    $ttl = $_POST['ttl'];
    $pendidikan = $_POST['pendidikan'];
    $email = $_POST['email'];
    $nip = $_POST['nip'];
    $gender = $_POST['gender'];
    $sebagai = $_POST['sebagai'];
    $seksi = $_POST['seksi'];
    $jabatan = $_POST['jabatan'];
    $alamat = $_POST['alamat'];

    // Ambil data untuk kolom baru
    $kecamatan = $_POST['kecamatan'] ?? '';
    $inisial = $_POST['inisial'] ?? '';
    $no_urut = $_POST['no_urut'] ?? 0;
    $nip_bps = $_POST['nip_bps'] ?? '';
    $gol_akhir = $_POST['gol_akhir'] ?? '';
    $status = $_POST['status'] ?? '';
    $tmt_cpns = $_POST['tmt_cpns'];

    // Proses upload foto
    $nama_foto = '';
    if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] === UPLOAD_ERR_OK) {
        $target_dir = "../assets/img/pegawai/";
        $nama_foto = uniqid() . '-' . basename($_FILES["foto"]["name"]);
        $target_file = $target_dir . $nama_foto;
        move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file);
    }

    // Query untuk memasukkan data ke database menggunakan Prepared Statement
    $sql = "INSERT INTO pegawai (nama, ttl, pendidikan, email, nip, gender, sebagai, seksi, jabatan, alamat, foto, kecamatan, inisial, no_urut, nip_bps, gol_akhir, status, tmt_cpns)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("sssssssssssssssssi",
        $nama, $ttl, $pendidikan, $email, $nip, $gender, $sebagai, $seksi, $jabatan, $alamat, $nama_foto,
        $kecamatan, $inisial, $no_urut, $nip_bps, $gol_akhir, $status, $tmt_cpns
    );

    if ($stmt->execute()) {
        header("Location: ../pages/pegawai.php");
    } else {
        error_log("Error saat menyimpan data: " . $stmt->error);
        echo "Error: " . $stmt->error;
    }
    
    $stmt->close();
    $koneksi->close();
}
?>