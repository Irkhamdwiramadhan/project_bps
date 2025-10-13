<?php
require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$query = "SELECT ms.*, p.nama AS nama_pegawai 
          FROM memo_satpam ms
          JOIN pegawai p ON ms.pegawai_id = p.id
          ORDER BY tanggal DESC";
$result = mysqli_query($koneksi, $query);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Memo Satpam');

// Header kolom
$sheet->fromArray(['No', 'Tanggal', 'Nama Pegawai', 'Keperluan', 'Jam Pergi', 'Jam Pulang', 'Petugas'], NULL, 'A1');

// Data
$rowNum = 2;
$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $no++,
        $row['tanggal'],
        $row['nama_pegawai'],
        $row['keperluan'],
        $row['jam_pergi'],
        $row['jam_pulang'],
        $row['petugas']
    ], NULL, 'A' . $rowNum++);
}

// Styling (opsional)
$sheet->getStyle('A1:G1')->getFont()->setBold(true);
$sheet->getColumnDimension('B')->setAutoSize(true);

// Output ke browser
$filename = 'Memo_Satpam_Export_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
