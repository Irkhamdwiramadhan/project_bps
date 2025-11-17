<?php
header('Content-Type: application/json');
include '../includes/koneksi.php';

// Ambil parameter tahun (jika ada)
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';

$sql = "SELECT id, nama_lengkap, tahun FROM mitra";

// Jika tahun dipilih, filter query
if (!empty($tahun)) {
    $sql .= " WHERE tahun = ?";
}

$sql .= " ORDER BY nama_lengkap ASC";

$stmt = $koneksi->prepare($sql);

if (!empty($tahun)) {
    $stmt->bind_param("s", $tahun);
}

$stmt->execute();
$result = $stmt->get_result();

$mitra_list = [];
while ($row = $result->fetch_assoc()) {
    $mitra_list[] = $row;
}

$stmt->close();
$koneksi->close();

echo json_encode($mitra_list);
?>