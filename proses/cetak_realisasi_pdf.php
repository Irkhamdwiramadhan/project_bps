<?php
// proses/cetak_realisasi_pdf.php (REVISI FINAL BERDASARKAN STRUKTUR RPD)

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
LEFT JOIN master_akun ma ON mi.akun_id = ma.id LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
LEFT JOIN master_output mo ON mso.output_id = mo.id LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
LEFT JOIN master_program mp ON mk.program_id = mp.id
WHERE mi.tahun = ? ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.nama_item ASC";
$stmt_hierarchy = $koneksi->prepare($sql_hierarchy); $stmt_hierarchy->bind_param("i", $tahun_filter); $stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result(); $flat_data = []; $all_kode_uniks = [];
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt_hierarchy->close();

$rpd_data = []; $realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?')); $types = 'i' . str_repeat('s', count($all_kode_uniks)); $params = array_merge([$tahun_filter], $all_kode_uniks);
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd); $stmt_rpd->bind_param($types, ...$params); $stmt_rpd->execute(); $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) { $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah']; }
    $stmt_rpd->close();
    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi); $stmt_realisasi->bind_param($types, ...$params); $stmt_realisasi->execute(); $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) { $realisasi_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah_realisasi']; }
    $stmt_realisasi->close();
}

$hierarki = [];
foreach ($flat_data as $row) {
    $p_kode = $row['program_kode']; $k_kode = $row['kegiatan_kode']; $o_kode = $row['output_kode']; $so_kode = $row['sub_output_kode'];
    $kom_kode = $row['komponen_kode']; $sk_kode = $row['sub_komponen_kode']; $a_kode = $row['akun_kode'];
    if (!$p_kode) continue;
    if (!isset($hierarki[$p_kode])) { $hierarki[$p_kode] = ['nama' => $row['program_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode])) { $hierarki[$p_kode]['children'][$k_kode] = ['nama' => $row['kegiatan_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode] = ['nama' => $row['output_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode] = ['nama' => $row['sub_output_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode] = ['nama' => $row['komponen_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode] = ['nama' => $row['sub_komponen_nama'], 'children' => []]; }
    if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode] = ['nama' => $row['akun_nama'], 'items' => []]; }
    $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode]['items'][] = $row;
}
function calculateTotals(&$node, $rpd_data, $realisasi_data) {
    // Pastikan selalu punya struktur array lengkap
    $total_pagu = 0;
    $total_rpd_bulanan = array_fill(1, 12, 0);
    $total_realisasi_bulanan = array_fill(1, 12, 0);

    // Hitung total dari item langsung
    if (isset($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)$item['pagu'];

            // Tambahkan nilai RPD
            if (!empty($rpd_data[$item['kode_unik']]) && is_array($rpd_data[$item['kode_unik']])) {
                foreach ($rpd_data[$item['kode_unik']] as $b => $j) {
                    if (isset($total_rpd_bulanan[$b])) {
                        $total_rpd_bulanan[$b] += (float)$j;
                    }
                }
            }

            // Tambahkan nilai Realisasi
            if (!empty($realisasi_data[$item['kode_unik']]) && is_array($realisasi_data[$item['kode_unik']])) {
                foreach ($realisasi_data[$item['kode_unik']] as $b => $j) {
                    if (isset($total_realisasi_bulanan[$b])) {
                        $total_realisasi_bulanan[$b] += (float)$j;
                    }
                }
            }
        }
    }

    // Rekursif untuk anak
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child_node) {
            calculateTotals($child_node, $rpd_data, $realisasi_data);

            $total_pagu += (float)($child_node['total_pagu'] ?? 0);

            if (!empty($child_node['total_rpd_bulanan']) && is_array($child_node['total_rpd_bulanan'])) {
                foreach ($child_node['total_rpd_bulanan'] as $b => $j) {
                    $total_rpd_bulanan[$b] += (float)$j;
                }
            }

            if (!empty($child_node['total_realisasi_bulanan']) && is_array($child_node['total_realisasi_bulanan'])) {
                foreach ($child_node['total_realisasi_bulanan'] as $b => $j) {
                    $total_realisasi_bulanan[$b] += (float)$j;
                }
            }
        }
    }

    // Simpan hasil akhir ke node
    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd_bulanan;
    $node['total_realisasi_bulanan'] = $total_realisasi_bulanan;
}

