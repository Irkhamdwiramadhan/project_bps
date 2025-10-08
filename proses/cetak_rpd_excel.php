<?php
// proses/cetak_rpd_excel.php (REVISI FINAL)

require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. MENGAMBIL DAN MEMPROSES DATA (LOGIKA IDENTIK DENGAN FILE PDF)
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

$stmt_hierarchy = $koneksi->prepare($sql_hierarchy); $stmt_hierarchy->bind_param("i", $tahun_filter); $stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result(); $flat_data = [];
while ($row = $result_hierarchy->fetch_assoc()) { $flat_data[] = $row; }
$stmt_hierarchy->close();

$rpd_data = [];
$sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ?";
$stmt_rpd = $koneksi->prepare($sql_rpd); $stmt_rpd->bind_param("i", $tahun_filter); $stmt_rpd->execute();
$result_rpd = $stmt_rpd->get_result();
while ($row = $result_rpd->fetch_assoc()) { $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah']; }
$stmt_rpd->close();

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

function calculateTotals(&$node, $rpd_data) {
    $total_pagu = 0; $total_rpd_bulanan = array_fill(1, 12, 0);
    if (isset($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)$item['pagu'];
            if (isset($rpd_data[$item['kode_unik']])) {
                foreach ($rpd_data[$item['kode_unik']] as $bulan => $jumlah) { if (isset($total_rpd_bulanan[$bulan])) { $total_rpd_bulanan[$bulan] += (float)$jumlah; } }
            }
        }
    }
    if (isset($node['children'])) {
        foreach ($node['children'] as &$child_node) {
            calculateTotals($child_node, $rpd_data);
            $total_pagu += $child_node['total_pagu'];
            foreach ($child_node['total_rpd_bulanan'] as $bulan => $jumlah) { if (isset($total_rpd_bulanan[$bulan])) { $total_rpd_bulanan[$bulan] += $jumlah; } }
        }
    }
    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd_bulanan;
}
foreach ($hierarki as &$program_node) { calculateTotals($program_node, $rpd_data); } unset($program_node);

// =========================================================================
// 2. MEMBUAT DOKUMEN EXCEL
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
$sheet->getStyle('A3:P3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');

