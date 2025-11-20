<?php
// proses/cetak_rekonsiliasi_excel.php

// Pastikan library diload
require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// ===================== 1. LOGIKA DATA (SAMA PERSIS DENGAN PDF) =====================
// Kita gunakan logika query dan perhitungan yang sudah valid dari versi PDF sebelumnya

$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date("Y");
$raw_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : [];

$map = [
    'program' => 'program', 'kegiatan' => 'kegiatan', 'output' => 'output', 'suboutput' => 'suboutput',
    'sub_output' => 'suboutput', 'komponen' => 'komponen', 'subkomponen' => 'subkomponen',
    'sub_komponen' => 'subkomponen', 'akun' => 'akun', 'item' => 'item'
];
$selected_levels = [];
foreach ($raw_levels as $r) {
    $k = strtolower(str_replace(' ', '', trim($r)));
    if (isset($map[$k])) $selected_levels[] = $map[$k];
}
$selected_levels = array_values(array_unique($selected_levels));
if (empty($selected_levels)) {
    $selected_levels = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
}

$filters = [
    'program_id' => !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null,
    'kegiatan_id' => !empty($_GET['kegiatan_id']) ? (int)$_GET['kegiatan_id'] : null,
    'output_id' => !empty($_GET['output_id']) ? (int)$_GET['output_id'] : null,
    'sub_output_id' => !empty($_GET['sub_output_id']) ? (int)$_GET['sub_output_id'] : null,
    'komponen_id' => !empty($_GET['komponen_id']) ? (int)$_GET['komponen_id'] : null,
    'sub_komponen_id' => !empty($_GET['sub_komponen_id']) ? (int)$_GET['sub_komponen_id'] : null,
    'akun_id' => !empty($_GET['akun_id']) ? (int)$_GET['akun_id'] : null,
];

// --- QUERY STRUKTUR ---
$sql_base = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama,
    mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama,
    mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama,
    msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama,
    mi.nama_item AS item_nama, mi.pagu, mi.kode_unik, mi.id
FROM master_item mi
LEFT JOIN master_akun ma ON mi.akun_id = ma.id
LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id
LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
LEFT JOIN master_output mo ON mso.output_id = mo.id
LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
LEFT JOIN master_program mp ON mk.program_id = mp.id";

$where_clauses = ["mi.tahun = ?"];
$param_types = "i";
$param_values = [$tahun_filter];
$filter_column_map = [
    'program_id' => 'mp.id', 'kegiatan_id' => 'mk.id', 'output_id' => 'mo.id', 'sub_output_id' => 'mso.id',
    'komponen_id' => 'mkom.id', 'sub_komponen_id' => 'msk.id', 'akun_id' => 'ma.id',
];

foreach ($filters as $key => $value) {
    if ($value !== null) {
        $where_clauses[] = $filter_column_map[$key] . " = ?";
        $param_types .= "i";
        $param_values[] = $value;
    }
}

$sql_final = $sql_base . " WHERE " . implode(" AND ", $where_clauses) . " ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.id ASC";

$stmt = $koneksi->prepare($sql_final);
$stmt->bind_param($param_types, ...$param_values);
$stmt->execute();
$result = $stmt->get_result();

$flat_data = [];
$all_kode_uniks = [];
while ($row = $result->fetch_assoc()) {
    $row['pagu'] = (float)$row['pagu'];
    $flat_data[] = $row;
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt->close();

if (empty($flat_data)) { die("Tidak ada data ditemukan."); }

// --- QUERY DATA KEUANGAN ---
$rpd_sakti = [];
$rpd_sitik = [];

if (!empty($all_kode_uniks)) {
    $all_kode_uniks = array_values(array_unique($all_kode_uniks));
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);
    
    function refValues($arr){ $refs = []; foreach ($arr as $k => $v) $refs[$k] = &$arr[$k]; return $refs; }

    // SITIK
    $sql_sitik = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt1 = $koneksi->prepare($sql_sitik);
    call_user_func_array([$stmt1, 'bind_param'], refValues(array_merge([$types], $params)));
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($r = $res1->fetch_assoc()) {
        $rpd_sitik[$r['kode_unik_item']][(int)$r['bulan']] = (float)$r['jumlah'];
    }
    $stmt1->close();

    // SAKTI
    $sql_sakti = "SELECT kode_unik_item, bulan, jumlah FROM rpd_sakti WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt2 = $koneksi->prepare($sql_sakti);
    call_user_func_array([$stmt2, 'bind_param'], refValues(array_merge([$types], $params)));
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($r = $res2->fetch_assoc()) {
        $rpd_sakti[$r['kode_unik_item']][(int)$r['bulan']] = (float)$r['jumlah'];
    }
    $stmt2->close();
}