foreach ($hierarki as &$program_node) { calculateTotals($program_node, $rpd_data, $realisasi_data); } unset($program_node);

// =========================================================================
// 2. SETUP DOKUMEN PDF
// =========================================================================
class MYPDF_REALISASI extends TCPDF {
    public $tahun_laporan;
    public $col_widths = ['uraian' => 80, 'pagu' => 25, 'bulan' => 40];

    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Laporan RPD VS Realisasi  Anggaran - Tahun ' . $this->tahun_laporan, 0, 1, 'C');
        $this->SetY($this->GetY() + 2);
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(230, 230, 230);
        $this->SetLineStyle(['width' => 0.2, 'color' => [150, 150, 150]]);

        $w_sub_bulan = $this->col_widths['bulan'] / 2;
        $h1 = 7; $h2 = 6; $total_h = $h1 + $h2;

        $this->MultiCell($this->col_widths['uraian'], $total_h, "\nUraian Anggaran", 1, 'C', 1, 0, '', '', true, 0, false, true, $total_h, 'M');
        $this->MultiCell($this->col_widths['pagu'], $total_h, "\nJumlah Pagu", 1, 'C', 1, 0, '', '', true, 0, false, true, $total_h, 'M');

        $x_start = $this->GetX();
        for ($i = 1; $i <= 12; $i++) {
            $this->MultiCell($this->col_widths['bulan'], $h1, DateTime::createFromFormat('!m', $i)->format('F'), 1, 'C', 1, 0, '', '', true, 0, false, true, $h1, 'M');
        }
        $this->Ln($h1);
        $this->SetX($x_start);
        $this->SetFont('helvetica', 'B', 7);
        for ($i = 1; $i <= 12; $i++) {
            $this->Cell($w_sub_bulan, $h2, 'RPD', 1, 0, 'C', 1);
            $this->Cell($w_sub_bulan, $h2, 'Realisasi', 1, 0, 'C', 1);
        }
        $this->Ln($h2);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

$pdf = new MYPDF_REALISASI('L', 'mm', 'A2', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetMargins(7, 40, 7);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// =========================================================================
// 3. FUNGSI REKURSIF UNTUK MENCETAK POHON
// =========================================================================
function printRealizationTree($pdf, $nodes, $level, $rpd_data, $realisasi_data) {
    $w = $pdf->col_widths; 
    $w_sub_bulan = $w['bulan'] / 2;

    $level_styles = [
        ['font' => ['style' => 'B', 'size' => 8.5], 'fill' => [220, 220, 220], 'indent' => 0],
        ['font' => ['style' => 'B', 'size' => 8], 'fill' => [225, 225, 225], 'indent' => 5],
        ['font' => ['style' => 'B', 'size' => 7.5], 'fill' => [230, 230, 230], 'indent' => 10],
        ['font' => ['style' => 'B', 'size' => 7.5], 'fill' => [235, 235, 235], 'indent' => 15],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [240, 240, 240], 'indent' => 20],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [245, 245, 245], 'indent' => 25],
        ['font' => ['style' => 'BI', 'size' => 7], 'fill' => [250, 250, 250], 'indent' => 30],
    ];

    $current_style = $level_styles[$level] ?? end($level_styles);

    foreach ($nodes as $kode => $node) {
        $nama_node = $node['nama'] ?? 'Uraian Tidak Ditemukan';
        $rpd_bulanan = (is_array($node['total_rpd_bulanan'] ?? null)) ? $node['total_rpd_bulanan'] : array_fill(1, 12, 0);
        $realisasi_bulanan = (is_array($node['total_realisasi_bulanan'] ?? null)) ? $node['total_realisasi_bulanan'] : array_fill(1, 12, 0);

        $pdf->SetFont('helvetica', $current_style['font']['style'], $current_style['font']['size']);
        $pdf->SetFillColor(...$current_style['fill']);
        $pdf->Cell($w['uraian'], 6, str_repeat(' ', $current_style['indent']) . "{$kode} - {$nama_node}", 1, 0, 'L', 1);
        $pdf->Cell($w['pagu'], 6, number_format($node['total_pagu'] ?? 0), 1, 0, 'R', 1);

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $pdf->Cell($w_sub_bulan, 6, number_format($rpd_bulanan[$bulan]), 1, 0, 'R', 1);
            $pdf->Cell($w_sub_bulan, 6, number_format($realisasi_bulanan[$bulan]), 1, 0, 'R', 1);
        }
        $pdf->Ln();