// Menentukan Style
$currencyFormat = '#,##0;-#,##0;"0"';
$allBorders = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]]];
$level_styles = [
    ['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE6E6E6']]],
    ['font' => ['bold' => true, 'size' => 10], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDEDED']]],
    ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F3F3']]],
    ['font' => ['bold' => true]],
    ['font' => ['bold' => false]],
    ['font' => ['bold' => false, 'italic' => true]],
    ['font' => ['bold' => true, 'italic' => true, 'color' => ['argb' => 'FF27AE60']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F8F8']]],
];
$style_item_sisa = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF3CD']]];

$row_num = 4; // Mulai dari baris ke-4

// [REVISI] Baris Total Keseluruhan
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ...... (sebelumnya tetap sama sampai ke bagian $row_num = 4)

// ===== Revisi: Baris Total Keseluruhan (tulis cell-by-cell, jangan merge sebelum menulis) =====
$grand_total_pagu = array_sum(array_column($hierarki, 'total_pagu'));
$grand_total_rpd_bulanan = array_fill(1, 12, 0);
foreach ($hierarki as $program_node) {
    $t = (is_array($program_node['total_rpd_bulanan'] ?? null)) ? $program_node['total_rpd_bulanan'] : array_fill(1,12,0);
    foreach ($t as $bulan => $jumlah) { $grand_total_rpd_bulanan[$bulan] += (float)$jumlah; }
}
$grand_total_rpd = array_sum($grand_total_rpd_bulanan);
$grand_sisa_pagu = (float)$grand_total_pagu - (float)$grand_total_rpd;

// tulis label + angka cell-by-cell
$sheet->setCellValue('A' . $row_num, "JUMLAH KESELURUHAN");
$sheet->getStyle('A' . $row_num)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($level_styles[0]);

$sheet->setCellValue('B' . $row_num, (float)$grand_total_pagu);
$sheet->setCellValue('C' . $row_num, (float)$grand_sisa_pagu);

$colIdx = 4; // kolom D = index 4 -> Jan
for ($b = 1; $b <= 12; $b++) {
    $colLetter = Coordinate::stringFromColumnIndex($colIdx++);
    $sheet->setCellValue($colLetter . $row_num, (float)($grand_total_rpd_bulanan[$b] ?? 0));
}
$row_num++;

// ===== Revisi: fungsi rekursif printExcelTree (tulis cell-by-cell, hindari merge sebelum menulis) =====
function printExcelTree($sheet, $nodes, $level, &$row_num, $rpd_data, $level_styles, $style_item_sisa) {
    $indent = str_repeat('  ', $level);
    $current_style = $level_styles[$level] ?? end($level_styles);

    foreach ($nodes as $kode => $node) {
        $nama_node = $node['nama'] ?? 'Uraian Tidak Ditemukan';
        $total_rpd_node = array_sum(is_array($node['total_rpd_bulanan'] ?? null) ? $node['total_rpd_bulanan'] : array_fill(1,12,0));
        $sisa_pagu_node = (float)($node['total_pagu'] ?? 0) - (float)$total_rpd_node;

        // tulis label dan angka tanpa merge
        $sheet->setCellValue('A' . $row_num, $indent . $kode . ' - ' . $nama_node);
        $sheet->setCellValue('B' . $row_num, (float)($node['total_pagu'] ?? 0));
        $sheet->setCellValue('C' . $row_num, (float)$sisa_pagu_node);

        // tulis bulan (D.. sampai)
        $colIdx = 4;
        $rpd_bulanan = is_array($node['total_rpd_bulanan'] ?? null) ? $node['total_rpd_bulanan'] : array_fill(1,12,0);
        for ($b = 1; $b <= 12; $b++) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx++);
            $sheet->setCellValue($colLetter . $row_num, (float)($rpd_bulanan[$b] ?? 0));
        }

        // apply style ke seluruh range baris (A..P)
        $sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($current_style);
        $row_num++;

        // items: tulis cell-by-cell juga (hindari fromArray agar kontrol penuh)
        if (!empty($node['items']) && is_array($node['items'])) {
            $item_indent = str_repeat('  ', $level + 1);
            foreach ($node['items'] as $item) {
                $item_total_rpd = (isset($rpd_data[$item['kode_unik']]) && is_array($rpd_data[$item['kode_unik']])) ? array_sum($rpd_data[$item['kode_unik']]) : 0;
                $sisa_pagu_item = (float)($item['pagu'] ?? 0) - (float)$item_total_rpd;

                $sheet->setCellValue('A' . $row_num, $item_indent . "- " . ($item['item_nama'] ?? ''));
                $sheet->setCellValue('B' . $row_num, (float)($item['pagu'] ?? 0));
                $sheet->setCellValue('C' . $row_num, (float)$sisa_pagu_item);

                $colIdx = 4;
                for ($b = 1; $b <= 12; $b++) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIdx++);
                    $val = ($item['kode_unik'] && isset($rpd_data[$item['kode_unik']]) && is_array($rpd_data[$item['kode_unik']])) ? ($rpd_data[$item['kode_unik']][$b] ?? 0) : 0;
                    $sheet->setCellValue($colLetter . $row_num, (float)$val);
                }

                if ($sisa_pagu_item != 0) {
                    $sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($style_item_sisa);
                }
                $row_num++;
            }
        }

        // rekursif children
        if (!empty($node['children'])) {
            printExcelTree($sheet, $node['children'], $level + 1, $row_num, $rpd_data, $level_styles, $style_item_sisa);
        }
    }
}


// Panggil fungsi rekursif
if (!empty($hierarki)) {
    printExcelTree($sheet, $hierarki, 0, $row_num, $rpd_data, $level_styles, $style_item_sisa);
}

// Finishing Styles
$last_row = $row_num - 1;
$sheet->getStyle("A3:P{$last_row}")->applyFromArray($allBorders);
$sheet->getStyle("B4:P{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle("B4:P{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// Atur lebar kolom
$sheet->getColumnDimension('A')->setWidth(70);
for ($col = 'B'; $col <= 'P'; $col++) {
    $sheet->getColumnDimension($col)->setWidth(18);
}

// =========================================================================
// 3. OUTPUT FILE EXCEL KE BROWSER
// =========================================================================
$filename = 'laporan_rpd_' . $tahun_filter . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>