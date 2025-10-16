<?php
require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ===================== 1. TANGKAP SEMUA FILTER DARI URL =====================
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$selected_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : [
    'program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'
];

// Tangkap filter spesifik. Gunakan null jika tidak ada.
$filters = [
    'program_id' => !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null,
    'kegiatan_id' => !empty($_GET['kegiatan_id']) ? (int)$_GET['kegiatan_id'] : null,
    'output_id' => !empty($_GET['output_id']) ? (int)$_GET['output_id'] : null,
    'sub_output_id' => !empty($_GET['sub_output_id']) ? (int)$_GET['sub_output_id'] : null,
    'komponen_id' => !empty($_GET['komponen_id']) ? (int)$_GET['komponen_id'] : null,
    'sub_komponen_id' => !empty($_GET['sub_komponen_id']) ? (int)$_GET['sub_komponen_id'] : null,
    'akun_id' => !empty($_GET['akun_id']) ? (int)$_GET['akun_id'] : null,
];

// ===================== 2. BANGUN KUERI SQL SECARA DINAMIS =====================
$sql_hierarchy = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama,
    mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama,
    mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama,
    msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama,
    mi.nama_item AS item_nama, mi.pagu, mi.kode_unik, mi.id AS item_id
FROM master_item mi
LEFT JOIN master_akun ma ON mi.akun_id = ma.id
LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id
LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
LEFT JOIN master_output mo ON mso.output_id = mo.id
LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
LEFT JOIN master_program mp ON mk.program_id = mp.id";

// Persiapan untuk Prepared Statement yang dinamis
$where_clauses = ["mi.tahun = ?"];
$param_types = "i";
$param_values = [$tahun_filter];

$filter_column_map = [
    'program_id' => 'mp.id', 'kegiatan_id' => 'mk.id', 'output_id' => 'mo.id',
    'sub_output_id' => 'mso.id', 'komponen_id' => 'mkom.id',
    'sub_komponen_id' => 'msk.id', 'akun_id' => 'ma.id',
];

foreach ($filters as $key => $value) {
    if ($value !== null) {
        $where_clauses[] = $filter_column_map[$key] . " = ?";
        $param_types .= "i";
        $param_values[] = $value;
    }
}

$sql_hierarchy .= " WHERE " . implode(" AND ", $where_clauses);
$sql_hierarchy .= " ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.id ASC";

$stmt = $koneksi->prepare($sql_hierarchy);
$stmt->bind_param($param_types, ...$param_values);
$stmt->execute();
$result = $stmt->get_result();

$flat_data = [];
while ($row = $result->fetch_assoc()) {
    $flat_data[] = $row;
}
$stmt->close();

if (empty($flat_data)) {
    die("Tidak ada data yang ditemukan untuk filter yang dipilih.");
}

// ===================== AMBIL DATA RPD (Tidak perlu diubah) =====================
$rpd_data = [];
$sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ?";
$stmt2 = $koneksi->prepare($sql_rpd);
$stmt2->bind_param("i", $tahun_filter);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($r = $res2->fetch_assoc()) {
    $rpd_data[$r['kode_unik_item']][$r['bulan']] = $r['jumlah'];
}
$stmt2->close();

// ===================== BANGUN HIERARKI & HITUNG TOTAL (Tidak perlu diubah) =====================
$hierarki = [];
// ... (Logika membangun hierarki Anda sama persis, tidak perlu diubah)
foreach ($flat_data as $row) {
    $p = $row['program_kode']; $k = $row['kegiatan_kode'];
    $o = $row['output_kode']; $so = $row['sub_output_kode'];
    $kom = $row['komponen_kode']; $sk = $row['sub_komponen_kode'];
    $a = $row['akun_kode'];

    if (!isset($hierarki[$p])) $hierarki[$p] = ['nama' => $row['program_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k])) $hierarki[$p]['children'][$k] = ['nama' => $row['kegiatan_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o])) $hierarki[$p]['children'][$k]['children'][$o] = ['nama' => $row['output_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so] = ['nama' => $row['sub_output_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom] = ['nama' => $row['komponen_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk] = ['nama' => $row['sub_komponen_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a])) {
        $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a] = [
            'nama' => $row['akun_nama'], 'items' => []
        ];
    }
    $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]['items'][] = $row;
}

function calculateTotals(&$node, $rpd_data) {
    // ... (Fungsi calculateTotals Anda sama persis, tidak perlu diubah)
    $total_pagu = 0;
    $total_rpd_bulanan = array_fill(1, 12, 0);

    if (isset($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)$item['pagu'];
            if (isset($rpd_data[$item['kode_unik']])) {
                foreach ($rpd_data[$item['kode_unik']] as $bulan => $jumlah) {
                    $total_rpd_bulanan[$bulan] += (float)$jumlah;
                }
            }
        }
    }
    if (isset($node['children'])) {
        foreach ($node['children'] as &$child) {
            calculateTotals($child, $rpd_data);
            $total_pagu += $child['total_pagu'];
            foreach ($child['total_rpd_bulanan'] as $bulan => $jumlah) {
                $total_rpd_bulanan[$bulan] += $jumlah;
            }
        }
    }
    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd_bulanan;
}
foreach ($hierarki as &$prog) {
    calculateTotals($prog, $rpd_data);
}
unset($prog);

