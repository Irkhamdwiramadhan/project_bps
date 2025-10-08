<?php
// proses/cetak_rpd_pdf.php (REVISI FINAL DENGAN TOTAL & HIERARKI LENGKAP)

require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. PENGAMBILAN & PEMROSESAN DATA (Logika tetap sama)
// =========================================================================
// ... (Bagian query SQL untuk mengambil $flat_data dan $rpd_data tidak berubah)
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
$stmt_hierarchy = $koneksi->prepare($sql_hierarchy);
$stmt_hierarchy->bind_param("i", $tahun_filter);
$stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result();
$flat_data = [];
while ($row = $result_hierarchy->fetch_assoc()) { $flat_data[] = $row; }
$stmt_hierarchy->close();
$rpd_data = [];
$sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ?";
$stmt_rpd = $koneksi->prepare($sql_rpd);
$stmt_rpd->bind_param("i", $tahun_filter);
$stmt_rpd->execute();
$result_rpd = $stmt_rpd->get_result();
while ($row = $result_rpd->fetch_assoc()) { $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah']; }
$stmt_rpd->close();

// ... (Logika membangun struktur pohon $hierarki tetap sama)
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

// REVISI: Fungsi rekursif untuk menghitung total pagu dan RPD dari bawah ke atas
function calculateTotals(&$node, $rpd_data) {
    $total_pagu = 0;
    $total_rpd_bulanan = array_fill(1, 12, 0);
    if (isset($node['items'])) {
        foreach ($node['items'] as $item) {
            $total_pagu += (float)$item['pagu'];
            if (isset($rpd_data[$item['kode_unik']])) {
                foreach ($rpd_data[$item['kode_unik']] as $bulan => $jumlah) {
                    $total_rpd_bulanan[$bulan] += (float)$jumlah;
                }
            }
        }
    }
    if (isset($node['children'])) {
        foreach ($node['children'] as &$child_node) {
            calculateTotals($child_node, $rpd_data);
            $total_pagu += $child_node['total_pagu'];
            foreach ($child_node['total_rpd_bulanan'] as $bulan => $jumlah) {
                $total_rpd_bulanan[$bulan] += $jumlah;
            }
        }
    }
    $node['total_pagu'] = $total_pagu;
    $node['total_rpd_bulanan'] = $total_rpd_bulanan;
}
foreach ($hierarki as &$program_node) {
    calculateTotals($program_node, $rpd_data);
}
unset($program_node);

// =========================================================================
// 2. SETUP DOKUMEN PDF (PERBAIKI KESALAHAN INI)
// =========================================================================
class MYPDF extends TCPDF {
    public $tahun_laporan;

    // PASTIKAN BARIS INI BENAR - HARUS BERUPA ARRAY DENGAN KURUNG SIKU [...]
    public $w = [120, 25, 25, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19];

    // Metode Header() akan dipanggil otomatis di setiap halaman baru
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Laporan Rencana Penarikan Dana (RPD) - Tahun ' . $this->tahun_laporan, 0, 1, 'C');
        $this->SetY($this->GetY() - 5);

        // Bagian ini sekarang akan berjalan tanpa error
        $header = ['Uraian Anggaran', 'Pagu', 'Sisa Pagu', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        $this->SetFillColor(200, 200, 200);
        $this->SetFont('helvetica', 'B', 7);
        
        $this->Ln();
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Inisialisasi PDF (Tidak ada perubahan, tapi sertakan untuk kelengkapan)
$pdf = new MYPDF('L', 'mm', 'A3', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetMargins(10, 30, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();


// =========================================================================
// [REVISI] 3. FUNGSI BARU UNTUK MENCETAK POHON SECARA REKURSIF
// =========================================================================
function printPdfTree($pdf, $nodes, $level_info, $rpd_data) {
    $w = [120, 25, 25, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19];
    $level_styles = [
        ['font' => ['style' => 'B', 'size' => 8], 'fill' => [220, 220, 220], 'indent' => 0],
        ['font' => ['style' => 'B', 'size' => 7.5], 'fill' => [225, 225, 225], 'indent' => 5],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [230, 230, 230], 'indent' => 10],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [235, 235, 235], 'indent' => 15],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [240, 240, 240], 'indent' => 25],
        ['font' => ['style' => 'B', 'size' => 7], 'fill' => [245, 245, 245], 'indent' => 25],
        ['font' => ['style' => 'BI', 'size' => 7], 'fill' => [250, 250, 250], 'indent' => 30],
    ];
    $current_style = $level_styles[$level_info['level']];

    foreach ($nodes as $kode => $node) {
        $total_rpd_node = array_sum($node['total_rpd_bulanan']);
        $sisa_pagu_node = $node['total_pagu'] - $total_rpd_node;
        
        $pdf->SetFont('helvetica', $current_style['font']['style'], $current_style['font']['size']);
        $pdf->SetFillColor($current_style['fill'][0], $current_style['fill'][1], $current_style['fill'][2]);

        $pdf->Cell($w[0], 6, str_repeat(' ', $current_style['indent']) . "{$kode} - {$node['nama']}", 1, 0, 'L', 1);
        $pdf->Cell($w[1], 6, number_format($node['total_pagu']), 1, 0, 'R', 1);
        $pdf->Cell($w[2], 6, number_format($sisa_pagu_node), 1, 0, 'R', 1);
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $pdf->Cell($w[2 + $bulan], 6, number_format($node['total_rpd_bulanan'][$bulan]), 1, 0, 'R', 1);
        }
        $pdf->Ln();

        if (isset($node['items'])) {
            $pdf->SetFont('helvetica', '', 7);
            foreach ($node['items'] as $item) {
                $item_total_rpd = isset($rpd_data[$item['kode_unik']]) ? array_sum($rpd_data[$item['kode_unik']]) : 0;
                $sisa_pagu_item = $item['pagu'] - $item_total_rpd;
                if ($sisa_pagu_item != 0) $pdf->SetFillColor(255, 243, 205); else $pdf->SetFillColor(255, 255, 255);
                
                $pdf->Cell($w[0], 5, str_repeat(' ', $current_style['indent'] + 5) . "- {$item['item_nama']}", 1, 0, 'L', 1);
                $pdf->Cell($w[1], 5, number_format($item['pagu']), 1, 0, 'R', 1);
                $pdf->Cell($w[2], 5, number_format($sisa_pagu_item), 1, 0, 'R', 1);
                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $jumlah = $rpd_data[$item['kode_unik']][$bulan] ?? 0;
                    $pdf->Cell($w[2 + $bulan], 5, number_format($jumlah), 1, 0, 'R', 1);
                }
                $pdf->Ln();
            }
        }

        if (!empty($node['children'])) {
            printPdfTree($pdf, $node['children'], ['level' => $level_info['level'] + 1], $rpd_data);
        }
    }
}

