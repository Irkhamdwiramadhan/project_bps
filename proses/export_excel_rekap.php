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
// LANGKAH 3: SIAPKAN STRUKTUR HEADER (TIM -> KEGIATAN -> ITEM)
// ==========================================================================

$sql_header = "
    SELECT 
        t.id as tim_id,
        t.nama_tim,
        mk.kode as kode_kegiatan,
        hm.item_kode_unik,
        (
            SELECT mi.nama_item 
            FROM master_item mi 
            WHERE mi.kode_unik = hm.item_kode_unik 
              AND mi.tahun = hm.tahun_pembayaran
            LIMIT 1
        ) as nama_item
        
    FROM honor_mitra hm
    JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    JOIN tim t ON ms.tim_id = t.id
    JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode AND mk.tahun = hm.tahun_pembayaran
    
    WHERE hm.bulan_pembayaran = '$bulan' 
      AND hm.tahun_pembayaran = '$tahun'
      AND ms.tim_id IN ($tim_clause)
      
    -- REVISI UTAMA DI SINI: Grouping mencakup Item Unik agar tidak tertumpuk
    GROUP BY t.id, t.nama_tim, mk.kode, hm.item_kode_unik
    ORDER BY t.nama_tim ASC, mk.kode ASC, hm.item_kode_unik ASC
";

$res_header = $koneksi->query($sql_header);

// Kita susun Hierarki Header untuk memudahkan looping Excel nanti
// Struktur: $hierarki[Nama_Tim][Kode_Kegiatan][] = ['kode_item' => ..., 'nama_item' => ...]
$hierarki_header = [];
$flat_columns = []; // Untuk mapping saat isi data (O(1) access)

while ($row = $res_header->fetch_assoc()) {
    $nama_item = !empty($row['nama_item']) ? $row['nama_item'] : 'Item Tidak Diketahui';
    
    // Susun Hierarki
    $hierarki_header[$row['nama_tim']][$row['kode_kegiatan']][] = [
        'item_kode' => $row['item_kode_unik'],
        'item_nama' => $nama_item
    ];

    // Simpan referensi kolom datar (kombinasi Tim + Item)
    // Kita pakai kunci: ID_TIM_KODE_ITEM
    $flat_key = $row['tim_id'] . '_' . $row['item_kode_unik'];
    $flat_columns[] = $flat_key; // Nanti index array ini menentukan kolom ke berapa di Excel
}

// ==========================================================================
// LANGKAH 4: AMBIL DATA UTAMA (PIVOT)
// ==========================================================================

$sql_data = "
    SELECT 
        m.id as mitra_id,
        m.nama_lengkap,
        m.norek, 
        m.bank,
        t.id as tim_id,
        hm.item_kode_unik,
        SUM(hm.total_honor) as subtotal_honor

    FROM honor_mitra hm
    JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    JOIN mitra m ON hm.mitra_id = m.id 
    JOIN tim t ON ms.tim_id = t.id
    
    WHERE hm.bulan_pembayaran = '$bulan' 
      AND hm.tahun_pembayaran = '$tahun'
      AND ms.tim_id IN ($tim_clause)
      
    -- Grouping data per Mitra, per Tim, per Item
    GROUP BY m.id, t.id, hm.item_kode_unik
    ORDER BY m.nama_lengkap ASC
";

$res_data = $koneksi->query($sql_data);
$rows = [];

while ($row = $res_data->fetch_assoc()) {
    $mitra_id = $row['mitra_id'];
    
    // Inisialisasi data mitra jika belum ada
    if (!isset($rows[$mitra_id])) {
        $rows[$mitra_id] = [
            'info' => [
                'nama' => $row['nama_lengkap'],
                'norek' => $row['norek'],
                'bank' => $row['bank']
            ],
            'values' => [] // Menyimpan nilai honor berdasarkan key unik (Tim_Item)
        ];
    }
    
    // Key unik untuk mapping nilai ke kolom yang tepat
    $col_key = $row['tim_id'] . '_' . $row['item_kode_unik'];
    
    if (!isset($rows[$mitra_id]['values'][$col_key])) {
        $rows[$mitra_id]['values'][$col_key] = 0;
    }
    $rows[$mitra_id]['values'][$col_key] += $row['subtotal_honor'];
}

// ==========================================================================
// LANGKAH 5: GENERATE EXCEL
// ==========================================================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Honor');

