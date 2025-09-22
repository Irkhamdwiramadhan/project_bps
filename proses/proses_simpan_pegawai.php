<?php
session_start();
include '../includes/koneksi.php';

// Pastikan hanya admin yang dapat mengakses
if (!isset($_SESSION['user_role']) || !in_array('super_admin', $_SESSION['user_role'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses Ditolak.']);
    exit();
}

// Pastikan data yang diperlukan dikirim, termasuk 'tahun' yang baru
if (isset($_POST['akun_id']) && isset($_POST['role']) && isset($_POST['pegawai_id']) && isset($_POST['tahun'])) {
    $akun_id = $_POST['akun_id'];
    $role = $_POST['role'];
    $pegawai_id = $_POST['pegawai_id'];
    $tahun = $_POST['tahun'];

    // Menentukan kolom yang akan diupdate
    $kolom_update = '';
    if ($role === 'ketua') {
        $kolom_update = 'id_ketua';
    } elseif ($role === 'pengelola') {
        $kolom_update = 'id_pengelola';
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Peran tidak valid.']);
        exit();
    }
    
    // Menggunakan INSERT ON DUPLICATE KEY UPDATE ke tabel baru 'akun_pengelola_tahun'
    $sql = "INSERT INTO akun_pengelola_tahun (akun_id, tahun, {$kolom_update}) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE {$kolom_update} = ?";
    $stmt = $koneksi->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("iiii", $akun_id, $tahun, $pegawai_id, $pegawai_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan.']);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan statement: ' . $koneksi->error]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
}
exit();