        if (!empty($node['items'])) {
    foreach ($node['items'] as $item) {
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);

        $xStart = $pdf->GetX();
        $yStart = $pdf->GetY();

        // ====== 1. Tulis uraian item pakai MultiCell (karena bisa panjang)
        $uraian_text = str_repeat(' ', $current_style['indent'] + 5) . "- " . ($item['item_nama'] ?? '');
        $pdf->MultiCell($w['uraian'], 5, $uraian_text, 1, 'L', 0, 0);

        // Hitung tinggi baris uraian (agar bisa samakan tinggi untuk kolom lain)
        $uraian_height = $pdf->GetY() - $yStart;
        $row_height = max($uraian_height, 5); // minimal tinggi 5 mm

        // ====== 2. Geser posisi X ke kanan kolom uraian
        $pdf->SetXY($xStart + $w['uraian'], $yStart);
        $pdf->Cell($w['pagu'], $row_height, number_format($item['pagu'] ?? 0), 1, 0, 'R', 0);

        // ====== 3. Cetak kolom per bulan (RPD dan Realisasi)
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $rpd_val = $rpd_data[$item['kode_unik']][$bulan] ?? 0;
            $realisasi_val = $realisasi_data[$item['kode_unik']][$bulan] ?? 0;
            $pdf->Cell($w_sub_bulan, $row_height, number_format($rpd_val), 1, 0, 'R', 0);
            $pdf->Cell($w_sub_bulan, $row_height, number_format($realisasi_val), 1, 0, 'R', 0);
        }

        // ====== 4. Pindah ke baris berikutnya
        $pdf->Ln($row_height);
    }
}


        if (!empty($node['children'])) {
            printRealizationTree($pdf, $node['children'], $level + 1, $rpd_data, $realisasi_data);
        }
    }
}


// =========================================================================
// 4. MEMULAI PROSES CETAK
// =========================================================================
$grand_total_pagu = array_sum(array_column($hierarki, 'total_pagu'));
$grand_total_rpd = array_fill(1, 12, 0);
$grand_total_realisasi = array_fill(1, 12, 0);
foreach ($hierarki as $program_node) {
    foreach($program_node['total_rpd_bulanan'] as $bulan => $jumlah) { $grand_total_rpd[$bulan] += $jumlah; }
    foreach($program_node['total_realisasi_bulanan'] as $bulan => $jumlah) { $grand_total_realisasi[$bulan] += $jumlah; }
}

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(211, 211, 211);
$pdf->Cell($pdf->col_widths['uraian'], 7, 'JUMLAH KESELURUHAN', 1, 0, 'C', 1);
$pdf->Cell($pdf->col_widths['pagu'], 7, number_format($grand_total_pagu), 1, 0, 'R', 1);
$w_sub_bulan = $pdf->col_widths['bulan'] / 2;
for ($bulan = 1; $bulan <= 12; $bulan++) {
    $pdf->Cell($w_sub_bulan, 7, number_format($grand_total_rpd[$bulan]), 1, 0, 'R', 1);
    $pdf->Cell($w_sub_bulan, 7, number_format($grand_total_realisasi[$bulan]), 1, 0, 'R', 1);
}
$pdf->Ln();

if (!empty($hierarki)) {
    printRealizationTree($pdf, $hierarki, 0, $rpd_data, $realisasi_data);
} else {
    $pdf->Cell(0, 10, 'Tidak ada data ditemukan.', 1, 1, 'C');
}

$pdf->Output('laporan_realisasi_akumulasi_' . $tahun_filter . '.pdf', 'I');
?>