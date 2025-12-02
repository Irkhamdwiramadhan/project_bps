<?php
// File: pages/ajax_cek_ruangan.php
require '../includes/koneksi.php';

header('Content-Type: application/json');

// Ambil data dari JSON request
$input = json_decode(file_get_contents('php://input'), true);

$tanggal = $input['tanggal'] ?? '';
$mulai   = $input['mulai'] ?? '';
$selesai = $input['selesai'] ?? '';
$ruangan = $input['ruangan'] ?? '';

if (empty($tanggal) || empty($mulai) || empty($selesai) || empty($ruangan)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

// Query Cek Bentrok
$sql = "SELECT aktivitas, waktu_mulai, waktu_selesai, tim_kerja_id 
        FROM kegiatan_pegawai 
        WHERE tanggal = ? 
          AND tempat = ? 
          AND (
              (waktu_mulai < ? AND waktu_selesai > ?) -- Logika Overlap
          )
        LIMIT 1";

$stmt = $koneksi->prepare($sql);
// Bind: Tanggal, Tempat, Selesai_Baru, Mulai_Baru
$stmt->bind_param("ssss", $tanggal, $ruangan, $selesai, $mulai);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // JIKA KETEMU = BENTROK
    echo json_encode([
        'status' => 'bentrok',
        'data' => $row
    ]);
} else {
    // JIKA KOSONG = AMAN
    echo json_encode(['status' => 'aman']);
}

$stmt->close();
$koneksi->close();
?>