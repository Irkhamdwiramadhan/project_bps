<?php
session_start();
include "../includes/koneksi.php";

// Fungsi helper
function get_kode($koneksi, $table, $id) {
    if (empty($id)) return null; 
    $stmt = $koneksi->prepare("SELECT kode FROM $table WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res['kode'] ?? null;
}

$max_honor_per_month = 5000000; 

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Akses tidak valid."));
    exit;
}

// ==================================================
// 1. AMBIL DATA DASAR
// ==================================================
$tim_id = $_POST['tim_id'] ?? null;
$mitra_ids = $_POST['mitra_id'] ?? [];
$jumlah_satuan_array = $_POST['jumlah_satuan'] ?? [];

// Data Anggaran (Sama untuk semua)
$program_id = $_POST['program_id'] ?? null;
$kegiatan_id = $_POST['kegiatan_id'] ?? null;
$output_id = $_POST['output_id'] ?? null;
$sub_output_id = $_POST['sub_output_id'] ?? null;
$komponen_id = $_POST['komponen_id'] ?? null;
$sub_komponen_id = $_POST['sub_komponen_id'] ?? null;
$akun_id = $_POST['akun_id'] ?? null;
$item_id = $_POST['item_id'] ?? null; 
$is_sensus = isset($_POST['is_sensus']) ? 1 : 0;

// ==================================================
// 2. AMBIL & VALIDASI PERIODE (MULTI-CHECKBOX)
// ==================================================
$periode_jenis = $_POST['periode_jenis'] ?? '';
$periode_list_pelaksanaan = []; // Array untuk menyimpan (Jan, Feb, Mar)

if ($periode_jenis == 'bulanan') {
    $periode_list_pelaksanaan = $_POST['periode_nilai_bulanan'] ?? [];
} elseif ($periode_jenis == 'triwulan') {
    $periode_list_pelaksanaan = $_POST['periode_nilai_triwulan'] ?? [];
} elseif ($periode_jenis == 'subron') {
    $periode_list_pelaksanaan = $_POST['periode_nilai_subron'] ?? [];
} elseif ($periode_jenis == 'tahunan') {
    // Untuk tahunan, kita anggap 1 item saja
    if(!empty($_POST['periode_nilai_tahunan'])) {
        $periode_list_pelaksanaan = [$_POST['periode_nilai_tahunan']];
    }
}

// Ambil Waktu Pembayaran (Multi-Checkbox juga)
$bulan_bayar_list = $_POST['bulan_pembayaran'] ?? [];
$tahun_bayar = $_POST['tahun_pembayaran'] ?? date('Y');

// VALIDASI
if (empty($mitra_ids)) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Tidak ada mitra yang dipilih.")); exit;
}
if (empty($periode_list_pelaksanaan)) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Pilih minimal satu Waktu Pelaksanaan.")); exit;
}
if (empty($bulan_bayar_list)) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Pilih minimal satu Bulan Pembayaran.")); exit;
}


// ==================================================
// 3. PERSIAPAN DATA REFERENSI
// ==================================================

// Ambil detail item (Harga & Kode Unik)
// Gunakan ORDER BY LENGTH DESC agar mengambil kode terpanjang (sesuai perbaikan sebelumnya)
$sql_item = "SELECT kode_unik, nama_item, harga, satuan 
             FROM master_item 
             WHERE kode_unik LIKE CONCAT(?, '%') 
             ORDER BY LENGTH(kode_unik) DESC 
             LIMIT 1";
$stmt_item = $koneksi->prepare($sql_item);
$stmt_item->bind_param("s", $item_id);
$stmt_item->execute();
$res_item = $stmt_item->get_result();
if ($res_item->num_rows == 0) {
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode("Item tidak ditemukan di master.")); exit;
}
$item_data = $res_item->fetch_assoc();
$harga_per_satuan = (float)$item_data['harga'];
$item_kode_unik_full = $item_data['kode_unik']; 
$stmt_item->close();

$mitra_over_limit = [];

