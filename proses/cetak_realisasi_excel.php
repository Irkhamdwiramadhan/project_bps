<?php
// proses/cetak_realisasi_excel.php

// 1. SETUP DAN DEPENDENCIES
// =========================================================================
require_once '../vendor/autoload.php'; // Panggil autoloader dari Composer
include '../includes/koneksi.php';

// Panggil kelas-kelas yang diperlukan dari PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");


// 2. MENGAMBIL SEMUA DATA YANG DIPERLUKAN (LOGIKA IDENTIK DENGAN FILE PDF)
// =========================================================================
$sql_hierarchy = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama, mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama, mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama, msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama, mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
FROM master_item mi
LEFT JOIN master_akun ma ON mi.akun_id = ma.id LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
LEFT JOIN master_output mo ON mso.output_id = mo.id LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
LEFT JOIN master_program mp ON mk.program_id = mp.id
WHERE mi.tahun = ? ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.nama_item ASC";
$stmt_hierarchy = $koneksi->prepare($sql_hierarchy);
$stmt_hierarchy->bind_param("i", $tahun_filter);
$stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result();
$flat_data = [];
$all_kode_uniks = [];
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
    $all_kode_uniks[] = $row['kode_unik'];
}
$stmt_hierarchy->close();
$rpd_data = [];
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
    }
    $stmt_rpd->close();
    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) {
        $realisasi_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah_realisasi'];
    }
    $stmt_realisasi->close();
}


// 3. MEMBUAT DOKUMEN EXCEL DENGAN PHPSPREADSHEET
// =========================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul Laporan
$sheet->mergeCells('A1:Z1');
$sheet->setCellValue('A1', 'Laporan Realisasi Anggaran - Tahun ' . $tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Membuat Header Tabel yang kompleks (2 baris)
$sheet->mergeCells('A3:A4')->setCellValue('A3', 'Uraian Anggaran');
$sheet->mergeCells('B3:B4')->setCellValue('B3', 'Jumlah Pagu');

$header_style = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'F0F0F0']]
];
$sheet->getStyle('A3:B4')->applyFromArray($header_style);

$start_col_index = 2; // Kolom 'C'
for ($bulan = 1; $bulan <= 12; $bulan++) {
    $nama_bulan = DateTime::createFromFormat('!m', $bulan)->format('F');
    $col1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start_col_index + 1);
    $col2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start_col_index + 2);

    $sheet->mergeCells("{$col1}3:{$col2}3")->setCellValue("{$col1}3", $nama_bulan);
    $sheet->setCellValue("{$col1}4", 'RPD');
    $sheet->setCellValue("{$col2}4", 'Realisasi');

    $start_col_index += 2;
}
$sheet->getStyle('C3:Z4')->applyFromArray($header_style);

// Menentukan Style untuk baris data
$style_hierarchy = [
    'program' => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '0A2E5D']]],
    'kegiatan' => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F618D']]],
    'akun' => ['font' => ['bold' => true, 'italic' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E8F8F5']]]
];
$currencyFormat = '#,##0';

// Proses dan cetak data
$row_num = 5;
if (!empty($flat_data)) {
    $printed_headers = [];

    foreach ($flat_data as $row) {
        // Cetak header Program jika berubah
        if (($printed_headers['program'] ?? '') !== $row['program_kode']) {
            $sheet->mergeCells("A{$row_num}:Z{$row_num}");
            $sheet->setCellValue("A{$row_num}", "{$row['program_kode']} - {$row['program_nama']}");
            $sheet->getStyle("A{$row_num}")->applyFromArray($style_hierarchy['program']);
            $printed_headers['program'] = $row['program_kode'];
            $printed_headers['kegiatan'] = null; // Reset sub-level
            $printed_headers['akun'] = null;
            $row_num++;
        }
        // Cetak header Kegiatan jika berubah
        if (($printed_headers['kegiatan'] ?? '') !== $row['kegiatan_kode']) {
            $sheet->mergeCells("A{$row_num}:Z{$row_num}");
            $sheet->setCellValue("A{$row_num}", "  {$row['kegiatan_kode']} - {$row['kegiatan_nama']}");
            $sheet->getStyle("A{$row_num}")->applyFromArray($style_hierarchy['kegiatan']);
            $printed_headers['kegiatan'] = $row['kegiatan_kode'];
            $printed_headers['akun'] = null; // Reset sub-level
            $row_num++;
        }
        // Cetak header Akun jika berubah
        if (($printed_headers['akun'] ?? '') !== $row['akun_kode']) {
            $sheet->mergeCells("A{$row_num}:Z{$row_num}");
            $sheet->setCellValue("A{$row_num}", "      {$row['akun_kode']} - {$row['akun_nama']}");
            $sheet->getStyle("A{$row_num}")->applyFromArray($style_hierarchy['akun']);
            $printed_headers['akun'] = $row['akun_kode'];
            $row_num++;
        }

        // Siapkan baris data untuk item
        $kode_unik = $row['kode_unik'];
        $item_row_data = [
            "        - {$row['item_nama']}",
            $row['pagu']
        ];
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $item_row_data[] = $rpd_data[$kode_unik][$bulan] ?? 0;
            $item_row_data[] = $realisasi_data[$kode_unik][$bulan] ?? 0;
        }

        // Tulis baris data item ke sheet
        $sheet->fromArray($item_row_data, NULL, "A{$row_num}");

        // Format angka (kolom B, dan C sampai Z)
        $sheet->getStyle("B{$row_num}:Z{$row_num}")->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle("B{$row_num}:Z{$row_num}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $row_num++;
    }
} else {
    $sheet->mergeCells("A5:Z5")->setCellValue('A5', 'Tidak ada data anggaran ditemukan untuk tahun ini.');
    $sheet->getStyle("A5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row_num++;
}

// Finishing Styles
$last_row = $row_num - 1;
$sheet->getStyle("A3:Z{$last_row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Set Column Widths
$sheet->getColumnDimension('A')->setWidth(70);
$sheet->getColumnDimension('B')->setWidth(20);
for ($i = 'C'; $i !== 'AA'; $i++) {
    $sheet->getColumnDimension($i)->setWidth(15);
}


// 4. OUTPUT FILE EXCEL KE BROWSER
// =========================================================================
$filename = 'laporan_realisasi_' . $tahun_filter . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>