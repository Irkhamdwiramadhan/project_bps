<?php
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
$selected_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] :
    ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];

// ==============================
// 1. AMBIL DATA HIERARKI
// ==============================
$sql_hierarchy = "SELECT
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
LEFT JOIN master_program mp ON mk.program_id = mp.id
WHERE mi.tahun = ? ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.id ASC";

$stmt = $koneksi->prepare($sql_hierarchy);
$stmt->bind_param("i", $tahun_filter);
$stmt->execute();
$result = $stmt->get_result();

$flat_data = [];
$all_kode_uniks = [];
while ($row = $result->fetch_assoc()) {
    $flat_data[] = $row;
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt->close();

// ==============================
// 2. AMBIL DATA REALISASI
// ==============================
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = str_repeat('s', count($all_kode_uniks));
    $params = $all_kode_uniks;

    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt2 = $koneksi->prepare($sql_realisasi);
    $stmt2->bind_param('i' . $types, $tahun_filter, ...$params);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $kode = $row['kode_unik_item'];
        $bulan = (int)$row['bulan'];
        if ($bulan < 1 || $bulan > 12) continue;
        $realisasi_data[$kode][$bulan] = is_numeric($row['jumlah_realisasi']) ? (float)$row['jumlah_realisasi'] : 0.0;
    }
    $stmt2->close();
}

// ==============================
// 3. BANGUN HIERARKI
// ==============================
$hierarki = [];
foreach ($flat_data as $row) {
    $p = $row['program_kode']; $k = $row['kegiatan_kode'];
    $o = $row['output_kode']; $so = $row['sub_output_kode'];
    $kom = $row['komponen_kode']; $sk = $row['sub_komponen_kode']; $a = $row['akun_kode'];

    if (!$p) continue;

    if (!isset($hierarki[$p])) $hierarki[$p] = ['nama'=>$row['program_nama'], 'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k])) $hierarki[$p]['children'][$k] = ['nama'=>$row['kegiatan_nama'], 'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o])) $hierarki[$p]['children'][$k]['children'][$o] = ['nama'=>$row['output_nama'], 'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so] = ['nama'=>$row['sub_output_nama'], 'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom] = ['nama'=>$row['komponen_nama'], 'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk] = ['nama'=>$row['sub_komponen_nama'], 'children'=>[]];
    if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a] = ['nama'=>$row['akun_nama'], 'items'=>[]];

    $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]['items'][] = $row;
}

// ==============================
// 4. HITUNG TOTAL
// ==============================
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

// ==============================
// 5. SETUP EXCEL
// ==============================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->mergeCells('A1:N1')->setCellValue('A1', 'Laporan Realisasi Anggaran - Tahun '.$tahun_filter);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$header = ['Uraian Anggaran','Jumlah Pagu','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
$sheet->fromArray($header,NULL,'A3');
$sheet->getStyle('A3:N3')->getFont()->setBold(true);
$sheet->getStyle('A3:N3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');

$currencyFormat = '#,##0;-#,##0;0';
$allBorders = ['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFBFBFBF']]]];

