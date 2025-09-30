<?php
// proses/cetak_realisasi_pdf.php

require_once('../vendor/autoload.php'); // Panggil autoloader TCPDF
include '../includes/koneksi.php';

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. MENGAMBIL SEMUA DATA YANG DIPERLUKAN (HIERARKI, RPD, REALISASI)
// =========================================================================
// ... (Bagian ini tidak berubah, sama seperti kode Anda sebelumnya) ...
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
$all_kode_uniks = [];
while ($row = $result_hierarchy->fetch_assoc()) {
    $flat_data[] = $row;
    $all_kode_uniks[] = $row['kode_unik'];
}
$stmt_hierarchy->close();
$rpd_data = [];
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
    }
    $stmt_rpd->close();
    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) {
        $realisasi_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah_realisasi'];
    }
    $stmt_realisasi->close();
}


// =========================================================================
// 2. SETUP DOKUMEN PDF DENGAN TCPDF
// =========================================================================

class MYPDF_REALISASI extends TCPDF {
    public $tahun_laporan;

    public function Header() {
        // ... (Fungsi Header tidak berubah) ...
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Laporan Realisasi Anggaran - Tahun ' . $this->tahun_laporan, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(15);
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(240, 240, 240);
        $w_uraian = 90; $w_pagu = 25; $w_bulan = 23; $w_sub_bulan = $w_bulan / 2;
        $header_height1 = 6; $header_height2 = 5;
        $this->MultiCell($w_uraian, $header_height1 + $header_height2, 'Uraian Anggaran', 1, 'C', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $this->MultiCell($w_pagu, $header_height1 + $header_height2, 'Jumlah Pagu', 1, 'C', 1, 0, '', '', true, 0, false, true, 0, 'M');
        $x_start = $this->GetX();
        for ($i = 1; $i <= 12; $i++) {
            $nama_bulan = DateTime::createFromFormat('!m', $i)->format('F');
            $this->MultiCell($w_bulan, $header_height1, $nama_bulan, 1, 'C', 1, 0);
        }
        $this->Ln($header_height1);
        $this->SetX($x_start);
        $this->SetFont('helvetica', 'B', 7);
        for ($i = 1; $i <= 12; $i++) {
            $this->Cell($w_sub_bulan, $header_height2, 'RPD', 1, 0, 'C', 1);
            $this->Cell($w_sub_bulan, $header_height2, 'Realisasi', 1, 0, 'C', 1);
        }
        $this->Ln($header_height2);
    }

    public function Footer() {
        // ... (Fungsi Footer tidak berubah) ...
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    // REVISI: Tambahkan fungsi public ini untuk memanggil checkPageBreak secara aman
    public function checkAndAddPage($h = 0) {
        // Panggilan ini valid karena dilakukan dari dalam class turunan TCPDF
        $this->checkPageBreak($h);
    }
}

$pdf = new MYPDF_REALISASI('L', 'mm', 'A3', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetMargins(7, 35, 7); 
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// =========================================================================
// 4. FUNGSI REKURSIF UNTUK MENCETAK HIERARKI
// =========================================================================

function cetakHierarki($pdf, $data, $level, &$printed_headers, $rpd_data, $realisasi_data) {
    // ... (Bagian atas fungsi ini tidak berubah) ...
    $w_total = 410; 
    $colors = ['program' => '#0A2E5D', 'kegiatan' => '#154360', 'output' => '#1F618D', 'sub_output' => '#2980B9', 'komponen' => '#5499C7', 'sub_komponen' => '#7f8c8d', 'akun' => '#27AE60'];
    $paddings = ['program' => 0, 'kegiatan' => 5, 'output' => 10, 'sub_output' => 15, 'komponen' => 20, 'sub_komponen' => 25, 'akun' => 30];
    
    $levels_order = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
    $current_level_name = $levels_order[$level] ?? 'item';

    $kode = $data[$current_level_name . '_kode'];
    $nama = $data[$current_level_name . '_nama'];

    if (!empty($nama) && (!isset($printed_headers[$current_level_name]) || $printed_headers[$current_level_name] !== $nama)) {
        // REVISI: Panggil fungsi public yang baru kita buat
        $pdf->checkAndAddPage(12);
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColorArray(sscanf($colors[$current_level_name], "#%02x%02x%02x"));
        $pdf->SetFillColor(248, 249, 250);
        $pdf->Cell($paddings[$current_level_name], 6, '', 'L', 0, 'L', 1);
        $pdf->Cell($w_total - $paddings[$current_level_name], 6, "{$kode} - {$nama}", 'R', 1, 'L', 1);
        $printed_headers[$current_level_name] = $nama;
    }

    if ($level + 1 < count($levels_order)) {
        if(!empty($data[$levels_order[$level+1].'_kode'])){
            cetakHierarki($pdf, $data, $level + 1, $printed_headers, $rpd_data, $realisasi_data);
        }
    } else { 
        // ... (Bagian else ini tidak berubah, sudah benar dari revisi sebelumnya) ...
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0,0,0);
        $w_uraian = 90; $w_pagu = 25; $w_sub_bulan = 23/2;
        $indent = $paddings['akun'] + 10;
        $uraianWidth = $w_uraian - $indent;
        $min_height = 5;
        $calculated_height = $pdf->getStringHeight($uraianWidth, $data['item_nama']);
        $actual_row_height = max($calculated_height, $min_height);
        
        // REVISI: Panggil fungsi public yang baru kita buat
        $pdf->checkAndAddPage($actual_row_height);

        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        $pdf->MultiCell($indent, $actual_row_height, '', 'LB', 'L', 0, 0, $startX, $startY, true, 0, false, true, $actual_row_height, 'M');
        $pdf->MultiCell($uraianWidth, $actual_row_height, $data['item_nama'], 'RB', 'L', 0, 0, $startX + $indent, $startY, true, 0, false, true, $actual_row_height, 'M');
        $pdf->MultiCell($w_pagu, $actual_row_height, number_format($data['pagu'], 0, ',', '.'), 1, 'R', 0, 0, $startX + $w_uraian, $startY, true, 0, false, true, $actual_row_height, 'M');
        $currentX = $startX + $w_uraian + $w_pagu;
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $rpd_val = $rpd_data[$data['kode_unik']][$bulan] ?? 0;
            $realisasi_val = $realisasi_data[$data['kode_unik']][$bulan] ?? 0;
            $pdf->MultiCell($w_sub_bulan, $actual_row_height, number_format($rpd_val, 0, ',', '.'), 1, 'R', 0, 0, $currentX, $startY, true, 0, false, true, $actual_row_height, 'M');
            $currentX += $w_sub_bulan;
            $pdf->MultiCell($w_sub_bulan, $actual_row_height, number_format($realisasi_val, 0, ',', '.'), 1, 'R', 0, 0, $currentX, $startY, true, 0, false, true, $actual_row_height, 'M');
            $currentX += $w_sub_bulan;
        }
        $pdf->Ln($actual_row_height);
    }
}

// =========================================================================
// 5. PROSES DAN CETAK DATA UTAMA
// =========================================================================
// ... (Bagian ini tidak berubah) ...
if (!empty($flat_data)) {
    $printed_headers = [];
    foreach($flat_data as $row) {
        cetakHierarki($pdf, $row, 0, $printed_headers, $rpd_data, $realisasi_data);
    }
} else {
    $w_uraian = 90; $w_pagu = 25; $w_bulan = 23;
    $total_width = $w_uraian + $w_pagu + (12 * $w_bulan);
    $pdf->Cell($total_width, 10, 'Tidak ada data anggaran ditemukan untuk tahun ini.', 1, 1, 'C');
}

// Tutup dan output dokumen PDF
$pdf->Output('laporan_realisasi_' . $tahun_filter . '.pdf', 'I');
?>