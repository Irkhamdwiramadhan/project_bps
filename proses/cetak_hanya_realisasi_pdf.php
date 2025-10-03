<?php
// proses/cetak_hanya_realisasi_pdf.php

require_once('../vendor/autoload.php'); // Panggil autoloader TCPDF
include '../includes/koneksi.php';

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. MENGAMBIL DATA HIERARKI DAN REALISASI (BAGIAN RPD DIHAPUS)
// =========================================================================

// Query untuk mengambil hierarki anggaran (tidak berubah)
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

// Variabel $rpd_data tidak diperlukan lagi
$realisasi_data = [];

// Hanya mengambil data realisasi
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);
    
    // Query RPD telah dihapus
    
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
// 2. SETUP DOKUMEN PDF DENGAN TCPDF (HEADER DIPERSEDERHANA)
// =========================================================================

class MYPDF_KHUSUS_REALISASI extends TCPDF {
    public $tahun_laporan;

    public function Header() {
        // Judul Laporan
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Laporan Realisasi Anggaran - Tahun ' . $this->tahun_laporan, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(15);

        // Header Tabel (disederhanakan, tanpa RPD)
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(240, 240, 240);
        
        // Lebar kolom disesuaikan untuk format A4 Landscape
        $w_uraian = 85; 
        $w_pagu = 25; 
        $w_bulan = 15; 
        $header_height = 7;

        $this->Cell($w_uraian, $header_height, 'Uraian Anggaran', 1, 0, 'C', 1);
        $this->Cell($w_pagu, $header_height, 'Jumlah Pagu', 1, 0, 'C', 1);
        for ($i = 1; $i <= 12; $i++) {
            // Menggunakan nama bulan singkat agar pas
            $nama_bulan = DateTime::createFromFormat('!m', $i)->format('M');
            $this->Cell($w_bulan, $header_height, $nama_bulan, 1, 0, 'C', 1);
        }
        $this->Ln($header_height);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    public function checkAndAddPage($h = 0) {
        $this->checkPageBreak($h);
    }
}

// Menggunakan kertas A4 Landscape karena kolom lebih sedikit
$pdf = new MYPDF_KHUSUS_REALISASI('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetMargins(10, 30, 10); 
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// =========================================================================
// 3. FUNGSI REKURSIF UNTUK MENCETAK (DISEDERHANAKAN)
// =========================================================================

function cetakHierarki($pdf, $data, $level, &$printed_headers, $realisasi_data) {
    // Definisi warna dan padding (tidak berubah)
    $w_total = 277; // Total lebar kertas A4 Landscape (297mm) dikurangi margin (10+10)
    $colors = ['program' => '#0A2E5D', 'kegiatan' => '#1F618D', 'akun' => '#27AE60'];
    $paddings = ['program' => 0, 'kegiatan' => 5, 'akun' => 10];
    
    $levels_order = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
    $current_level_name = $levels_order[$level] ?? 'item';

    $kode = $data[$current_level_name . '_kode'] ?? '';
    $nama = $data[$current_level_name . '_nama'] ?? '';

    // Cetak header hierarki (program, kegiatan, akun)
    if (in_array($current_level_name, ['program', 'kegiatan', 'akun']) && !empty($nama) && (!isset($printed_headers[$current_level_name]) || $printed_headers[$current_level_name] !== $nama)) {
        $pdf->checkAndAddPage(8);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColorArray(sscanf($colors[$current_level_name], "#%02x%02x%02x"));
        $pdf->SetFillColor(248, 249, 250);
        $pdf->Cell($paddings[$current_level_name], 6, '', 'L', 0, 'L', 1);
        $pdf->Cell($w_total - $paddings[$current_level_name], 6, "{$kode} - {$nama}", 'R', 1, 'L', 1);
        $printed_headers[$current_level_name] = $nama;
    }

    // Lanjutkan ke level berikutnya atau cetak item
    if ($level + 1 < count($levels_order)) {
        if(!empty($data[$levels_order[$level+1].'_kode'])){
            cetakHierarki($pdf, $data, $level + 1, $printed_headers, $realisasi_data);
        }
    } else { 
        // Cetak baris item
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0,0,0);
        $w_uraian = 85; $w_pagu = 25; $w_bulan = 15;
        $indent = $paddings['akun'] + 5;
        $uraianWidth = $w_uraian - $indent;
        
        $min_height = 5;
        $calculated_height = $pdf->getStringHeight($uraianWidth, $data['item_nama']);
        $actual_row_height = max($calculated_height, $min_height);
        
        $pdf->checkAndAddPage($actual_row_height);

        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        
        // Kolom Uraian
        $pdf->MultiCell($indent, $actual_row_height, '', 'LB', 'L', 0, 0, $startX, $startY, true, 0, false, true, $actual_row_height, 'M');
        $pdf->MultiCell($uraianWidth, $actual_row_height, $data['item_nama'], 'RB', 'L', 0, 0, $startX + $indent, $startY, true, 0, false, true, $actual_row_height, 'M');
        
        // Kolom Pagu
        $pdf->MultiCell($w_pagu, $actual_row_height, number_format($data['pagu'], 0, ',', '.'), 1, 'R', 0, 0, $startX + $w_uraian, $startY, true, 0, false, true, $actual_row_height, 'M');
        
        // Kolom Realisasi per Bulan
        $currentX = $startX + $w_uraian + $w_pagu;
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $realisasi_val = $realisasi_data[$data['kode_unik']][$bulan] ?? 0;
            $pdf->MultiCell($w_bulan, $actual_row_height, number_format($realisasi_val, 0, ',', '.'), 1, 'R', 0, 0, $currentX, $startY, true, 0, false, true, $actual_row_height, 'M');
            $currentX += $w_bulan;
        }
        $pdf->Ln($actual_row_height);
    }
}

// =========================================================================
// 4. PROSES DAN CETAK DATA UTAMA
// =========================================================================
if (!empty($flat_data)) {
    $printed_headers = [];
    foreach($flat_data as $row) {
        // Panggil fungsi rekursif tanpa $rpd_data
        cetakHierarki($pdf, $row, 0, $printed_headers, $realisasi_data);
    }
} else {
    $total_width = 85 + 25 + (12 * 15);
    $pdf->Cell($total_width, 10, 'Tidak ada data anggaran ditemukan untuk tahun ini.', 1, 1, 'C');
}

// Tutup dan output dokumen PDF
$pdf->Output('laporan_khusus_realisasi_' . $tahun_filter . '.pdf', 'I');
?>