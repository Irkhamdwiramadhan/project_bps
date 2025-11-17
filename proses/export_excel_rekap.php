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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// 2. Ambil Data Filter
$bulan = $_POST['bulan'] ?? '';
$tahun = $_POST['tahun'] ?? '';
$tim_ids = $_POST['tim_id'] ?? [];

if (empty($bulan) || empty($tahun) || empty($tim_ids)) {
    die("Harap lengkapi filter (Bulan, Tahun, dan Tim).");
}

$tim_ids_clean = array_map('intval', $tim_ids);
$tim_clause = implode(',', $tim_ids_clean);

// ==========================================================================
// LANGKAH 3: SIAPKAN DATA
// ==========================================================================

// A. HEADER KOLOM (REVISI: AMBIL NAMA ITEM, BUKAN NAMA KEGIATAN)
$sql_header = "
    SELECT 
        mk.kode,
        -- REVISI: Ambil Nama Item untuk Header
        -- Menggunakan Subquery dengan LIMIT 1 agar mendapat 1 nama item per Kode Kegiatan
        (
            SELECT mi.nama_item 
            FROM master_item mi 
            WHERE mi.kode_unik LIKE CONCAT(hm.item_kode_unik, '%') 
              AND mi.tahun = hm.tahun_pembayaran
            ORDER BY LENGTH(mi.kode_unik) DESC 
            LIMIT 1
        ) as nama_header_item
        
    FROM honor_mitra hm
    JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode AND mk.tahun = hm.tahun_pembayaran
    
    WHERE hm.bulan_pembayaran = '$bulan' 
      AND hm.tahun_pembayaran = '$tahun'
      AND ms.tim_id IN ($tim_clause)
      
    GROUP BY mk.kode -- Satu kolom per Kode Kegiatan
    ORDER BY mk.kode ASC
";

$res_header = $koneksi->query($sql_header);
$kegiatan_headers = [];
while ($row = $res_header->fetch_assoc()) {
    // Fallback jika nama item kosong
    if (empty($row['nama_header_item'])) {
        $row['nama_header_item'] = 'Item Tidak Ditemukan';
    }
    $kegiatan_headers[] = $row; 
}

// B. DATA UTAMA (Pivot)
$sql_data = "
    SELECT 
        m.nama_lengkap,
        m.norek, 
        m.bank,
        t.nama_tim,
        mk.kode AS kode_kegiatan,
        
        -- Ambil Nama Item (Untuk isi cell, jika perlu ditampilkan lagi)
        (
            SELECT mi.nama_item 
            FROM master_item mi 
            WHERE mi.kode_unik LIKE CONCAT(hm.item_kode_unik, '%') 
              AND mi.tahun = hm.tahun_pembayaran
            ORDER BY LENGTH(mi.kode_unik) DESC 
            LIMIT 1
        ) AS nama_item,
        
        SUM(hm.total_honor) as subtotal_honor

    FROM honor_mitra hm
    JOIN mitra m ON hm.mitra_id = m.id
    JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    JOIN tim t ON ms.tim_id = t.id
    JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode AND mk.tahun = hm.tahun_pembayaran
    
    WHERE hm.bulan_pembayaran = '$bulan' 
      AND hm.tahun_pembayaran = '$tahun'
      AND ms.tim_id IN ($tim_clause)
      
    GROUP BY m.id, t.id, t.nama_tim, mk.kode, hm.item_kode_unik
    ORDER BY t.nama_tim ASC, m.nama_lengkap ASC
";

$res_data = $koneksi->query($sql_data);
$rows = [];

while ($row = $res_data->fetch_assoc()) {
    // Key Unik: Nama + Tim
    $key = $row['nama_lengkap'] . "_" . $row['nama_tim'];
    
    if (!isset($rows[$key])) {
        $rows[$key] = [
            'nama' => $row['nama_lengkap'],
            'norek' => $row['norek'],
            'bank' => $row['bank'],
            'tim' => $row['nama_tim'],
            'kegiatan' => []
        ];
    }
    
    if (!isset($rows[$key]['kegiatan'][$row['kode_kegiatan']])) {
        $rows[$key]['kegiatan'][$row['kode_kegiatan']] = [
            'honor' => 0, 
            'item' => []
        ];
    }
    
    $rows[$key]['kegiatan'][$row['kode_kegiatan']]['honor'] += $row['subtotal_honor'];
    $rows[$key]['kegiatan'][$row['kode_kegiatan']]['item'][] = $row['nama_item'];
}

// ==========================================================================
// LANGKAH 4: GENERATE EXCEL
// ==========================================================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Honor');

// --- Styles ---
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1E7DD']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$styleTotal = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$styleBorder = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

