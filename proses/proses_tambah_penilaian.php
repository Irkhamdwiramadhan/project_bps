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
    $kualitas = filter_input(INPUT_POST, 'kualitas', FILTER_VALIDATE_INT);
    $volume_pemasukan = filter_input(INPUT_POST, 'volume_pemasukan', FILTER_VALIDATE_INT);
    $perilaku = filter_input(INPUT_POST, 'perilaku', FILTER_VALIDATE_INT);
    
    // Tangkap data 'keterangan'
    // Gunakan FILTER_SANITIZE_STRING untuk membersihkan input teks
    $keterangan = filter_input(INPUT_POST, 'keterangan', FILTER_SANITIZE_STRING);

    // Validasi data: pastikan semua input yang diperlukan valid
    if ($mitra_survey_id === false || $penilai_id === false || $kualitas === false || $volume_pemasukan === false || $perilaku === false) {
        throw new Exception("Data penilaian tidak lengkap atau tidak valid.");
    }
    
    // Periksa apakah penilaian untuk mitra_survey_id ini sudah ada
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
        throw new Exception("Survey untuk mitra ini sudah pernah dinilai.");
    }

    // Siapkan kueri INSERT untuk menyimpan data
    $sql_insert = "INSERT INTO mitra_penilaian_kinerja (mitra_survey_id, penilai_id, kualitas, volume_pemasukan, perilaku, keterangan) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = $koneksi->prepare($sql_insert);
    
    if (!$stmt_insert) {
        throw new Exception("Gagal menyiapkan statement insert: " . $koneksi->error);
    }

    // Ikat parameter ke kueri
    // PERBAIKAN: Ubah "iiiiis" menjadi "iiiiis"
    $stmt_insert->bind_param("iiiiis", $mitra_survey_id, $penilai_id, $kualitas, $volume_pemasukan, $perilaku, $keterangan);
    
    // Eksekusi kueri
    if ($stmt_insert->execute()) {
        $stmt_insert->close();
        header('Location: ../pages/penilaian_mitra.php?status=success&message=' . urlencode('Penilaian kinerja berhasil ditambahkan.'));
        exit;
    } else {
        throw new Exception("Gagal menambahkan penilaian kinerja: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    header('Location: ../pages/tambah_penilaian_mitra.php?status=error&message=' . urlencode($e->getMessage()));
    exit;
}
?>