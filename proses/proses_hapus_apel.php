<?php
// proses/proses_hapus_apel.php
// (opsional) buat mysqli lempar exception agar mudah debug
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// include koneksi (lihat struktur foldermu)
require_once __DIR__ . '/../includes/koneksi.php';

// Normalisasi variabel koneksi ($koneksi atau $conn)
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $db = $koneksi;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    http_response_code(500);
    exit('Koneksi database tidak ditemukan. Pastikan includes/koneksi.php mendefinisikan $koneksi.');
}

// Validasi ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ../pages/apel.php?msg=invalid_id');
    exit;
}

// Ambil nama file foto (jika ada)
$foto = null;
$stmt = $db->prepare("SELECT foto_bukti FROM apel WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($foto);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    header('Location: ../pages/apel.php?msg=not_found');
    exit;
}

// Hapus file foto fisik jika ada
if (!empty($foto)) {
    $pathFoto = __DIR__ . '/../assets/img/bukti_apel/' . basename($foto);
    if (is_file($pathFoto)) {
        @unlink($pathFoto);
    }
}

// Hapus data apel
$stmt = $db->prepare("DELETE FROM apel WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Sukses
header('Location: ../pages/apel.php?msg=deleted');
exit;
