<?php
session_start();
include '../includes/koneksi.php';

// Pastikan request menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header('Location: ../pages/tambah_penilaian_mitra.php?status=error&message=' . urlencode('Metode permintaan tidak valid.'));
    exit;
}

// Inisialisasi variabel redirect default (jika terjadi error fatal di awal)
$redirect_params = "";

try {
    // 1. Tangkap Data Wajib
    $mitra_survey_id = isset($_POST['mitra_survey_id']) ? intval($_POST['mitra_survey_id']) : 0;
    $penilai_id      = isset($_POST['penilai_id']) ? intval($_POST['penilai_id']) : 0;

    // 2. Tangkap Data Nilai
    $kualitas        = isset($_POST['kualitas']) ? intval($_POST['kualitas']) : 0;
    $volume          = isset($_POST['volume_pemasukan']) ? intval($_POST['volume_pemasukan']) : 0;
    $perilaku        = isset($_POST['perilaku']) ? intval($_POST['perilaku']) : 0;
    $keterangan      = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
    
    // Beban kerja (Opsional/Boleh Null)
    $beban_kerja     = !empty($_POST['beban_kerja']) ? intval($_POST['beban_kerja']) : null;

    // =======================================================================
    // LANGKAH 1: AMBIL INFO CONTEXT (TIM & TAHUN) UNTUK REDIRECT
    // =======================================================================
    // Kita perlu tahu mitra ini ada di Tim mana dan Tahun berapa
    // agar setelah simpan, kita kembalikan user ke halaman filter yang benar.
    $sql_context = "SELECT ms.tim_id, hm.tahun_pembayaran 
                    FROM mitra_surveys ms
                    JOIN honor_mitra hm ON ms.id = hm.mitra_survey_id
                    WHERE ms.id = ?";
    $stmt_ctx = $koneksi->prepare($sql_context);
    $stmt_ctx->bind_param("i", $mitra_survey_id);
    $stmt_ctx->execute();
    $res_ctx = $stmt_ctx->get_result();
    $ctx_data = $res_ctx->fetch_assoc();
    $stmt_ctx->close();

    if ($ctx_data) {
        // Set parameter agar user kembali ke filter yang sama
        $redirect_params = "&tim_id=" . $ctx_data['tim_id'] . "&tahun=" . $ctx_data['tahun_pembayaran'];
    }

    // =======================================================================
    // LANGKAH 2: VALIDASI INPUT
    // =======================================================================
    if ($mitra_survey_id === 0 || $penilai_id === 0) {
        throw new Exception("ID Data tidak valid.");
    }
    if ($kualitas < 1 || $volume < 1 || $perilaku < 1) {
        throw new Exception("Nilai Kualitas, Volume, dan Perilaku wajib diisi (Skala 1-4).");
    }

    // =======================================================================
    // LANGKAH 3: CEK DUPLIKASI
    // =======================================================================
    $sql_check = "SELECT id FROM mitra_penilaian_kinerja WHERE mitra_survey_id = ?";
    $stmt_check = $koneksi->prepare($sql_check);
    $stmt_check->bind_param("i", $mitra_survey_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        throw new Exception("Pekerjaan mitra ini sudah dinilai sebelumnya.");
    }
    $stmt_check->close();

    // =======================================================================
    // LANGKAH 4: INSERT DATA
    // =======================================================================
    $sql_insert = "INSERT INTO mitra_penilaian_kinerja 
                  (mitra_survey_id, penilai_id, beban_kerja, kualitas, volume_pemasukan, perilaku, keterangan, tanggal_penilaian) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt_insert = $koneksi->prepare($sql_insert);
    if (!$stmt_insert) {
        throw new Exception("Gagal prepare: " . $koneksi->error);
    }

    // Bind param (iiiiiis): 
    // id, id, int(null), int, int, int, string
    $stmt_insert->bind_param(
        "iiiiiis",
        $mitra_survey_id,
        $penilai_id,
        $beban_kerja, // Bisa null
        $kualitas,
        $volume,
        $perilaku,
        $keterangan
    );

    if ($stmt_insert->execute()) {
        $stmt_insert->close();
        $koneksi->close();
        
        // REDIRECT SUKSES (Kembali ke halaman tambah dengan filter aktif)
        header("Location: ../pages/tambah_penilaian_mitra.php?status=success&message=" . urlencode('Penilaian berhasil disimpan.') . $redirect_params);
        exit;
    } else {
        throw new Exception("Gagal menyimpan ke database: " . $stmt_insert->error);
    }

} catch (Exception $e) {
    if (isset($koneksi)) $koneksi->close();
    
    // REDIRECT ERROR (Tetap pertahankan filter tim/tahun jika ada)
    header("Location: ../pages/tambah_penilaian_mitra.php?status=error&message=" . urlencode($e->getMessage()) . $redirect_params);
    exit;
}
?>