// level style
$level_styles = [
    'program' => ['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFE6E6E6']]],
    'kegiatan'=> ['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFEDEDED']]],
    'output'  => ['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFF3F3F3']]],
    'suboutput'=> ['font'=>['bold'=>true]],
    'komponen'=> ['font'=>['bold'=>false]],
    'subkomponen'=> ['font'=>['italic'=>true]],
    'akun'    => ['font'=>['bold'=>true,'italic'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FFF8F8F8']]],
    'item'    => ['font'=>['bold'=>false]],
];

$level_order = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
$display_fields = [
    'program'=>'nama','kegiatan'=>'nama','output'=>'nama','suboutput'=>'nama','komponen'=>'nama',
    'subkomponen'=>'nama','akun'=>'nama','item'=>'item_nama'
];

// ==============================
// 6. CETAK JUMLAH KESELURUHAN DI BAWAH HEADER
// ==============================
$row_num = 4;
$grand_total_pagu = 0;
$grand_total_realisasi = array_fill(1,12,0.0);
foreach($hierarki as $p){
    $grand_total_pagu += $p['total_pagu'] ?? 0;
    foreach($p['total_realisasi_bulanan'] as $b=>$val) $grand_total_realisasi[$b] += $val;
}

$sheet->setCellValue("A{$row_num}", "JUMLAH KESELURUHAN");
$sheet->setCellValue("B{$row_num}", $grand_total_pagu);
for($b=1;$b<=12;$b++){
    $colLetter = Coordinate::stringFromColumnIndex($b+2);
    $sheet->setCellValue("{$colLetter}{$row_num}", $grand_total_realisasi[$b]);
}
$sheet->getStyle("A{$row_num}:N{$row_num}")->applyFromArray($level_styles['program']);
$row_num++;

// ==============================
// 7. CETAK TREE SESUAI SELECTED_LEVELS
// ==============================
function printExcelTree($sheet, $nodes, $level, &$row_num, $realisasi_data, $level_styles, $level_order, $display_fields, $selected_levels){
    foreach($nodes as $kode=>$node){
        $lvl_name = $level_order[$level] ?? 'item';
        if (!in_array($lvl_name,$selected_levels) && empty($node['items'])){
            if(!empty($node['children'])){
                printExcelTree($sheet,$node['children'],$level+1,$row_num,$realisasi_data,$level_styles,$level_order,$display_fields,$selected_levels);
            }
            continue;
        }

        $value = $node[$display_fields[$lvl_name]] ?? '';
        $sheet->setCellValue("A{$row_num}", str_repeat('  ',$level).$value);
        $sheet->setCellValue("B{$row_num}", $node['total_pagu'] ?? 0);
        for($b=1;$b<=12;$b++){
            $colLetter = Coordinate::stringFromColumnIndex($b+2);
            $sheet->setCellValue("{$colLetter}{$row_num}", $node['total_realisasi_bulanan'][$b] ?? 0);
        }
        $sheet->getStyle("A{$row_num}:N{$row_num}")->applyFromArray($level_styles[$lvl_name] ?? []);
        $sheet->getStyle("A{$row_num}")->getAlignment()->setIndent($level);
        $row_num++;

        if(!empty($node['items'])){
            foreach($node['items'] as $item){
                $sisa_item = $item['pagu'] - array_sum($realisasi_data[$item['kode_unik']] ?? []);
                $sheet->setCellValue("A{$row_num}", str_repeat('  ',$level+1).($item['item_nama'] ?? ''));
                $sheet->setCellValue("B{$row_num}", $item['pagu'] ?? 0);
                for($b=1;$b<=12;$b++){
                    $colLetter = Coordinate::stringFromColumnIndex($b+2);
                    $sheet->setCellValue("{$colLetter}{$row_num}", $realisasi_data[$item['kode_unik']][$b] ?? 0);
                }
                if($sisa_item != 0){
                    $sheet->getStyle("A{$row_num}:N{$row_num}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFC0C0');
                } else {
                    $sheet->getStyle("A{$row_num}:N{$row_num}")->applyFromArray($level_styles['item']);
                }
                $sheet->getStyle("A{$row_num}")->getAlignment()->setIndent($level+1);
                $row_num++;
            }
        }

        if(!empty($node['children'])){
            ksort($node['children']);
            printExcelTree($sheet,$node['children'],$level+1,$row_num,$realisasi_data,$level_styles,$level_order,$display_fields,$selected_levels);
        }
    }
}

if(!empty($hierarki)){
    ksort($hierarki);
    printExcelTree($sheet,$hierarki,0,$row_num,$realisasi_data,$level_styles,$level_order,$display_fields,$selected_levels);
}

// ==============================
// 8. STYLE & LEBAR KOLOM
// ==============================
$last_row = $row_num-1;
$sheet->getStyle("A3:N{$last_row}")->applyFromArray($allBorders);
$sheet->getStyle("B4:N{$last_row}")->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle("B4:N{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getColumnDimension('A')->setWidth(70);
$sheet->getColumnDimension('B')->setWidth(20);
for($i='C';$i!=='O';$i++) $sheet->getColumnDimension($i)->setWidth(18);

// ==============================
// 9. OUTPUT EXCEL
// ==============================
$filename = 'laporan_realisasi_'.$tahun_filter.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

?>
