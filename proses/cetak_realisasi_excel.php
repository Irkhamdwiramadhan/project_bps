<?php
// proses/cetak_realisasi_excel.php

// Autoload dan koneksi
require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ==========================================
// 1. TANGKAP SEMUA FILTER & DATA
// ==========================================
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$selected_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];

$filters = [
    'program_id' => !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null,
    'kegiatan_id' => !empty($_GET['kegiatan_id']) ? (int)$_GET['kegiatan_id'] : null,
    'output_id' => !empty($_GET['output_id']) ? (int)$_GET['output_id'] : null,
    'sub_output_id' => !empty($_GET['sub_output_id']) ? (int)$_GET['sub_output_id'] : null,
    'komponen_id' => !empty($_GET['komponen_id']) ? (int)$_GET['komponen_id'] : null,
    'sub_komponen_id' => !empty($_GET['sub_komponen_id']) ? (int)$_GET['sub_komponen_id'] : null,
    'akun_id' => !empty($_GET['akun_id']) ? (int)$_GET['akun_id'] : null,
];

// Query Dasar
$sql_base = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama,
    mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama,
    mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama,
    msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama,
    mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
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
    $flat_data[] = $row;
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt->close();

if (empty($flat_data)) { die("Tidak ada data yang ditemukan untuk filter yang dipilih."); }

// Ambil RPD & Realisasi
$rpd_data = [];
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);

    // RPD
    $stmt_rpd = $koneksi->prepare("SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)");
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $res_rpd = $stmt_rpd->get_result();
    while ($row = $res_rpd->fetch_assoc()) $rpd_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah'];
    $stmt_rpd->close();

    // Realisasi
    $stmt_rea = $koneksi->prepare("SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)");
    $stmt_rea->bind_param($types, ...$params);
    $stmt_rea->execute();
    $res_rea = $stmt_rea->get_result();
    while ($row = $res_rea->fetch_assoc()) $realisasi_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah_realisasi'];
    $stmt_rea->close();
}

// Bangun Hierarki
$hierarki = [];
foreach ($flat_data as $row) {
    $p=$row['program_kode']; $k=$row['kegiatan_kode']; $o=$row['output_kode']; $so=$row['sub_output_kode'];
    $kom=$row['komponen_kode']; $sk=$row['sub_komponen_kode']; $a=$row['akun_kode'];
    if (!isset($hierarki[$p])) $hierarki[$p]=['nama'=>$row['program_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k])) $hierarki[$p]['children'][$k]=['nama'=>$row['kegiatan_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o])) $hierarki[$p]['children'][$k]['children'][$o]=['nama'=>$row['output_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]=['nama'=>$row['sub_output_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]=['nama'=>$row['komponen_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]=['nama'=>$row['sub_komponen_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]=['nama'=>$row['akun_nama'],'items'=>[]];
    $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]['items'][]=$row;
}

// Hitung Total
function calculateTotals(&$node,$rpd_data,$realisasi_data){
    $total_pagu=0; $total_rpd=array_fill(1,12,0.0); $total_rea=array_fill(1,12,0.0);
    if(!empty($node['items'])){
        foreach($node['items'] as $item){
            $total_pagu+=(float)$item['pagu']; $kode=$item['kode_unik'];
            for($b=1;$b<=12;$b++){
                $total_rpd[$b]+=isset($rpd_data[$kode][$b])?(float)$rpd_data[$kode][$b]:0.0;
                $total_rea[$b]+=isset($realisasi_data[$kode][$b])?(float)$realisasi_data[$kode][$b]:0.0;
            }
        }
    }
    if(!empty($node['children'])){
        foreach($node['children'] as &$child){
            calculateTotals($child,$rpd_data,$realisasi_data);
            $total_pagu+=(float)$child['total_pagu'];
            for($b=1;$b<=12;$b++){
                $total_rpd[$b]+=$child['total_rpd_bulanan'][$b];
                $total_rea[$b]+=$child['total_realisasi_bulanan'][$b];
            }
        }
    }
    $node['total_pagu']=$total_pagu;
    $node['total_rpd_bulanan']=$total_rpd;
    $node['total_realisasi_bulanan']=$total_rea;
}
foreach($hierarki as &$prog) calculateTotals($prog,$rpd_data,$realisasi_data);
unset($prog);

