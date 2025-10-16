<?php
require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ==============================
// 1. TANGKAP SEMUA FILTER DARI URL
// ==============================
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$selected_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];

// Tangkap filter spesifik
$filters = [
    'program_id' => !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null,
    'kegiatan_id' => !empty($_GET['kegiatan_id']) ? (int)$_GET['kegiatan_id'] : null,
    'output_id' => !empty($_GET['output_id']) ? (int)$_GET['output_id'] : null,
    'sub_output_id' => !empty($_GET['sub_output_id']) ? (int)$_GET['sub_output_id'] : null,
    'komponen_id' => !empty($_GET['komponen_id']) ? (int)$_GET['komponen_id'] : null,
    'sub_komponen_id' => !empty($_GET['sub_komponen_id']) ? (int)$_GET['sub_komponen_id'] : null,
    'akun_id' => !empty($_GET['akun_id']) ? (int)$_GET['akun_id'] : null,
];

// ==============================
// 2. BANGUN & EKSEKUSI KUERI DINAMIS
// ==============================
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
    $flat_data[] = $row;
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt->close();

if (empty($flat_data)) {
    die("Tidak ada data yang ditemukan untuk filter yang dipilih.");
}

// ==============================
// 3. AMBIL DATA REALISASI (SECARA EFISIEN)
// ==============================
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);

    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result = $stmt_realisasi->get_result();
    while ($row = $result->fetch_assoc()) {
        $realisasi_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah_realisasi'];
    }
    $stmt_realisasi->close();
}


// ==============================
// 4. BANGUN HIERARKI, HITUNG TOTAL, & CETAK EXCEL (Tidak perlu diubah)
// ==============================
// Seluruh logika setelah ini (membangun hierarki, calculateTotals, setup Excel, printExcelTree, dll.)
// akan bekerja dengan benar pada $flat_data yang sudah terfilter.

$hierarki = [];
foreach ($flat_data as $row) {
    $p = $row['program_kode']; $k = $row['kegiatan_kode']; $o = $row['output_kode'];
    $so = $row['sub_output_kode']; $kom = $row['komponen_kode'];
    $sk = $row['sub_komponen_kode']; $a = $row['akun_kode'];
    if (!isset($hierarki[$p])) $hierarki[$p] = ['nama'=>$row['program_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k])) $hierarki[$p]['children'][$k] = ['nama'=>$row['kegiatan_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o])) $hierarki[$p]['children'][$k]['children'][$o] = ['nama'=>$row['output_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so] = ['nama'=>$row['sub_output_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom] = ['nama'=>$row['komponen_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk] = ['nama'=>$row['sub_komponen_nama'],'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a] = ['nama'=>$row['akun_nama'],'items'=>[]];
    $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]['items'][] = $row;
}

