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

// Ambil Filter
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// QUERY REVISI (HYBRID DATA)
// Kita gunakan LEFT JOIN ke tabel tim.
// Jika tim_kerja_id berisi angka (ID), maka t.nama_tim akan ada isinya.
// Jika tim_kerja_id berisi teks (Manual), maka t.nama_tim akan NULL (karena tidak cocok dengan ID).
$query = "
    SELECT 
        kp.*,
        t.nama_tim as nama_tim_db
    FROM kegiatan_pegawai kp
    LEFT JOIN tim t ON kp.tim_kerja_id = t.id 
    WHERE kp.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'
    ORDER BY kp.tanggal ASC, kp.waktu_mulai ASC
";
$result = mysqli_query($koneksi, $query);

// Setup Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Kegiatan');

// Header Judul
$sheet->setCellValue('A1', 'LAPORAN KEGIATAN PEGAWAI');
$sheet->setCellValue('A2', 'Periode: ' . date('d M Y', strtotime($tgl_mulai)) . ' s/d ' . date('d M Y', strtotime($tgl_selesai)));
$sheet->mergeCells('A1:I1');
$sheet->mergeCells('A2:I2');
$sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header Tabel
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

// Style Header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']], // Hijau
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle("A4:I4")->applyFromArray($headerStyle);

// Isi Data
$rowNum = 5;
$no = 1;
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        
        // 1. Format Jam
        $jam = date('H:i', strtotime($row['waktu_mulai'])) . ' - ' . $row['waktu_selesai'];
        
        // 2. Logika Cerdas untuk Tim Kerja
        // Jika hasil join (nama_tim_db) ada isinya, pakai itu (berarti data lama pakai ID).
        // Jika tidak, pakai tim_kerja_id langsung (berarti data baru pakai input manual).
        $tim_kerja_final = !empty($row['nama_tim_db']) ? $row['nama_tim_db'] : $row['tim_kerja_id'];

        // Tulis ke Excel
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, date('d/m/Y', strtotime($row['tanggal'])));
        $sheet->setCellValue('C' . $rowNum, $jam);
        $sheet->setCellValue('D' . $rowNum, $row['jenis_aktivitas']);
        $sheet->setCellValue('E' . $rowNum, $row['aktivitas']);
        $sheet->setCellValue('F' . $rowNum, $row['tempat']);
        
        // Kolom G (Tim Kerja)
        $sheet->setCellValue('G' . $rowNum, $tim_kerja_final);
        
        $sheet->setCellValue('H' . $rowNum, $row['jumlah_peserta']);
        $sheet->setCellValue('I' . $rowNum, $row['peserta_ids']); 
        
        $rowNum++;
    }
} else {
    $sheet->setCellValue('A5', 'Tidak ada data pada periode ini.');
    $sheet->mergeCells('A5:I5');
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Style Border & Auto Width
$lastRow = $rowNum - 1;
$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP]
];
if ($lastRow >= 5) {
    $sheet->getStyle("A5:I{$lastRow}")->applyFromArray($styleData);
}

// Auto width sederhana
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
// Fix width untuk kolom panjang
$sheet->getColumnDimension('E')->setAutoSize(false);
$sheet->getColumnDimension('E')->setWidth(40);
$sheet->getStyle("E5:E{$lastRow}")->getAlignment()->setWrapText(true);

$sheet->getColumnDimension('I')->setAutoSize(false);
$sheet->getColumnDimension('I')->setWidth(50);
$sheet->getStyle("I5:I{$lastRow}")->getAlignment()->setWrapText(true);

// Download
$filename = 'Laporan_Kegiatan_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>