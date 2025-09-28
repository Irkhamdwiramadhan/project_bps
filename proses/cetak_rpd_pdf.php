<?php
// proses/cetak_rpd_pdf.php

require_once('../vendor/autoload.php'); // Panggil autoloader dari Composer
include '../includes/koneksi.php';

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// Mengambil dan memproses data dengan sistem KODE_UNIK
// =========================================================================

// REVISI: Query utama sekarang mengambil kode_unik
$sql_hierarchy = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama, mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama, mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama, msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama, mi.id AS id_item, mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
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
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
}
$stmt_hierarchy->close();

// REVISI: Query RPD sekarang menggunakan kode_unik_item
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

// Membangun struktur pohon (tidak ada perubahan)
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

// =========================================================================
// MEMBUAT DOKUMEN PDF DENGAN TCPDF (Tidak ada perubahan di bagian setup)
// =========================================================================

class MYPDF extends TCPDF {
    public $tahun_laporan;
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Laporan Rencana Penarikan Dana (RPD) - Tahun ' . $this->tahun_laporan, 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistem Anggaran Anda');
$pdf->SetTitle('Laporan RPD ' . $tahun_filter);
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$pdf->SetMargins(10, 20, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 7);

$header = ['Uraian Anggaran', 'Pagu', 'Sisa Pagu', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
$w = [65, 20, 20, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14, 14];

$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('', 'B');
for($i = 0; $i < count($header); ++$i) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();
$pdf->SetFont('');

// Loop dan cetak data hierarki ke PDF
if (!empty($hierarki)) {
    foreach ($hierarki as $p_kode => $program) {
        $pdf->SetFillColor(220, 220, 220); $pdf->SetFont('', 'B', 8);
        $pdf->MultiCell(array_sum($w), 6, "{$p_kode} - {$program['nama']}", 1, 'L', 1, 1);
        foreach ($program['children'] as $k_kode => $kegiatan) {
            $pdf->SetFont('', 'B', 7); $pdf->MultiCell(array_sum($w), 5, "  {$k_kode} - {$kegiatan['nama']}", 1, 'L', 0, 1);
            // Anda bisa menambahkan loop untuk semua level jika diperlukan,
            // namun untuk keringkasan, kita langsung ke level Akun.
            foreach ($kegiatan['children'] as $o_kode => $output) {
            foreach ($output['children'] as $so_kode => $sub_output) {
            foreach ($sub_output['children'] as $kom_kode => $komponen) {
            foreach ($komponen['children'] as $sk_kode => $sub_komponen) {
            foreach ($sub_komponen['children'] as $a_kode => $akun) {
                $pdf->SetFillColor(245, 245, 245); $pdf->SetFont('', 'BI', 7);
                $pdf->MultiCell(array_sum($w), 5, "        {$a_kode} - {$akun['nama']}", 1, 'L', 1, 1);
                foreach ($akun['items'] as $item) {
                    // =========================================================================
                    // REVISI: Menggunakan kode_unik untuk mencocokkan data
                    // =========================================================================
                    $kode_unik_item = $item['kode_unik'];
                    $item_total_rpd = isset($rpd_data[$kode_unik_item]) ? array_sum($rpd_data[$kode_unik_item]) : 0;
                    $sisa_pagu = $item['pagu'] - $item_total_rpd;

                    $pdf->SetFont('', '', 7);
                    if ($sisa_pagu != 0) $pdf->SetFillColor(255, 243, 205); else $pdf->SetFillColor(255, 255, 255);

                    $pdf->Cell($w[0], 5, "          - {$item['item_nama']}", 'LR', 0, 'L', 1);
                    $pdf->Cell($w[1], 5, number_format($item['pagu']), 'LR', 0, 'R', 1);
                    $pdf->Cell($w[2], 5, number_format($sisa_pagu), 'LR', 0, 'R', 1);
                    for ($bulan = 1; $bulan <= 12; $bulan++) {
                        $jumlah = $rpd_data[$kode_unik_item][$bulan] ?? 0;
                        $pdf->Cell($w[2 + $bulan], 5, number_format($jumlah), 'LR', 0, 'R', 1);
                    }
                    $pdf->Ln();
                }
            }
            }
            }
            }
            }
        }
    }
}
$pdf->Cell(array_sum($w), 0, '', 'T'); // Garis penutup tabel

// Tutup dan output dokumen PDF
$pdf->Output('laporan_rpd_' . $tahun_filter . '.pdf', 'I');
?>