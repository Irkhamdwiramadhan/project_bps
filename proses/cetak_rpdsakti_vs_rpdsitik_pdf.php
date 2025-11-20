<?php
// proses/cetak_rekonsiliasi_pdf.php
require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// ===================== 1. TANGKAP FILTER =====================
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

// ===================== 2. QUERY STRUKTUR UTAMA =====================
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
if (!$stmt) { die("Prepare failed: " . $koneksi->error); }
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

// ===================== 3. AMBIL DATA RPD =====================
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

// ===================== 4. HIERARKI & TOTAL =====================
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

// ===================== 5. SETUP PDF & UPDATE LAYOUT =====================
class MYPDF extends TCPDF {
    public $tahun_laporan;
    public $selected_levels;
    // UPDATE 1: Menambahkan 'validasi' => 25
    public $col_widths = ['uraian' => 120, 'pagu' => 30, 'bulan' => 48, 'validasi' => 25]; 

    function Header() {
        $this->SetFont('helvetica','B',12);
        $this->Cell(0,10,'Rekonsiliasi RPD (SAKTI vs SITIK) - Tahun '.$this->tahun_laporan,0,1,'C');
        $this->SetFont('helvetica','',9);
        $this->Cell(0,0,'Level Detail: '.(is_array($this->selected_levels)?implode(', ',$this->selected_levels):''),0,1,'C');
        $this->Ln(4);
        $this->printTableHeader();
    }

    function printTableHeader() {
        $w = $this->col_widths; $w_sub = $w['bulan'] / 2;
        $this->SetFont('helvetica','B',8); $this->SetFillColor(210,210,210);
        $h1 = 7; $h2 = 6;

        // Baris 1 Header
        $this->MultiCell($w['uraian'], $h1+$h2, "\nUraian Anggaran", 1, 'C', 1, 0, '', '', true, 0, false, true, $h1+$h2, 'M');
        $this->MultiCell($w['pagu'], $h1+$h2, "\nJumlah Pagu", 1, 'C', 1, 0, '', '', true, 0, false, true, $h1+$h2, 'M');
        
        $xStart = $this->GetX();
        for ($m=1;$m<=12;$m++) { 
            $this->MultiCell($w['bulan'], $h1, DateTime::createFromFormat('!m',$m)->format('M'), 1, 'C', 1, 0, '', '', true, 0, false, true, $h1, 'M'); 
        }
        
        // UPDATE 2: Header Kolom Validasi
        $this->MultiCell($w['validasi'], $h1+$h2, "\nVALIDASI", 1, 'C', 1, 0, '', '', true, 0, false, true, $h1+$h2, 'M');
        
        $this->Ln($h1);
        
        // Baris 2 Header (Sub Kolom)
        $this->SetX($xStart); $this->SetFont('helvetica','B',6); 
        for ($m=1;$m<=12;$m++) { 
            $this->Cell($w_sub, $h2, 'SAKTI', 1, 0, 'C', 1); 
            $this->Cell($w_sub, $h2, 'SITIK', 1, 0, 'C', 1); 
        }
        $this->Ln($h2);
    }
    
    function Footer() {
        $this->SetY(-15); $this->SetFont('helvetica','I',8);
        $this->Cell(0,10,'Halaman '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),0,0,'C');
    }
}

