<?php
// File: api/get_mitra_by_tim.php
header('Content-Type: application/json');
include '../includes/koneksi.php';

$tim_id = isset($_GET['tim_id']) ? intval($_GET['tim_id']) : 0;

if ($tim_id === 0) {
    echo json_encode([]);
    exit;
}

// REVISI: Join ke tabel 'mitra_tim' (bukan mitra_jenis_pivot)
$sql = "SELECT m.id, m.nama_lengkap 
        FROM mitra m
        JOIN mitra_tim mt ON m.id = mt.mitra_id
        WHERE mt.tim_id = ?
        ORDER BY m.nama_lengkap ASC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $tim_id);
$stmt->execute();
$result = $stmt->get_result();

$mitra_list = [];
while ($row = $result->fetch_assoc()) {
    $mitra_list[] = $row;
}

echo json_encode($mitra_list);
?>