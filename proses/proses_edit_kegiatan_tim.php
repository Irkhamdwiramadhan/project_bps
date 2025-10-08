<?php
// ../proses/proses_edit_kegiatan.php

session_start();
include '../includes/koneksi.php';

// Keamanan: Pastikan metode request adalah POST dan pengguna memiliki akses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Akses tidak sah.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu'];
if (count(array_intersect($allowed_roles, (array)$user_roles)) === 0) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// ===================================================================
// PENGAMBILAN & PEMBERSIHAN DATA DARI FORMULIR
// ===================================================================
$kegiatan_id = (int)$_POST['id'];
$nama_kegiatan = trim($_POST['nama_kegiatan']);
$tim_id = (int)$_POST['tim_id'];
$target = (float)$_POST['target'];
$satuan = trim($_POST['satuan']);
$batas_waktu = $_POST['batas_waktu'];
$keterangan = trim($_POST['keterangan']);

// Ambil data anggota (akan berbentuk array)
$anggota_ids = $_POST['anggota_id'] ?? [];
$target_anggotas = $_POST['target_anggota'] ?? [];


// ===================================================================
// VALIDASI DATA
// ===================================================================
if (empty($kegiatan_id) || empty($nama_kegiatan) || empty($tim_id) || empty($satuan) || empty($batas_waktu) || empty($anggota_ids)) {
    $_SESSION['error_message'] = "Semua field wajib diisi, dan minimal harus ada satu anggota.";
    header("Location: ../pages/edit_kegiatan_tim.php?id=" . $kegiatan_id);
    exit;
}

// ===================================================================
// PROSES UPDATE KE DATABASE DENGAN TRANSAKSI
// ===================================================================
// Mulai Transaksi Database
$koneksi->begin_transaction();

try {
    // LANGKAH 1: Update data utama di tabel `kegiatan`
    $stmt_kegiatan = $koneksi->prepare(
        "UPDATE kegiatan SET 
            nama_kegiatan = ?, 
            tim_id = ?, 
            target = ?, 
            satuan = ?, 
            batas_waktu = ?, 
            keterangan = ?
         WHERE id = ?"
    );
    // Tipe data: s=string, i=integer, d=double
    $stmt_kegiatan->bind_param("sidsssi", $nama_kegiatan, $tim_id, $target, $satuan, $batas_waktu, $keterangan, $kegiatan_id);
    
    if (!$stmt_kegiatan->execute()) {
        throw new Exception("Gagal mengupdate data kegiatan utama: " . $stmt_kegiatan->error);
    }
    $stmt_kegiatan->close();

    // LANGKAH 2: Hapus semua data anggota lama yang terkait dengan kegiatan ini
    $stmt_delete = $koneksi->prepare("DELETE FROM kegiatan_anggota WHERE kegiatan_id = ?");
    $stmt_delete->bind_param("i", $kegiatan_id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Gagal menghapus data anggota lama: " . $stmt_delete->error);
    }
    $stmt_delete->close();

    // LANGKAH 3: Masukkan kembali daftar anggota yang baru dari form
    $stmt_insert = $koneksi->prepare(
        "INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota) 
         VALUES (?, ?, ?)"
    );
    foreach ($anggota_ids as $key => $anggota_id) {
        $target_individu = (float)$target_anggotas[$key];
        $stmt_insert->bind_param("iid", $kegiatan_id, $anggota_id, $target_individu);
        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal menyimpan data anggota baru: " . $stmt_insert->error);
        }
    }
    $stmt_insert->close();

    // Jika semua langkah di atas berhasil, commit transaksi untuk menyimpan perubahan secara permanen
    $koneksi->commit();
    $_SESSION['success_message'] = "Data kegiatan berhasil diperbarui!";

} catch (Exception $e) {
    // Jika ada kegagalan di salah satu langkah, batalkan semua perubahan
    $koneksi->rollback();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
} finally {
    // Selalu tutup koneksi di akhir
    $koneksi->close();
}

// Alihkan kembali ke halaman utama daftar kegiatan
header('Location: ../pages/kegiatan_tim.php');
exit;
?>