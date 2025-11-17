<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['id'])) {
    header('Location: ../pages/mitra.php?status=error&message=Permintaan_tidak_valid');
    exit;
}

$id = $_POST['id'];

try {
    // === Ambil semua data dari form ===
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
    $norek = $_POST['norek'] ?? ''; // ✅ kolom baru
    $bank = $_POST['bank'] ?? '';   // ✅ kolom baru
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

    // Checkbox pengalaman survei
    $sp = isset($_POST['sp']) ? 1 : 0;
    $st = isset($_POST['st']) ? 1 : 0;
    $se = isset($_POST['se']) ? 1 : 0;
    $susenas = isset($_POST['susenas']) ? 1 : 0;
    $sakernas = isset($_POST['sakernas']) ? 1 : 0;
    $sbh = isset($_POST['sbh']) ? 1 : 0;

    // === Susun query dinamis ===
    $params = [];
    $types = "";
    $sql = "UPDATE mitra SET id_mitra = ?";
    $params[] = $id_mitra;
    $types .= "s";

    // Tambahkan kolom jika ada isinya
    $fields = [
        'nama_lengkap' => $nama_lengkap,
        'nik' => $nik,
        'tanggal_lahir' => $tanggal_lahir,
        'jenis_kelamin' => $jenis_kelamin,
        'agama' => $agama,
        'status_perkawinan' => $status_perkawinan,
        'pendidikan' => $pendidikan,
        'pekerjaan' => $pekerjaan,
        'deskripsi_pekerjaan_lain' => $deskripsi_pekerjaan_lain,
        'npwp' => $npwp,
        'norek' => $norek, // ✅ ditambahkan
        'bank' => $bank,   // ✅ ditambahkan
        'no_telp' => $no_telp,
        'email' => $email,
        'alamat_provinsi' => $alamat_provinsi,
        'alamat_kabupaten' => $alamat_kabupaten,
        'nama_kecamatan' => $nama_kecamatan,
        'alamat_desa' => $alamat_desa,
        'nama_desa' => $nama_desa,
        'alamat_detail' => $alamat_detail,
        'mengikuti_pendataan_bps' => $mengikuti_pendataan_bps,
        'posisi' => $posisi
    ];

    foreach ($fields as $key => $value) {
        if ($value !== '' && $value !== null) {
            $sql .= ", $key = ?";
            $params[] = $value;
            $types .= "s";
        }
    }

    // Kolom boolean/checkbox
    $sql .= ", domisili_sama = ?, sp = ?, st = ?, se = ?, susenas = ?, sakernas = ?, sbh = ?";
    array_push($params, $domisili_sama, $sp, $st, $se, $susenas, $sakernas, $sbh);
    $types .= "iiiiiii";

    // === Upload foto baru (jika ada) ===
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

    // WHERE clause
    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "s";

    // === Eksekusi ===
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
