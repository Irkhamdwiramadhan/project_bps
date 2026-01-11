<?php
session_start();
include '../includes/koneksi.php';

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 2. Ambil ID
$id_lama = $_GET['id'] ?? null;
if (!$id_lama) {
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// 3. Mulai Transaksi (Penting agar data konsisten)
$koneksi->begin_transaction();

try {
    // ---------------------------------------------------------
    // LANGKAH A: Ambil Data Kegiatan Lama
    // ---------------------------------------------------------
    $stmt_get = $koneksi->prepare("SELECT * FROM kegiatan WHERE id = ?");
    $stmt_get->bind_param("i", $id_lama);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $data_lama = $result->fetch_assoc();
    $stmt_get->close();

    if (!$data_lama) {
        throw new Exception("Data kegiatan tidak ditemukan.");
    }

    // ---------------------------------------------------------
    // LANGKAH B: Insert Kegiatan Baru
    // ---------------------------------------------------------
    // Nama baru diberi awalan "Salinan - " agar user tahu
    $nama_baru = "Salinan - " . $data_lama['nama_kegiatan'];
    
    // Realisasi di-reset jadi 0, Target tetap sama
    $stmt_insert = $koneksi->prepare("
        INSERT INTO kegiatan (nama_kegiatan, tim_id, target, realisasi, satuan, batas_waktu, keterangan, created_at) 
        VALUES (?, ?, ?, 0, ?, ?, ?, NOW())
    ");

    $stmt_insert->bind_param(
        "sidsss", 
        $nama_baru, 
        $data_lama['tim_id'], 
        $data_lama['target'], 
        $data_lama['satuan'], 
        $data_lama['batas_waktu'], 
        $data_lama['keterangan']
    );

    if (!$stmt_insert->execute()) {
        throw new Exception("Gagal membuat salinan kegiatan.");
    }
    
    // Dapatkan ID Kegiatan Baru
    $id_baru = $koneksi->insert_id;
    $stmt_insert->close();

    // ---------------------------------------------------------
    // LANGKAH C: Salin Anggota & Target Individu
    // ---------------------------------------------------------
    // Ambil anggota dari kegiatan lama
    $sql_anggota = "SELECT anggota_id, target_anggota FROM kegiatan_anggota WHERE kegiatan_id = ?";
    $stmt_get_ang = $koneksi->prepare($sql_anggota);
    $stmt_get_ang->bind_param("i", $id_lama);
    $stmt_get_ang->execute();
    $res_ang = $stmt_get_ang->get_result();
    
    // Siapkan query insert anggota baru (Realisasi individu di-reset jadi 0)
    $stmt_ins_ang = $koneksi->prepare("
        INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota, realisasi_anggota) 
        VALUES (?, ?, ?, 0)
    ");

    while ($row = $res_ang->fetch_assoc()) {
        // Kita langsung copy ID Anggota (karena ID di database sudah benar hasil perbaikan sebelumnya)
        $stmt_ins_ang->bind_param("iid", $id_baru, $row['anggota_id'], $row['target_anggota']);
        if (!$stmt_ins_ang->execute()) {
            throw new Exception("Gagal menyalin anggota tim.");
        }
    }
    
    $stmt_get_ang->close();
    $stmt_ins_ang->close();

    // ---------------------------------------------------------
    // LANGKAH D: Commit Transaksi
    // ---------------------------------------------------------
    $koneksi->commit();
    $_SESSION['success_message'] = "Kegiatan berhasil diduplikat! Silakan edit tanggal atau rinciannya jika perlu.";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['error_message'] = "Gagal menduplikat: " . $e->getMessage();
}

// Redirect kembali
header('Location: ../pages/kegiatan_tim.php');
exit;
?>