<?php
// Menonaktifkan pelaporan error PHP agar tidak ada output lain selain JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set header untuk respons JSON
header('Content-Type: application/json');

// Fungsi untuk mengirim respons error JSON
function sendErrorResponse($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Sertakan file koneksi database
$koneksi_path = '../includes/koneksi.php';
if (!file_exists($koneksi_path)) {
    sendErrorResponse('Error: File koneksi.php tidak ditemukan. Pastikan path sudah benar.');
}
require_once $koneksi_path;

// Periksa apakah koneksi database berhasil
if ($koneksi->connect_error) {
    sendErrorResponse('Error Koneksi Database: ' . $koneksi->connect_error);
}

// Pastikan request menggunakan metode POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    sendErrorResponse('Metode permintaan tidak valid.');
}

try {
    // Ambil data dari POST request
    $tanggal = $_POST['tanggal'] ?? null;
    $kondisi_apel = $_POST['kondisi_apel'] ?? null;
    
    // Inisialisasi variabel dengan nilai default
    $petugas = $_POST['petugas'] ?? '';
    $komando = $_POST['komando'] ?? '';
    $pemimpin_doa = $_POST['pemimpin_doa'] ?? '';
    $pembina_apel = $_POST['pembina_apel'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $alasan_tidak_ada = $_POST['alasan_tidak_ada'] ?? '';
    $kehadiran_json = null;
    $foto_bukti = null;

    // Logika utama untuk memproses data
    if ($kondisi_apel === 'tidak_ada') {
        $alasan_tidak_ada = $_POST['alasan_tidak_ada'] ?? '';
    } else { // 'ada' atau 'lupa_didokumentasikan' atau 'lupa'
        $petugas = $_POST['petugas'] ?? '';
        $komando = $_POST['komando'] ?? '';
        $pemimpin_doa = $_POST['pemimpin_doa'] ?? '';
        $pembina_apel = $_POST['pembina_apel'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';

        // Tangani data kehadiran dari radio button
        $kehadiran = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'kehadiran_') === 0) {
                $id = str_replace('kehadiran_', '', $key);
                $kehadiran[$id] = $value;
            }
        }
        $kehadiran_json = json_encode($kehadiran);

        // Tangani unggahan foto
        if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../assets/img/bukti_apel/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = uniqid() . '-' . basename($_FILES['foto_bukti']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $target_file)) {
                $foto_bukti = $file_name;
            } else {
                sendErrorResponse('Gagal mengunggah file foto bukti.');
            }
        }
    }
    
    // Jika nilai dari form adalah 'lupa', ubah menjadi 'lupa_didokumentasikan'
    if ($kondisi_apel === 'lupa') {
        $kondisi_apel = 'lupa_didokumentasikan';
    }

    // Gunakan prepared statement untuk keamanan
    $sql = "INSERT INTO apel (tanggal, kondisi_apel, petugas, komando, pemimpin_doa, pembina_apel, alasan_tidak_ada, kehadiran, keterangan, foto_bukti) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($sql);
    if ($stmt === false) {
        sendErrorResponse('Gagal menyiapkan statement: ' . $koneksi->error);
    }
    
    $stmt->bind_param("ssssssssss", $tanggal, $kondisi_apel, $petugas, $komando, $pemimpin_doa, $pembina_apel, $alasan_tidak_ada, $kehadiran_json, $keterangan, $foto_bukti);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data apel berhasil ditambahkan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan data apel: ' . $stmt->error]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    sendErrorResponse('Terjadi kesalahan server: ' . $e->getMessage());
}

exit;
?>