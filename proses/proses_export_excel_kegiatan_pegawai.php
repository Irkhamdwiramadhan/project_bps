<?php
session_start();
require '../vendor/autoload.php'; 
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Akses Ditolak");
}

// ============================================================
// 1. QUERY DATA (REVISI: SEMUA DATA)
// ============================================================
// Kita menghapus klausa WHERE ... BETWEEN ... agar semua data terambil.
// Query tetap menggunakan LEFT JOIN untuk menangani nama tim.

$query = "
    SELECT 
        kp.*,
        t.nama_tim as nama_tim_db
    FROM kegiatan_pegawai kp
    LEFT JOIN tim t ON kp.tim_kerja_id = t.id 
    ORDER BY kp.tanggal DESC, kp.waktu_mulai ASC
"; 
// Note: Saya ubah ORDER BY tanggal menjadi DESC (Terbaru di atas) agar lebih rapi, 
// atau kembalikan ke ASC jika ingin dari yang terlama.

$result = mysqli_query($koneksi, $query);

// ============================================================
// 2. SETUP EXCEL
// ============================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Kegiatan Full');

// --- HEADER JUDUL ---
$sheet->setCellValue('A1', 'Laporan Seluruh Kegiatan Tim Kerja dan Penggunaan Ruang Rapat');
// Ubah baris kedua menjadi Tanggal Cetak karena tidak ada filter periode
$sheet->setCellValue('A2', 'Data per Tanggal: ' . date('d M Y')); 

$sheet->mergeCells('A1:I1');
$sheet->mergeCells('A2:I2');
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --- HEADER TABEL ---
$headers = [
    'A' => 'NO',
    'B' => 'TANGGAL',
    'C' => 'JAM',
    'D' => 'JENIS AKTIVITAS',
    'E' => 'URAIAN KEGIATAN',
    'F' => 'TEMPAT',
    'G' => 'TIM KERJA',
    'H' => 'JML',
    'I' => 'PESERTA'
];

$rowNum = 4;
foreach ($headers as $col => $val) {
    $sheet->setCellValue($col . $rowNum, $val);
}

// Style Header (Hijau)
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']], 
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle("A4:I4")->applyFromArray($headerStyle);

// ============================================================
// 3. ISI DATA
// ============================================================
$rowNum = 5;
$no = 1;

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        // Format Jam
        $jam = date('H:i', strtotime($row['waktu_mulai'])) . ' - ' . $row['waktu_selesai'];
        
        // Logika Tim Kerja (Hybrid: ID vs Manual)
        $tim_kerja_final = !empty($row['nama_tim_db']) ? $row['nama_tim_db'] : $row['tim_kerja_id'];

        // Tulis ke Excel
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, date('d/m/Y', strtotime($row['tanggal'])));
        $sheet->setCellValue('C' . $rowNum, $jam);
        $sheet->setCellValue('D' . $rowNum, $row['jenis_aktivitas']);
        $sheet->setCellValue('E' . $rowNum, $row['aktivitas']);
        $sheet->setCellValue('F' . $rowNum, $row['tempat']);
        $sheet->setCellValue('G' . $rowNum, $tim_kerja_final); // Pakai hasil logika di atas
        $sheet->setCellValue('H' . $rowNum, $row['jumlah_peserta']);
        $sheet->setCellValue('I' . $rowNum, $row['peserta_ids']); 
        
        $rowNum++;
    }
} else {
    $sheet->setCellValue('A5', 'Belum ada data sama sekali.');
    $sheet->mergeCells('A5:I5');
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum++; // Increment agar border tetap tercetak di baris kosong
}

// ============================================================
// 4. FINAL STYLING
// ============================================================
$lastRow = $rowNum - 1;
$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP] // Rata atas agar rapi
];

// Terapkan border ke seluruh data (jika ada data)
if ($lastRow >= 5) {
    $sheet->getStyle("A5:I{$lastRow}")->applyFromArray($styleData);
    
    // Center alignment khusus untuk kolom-kolom pendek
    $sheet->getStyle("A5:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("H5:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto width
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Fix width & Wrap Text untuk kolom Uraian (E) dan Peserta (I) agar tidak terlalu lebar
$sheet->getColumnDimension('E')->setAutoSize(false);
$sheet->getColumnDimension('E')->setWidth(40);
$sheet->getStyle("E5:E{$lastRow}")->getAlignment()->setWrapText(true);

$sheet->getColumnDimension('I')->setAutoSize(false);
$sheet->getColumnDimension('I')->setWidth(50);
$sheet->getStyle("I5:I{$lastRow}")->getAlignment()->setWrapText(true);

// Output File
$filename = 'Laporan_Semua_Kegiatan_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>