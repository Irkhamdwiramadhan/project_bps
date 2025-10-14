<?php
require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// ===================== PARAMETER =====================
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$selected_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : [];

// ===================== AMBIL DATA HIERARKI =====================
$sql_hierarchy = "SELECT
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
LEFT JOIN master_program mp ON mk.program_id = mp.id
WHERE mi.tahun = ?
ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.id ASC";

$stmt = $koneksi->prepare($sql_hierarchy);
$stmt->bind_param("i", $tahun_filter);
$stmt->execute();
$result = $stmt->get_result();
$flat_data = [];
while ($row = $result->fetch_assoc()) $flat_data[] = $row;
$stmt->close();

// ===================== AMBIL DATA REALISASI =====================
$realisasi_data = [];
$sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ?";
$stmt = $koneksi->prepare($sql_realisasi);
$stmt->bind_param("i", $tahun_filter);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $realisasi_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah_realisasi'];
}
$stmt->close();

// ===================== BANGUN HIERARKI =====================
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

// ===================== HITUNG TOTAL =====================
function calculateTotals(&$node, $realisasi_data) {
    $total_pagu = 0;
    $total_realisasi = array_fill(1, 12, 0);
    if (isset($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)$item['pagu'];
            if (isset($realisasi_data[$item['kode_unik']])) {
                foreach ($realisasi_data[$item['kode_unik']] as $b => $val) {
                    $total_realisasi[$b] += (float)$val;
                }
            }
        }
    }
    if (isset($node['children'])) {
        foreach ($node['children'] as &$child) {
            calculateTotals($child, $realisasi_data);
            $total_pagu += $child['total_pagu'];
            foreach ($child['total_realisasi_bulanan'] as $b => $val) $total_realisasi[$b] += $val;
        }
    }
    $node['total_pagu'] = $total_pagu;
    $node['total_realisasi_bulanan'] = $total_realisasi;
}
foreach ($hierarki as &$program_node) calculateTotals($program_node, $realisasi_data);

// ===================== PDF =====================
class MYPDF extends TCPDF {
    public $tahun_laporan;
    public $selected_levels;

    function Header() {
        $this->SetFont('helvetica','B',12);
        $this->Cell(0,10,'Laporan Realisasi Anggaran - Tahun '.$this->tahun_laporan,0,1,'C');
        $this->SetFont('helvetica','',10);
        $this->Cell(0,0,'Level Detail: '.implode(', ',$this->selected_levels),0,1,'C');
        $this->Ln(5);
        $this->printTableHeader();
    }

    function printTableHeader() {
        $header = ['Uraian Anggaran','Pagu','Sisa Pagu','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        $w = [120,25,25,19,19,19,19,19,19,19,19,19,19,19,19];
        $this->SetFont('helvetica','B',7);
        $this->SetFillColor(200,200,200);
        foreach($header as $i=>$h){ $this->Cell($w[$i],7,$h,1,0,'C',1); }
        $this->Ln();
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica','I',8);
        $this->Cell(0,10,'Halaman '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),0,0,'C');
    }
}

$pdf = new MYPDF('L','mm','A3',true,'UTF-8',false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->selected_levels = $selected_levels;
$pdf->SetMargins(10,30,10);
$pdf->SetAutoPageBreak(true,25);
$pdf->AddPage();

// ===================== HITUNG JUMLAH KESELURUHAN =====================
$grand_pagu = 0;
$grand_realisasi = array_fill(1,12,0);
foreach ($hierarki as $pr) {
    $grand_pagu += $pr['total_pagu'];
    foreach ($pr['total_realisasi_bulanan'] as $b => $v) $grand_realisasi[$b] += $v;
}
$grand_sisa = $grand_pagu - array_sum($grand_realisasi);

// -------------------- CETAK BARIS JUMLAH KESELURUHAN --------------------
$w = [120,25,25,19,19,19,19,19,19,19,19,19,19,19,19];
$pdf->SetFont('helvetica','B',7);
$pdf->SetFillColor(220,220,220);
$pdf->Cell($w[0],7,'JUMLAH KESELURUHAN',1,0,'C',1);
$pdf->Cell($w[1],7,number_format($grand_pagu),1,0,'R',1);
$pdf->Cell($w[2],7,number_format($grand_sisa),1,0,'R',1);
for($b=1;$b<=12;$b++){
    $pdf->Cell($w[2+$b],7,number_format($grand_realisasi[$b]),1,0,'R',1);
}
$pdf->Ln();

// ===================== CETAK DATA REKURSIF =====================
$level_order = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];

function checkPageBreak($pdf){
    if($pdf->GetY() > 300){ 
        $pdf->AddPage();
    }
}

function printFilteredTree($pdf, $nodes, $realisasi_data, $level_now, $level_order, $selected_levels){
    $w = [120,25,25,19,19,19,19,19,19,19,19,19,19,19,19];
    foreach ($nodes as $kode => $node){
        $level_name = $level_order[$level_now];

        if (!in_array($level_name, $selected_levels) && isset($node['children'])){
            printFilteredTree($pdf, $node['children'], $realisasi_data, $level_now+1, $level_order, $selected_levels);
            continue;
        }

        if (in_array($level_name, $selected_levels)){
            checkPageBreak($pdf);
            $pdf->SetFillColor(235,235,235);
            $pdf->SetFont('helvetica','B',7);

            $total_realisasi_node = array_sum($node['total_realisasi_bulanan']);
            $sisa_pagu_node = $node['total_pagu'] - $total_realisasi_node;

            $pdf->Cell($w[0],6,str_repeat(' ', $level_now*4)."$kode - {$node['nama']}",1,0,'L',1);
            $pdf->Cell($w[1],6,number_format($node['total_pagu']),1,0,'R',1);
            $pdf->Cell($w[2],6,number_format($sisa_pagu_node),1,0,'R',1);
            for($b=1;$b<=12;$b++){
                $pdf->Cell($w[2+$b],6,number_format($node['total_realisasi_bulanan'][$b]),1,0,'R',1);
            }
            $pdf->Ln();
        }

        if (isset($node['items']) && in_array('item', $selected_levels)){
            foreach ($node['items'] as $item){
                checkPageBreak($pdf);
                $item_total = isset($realisasi_data[$item['kode_unik']]) ? array_sum($realisasi_data[$item['kode_unik']]) : 0;
                $sisa = $item['pagu'] - $item_total;

                $pdf->SetFillColor($sisa != 0 ? 255 : 255, $sisa != 0 ? 200 : 255, $sisa != 0 ? 200 : 255);

                $pdf->SetFont('helvetica','',7);
                $pdf->Cell($w[0],5,str_repeat(' ', ($level_now+1)*4)."- {$item['item_nama']}",1,0,'L',1);
                $pdf->Cell($w[1],5,number_format($item['pagu']),1,0,'R',1);
                $pdf->Cell($w[2],5,number_format($sisa),1,0,'R',1);
                for($b=1;$b<=12;$b++){
                    $val = $realisasi_data[$item['kode_unik']][$b] ?? 0;
                    $pdf->Cell($w[2+$b],5,number_format($val),1,0,'R',1);
                }
                $pdf->Ln();
            }
        }

        if (isset($node['children'])){
            printFilteredTree($pdf, $node['children'], $realisasi_data, $level_now+1, $level_order, $selected_levels);
        }
    }
}

printFilteredTree($pdf, $hierarki, $realisasi_data, 0, $level_order, $selected_levels);
$pdf->Output('laporan_realisasi_'.$tahun_filter.'.pdf','I');
?>
