<?php
// proses/cetak_realisasi_pdf.php
require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// -------------------- INPUT --------------------
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date("Y");
$raw_levels = isset($_GET['level_detail']) ? (array)$_GET['level_detail'] : [];

// Normalisasi pilihan level (case-insensitive) ke key internal
$map = [
    'program' => 'program','kegiatan' => 'kegiatan','output' => 'output','suboutput' => 'suboutput',
    'sub_output' => 'suboutput','komponen' => 'komponen','subkomponen' => 'subkomponen','sub_komponen' => 'subkomponen',
    'akun' => 'akun','item' => 'item'
];
$selected_levels = [];
foreach ($raw_levels as $r) {
    $k = strtolower(trim($r));
    $k = str_replace(' ', '', $k);
    if (isset($map[$k])) $selected_levels[] = $map[$k];
}
$selected_levels = array_values(array_unique($selected_levels));
// default semua level bila kosong
if (empty($selected_levels)) $selected_levels = ['program','kegiatan','output','suboutput','komponen','subkomponen','akun','item'];

// -------------------- AMBIL DATA MASTER (HIERARKI + ITEM) --------------------
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
if (!$stmt) { die("Prepare failed: (" . $koneksi->errno . ") " . $koneksi->error); }
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

// -------------------- AMBIL DATA RPD & REALISASI --------------------
$rpd_data = [];
$realisasi_data = [];

if (!empty($all_kode_uniks)) {
    // unikkan daftar
    $all_kode_uniks = array_values(array_unique($all_kode_uniks));
    // buat placeholder untuk IN (safety: kita bind param sebagai string)
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));

    // helper untuk bind_param dynamic (mysqli membutuhkan references)
    function refValues($arr){
        $refs = [];
        foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
        return $refs;
    }

    // RPD
    $types = 'i' . str_repeat('s', count($all_kode_uniks)); // tahun(int) + kode_unik(s)...
    $params = array_merge([$tahun_filter], $all_kode_uniks);
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    if ($stmt_rpd) {
        $bind = array_merge([$types], $params);
        call_user_func_array([$stmt_rpd, 'bind_param'], refValues($bind));
        $stmt_rpd->execute();
        $res = $stmt_rpd->get_result();
        while ($r = $res->fetch_assoc()) {
            $kode = $r['kode_unik_item'];
            $bulan = (int)$r['bulan'];
            $val = (float)$r['jumlah'];
            if (!isset($rpd_data[$kode])) $rpd_data[$kode] = array_fill(1,12,0.0);
            $rpd_data[$kode][$bulan] += $val;
        }
        $stmt_rpd->close();
    }

    // REALISASI
    $sql_rea = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rea = $koneksi->prepare($sql_rea);
    if ($stmt_rea) {
        $bind2 = array_merge([$types], $params);
        call_user_func_array([$stmt_rea, 'bind_param'], refValues($bind2));
        $stmt_rea->execute();
        $res2 = $stmt_rea->get_result();
        while ($r = $res2->fetch_assoc()) {
            $kode = $r['kode_unik_item'];
            $bulan = (int)$r['bulan'];
            $val = (float)$r['jumlah_realisasi'];
            if (!isset($realisasi_data[$kode])) $realisasi_data[$kode] = array_fill(1,12,0.0);
            $realisasi_data[$kode][$bulan] += $val;
        }
        $stmt_rea->close();
    }
}

// -------------------- BANGUN HIERARKI --------------------
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

// -------------------- HITUNG TOTAL REKURSIF --------------------
function calculateTotals(&$node, $rpd_data, $realisasi_data) {
    $total_pagu = 0.0;
    $total_rpd = array_fill(1,12,0.0);
    $total_rea = array_fill(1,12,0.0);

    if (!empty($node['items'])) {
        foreach ($node['items'] as $it) {
            $total_pagu += (float)($it['pagu'] ?? 0);
            $k = $it['kode_unik'] ?? null;
            if ($k && isset($rpd_data[$k])) {
                for ($b=1;$b<=12;$b++) $total_rpd[$b] += isset($rpd_data[$k][$b]) ? (float)$rpd_data[$k][$b] : 0.0;
            }
            if ($k && isset($realisasi_data[$k])) {
                for ($b=1;$b<=12;$b++) $total_rea[$b] += isset($realisasi_data[$k][$b]) ? (float)$realisasi_data[$k][$b] : 0.0;
            }
        }
    }

    if (!empty($node['children'])) {
        foreach ($node['children'] as &$c) {
            calculateTotals($c, $rpd_data, $realisasi_data);
            $total_pagu += (float)($c['total_pagu'] ?? 0);
            if (!empty($c['total_rpd_bulanan'])) {
                foreach ($c['total_rpd_bulanan'] as $b => $v) $total_rpd[$b] += (float)$v;
            }
            if (!empty($c['total_realisasi_bulanan'])) {
                foreach ($c['total_realisasi_bulanan'] as $b => $v) $total_rea[$b] += (float)$v;
            }
        }
        unset($c);
    }

    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd;
    $node['total_realisasi_bulanan'] = $total_rea;
}

