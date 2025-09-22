<?php
// Masukkan file koneksi database
include '../includes/koneksi.php';

// Cek apakah request datang dari metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data yang dikirim dari formulir
    $id_penilai = isset($_POST['id_penilai']) ? (int)$_POST['id_penilai'] : 0;
    $triwulan = isset($_POST['triwulan']) ? (int)$_POST['triwulan'] : 0;
    $tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;
    $jenis_penilaian = isset($_POST['jenis_penilaian']) ? htmlspecialchars($_POST['jenis_penilaian']) : '';
    $id_calon_dinilai_array = isset($_POST['id_calon_dinilai']) ? $_POST['id_calon_dinilai'] : [];

    // Validasi sederhana: pastikan data penilai, triwulan, tahun, dan calon ada
    if ($id_penilai == 0 || $triwulan == 0 || $tahun == 0 || empty($id_calon_dinilai_array)) {
        header("Location: ../pages/form_penilaian.php?status=error&message=Data tidak lengkap.");
        exit;
    }

    // Persiapkan query untuk insert data ke tabel 'penilaian_triwulan'
    $sql_insert = "
        INSERT INTO penilaian_triwulan (
            id_calon, 
            id_penilai, 
            triwulan, 
            tahun, 
            jenis_penilaian,
            skor_berorientasi, 
            skor_akuntabel, 
            skor_kompeten, 
            skor_harmonis, 
            skor_loyal, 
            skor_adaptif, 
            skor_kolaboratif,
            komentar, 
            total_skor
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $koneksi->prepare($sql_insert);

    // Proses setiap calon yang dinilai dalam array
    foreach ($id_calon_dinilai_array as $calon_id) {
        // Ambil skor untuk calon saat ini
        $skor_berorientasi   = isset($_POST['skor_berorientasi'][$calon_id]) ? (int)$_POST['skor_berorientasi'][$calon_id] : 0;
        $skor_akuntabel      = isset($_POST['skor_akuntabel'][$calon_id]) ? (int)$_POST['skor_akuntabel'][$calon_id] : 0;
        $skor_kompeten       = isset($_POST['skor_kompeten'][$calon_id]) ? (int)$_POST['skor_kompeten'][$calon_id] : 0;
        $skor_harmonis       = isset($_POST['skor_harmonis'][$calon_id]) ? (int)$_POST['skor_harmonis'][$calon_id] : 0;
        $skor_loyal          = isset($_POST['skor_loyal'][$calon_id]) ? (int)$_POST['skor_loyal'][$calon_id] : 0;
        $skor_adaptif        = isset($_POST['skor_adaptif'][$calon_id]) ? (int)$_POST['skor_adaptif'][$calon_id] : 0;
        $skor_kolaboratif    = isset($_POST['skor_kolaboratif'][$calon_id]) ? (int)$_POST['skor_kolaboratif'][$calon_id] : 0;
        $komentar            = isset($_POST['komentar'][$calon_id]) ? htmlspecialchars($_POST['komentar'][$calon_id]) : '';
    
        // Hitung total skor
        $total_skor = $skor_berorientasi + $skor_akuntabel + $skor_kompeten + $skor_harmonis + $skor_loyal + $skor_adaptif + $skor_kolaboratif;

        // Bind parameter dan eksekusi untuk setiap calon
        // \"iiiisiiiiiiis\" artinya: 4 integer, 1 string, 7 integer, 1 string, 1 integer
        $stmt_insert->bind_param("iiiisiiiiiiisi", 
            $calon_id, 
            $id_penilai, 
            $triwulan, 
            $tahun, 
            $jenis_penilaian,
            $skor_berorientasi, 
            $skor_akuntabel, 
            $skor_kompeten, 
            $skor_harmonis, 
            $skor_loyal, 
            $skor_adaptif, 
            $skor_kolaboratif,
            $komentar,
            $total_skor
        );
        
        if (!$stmt_insert->execute()) {
            // Jika ada kesalahan, redirect dengan pesan error
            header("Location: ../pages/form_penilaian.php?status=error&message=" . urlencode($stmt_insert->error));
            $stmt_insert->close();
            mysqli_close($koneksi);
            exit;
        }
    }

    // Tutup statement
    $stmt_insert->close();

    // Jika semua data berhasil dimasukkan, redirect dengan status sukses
    header("Location: ../pages/form_penilaian.php?status=success");
    exit;

} else {
    // Jika bukan metode POST, tolak akses
    header("Location: ../pages/form_penilaian.php?status=error&message=Metode permintaan tidak valid.");
    exit;
}
?>