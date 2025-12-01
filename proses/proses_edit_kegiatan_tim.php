<?php
session_start();
include '../includes/koneksi.php';

// ===================================================================
// VALIDASI AKSES DAN METODE REQUEST
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Akses tidak sah.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'ketua_tim'];
$has_access = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles)) {
        $has_access = true;
        break;
    }
}

if (!$has_access) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// ===================================================================
// PENGAMBILAN DATA DARI FORMULIR
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
$realisasi_anggotas = $_POST['realisasi_anggota'] ?? []; // <-- REVISI: Tangkap realisasi per anggota

// ===================================================================
// VALIDASI DASAR
// ===================================================================
if (empty($kegiatan_id) || empty($nama_kegiatan) || empty($tim_id) || empty($satuan) || empty($batas_waktu) || empty($anggota_ids)) {
    $_SESSION['error_message'] = "Semua field wajib diisi, dan minimal harus ada satu anggota.";
    header("Location: ../pages/edit_kegiatan_tim.php?id=" . $kegiatan_id);
    exit;
}

// ===================================================================
// TRANSAKSI DATABASE
// ===================================================================
$koneksi->begin_transaction();

try {
    // ===============================================================
    // LANGKAH 1: Update data utama di tabel `kegiatan`
    // ===============================================================
    $stmt_kegiatan = $koneksi->prepare("
        UPDATE kegiatan SET 
            nama_kegiatan = ?, 
            tim_id = ?, 
            target = ?, 
            realisasi = ?, 
            satuan = ?, 
            batas_waktu = ?, 
            keterangan = ?,
            updated_at = NOW()  -- Update waktu perubahan
        WHERE id = ?
    ");

    // Tipe data: s=string, i=integer, d=double
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

    // ===============================================================
    // LANGKAH 2: Hapus data anggota lama dan masukkan yang baru
    // ===============================================================
    $stmt_delete = $koneksi->prepare("DELETE FROM kegiatan_anggota WHERE kegiatan_id = ?");
    $stmt_delete->bind_param("i", $kegiatan_id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Gagal menghapus data anggota lama: " . $stmt_delete->error);
    }
    $stmt_delete->close();

    // REVISI: Insert data anggota dengan target DAN realisasi individu
    $stmt_insert = $koneksi->prepare("
        INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota, realisasi_anggota) 
        VALUES (?, ?, ?, ?)
    ");

    foreach ($anggota_ids as $key => $anggota_id) {
        $target_individu = (float) ($target_anggotas[$key] ?? 0);
        $realisasi_individu = (float) ($realisasi_anggotas[$key] ?? 0); // Ambil realisasi individu
        
        // Bind param: iidd (int, int, double, double)
        $stmt_insert->bind_param("iidd", $kegiatan_id, $anggota_id, $target_individu, $realisasi_individu);
        
        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal menyimpan data anggota baru: " . $stmt_insert->error);
        }
    }

    $stmt_insert->close();

    // ===============================================================
    // LANGKAH 3: Commit transaksi (simpan perubahan)
    // ===============================================================
    $koneksi->commit();
    $_SESSION['success_message'] = "Data kegiatan berhasil diperbarui!";

} catch (Exception $e) {
    // Jika salah satu langkah gagal, rollback semua perubahan
    $koneksi->rollback();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: ../pages/edit_kegiatan_tim.php?id=" . $kegiatan_id);
    exit;
} finally {
    $koneksi->close();
}

// Arahkan kembali ke halaman daftar kegiatan
header('Location: ../pages/kegiatan_tim.php');
exit;
?>