foreach ($hierarki as &$pnode) calculateTotals($pnode, $rpd_data, $realisasi_data);
unset($pnode);

// -------------------- SETUP TCPDF --------------------
class MYPDF extends TCPDF {
    public $tahun_laporan;
    public $selected_levels;
    public $col_widths = ['uraian' => 90, 'pagu' => 20, 'bulan' => 40]; // bulan dibagi 2 (RPD & Realisasi)

    function Header() {
        $this->SetFont('helvetica','B',12);
        $this->Cell(0,10,'Laporan RPD & Realisasi - Tahun '.$this->tahun_laporan,0,1,'C');
        $this->SetFont('helvetica','',9);
        $this->Cell(0,0,'Level Detail: '.(is_array($this->selected_levels)?implode(', ',$this->selected_levels):''),0,1,'C');
        $this->Ln(4);
        $this->printTableHeader();
    }

    function printTableHeader() {
        $w = $this->col_widths;
        $w_sub = $w['bulan'] / 2;
        $this->SetFont('helvetica','B',8);
        $this->SetFillColor(210,210,210);
        $h1 = 7; $h2 = 6;

        // first row
        $this->MultiCell($w['uraian'], $h1+$h2, "\nUraian Anggaran", 1, 'C', 1, 0, '', '', true, 0, false, true, $h1+$h2, 'M');
        $this->MultiCell($w['pagu'], $h1+$h2, "\nJumlah Pagu", 1, 'C', 1, 0, '', '', true, 0, false, true, $h1+$h2, 'M');

        $xStart = $this->GetX();
        for ($m=1;$m<=12;$m++) {
            $month = DateTime::createFromFormat('!m',$m)->format('M');
            $this->MultiCell($w['bulan'], $h1, $month, 1, 'C', 1, 0, '', '', true, 0, false, true, $h1, 'M');
        }
        $this->Ln($h1);

        // second row (subcolumns RPD & Realisasi)
        $this->SetX($xStart);
        $this->SetFont('helvetica','B',7);
        for ($m=1;$m<=12;$m++) {
            $this->Cell($w_sub, $h2, 'RPD', 1, 0, 'C', 1);
            $this->Cell($w_sub, $h2, 'Realisasi', 1, 0, 'C', 1);
        }
        $this->Ln($h2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica','I',8);
        $this->Cell(0,10,'Halaman '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),0,0,'C');
    }
}

