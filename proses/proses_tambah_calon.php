<?php
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $jenis_penilaian = $_POST['jenis_penilaian'];
    $tahun           = $_POST['tahun'];
    $calon_data      = $_POST['calon_data'];
    
    // Periksa apakah triwulan dikirim (hanya untuk "pegawai_prestasi")
    $triwulan = ($jenis_penilaian === 'pegawai_prestasi') ? $_POST['triwulan'] : null;

    $sukses = true;
    $pesan_error = [];

    // Persiapkan statement untuk insert data.
    // Kolom 'jumlah' perlu ditambahkan ke tabel database Anda.
    $sql_insert = "INSERT INTO calon_triwulan (id_pegawai, tahun, triwulan, jenis_penilaian) VALUES (?, ?, ?, ?)";
    $stmt_insert = $koneksi->prepare($sql_insert);

    foreach ($calon_data as $data) {
        $pegawai_id = $data['id_pegawai'];
    

        // Validasi sederhana
        if (empty($pegawai_id) || empty($tahun) || ($jenis_penilaian === 'pegawai_prestasi' && empty($triwulan))) {
            $pesan_error[] = "Data tidak lengkap untuk salah satu baris.";
            $sukses = false;
            continue;
        }

        // Cek apakah calon sudah ada (berdasarkan jenis_penilaian, tahun, dan triwulan jika ada)
        if ($jenis_penilaian === 'pegawai_prestasi') {
            $sql_check = "SELECT id FROM calon_triwulan WHERE id_pegawai = ? AND tahun = ? AND triwulan = ? AND jenis_penilaian = ?";
            $stmt_check = $koneksi->prepare($sql_check);
            $stmt_check->bind_param("iiss", $pegawai_id, $tahun, $triwulan, $jenis_penilaian);
        } else { // 'can'
            $sql_check = "SELECT id FROM calon_triwulan WHERE id_pegawai = ? AND tahun = ? AND jenis_penilaian = ?";
            $stmt_check = $koneksi->prepare($sql_check);
            $stmt_check->bind_param("iis", $pegawai_id, $tahun, $jenis_penilaian);
        }
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $pesan_error[] = "Pegawai (ID: " . htmlspecialchars($pegawai_id) . ") sudah terdaftar sebagai calon pada periode ini.";
            $sukses = false;
        } else {
            // Lanjutkan insert jika belum terdaftar
            $stmt_insert->bind_param("iiss", $pegawai_id, $tahun, $triwulan, $jenis_penilaian, );
            if (!$stmt_insert->execute()) {
                $pesan_error[] = "Gagal menambahkan pegawai (ID: " . htmlspecialchars($pegawai_id) . "). Error: " . $stmt_insert->error;
                $sukses = false;
            }
        }
        $stmt_check->close();
    }
    
    $stmt_insert->close();
    
    if ($sukses) {
        header("Location: ../pages/calon_berprestasi.php?status=success");
    } else {
        $errorMessage = implode(' ', $pesan_error);
        header("Location: ../pages/calon_berprestasi.php?status=error&message=" . urlencode($errorMessage));
    }
    
} else {
    header("Location: ../pages/calon_berprestasi.php");
}

mysqli_close($koneksi);
exit();
?>