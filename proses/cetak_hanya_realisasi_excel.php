<?php
// proses/cetak_hanya_realisasi_excel.php (REVISI FINAL - FIX getCellByColumnAndRow -> setCellValue)

require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. MENGAMBIL DAN MEMPROSES DATA
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
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt_hierarchy->close();

$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);
    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) {
        $kode = $row['kode_unik_item'];
        $bulan = (int)$row['bulan'];
        if ($bulan < 1 || $bulan > 12) continue;
        $realisasi_data[$kode][$bulan] = is_numeric($row['jumlah_realisasi']) ? (float)$row['jumlah_realisasi'] : 0.0;
    }
    $stmt_realisasi->close();
}

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

function calculateTotals(&$node, $realisasi_data) {
    $total_pagu = 0.0;
    $total_realisasi_bulanan = array_fill(1, 12, 0.0);

    if (isset($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)($item['pagu'] ?? 0);
            $kode = $item['kode_unik'] ?? null;
            if ($kode && isset($realisasi_data[$kode]) && is_array($realisasi_data[$kode])) {
                foreach ($realisasi_data[$kode] as $b => $j) {
                    $b = (int)$b;
                    if ($b >= 1 && $b <= 12) $total_realisasi_bulanan[$b] += (float)$j;
                }
            }
        }
    }

    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child_node) {
            calculateTotals($child_node, $realisasi_data);
            $total_pagu += (float)($child_node['total_pagu'] ?? 0);
            if (!isset($child_node['total_realisasi_bulanan']) || !is_array($child_node['total_realisasi_bulanan'])) {
                $child_node['total_realisasi_bulanan'] = array_fill(1, 12, 0.0);
            }
            foreach ($child_node['total_realisasi_bulanan'] as $b => $j) {
                $b = (int)$b;
                if ($b >= 1 && $b <= 12) $total_realisasi_bulanan[$b] += (float)$j;
            }
        }
        unset($child_node);
    }

    $node['total_pagu'] = $total_pagu;
    $node['total_realisasi_bulanan'] = $total_realisasi_bulanan;
}

foreach ($hierarki as &$program_node) { calculateTotals($program_node, $realisasi_data); } unset($program_node);

// =========================================================================
// 2. MEMBUAT DOKUMEN EXCEL
// =========================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->mergeCells('A1:N1')->setCellValue('A1', 'Laporan Realisasi Anggaran - Tahun ' . $tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$header = ['Uraian Anggaran', 'Jumlah Pagu', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
$sheet->fromArray($header, NULL, 'A3');
$sheet->getStyle('A3:N3')->getFont()->setBold(true);
$sheet->getStyle('A3:N3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');

$currencyFormat = '#,##0;-#,##0;0';
$allBorders = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]]];
$level_styles = [
    ['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE6E6E6']]],
    ['font' => ['bold' => true, 'size' => 10], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDEDED']]],
    ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F3F3']]],
    ['font' => ['bold' => true]], ['font' => ['bold' => false]], ['font' => ['bold' => false, 'italic' => true]],
    ['font' => ['bold' => true, 'italic' => true, 'color' => ['argb' => 'FF27AE60']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F8F8']]],
];

$row_num = 4;

// Hitung total keseluruhan
$grand_total_pagu = array_sum(array_column($hierarki, 'total_pagu'));
$grand_total_realisasi = array_fill(1, 12, 0.0);

foreach ($hierarki as $p_node) {
    if (isset($p_node['total_realisasi_bulanan']) && is_array($p_node['total_realisasi_bulanan'])) {
        foreach ($p_node['total_realisasi_bulanan'] as $b => $j) {
            $b = (int)$b;
            if ($b >= 1 && $b <= 12) {
                $grand_total_realisasi[$b] += (float)$j;
            }
        }
    }
}

$sheet->setCellValue("A{$row_num}", "JUMLAH KESELURUHAN");
$sheet->setCellValue("B{$row_num}", $grand_total_pagu);

// 🔧 Revisi di sini: ubah angka kolom ke huruf
for ($b = 1; $b <= 12; $b++) {
    $colLetter = Coordinate::stringFromColumnIndex($b + 2);
    $sheet->setCellValue("{$colLetter}{$row_num}", $grand_total_realisasi[$b]);
}

$sheet->getStyle("A{$row_num}:N{$row_num}")->applyFromArray($level_styles[0]);
$row_num++;


// ======================
// FUNGSI printExcelTree
// ======================
function printExcelTree($sheet, $nodes, $level, &$row_num, $realisasi_data, $level_styles) {
    $indent = str_repeat('  ', $level);
    $current_style = $level_styles[$level] ?? end($level_styles);

    foreach ($nodes as $kode => $node) {
        $nama_node = $node['nama'] ?? 'Uraian Tidak Ditemukan';

        $sheet->setCellValue("A{$row_num}", $indent . $kode . ' - ' . $nama_node);
        $sheet->setCellValue("B{$row_num}", $node['total_pagu'] ?? 0.0);

        for ($b = 1; $b <= 12; $b++) {
            $colLetter = Coordinate::stringFromColumnIndex($b + 2);
            $sheet->setCellValue("{$colLetter}{$row_num}", $node['total_realisasi_bulanan'][$b] ?? 0.0);
        }

        $sheet->getStyle("A{$row_num}:N{$row_num}")->applyFromArray($current_style);
        $row_num++;

        if (!empty($node['items']) && is_array($node['items'])) {
            $item_indent = str_repeat('  ', $level + 1);
            foreach ($node['items'] as $item) {
                $sheet->setCellValue("A{$row_num}", $item_indent . "- " . $item['item_nama']);
                $sheet->setCellValue("B{$row_num}", $item['pagu'] ?? 0.0);

                for ($b = 1; $b <= 12; $b++) {
                    $colLetter = Coordinate::stringFromColumnIndex($b + 2);
                    $val = isset($realisasi_data[$item['kode_unik']][$b])
                        ? $realisasi_data[$item['kode_unik']][$b]
                        : 0.0;
                    $sheet->setCellValue("{$colLetter}{$row_num}", $val);
                }
                $row_num++;
            }
        }

        if (!empty($node['children'])) {
            printExcelTree($sheet, $node['children'], $level + 1, $row_num, $realisasi_data, $level_styles);
        }
    }
}
if (!empty($hierarki)) {
    printExcelTree($sheet, $hierarki, 0, $row_num, $realisasi_data, $level_styles);
}

$last_row = $row_num - 1;
if ($last_row > 3) {
    $sheet->getStyle("A3:N{$last_row}")->applyFromArray($allBorders);
    $sheet->getStyle("B4:N{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle("B4:N{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$sheet->getColumnDimension('A')->setWidth(70);
$sheet->getColumnDimension('B')->setWidth(20);
for ($i = 'C'; $i !== 'O'; $i++) {
    $sheet->getColumnDimension($i)->setWidth(18);
}

// =========================================================================
// 3. OUTPUT FILE EXCEL
// =========================================================================
$filename = 'laporan_realisasi_' . $tahun_filter . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
