<?php
require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Filter tanggal (jika ada)
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$where_clause = $filter_tanggal ? "WHERE tanggal = '$filter_tanggal'" : "";

$query = "SELECT * FROM tamu $where_clause ORDER BY tanggal DESC";
$result = mysqli_query($koneksi, $query);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Tamu');

// Header kolom
$headers = ['No', 'Tanggal', 'Nama', 'Asal', 'Keperluan', 'Jam Datang', 'Jam Pulang', 'Petugas'];
$sheet->fromArray($headers, NULL, 'A1');

// Data
$rowIndex = 2;
$no = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $sheet->fromArray([
        $no++,
        $row['tanggal'],
        $row['nama'],
        $row['asal'],
        $row['keperluan'],
        $row['jam_datang'],
        $row['jam_pulang'],
        $row['petugas']
    ], NULL, 'A' . $rowIndex++);
}

// Styling Header
$headerStyle = $sheet->getStyle('A1:H1');
$headerStyle->getFont()->setBold(true)->setSize(11);
$headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFDDEBF7');
$sheet->getRowDimension(1)->setRowHeight(22);

// Auto width kolom
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output ke browser
$filename = 'Data_Tamu_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