// ==========================================
// 2. SETUP EXCEL (REVISI KEREN)
// ==========================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Realisasi');

// --- Judul Utama (Row 1-2) ---
$sheet->setCellValue('A1', 'LAPORAN REALISASI ANGGARAN');
$sheet->setCellValue('A2', 'TAHUN ANGGARAN ' . $tahun_filter);

// Merge Judul sampai kolom Z (12 bulan x 2 kolom + 2 kolom awal)
$sheet->mergeCells('A1:Z1');
$sheet->mergeCells('A2:Z2');

$styleTitle = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A1:Z2')->applyFromArray($styleTitle);

// --- Header Tabel (Row 4-5) ---
$row_head_1 = 4;
$row_head_2 = 5;

// Kolom Uraian & Pagu (Merge Vertikal)
$sheet->setCellValue("A$row_head_1", "URAIAN ANGGARAN");
$sheet->mergeCells("A$row_head_1:A$row_head_2");
$sheet->getColumnDimension('A')->setWidth(60);

$sheet->setCellValue("B$row_head_1", "JUMLAH PAGU");
$sheet->mergeCells("B$row_head_1:B$row_head_2");
$sheet->getColumnDimension('B')->setWidth(18);

// Kolom Bulan (Jan-Des)
$col_idx = 3; // Mulai dari kolom C
for ($m = 1; $m <= 12; $m++) {
    $colStart = Coordinate::stringFromColumnIndex($col_idx);
    $colEnd   = Coordinate::stringFromColumnIndex($col_idx + 1);
    
    // Merge Bulan (Horizontal)
    $sheet->mergeCells("{$colStart}{$row_head_1}:{$colEnd}{$row_head_1}");
    $sheet->setCellValue("{$colStart}{$row_head_1}", strtoupper(DateTime::createFromFormat('!m', $m)->format('F')));
    
    // Sub-Header
    $sheet->setCellValue("{$colStart}{$row_head_2}", "RPD");
    $sheet->setCellValue("{$colEnd}{$row_head_2}", "Realisasi");
    
    $sheet->getColumnDimension($colStart)->setWidth(15);
    $sheet->getColumnDimension($colEnd)->setWidth(15);
    
    $col_idx += 2;
}

// Style Header Tabel
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']], // Abu-abu
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle("A$row_head_1:Z$row_head_2")->applyFromArray($styleHeader);

// Freeze Pane (Agar header tetap terlihat saat scroll)
$sheet->freezePane('C6');

// ==========================================
// 3. PRINT DATA TREE
// ==========================================

$current_row = 6; // Mulai data setelah header