try {
    $koneksi->begin_transaction();

    // Ambil kode anggaran
    $program_kode = get_kode($koneksi, 'master_program', $program_id);
    $kegiatan_kode = get_kode($koneksi, 'master_kegiatan', $kegiatan_id);
    $output_kode = get_kode($koneksi, 'master_output', $output_id);
    $sub_output_kode = get_kode($koneksi, 'master_sub_output', $sub_output_id);
    $komponen_kode = get_kode($koneksi, 'master_komponen', $komponen_id);
    $sub_komponen_kode = get_kode($koneksi, 'master_sub_komponen', $sub_komponen_id);
    $akun_kode = get_kode($koneksi, 'master_akun', $akun_id);

    // Siapkan Statement INSERT (Di luar loop agar efisien)
    $sql_sur = "INSERT INTO mitra_surveys (tim_id, mitra_id, program_id, kegiatan_id, output_id, sub_output_id, komponen_id, sub_komponen_id, akun_id, survey_ke_berapa, periode_jenis, periode_nilai) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_sur = $koneksi->prepare($sql_sur);
    
    $sql_hon = "INSERT INTO honor_mitra (mitra_survey_id, mitra_id, honor_per_satuan, jumlah_satuan, total_honor, tanggal_input, bulan_pembayaran, tahun_pembayaran, item_kode_unik, is_sensus) VALUES (?,?,?,?,?,NOW(),?,?,?,?)";
    $stmt_hon = $koneksi->prepare($sql_hon);

    // ==================================================
    // 4. LOGIKA LOOPING (The Magic Part)
    // ==================================================
    
    // Kita perlu memetakan Pelaksanaan -> Pembayaran.
    // Skenario 1: Jumlah Bulan Pelaksanaan == Jumlah Bulan Pembayaran (1-on-1 Mapping)
    // Skenario 2: Jumlah Beda -> Kita pakai Bulan Pembayaran PERTAMA untuk semua (Simplified)
    //             Atau kita loop semua kombinasi (Cartesian Product - Hati-hati ini bisa banyak sekali data!)
    
    // SOLUSI AMAN:
    // Kita loop berdasarkan Periode Pelaksanaan.
    // Untuk Bulan Pembayaran, kita ambil index yang sama jika ada, atau ambil index terakhir (repeat).
    
    $count_bayar = count($bulan_bayar_list);
    
    foreach ($periode_list_pelaksanaan as $index_p => $nilai_pelaksanaan) {
        
        // Tentukan bulan bayar untuk periode ini
        if ($index_p < $count_bayar) {
            $bulan_bayar_saat_ini = $bulan_bayar_list[$index_p];
        } else {
            // Jika pelaksanaan lebih banyak dari pembayaran, gunakan pembayaran terakhir
            $bulan_bayar_saat_ini = $bulan_bayar_list[$count_bayar - 1];
        }

        // Loop Mitra
        foreach ($mitra_ids as $i => $mitra_id) {
            $mitra_id_int = intval($mitra_id);
            if ($mitra_id_int <= 0) continue; 

            $jumlah_satuan = (float)($jumlah_satuan_array[$i] ?? 0);
            if ($jumlah_satuan <= 0) continue; 

            $total_honor_baru = $harga_per_satuan * $jumlah_satuan;

            // Cek Limit Honor (Per Bulan Bayar)
            if (!$is_sensus) {
                $stmt_check = $koneksi->prepare("SELECT COALESCE(SUM(total_honor),0) AS total FROM honor_mitra WHERE mitra_id=? AND bulan_pembayaran=? AND tahun_pembayaran=?");
                $stmt_check->bind_param("isi", $mitra_id_int, $bulan_bayar_saat_ini, $tahun_bayar); 
                $stmt_check->execute();
                $res_check = $stmt_check->get_result()->fetch_assoc();
                $current_honor = $res_check['total'] ?? 0;
                $stmt_check->close();

                if (($current_honor + $total_honor_baru) > $max_honor_per_month) {
                    $mitra_over_limit[] = $mitra_id_int;
                    continue; // Skip mitra ini untuk bulan ini
                }
            }

            // Hitung Survey Ke-X
            $stmt_max = $koneksi->prepare("SELECT COALESCE(MAX(survey_ke_berapa),0) AS max_survey FROM mitra_surveys WHERE mitra_id=?");
            $stmt_max->bind_param("i", $mitra_id_int);
            $stmt_max->execute();
            $row_max = $stmt_max->get_result()->fetch_assoc();
            $survey_ke_berapa = ((int)$row_max['max_survey']) + 1;
            $stmt_max->close();

            // A. INSERT MITRA_SURVEYS
            // Mencatat Kapan Dilaksanakan ($nilai_pelaksanaan)
            $stmt_sur->bind_param("iisssssssiss", 
                $tim_id, $mitra_id_int, $program_kode, $kegiatan_kode, 
                $output_kode, $sub_output_kode, $komponen_kode, $sub_komponen_kode, 
                $akun_kode, $survey_ke_berapa, $periode_jenis, $nilai_pelaksanaan
            );
            
            if (!$stmt_sur->execute()) {
                throw new Exception("Gagal simpan survey: " . $stmt_sur->error);
            }
            $sur_id = $koneksi->insert_id;

            // B. INSERT HONOR_MITRA
            // Mencatat Kapan Dibayar ($bulan_bayar_saat_ini)
            $stmt_hon->bind_param("iidddsssi", 
                $sur_id, $mitra_id_int, $harga_per_satuan, $jumlah_satuan, 
                $total_honor_baru, $bulan_bayar_saat_ini, $tahun_bayar, 
                $item_kode_unik_full, $is_sensus
            );
            
            if (!$stmt_hon->execute()) {
                throw new Exception("Gagal simpan honor: " . $stmt_hon->error);
            }
        }
    }

    if (!empty($mitra_over_limit)) {
        $koneksi->rollback();
        // Ambil nama mitra error
        $mitra_names = [];
        if(count($mitra_over_limit) > 0) {
             $ids_str = implode(',', array_unique($mitra_over_limit));
             $res_n = $koneksi->query("SELECT nama_lengkap FROM mitra WHERE id IN ($ids_str)");
             while($rn = $res_n->fetch_assoc()) $mitra_names[] = $rn['nama_lengkap'];
        }
        $message = "Gagal! Honor melebihi batas untuk: " . implode(', ', $mitra_names);
        header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode($message));
        exit;
    }

    $koneksi->commit();
    header("Location: ../pages/kegiatan.php?status=success&message=" . urlencode("Kegiatan Berhasil Disimpan (Batch)"));
    exit;

} catch (Exception $e) {
    $koneksi->rollback();
    header("Location: ../pages/tambah_kegiatan.php?status=error&message=" . urlencode($e->getMessage()));
    exit;
} finally {
    if(isset($stmt_sur)) $stmt_sur->close();
    if(isset($stmt_hon)) $stmt_hon->close();
    $koneksi->close();
}
?>