<?php
session_start();
include '../includes/koneksi.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// =========================================================================
// VALIDASI AWAL & SETUP
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") die("Akses ditolak.");

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_tu'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../pages/upload.php");
    exit();
}

$tahun_form = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
$bulan_form = filter_input(INPUT_POST, 'bulan', FILTER_VALIDATE_INT);

if (!$tahun_form || !$bulan_form || !isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != 0) {
    $_SESSION['flash_message'] = "Data tidak lengkap atau file tidak terunggah dengan benar.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../pages/upload.php");
    exit();
}

$file_tmp_path = $_FILES['file_excel']['tmp_name'];
$koneksi->begin_transaction();

try {
    $KOLOM_REALISASI = 'X';
    $spreadsheet = IOFactory::load($file_tmp_path);
    $sheet = $spreadsheet->getActiveSheet();

    // ======================= LOGIKA VALIDASI PERIODE =======================
    $tahun_excel = null;
    $bulan_excel = null;
    $nama_bulan_excel = '';
    $periode_ditemukan = false;
    
    $daftar_bulan = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
    ];
    
    // ############### REVISI UTAMA DI SINI ###############
    // Mengubah rentang pencarian dari 'B3:AF3' menjadi 'A3:AF3' agar Kolom A ikut terbaca.
    $range_periode = $sheet->rangeToArray('A3:AF3', NULL, TRUE, FALSE)[0];
    // ######################################################

    foreach ($range_periode as $cell_value) {
        if (!empty($cell_value) && preg_match('/(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})/i', $cell_value, $matches)) {
            $nama_bulan_raw = strtolower($matches[1]);
            $nama_bulan_excel = ucfirst($nama_bulan_raw);
            $bulan_excel = $daftar_bulan[$nama_bulan_raw];
            $tahun_excel = (int)$matches[2];
            $periode_ditemukan = true;
            break;
        }
    }
    
    if (!$periode_ditemukan) {
        throw new Exception("Tidak dapat menemukan informasi periode (Bulan Tahun) di baris ke-3 file Excel.");
    }

    if ($tahun_form != $tahun_excel || $bulan_form != $bulan_excel) {
        $nama_bulan_form = DateTime::createFromFormat('!m', $bulan_form)->format('F');
        $pesan_error = "File Ditolak! Periode pada form ({$nama_bulan_form} {$tahun_form}) tidak cocok dengan periode di file Excel ({$nama_bulan_excel} {$tahun_excel}).";
        throw new Exception($pesan_error);
    }
    // ======================= AKHIR DARI LOGIKA VALIDASI =======================

    $stmt_delete = $koneksi->prepare("DELETE FROM realisasi WHERE tahun = ? AND bulan = ?");
    $stmt_delete->bind_param("ii", $tahun_form, $bulan_form);
    $stmt_delete->execute();
    $stmt_delete->close();

    $sql_insert = "INSERT INTO realisasi (kode_unik_item, tahun, bulan, jumlah_realisasi) VALUES (?, ?, ?, ?)";
    $stmt_insert = $koneksi->prepare($sql_insert);

    $highestRow = $sheet->getHighestRow();
    $current_codes_anggaran = [];
    $items_saved_count = 0;

    for ($row_num = 1; $row_num <= $highestRow; $row_num++) {
        $rowData = $sheet->rangeToArray('A' . $row_num . ':' . $KOLOM_REALISASI . $row_num, NULL, TRUE, FALSE)[0];
        
        if (count(array_filter($rowData, 'trim')) == 0) continue;

        $patterns = [
            'kegiatan'      => '/^[A-Z]{2}\.\d{4}$/', 'program'       => '/^[A-Z]{2}$/',
            'sub_output'    => '/^[A-Z]{3}\.\d{3}$/', 'output'        => '/^[A-Z]{3}$/',
            'sub_komponen'  => '/^\d{3}\.0[A-Z]$/', 'komponen'      => '/^\d{3}$/',
            'akun'          => '/^\d{6}$/'
        ];

        $found_level = null; $found_kode = ''; $found_uraian = '';
        
        foreach ($rowData as $cell_value) {
            $val = trim($cell_value);
            if (!empty($val)) {
                if (empty($found_kode)) {
                    foreach ($patterns as $level => $pattern) {
                        if (preg_match($pattern, $val)) {
                            $found_level = $level; $found_kode = $val; continue 2;
                        }
                    }
                    $found_kode = $val;
                } elseif (empty($found_uraian) && !is_numeric(str_replace(['.',','], '', $val))) {
                    $found_uraian = $val; break;
                }
            }
        }
        
        if (empty($found_kode) && empty($found_uraian)) continue;
        if (stripos($found_uraian, 'JUMLAH') !== false || stripos($found_kode, 'JUMLAH') !== false) continue;

        if (!$found_level) {
            foreach ($patterns as $level => $pattern) {
                if (preg_match($pattern, $found_kode)) { $found_level = $level; break; }
            }
        }
        
        if ($found_level) {
            $code_to_save = $found_kode;
            if ($found_level === 'program') {
                $stmt_prog = $koneksi->prepare("SELECT kode FROM master_program WHERE tahun = ? AND kode LIKE ?");
                $like_prog = '%' . $found_kode; $stmt_prog->bind_param("is", $tahun_form, $like_prog); $stmt_prog->execute();
                if ($prog = $stmt_prog->get_result()->fetch_assoc()) { $code_to_save = $prog['kode']; }
                $stmt_prog->close();
            } elseif ($found_level === 'kegiatan') {
                $parts = explode('.', $found_kode, 2); $code_to_save = $parts[1];
            } elseif ($found_level === 'output') {
                if (isset($current_codes_anggaran['kegiatan'])) { $code_to_save = $current_codes_anggaran['kegiatan'] . '.' . $found_kode; }
            } elseif ($found_level === 'sub_komponen' && strpos($found_kode, '.') !== false) {
                 $parts = explode('.', $found_kode); $code_to_save = preg_replace('/^0/', '', end($parts));
            }
            $current_codes_anggaran[$found_level] = $code_to_save;
            $all_levels = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
            $reset = false;
            foreach ($all_levels as $level_to_check) {
                if ($reset) unset($current_codes_anggaran[$level_to_check]);
                if ($level_to_check === $found_level) $reset = true;
            }
        } else {
            $uraian_item_N = trim($rowData[13] ?? ''); $uraian_item_O = trim($rowData[14] ?? '');
            $uraian_item_raw = trim($uraian_item_N . ' ' . $uraian_item_O);
            $uraian_item_clean = preg_replace('/^\d+\.\s*/', '', $uraian_item_raw);
            $col_index_realisasi = ord('X') - ord('A');
            $realisasiBulanIni = (float)($rowData[$col_index_realisasi] ?? 0);
            if (!empty($uraian_item_clean) && $realisasiBulanIni > 0 && isset($current_codes_anggaran['akun'])) {
                $kode_unik_parts = [$tahun_form];
                foreach (['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'] as $level) {
                    $kode_unik_parts[] = $current_codes_anggaran[$level] ?? '';
                }
                $kode_unik_parts[] = $uraian_item_clean; $kode_unik_to_find = implode('-', $kode_unik_parts);
                $stmt_insert->bind_param("siid", $kode_unik_to_find, $tahun_form, $bulan_form, $realisasiBulanIni);
                $stmt_insert->execute();
                if ($stmt_insert->affected_rows > 0) { $items_saved_count++; }
            }
        }
    }
    
    $stmt_insert->close();
    $koneksi->commit();
    $nama_bulan_sukses = DateTime::createFromFormat('!m', $bulan_form)->format('F');
    $_SESSION['flash_message'] = "Impor berhasil! Sebanyak {$items_saved_count} baris data realisasi untuk periode {$nama_bulan_sukses} {$tahun_form} telah divalidasi dan disimpan.";
    $_SESSION['flash_message_type'] = "success";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['flash_message'] = "Terjadi kesalahan. Proses impor dibatalkan. Pesan: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
}

header("Location: ../pages/upload.php");
exit();
?>