// Styles untuk Level
$level_styles = [
    ['font'=>['bold'=>true,'size'=>11],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFE6E6E6']]], // Program
    ['font'=>['bold'=>true,'size'=>10],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFEDEDED']]], // Kegiatan
    ['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFF3F3F3']]], // Output
    ['font'=>['bold'=>true]], 
    ['font'=>['bold'=>false]], 
    ['font'=>['italic'=>true]],
    ['font'=>['bold'=>true,'italic'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFF8F8F8']]]
];

// Fungsi Print Rekursif
function printExcelTreeFiltered($sheet, $nodes, $level, &$row_num, $rpd_data, $realisasi_data, $level_styles, $selected_levels){
    $level_order = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
    
    foreach($nodes as $kode => $node){
        $level_name = $level_order[$level] ?? 'item';
        
        // Skip jika level tidak dipilih tapi punya anak
        if(!in_array($level_name, $selected_levels) && !empty($node['children'])){
            printExcelTreeFiltered($sheet, $node['children'], $level+1, $row_num, $rpd_data, $realisasi_data, $level_styles, $selected_levels);
            continue;
        }

        // Cetak Parent/Node
        $indent = str_repeat('    ', $level); // Indentasi spasi
        $sheet->setCellValue('A'.$row_num, $indent . $kode . ' - ' . $node['nama']);
        $sheet->setCellValue('B'.$row_num, (float)$node['total_pagu']);
        
        $rpd_bulanan = $node['total_rpd_bulanan'] ?? array_fill(1,12,0.0);
        $rea_bulanan = $node['total_realisasi_bulanan'] ?? array_fill(1,12,0.0);
        
        $col_idx = 3;
        for($b=1; $b<=12; $b++){
            $c1 = Coordinate::stringFromColumnIndex($col_idx++);
            $c2 = Coordinate::stringFromColumnIndex($col_idx++);
            $sheet->setCellValue($c1.$row_num, (float)$rpd_bulanan[$b]);
            $sheet->setCellValue($c2.$row_num, (float)$rea_bulanan[$b]);
        }
        
        // Apply Style per Level
        $style = $level_styles[$level] ?? end($level_styles);
        $sheet->getStyle("A{$row_num}:Z{$row_num}")->applyFromArray($style);
        
        $row_num++;

        // Cetak Items (Daun)
        if(isset($node['items']) && in_array('item', $selected_levels)){
            foreach($node['items'] as $item){
                $sheet->setCellValue('A'.$row_num, str_repeat('    ', $level+1) . '- ' . $item['item_nama']);
                $sheet->setCellValue('B'.$row_num, (float)$item['pagu']);
                
                $col_idx = 3;
                for($b=1; $b<=12; $b++){
                    $kode = $item['kode_unik'] ?? null;
                    $rpd_val = ($kode && isset($rpd_data[$kode][$b])) ? $rpd_data[$kode][$b] : 0.0;
                    $rea_val = ($kode && isset($realisasi_data[$kode][$b])) ? $realisasi_data[$kode][$b] : 0.0;
                    
                    $c1 = Coordinate::stringFromColumnIndex($col_idx++);
                    $c2 = Coordinate::stringFromColumnIndex($col_idx++);
                    
                    $sheet->setCellValue($c1.$row_num, (float)$rpd_val);
                    $sheet->setCellValue($c2.$row_num, (float)$rea_val);
                }
                // Style Item (Polos/Putih)
                $row_num++;
            }
        }
        
        // Rekursif ke anak
        if(!empty($node['children'])){
            printExcelTreeFiltered($sheet, $node['children'], $level+1, $row_num, $rpd_data, $realisasi_data, $level_styles, $selected_levels);
        }
    }
}

// --- Cetak Baris TOTAL KESELURUHAN Dulu ---
$total_pagu_all = 0;
$total_rpd_all = array_fill(1,12,0.0);
$total_rea_all = array_fill(1,12,0.0);

foreach($hierarki as $prog){
    $total_pagu_all += $prog['total_pagu'];
    for($b=1; $b<=12; $b++){
        $total_rpd_all[$b] += $prog['total_rpd_bulanan'][$b];
        $total_rea_all[$b] += $prog['total_realisasi_bulanan'][$b];
    }
}

$sheet->setCellValue('A'.$current_row, 'JUMLAH KESELURUHAN');
$sheet->setCellValue('B'.$current_row, $total_pagu_all);
$col_idx = 3;
for($b=1; $b<=12; $b++){
    $c1 = Coordinate::stringFromColumnIndex($col_idx++);
    $c2 = Coordinate::stringFromColumnIndex($col_idx++);
    $sheet->setCellValue($c1.$current_row, $total_rpd_all[$b]);
    $sheet->setCellValue($c2.$current_row, $total_rea_all[$b]);
}
// Style Total (Paling Gelap)
$sheet->getStyle("A{$current_row}:Z{$current_row}")->applyFromArray($level_styles[0]);
$sheet->getStyle("A{$current_row}:Z{$current_row}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
$current_row++;

// --- Cetak Tree Data ---
printExcelTreeFiltered($sheet, $hierarki, 0, $current_row, $rpd_data, $realisasi_data, $level_styles, $selected_levels);

// --- Final Formatting ---
$last_row = $current_row - 1;

// Border Seluruh Data
$styleBorderAll = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFBFBFBF']]]];
$sheet->getStyle("A6:Z{$last_row}")->applyFromArray($styleBorderAll);

// Format Angka (Ribuan)
$sheet->getStyle("B6:Z{$last_row}")->getNumberFormat()->setFormatCode('#,##0');

$filename = 'laporan_realisasi_anggaran_'.$tahun_filter.'.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>