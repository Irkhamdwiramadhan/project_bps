<?php
session_start();
include "../includes/koneksi.php";

// Fungsi ambil kode dari tabel master
function get_kode($koneksi, $table, $id) {
    if (empty($id)) return null; 
    $stmt = $koneksi->prepare("SELECT kode FROM $table WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res['kode'] ?? null;
}

// Batas maksimal honor per bulan
$max_honor_per_month = 5000000; 

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Akses tidak valid."));
    exit;
}

// === AMBIL DATA INPUT ===
// Ambil tim_id (Ini Wajib)
$tim_id = $_POST['tim_id'] ?? null;

// Ambil data dasar
$mitra_ids = $_POST['mitra_id'] ?? [];
$jumlah_satuan_array = $_POST['jumlah_satuan'] ?? [];
$bulan_pembayaran = $_POST['bulan_pembayaran'] ?? null;
$tahun_pembayaran = $_POST['tahun_pembayaran'] ?? null;

// Ambil data anggaran
$program_id = $_POST['program_id'] ?? null;
$kegiatan_id = $_POST['kegiatan_id'] ?? null;
$output_id = $_POST['output_id'] ?? null;
$sub_output_id = $_POST['sub_output_id'] ?? null;
$komponen_id = $_POST['komponen_id'] ?? null;
$sub_komponen_id = $_POST['sub_komponen_id'] ?? null;
$akun_id = $_POST['akun_id'] ?? null;
$item_id = $_POST['item_id'] ?? null; 
$is_sensus = isset($_POST['is_sensus']) ? 1 : 0;

// === REVISI BAGIAN PERIODE ===
// 1. Ambil periode_jenis sebagai STRING (Jangan di-intval)
$periode_jenis = !empty($_POST['periode_jenis']) ? $_POST['periode_jenis'] : null;

// 2. Ambil periode_nilai berdasarkan jenisnya
$periode_nilai = null;
if ($periode_jenis) {
    if (isset($_POST['periode_nilai_bulanan']) && $periode_jenis == 'bulanan') $periode_nilai = $_POST['periode_nilai_bulanan'];
    elseif (isset($_POST['periode_nilai_triwulan']) && $periode_jenis == 'triwulan') $periode_nilai = $_POST['periode_nilai_triwulan'];
    elseif (isset($_POST['periode_nilai_subron']) && $periode_jenis == 'subron') $periode_nilai = $_POST['periode_nilai_subron'];
    elseif (isset($_POST['periode_nilai_tahunan']) && $periode_jenis == 'tahunan') $periode_nilai = $_POST['periode_nilai_tahunan'];
}

// Validasi Input Wajib
if (empty($tim_id) || empty($mitra_ids) || empty($bulan_pembayaran) || empty($tahun_pembayaran) || empty($item_id)) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Data kegiatan tidak lengkap (Tim, Mitra, Bulan, Tahun, Item)."));
    exit;
}

// Ambil data item (dengan ORDER BY LENGTH untuk mencegah kode terpotong)
$sql_item = "SELECT kode_unik, nama_item, harga, satuan 
             FROM master_item 
             WHERE kode_unik LIKE CONCAT(?, '%') 
             ORDER BY LENGTH(kode_unik) DESC 
             LIMIT 1";
$stmt_item = $koneksi->prepare($sql_item);
if (!$stmt_item) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Gagal prepare item: " . $koneksi->error));
    exit;
}
$stmt_item->bind_param("s", $item_id);
$stmt_item->execute();
$res_item = $stmt_item->get_result();

if ($res_item->num_rows == 0) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Item tidak ditemukan di master_item."));
    exit;
}

$item_data = $res_item->fetch_assoc();
$harga_per_satuan = (float)$item_data['harga'];
$satuan_item = $item_data['satuan'];
$item_kode_unik_full = $item_data['kode_unik']; 
$stmt_item->close();

$mitra_over_limit = [];