// ===================== PROSES EXCEL (Tidak ada perubahan signifikan) =====================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul dan header lainnya
$sheet->mergeCells('A1:P1')->setCellValue('A1', "Laporan Rencana Penarikan Dana (RPD) - Tahun $tahun_filter");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->mergeCells('A2:P2')->setCellValue('A2', 'Level Detail: ' . implode(', ', array_map('ucwords', $selected_levels)));
$sheet->getStyle('A2')->getFont()->setItalic(true);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$header = ['Uraian Anggaran', 'Pagu', 'Sisa Pagu', 'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
$sheet->fromArray($header, NULL, 'A4');
$sheet->getStyle('A4:P4')->getFont()->setBold(true);
$sheet->getStyle('A4:P4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');

// Styles
$currencyFormat = '#,##0;-#,##0;"0"';
$allBorders = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
$style_item_merah = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFC0C0']]];
$style_level = [
    'level' => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEFEFEF']], 'font' => ['bold' => true]],
    'item' => ['font' => ['bold' => false]]
];

$row_num = 5;

// Hitung total keseluruhan dari data yang sudah difilter
$total_pagu_all = 0;
$total_rpd_bulanan_all = array_fill(1, 12, 0);
foreach ($hierarki as $prog) {
    $total_pagu_all += $prog['total_pagu'] ?? 0;
    foreach ($prog['total_rpd_bulanan'] as $b => $j) {
        $total_rpd_bulanan_all[$b] += $j;
    }
}
$total_rpd_all = array_sum($total_rpd_bulanan_all);
$total_sisa_all = $total_pagu_all - $total_rpd_all;

// Tulis baris JUMLAH KESELURUHAN
$sheet->setCellValue("A{$row_num}", "JUMLAH KESELURUHAN");
$sheet->setCellValue("B{$row_num}", $total_pagu_all);
$sheet->setCellValue("C{$row_num}", $total_sisa_all);
$colIdx = 4;
for ($b=1; $b<=12; $b++) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row_num, $total_rpd_bulanan_all[$b]);
}
$sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($style_level['level']);
$row_num++;

function printExcelTreeFiltered($sheet, $nodes, $level, &$row_num, $rpd_data, $styles, $selected_levels) {
    // ... (Fungsi printExcelTreeFiltered Anda sama persis, tidak perlu diubah)
    $level_order = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
    $w_indent = 2;

    foreach ($nodes as $kode => $node) {
        $level_name = $level_order[$level] ?? 'item';
        $indent = str_repeat(' ', $level * $w_indent);

        if (!in_array($level_name, $selected_levels) && isset($node['children'])) {
            printExcelTreeFiltered($sheet, $node['children'], $level+1, $row_num, $rpd_data, $styles, $selected_levels);
            continue;
        }

        if (in_array($level_name, $selected_levels)) {
            $total_rpd = array_sum($node['total_rpd_bulanan'] ?? []);
            $sisa = ($node['total_pagu'] ?? 0) - $total_rpd;
            $sheet->setCellValue("A{$row_num}", $indent . "$kode - " . ($node['nama'] ?? ''));
            $sheet->setCellValue("B{$row_num}", $node['total_pagu'] ?? 0);
            $sheet->setCellValue("C{$row_num}", $sisa);
            $colIdx = 4;
            for ($b = 1; $b <= 12; $b++) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row_num, $node['total_rpd_bulanan'][$b] ?? 0);
            }
            $sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($styles['level']);
            $row_num++;
        }

        if (isset($node['items']) && in_array('item', $selected_levels)) {
            foreach ($node['items'] as $item) {
                $item_total = array_sum($rpd_data[$item['kode_unik']] ?? []);
                $sisa_item = $item['pagu'] - $item_total;
                $sheet->setCellValue("A{$row_num}", $indent . "  - " . $item['item_nama']);
                $sheet->setCellValue("B{$row_num}", $item['pagu']);
                $sheet->setCellValue("C{$row_num}", $sisa_item);
                $colIdx = 4;
                for ($b = 1; $b <= 12; $b++) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $row_num, $rpd_data[$item['kode_unik']][$b] ?? 0);
                }
                if ($sisa_item != 0) {
                    $sheet->getStyle("A{$row_num}:P{$row_num}")->applyFromArray($styles['item_merah']);
                }
                $row_num++;
            }
        }

        if (isset($node['children'])) {
            printExcelTreeFiltered($sheet, $node['children'], $level+1, $row_num, $rpd_data, $styles, $selected_levels);
        }
    }
}

// Cetak isi
if (!empty($hierarki)) {
    printExcelTreeFiltered($sheet, $hierarki, 0, $row_num, $rpd_data, [
        'level' => $style_level['level'],
        'item_merah' => $style_item_merah
    ], $selected_levels);
}

// Styling akhir
$last_row = $row_num - 1;
if ($last_row >= 4) {
    $sheet->getStyle("A4:P{$last_row}")->applyFromArray($allBorders);
    $sheet->getStyle("B5:P{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle("A5:A{$last_row}")->getAlignment()->setWrapText(true);
}

$sheet->getColumnDimension('A')->setWidth(70);
for ($col = 'B'; $col <= 'P'; $col++) {
    $sheet->getColumnDimension($col)->setWidth(18);
}

// Output ke browser
$filename = "laporan_rpd_{$tahun_filter}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;