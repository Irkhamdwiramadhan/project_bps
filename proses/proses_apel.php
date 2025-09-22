<?php
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tanggal = $_POST['tanggal'];
    $kondisi_apel = $_POST['kondisi_apel'];
    
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

        // Blok ini dijalankan untuk 'ada' dan 'lupa_didokumentasikan'
        $kehadiran = [];
        if (isset($_POST['kehadiran']) && is_array($_POST['kehadiran'])) {
            foreach ($_POST['kehadiran'] as $id_pegawai => $data) {
                $kehadiran[] = [
                    'id_pegawai' => $id_pegawai,
                    'status' => $data['status'] ?? 'absen',
                    'catatan' => $data['catatan'] ?? ''
                ];
            }
        }
        $kehadiran_json = json_encode($kehadiran);

        // Upload foto hanya untuk kondisi 'ada'
        if ($kondisi_apel === 'ada') {
            if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = "../assets/img/bukti_apel/";
                $file_name = uniqid() . '-' . basename($_FILES['foto_bukti']['name']);
                $target_file = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $target_file)) {
                    $foto_bukti = $file_name;
                }
            }
        }
    }
    
    // Pastikan nilai yang dikirimkan terdaftar di database
    // Jika nilai dari form adalah 'lupa', ubah menjadi 'lupa_didokumentasikan'
    if ($kondisi_apel === 'lupa') {
        $kondisi_apel = 'lupa_didokumentasikan';
    }

    $sql = "INSERT INTO apel (tanggal, kondisi_apel, petugas, komando, pemimpin_doa, pembina_apel, alasan_tidak_ada, kehadiran, keterangan, foto_bukti) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("ssssssssss", $tanggal, $kondisi_apel, $petugas, $komando, $pemimpin_doa, $pembina_apel, $alasan_tidak_ada, $kehadiran_json, $keterangan, $foto_bukti);
    
    if ($stmt->execute()) {
        header("Location: ../pages/apel.php?status=success");
    } else {
        error_log("Error saat menyimpan data: " . $stmt->error);
        header("Location: ../pages/apel.php?status=error&message=" . urlencode($stmt->error));
    }

    $stmt->close();
    $koneksi->close();
}
?>