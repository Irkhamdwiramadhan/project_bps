<?php
// proses/cetak_realisasi_excel.php (REVISI - kompatibel tanpa setCellValueByColumnAndRow)

require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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
        $rpd_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah'];
    }
    $stmt_rpd->close();

    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) {
        $realisasi_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah_realisasi'];
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

// ===== Perbaikan: fungsi calculateTotals yang solid (selalu inisialisasi array bulanan) =====
function calculateTotals(&$node, $rpd_data, $realisasi_data) {
    // inisialisasi default
    $total_pagu = 0.0;
    $total_rpd_bulanan = array_fill(1, 12, 0.0);
    $total_realisasi_bulanan = array_fill(1, 12, 0.0);

    // items langsung
    if (!empty($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)($item['pagu'] ?? 0);

            // rpd per item
            $kode = $item['kode_unik'] ?? null;
            if ($kode && !empty($rpd_data[$kode]) && is_array($rpd_data[$kode])) {
                foreach ($rpd_data[$kode] as $b => $j) {
                    $b = (int)$b;
                    if (isset($total_rpd_bulanan[$b])) $total_rpd_bulanan[$b] += (float)$j;
                }
            }

            // realisasi per item
            if ($kode && !empty($realisasi_data[$kode]) && is_array($realisasi_data[$kode])) {
                foreach ($realisasi_data[$kode] as $b => $j) {
                    $b = (int)$b;
                    if (isset($total_realisasi_bulanan[$b])) $total_realisasi_bulanan[$b] += (float)$j;
                }
            }
        }
    }

    // children
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child_node) {
            // rekursif
            calculateTotals($child_node, $rpd_data, $realisasi_data);

            // pastikan child_node punya struktur yang benar
            if (!isset($child_node['total_pagu'])) $child_node['total_pagu'] = 0.0;
            if (!isset($child_node['total_rpd_bulanan']) || !is_array($child_node['total_rpd_bulanan'])) $child_node['total_rpd_bulanan'] = array_fill(1, 12, 0.0);
            if (!isset($child_node['total_realisasi_bulanan']) || !is_array($child_node['total_realisasi_bulanan'])) $child_node['total_realisasi_bulanan'] = array_fill(1, 12, 0.0);

            $total_pagu += (float)$child_node['total_pagu'];

            foreach ($child_node['total_rpd_bulanan'] as $b => $j) {
                $b = (int)$b;
                if (isset($total_rpd_bulanan[$b])) $total_rpd_bulanan[$b] += (float)$j;
            }
            foreach ($child_node['total_realisasi_bulanan'] as $b => $j) {
                $b = (int)$b;
                if (isset($total_realisasi_bulanan[$b])) $total_realisasi_bulanan[$b] += (float)$j;
            }
        }
        unset($child_node);
    }

    // simpan ke node
    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd_bulanan;
    $node['total_realisasi_bulanan'] = $total_realisasi_bulanan;
}

// Hitung totals untuk seluruh pohon
foreach ($hierarki as &$program_node) { calculateTotals($program_node, $rpd_data, $realisasi_data); }
unset($program_node);

// =========================================================================
// 2. MEMBUAT DOKUMEN EXCEL
// =========================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul Laporan
$sheet->mergeCells('A1:Z1')->setCellValue('A1', 'Laporan Realisasi Anggaran - Tahun ' . $tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header Tabel
$sheet->mergeCells('A3:A4')->setCellValue('A3', 'Uraian Anggaran');
$sheet->mergeCells('B3:B4')->setCellValue('B3', 'Jumlah Pagu');
$header_style = [
    'font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']]
];
$sheet->getStyle('A3:B4')->applyFromArray($header_style);

// Kolom bulan mulai dari C (index 3)
$start_col_index = 3;
for ($bulan = 1; $bulan <= 12; $bulan++) {
    $col1_char = Coordinate::stringFromColumnIndex($start_col_index);
    $col2_char = Coordinate::stringFromColumnIndex($start_col_index + 1);
    $sheet->mergeCells("{$col1_char}3:{$col2_char}3")->setCellValue("{$col1_char}3", DateTime::createFromFormat('!m', $bulan)->format('F'));
    $sheet->setCellValue("{$col1_char}4", 'RPD');
    $sheet->getColumnDimension($col1_char)->setWidth(18);
    $sheet->setCellValue("{$col2_char}4", 'Realisasi');
    $sheet->getColumnDimension($col2_char)->setWidth(18);
    $start_col_index += 2;
}
$sheet->getStyle('C3:Z4')->applyFromArray($header_style);