function calculateTotals(&$node, $realisasi_data) {
    $total_pagu = 0;
    $total_realisasi_bulanan = array_fill(1,12,0.0);
    if(isset($node['items'])){
        foreach($node['items'] as $item){
            $total_pagu += (float)($item['pagu'] ?? 0);
            $kode = $item['kode_unik'] ?? null;
            if($kode && isset($realisasi_data[$kode])){
                foreach($realisasi_data[$kode] as $b=>$val){
                    $b=(int)$b; if($b>=1 && $b<=12) $total_realisasi_bulanan[$b]+=(float)$val;
                }
            }
        }
    }
    if(isset($node['children'])){
        foreach($node['children'] as &$child){
            calculateTotals($child, $realisasi_data);
            $total_pagu += $child['total_pagu'];
            foreach($child['total_realisasi_bulanan'] as $b=>$val){ $total_realisasi_bulanan[$b]+=$val; }
        }
        unset($child);
    }
    $node['total_pagu']=$total_pagu;
    $node['total_realisasi_bulanan']=$total_realisasi_bulanan;
}
foreach($hierarki as &$prog) calculateTotals($prog,$realisasi_data);
unset($prog);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->mergeCells('A1:O1')->setCellValue('A1', 'Laporan Realisasi Anggaran - Tahun '.$tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$header = ['Uraian Anggaran','Jumlah Pagu','Sisa Pagu','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
$sheet->fromArray($header,NULL,'A3');
$sheet->getStyle('A3:O3')->getFont()->setBold(true);
$sheet->getStyle('A3:O3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');

$currencyFormat = '#,##0;-#,##0;0';
$allBorders = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFBFBFBF']]]];
$level_styles = [ /* ... */ ]; // Gaya Anda di sini

$row_num = 4;
$grand_total_pagu = 0;
$grand_total_realisasi = array_fill(1,12,0.0);
foreach($hierarki as $p){
    $grand_total_pagu += $p['total_pagu'] ?? 0;
    foreach($p['total_realisasi_bulanan'] as $b=>$val) $grand_total_realisasi[$b] += $val;
}
$grand_sisa = $grand_total_pagu - array_sum($grand_total_realisasi);

$sheet->setCellValue("A{$row_num}", "JUMLAH KESELURUHAN");
$sheet->setCellValue("B{$row_num}", $grand_total_pagu);
$sheet->setCellValue("C{$row_num}", $grand_sisa);
for($b=1;$b<=12;$b++){
    $colLetter = Coordinate::stringFromColumnIndex($b+3);
    $sheet->setCellValue("{$colLetter}{$row_num}", $grand_total_realisasi[$b]);
}
$sheet->getStyle("A{$row_num}:O{$row_num}")->getFont()->setBold(true);
$sheet->getStyle("A{$row_num}:O{$row_num}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6E6');
$row_num++;

function printExcelTree($sheet, $nodes, $level, &$row_num, $realisasi_data, $selected_levels){
    $level_order = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
    foreach($nodes as $kode=>$node){
        $lvl_name = $level_order[$level] ?? 'item';
        if (!in_array($lvl_name,$selected_levels) && !empty($node['children'])){
            printExcelTree($sheet,$node['children'],$level+1,$row_num,$realisasi_data,$selected_levels);
            continue;
        }

        $sisa_pagu = $node['total_pagu'] - array_sum($node['total_realisasi_bulanan']);
        $sheet->setCellValue("A{$row_num}", str_repeat('  ',$level).$kode.' - '.$node['nama']);
        $sheet->setCellValue("B{$row_num}", $node['total_pagu']);
        $sheet->setCellValue("C{$row_num}", $sisa_pagu);
        for($b=1;$b<=12;$b++){
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($b+3).$row_num, $node['total_realisasi_bulanan'][$b]);
        }
        $row_num++;

        if(!empty($node['items']) && in_array('item', $selected_levels)){
            foreach($node['items'] as $item){
                $sisa_item = $item['pagu'] - array_sum($realisasi_data[$item['kode_unik']] ?? []);
                $sheet->setCellValue("A{$row_num}", str_repeat('  ',$level+1).'- '.$item['item_nama']);
                $sheet->setCellValue("B{$row_num}", $item['pagu']);
                $sheet->setCellValue("C{$row_num}", $sisa_item);
                for($b=1;$b<=12;$b++){
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($b+3).$row_num, $realisasi_data[$item['kode_unik']][$b] ?? 0);
                }
                if ($sisa_item != 0) {
                     $sheet->getStyle("A{$row_num}:O{$row_num}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFC0C0');
                }
                $row_num++;
            }
        }
        if(!empty($node['children'])){
            printExcelTree($sheet,$node['children'],$level+1,$row_num,$realisasi_data,$selected_levels);
        }
    }
}
printExcelTree($sheet,$hierarki,0,$row_num,$realisasi_data,$selected_levels);

$last_row = $row_num-1;
$sheet->getStyle("A3:O{$last_row}")->applyFromArray($allBorders);
$sheet->getStyle("B4:O{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle("B4:O{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getColumnDimension('A')->setWidth(70);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);
for($i='D';$i!=='P';$i++) $sheet->getColumnDimension($i)->setWidth(18);

$filename = 'laporan_realisasi_'.$tahun_filter.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;