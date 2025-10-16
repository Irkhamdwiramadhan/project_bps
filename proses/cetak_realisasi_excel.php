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
// 1. TANGKAP SEMUA FILTER DARI URL
// ==========================================
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

// ==========================================
// 2. BANGUN & EKSEKUSI KUERI DINAMIS
// ==========================================
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

if (empty($flat_data)) {
    die("Tidak ada data yang ditemukan untuk filter yang dipilih.");
}

// ==========================================
// 3. AMBIL DATA RPD & REALISASI (Tidak perlu diubah)
// ==========================================
$rpd_data = [];
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);

    // RPD
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $res_rpd = $stmt_rpd->get_result();
    while ($row = $res_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah'];
    }
    $stmt_rpd->close();

    // Realisasi
    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $res_realisasi = $stmt_realisasi->get_result();
    while ($row = $res_realisasi->fetch_assoc()) {
        $realisasi_data[$row['kode_unik_item']][(int)$row['bulan']] = (float)$row['jumlah_realisasi'];
    }
    $stmt_realisasi->close();
}

// ==========================================
// 4. BANGUN HIERARKI, HITUNG TOTAL, & CETAK EXCEL (Tidak perlu diubah)
// ==========================================
// Seluruh logika setelah ini (membangun hierarki, calculateTotals, setup Excel, printExcelTreeFiltered, dll.)
// tidak perlu diubah. Mereka akan bekerja dengan benar pada $flat_data yang sudah terfilter.

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
        unset($child);
    }
    $node['total_pagu']=$total_pagu;
    $node['total_rpd_bulanan']=$total_rpd;
    $node['total_realisasi_bulanan']=$total_rea;
}
foreach($hierarki as &$prog) calculateTotals($prog,$rpd_data,$realisasi_data);
unset($prog);

$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();
$sheet->mergeCells('A1:Z1')->setCellValue('A1','Laporan Realisasi Anggaran - Tahun '.$tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->mergeCells('A3:A4')->setCellValue('A3','Uraian Anggaran');
$sheet->mergeCells('B3:B4')->setCellValue('B3','Jumlah Pagu');
$header_style=['font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFD9D9D9']]];
$sheet->getStyle('A3:B4')->applyFromArray($header_style);
$row_header=3; $start_col=3;
for($bulan=1;$bulan<=12;$bulan++){
    $col1=Coordinate::stringFromColumnIndex($start_col); $col2=Coordinate::stringFromColumnIndex($start_col+1);
    $sheet->mergeCells("{$col1}{$row_header}:{$col2}{$row_header}");
    $sheet->setCellValue("{$col1}{$row_header}",DateTime::createFromFormat('!m',$bulan)->format('F'));
    $sheet->setCellValue("{$col1}".($row_header+1),'RPD');
    $sheet->setCellValue("{$col2}".($row_header+1),'Realisasi');
    $sheet->getColumnDimension($col1)->setWidth(18);
    $sheet->getColumnDimension($col2)->setWidth(18);
    $start_col+=2;
}
$sheet->getStyle('C3:Z4')->applyFromArray($header_style);