try {
    $koneksi->begin_transaction();

    // Ambil kode dari master tabel
    $program_kode = get_kode($koneksi, 'master_program', $program_id);
    $kegiatan_kode = get_kode($koneksi, 'master_kegiatan', $kegiatan_id);
    $output_kode = get_kode($koneksi, 'master_output', $output_id);
    $sub_output_kode = get_kode($koneksi, 'master_sub_output', $sub_output_id);
    $komponen_kode = get_kode($koneksi, 'master_komponen', $komponen_id);
    $sub_komponen_kode = get_kode($koneksi, 'master_sub_komponen', $sub_komponen_id);
    $akun_kode = get_kode($koneksi, 'master_akun', $akun_id);

    // === QUERY INSERT KE MITRA_SURVEYS ===
    // Pastikan kolom 'periode_jenis' di DB tipe datanya VARCHAR/TEXT/ENUM
    $sql_insert_survey = "INSERT INTO mitra_surveys 
        (tim_id, mitra_id, program_id, kegiatan_id, output_id, sub_output_id, komponen_id, sub_komponen_id, akun_id, survey_ke_berapa, periode_jenis, periode_nilai)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert_survey = $koneksi->prepare($sql_insert_survey);
    if (!$stmt_insert_survey) throw new Exception("Gagal prepare INSERT mitra_surveys: " . $koneksi->error);

    // Query Insert Honor
    $sql_insert_honor = "INSERT INTO honor_mitra
        (mitra_survey_id, mitra_id, honor_per_satuan, jumlah_satuan, total_honor, tanggal_input, bulan_pembayaran, tahun_pembayaran, item_kode_unik, is_sensus)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert_honor = $koneksi->prepare($sql_insert_honor);
    if (!$stmt_insert_honor) throw new Exception("Gagal prepare INSERT honor_mitra: " . $koneksi->error);

    foreach ($mitra_ids as $i => $mitra_id) {
        $mitra_id_int = intval($mitra_id);
        if ($mitra_id_int <= 0) continue; 

        $jumlah_satuan = (float)($jumlah_satuan_array[$i] ?? 0);
        if ($jumlah_satuan <= 0) continue; 

        $total_honor_baru = $harga_per_satuan * $jumlah_satuan;

        // Cek total honor bulan ini
        $stmt_check = $koneksi->prepare("SELECT COALESCE(SUM(total_honor),0) AS total_honor_bulan_ini FROM honor_mitra WHERE mitra_id=? AND bulan_pembayaran=? AND tahun_pembayaran=?");
        $stmt_check->bind_param("isi", $mitra_id_int, $bulan_pembayaran, $tahun_pembayaran); 
        $stmt_check->execute();
        $res_check = $stmt_check->get_result()->fetch_assoc();
        $current_honor = $res_check['total_honor_bulan_ini'] ?? 0;
        $stmt_check->close();

        if (($current_honor + $total_honor_baru) > $max_honor_per_month && !$is_sensus) {
            $mitra_over_limit[] = $mitra_id_int;
            continue;
        }

        // Hitung survey ke berapa
        $stmt_max = $koneksi->prepare("SELECT COALESCE(MAX(survey_ke_berapa),0) AS max_survey FROM mitra_surveys WHERE mitra_id=?");
        $stmt_max->bind_param("i", $mitra_id_int);
        $stmt_max->execute();
        $row_max = $stmt_max->get_result()->fetch_assoc();
        $survey_ke_berapa = ((int)($row_max['max_survey'] ?? 0)) + 1;
        $stmt_max->close();

        // === EKSEKUSI INSERT SURVEY (PERBAIKAN BIND PARAM) ===
        // Ubah tipe binding: 'periode_jenis' (ke-11) dan 'periode_nilai' (ke-12) adalah STRING ('s')
        // Format: i i s s s s s s s i s s
        $stmt_insert_survey->bind_param(
            "iisssssssiss", 
            $tim_id,            // i
            $mitra_id_int,      // i
            $program_kode,      // s
            $kegiatan_kode,     // s
            $output_kode,       // s
            $sub_output_kode,   // s
            $komponen_kode,     // s
            $sub_komponen_kode, // s
            $akun_kode,         // s
            $survey_ke_berapa,  // i
            $periode_jenis,     // s (STRING, misal "bulanan")
            $periode_nilai      // s (STRING, misal "01" atau "1")
        );
        
        if (!$stmt_insert_survey->execute()) {
            throw new Exception("Gagal simpan mitra_surveys: " . $stmt_insert_survey->error);
        }

        $mitra_survey_id = $koneksi->insert_id;
        $tanggal_input = date('Y-m-d H:i:s');

        // Eksekusi Insert Honor
        $stmt_insert_honor->bind_param(
            "iidddsiisi",
            $mitra_survey_id,
            $mitra_id_int,
            $harga_per_satuan,
            $jumlah_satuan,
            $total_honor_baru,
            $tanggal_input,
            $bulan_pembayaran,
            $tahun_pembayaran,
            $item_kode_unik_full,
            $is_sensus
        );
        if (!$stmt_insert_honor->execute()) {
            throw new Exception("Gagal simpan honor_mitra: " . $stmt_insert_honor->error);
        }
    }

    if (!empty($mitra_over_limit)) {
        $koneksi->rollback();
        $mitra_names = [];
        if (count($mitra_over_limit) > 0) {
            $sql_names = "SELECT nama_lengkap FROM mitra WHERE id IN (" . implode(',', array_map('intval', $mitra_over_limit)) . ")";
            $res_names = $koneksi->query($sql_names);
            while ($row = $res_names->fetch_assoc()) $mitra_names[] = $row['nama_lengkap'];
        }
        $message = "Honor melebihi batas untuk: " . implode(', ', $mitra_names);
        header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode($message));
        exit;
    }

    $koneksi->commit();
    header("Location: ../pages/kegiatan.php?status=success&message=" . urlencode("Kegiatan berhasil ditambahkan."));
    exit;

} catch (Exception $e) {
    $koneksi->rollback();
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode($e->getMessage()));
    exit;
} finally {
    if (isset($stmt_insert_survey)) $stmt_insert_survey->close();
    if (isset($stmt_insert_honor)) $stmt_insert_honor->close();
    $koneksi->close();
}
?>