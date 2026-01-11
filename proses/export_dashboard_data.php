<?php
session_start();
require '../vendor/autoload.php';
include '../includes/koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    exit('Akses ditolak');
}

// Ambil Parameter
$type  = $_GET['type'] ?? ''; // 'volume', 'tim', atau 'individu'
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Nama File Default
$filename = "Data_Dashboard.xlsx";
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Style Header
$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']], // Biru
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];

// =======================================================================
// LOGIKA BERDASARKAN TIPE GRAFIK
// =======================================================================

switch ($type) {
    case 'volume': // GRAFIK 1: VOLUME KEGIATAN
        $filename = "Volume_Kegiatan_Tim_{$bulan}_{$tahun}.xlsx";
        $headers = ['NO', 'NAMA TIM', 'JUMLAH KEGIATAN'];
        
        $query = "SELECT t.nama_tim, COUNT(k.id) AS jumlah
                  FROM tim t
                  LEFT JOIN kegiatan k ON t.id = k.tim_id 
                  AND MONTH(k.batas_waktu) = ? AND YEAR(k.batas_waktu) = ?
                  WHERE t.is_active = 1
                  GROUP BY t.id ORDER BY t.nama_tim ASC";
        
        $stmt = $koneksi->prepare($query);
        $stmt->bind_param("ii", $bulan, $tahun);
        break;

    case 'tim': // GRAFIK 2: KINERJA TIM (TARGET VS REALISASI)
        $filename = "Kinerja_Tim_{$bulan}_{$tahun}.xlsx";
        $headers = ['NO', 'NAMA TIM', 'TOTAL TARGET', 'TOTAL REALISASI', 'CAPAIAN (%)'];
        
        $query = "SELECT t.nama_tim, 
                         COALESCE(SUM(k.target), 0) AS total_target, 
                         COALESCE(SUM(k.realisasi), 0) AS total_realisasi
                  FROM tim t
                  LEFT JOIN kegiatan k ON t.id = k.tim_id 
                  AND MONTH(k.batas_waktu) = ? AND YEAR(k.batas_waktu) = ?
                  WHERE t.is_active = 1
                  GROUP BY t.id ORDER BY t.nama_tim ASC";
        
        $stmt = $koneksi->prepare($query);
        $stmt->bind_param("ii", $bulan, $tahun);
        break;

    case 'individu': // GRAFIK 3: KINERJA INDIVIDU (REVISI UNIVERSAL)
        $filename = "Kinerja_Individu_{$bulan}_{$tahun}.xlsx";
        $headers = ['NO', 'NAMA ANGGOTA', 'TIM', 'TARGET INDIVIDU', 'REALISASI INDIVIDU', 'CAPAIAN (%)'];
        
        // QUERY UNIVERSAL (SAMA DENGAN DASHBOARD)
        // Menggunakan COALESCE untuk mengambil nama dari berbagai sumber (Anggota Tim / Pegawai Langsung / Mitra Langsung)
        $query = "SELECT 
                    member_name,
                    nama_tim,
                    COALESCE(SUM(target_individu), 0) as total_target,
                    COALESCE(SUM(realisasi_individu), 0) as total_realisasi
                  FROM (
                    SELECT 
                        -- Cek Nama: Dari Relasi Anggota Tim -> Pegawai Langsung -> Mitra Langsung
                        COALESCE(p_rel.nama, m_rel.nama_lengkap, p_direct.nama, m_direct.nama_lengkap) as member_name,
                        t.nama_tim,
                        ka.target_anggota as target_individu, 
                        ka.realisasi_anggota as realisasi_individu
                    FROM kegiatan_anggota ka
                    JOIN kegiatan k ON ka.kegiatan_id = k.id
                    JOIN tim t ON k.tim_id = t.id
                    
                    -- JALUR 1: Via Anggota Tim (Old Logic)
                    LEFT JOIN anggota_tim at ON ka.anggota_id = at.id
                    LEFT JOIN pegawai p_rel ON at.member_id = p_rel.id AND at.member_type = 'pegawai'
                    LEFT JOIN mitra m_rel ON at.member_id = m_rel.id AND at.member_type = 'mitra'
                    
                    -- JALUR 2: Via Pegawai Langsung (New Logic)
                    LEFT JOIN pegawai p_direct ON ka.anggota_id = p_direct.id
                    
                    -- JALUR 3: Via Mitra Langsung (New Logic)
                    LEFT JOIN mitra m_direct ON ka.anggota_id = m_direct.id
                    
                    WHERE t.is_active = 1 
                      AND MONTH(k.batas_waktu) = ? 
                      AND YEAR(k.batas_waktu) = ?
                      AND ka.target_anggota > 0 -- Hanya export yang punya target
                  ) as combined_data
                  WHERE member_name IS NOT NULL -- Hilangkan data hantu
                  GROUP BY member_name, nama_tim
                  ORDER BY total_target DESC";
        
        $stmt = $koneksi->prepare($query);
        // REVISI PARAMETER: Cukup 2 parameter (bulan, tahun) karena tidak pakai UNION lagi
        $stmt->bind_param("ii", $bulan, $tahun);
        break;

    default:
        exit("Tipe data tidak valid.");
}

// Eksekusi Query
$stmt->execute();
$result = $stmt->get_result();

// Tulis Header
$col = 'A';
foreach ($headers as $head) {
    $sheet->setCellValue($col . '1', $head);
    $sheet->getColumnDimension($col)->setAutoSize(true);
    $col++;
}
$sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->applyFromArray($styleHeader);

// Tulis Data
$rowNum = 2;
$no = 1;
while ($row = $result->fetch_assoc()) {
    if ($type == 'volume') {
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['nama_tim']);
        $sheet->setCellValue('C' . $rowNum, $row['jumlah']);
    } elseif ($type == 'tim') {
        $capaian = ($row['total_target'] > 0) ? round(($row['total_realisasi'] / $row['total_target']) * 100, 2) : 0;
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['nama_tim']);
        $sheet->setCellValue('C' . $rowNum, $row['total_target']);
        $sheet->setCellValue('D' . $rowNum, $row['total_realisasi']);
        $sheet->setCellValue('E' . $rowNum, $capaian . '%');
    } elseif ($type == 'individu') {
        $capaian = ($row['total_target'] > 0) ? round(($row['total_realisasi'] / $row['total_target']) * 100, 2) : 0;
        $sheet->setCellValue('A' . $rowNum, $no++);
        $sheet->setCellValue('B' . $rowNum, $row['member_name']);
        $sheet->setCellValue('C' . $rowNum, $row['nama_tim']);
        $sheet->setCellValue('D' . $rowNum, $row['total_target']);
        $sheet->setCellValue('E' . $rowNum, $row['total_realisasi']);
        $sheet->setCellValue('F' . $rowNum, $capaian . '%');
    }
    $rowNum++;
}

// Apply Style Border ke Data
$lastCol = chr(ord('A') + count($headers) - 1);
$sheet->getStyle('A2:' . $lastCol . ($rowNum - 1))->applyFromArray($styleData);

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>