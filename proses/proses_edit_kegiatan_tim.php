<?php
session_start();
include '../includes/koneksi.php';

// ===================================================================
// 1. CEK LOGIN & METODE REQUEST
// ===================================================================

// Pastikan user sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Pastikan data dikirim via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Akses tidak sah.");
}

// [HAK AKSES DIHAPUS] 
// Sekarang semua pegawai yang login bisa lanjut ke proses di bawah ini.

// ===================================================================
// 2. PENGAMBILAN DATA DARI FORMULIR
// ===================================================================
$kegiatan_id     = (int) $_POST['id'];
$nama_kegiatan   = trim($_POST['nama_kegiatan']);
$tim_id          = (int) $_POST['tim_id'];
$target          = (float) $_POST['target'];
$realisasi       = isset($_POST['realisasi']) ? (float) $_POST['realisasi'] : 0;
$satuan          = trim($_POST['satuan']);
$batas_waktu     = $_POST['batas_waktu'];
$keterangan      = trim($_POST['keterangan'] ?? '');

// Data Array Anggota
$anggota_ids        = $_POST['anggota_id'] ?? [];
$target_anggotas    = $_POST['target_anggota'] ?? [];
$realisasi_anggotas = $_POST['realisasi_anggota'] ?? []; 

// ===================================================================
// 3. VALIDASI INPUT
// ===================================================================
if (empty($kegiatan_id) || empty($nama_kegiatan) || empty($tim_id) || empty($satuan) || empty($batas_waktu) || empty($anggota_ids)) {
    $_SESSION['error_message'] = "Semua field wajib diisi, dan minimal harus ada satu anggota.";
    header("Location: ../pages/edit_kegiatan_tim.php?id=" . $kegiatan_id);
    exit;
}

// ===================================================================
// 4. TRANSAKSI DATABASE
// ===================================================================
$koneksi->begin_transaction();

try {
    // ---------------------------------------------------------------
    // LANGKAH A: Update data utama di tabel `kegiatan`
    // ---------------------------------------------------------------
    $stmt_kegiatan = $koneksi->prepare("
        UPDATE kegiatan SET 
            nama_kegiatan = ?, 
            tim_id = ?, 
            target = ?, 
            realisasi = ?, 
            satuan = ?, 
            batas_waktu = ?, 
            keterangan = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    // s=string, i=integer, d=double
    $stmt_kegiatan->bind_param(
        "siddsssi",
        $nama_kegiatan,
        $tim_id,
        $target,
        $realisasi,
        $satuan,
        $batas_waktu,
        $keterangan,
        $kegiatan_id
    );

    if (!$stmt_kegiatan->execute()) {
        throw new Exception("Gagal mengupdate data kegiatan utama: " . $stmt_kegiatan->error);
    }
    $stmt_kegiatan->close();

    // ---------------------------------------------------------------
    // LANGKAH B: Reset Anggota (Hapus lama, Insert baru)
    // ---------------------------------------------------------------
    
    // Hapus data anggota lama
    $stmt_delete = $koneksi->prepare("DELETE FROM kegiatan_anggota WHERE kegiatan_id = ?");
    $stmt_delete->bind_param("i", $kegiatan_id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Gagal menghapus data anggota lama: " . $stmt_delete->error);
    }
    $stmt_delete->close();

    // Insert data anggota baru (termasuk target & realisasi individu)
    $stmt_insert = $koneksi->prepare("
        INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota, realisasi_anggota) 
        VALUES (?, ?, ?, ?)
    ");

    foreach ($anggota_ids as $key => $anggota_id) {
        $target_individu    = (float) ($target_anggotas[$key] ?? 0);
        $realisasi_individu = (float) ($realisasi_anggotas[$key] ?? 0);
        
        $stmt_insert->bind_param("iidd", $kegiatan_id, $anggota_id, $target_individu, $realisasi_individu);
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal menyimpan data anggota baru: " . $stmt_insert->error);
        }
    }

    $stmt_insert->close();

    // ---------------------------------------------------------------
    // LANGKAH C: Commit (Simpan Permanen)
    // ---------------------------------------------------------------
    $koneksi->commit();
    $_SESSION['success_message'] = "Data kegiatan berhasil diperbarui!";

} catch (Exception $e) {
    // Jika error, batalkan semua perubahan
    $koneksi->rollback();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: ../pages/edit_kegiatan_tim.php?id=" . $kegiatan_id);
    exit;
} finally {
    $koneksi->close();
}

// Redirect Sukses
header('Location: ../pages/kegiatan_tim.php');
exit;
?>