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
    header('Location: ../pages/memo_keluar_kantor.php');
    exit;
}

// 2. Ambil Data
$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tanggal     = $_POST['tanggal'] ?? '';
$keperluan   = trim($_POST['keperluan'] ?? '');
$jam_pergi   = $_POST['jam_pergi'] ?? '';
$jam_pulang  = $_POST['jam_pulang'] ?? '';
$petugas_id  = $_POST['petugas'] ?? ''; 
$pegawai_ids = $_POST['pegawai_id'] ?? []; 

// 3. Validasi Dasar
if ($id <= 0 || empty($tanggal) || empty($keperluan) || empty($jam_pergi) || empty($petugas_id) || empty($pegawai_ids)) {
    $_SESSION['error_message'] = "Semua kolom wajib diisi.";
    header("Location: ../pages/edit_memo_keluar.php?id=$id");
    exit;
}

// Gabungkan array pegawai menjadi string (1,2,3)
$pegawai_string = implode(',', $pegawai_ids);

// 4. Persiapan Query Default (Tanpa Ganti Foto)
$query_sql = "UPDATE memo_satpam SET 
              tanggal = ?, 
              keperluan = ?, 
              jam_pergi = ?, 
              jam_pulang = ?, 
              petugas = ?, 
              pegawai_id = ? 
              WHERE id = ?";

// Urutan parameter: tanggal(s), keperluan(s), jam_pergi(s), jam_pulang(s), petugas(s), pegawai(s), id(i)
$params_types = "ssssssi"; 
$params_values = [$tanggal, $keperluan, $jam_pergi, $jam_pulang, $petugas_id, $pegawai_string, $id];

// 5. Cek Apakah Ada Upload Foto Baru?
if (!empty($_FILES['foto']['name'])) {
    $upload_dir = '../uploads/memo/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_ext, $allowed_ext)) {
        if ($_FILES['foto']['error'] === 0) {
            // Ambil data lama untuk menghapus foto lama
            $q_cek = $koneksi->prepare("SELECT foto FROM memo_satpam WHERE id = ?");
            $q_cek->bind_param("i", $id);
            $q_cek->execute();
            $res_cek = $q_cek->get_result();
            $old_data = $res_cek->fetch_assoc();
            $q_cek->close();

            // Generate nama file baru
            $new_file_name = 'memo_' . time() . '_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
                // Hapus foto lama fisik jika ada
                if (!empty($old_data['foto'])) {
                    // Cek path (apakah path lengkap atau nama file saja)
                    $old_path = (strpos($old_data['foto'], 'uploads') !== false) ? '../' . $old_data['foto'] : '../uploads/memo/' . $old_data['foto'];
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }

                // --- REVISI QUERY JIKA ADA FOTO ---
                $foto_db_val = 'uploads/memo/' . $new_file_name;
                
                $query_sql = "UPDATE memo_satpam SET 
                              tanggal = ?, 
                              keperluan = ?, 
                              jam_pergi = ?, 
                              jam_pulang = ?, 
                              petugas = ?, 
                              pegawai_id = ?,
                              foto = ? 
                              WHERE id = ?";
                
                $params_types = "sssssssi"; // Tambah 1 string
                // Update values array (foto sebelum ID)
                $params_values = [$tanggal, $keperluan, $jam_pergi, $jam_pulang, $petugas_id, $pegawai_string, $foto_db_val, $id];

            } else {
                $_SESSION['error_message'] = "Gagal mengupload foto baru ke server.";
                header("Location: ../pages/edit_memo_keluar.php?id=$id");
                exit;
            }
        } else {
            $_SESSION['error_message'] = "Terjadi error saat upload foto. Kode: " . $_FILES['foto']['error'];
            header("Location: ../pages/edit_memo_keluar.php?id=$id");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Format foto tidak valid. Gunakan JPG, JPEG, PNG, atau GIF.";
        header("Location: ../pages/edit_memo_keluar.php?id=$id");
        exit;
    }
}

// 6. Eksekusi Query Final
$stmt = $koneksi->prepare($query_sql);
// Gunakan unpacking operator (...) untuk bind dynamic parameters
$stmt->bind_param($params_types, ...$params_values);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Memo berhasil diperbarui!";
    header('Location: ../pages/memo_keluar_kantor.php');
} else {
    $_SESSION['error_message'] = "Gagal update data: " . $stmt->error;
    header("Location: ../pages/edit_memo_keluar.php?id=$id");
}

$stmt->close();
$koneksi->close();
exit;
?>