<?php
// proses/cetak_realisasi1.php (Laporan Realisasi Saja)

require_once('../vendor/autoload.php');
include '../includes/koneksi.php';

// Ambil dan validasi tahun dari URL
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// =========================================================================
// 1. MENGAMBIL DATA HIERARKI & REALISASI
// =========================================================================

// Query utama untuk mengambil data master item
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
    if (!empty($row['kode_unik'])) $all_kode_uniks[] = $row['kode_unik'];
}
$stmt_hierarchy->close();

// Ambil data Realisasi saja (DATA RPD TIDAK DIPERLUKAN LAGI)
$realisasi_data = [];
if (!empty($all_kode_uniks)) {
    $placeholders = implode(',', array_fill(0, count($all_kode_uniks), '?'));
    $types = 'i' . str_repeat('s', count($all_kode_uniks));
    $params = array_merge([$tahun_filter], $all_kode_uniks);

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

// ... (Logika membangun struktur TREE dari flat_data tetap sama) ...
$tree = [];
foreach ($flat_data as $item) {
    $p_kode = $item['program_kode']; $k_kode = $item['kegiatan_kode']; $o_kode = $item['output_kode']; $so_kode = $item['sub_output_kode'];
    $kom_kode = $item['komponen_kode']; $sk_kode = $item['sub_komponen_kode']; $a_kode = $item['akun_kode'];
    if (!isset($tree[$p_kode])) { $tree[$p_kode] = ['nama' => $item['program_nama'], 'children' => []]; }
    if (!isset($tree[$p_kode]['children'][$k_kode])) { $tree[$p_kode]['children'][$k_kode] = ['nama' => $item['kegiatan_nama'], 'children' => []]; }
    // ... dst untuk semua level
    if (!isset($tree[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode])) { $tree[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode] = ['nama' => $item['akun_nama'], 'items' => []]; }
    $tree[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode]['items'][] = $item;
}

// =========================================================================
// 2. SETUP DOKUMEN PDF DENGAN TCPDF
// =========================================================================
class MYPDF_REALISASI_SIMPLE extends TCPDF {
    public $tahun_laporan;
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, 'Laporan Realisasi Anggaran (Bulanan) - Tahun ' . $this->tahun_laporan, 0, 1, 'C');
        
        // REVISI: Header tabel satu baris yang lebih sederhana
        $this->SetY($this->GetY() + 2);
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(230, 230, 230);
        $w = [80, 25, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15]; // Lebar kolom disesuaikan
        $header = ['Uraian Anggaran', 'Jumlah Pagu', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        for($i = 0; $i < count($header); ++$i) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $this->Ln();
    }
    public function Footer() { /* ... Footer tetap sama ... */ }
}

$pdf = new MYPDF_REALISASI_SIMPLE('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->tahun_laporan = $tahun_filter;
$pdf->SetMargins(10, 30, 10); // Margin atas disesuaikan untuk header baru
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// =========================================================================
// 3. FUNGSI REKURSIF UNTUK MENCETAK POHON DATA (Disederhanakan)
// =========================================================================
function cetakTreeSimple($pdf, $nodes, $level, $realisasi_data) {
    // Definisi warna, padding, dan lebar kolom
    $colors = ['#0A2E5D', '#154360', '#1F618D', '#2980B9', '#5499C7', '#7f8c8d', '#27AE60'];
    $paddings = [0, 5, 10, 15, 20, 25, 30];
    $w = [80, 25, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15];

    foreach ($nodes as $kode => $node) {
        // [FIX] Cek keberadaan key 'nama' sebelum digunakan
        $nama_node = $node['nama'] ?? 'Uraian Tidak Ditemukan';

        // Cetak baris hierarki
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColorArray(sscanf($colors[$level], "#%02x%02x%02x"));
        $pdf->SetFillColor(248, 249, 250);
        $pdf->Cell($paddings[$level], 6, '', 'L', 0, 'L', 1);
        $pdf->Cell(array_sum($w) - $paddings[$level], 6, "{$kode} - {$nama_node}", 'R', 1, 'L', 1);

        // Jika ada 'items', cetak item-item tersebut
        if (isset($node['items'])) {
            foreach ($node['items'] as $item) {
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(0,0,0);
                $padding_item = $paddings[$level] + 10;
                
                // Logika untuk menangani teks multi-baris
                $start_y = $pdf->GetY();
                $start_x = $pdf->GetX();
                // BENAR
$margins = $pdf->getMargins();
$original_margin = $margins['left'];
                
                $pdf->SetLeftMargin($original_margin + $padding_item);
                $pdf->MultiCell($w[0] - $padding_item, 0, $item['item_nama'], 1, 'L', 0, 1, '', '', true, 0, false, true, 0, 'T');
                
                $end_y = $pdf->GetY();
                $row_height = $end_y - $start_y;
                
                $pdf->SetLeftMargin($original_margin);
                $pdf->SetXY($start_x + $w[0], $start_y);
                
                // Gambar sisa kolom
                $pdf->Cell($w[1], $row_height, number_format($item['pagu'], 0, ',', '.'), 1, 0, 'R', 0);
                for ($bulan = 1; $bulan <= 12; $bulan++) {
                    $realisasi_val = $realisasi_data[$item['kode_unik']][$bulan] ?? 0;
                    $pdf->Cell($w[2 + ($bulan-1)], $row_height, number_format($realisasi_val, 0, ',', '.'), 1, 0, 'R', 0);
                }
                $pdf->Ln($row_height);
            }
        }

        // Panggil rekursif untuk anak-anaknya
        if (!empty($node['children'])) {
            cetakTreeSimple($pdf, $node['children'], $level + 1, $realisasi_data);
        }
    }
}

if (!empty($tree)) {
    cetakTreeSimple($pdf, $tree, 0, $realisasi_data);
} else { /* ... */ }

$pdf->Output('laporan_realisasi_bulanan_' . $tahun_filter . '.pdf', 'I');
?>