// --- Judul ---
$sheet->setCellValue('A1', 'REKAP HONOR MITRA BPS');
$sheet->setCellValue('A2', "Bulan: $bulan | Tahun: $tahun");
$sheet->mergeCells('A1:F1');
$sheet->mergeCells('A2:F2');
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

// --- Header Tabel ---
$headerRow1 = 4;
$headerRow2 = 5;

// Kolom Statis
$fixedCols = ['A' => 'NO', 'B' => 'NAMA MITRA', 'C' => 'NO. REKENING', 'D' => 'BANK', 'E' => 'TIM PELAKSANA'];
foreach ($fixedCols as $col => $text) {
    $sheet->setCellValue($col . $headerRow1, $text);
    $sheet->mergeCells($col . $headerRow1 . ':' . $col . $headerRow2);
}

// Kolom Dinamis (Kegiatan)
$colIndex = 6; // Mulai kolom F
foreach ($kegiatan_headers as $keg) {
    $colString = Coordinate::stringFromColumnIndex($colIndex);
    
    // Baris 4: Kode Kegiatan
    $sheet->setCellValue($colString . $headerRow1, $keg['kode']); 
    
    // Baris 5: Nama ITEM (Bukan Nama Kegiatan) [REVISI]
    $sheet->setCellValue($colString . $headerRow2, $keg['nama_header_item']); 
    
    $colIndex++;
}

// Kolom Total
$lastColString = Coordinate::stringFromColumnIndex($colIndex);
$sheet->setCellValue($lastColString . $headerRow1, 'TOTAL TERIMA');
$sheet->mergeCells($lastColString . $headerRow1 . ':' . $lastColString . $headerRow2);

// Terapkan Style Header
$fullHeaderRange = "A{$headerRow1}:{$lastColString}{$headerRow2}";
$sheet->getStyle($fullHeaderRange)->applyFromArray($styleHeader);

// Lebar Kolom
$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(25);
for ($i = 6; $i <= $colIndex; $i++) {
    // Lebar kolom dinamis sedikit diperlebar agar nama item muat
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(35);
}

// --- Isi Data ---
$rowNum = 6;
$no = 1;
$grandTotalSemua = 0;

foreach ($rows as $mitra) {
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $mitra['nama']);
    $sheet->setCellValueExplicit('C' . $rowNum, $mitra['norek'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('D' . $rowNum, $mitra['bank'] ?? '-');
    $sheet->setCellValue('E' . $rowNum, $mitra['tim']);

    $colIndex = 6;
    $totalPerMitra = 0;

    foreach ($kegiatan_headers as $header) {
        $kode = $header['kode'];
        $colString = Coordinate::stringFromColumnIndex($colIndex);
        
        if (isset($mitra['kegiatan'][$kode])) {
            $dataSel = $mitra['kegiatan'][$kode];
            $amount = $dataSel['honor'];
            
            // [OPSIONAL] Anda bisa menghapus nama item di dalam cell ini
            // karena sudah ada di header. Tapi jika dalam 1 kode kegiatan
            // ada banyak item berbeda, tetap menampilkannya di sini berguna.
            // Di sini saya tetap menampilkannya untuk detail.
         $sheet->setCellValue($colString . $rowNum, $amount);
            
            // Format sel menjadi angka dengan pemisah ribuan
            $sheet->getStyle($colString . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
            
            $totalPerMitra += $amount;
        } else {
            $sheet->setCellValue($colString . $rowNum, '-');
            $sheet->getStyle($colString . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $colIndex++;
    }

    // Total Baris
    $sheet->setCellValue($lastColString . $rowNum, $totalPerMitra);
    $sheet->getStyle($lastColString . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle($lastColString . $rowNum)->applyFromArray($styleTotal);

    $grandTotalSemua += $totalPerMitra;
    $rowNum++;
}

// --- Footer ---
$sheet->setCellValue('A' . $rowNum, 'GRAND TOTAL');
$sheet->mergeCells("A{$rowNum}:" . Coordinate::stringFromColumnIndex($colIndex - 1) . $rowNum);
$sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->setCellValue($lastColString . $rowNum, $grandTotalSemua);
$sheet->getStyle($lastColString . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("A{$rowNum}:{$lastColString}{$rowNum}")->applyFromArray($styleTotal);

// Styling Akhir
$sheet->getStyle("A6:{$lastColString}" . ($rowNum))->applyFromArray($styleBorder);
$sheet->getStyle("A4:{$lastColString}{$rowNum}")->getAlignment()->setWrapText(true);
$sheet->getStyle("A4:{$lastColString}{$rowNum}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

// Output
$filename = "Rekap_Honor_Bulan_{$bulan}_{$tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>