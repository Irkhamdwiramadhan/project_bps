<?php
session_start();
include '../includes/koneksi.php';

// 1. Cek Login & Method
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Akses tidak sah.";
    header('Location: ../pages/tamu.php');
    exit;
}

// 2. Ambil Data dari Form
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tanggal = $_POST['tanggal'] ?? '';
$nama = trim($_POST['nama'] ?? '');
$asal = trim($_POST['asal'] ?? '');
$keperluan = trim($_POST['keperluan'] ?? '');
$jam_datang = $_POST['jam_datang'] ?? '';
$jam_pulang = $_POST['jam_pulang'] ?? '';
$petugas = trim($_POST['petugas'] ?? '');

// 3. Validasi Input Wajib
if ($id <= 0 || empty($tanggal) || empty($nama) || empty($asal) || empty($keperluan) || empty($jam_datang) || empty($petugas)) {
    $_SESSION['error_message'] = "Semua kolom wajib diisi kecuali jam pulang dan foto.";
    header("Location: ../pages/edit_tamu.php?id=$id");
    exit;
}

// 4. Logika Upload Foto (Jika Ada File Baru)
$sql_foto_fragment = ""; // Potongan query untuk foto
$params_types = "sssssssi"; // Tipe data default (tanggal, nama, asal, keperluan, jam_datang, jam_pulang, petugas, id)
$params_values = [$tanggal, $nama, $asal, $keperluan, $jam_datang, $jam_pulang, $petugas, $id];

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
    $upload_dir = '../uploads/tamu/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_info = pathinfo($_FILES['foto']['name']);
    $file_ext = strtolower($file_info['extension']);
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_ext, $allowed_ext)) {
        // Ambil nama foto lama untuk dihapus
        $q_cek = $koneksi->prepare("SELECT foto FROM tamu WHERE id = ?");
        $q_cek->bind_param("i", $id);
        $q_cek->execute();
        $res_cek = $q_cek->get_result();
        $old_data = $res_cek->fetch_assoc();
        $q_cek->close();

        // Generate nama file baru
        $new_file_name = 'tamu_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $destination = $upload_dir . $new_file_name;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
            // Hapus foto lama jika ada
            if (!empty($old_data['foto'])) {
                $path_old = '../' . $old_data['foto']; // Sesuaikan path relatif
                if (file_exists($path_old)) {
                    unlink($path_old);
                }
            }

            // Update string query dan parameter
            // Kita sisipkan foto sebelum parameter ID
            $foto_path_db = 'uploads/tamu/' . $new_file_name;
            $sql_foto_fragment = ", foto = ?";
            
            // Rebuild params: masukkan foto sebelum ID
            array_pop($params_values); // Keluarkan ID sebentar
            $params_values[] = $foto_path_db; // Masukkan foto
            $params_values[] = $id; // Masukkan ID kembali
            
            $params_types = "ssssssssi"; // Tambah 1 's' untuk foto
        } else {
            $_SESSION['error_message'] = "Gagal mengupload foto baru.";
            header("Location: ../pages/edit_tamu.php?id=$id");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Format foto tidak valid. Gunakan JPG, JPEG, PNG, atau GIF.";
        header("Location: ../pages/edit_tamu.php?id=$id");
        exit;
    }
}

// 5. Eksekusi Update Database
$sql = "UPDATE tamu SET 
        tanggal = ?, 
        nama = ?, 
        asal = ?, 
        keperluan = ?, 
        jam_datang = ?, 
        jam_pulang = ?, 
        petugas = ? 
        $sql_foto_fragment 
        WHERE id = ?";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($params_types, ...$params_values);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Data tamu berhasil diperbarui!";
    header('Location: ../pages/tamu.php');
} else {
    $_SESSION['error_message'] = "Gagal memperbarui data: " . $stmt->error;
    header("Location: ../pages/tamu.php?id=$id");
}

$stmt->close();
$koneksi->close();
exit;
?>