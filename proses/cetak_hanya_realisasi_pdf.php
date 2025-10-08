<?php
// proses/cetak_hanya_realisasi_pdf.php (REVISI FINAL - FIXED)

// NOTE: pastikan composer autoload & koneksi sudah benar
require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. PENGAMBILAN & PEMROSESAN DATA
// =========================================================================
$sql_hierarchy = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama, mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama, mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama, msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama, mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
FROM master_item mi
LEFT JOIN master_akun ma ON mi.akun_id = ma.id
LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id
LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
LEFT JOIN master_output mo ON mso.output_id = mo.id
LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
LEFT JOIN master_program mp ON mk.program_id = mp.id
WHERE mi.tahun = ? ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.nama_item ASC";

$stmt_hierarchy = $koneksi->prepare($sql_hierarchy);
$stmt_hierarchy->bind_param("i", $tahun_filter);
$stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result();
$flat_data = [];
$all_kode_uniks = [];
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt_hierarchy->close();

$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    // Prepare placeholders safely
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);

    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    // bind_param requires variables, so use argument unpacking
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) {
        // ensure bulan is integer and jumlah_realisasi numeric
        $kode = $row['kode_unik_item'];
        $bulan = (int)$row['bulan'];
        $jumlah = is_numeric($row['jumlah_realisasi']) ? (float)$row['jumlah_realisasi'] : 0.0;
        $realisasi_data[$kode][$bulan] = $jumlah;
    }
    $stmt_realisasi->close();
}

// Build hierarchical structure
$hierarki = [];
foreach ($flat_data as $row) {
    $p_kode = $row['program_kode']; $k_kode = $row['kegiatan_kode']; $o_kode = $row['output_kode']; $so_kode = $row['sub_output_kode'];
    $kom_kode = $row['komponen_kode']; $sk_kode = $row['sub_komponen_kode']; $a_kode = $row['akun_kode'];

    if (!$p_kode) continue;

    if (!isset($hierarki[$p_kode])) {
        $hierarki[$p_kode] = ['nama' => $row['program_nama'], 'children' => []];
    }
    if (!isset($hierarki[$p_kode]['children'][$k_kode])) {
        $hierarki[$p_kode]['children'][$k_kode] = ['nama' => $row['kegiatan_nama'], 'children' => []];
    }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode])) {
        $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode] = ['nama' => $row['output_nama'], 'children' => []];
    }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode])) {
        $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode] = ['nama' => $row['sub_output_nama'], 'children' => []];
    }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode])) {
        $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode] = ['nama' => $row['komponen_nama'], 'children' => []];
    }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode])) {
        $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode] = ['nama' => $row['sub_komponen_nama'], 'children' => []];
    }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode])) {
        $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode] = ['nama' => $row['akun_nama'], 'items' => []];
    }

    $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode]['items'][] = $row;
}

// =========================================================================
// calculateTotals: recursive and robust (ensures arrays always exist)
// =========================================================================
function calculateTotals(&$node, $realisasi_data) {
    // Ensure monthly array exists
    if (!isset($node['total_realisasi_bulanan']) || !is_array($node['total_realisasi_bulanan'])) {
        $node['total_realisasi_bulanan'] = array_fill(1, 12, 0.0);
    }

    $total_pagu = 0.0;
    $total_realisasi_bulanan = array_fill(1, 12, 0.0);

    // items
    if (isset($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)($item['pagu'] ?? 0);
            $kode = $item['kode_unik'] ?? null;
            if ($kode && isset($realisasi_data[$kode]) && is_array($realisasi_data[$kode])) {
                foreach ($realisasi_data[$kode] as $bulan => $jumlah) {
                    $idx = (int)$bulan;
                    if ($idx >= 1 && $idx <= 12) {
                        $total_realisasi_bulanan[$idx] += (float)$jumlah;
                    }
                }
            }
        }
    }

    // children
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            calculateTotals($child, $realisasi_data);

            $total_pagu += (float)($child['total_pagu'] ?? 0);
            // ensure child monthly array is valid
            if (!isset($child['total_realisasi_bulanan']) || !is_array($child['total_realisasi_bulanan'])) {
                $child['total_realisasi_bulanan'] = array_fill(1, 12, 0.0);
            }
            for ($m = 1; $m <= 12; $m++) {
                $total_realisasi_bulanan[$m] += (float)($child['total_realisasi_bulanan'][$m] ?? 0.0);
            }
        }
        unset($child);
    }

    // finalize
    $node['total_pagu'] = $total_pagu;
    $node['total_realisasi_bulanan'] = $total_realisasi_bulanan;
}

// run totals
foreach ($hierarki as &$program_node) {
    calculateTotals($program_node, $realisasi_data);
}
unset($program_node);

// =========================================================================
// 2. SETUP DOKUMEN PDF (perbaikan: jangan pakai properti name 'w' karena konflik internal TCPDF)
// =========================================================================
class MYPDF_KHUSUS_REALISASI extends TCPDF {
    public $tahun_laporan;

    // gunakan nama lain: colWidths (agar tidak override TCPDF::$w)
    public $colWidths = [
        120, // Uraian
        25, // Pagu
        20,20,20,20,20,20,20,20,20,20,20,20 // Jan..Des (12)
    ];

