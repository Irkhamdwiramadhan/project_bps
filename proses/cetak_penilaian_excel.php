<?php
session_start();

// 1. Load Library & Koneksi
require '../vendor/autoload.php'; 
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// 2. Ambil Data Filter
$tahun = $_POST['tahun'] ?? '';
$tim_ids = $_POST['tim_id'] ?? [];

if (empty($tahun) || empty($tim_ids)) {
    die("Harap lengkapi filter (Tahun dan Tim).");
}

$tim_ids_clean = array_map('intval', $tim_ids);
$tim_clause = implode(',', $tim_ids_clean);

// ==========================================================================
// LANGKAH 3: QUERY DATA (REKAPITULASI PER MITRA)
// ==========================================================================

// LOGIKA SQL:
// 1. Mengambil data diri mitra (Nama, Alamat, Telp).
// 2. Menghitung jumlah penilaian (COUNT) berapa kali dia dinilai di tim & tahun tsb.
// 3. Menghitung Rata-rata Final (AVG) dari nilai ((kualitas+volume+perilaku)/3).
// 4. GROUP BY m.id agar 1 mitra hanya muncul 1 baris.

$sql = "
    SELECT 
        m.id,
        m.nama_lengkap,
        m.alamat_detail,
        m.no_telp,
        
        -- Menghitung berapa kali mitra dinilai
        COUNT(mpk.id) as jumlah_penilaian,
        
        -- Menghitung rata-rata dari (Rata-rata 3 komponen)
        AVG( (mpk.kualitas + mpk.volume_pemasukan + mpk.perilaku) / 3 ) as rata_rata_final
        
    FROM mitra_penilaian_kinerja mpk
    JOIN mitra_surveys ms ON mpk.mitra_survey_id = ms.id
    JOIN mitra m ON ms.mitra_id = m.id
    JOIN tim t ON ms.tim_id = t.id
    JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode 
    
    WHERE mk.tahun = '$tahun'
      AND ms.tim_id IN ($tim_clause)
      
    GROUP BY m.id
    ORDER BY m.nama_lengkap ASC
";

$result = $koneksi->query($sql);

if (!$result) {
    die("Error Database: " . $koneksi->error);
}

// ==========================================================================
// LANGKAH 4: GENERATE EXCEL
// ==========================================================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Nilai Mitra');

// --- Styles ---
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0A2E5D']], 
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$styleBorder = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];
$styleCenter = [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];

// --- Judul Laporan ---
$sheet->setCellValue('A1', 'REKAPITULASI PENILAIAN MITRA BPS');
$sheet->setCellValue('A2', "Tahun: $tahun");
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

// --- Header Kolom (Sesuai Request Baru) ---
$headers = [
    'A' => 'NO',
    'B' => 'NAMA MITRA',
    'C' => 'ALAMAT DETAIL',
    'D' => 'NO TELP',
    'E' => 'JUMLAH PENILAIAN', // Berapa kali dinilai
    'F' => 'RATA-RATA NILAI'   // Rata-rata akumulasi
];

$headerRow = 4;
foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . $headerRow, $text);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
// Memperlebar kolom Alamat agar lebih lega
$sheet->getColumnDimension('C')->setAutoSize(false);
$sheet->getColumnDimension('C')->setWidth(40);

$sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray($styleHeader);

// --- Isi Data ---
$rowNum = 5;
$no = 1;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['nama_lengkap']);
        $sheet->setCellValue('C' . $rowNum, $row['alamat_detail']);
        
        // Format No Telp sebagai Text agar 0 di depan tidak hilang
        $sheet->setCellValueExplicit('D' . $rowNum, $row['no_telp'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        
        // Jumlah Penilaian (Center)
        $sheet->setCellValue('E' . $rowNum, $row['jumlah_penilaian']);
        
        // Rata-rata Final
        $rata_rata = (float)$row['rata_rata_final'];
        $sheet->setCellValue('F' . $rowNum, $rata_rata);
        
        // Styling Angka
        $sheet->getStyle('E' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $rowNum)->getNumberFormat()->setFormatCode('0.00'); // 2 Desimal
        $sheet->getStyle('F' . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle('F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Pewarnaan jika nilai rendah (Opsional Visual)
        if ($rata_rata < 60) {
            $sheet->getStyle('F' . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000'));
        }
        
        $rowNum++;
    }
} else {
    $sheet->setCellValue('A' . $rowNum, 'Belum ada data penilaian untuk filter ini.');
    $sheet->mergeCells("A{$rowNum}:F{$rowNum}");
}

// Border
$lastDataRow = $rowNum - 1;
if ($lastDataRow >= 5) {
    $sheet->getStyle("A5:F{$lastDataRow}")->applyFromArray($styleBorder);
    // Wrap text untuk alamat
    $sheet->getStyle("C5:C{$lastDataRow}")->getAlignment()->setWrapText(true);
}

// Output
$filename = "Rekap_Nilai_Mitra_{$tahun}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>