// =========================================================================
// 4. MEMULAI PROSES CETAK
// =========================================================================
$pdf->SetFont('helvetica', '', 7);
$w = [120, 25, 25, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19, 19];

// Cetak Header
$header = ['Uraian Anggaran', 'Pagu', 'Sisa Pagu', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
$pdf->SetFillColor(200, 200, 200);
$pdf->SetFont('helvetica', 'B', 7);
for($i = 0; $i < count($header); ++$i) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// [REVISI] Cetak Baris TOTAL KESELURUHAN
$grand_total_pagu = 0;
$grand_total_rpd_bulanan = array_fill(1, 12, 0);
foreach ($hierarki as $program_node) {
    $grand_total_pagu += $program_node['total_pagu'];
    foreach($program_node['total_rpd_bulanan'] as $bulan => $jumlah) {
        $grand_total_rpd_bulanan[$bulan] += $jumlah;
    }
}
$grand_total_rpd = array_sum($grand_total_rpd_bulanan);
$grand_sisa_pagu = $grand_total_pagu - $grand_total_rpd;

$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(211, 211, 211);
$pdf->Cell($w[0], 7, 'JUMLAH KESELURUHAN', 1, 0, 'C', 1);
$pdf->Cell($w[1], 7, number_format($grand_total_pagu), 1, 0, 'R', 1);
$pdf->Cell($w[2], 7, number_format($grand_sisa_pagu), 1, 0, 'R', 1);
for ($bulan = 1; $bulan <= 12; $bulan++) {
    $pdf->Cell($w[2 + $bulan], 7, number_format($grand_total_rpd_bulanan[$bulan]), 1, 0, 'R', 1);
}
$pdf->Ln();

// Panggil fungsi rekursif untuk mencetak seluruh data
if (!empty($hierarki)) {
    printPdfTree($pdf, $hierarki, ['level' => 0], $rpd_data);
} else {
    $pdf->Cell(array_sum($w), 10, 'Tidak ada data ditemukan.', 1, 1, 'C');
}

// Tutup dan output dokumen PDF
$pdf->Output('laporan_rpd_akumulasi_' . $tahun_filter . '.pdf', 'I');
?>