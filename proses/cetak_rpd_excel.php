<?php
// proses/cetak_rpd_excel.php

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


// 2. MENGAMBIL DAN MEMPROSES DATA (LOGIKA IDENTIK DENGAN FILE PDF)
// =========================================================================

// Query utama untuk mengambil hierarki anggaran
$sql_hierarchy = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama, mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama, mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama, msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama, mi.id AS id_item, mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
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
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
}
$stmt_hierarchy->close();

// Query untuk mengambil data RPD
$rpd_data = [];
$sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ?";
$stmt_rpd = $koneksi->prepare($sql_rpd);
$stmt_rpd->bind_param("i", $tahun_filter);
$stmt_rpd->execute();
$result_rpd = $stmt_rpd->get_result();
while ($row = $result_rpd->fetch_assoc()) {
    $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
}
$stmt_rpd->close();

// Membangun struktur pohon hierarki
$hierarki = [];
foreach ($flat_data as $row) {
    $p_kode = $row['program_kode']; $k_kode = $row['kegiatan_kode']; $o_kode = $row['output_kode']; $so_kode = $row['sub_output_kode'];
    $kom_kode = $row['komponen_kode']; $sk_kode = $row['sub_komponen_kode']; $a_kode = $row['akun_kode'];
    if (!$p_kode) continue;
    if (!isset($hierarki[$p_kode])) { $hierarki[$p_kode] = ['nama' => $row['program_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode])) { $hierarki[$p_kode]['children'][$k_kode] = ['nama' => $row['kegiatan_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode] = ['nama' => $row['output_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode] = ['nama' => $row['sub_output_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode] = ['nama' => $row['komponen_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode] = ['nama' => $row['sub_komponen_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode] = ['nama' => $row['akun_nama'], 'items' => []]; }
    $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode]['items'][] = $row;
}


// 3. MEMBUAT DOKUMEN EXCEL DENGAN PHPSPREADSHEET
// =========================================================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul Laporan
$sheet->mergeCells('A1:P1');
$sheet->setCellValue('A1', 'Laporan Rencana Penarikan Dana (RPD) - Tahun ' . $tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header Tabel
$header = ['Uraian Anggaran', 'Pagu', 'Sisa Pagu', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
$sheet->fromArray($header, NULL, 'A3');
$sheet->getStyle('A3:P3')->getFont()->setBold(true);
$sheet->getStyle('A3:P3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F0F0F0');
$sheet->getStyle('A3:P3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Menentukan Style
$style_program = [ 'font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'DCDCDC']] ];
$style_kegiatan = [ 'font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E8E8E8']] ];
$style_akun = [ 'font' => ['bold' => true, 'italic' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'F5F5F5']] ];
$style_item_sisa = [ 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3CD']] ];
$currencyFormat = '#,##0';
$allBorders = [ 'borders' => [ 'allBorders' => [ 'borderStyle' => Border::BORDER_THIN, ], ], ];

$row_num = 4; // Mulai dari baris ke-4

// Loop dan cetak data hierarki ke Excel
if (!empty($hierarki)) {
    foreach ($hierarki as $p_kode => $program) {
        $sheet->mergeCells("A{$row_num}:P{$row_num}");
        $sheet->setCellValue("A{$row_num}", "{$p_kode} - {$program['nama']}");
        $sheet->getStyle("A{$row_num}")->applyFromArray($style_program);
        $row_num++;

        foreach ($program['children'] as $k_kode => $kegiatan) {
            $sheet->mergeCells("A{$row_num}:P{$row_num}");
            $sheet->setCellValue("A{$row_num}", "  {$k_kode} - {$kegiatan['nama']}");
            $sheet->getStyle("A{$row_num}")->applyFromArray($style_kegiatan);
            $row_num++;
            
            // Loop ke level Akun (sama seperti di PDF untuk keringkasan)
            foreach ($kegiatan['children'] as $o_kode => $output) {
            foreach ($output['children'] as $so_kode => $sub_output) {
            foreach ($sub_output['children'] as $kom_kode => $komponen) {
            foreach ($komponen['children'] as $sk_kode => $sub_komponen) {
            foreach ($sub_komponen['children'] as $a_kode => $akun) {
                $sheet->mergeCells("A{$row_num}:P{$row_num}");
                $sheet->setCellValue("A{$row_num}", "      {$a_kode} - {$akun['nama']}");
                $sheet->getStyle("A{$row_num}")->applyFromArray($style_akun);
                $row_num++;

                foreach ($akun['items'] as $item) {
                    $kode_unik_item = $item['kode_unik'];
                    $item_total_rpd = isset($rpd_data[$kode_unik_item]) ? array_sum($rpd_data[$kode_unik_item]) : 0;
                    $sisa_pagu = $item['pagu'] - $item_total_rpd;

                    // Menyiapkan baris data untuk item
                    $item_row_data = [
                        "        - {$item['item_nama']}",
                        $item['pagu'],
                        $sisa_pagu
                    ];

                    for ($bulan = 1; $bulan <= 12; $bulan++) {
                        $item_row_data[] = $rpd_data[$kode_unik_item][$bulan] ?? 0;
                    }
                    
                    // Menulis baris data ke sheet
                    $sheet->fromArray($item_row_data, NULL, "A{$row_num}");
                    
                    // Apply cell styles
                    if ($sisa_pagu != 0) {
                        $sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($style_item_sisa);
                    }
                    
                    // Format angka
                    $sheet->getStyle("B{$row_num}:P{$row_num}")->getNumberFormat()->setFormatCode($currencyFormat);
                    $sheet->getStyle("B{$row_num}:P{$row_num}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    
                    $row_num++;
                }
            }
            }
            }
            }
            }
        }
    }
}

// Finishing Styles
$last_row = $row_num - 1;
$sheet->getStyle("A3:P{$last_row}")->applyFromArray($allBorders);

// Set Column Widths
$sheet->getColumnDimension('A')->setWidth(60);
for ($col = 'B'; $col <= 'P'; $col++) {
    $sheet->getColumnDimension($col)->setWidth(15);
}


// 4. OUTPUT FILE EXCEL KE BROWSER
// =========================================================================

$filename = 'laporan_rpd_' . $tahun_filter . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>