    public function Header() {
        // ensure colWidths valid
        if (!is_array($this->colWidths) || count($this->colWidths) < 14) {
            $this->colWidths = [85, 25];
            for ($i = 0; $i < 12; $i++) $this->colWidths[] = 20;
        }

        $this->SetFont('helvetica', 'B', 12);
        // gunakan lebar tetap (190) untuk judul agar tidak men-trigger kalkulasi internal dengan array
        $this->Cell(190, 20, 'Laporan Realisasi Anggaran (Bulanan) - Tahun ' . ($this->tahun_laporan ?? ''), 0, 1, 'C');

        $this->SetY($this->GetY() + 2);
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(230, 230, 230);

        $header = [
            'Uraian Anggaran', 'Jumlah Pagu',
            'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'
        ];

        for ($i = 0; $i < count($header); $i++) {
            $cell_width = isset($this->colWidths[$i]) ? $this->colWidths[$i] : 20;
            $this->Cell($cell_width, 7, $header[$i], 1, 0, 'C', 1);
        }
        $this->Ln();
    }

    public function Footer() {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

// create PDF
$pdf = new MYPDF_KHUSUS_REALISASI('L', 'mm', 'A3', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetMargins(10, 30, 10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

// =========================================================================
// 3. FUNGSI REKURSIF UNTUK MENCETAK
// =========================================================================
function printRealizationOnlyTree($pdf, $nodes, $level, $realisasi_data) {
    // gunakan colWidths dari instance
    $col = $pdf->colWidths;
    $level_styles = [
        ['font' => ['style' => 'B', 'size' => 8], 'fill' => [220,220,220], 'indent' => 0],
        ['font' => ['style' => 'B', 'size' => 7.5], 'fill' => [225,225,225], 'indent' => 5],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [230,230,230], 'indent' => 10],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [235,235,235], 'indent' => 20],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [240,240,240], 'indent' => 20],
        ['font' => ['style' => 'BI', 'size' => 7], 'fill' => [245,245,245], 'indent' => 25],
    ];
    $current_style = $level_styles[$level] ?? end($level_styles);

    foreach ($nodes as $kode => $node) {
        $nama_node = $node['nama'] ?? 'Uraian Tdk Ditemukan';
        $pdf->SetFont('helvetica', $current_style['font']['style'], $current_style['font']['size']);
        $pdf->SetFillColor($current_style['fill'][0], $current_style['fill'][1], $current_style['fill'][2]);

        // ensure node arrays exist
        $total_pagu = isset($node['total_pagu']) ? (float)$node['total_pagu'] : 0.0;
        $realisasi_bulanan = (isset($node['total_realisasi_bulanan']) && is_array($node['total_realisasi_bulanan']))
            ? $node['total_realisasi_bulanan']
            : array_fill(1, 12, 0.0);

        // baris node (level)
        $pdf->Cell($col[0], 6, str_repeat(' ', $current_style['indent']) . "{$kode} - {$nama_node}", 1, 0, 'L', 1);
        $pdf->Cell($col[1], 6, number_format($total_pagu), 1, 0, 'R', 1);
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $val = (float)($realisasi_bulanan[$bulan] ?? 0.0);
            $pdf->Cell($col[1 + $bulan], 6, number_format($val), 1, 0, 'R', 1);
        }
        $pdf->Ln();

        // items (akun -> items)
        if (!empty($node['items']) && is_array($node['items'])) {
            $pdf->SetFont('helvetica', '', 7);
            foreach ($node['items'] as $item) {
                $pdf->setCellPaddings(1,1,1,1);
                $item_name = $item['item_nama'] ?? '';
                $item_pagu = (float)($item['pagu'] ?? 0.0);

                // uraian (indent)
                $pdf->MultiCell($col[0], 5, str_repeat(' ', $current_style['indent'] + 5) . "- " . $item_name, 1, 'L', 0, 0);
                // pagu item
                $pdf->MultiCell($col[1], 5, number_format($item_pagu), 1, 'R', 0, 0);

                // bulan
                $kode_unik = $item['kode_unik'] ?? null;
                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $re_val = ($kode_unik && isset($realisasi_data[$kode_unik]) && is_array($realisasi_data[$kode_unik]))
                        ? (float)($realisasi_data[$kode_unik][$bulan] ?? 0.0)
                        : 0.0;
                    $pdf->MultiCell($col[1 + $bulan], 5, number_format($re_val), 1, 'R', 0, 0);
                }
                $pdf->Ln();
            }
        }

        // children
        if (!empty($node['children']) && is_array($node['children'])) {
            printRealizationOnlyTree($pdf, $node['children'], $level + 1, $realisasi_data);
        }
    }
}

// =========================================================================
// 4. MEMULAI PROSES CETAK
// =========================================================================
$grand_total_pagu = 0.0;
$grand_total_realisasi = array_fill(1, 12, 0.0);
foreach ($hierarki as $p_node) {
    $grand_total_pagu += (float)($p_node['total_pagu'] ?? 0.0);
    $tr = (isset($p_node['total_realisasi_bulanan']) && is_array($p_node['total_realisasi_bulanan'])) ? $p_node['total_realisasi_bulanan'] : array_fill(1,12,0.0);
    for ($m = 1; $m <= 12; $m++) {
        $grand_total_realisasi[$m] += (float)($tr[$m] ?? 0.0);
    }
}

// print grand total header row
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(211,211,211);
$col = $pdf->colWidths;
$pdf->Cell($col[0], 7, 'JUMLAH KESELURUHAN', 1, 0, 'C', 1);
$pdf->Cell($col[1], 7, number_format($grand_total_pagu), 1, 0, 'R', 1);
for ($bulan = 1; $bulan <= 12; $bulan++) {
    $pdf->Cell($col[1 + $bulan], 7, number_format($grand_total_realisasi[$bulan]), 1, 0, 'R', 1);
}
$pdf->Ln();

// print tree
if (!empty($hierarki)) {
    printRealizationOnlyTree($pdf, $hierarki, 0, $realisasi_data);
} else {
    $pdf->Cell(0, 10, 'Tidak ada data ditemukan.', 1, 1, 'C');
}

// output
$pdf->Output('laporan_realisasi_' . $tahun_filter . '.pdf', 'I');
exit;
?>
