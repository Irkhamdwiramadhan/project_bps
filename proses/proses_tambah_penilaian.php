<?php
session_start();
include '../includes/koneksi.php';

// Pastikan request menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header('Location: ../pages/tambah_penilaian_mitra.php?status=error&message=' . urlencode('Metode permintaan tidak valid.'));
    exit;
}

try {
    // Tangkap data dari form dengan validasi dasar
    $mitra_survey_id = filter_input(INPUT_POST, 'mitra_survey_id', FILTER_VALIDATE_INT);
    $penilai_id = filter_input(INPUT_POST, 'penilai_id', FILTER_VALIDATE_INT);

    // ==========================================================
    // REVISI: beban_kerja boleh kosong, maka gunakan FILTER_DEFAULT
    // ==========================================================
    $beban_kerja_input = $_POST['beban_kerja'] ?? null;
    $beban_kerja = ($beban_kerja_input !== '' && $beban_kerja_input !== null) 
        ? filter_var($beban_kerja_input, FILTER_VALIDATE_INT)
        : null;

    $kualitas = filter_input(INPUT_POST, 'kualitas', FILTER_VALIDATE_INT);
    $volume_pemasukan = filter_input(INPUT_POST, 'volume_pemasukan', FILTER_VALIDATE_INT);
    $perilaku = filter_input(INPUT_POST, 'perilaku', FILTER_VALIDATE_INT);
    $keterangan = filter_input(INPUT_POST, 'keterangan', FILTER_SANITIZE_STRING);

    // ==========================================================
    // Validasi wajib isi (beban_kerja tidak termasuk)
    // ==========================================================
    if ($mitra_survey_id === false || $penilai_id === false || 
        $kualitas === false || $volume_pemasukan === false || $perilaku === false) {
        throw new Exception("Data penilaian tidak lengkap atau tidak valid. Pastikan semua kolom wajib terisi dengan benar.");
    }

    // Cek apakah sudah ada penilaian untuk mitra_survey_id ini
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
    // INSERT: kolom beban_kerja boleh NULL
    // ==========================================================
    $sql_insert = "INSERT INTO mitra_penilaian_kinerja 
                   (mitra_survey_id, penilai_id, beban_kerja, kualitas, volume_pemasukan, perilaku, keterangan) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt_insert = $koneksi->prepare($sql_insert);
    if (!$stmt_insert) {
        throw new Exception("Gagal menyiapkan statement insert: " . $koneksi->error);
    }

    // Bind param (beban_kerja bisa null, jadi pastikan pakai 'i' dan nilai null diganti null literal)
    $stmt_insert->bind_param(
        "iiiiiis",
        $mitra_survey_id,
        $penilai_id,
        $beban_kerja,
        $kualitas,
        $volume_pemasukan,
        $perilaku,
        $keterangan
    );

    if ($stmt_insert->execute()) {
        $stmt_insert->close();
        $koneksi->close();
        header('Location: ../pages/penilaian_mitra.php?status=success&message=' . urlencode('Penilaian kinerja berhasil ditambahkan.'));
        exit;
    } else {
        throw new Exception("Gagal menambahkan penilaian kinerja: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    if (isset($koneksi) && $koneksi->ping()) {
        $koneksi->close();
    }
    header('Location: ../pages/tambah_penilaian_mitra.php?status=error&message=' . urlencode($e->getMessage()));
    exit;
}
?>
