<?php
session_start();

// 1. Load Library & Koneksi
require '../vendor/autoload.php'; 
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// 2. Ambil Data Filter
$tahun = $_POST['tahun'] ?? '';
// Filter tim_id diambil tapi tidak membatasi baris utama (hanya info)
$tim_ids = $_POST['tim_id'] ?? []; 

if (empty($tahun)) {
    die("Harap pilih Tahun.");
}

// ==========================================================================
// LANGKAH 3: QUERY DATA MITRA (FIX ERROR SUBQUERY)
// ==========================================================================

// REVISI:
// - Subquery 'riwayat_tim' sekarang melakukan JOIN ke 'master_kegiatan' (mk)
//   untuk memfilter tahun, karena tabel 'mitra_surveys' (ms) tidak punya kolom tahun.

$sql = "
    SELECT 
        m.id,
        m.id_mitra,
        m.nik,
        m.nama_lengkap,
        m.posisi,
        m.email,
        m.norek,
        m.bank,
        m.alamat_provinsi,
        m.alamat_kabupaten,
        m.nama_kecamatan,
        m.alamat_desa,
        m.alamat_detail,
        m.domisili_sama,
        m.tanggal_lahir,
        m.npwp,
        m.jenis_kelamin,
        m.agama,
        m.status_perkawinan,
        m.pendidikan,
        m.pekerjaan,
        m.deskripsi_pekerjaan_lain,
        m.no_telp,
        m.mengikuti_pendataan_bps,
        m.sp,
        m.st,
        m.se,
        m.susenas,
        m.sakernas,
        m.sbh,
        m.tahun, -- Tahun registrasi dari tabel mitra
        
        -- PERBAIKAN SUBQUERY DI SINI:
        (
            SELECT GROUP_CONCAT(DISTINCT t.nama_tim SEPARATOR ', ')
            FROM mitra_surveys ms
            JOIN tim t ON ms.tim_id = t.id
            JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode -- Join Kegiatan untuk ambil Tahun
            WHERE ms.mitra_id = m.id AND mk.tahun = '$tahun'    -- Filter Tahun via Master Kegiatan
        ) as riwayat_tim

    FROM mitra m
    WHERE m.tahun = '$tahun' 
    ORDER BY m.nama_lengkap ASC
";

$result = $koneksi->query($sql);

if (!$result) {
    die("Error Database: " . $koneksi->error);
}

// ==========================================================================
// LANGKAH 4: GENERATE EXCEL
// ==========================================================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Mitra');

// --- Styles ---
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A2E5D']], // Biru BPS
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$styleBorder = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

// --- Judul Laporan ---
$sheet->setCellValue('A1', 'DATABASE LENGKAP MITRA BPS');
$sheet->setCellValue('A2', "Tahun Registrasi: $tahun");
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

// --- Definisi Header Kolom ---
$columns = [
    'A' => ['db' => 'no', 'label' => 'NO'],
    'B' => ['db' => 'id_mitra', 'label' => 'ID MITRA'],
    'C' => ['db' => 'nik', 'label' => 'NIK'],
    'D' => ['db' => 'nama_lengkap', 'label' => 'NAMA LENGKAP'],
    'E' => ['db' => 'riwayat_tim', 'label' => 'RIWAYAT TIM (KEGIATAN)'],
    'F' => ['db' => 'posisi', 'label' => 'POSISI'],
    'G' => ['db' => 'jenis_kelamin', 'label' => 'JK'],
    'H' => ['db' => 'no_telp', 'label' => 'NO HP/WA'],
    'I' => ['db' => 'email', 'label' => 'EMAIL'],
    'J' => ['db' => 'norek', 'label' => 'NO REKENING'],
    'K' => ['db' => 'bank', 'label' => 'BANK'],
    'L' => ['db' => 'npwp', 'label' => 'NPWP'],
    'M' => ['db' => 'tanggal_lahir', 'label' => 'TGL LAHIR'],
    'N' => ['db' => 'pendidikan', 'label' => 'PENDIDIKAN'],
    'O' => ['db' => 'pekerjaan', 'label' => 'PEKERJAAN'],
    'P' => ['db' => 'alamat_detail', 'label' => 'ALAMAT DETAIL'],
    'Q' => ['db' => 'alamat_desa', 'label' => 'DESA/KEL'],
    'R' => ['db' => 'nama_kecamatan', 'label' => 'KECAMATAN'],
    'S' => ['db' => 'alamat_kabupaten', 'label' => 'KABUPATEN'],
    'T' => ['db' => 'mengikuti_pendataan_bps', 'label' => 'PENGALAMAN BPS'],
    'U' => ['db' => 'sp', 'label' => 'SP'], 
    'V' => ['db' => 'st', 'label' => 'ST'], 
    'W' => ['db' => 'se', 'label' => 'SE'], 
    'X' => ['db' => 'susenas', 'label' => 'SUSENAS'],
    'Y' => ['db' => 'sakernas', 'label' => 'SAKERNAS'],
    'Z' => ['db' => 'sbh', 'label' => 'SBH'],
    'AA' => ['db' => 'tahun', 'label' => 'THN REG']
];

$headerRow = 4;

// Cetak Header
foreach ($columns as $col => $info) {
    $sheet->setCellValue($col . $headerRow, $info['label']);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$lastCol = array_key_last($columns);
$sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray($styleHeader);

// --- Isi Data ---
$rowNum = 5;
$no = 1;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Kolom No
        $sheet->setCellValue('A' . $rowNum, $no++);
        
        foreach ($columns as $col => $info) {
            if ($col == 'A') continue; 
            
            $val = $row[$info['db']] ?? '';
            
            // Logika Riwayat Tim Kosong
            if ($info['db'] == 'riwayat_tim') {
                if (empty($val)) {
                    $val = 'Belum Ada Tim';
                }
            }
            
            // Format Data Khusus
            if (in_array($info['db'], ['nik', 'no_telp', 'norek', 'npwp'])) {
                 $sheet->setCellValueExplicit($col . $rowNum, $val, DataType::TYPE_STRING);
            } 
            elseif ($info['db'] == 'tanggal_lahir' && !empty($val) && $val != '0000-00-00') {
                 $sheet->setCellValue($col . $rowNum, date('d/m/Y', strtotime($val)));
            }
            else {
                 $sheet->setCellValue($col . $rowNum, $val);
            }
        }
        $rowNum++;
    }
} else {
    $sheet->setCellValue('A' . $rowNum, 'Tidak ada data mitra ditemukan.');
    $sheet->mergeCells("A{$rowNum}:{$lastCol}{$rowNum}");
}

// Apply Border
$lastDataRow = $rowNum - 1;
if ($lastDataRow >= 5) {
    $sheet->getStyle("A5:{$lastCol}{$lastDataRow}")->applyFromArray($styleBorder);
}

// Output File
$filename = "Data_Mitra_Full_Tahun_{$tahun}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>