// --- BUILD HIERARCHY & CALCULATE ---
$hierarki = [];
foreach ($flat_data as $row) {
    $p = $row['program_kode']; $k = $row['kegiatan_kode']; $o = $row['output_kode'];
    $so = $row['sub_output_kode']; $kom = $row['komponen_kode'];
    $sk = $row['sub_komponen_kode']; $a = $row['akun_kode'];
    
    if (!$p) continue;
    if (!isset($hierarki[$p])) $hierarki[$p] = ['nama' => $row['program_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k])) $hierarki[$p]['children'][$k] = ['nama' => $row['kegiatan_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o])) $hierarki[$p]['children'][$k]['children'][$o] = ['nama' => $row['output_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so] = ['nama' => $row['sub_output_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom] = ['nama' => $row['komponen_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk] = ['nama' => $row['sub_komponen_nama'], 'children' => []];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a] = ['nama' => $row['akun_nama'], 'items' => []];
    
    $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]['items'][] = $row;
}

function calculateTotals(&$node, $rpd_sitik, $rpd_sakti) {
    $total_pagu = 0.0;
    $total_sitik = array_fill(1,12,0.0);
    $total_sakti = array_fill(1,12,0.0);

    if (!empty($node['items'])) {
        foreach ($node['items'] as $it) {
            $total_pagu += (float)($it['pagu'] ?? 0);
            $k = $it['kode_unik'] ?? null;
            if ($k) {
                for ($b=1; $b<=12; $b++) {
                    $total_sitik[$b] += isset($rpd_sitik[$k][$b]) ? (float)$rpd_sitik[$k][$b] : 0.0;
                    $total_sakti[$b] += isset($rpd_sakti[$k][$b]) ? (float)$rpd_sakti[$k][$b] : 0.0;
                }
            }
        }
    }

    if (!empty($node['children'])) {
        foreach ($node['children'] as &$c) {
            calculateTotals($c, $rpd_sitik, $rpd_sakti);
            $total_pagu += (float)($c['total_pagu'] ?? 0);
            if (!empty($c['total_sitik_bulanan'])) {
                foreach ($c['total_sitik_bulanan'] as $b => $v) $total_sitik[$b] += (float)$v;
            }
            if (!empty($c['total_sakti_bulanan'])) {
                foreach ($c['total_sakti_bulanan'] as $b => $v) $total_sakti[$b] += (float)$v;
            }
        }
        unset($c);
    }

    $node['total_pagu'] = $total_pagu;
    $node['total_sitik_bulanan'] = $total_sitik;
    $node['total_sakti_bulanan'] = $total_sakti;
}

foreach ($hierarki as &$pnode) calculateTotals($pnode, $rpd_sitik, $rpd_sakti);
unset($pnode);


// ===================== 2. MEMULAI PHP SPREADSHEET =====================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekonsiliasi RPD');

// --- HEADER LAPORAN ---
$sheet->setCellValue('A1', 'LAPORAN REKONSILIASI RPD SAKTI VS SITIK');
$sheet->setCellValue('A2', "TAHUN ANGGARAN: $tahun_filter");
$sheet->mergeCells('A1:AA1'); // Merge Title agar di tengah
$sheet->mergeCells('A2:AA2');
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --- HEADER TABEL (ROW 4 & 5) ---
// A=1, B=2, C=3 ...
$row_head_1 = 4;
$row_head_2 = 5;

// Kolom Tetap
$sheet->setCellValue("A$row_head_1", "URAIAN ANGGARAN");
$sheet->mergeCells("A$row_head_1:A$row_head_2");
$sheet->getColumnDimension('A')->setWidth(60); // Lebar kolom uraian

$sheet->setCellValue("B$row_head_1", "PAGU");
$sheet->mergeCells("B$row_head_1:B$row_head_2");
$sheet->getColumnDimension('B')->setWidth(18);

// Kolom Bulan (Looping)
// Bulan 1 mulai kolom C (3). Struktur: 2 kolom per bulan.
$col_idx = 3; 
$bulan_names = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Ags","Sep","Okt","Nov","Des"];

foreach($bulan_names as $bln) {
    // Header Bulan (Merge 2 kolom: Sakti & Sitik)
    $cell_start = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
    $cell_end = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx + 1);
    
    $sheet->setCellValue($cell_start . $row_head_1, strtoupper($bln));
    $sheet->mergeCells("$cell_start$row_head_1:$cell_end$row_head_1");
    
    // Sub Header (Sakti & Sitik)
    $sheet->setCellValue($cell_start . $row_head_2, "SAKTI");
    $sheet->setCellValue($cell_end . $row_head_2, "SITIK");
    
    // Lebar kolom
    $sheet->getColumnDimension($cell_start)->setWidth(15);
    $sheet->getColumnDimension($cell_end)->setWidth(15);
    
    $col_idx += 2;
}

