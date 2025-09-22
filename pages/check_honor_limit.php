<?php
include '../includes/koneksi.php';

header('Content-Type: application/json');

$mitra_id = $_GET['mitra_id'] ?? null;
$bulan = $_GET['bulan'] ?? null;
$tahun = $_GET['tahun'] ?? null;
$current_honor_added = $_GET['current_honor'] ?? 0;

if (!$mitra_id || !$bulan || !$tahun) {
    echo json_encode(['is_within_limit' => true, 'total_honor' => 0]);
    exit;
}

// Ambil total honor yang sudah diterima mitra di bulan dan tahun yang sama
$sql_existing_honor = "SELECT SUM(total_honor) AS total_existing_honor FROM honor_mitra WHERE mitra_id = ? AND bulan_pembayaran = ? AND tahun_pembayaran = ?";
$stmt_existing = $koneksi->prepare($sql_existing_honor);
$stmt_existing->bind_param("sss", $mitra_id, $bulan, $tahun);
$stmt_existing->execute();
$result_existing = $stmt_existing->get_result();
$row_existing = $result_existing->fetch_assoc();
$total_existing_honor = $row_existing['total_existing_honor'] ?? 0;
$stmt_existing->close();

// Ambil batas honor (honor cap) untuk bulan dan tahun yang sama
$sql_limit = "SELECT batas_honor FROM batas_honor WHERE bulan = ? AND tahun = ?";
$stmt_limit = $koneksi->prepare($sql_limit);
$stmt_limit->bind_param("ss", $bulan, $tahun);
$stmt_limit->execute();
$result_limit = $stmt_limit->get_result();
$row_limit = $result_limit->fetch_assoc();
$honor_limit = $row_limit['batas_honor'] ?? PHP_INT_MAX; // Jika tidak ada batasan, anggap tidak terbatas
$stmt_limit->close();

$total_honor_with_new = $total_existing_honor + $current_honor_added;

$response = [
    'is_within_limit' => $total_honor_with_new <= $honor_limit,
    'total_honor' => $total_honor_with_new
];

echo json_encode($response);
$koneksi->close();
?>