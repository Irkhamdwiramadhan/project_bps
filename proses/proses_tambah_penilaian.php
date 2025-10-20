<?php
session_start();
include '../includes/koneksi.php';

// Pastikan request menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header('Location: ../pages/tambah_penilaian_mitra.php?status=error&message=' . urlencode('Metode permintaan tidak valid.'));
    exit;
}

try {
    // Tangkap data dari form dengan validasi
    $mitra_survey_id = filter_input(INPUT_POST, 'mitra_survey_id', FILTER_VALIDATE_INT);
    $penilai_id = filter_input(INPUT_POST, 'penilai_id', FILTER_VALIDATE_INT);
    
    // ==========================================================
    // REVISI 1: TANGKAP DATA 'beban_kerja' DARI FORM
    // ==========================================================
    $beban_kerja = filter_input(INPUT_POST, 'beban_kerja', FILTER_VALIDATE_INT);
    
    $kualitas = filter_input(INPUT_POST, 'kualitas', FILTER_VALIDATE_INT);
    $volume_pemasukan = filter_input(INPUT_POST, 'volume_pemasukan', FILTER_VALIDATE_INT);
    $perilaku = filter_input(INPUT_POST, 'perilaku', FILTER_VALIDATE_INT);
    $keterangan = filter_input(INPUT_POST, 'keterangan', FILTER_SANITIZE_STRING);

    // ==========================================================
    // REVISI 2: TAMBAHKAN 'beban_kerja' KE DALAM VALIDASI
    // ==========================================================
    if ($mitra_survey_id === false || $penilai_id === false || $beban_kerja === false || $kualitas === false || $volume_pemasukan === false || $perilaku === false) {
        throw new Exception("Data penilaian tidak lengkap atau tidak valid. Pastikan semua kolom terisi dengan benar.");
    }
    
    // Periksa apakah penilaian untuk mitra_survey_id ini sudah ada (logika ini tetap valid)
    $sql_check = "SELECT COUNT(*) FROM mitra_penilaian_kinerja WHERE mitra_survey_id = ?";
    $stmt_check = $koneksi->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception("Gagal menyiapkan statement cek: " . $koneksi->error);
    }
    
    $stmt_check->bind_param("i", $mitra_survey_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        throw new Exception("Pekerjaan untuk mitra ini sudah pernah dinilai sebelumnya.");
    }

    // ==========================================================
    // REVISI 3: TAMBAHKAN 'beban_kerja' KE DALAM KUERI INSERT
    // ==========================================================
    $sql_insert = "INSERT INTO mitra_penilaian_kinerja 
                   (mitra_survey_id, penilai_id, beban_kerja, kualitas, volume_pemasukan, perilaku, keterangan) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
                   
    $stmt_insert = $koneksi->prepare($sql_insert);
    if (!$stmt_insert) {
        throw new Exception("Gagal menyiapkan statement insert: " . $koneksi->error);
    }

    // ==========================================================
    // REVISI 4: SESUAIKAN bind_param DENGAN DATA BARU
    // ==========================================================
    // Tipe data menjadi 6 integer dan 1 string -> "iiiiis"
    $stmt_insert->bind_param("iiiiiis", 
        $mitra_survey_id, 
        $penilai_id, 
        $beban_kerja, 
        $kualitas, 
        $volume_pemasukan, 
        $perilaku, 
        $keterangan
    );
    
    // Eksekusi kueri
    if ($stmt_insert->execute()) {
        $stmt_insert->close();
        $koneksi->close();
        header('Location: ../pages/penilaian_mitra.php?status=success&message=' . urlencode('Penilaian kinerja berhasil ditambahkan.'));
        exit;
    } else {
        throw new Exception("Gagal menambahkan penilaian kinerja: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    // Tutup koneksi jika masih terbuka
    if (isset($koneksi) && $koneksi->ping()) {
        $koneksi->close();
    }
    header('Location: ../pages/tambah_penilaian_mitra.php?status=error&message=' . urlencode($e->getMessage()));
    exit;
}
?>