// Kolom Validasi (Di ujung kanan)
$cell_val = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
$sheet->setCellValue($cell_val . $row_head_1, "VALIDASI");
$sheet->mergeCells("$cell_val$row_head_1:$cell_val$row_head_2");
$sheet->getColumnDimension($cell_val)->setWidth(15);

// Styling Header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
$sheet->getStyle("A$row_head_1:$last_col_letter$row_head_2")->applyFromArray($headerStyle);

// Freeze Panes (Agar header tetap terlihat saat scroll)
$sheet->freezePane('C6');

// ===================== 3. FUNGSI PRINT REKURSIF (EXCEL) =====================
$current_row = 6;

// ===================== PERBAIKAN FUNGSI PRINT EXCEL TREE =====================
function printExcelTree($sheet, &$current_row, $nodes, $level, $rpd_sitik, $rpd_sakti, $selected_levels) {
    $level_map = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
    $level_name = $level_map[$level] ?? 'item';

    foreach ($nodes as $kode => $node) {
        if (!in_array($level_name, $selected_levels) && !empty($node['children'])) {
            printExcelTree($sheet, $current_row, $node['children'], $level+1, $rpd_sitik, $rpd_sakti, $selected_levels);
            continue;
        }

        // --- CETAK HEADER (PARENT) ---
        if (in_array($level_name, $selected_levels)) {
            $sheet->setCellValue("A$current_row", str_repeat("    ", $level) . "{$kode} - " . ($node['nama'] ?? '-'));
            $sheet->setCellValue("B$current_row", $node['total_pagu'] ?? 0);
            
            $sitik_b = $node['total_sitik_bulanan'] ?? array_fill(1,12,0.0);
            $sakti_b = $node['total_sakti_bulanan'] ?? array_fill(1,12,0.0);
            
            $col_idx = 3; // Mulai dari kolom C
            $total_row_sakti = 0;
            $total_row_sitik = 0;

            for ($m=1; $m<=12; $m++) {
                $v_sakti = $sakti_b[$m] ?? 0;
                $v_sitik = $sitik_b[$m] ?? 0;
                $total_row_sakti += $v_sakti;
                $total_row_sitik += $v_sitik;

                // --- PERBAIKAN DI SINI (Gunakan String Column) ---
                $colLetterSakti = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
                $colLetterSitik = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx+1);

                $sheet->setCellValue($colLetterSakti . $current_row, $v_sakti);
                $sheet->setCellValue($colLetterSitik . $current_row, $v_sitik);

                // Logika Warna Merah jika BEDA di level Parent
                if (abs($v_sakti - $v_sitik) > 1) {
                     $sheet->getStyle("$colLetterSakti$current_row:$colLetterSitik$current_row")
                           ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE0E0'); 
                }

                $col_idx += 2;
            }

            // --- VALIDASI KOLOM ---
            $colLetterVal = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
            
            $isValid = abs($total_row_sakti - $total_row_sitik) <= 1;
            $valText = $isValid ? "VALID" : "CEK LAGI";
            
            $sheet->setCellValue($colLetterVal . $current_row, $valText);
            
            // Style Validasi Parent
            if (!$isValid) {
                 $sheet->getStyle("$colLetterVal$current_row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC7CE'); 
                 $sheet->getStyle("$colLetterVal$current_row")->getFont()->getColor()->setARGB('9C0006'); 
            } else {
                 $sheet->getStyle("$colLetterVal$current_row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('C6EFCE'); 
                 $sheet->getStyle("$colLetterVal$current_row")->getFont()->getColor()->setARGB(''); 
            }

            // Style Baris Parent (Bold & Background Abu)
            $sheet->getStyle("A$current_row:$colLetterVal$current_row")->getFont()->setBold(true);
            $sheet->getStyle("A$current_row:$colLetterVal$current_row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F2F2F2');
            
            // Number Format
            $sheet->getStyle("B$current_row:$colLetterVal$current_row")->getNumberFormat()->setFormatCode('#,##0');

            $current_row++;
        }

        // --- CETAK ITEM (CHILD) ---
        if (!empty($node['items']) && in_array('item', $selected_levels)) {
            foreach ($node['items'] as $item) {
                $kode_unik = $item['kode_unik'] ?? null; 
                $pagu_item = (float)($item['pagu'] ?? 0);
                
                $sitik_vals = $rpd_sitik[$kode_unik] ?? array_fill(1,12,0.0);
                $sakti_vals = $rpd_sakti[$kode_unik] ?? array_fill(1,12,0.0);

                // Kolom Uraian & Pagu
                $sheet->setCellValue("A$current_row", str_repeat("    ", $level+1) . "- " . ($item['item_nama'] ?? '-'));
                $sheet->setCellValue("B$current_row", $pagu_item);

                $col_idx = 3;
                $total_row_sakti = 0;
                $total_row_sitik = 0;

                for ($m=1; $m<=12; $m++) {
                    $val_sakti = $sakti_vals[$m] ?? 0;
                    $val_sitik = $sitik_vals[$m] ?? 0;
                    $total_row_sakti += $val_sakti;
                    $total_row_sitik += $val_sitik;

                    // --- PERBAIKAN DI SINI (Gunakan String Column) ---
                    $colLetterSakti = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
                    $colLetterSitik = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx+1);

                    $sheet->setCellValue($colLetterSakti . $current_row, $val_sakti);
                    $sheet->setCellValue($colLetterSitik . $current_row, $val_sitik);

                    // LOGIKA WARNA CELL JIKA BEDA
                    if (abs($val_sakti - $val_sitik) > 1) {
                        $sheet->getStyle("$colLetterSakti$current_row:$colLetterSitik$current_row")
                              ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC7CE');
                    }
                    $col_idx += 2;
                }

                // Validasi Row Item
                $colLetterVal = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_idx);
                
                $isValid = abs($total_row_sakti - $total_row_sitik) <= 1;
                $valText = $isValid ? "VALID" : "CEK LAGI";
                
                $sheet->setCellValue($colLetterVal . $current_row, $valText);
                
                if (!$isValid) {
                     $sheet->getStyle("$colLetterVal$current_row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC7CE'); 
                     $sheet->getStyle("$colLetterVal$current_row")->getFont()->getColor()->setARGB('9C0006');
                }

                // Number Format Item
                $sheet->getStyle("B$current_row:$colLetterVal$current_row")->getNumberFormat()->setFormatCode('#,##0');

                $current_row++;
            }
        }

        // Rekursi Children
        if (!empty($node['children'])) {
            printExcelTree($sheet, $current_row, $node['children'], $level+1, $rpd_sitik, $rpd_sakti, $selected_levels);
        }
    }
}

// --- JALANKAN PENCETAKAN ---
if (!empty($hierarki)) {
    printExcelTree($sheet, $current_row, $hierarki, 0, $rpd_sitik, $rpd_sakti, $selected_levels);
} else {
    $sheet->setCellValue('A6', 'TIDAK ADA DATA');
}

// --- FINAL STYLING BORDER KESELURUHAN ---
$last_row = $current_row - 1;
$last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(3 + (12*2)); // Kolom Validasi
$styleBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
];
if($last_row >= 6) {
    $sheet->getStyle("A4:$last_col_letter$last_row")->applyFromArray($styleBorder);
}

// Autosize Kolom Uraian (Opsional, hati-hati jika terlalu panjang)
// $sheet->getColumnDimension('A')->setAutoSize(true); 

// ===================== 4. OUTPUT FILE DOWNLOAD =====================
$filename = "Rekonsiliasi_RPD_SAKTI_vs_SITIK_" . $tahun_filter . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;