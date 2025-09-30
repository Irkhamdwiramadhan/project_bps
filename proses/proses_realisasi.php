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
    header("Location: ../realisasi_upload.php");
    exit();
}

$tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
$bulan = filter_input(INPUT_POST, 'bulan', FILTER_VALIDATE_INT);

if (!$tahun || !$bulan || !isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != 0) {
    $_SESSION['flash_message'] = "Data tidak lengkap atau file tidak terunggah dengan benar.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../realisasi_upload.php");
    exit();
}

$file_tmp_path = $_FILES['file_excel']['tmp_name'];
$koneksi->begin_transaction();

try {
    // KONFIGURASI KOLOM REALISASI
    $KOLOM_REALISASI = 'X';

    // Hapus data realisasi lama
    $stmt_delete = $koneksi->prepare("DELETE FROM realisasi WHERE tahun = ? AND bulan = ?");
    $stmt_delete->bind_param("ii", $tahun, $bulan);
    $stmt_delete->execute();
    $stmt_delete->close();

    $sql_insert = "INSERT INTO realisasi (kode_unik_item, tahun, bulan, jumlah_realisasi) VALUES (?, ?, ?, ?)";
    $stmt_insert = $koneksi->prepare($sql_insert);

    $spreadsheet = IOFactory::load($file_tmp_path);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    $current_codes_anggaran = [];
    $items_saved_count = 0;

    for ($row_num = 1; $row_num <= $highestRow; $row_num++) {
        $rowData = $sheet->rangeToArray('A' . $row_num . ':' . $KOLOM_REALISASI . $row_num, NULL, TRUE, FALSE)[0];
        
        if (count(array_filter($rowData, 'trim')) == 0) continue;

        $patterns = [
            'kegiatan'      => '/^[A-Z]{2}\.\d{4}$/',
            'program'       => '/^[A-Z]{2}$/',
            'sub_output'    => '/^[A-Z]{3}\.\d{3}$/',
            'output'        => '/^[A-Z]{3}$/',
            'sub_komponen'  => '/^\d{3}\.0[A-Z]$/',
            'komponen'      => '/^\d{3}$/',
            'akun'          => '/^\d{6}$/'
        ];

        $found_level = null;
        $found_kode = '';
        $found_uraian = '';
        
        foreach ($rowData as $cell_value) {
            $val = trim($cell_value);
            if (!empty($val)) {
                if (empty($found_kode)) {
                    foreach ($patterns as $level => $pattern) {
                        if (preg_match($pattern, $val)) {
                            $found_level = $level;
                            $found_kode = $val;
                            continue 2;
                        }
                    }
                    $found_kode = $val;
                } elseif (empty($found_uraian) && !is_numeric(str_replace(['.',','], '', $val))) {
                    $found_uraian = $val;
                    break;
                }
            }
        }
        
        if (empty($found_kode) && empty($found_uraian)) continue;
        if (stripos($found_uraian, 'JUMLAH') !== false || stripos($found_kode, 'JUMLAH') !== false) continue;

        if (!$found_level) {
            foreach ($patterns as $level => $pattern) {
                if (preg_match($pattern, $found_kode)) {
                    $found_level = $level;
                    break;
                }
            }
        }
        
        if ($found_level) {
            $code_to_save = $found_kode;
            
            if ($found_level === 'program') {
                $stmt_prog = $koneksi->prepare("SELECT kode FROM master_program WHERE tahun = ? AND kode LIKE ?");
                $like_prog = '%' . $found_kode;
                $stmt_prog->bind_param("is", $tahun, $like_prog);
                $stmt_prog->execute();
                if ($prog = $stmt_prog->get_result()->fetch_assoc()) {
                    $code_to_save = $prog['kode'];
                }
                $stmt_prog->close();
            } elseif ($found_level === 'kegiatan') {
                $parts = explode('.', $found_kode, 2);
                $code_to_save = $parts[1];

            // ======================= REVISI UTAMA ADA DI SINI =======================
            } elseif ($found_level === 'output') {
                // Cek apakah kode kegiatan sebelumnya sudah tersimpan
                if (isset($current_codes_anggaran['kegiatan'])) {
                    // Ambil kode kegiatan (misal: 2902) dan gabungkan dengan kode output (misal: BMA)
                    $code_to_save = $current_codes_anggaran['kegiatan'] . '.' . $found_kode;
                }
            // ======================= AKHIR DARI REVISI =======================

            } elseif ($found_level === 'sub_komponen' && strpos($found_kode, '.') !== false) {
                 $parts = explode('.', $found_kode);
                 $code_to_save = preg_replace('/^0/', '', end($parts));
            }

            $current_codes_anggaran[$found_level] = $code_to_save;

            $all_levels = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
            $reset = false;
            foreach ($all_levels as $level_to_check) {
                if ($reset) unset($current_codes_anggaran[$level_to_check]);
                if ($level_to_check === $found_level) $reset = true;
            }

        } else { // Jika tidak ada kode hierarki, ini adalah baris ITEM
            $uraian_item_N = trim($rowData[13] ?? '');
            $uraian_item_O = trim($rowData[14] ?? '');
            $uraian_item_raw = trim($uraian_item_N . ' ' . $uraian_item_O);
            $uraian_item_clean = preg_replace('/^\d+\.\s*/', '', $uraian_item_raw);

            $col_index_realisasi = ord('X') - ord('A');
            $realisasiBulanIni = (float)($rowData[$col_index_realisasi] ?? 0);

            if (!empty($uraian_item_clean) && $realisasiBulanIni > 0 && isset($current_codes_anggaran['akun'])) {
                
                $kode_unik_parts = [$tahun];
                foreach (['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'] as $level) {
                    $kode_unik_parts[] = $current_codes_anggaran[$level] ?? '';
                }
                $kode_unik_parts[] = $uraian_item_clean; 
                $kode_unik_to_find = implode('-', $kode_unik_parts);
                
                $stmt_insert->bind_param("siid", $kode_unik_to_find, $tahun, $bulan, $realisasiBulanIni);
                $stmt_insert->execute();
                if ($stmt_insert->affected_rows > 0) {
                    $items_saved_count++;
                }
            }
        }
    }
    
    $stmt_insert->close();
    $koneksi->commit();
    $_SESSION['flash_message'] = "Impor berhasil! Sebanyak {$items_saved_count} baris data realisasi untuk bulan " . DateTime::createFromFormat('!m', $bulan)->format('F') . " {$tahun} telah disimpan.";
    $_SESSION['flash_message_type'] = "success";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['flash_message'] = "Terjadi kesalahan. Proses impor dibatalkan. Pesan: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
}

header("Location: ../pages/tambah_realisasi.php");
exit();
?>