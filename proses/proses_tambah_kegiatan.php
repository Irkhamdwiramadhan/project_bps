<?php
session_start();
include "../includes/koneksi.php";

// Batas maksimal honor per bulan.
$max_honor_per_month = 5000000;

// Pastikan request method adalah POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Akses tidak valid."));
    exit;
}

// Ambil input dari form
$survei_id = $_POST['survei_id'] ?? null;
$mitra_ids = $_POST['mitra_id'] ?? [];
$jumlah_satuan_array = $_POST['jumlah_satuan'] ?? [];
$honor_per_satuan_val = (float) ($_POST['harga_per_satuan'] ?? 0);
$periode_jenis = $_POST['periode_jenis'] ?? null;
$periode_nilai = $_POST['periode_nilai'] ?? null;

// Validasi input
if (empty($survei_id) || empty($mitra_ids) || $honor_per_satuan_val <= 0 || empty($periode_jenis) || empty($periode_nilai)) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Data kegiatan tidak lengkap. Harap lengkapi semua field dengan benar."));
    exit;
}

$stmt_select = null;
$stmt_check_honor = null;
$stmt_insert_survey = null;
$stmt_insert_honor = null;
$mitra_over_limit = [];

try {
    // Memulai transaksi
    $koneksi->begin_transaction();

    // 1. Siapkan statement untuk mengecek honor bulanan mitra
    $sql_check_honor = "SELECT SUM(total_honor) AS total_honor_bulan_ini FROM honor_mitra WHERE mitra_id = ? AND MONTH(tanggal_input) = MONTH(CURDATE()) AND YEAR(tanggal_input) = YEAR(CURDATE())";
    $stmt_check_honor = $koneksi->prepare($sql_check_honor);
    if (!$stmt_check_honor) {
        throw new Exception("Gagal menyiapkan statement cek honor: " . $koneksi->error);
    }
    $stmt_check_honor->bind_param("i", $mitra_id_check);

    // 2. Siapkan kueri INSERT untuk tabel mitra_surveys dan honor_mitra
    $sql_insert_survey = "INSERT INTO mitra_surveys (mitra_id, survei_id, survey_ke_berapa, periode_jenis, periode_nilai) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert_survey = $koneksi->prepare($sql_insert_survey);
    if (!$stmt_insert_survey) {
        throw new Exception("Gagal menyiapkan statement INSERT untuk mitra_surveys: " . $koneksi->error);
    }
    $stmt_insert_survey->bind_param("iiiss", $mitra_id_insert_survey, $survei_id, $survey_ke_berapa, $periode_jenis, $periode_nilai);

    $sql_insert_honor = "INSERT INTO honor_mitra (mitra_id, survei_id, honor_per_satuan, jumlah_satuan, total_honor, tanggal_input) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert_honor = $koneksi->prepare($sql_insert_honor);
    if (!$stmt_insert_honor) {
        throw new Exception("Gagal menyiapkan statement INSERT untuk honor_mitra: " . $koneksi->error);
    }
    $stmt_insert_honor->bind_param("iiidss", $mitra_id_insert_honor, $survei_id_honor, $honor_per_satuan_val_honor, $jumlah_satuan_val_honor, $total_honor, $tanggal_input);

    // Looping untuk setiap mitra yang dipilih
    foreach ($mitra_ids as $i => $mitra_id) {
        $jumlah_satuan_val = (float) ($jumlah_satuan_array[$i] ?? 0);
        
        // Cek apakah jumlah satuan valid
        if ($jumlah_satuan_val <= 0) {
            $koneksi->rollback();
            header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Jumlah satuan untuk mitra " . htmlspecialchars($mitra_id) . " tidak valid."));
            exit;
        }
        
        $total_honor_baru = $honor_per_satuan_val * $jumlah_satuan_val;

        // Cek total honor mitra bulan ini
        $mitra_id_check = $mitra_id;
        $stmt_check_honor->execute();
        $result_check = $stmt_check_honor->get_result();
        $row_check = $result_check->fetch_assoc();
        $current_honor = $row_check['total_honor_bulan_ini'] ?? 0;

        // Cek apakah honor melebihi batas
        if (($current_honor + $total_honor_baru) > $max_honor_per_month) {
            $mitra_over_limit[] = $mitra_id;
            continue; // Lanjutkan ke mitra berikutnya tanpa menyimpan
        }

        // Dapatkan nilai survey_ke_berapa yang berikutnya
        $sql_select = "SELECT MAX(survey_ke_berapa) AS max_survey FROM mitra_surveys WHERE survei_id = ? AND mitra_id = ?";
        $stmt_select = $koneksi->prepare($sql_select);
        if (!$stmt_select) {
            throw new Exception("Gagal menyiapkan statement SELECT: " . $koneksi->error);
        }
        $stmt_select->bind_param("ii", $survei_id, $mitra_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $row = $result->fetch_assoc();
        $survey_ke_berapa = ($row['max_survey'] !== null) ? $row['max_survey'] + 1 : 1;
        $stmt_select->close();

        // Eksekusi INSERT ke tabel mitra_surveys
        $mitra_id_insert_survey = $mitra_id;
        if (!$stmt_insert_survey->execute()) {
            throw new Exception("Gagal menyimpan data kegiatan untuk mitra ID " . htmlspecialchars($mitra_id) . ": " . $stmt_insert_survey->error);
        }

        // Eksekusi INSERT ke tabel honor_mitra
        $mitra_id_insert_honor = $mitra_id;
        $survei_id_honor = $survei_id;
        $honor_per_satuan_val_honor = $honor_per_satuan_val;
        $jumlah_satuan_val_honor = $jumlah_satuan_val;
        $total_honor = $total_honor_baru;
        $tanggal_input = date('Y-m-d H:i:s');
        if (!$stmt_insert_honor->execute()) {
            throw new Exception("Gagal menyimpan data honor untuk mitra ID " . htmlspecialchars($mitra_id) . ": " . $stmt_insert_honor->error);
        }
    }

    // Jika ada mitra yang over limit, batalkan transaksi dan beri pesan error
    if (!empty($mitra_over_limit)) {
        $koneksi->rollback();
        $mitra_names = [];
        $sql_names = "SELECT nama_lengkap FROM mitra WHERE id IN (" . implode(',', $mitra_over_limit) . ")";
        $result_names = $koneksi->query($sql_names);
        while ($row = $result_names->fetch_assoc()) {
            $mitra_names[] = $row['nama_lengkap'];
        }
        $message = "Gagal menambahkan kegiatan. Total honor bulanan mitra berikut melebihi batas: " . implode(', ', $mitra_names);
        header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode($message));
        exit;
    }

    // Commit transaksi jika semua berhasil
    $koneksi->commit();

    // Alihkan ke halaman daftar kegiatan dengan pesan sukses
    header("Location: ../pages/kegiatan.php?status=success&message=" . urlencode("Kegiatan berhasil ditambahkan."));
    exit;

} catch (Exception $e) {
    // Rollback transaksi jika ada kesalahan
    $koneksi->rollback();

    // Alihkan dengan pesan error
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode($e->getMessage()));
    exit;
} finally {
    // Pastikan statement dan koneksi selalu ditutup
    if ($stmt_select) {
        $stmt_select->close();
    }
    if ($stmt_check_honor) {
        $stmt_check_honor->close();
    }
    if ($stmt_insert_survey) {
        $stmt_insert_survey->close();
    }
    if ($stmt_insert_honor) {
        $stmt_insert_honor->close();
    }
    $koneksi->close();
}
?>