// --- Styles ---
$styleHeaderTim = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0d6efd']], // Biru
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleHeaderKeg = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'cfe2ff']], // Biru Muda
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleHeaderItem = [
    'font' => ['bold' => true, 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8f9fa']], // Abu-abu
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleTotal = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fff3cd']], // Kuning
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$styleBorder = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

// --- Judul Laporan ---
$sheet->setCellValue('A1', 'REKAP HONOR MITRA BPS');
$sheet->setCellValue('A2', "Bulan: $bulan | Tahun: $tahun");
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

// ==========================================
// RENDER HEADER TABEL (3 TINGKAT)
// ==========================================
// Baris 4: Nama Tim
// Baris 5: Kode Kegiatan
// Baris 6: Nama Item

$rowTim = 4;
$rowKeg = 5;
$rowItem = 6;

// 1. Kolom Statis (Kiri) - Merge Vertikal 3 Baris
$fixedCols = ['A' => 'NO', 'B' => 'NAMA MITRA', 'C' => 'NO. REKENING', 'D' => 'BANK'];
foreach ($fixedCols as $col => $text) {
    $sheet->setCellValue($col . $rowTim, $text);
    $sheet->mergeCells($col . $rowTim . ':' . $col . $rowItem);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
// Style Header Kiri
$sheet->getStyle("A{$rowTim}:D{$rowItem}")->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'font' => ['bold' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// 2. Kolom Dinamis (Kanan)
$colIndex = 5; // Mulai kolom E (1=A, 5=E)

// Loop Hierarki untuk print Header
foreach ($hierarki_header as $nama_tim => $kegiatans) {
    // Simpan posisi awal kolom Tim untuk merge nanti
    $startColTim = $colIndex;
    
    foreach ($kegiatans as $kode_kegiatan => $items) {
        // Simpan posisi awal kolom Kegiatan untuk merge nanti
        $startColKeg = $colIndex;
        
        foreach ($items as $item) {
            $colString = Coordinate::stringFromColumnIndex($colIndex);
            
            // Tulis Nama Item di Baris 6
            $sheet->setCellValue($colString . $rowItem, $item['item_nama']);
            $sheet->getColumnDimension($colString)->setWidth(25); // Lebar fix agar rapi
            
            $colIndex++;
        }
        
        // MERGE KEGIATAN (Baris 5)
        $endColKeg = $colIndex - 1;
        $startStr = Coordinate::stringFromColumnIndex($startColKeg);
        $endStr = Coordinate::stringFromColumnIndex($endColKeg);
        
        $sheet->mergeCells("{$startStr}{$rowKeg}:{$endStr}{$rowKeg}");
        $sheet->setCellValue("{$startStr}{$rowKeg}", "Keg: " . $kode_kegiatan);
    }
    
    // MERGE TIM (Baris 4)
    $endColTim = $colIndex - 1;
    $startStr = Coordinate::stringFromColumnIndex($startColTim);
    $endStr = Coordinate::stringFromColumnIndex($endColTim);
    
    $sheet->mergeCells("{$startStr}{$rowTim}:{$endStr}{$rowTim}");
    $sheet->setCellValue("{$startStr}{$rowTim}", strtoupper($nama_tim));
}

// Kolom TOTAL TERIMA (Paling Kanan)
$colStringTotal = Coordinate::stringFromColumnIndex($colIndex);
$sheet->setCellValue($colStringTotal . $rowTim, 'TOTAL TERIMA');
$sheet->mergeCells($colStringTotal . $rowTim . ':' . $colStringTotal . $rowItem);
$sheet->getColumnDimension($colStringTotal)->setWidth(15);
$sheet->getStyle($colStringTotal . $rowTim . ':' . $colStringTotal . $rowItem)->applyFromArray($styleHeaderTim);

// Terapkan Style Warna Header
$lastColStr = Coordinate::stringFromColumnIndex($colIndex);
// Style Tim (Row 4)
$sheet->getStyle("E{$rowTim}:{$lastColStr}{$rowTim}")->applyFromArray($styleHeaderTim);
// Style Keg (Row 5)
$sheet->getStyle("E{$rowKeg}:" . Coordinate::stringFromColumnIndex($colIndex - 1) . $rowKeg)->applyFromArray($styleHeaderKeg);
// Style Item (Row 6)
$sheet->getStyle("E{$rowItem}:" . Coordinate::stringFromColumnIndex($colIndex - 1) . $rowItem)->applyFromArray($styleHeaderItem);


// ==========================================
// ISI DATA BARIS
// ==========================================

$currentRow = 7;
$no = 1;
$grandTotalSemua = 0;

foreach ($rows as $mitra_id => $data) {
    // Kolom Statis
    $sheet->setCellValue('A' . $currentRow, $no++);
    $sheet->setCellValue('B' . $currentRow, $data['info']['nama']);
    $sheet->setCellValueExplicit('C' . $currentRow, $data['info']['norek'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('D' . $currentRow, $data['info']['bank'] ?? '-');
    
    // Kolom Dinamis
    $colCheck = 5; // Reset ke kolom E
    $totalRow = 0;
    
    // Kita loop berdasarkan $flat_columns (Mapping urutan kolom header)
    foreach ($flat_columns as $key_col) {
        $colStr = Coordinate::stringFromColumnIndex($colCheck);
        
        if (isset($data['values'][$key_col])) {
            $val = $data['values'][$key_col];
            $sheet->setCellValue($colStr . $currentRow, $val);
            $sheet->getStyle($colStr . $currentRow)->getNumberFormat()->setFormatCode('#,##0');
            $totalRow += $val;
        } else {
            $sheet->setCellValue($colStr . $currentRow, '-');
            $sheet->getStyle($colStr . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        $colCheck++;
    }
    
    // Kolom Total Per Baris
    $colStrTotal = Coordinate::stringFromColumnIndex($colCheck);
    $sheet->setCellValue($colStrTotal . $currentRow, $totalRow);
    $sheet->getStyle($colStrTotal . $currentRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle($colStrTotal . $currentRow)->applyFromArray($styleTotal);
    
    $grandTotalSemua += $totalRow;
    $currentRow++;
}

// ==========================================
// FOOTER TOTAL
// ==========================================
$sheet->setCellValue('A' . $currentRow, 'GRAND TOTAL');
$sheet->mergeCells("A{$currentRow}:" . Coordinate::stringFromColumnIndex($colIndex - 1) . $currentRow);
$sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);

// Nilai Grand Total
$colStrFinal = Coordinate::stringFromColumnIndex($colIndex);
$sheet->setCellValue($colStrFinal . $currentRow, $grandTotalSemua);
$sheet->getStyle($colStrFinal . $currentRow)->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle("A{$currentRow}:{$colStrFinal}{$currentRow}")->applyFromArray($styleTotal);

// Border Seluruh Data
$sheet->getStyle("A{$rowItem}:{$colStrFinal}{$currentRow}")->applyFromArray($styleBorder);

// Output
$filename = "Rekap_Honor_Gabungan_{$bulan}_{$tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>