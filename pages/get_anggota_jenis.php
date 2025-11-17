<?php
header('Content-Type: application/json');
include '../includes/koneksi.php'; // Sesuaikan path

$jenis_id = isset($_GET['jenis_id']) ? intval($_GET['jenis_id']) : 0;

if ($jenis_id === 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT mitra_id FROM mitra_jenis_pivot WHERE jenis_id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $jenis_id);
$stmt->execute();
$result = $stmt->get_result();

$anggota_list = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $anggota_list[] = $row;
    }
}
$stmt->close();
$koneksi->close();
echo json_encode($anggota_list);
?>