// Menentukan Style
$currencyFormat = '#,##0;-#,##0;0';
$allBorders = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]]];
$level_styles = [
    ['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE6E6E6']]],
    ['font' => ['bold' => true, 'size' => 10], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEDEDED']]],
    ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F3F3']]],
    ['font' => ['bold' => true]], ['font' => ['bold' => false]], ['font' => ['bold' => false, 'italic' => true]],
    ['font' => ['bold' => true, 'italic' => true, 'color' => ['argb' => 'FF27AE60']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8F8F8']]],
];

$row_num = 5;

// Baris Total Keseluruhan
$grand_total_pagu = 0.0;
$grand_total_rpd = array_fill(1, 12, 0.0);
$grand_total_realisasi = array_fill(1, 12, 0.0);
foreach ($hierarki as $p_node) {
    $grand_total_pagu += (float)($p_node['total_pagu'] ?? 0.0);
    $t_rpd = (is_array($p_node['total_rpd_bulanan'] ?? null)) ? $p_node['total_rpd_bulanan'] : array_fill(1, 12, 0.0);
    $t_rea = (is_array($p_node['total_realisasi_bulanan'] ?? null)) ? $p_node['total_realisasi_bulanan'] : array_fill(1, 12, 0.0);
    foreach ($t_rpd as $b => $j) { $grand_total_rpd[(int)$b] += (float)$j; }
    foreach ($t_rea as $b => $j) { $grand_total_realisasi[(int)$b] += (float)$j; }
}
$sheet->setCellValue('A' . $row_num, "JUMLAH KESELURUHAN");
$sheet->setCellValue('B' . $row_num, $grand_total_pagu);
$col_idx = 3;
for ($b = 1; $b <= 12; $b++) {
    $colLetter = Coordinate::stringFromColumnIndex($col_idx++);
    $sheet->setCellValue($colLetter . $row_num, $grand_total_rpd[$b]);
    $colLetter = Coordinate::stringFromColumnIndex($col_idx++);
    $sheet->setCellValue($colLetter . $row_num, $grand_total_realisasi[$b]);
}
$sheet->getStyle("A{$row_num}:Z{$row_num}")->applyFromArray($level_styles[0]);
$row_num++;

// Fungsi rekursif untuk mengisi data Excel (aman terhadap tipe)
function printExcelTree($sheet, $nodes, $level, &$row_num, $rpd_data, $realisasi_data, $level_styles) {
    $indent = str_repeat('  ', $level);
    $current_style = $level_styles[$level] ?? end($level_styles);
    foreach ($nodes as $kode => $node) {
        $nama_node = $node['nama'] ?? 'Uraian Tidak Ditemukan';
        $sheet->setCellValue('A' . $row_num, $indent . $kode . ' - ' . $nama_node);
        $sheet->setCellValue('B' . $row_num, (float)($node['total_pagu'] ?? 0.0));

        $rpd_bulanan = (is_array($node['total_rpd_bulanan'] ?? null)) ? $node['total_rpd_bulanan'] : array_fill(1,12,0.0);
        $rea_bulanan = (is_array($node['total_realisasi_bulanan'] ?? null)) ? $node['total_realisasi_bulanan'] : array_fill(1,12,0.0);

        $col_idx = 3;
        for ($b = 1; $b <= 12; $b++) {
            $colLetter = Coordinate::stringFromColumnIndex($col_idx++);
            $sheet->setCellValue($colLetter . $row_num, (float)($rpd_bulanan[$b] ?? 0.0));
            $colLetter = Coordinate::stringFromColumnIndex($col_idx++);
            $sheet->setCellValue($colLetter . $row_num, (float)($rea_bulanan[$b] ?? 0.0));
        }

        $sheet->getStyle("A{$row_num}:Z{$row_num}")->applyFromArray($current_style);
        $row_num++;

        if (!empty($node['items']) && is_array($node['items'])) {
            $item_indent = str_repeat('  ', $level + 1);
            foreach ($node['items'] as $item) {
                $sheet->setCellValue('A' . $row_num, $item_indent . "- " . ($item['item_nama'] ?? ''));
                $sheet->setCellValue('B' . $row_num, (float)($item['pagu'] ?? 0.0));
                $col_idx = 3;
                for ($b = 1; $b <= 12; $b++) {
                    $kode = $item['kode_unik'] ?? null;
                    $rpd_val = ($kode && isset($rpd_data[$kode]) && is_array($rpd_data[$kode])) ? ($rpd_data[$kode][$b] ?? 0.0) : 0.0;
                    $rea_val = ($kode && isset($realisasi_data[$kode]) && is_array($realisasi_data[$kode])) ? ($realisasi_data[$kode][$b] ?? 0.0) : 0.0;
                    $colLetter = Coordinate::stringFromColumnIndex($col_idx++);
                    $sheet->setCellValue($colLetter . $row_num, (float)$rpd_val);
                    $colLetter = Coordinate::stringFromColumnIndex($col_idx++);
                    $sheet->setCellValue($colLetter . $row_num, (float)$rea_val);
                }
                $row_num++;
            }
        }

        if (!empty($node['children'])) {
            printExcelTree($sheet, $node['children'], $level + 1, $row_num, $rpd_data, $realisasi_data, $level_styles);
        }
    }
}

// Panggil fungsi rekursif
if (!empty($hierarki)) {
    printExcelTree($sheet, $hierarki, 0, $row_num, $rpd_data, $realisasi_data, $level_styles);
}

// Finishing Styles
$last_row = $row_num - 1;
if ($last_row > 4) {
    $sheet->getStyle("A3:Z{$last_row}")->applyFromArray($allBorders);
    $sheet->getStyle("B5:Z{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle("B5:Z{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}
$sheet->getColumnDimension('A')->setWidth(70);
$sheet->getColumnDimension('B')->setWidth(20);

// =========================================================================
// 3. OUTPUT FILE EXCEL
// =========================================================================
$filename = 'laporan_rpd_vs_realisasi_' . $tahun_filter . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