$pdf = new MYPDF('L','mm','A1', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->selected_levels = $selected_levels;
$pdf->SetMargins(8, 45, 8);
$pdf->SetAutoPageBreak(TRUE, 18);
$pdf->AddPage();

function checkPageBreak($pdf, $neededHeight = 10) {
    if ($pdf->GetY() + $neededHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
        $pdf->AddPage();
    }
}

// ===================== 6. PRINT TREE (DENGAN VALIDASI) =====================
function printTree($pdf, $nodes, $level, $rpd_sitik, $rpd_sakti, $selected_levels) {
    $w = $pdf->col_widths; $w_sub = $w['bulan'] / 2;
    $level_map = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];
    
    $level_styles = [
        ['font'=>'B','size'=>9,'indent'=>0,'fill'=>[245,245,245]], 
        ['font'=>'B','size'=>8.5,'indent'=>4,'fill'=>[247,247,247]],
        ['font'=>'B','size'=>8,'indent'=>8,'fill'=>[250,250,250]], 
        ['font'=>'B','size'=>7.5,'indent'=>12,'fill'=>[255,255,255]],
        ['font'=>'B','size'=>7,'indent'=>16,'fill'=>[255,255,255]], 
        ['font'=>'B','size'=>7,'indent'=>20,'fill'=>[255,255,255]],
        ['font'=>'BI','size'=>7,'indent'=>24,'fill'=>[255,255,255]],
    ];
    $style = $level_styles[$level] ?? end($level_styles);
    $level_name = $level_map[$level] ?? 'item';

    foreach ($nodes as $kode => $node) {
        if (!in_array($level_name, $selected_levels) && !empty($node['children'])) {
            printTree($pdf, $node['children'], $level+1, $rpd_sitik, $rpd_sakti, $selected_levels);
            continue;
        }

        // --- CETAK HEADER/PARENT ---
        if (in_array($level_name, $selected_levels)) {
            checkPageBreak($pdf, 8);
            $pdf->SetFont('helvetica', $style['font'], $style['size']); 
            $pdf->SetFillColor(...$style['fill']);
            
            $pdf->Cell($w['uraian'], 6, str_repeat(' ', $style['indent']) . "{$kode} - " . ($node['nama'] ?? '-'), 1, 0, 'L', 1);
            $pdf->Cell($w['pagu'], 6, number_format($node['total_pagu'] ?? 0), 1, 0, 'R', 1);
            
            $sitik_b = $node['total_sitik_bulanan'] ?? array_fill(1,12,0.0);
            $sakti_b = $node['total_sakti_bulanan'] ?? array_fill(1,12,0.0);
            
            for ($m=1;$m<=12;$m++) {
                $v_sakti = $sakti_b[$m] ?? 0;
                $v_sitik = $sitik_b[$m] ?? 0;
                
                $is_diff = abs($v_sakti - $v_sitik) > 1; 
                $pdf->SetFillColor($is_diff ? 255 : $style['fill'][0], $is_diff ? 230 : $style['fill'][1], $is_diff ? 230 : $style['fill'][2]);

                $pdf->Cell($w_sub, 6, number_format($v_sakti), 1, 0, 'R', 1);
                $pdf->Cell($w_sub, 6, number_format($v_sitik), 1, 0, 'R', 1);
            }

            // === UPDATE 3: KOLOM VALIDASI PARENT ===
            $total_sakti_row = array_sum($sakti_b);
            $total_sitik_row = array_sum($sitik_b);
            $is_valid = abs($total_sakti_row - $total_sitik_row) <= 1; // Toleransi 1

            if ($is_valid) {
                $pdf->SetFillColor(200, 255, 200); // Hijau Muda (Opsional, biar rapi)
                $txt_val = "VALID";
            } else {
                $pdf->SetFillColor(255, 100, 100); // MERAH
                $txt_val = "CEK LAGI";
            }
            $pdf->Cell($w['validasi'], 6, $txt_val, 1, 0, 'C', 1);

            $pdf->Ln();
        }

        // --- CETAK ITEM ---
        if (!empty($node['items']) && in_array('item', $selected_levels)) {
            foreach ($node['items'] as $item) {
                checkPageBreak($pdf, 8);
                $kode_unik = $item['kode_unik'] ?? null; 
                $pagu_item = (float)($item['pagu'] ?? 0);
                
                $sitik_vals = $rpd_sitik[$kode_unik] ?? array_fill(1,12,0.0);
                $sakti_vals = $rpd_sakti[$kode_unik] ?? array_fill(1,12,0.0);
                
                $pdf->SetFont('helvetica','',7);
                
                $xStart = $pdf->GetX(); $yStart = $pdf->GetY();
                $uraian = str_repeat(' ', $style['indent'] + 4) . "- " . ($item['item_nama'] ?? '-');
                
                $pdf->SetFillColor(255,255,255);
                $pdf->MultiCell($w['uraian'], 5, $uraian, 1, 'L', 1, 0);
                
                $row_h = max(5, $pdf->GetY() - $yStart);
                $pdf->SetXY($xStart + $w['uraian'], $yStart);
                
                $pdf->Cell($w['pagu'], $row_h, number_format($pagu_item), 1, 0, 'R', 1);
                
                for ($m=1;$m<=12;$m++) {
                    $val_sakti = $sakti_vals[$m] ?? 0;
                    $val_sitik = $sitik_vals[$m] ?? 0;
                    
                    $diff = abs($val_sakti - $val_sitik) > 1; 
                    if ($diff) $pdf->SetFillColor(255, 200, 200); 
                    else $pdf->SetFillColor(255, 255, 255); 

                    $pdf->Cell($w_sub, $row_h, number_format($val_sakti), 1, 0, 'R', 1);
                    $pdf->Cell($w_sub, $row_h, number_format($val_sitik), 1, 0, 'R', 1);
                }

                // === UPDATE 4: KOLOM VALIDASI ITEM ===
                $total_sakti_row = array_sum($sakti_vals);
                $total_sitik_row = array_sum($sitik_vals);
                $is_valid = abs($total_sakti_row - $total_sitik_row) <= 1;

                if ($is_valid) {
                    $pdf->SetFillColor(255, 255, 255); // Putih kalau valid
                    $txt_val = "VALID";
                } else {
                    $pdf->SetFillColor(255, 100, 100); // MERAH kalau salah
                    $txt_val = "CEK LAGI";
                }
                $pdf->Cell($w['validasi'], $row_h, $txt_val, 1, 0, 'C', 1);

                $pdf->Ln($row_h);
            }
        }

        if (!empty($node['children'])) {
            printTree($pdf, $node['children'], $level+1, $rpd_sitik, $rpd_sakti, $selected_levels);
        }
    }
}

if (!empty($hierarki)) {
    printTree($pdf, $hierarki, 0, $rpd_sitik, $rpd_sakti, $selected_levels);
} else {
    $pdf->Cell(0,10,'Tidak ada data untuk tahun '.$tahun_filter,1,1,'C');
}

$pdf->Output('rekonsiliasi_rpd_'.$tahun_filter.'.pdf','I');
exit;
?>