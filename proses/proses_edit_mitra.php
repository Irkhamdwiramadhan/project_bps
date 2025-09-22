<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['id'])) {
    header('Location: ../pages/mitra.php?status=error&message=Permintaan_tidak_valid');
    exit;
}

$id = $_POST['id'];

try {
    $id_mitra = $_POST['id_mitra'] ?? '';
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $nik = $_POST['nik'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $agama = $_POST['agama'] ?? '';
    $status_perkawinan = $_POST['status_perkawinan'] ?? '';
    $pendidikan = $_POST['pendidikan'] ?? '';
    $pekerjaan = $_POST['pekerjaan'] ?? '';
    $deskripsi_pekerjaan_lain = $_POST['deskripsi_pekerjaan_lain'] ?? '';
    $npwp = $_POST['npwp'] ?? '';
    $no_telp = $_POST['no_telp'] ?? '';
    $email = $_POST['email'] ?? '';

    $alamat_provinsi = $_POST['alamat_provinsi'] ?? '';
    $alamat_kabupaten = $_POST['alamat_kabupaten'] ?? '';
    $nama_kecamatan = $_POST['nama_kecamatan'] ?? '';
    $alamat_desa = $_POST['alamat_desa'] ?? '';
    $nama_desa = $_POST['nama_desa'] ?? '';
    $alamat_detail = $_POST['alamat_detail'] ?? '';
    $domisili_sama = isset($_POST['domisili_sama']) ? 1 : 0;
    
    $mengikuti_pendataan_bps = $_POST['mengikuti_pendataan_bps'] ?? 'Tidak';
    $posisi = $_POST['posisi'] ?? null;
    
    // Perbaikan: Gunakan ternary operator untuk mengubah nilai checkbox menjadi 1 atau 0
    $sp = isset($_POST['sp']) ? 1 : 0;
    $st = isset($_POST['st']) ? 1 : 0;
    $se = isset($_POST['se']) ? 1 : 0;
    $susenas = isset($_POST['susenas']) ? 1 : 0;
    $sakernas = isset($_POST['sakernas']) ? 1 : 0;
    $sbh = isset($_POST['sbh']) ? 1 : 0;

    $params = [];
    $types = "";

    $sql = "UPDATE mitra SET id_mitra = ?";
    $params[] = $id_mitra;
    $types .= "s";

    if ($nama_lengkap !== '') { $sql .= ", nama_lengkap = ?"; $params[] = $nama_lengkap; $types .= "s"; }
    if ($nik !== '') { $sql .= ", nik = ?"; $params[] = $nik; $types .= "s"; }
    if ($tanggal_lahir !== null) { $sql .= ", tanggal_lahir = ?"; $params[] = $tanggal_lahir; $types .= "s"; }
    if ($jenis_kelamin !== '') { $sql .= ", jenis_kelamin = ?"; $params[] = $jenis_kelamin; $types .= "s"; }
    if ($agama !== '') { $sql .= ", agama = ?"; $params[] = $agama; $types .= "s"; }
    if ($status_perkawinan !== '') { $sql .= ", status_perkawinan = ?"; $params[] = $status_perkawinan; $types .= "s"; }
    if ($pendidikan !== '') { $sql .= ", pendidikan = ?"; $params[] = $pendidikan; $types .= "s"; }
    if ($pekerjaan !== '') { $sql .= ", pekerjaan = ?"; $params[] = $pekerjaan; $types .= "s"; }
    if ($deskripsi_pekerjaan_lain !== '') { $sql .= ", deskripsi_pekerjaan_lain = ?"; $params[] = $deskripsi_pekerjaan_lain; $types .= "s"; }
    if ($npwp !== '') { $sql .= ", npwp = ?"; $params[] = $npwp; $types .= "s"; }
    if ($no_telp !== '') { $sql .= ", no_telp = ?"; $params[] = $no_telp; $types .= "s"; }
    if ($email !== '') { $sql .= ", email = ?"; $params[] = $email; $types .= "s"; }

    if ($alamat_provinsi !== '') { $sql .= ", alamat_provinsi = ?"; $params[] = $alamat_provinsi; $types .= "s"; }
    if ($alamat_kabupaten !== '') { $sql .= ", alamat_kabupaten = ?"; $params[] = $alamat_kabupaten; $types .= "s"; }
    if ($nama_kecamatan !== '') { $sql .= ", nama_kecamatan = ?"; $params[] = $nama_kecamatan; $types .= "s"; }
    if ($alamat_desa !== '') { $sql .= ", alamat_desa = ?"; $params[] = $alamat_desa; $types .= "s"; }
    if ($nama_desa !== '') { $sql .= ", nama_desa = ?"; $params[] = $nama_desa; $types .= "s"; }
    if ($alamat_detail !== '') { $sql .= ", alamat_detail = ?"; $params[] = $alamat_detail; $types .= "s"; }
    $sql .= ", domisili_sama = ?"; $params[] = $domisili_sama; $types .= "i";

    if ($mengikuti_pendataan_bps !== '') { $sql .= ", mengikuti_pendataan_bps = ?"; $params[] = $mengikuti_pendataan_bps; $types .= "s"; }
    if ($posisi !== '') { $sql .= ", posisi = ?"; $params[] = $posisi; $types .= "s"; }
    
    // Perbaikan: Tambahkan semua parameter checkbox dengan tipe integer (i)
    $sql .= ", sp = ?, st = ?, se = ?, susenas = ?, sakernas = ?, sbh = ?";
    $params[] = $sp;
    $params[] = $st;
    $params[] = $se;
    $params[] = $susenas;
    $params[] = $sakernas;
    $params[] = $sbh;
    $types .= "iiiiii";

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $stmt_old_foto = $koneksi->prepare("SELECT foto FROM mitra WHERE id = ?");
        $stmt_old_foto->bind_param("s", $id);
        $stmt_old_foto->execute();
        $result_old_foto = $stmt_old_foto->get_result();
        $row_old_foto = $result_old_foto->fetch_assoc();
        $old_foto_path = $row_old_foto['foto'];
        $stmt_old_foto->close();

        $nama_file = basename($_FILES['foto']['name']);
        $ekstensi = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        $nama_unik = uniqid('foto_') . '.' . $ekstensi;
        $target_file = $target_dir . $nama_unik;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
            $foto_path = $nama_unik;
            $sql .= ", foto = ?";
            $params[] = $foto_path;
            $types .= "s";
            
            if ($old_foto_path && file_exists($target_dir . $old_foto_path)) {
                unlink($target_dir . $old_foto_path);
            }
        } else {
            throw new Exception("Gagal mengunggah foto.");
        }
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "s";

    $stmt = $koneksi->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Gagal menyiapkan statement: ' . $koneksi->error);
    }
    
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        header('Location: ../pages/mitra.php?status=success&message=Data_mitra_berhasil_diperbarui');
    } else {
        throw new Exception('Gagal memperbarui data mitra: ' . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    $errorMessage = urlencode($e->getMessage());
    header('Location: ../pages/edit_mitra.php?status=error&message=' . $errorMessage . '&id=' . $id);
} finally {
    if (isset($koneksi)) {
        $koneksi->close();
    }
    exit;
}
?>