$currencyFormat='#,##0;-#,##0;0';
$allBorders=['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFBFBFBF']]]];
$level_styles=[
    ['font'=>['bold'=>true,'size'=>11],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFE6E6E6']]],
    ['font'=>['bold'=>true,'size'=>10],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFEDEDED']]],
    ['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFF3F3F3']]],
    ['font'=>['bold'=>true]],['font'=>['bold'=>false]],['font'=>['italic'=>true]],
    ['font'=>['bold'=>true,'italic'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFF8F8F8']]]
];

$row_num=5;

function printExcelTreeFiltered($sheet,$nodes,$level,&$row_num,$rpd_data,$realisasi_data,$level_styles,$selected_levels){
    $level_order=['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
    foreach($nodes as $kode=>$node){
        $level_name=$level_order[$level]??'item';
        if(!in_array($level_name,$selected_levels) && !empty($node['children'])){
            printExcelTreeFiltered($sheet,$node['children'],$level+1,$row_num,$rpd_data,$realisasi_data,$level_styles,$selected_levels);
            continue;
        }
        $indent=str_repeat('  ',$level);
        $sheet->setCellValue('A'.$row_num,$indent.$kode.' - '.$node['nama']);
        $sheet->setCellValue('B'.$row_num,(float)$node['total_pagu']);
        $rpd_bulanan=$node['total_rpd_bulanan']??array_fill(1,12,0.0);
        $rea_bulanan=$node['total_realisasi_bulanan']??array_fill(1,12,0.0);
        $col_idx=3;
        for($b=1;$b<=12;$b++){
            $colLetter=Coordinate::stringFromColumnIndex($col_idx++);
            $sheet->setCellValue($colLetter.$row_num,(float)$rpd_bulanan[$b]);
            $colLetter=Coordinate::stringFromColumnIndex($col_idx++);
            $sheet->setCellValue($colLetter.$row_num,(float)$rea_bulanan[$b]);
        }
        $sheet->getStyle("A{$row_num}:Z{$row_num}")->applyFromArray($level_styles[$level]??end($level_styles));
        $row_num++;

        if(isset($node['items']) && in_array('item',$selected_levels)){
            foreach($node['items'] as $item){
                $sheet->setCellValue('A'.$row_num,str_repeat('  ',$level+1).'- '.$item['item_nama']);
                $sheet->setCellValue('B'.$row_num,(float)$item['pagu']);
                $col_idx=3;
                for($b=1;$b<=12;$b++){
                    $kode=$item['kode_unik']??null;
                    $rpd_val=$kode && isset($rpd_data[$kode][$b])?$rpd_data[$kode][$b]:0.0;
                    $rea_val=$kode && isset($realisasi_data[$kode][$b])?$realisasi_data[$kode][$b]:0.0;
                    $colLetter=Coordinate::stringFromColumnIndex($col_idx++);
                    $sheet->setCellValue($colLetter.$row_num,(float)$rpd_val);
                    $colLetter=Coordinate::stringFromColumnIndex($col_idx++);
                    $sheet->setCellValue($colLetter.$row_num,(float)$rea_val);
                }
                $row_num++;
            }
        }
        if(!empty($node['children'])){
            printExcelTreeFiltered($sheet,$node['children'],$level+1,$row_num,$rpd_data,$realisasi_data,$level_styles,$selected_levels);
        }
    }
}

$total_pagu_all=0;
$total_rpd_all=array_fill(1,12,0.0);
$total_rea_all=array_fill(1,12,0.0);
foreach($hierarki as $prog){
    $total_pagu_all+=$prog['total_pagu'];
    for($b=1;$b<=12;$b++){
        $total_rpd_all[$b]+=$prog['total_rpd_bulanan'][$b];
        $total_rea_all[$b]+=$prog['total_realisasi_bulanan'][$b];
    }
}
$sheet->setCellValue('A'.$row_num,'JUMLAH KESELURUHAN');
$sheet->setCellValue('B'.$row_num,$total_pagu_all);
$col_idx=3;
for($b=1;$b<=12;$b++){
    $colLetter=Coordinate::stringFromColumnIndex($col_idx++);
    $sheet->setCellValue($colLetter.$row_num,$total_rpd_all[$b]);
    $colLetter=Coordinate::stringFromColumnIndex($col_idx++);
    $sheet->setCellValue($colLetter.$row_num,$total_rea_all[$b]);
}
$sheet->getStyle("A{$row_num}:Z{$row_num}")->applyFromArray($level_styles[0]);
$row_num++;

printExcelTreeFiltered($sheet,$hierarki,0,$row_num,$rpd_data,$realisasi_data,$level_styles,$selected_levels);

$last_row=$row_num-1;
$sheet->getStyle("A3:Z{$last_row}")->applyFromArray($allBorders);
$sheet->getStyle("B5:Z{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle("B5:Z{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getColumnDimension('A')->setWidth(70);
$sheet->getColumnDimension('B')->setWidth(20);

$filename='laporan_rpd_vs_realisasi_'.$tahun_filter.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer=new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>