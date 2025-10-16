<?php
require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// ===================== 1. TANGKAP SEMUA FILTER DARI URL =====================
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$selected_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : [];

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
    mi.nama_item AS item_nama, mi.pagu, mi.kode_unik, mi.id
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

// Mapping kolom filter di database
$filter_column_map = [
    'program_id' => 'mp.id',
    'kegiatan_id' => 'mk.id',
    'output_id' => 'mo.id',
    'sub_output_id' => 'mso.id',
    'komponen_id' => 'mkom.id',
    'sub_komponen_id' => 'msk.id',
    'akun_id' => 'ma.id',
];

// Tambahkan klausa WHERE hanya jika filter dipilih
foreach ($filters as $key => $value) {
    if ($value !== null) {
        $where_clauses[] = $filter_column_map[$key] . " = ?";
        $param_types .= "i"; // Semua ID adalah integer
        $param_values[] = $value;
    }
}

$sql_hierarchy .= " WHERE " . implode(" AND ", $where_clauses);
$sql_hierarchy .= " ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.id ASC";

$stmt_hierarchy = $koneksi->prepare($sql_hierarchy);
$stmt_hierarchy->bind_param($param_types, ...$param_values); // Gunakan splat operator
$stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result();

$flat_data = [];
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
}
$stmt_hierarchy->close();

// Jika tidak ada data sama sekali setelah filter, tampilkan pesan
if (empty($flat_data)) {
    die("Tidak ada data yang ditemukan untuk filter yang dipilih.");
}

// ===================== AMBIL DATA RPD (Tidak perlu diubah) =====================
$rpd_data = [];
$sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ?";
$stmt_rpd = $koneksi->prepare($sql_rpd);
$stmt_rpd->bind_param("i", $tahun_filter);
$stmt_rpd->execute();
$result_rpd = $stmt_rpd->get_result();
while ($row = $result_rpd->fetch_assoc()) {
    $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
}
$stmt_rpd->close();

// ===================== BANGUN HIERARKI (Tidak perlu diubah) =====================
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

// ===================== HITUNG TOTAL (Tidak perlu diubah) =====================
function calculateTotals(&$node, $rpd_data) {
    $total_pagu = 0;
    $total_rpd = array_fill(1, 12, 0);
    if (isset($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)$item['pagu'];
            if (isset($rpd_data[$item['kode_unik']])) {
                foreach ($rpd_data[$item['kode_unik']] as $b => $val) {
                    $total_rpd[$b] += (float)$val;
                }
            }
        }
    }
    if (isset($node['children'])) {
        foreach ($node['children'] as &$child) {
            calculateTotals($child, $rpd_data);
            $total_pagu += $child['total_pagu'];
            foreach ($child['total_rpd_bulanan'] as $b => $val) $total_rpd[$b] += $val;
        }
    }
    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd;
}
foreach ($hierarki as &$program_node) calculateTotals($program_node, $rpd_data);

// ===================== PDF GENERATION (Tidak perlu diubah) =====================
class MYPDF extends TCPDF {
    // ... isi class MYPDF sama persis seperti kode Anda sebelumnya ...
    public $tahun_laporan;
    public $selected_levels;

    function Header() {
        $this->SetFont('helvetica','B',12);
        $this->Cell(0,10,'Laporan Rencana Penarikan Dana (RPD) - Tahun '.$this->tahun_laporan,0,1,'C');
        $this->SetFont('helvetica','',8);
        $this->Cell(0,0,'Level Detail: '.ucwords(implode(', ',$this->selected_levels)),0,1,'C');
        $this->Ln(5);
        $this->printTableHeader();
    }

    function printTableHeader() {
        $header = ['Uraian Anggaran','Pagu','Sisa Pagu','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        $w = [120,25,25,19,19,19,19,19,19,19,19,19,19,19,19]; // Sesuaikan dengan orientasi Landscape A3
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

$level_order = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];

function checkPageBreak($pdf) {
    if($pdf->GetY() > ($pdf->getPageHeight() - 30)){ // Cek jika Y mendekati batas bawah margin
        $pdf->AddPage();
    }
}

function printFilteredTree($pdf, $nodes, $rpd_data, $level_now, $level_order, $selected_levels) {
    // ... isi fungsi printFilteredTree sama persis seperti kode Anda sebelumnya ...
    $w = [120,25,25,19,19,19,19,19,19,19,19,19,19,19,19];
    foreach ($nodes as $kode => $node) {
        $level_name = $level_order[$level_now];
        
        if (!in_array($level_name, $selected_levels) && isset($node['children'])) {
            printFilteredTree($pdf, $node['children'], $rpd_data, $level_now+1, $level_order, $selected_levels);
            continue;
        }

        if (in_array($level_name, $selected_levels)) {
            checkPageBreak($pdf);
            $pdf->SetFillColor(235,235,235);
            $pdf->SetFont('helvetica','B',7);

            $total_rpd_node = array_sum($node['total_rpd_bulanan']);
            $sisa_pagu_node = $node['total_pagu'] - $total_rpd_node;

            $pdf->Cell($w[0],6,str_repeat('  ', $level_now)."$kode - {$node['nama']}",1,0,'L',1);
            $pdf->Cell($w[1],6,number_format($node['total_pagu']),1,0,'R',1);
            $pdf->Cell($w[2],6,number_format($sisa_pagu_node),1,0,'R',1);
            for($b=1;$b<=12;$b++) {
                $pdf->Cell($w[2+$b],6,number_format($node['total_rpd_bulanan'][$b]),1,0,'R',1);
            }
            $pdf->Ln();
        }

        if (isset($node['items']) && in_array('item', $selected_levels)) {
            foreach ($node['items'] as $item) {
                checkPageBreak($pdf);
                $item_total = isset($rpd_data[$item['kode_unik']]) ? array_sum($rpd_data[$item['kode_unik']]) : 0;
                $sisa = $item['pagu'] - $item_total;

                $pdf->SetFillColor(($sisa != 0) ? 255 : 255, ($sisa != 0) ? 220 : 255, ($sisa != 0) ? 220 : 255);

                $pdf->SetFont('helvetica','',7);
                $pdf->Cell($w[0],5,str_repeat('  ', ($level_now+1))."- {$item['item_nama']}",1,0,'L',1);
                $pdf->Cell($w[1],5,number_format($item['pagu']),1,0,'R',1);
                $pdf->Cell($w[2],5,number_format($sisa),1,0,'R',1);

                for($b=1;$b<=12;$b++){
                    $val = $rpd_data[$item['kode_unik']][$b] ?? 0;
                    $pdf->Cell($w[2+$b],5,number_format($val),1,0,'R',1);
                }
                $pdf->Ln();
            }
        }

        if (isset($node['children'])) {
            printFilteredTree($pdf, $node['children'], $rpd_data, $level_now+1, $level_order, $selected_levels);
        }
    }
}

printFilteredTree($pdf, $hierarki, $rpd_data, 0, $level_order, $selected_levels);
$pdf->Output('laporan_rpd_'.$tahun_filter.'.pdf','I');
?>