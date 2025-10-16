<?php
// Autoload dan koneksi
require_once '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Ambil filter dari GET (dikirim dari form_cetak_rekap.php)
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_tim = $_GET['tim_id'] ?? '';

// Nama bulan (untuk tampilan di Excel)
$nama_bulan = $filter_bulan ? date('F', mktime(0, 0, 0, $filter_bulan, 1)) : 'Semua Bulan';

// Query utama
$sql = "
SELECT
    t.nama_tim,
    p.nama AS ketua_tim,
    mk.nama AS nama_kegiatan,
    (
        SELECT mi.nama_item
        FROM master_item mi
        WHERE mi.kode_unik LIKE CONCAT(hm.item_kode_unik, '%')
        LIMIT 1
    ) AS nama_item,
    SUM(hm.total_honor) AS total_honor
FROM honor_mitra hm
LEFT JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
LEFT JOIN tim t ON ms.tim_id = t.id
LEFT JOIN pegawai p ON t.ketua_tim_id = p.id
LEFT JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode
WHERE 1=1
";

$params = [];
$types = '';

// Filter bulan
if (!empty($filter_bulan)) {
    $sql .= " AND hm.bulan_pembayaran = ?";
    $params[] = $filter_bulan;
    $types .= 'i';
}

// Filter tahun
if (!empty($filter_tahun)) {
    $sql .= " AND hm.tahun_pembayaran = ?";
    $params[] = $filter_tahun;
    $types .= 'i';
}

// Filter tim
if (!empty($filter_tim)) {
    $sql .= " AND t.id = ?";
    $params[] = $filter_tim;
    $types .= 'i';
}

// GROUP BY agar total honor per kombinasi unik
$sql .= "
GROUP BY t.nama_tim, p.nama, mk.nama, hm.item_kode_unik
ORDER BY t.nama_tim, mk.nama, nama_item
";

// Eksekusi query
$stmt = $koneksi->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ================== Generate Excel ==================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul laporan
$sheet->setCellValue('A1', 'REKAP KEGIATAN TIM');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Subjudul filter
$filter_text = "Periode: " . $nama_bulan . " " . $filter_tahun;
if ($filter_tim) {
    // Ambil nama tim dari database
    $tim_query = $koneksi->prepare("SELECT nama_tim FROM tim WHERE id = ?");
    $tim_query->bind_param("i", $filter_tim);
    $tim_query->execute();
    $tim_result = $tim_query->get_result()->fetch_assoc();
    $filter_text .= " | Tim: " . ($tim_result['nama_tim'] ?? 'Tidak Diketahui');
    $tim_query->close();
}
$sheet->setCellValue('A2', $filter_text);
$sheet->mergeCells('A2:E2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFont()->setItalic(true);

// Header tabel
$headers = ['Nama Tim', 'Ketua Tim', 'Nama Kegiatan', 'Nama Item', 'Jumlah Honor (Rp)'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '4', $header);
    $sheet->getStyle($col . '4')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'color' => ['rgb' => 'DCE6F1']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    $col++;
}

// Data
$rowNum = 5;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $rowNum, $row['nama_tim']);
        $sheet->setCellValue('B' . $rowNum, $row['ketua_tim']);
        $sheet->setCellValue('C' . $rowNum, $row['nama_kegiatan']);
        $sheet->setCellValue('D' . $rowNum, $row['nama_item']);
        $sheet->setCellValue('E' . $rowNum, $row['total_honor']);

        // Format angka rupiah
        $sheet->getStyle('E' . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
        $rowNum++;
    }
} else {
    $sheet->setCellValue('A5', 'Tidak ada data untuk filter yang dipilih');
    $sheet->mergeCells('A5:E5');
    $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Border tabel
$lastRow = $rowNum - 1;
$sheet->getStyle("A4:E{$lastRow}")->applyFromArray([
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
]);

// Auto width kolom
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Nama file hasil download
$filename = 'Rekap_Kegiatan_Tim_' . date('Ymd_His') . '.xlsx';

// Output file ke browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
