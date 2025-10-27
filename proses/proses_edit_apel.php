<?php
session_start();
include '../includes/koneksi.php';

// 1. VERIFIKASI METODE REQUEST
// ------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = "Error: Metode request tidak valid.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../admin/apel.php");
    exit;
}

// 2. AMBIL SEMUA DATA DARI FORMULIR
// ------------------------------------------------
$id_apel            = $_POST['id_apel'];
$tanggal            = $_POST['tanggal'];
$kondisi_apel       = $_POST['kondisi_apel'];
$pembina_apel       = $_POST['pembina_apel'] ?? NULL;
$komando            = $_POST['komando'] ?? NULL;
$petugas            = $_POST['petugas'] ?? NULL;
$pemimpin_doa       = $_POST['pemimpin_doa'] ?? NULL;
$keterangan         = $_POST['keterangan'] ?? NULL;
$alasan_tidak_ada   = $_POST['alasan_tidak_ada'] ?? NULL;
$kehadiran_array    = $_POST['kehadiran'] ?? [];
$foto_lama          = $_POST['foto_lama'] ?? NULL;

$upload_dir         = "../uploads/apel/";
$foto_bukti_nama    = $foto_lama; // Default: gunakan foto lama

// 3. LOGIKA PROSES UPLOAD FOTO BARU
// ------------------------------------------------
if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == UPLOAD_ERR_OK) {
    $file_tmp   = $_FILES['foto_bukti']['tmp_name'];
    $file_name  = $_FILES['foto_bukti']['name'];
    $file_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Buat nama file unik baru
    $foto_bukti_nama = "apel_" . time() . "_" . uniqid() . "." . $file_ext;
    $target_file = $upload_dir . $foto_bukti_nama;

    // Pindahkan file baru ke folder uploads
    if (move_uploaded_file($file_tmp, $target_file)) {
        // Jika upload file baru BERHASIL, hapus file lama (jika ada)
        if (!empty($foto_lama) && file_exists($upload_dir . $foto_lama)) {
            unlink($upload_dir . $foto_lama);
        }
    } else {
        // Jika upload file baru GAGAL
        $_SESSION['message'] = "Error: Gagal mengupload foto bukti baru.";
        $_SESSION['message_type'] = "danger";
        header("Location: ../admin/edit_apel.php?id=" . $id_apel);
        exit;
    }
}

// 4. LOGIKA PEMBERSIHAN DATA BERDASARKAN KONDISI APEL
// ------------------------------------------------
$kehadiran_json = '[]'; // Default

if ($kondisi_apel === 'tidak_ada') {
    // Kosongkan semua data yang tidak relevan
    $pembina_apel     = NULL;
    $komando          = NULL;
    $petugas          = NULL;
    $pemimpin_doa     = NULL;
    $keterangan       = NULL;
    $kehadiran_json   = '[]';

    // Hapus foto jika ada (baik foto lama atau yang baru diupload)
    if (!empty($foto_bukti_nama) && file_exists($upload_dir . $foto_bukti_nama)) {
        unlink($upload_dir . $foto_bukti_nama);
    }
    $foto_bukti_nama = NULL;

} elseif ($kondisi_apel === 'lupa_didokumentasikan') {
    // Kosongkan alasan
    $alasan_tidak_ada = NULL;

    // Hapus foto jika ada (karena 'lupa' berarti tidak ada foto)
    if (!empty($foto_bukti_nama) && file_exists($upload_dir . $foto_bukti_nama)) {
        unlink($upload_dir . $foto_bukti_nama);
    }
    $foto_bukti_nama = NULL;
    
    // Proses data kehadiran
    if (!empty($kehadiran_array)) {
        $kehadiran_json = json_encode(array_values($kehadiran_array));
    }

} else { // kondisi_apel === 'ada'
    // Kosongkan alasan
    $alasan_tidak_ada = NULL;

    // Proses data kehadiran
    if (!empty($kehadiran_array)) {
        $kehadiran_json = json_encode(array_values($kehadiran_array));
    }
    // $foto_bukti_nama sudah diatur di Blok 3
}


// 5. PERSIAPKAN DAN EKSEKUSI QUERY UPDATE DATABASE
// ------------------------------------------------
try {
    $sql = "UPDATE apel SET 
                tanggal = ?, 
                kondisi_apel = ?, 
                pembina_apel = ?, 
                komando = ?, 
                petugas = ?, 
                pemimpin_doa = ?, 
                keterangan = ?, 
                foto_bukti = ?, 
                kehadiran = ?, 
                alasan_tidak_ada = ? 
            WHERE id = ?";
            
    $stmt = $koneksi->prepare($sql);
    
    // Bind parameter (10 data + 1 id)
    // s = string, i = integer
    $stmt->bind_param(
        "ssssssssssi",
        $tanggal,
        $kondisi_apel,
        $pembina_apel,
        $komando,
        $petugas,
        $pemimpin_doa,
        $keterangan,
        $foto_bukti_nama,
        $kehadiran_json,
        $alasan_tidak_ada,
        $id_apel
    );

    // Eksekusi query
    if ($stmt->execute()) {
        $_SESSION['message'] = "Data apel berhasil diperbarui!";
        $_SESSION['message_type'] = "success";
    } else {
        throw new Exception("Gagal memperbarui data: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    $_SESSION['message'] = "Error: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    // Jika gagal, kembalikan ke halaman edit, bukan halaman utama
    header("Location: ../pages/edit_apel.php?id=" . $id_apel);
    exit;
}

// 6. REDIRECT KEMBALI KE HALAMAN UTAMA
// ------------------------------------------------
$koneksi->close();
header("Location: ../pages/apel.php");
exit;
?>