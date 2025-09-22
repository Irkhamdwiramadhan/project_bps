<?php
session_start();
include '../includes/koneksi.php';

// Pastikan hanya admin yang bisa mengunduh
if (!isset($_SESSION['user_role']) || !in_array('super_admin', $_SESSION['user_role'])) {
    die("Akses Ditolak.");
}

// Sertakan autoloader Composer
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Ambil tahun dari parameter GET
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Query data
$sql = "SELECT
            mu.nama AS unit_nama,
            mp.nama AS program_nama,
            mo.nama AS output_nama,
            mk.nama AS komponen_nama,
            ma.id   AS akun_id,
            ma.nama AS akun_nama,
            mi.id   AS id_item,
            mi.nama_item AS item_nama,
            mi.satuan,
            mi.volume,
            mi.harga,
            mi.pagu,
            mi.realisasi,
            mi.sisa_anggaran
        FROM master_item mi
        LEFT JOIN master_akun ma ON mi.id_akun = ma.id
        LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
        LEFT JOIN master_output mo ON mk.id_output = mo.id
        LEFT JOIN master_program mp ON mo.id_program = mp.id
        LEFT JOIN master_unit mu ON mp.id_unit = mu.id
        WHERE mi.tahun = {$tahun_filter}
        ORDER BY mu.nama, mp.nama, mo.nama, mk.nama, ma.nama, mi.nama_item ASC";

$result = $koneksi->query($sql);
$data_master = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data_master[] = $row;
    }
} else {
    die("Tidak ada data untuk tahun yang dipilih.");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Master Data ' . $tahun_filter);

// Header Kolom
$headers = [
    'Unit', 'Program', 'Output', 'Komponen', 'Akun', 'Uraian Anggaran', 'Satuan',
    'Volume', 'Harga', 'Pagu', 'Realisasi', 'Sisa Anggaran'
];
$sheet->fromArray([$headers], NULL, 'A1');

// Gaya untuk header
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D3D3D3']]
];
$sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')->applyFromArray($headerStyle);

$rowNumber = 2; // Mulai dari baris 2 untuk data
$prev_unit = $prev_program = $prev_output = $prev_komponen = $prev_akun = null;

foreach ($data_master as $row) {
    // Tulis nilai hanya jika berbeda dari baris sebelumnya
    $unit_nama = ($row['unit_nama'] !== $prev_unit) ? $row['unit_nama'] : '';
    $program_nama = ($row['program_nama'] !== $prev_program) ? $row['program_nama'] : '';
    $output_nama = ($row['output_nama'] !== $prev_output) ? $row['output_nama'] : '';
    $komponen_nama = ($row['komponen_nama'] !== $prev_komponen) ? $row['komponen_nama'] : '';
    $akun_nama = ($row['akun_nama'] !== $prev_akun) ? $row['akun_nama'] : '';

    $rowData = [
        $unit_nama,
        $program_nama,
        $output_nama,
        $komponen_nama,
        $akun_nama,
        $row['item_nama'],
        $row['satuan'],
        (float)$row['volume'],
        (float)$row['harga'],
        (float)$row['pagu'],
        (float)$row['realisasi'],
        (float)$row['sisa_anggaran']
    ];
    $sheet->fromArray([$rowData], NULL, 'A' . $rowNumber);
    
    // Perbarui variabel "prev"
    $prev_unit = $row['unit_nama'];
    $prev_program = $row['program_nama'];
    $prev_output = $row['output_nama'];
    $prev_komponen = $row['komponen_nama'];
    $prev_akun = $row['akun_nama'];

    $rowNumber++;
}

// Atur lebar kolom otomatis
foreach (range('A', Coordinate::stringFromColumnIndex(count($headers))) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set header HTTP untuk unduhan
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Master_Data_' . $tahun_filter . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();