$pdf = new MYPDF('L','mm','A2', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->selected_levels = $selected_levels;
$pdf->SetMargins(8, 45, 8);
$pdf->SetAutoPageBreak(TRUE, 18);
$pdf->AddPage();

// -------------------- PAGE BREAK CHECK --------------------
function checkPageBreak($pdf, $neededHeight = 10) {
    $y = $pdf->GetY();
    $pageHeight = $pdf->getPageHeight();
    $bottom = $pdf->getBreakMargin();
    if ($y + $neededHeight > ($pageHeight - $bottom)) {
        $pdf->AddPage();
    }
}

// -------------------- CETAK REKURSIF --------------------
function printTree($pdf, $nodes, $level, $rpd_data, $realisasi_data, $selected_levels) {
    $w = $pdf->col_widths;
    $w_sub = $w['bulan'] / 2;
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
        // Jika level saat ini tidak dipilih -> turun ke anak
        if (!in_array($level_name, $selected_levels) && !empty($node['children'])) {
            printTree($pdf, $node['children'], $level+1, $rpd_data, $realisasi_data, $selected_levels);
            continue;
        }

        // Cetak row summary level bila dipilih
        if (in_array($level_name, $selected_levels)) {
            checkPageBreak($pdf, 8);
            $pdf->SetFont('helvetica', $style['font'], $style['size']);
            $pdf->SetFillColor(...$style['fill']);
            $pdf->Cell($w['uraian'], 6, str_repeat(' ', $style['indent']) . "{$kode} - " . ($node['nama'] ?? '-'), 1, 0, 'L', 1);
            $pdf->Cell($w['pagu'], 6, number_format($node['total_pagu'] ?? 0), 1, 0, 'R', 1);

            $rpd_b = is_array($node['total_rpd_bulanan'] ?? null) ? $node['total_rpd_bulanan'] : array_fill(1,12,0.0);
            $rea_b = is_array($node['total_realisasi_bulanan'] ?? null) ? $node['total_realisasi_bulanan'] : array_fill(1,12,0.0);

            for ($m=1;$m<=12;$m++) {
                $pdf->Cell($w_sub, 6, number_format($rpd_b[$m] ?? 0), 1, 0, 'R', 1);
                $pdf->Cell($w_sub, 6, number_format($rea_b[$m] ?? 0), 1, 0, 'R', 1);
            }
            $pdf->Ln();
        }

        // Cetak items bila item dipilih
        if (!empty($node['items']) && in_array('item', $selected_levels)) {
            foreach ($node['items'] as $item) {
                checkPageBreak($pdf, 8);
                $kode_unik = $item['kode_unik'] ?? null;
                $pagu_item = (float)($item['pagu'] ?? 0);
                $rpd_vals = isset($rpd_data[$kode_unik]) ? $rpd_data[$kode_unik] : array_fill(1,12,0.0);
                $rea_vals = isset($realisasi_data[$kode_unik]) ? $realisasi_data[$kode_unik] : array_fill(1,12,0.0);
                $total_rea_item = array_sum($rea_vals);
                $sisa = $pagu_item - $total_rea_item;

                $is_red = ($sisa != 0.0);

                // uraian (MultiCell karena panjang)
                $pdf->SetFont('helvetica','',7);
                if ($is_red) $pdf->SetFillColor(255,200,200); else $pdf->SetFillColor(255,255,255);

                $xStart = $pdf->GetX();
                $yStart = $pdf->GetY();

                $uraian = str_repeat(' ', $style['indent'] + 4) . "- " . ($item['item_nama'] ?? '-');
                $pdf->MultiCell($w['uraian'], 5, $uraian, 1, 'L', 1, 0);
                $uraian_h = $pdf->GetY() - $yStart;
                $row_h = max(5, $uraian_h);

                // kolom lain di-align ke tinggi tersebut
                $pdf->SetXY($xStart + $w['uraian'], $yStart);
                $pdf->Cell($w['pagu'], $row_h, number_format($pagu_item), 1, 0, 'R', 1);

                for ($m=1;$m<=12;$m++) {
                    $pdf->Cell($w_sub, $row_h, number_format($rpd_vals[$m] ?? 0), 1, 0, 'R', 1);
                    $pdf->Cell($w_sub, $row_h, number_format($rea_vals[$m] ?? 0), 1, 0, 'R', 1);
                }
                $pdf->Ln($row_h);
            }
        }

        // Rekursif ke anak
        if (!empty($node['children'])) {
            printTree($pdf, $node['children'], $level+1, $rpd_data, $realisasi_data, $selected_levels);
        }
    }
}

// -------------------- HITUNG & CETAK GRAND TOTAL DI ATAS --------------------
$grand_pagu = 0.0;
$grand_rpd = array_fill(1,12,0.0);
$grand_rea = array_fill(1,12,0.0);
foreach ($hierarki as $pr) {
    $grand_pagu += (float)($pr['total_pagu'] ?? 0);
    if (!empty($pr['total_rpd_bulanan'])) {
        foreach ($pr['total_rpd_bulanan'] as $b => $v) $grand_rpd[$b] += (float)$v;
    }
    if (!empty($pr['total_realisasi_bulanan'])) {
        foreach ($pr['total_realisasi_bulanan'] as $b => $v) $grand_rea[$b] += (float)$v;
    }
}

checkPageBreak($pdf, 8);
$pdf->SetFont('helvetica','B',8);
$pdf->SetFillColor(220,220,220);
$pdf->Cell($pdf->col_widths['uraian'], 7, 'JUMLAH KESELURUHAN', 1, 0, 'C', 1);
$pdf->Cell($pdf->col_widths['pagu'], 7, number_format($grand_pagu), 1, 0, 'R', 1);
$w_sub = $pdf->col_widths['bulan'] / 2;
for ($b=1;$b<=12;$b++) {
    $pdf->Cell($w_sub, 7, number_format($grand_rpd[$b] ?? 0), 1, 0, 'R', 1);
    $pdf->Cell($w_sub, 7, number_format($grand_rea[$b] ?? 0), 1, 0, 'R', 1);
}
$pdf->Ln();

// -------------------- MULAI CETAK POHON --------------------
if (!empty($hierarki)) {
    printTree($pdf, $hierarki, 0, $rpd_data, $realisasi_data, $selected_levels);
} else {
    $pdf->Cell(0,10,'Tidak ada data untuk tahun '.$tahun_filter,1,1,'C');
}

// -------------------- OUTPUT --------------------
$pdf->Output('laporan_rpd_realisasi_'.$tahun_filter.'.pdf','I');
exit;
?>
