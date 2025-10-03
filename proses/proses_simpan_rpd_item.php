<?php
session_start();
include '../includes/koneksi.php';

// =========================================================================
// KEAMANAN: Validasi Awal
// =========================================================================

// 1. Pastikan skrip diakses melalui metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Akses ditolak.");
}

// 2. Cek hak akses pengguna
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melakukan tindakan ini.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../rpd.php");
    exit();
}

// 3. Validasi semua input yang diterima dari form
$kode_unik_item = filter_input(INPUT_POST, 'kode_unik_item', FILTER_SANITIZE_STRING);
$tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
$bulan_rpd_raw = $_POST['bulan_rpd'] ?? [];

if (empty($kode_unik_item) || empty($tahun) || !is_array($bulan_rpd_raw)) {
    $_SESSION['flash_message'] = "Data yang dikirim tidak lengkap atau tidak valid.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../rpd.php?tahun=" . $tahun);
    exit();
}

// =========================================================================
// LOGIKA INTI: Validasi dan Proses Simpan
// =========================================================================

try {
    // 1. Ambil Pagu asli dari database untuk validasi
    $stmt_pagu = $koneksi->prepare("SELECT pagu FROM master_item WHERE kode_unik = ?");
    $stmt_pagu->bind_param("s", $kode_unik_item);
    $stmt_pagu->execute();
    $result_pagu = $stmt_pagu->get_result();
    if ($result_pagu->num_rows === 0) {
        throw new Exception("Item anggaran dengan kode unik tersebut tidak ditemukan.");
    }
    $pagu_asli = (float) $result_pagu->fetch_assoc()['pagu'];
    $stmt_pagu->close();

    // 2. Bersihkan dan hitung total RPD yang di-submit
    $total_rpd_submitted = 0;
    $bulan_rpd_clean = [];
    foreach ($bulan_rpd_raw as $bulan => $jumlah_raw) {
        $jumlah = (float) preg_replace('/[^0-9]/', '', $jumlah_raw);
        $total_rpd_submitted += $jumlah;
        $bulan_rpd_clean[$bulan] = $jumlah;
    }

    // 3. Validasi Server-Side: Total RPD harus sama dengan Pagu
    if (abs($total_rpd_submitted - $pagu_asli) > 0.01) { // Toleransi untuk perbandingan float
        throw new Exception("Total RPD (Rp " . number_format($total_rpd_submitted) . ") tidak sama dengan Total Pagu (Rp " . number_format($pagu_asli) . "). Data tidak disimpan.");
    }

    // 4. Simpan ke database menggunakan transaksi
    $koneksi->begin_transaction();
    
    // Langkah A: Hapus semua data RPD lama
    $stmt_delete = $koneksi->prepare("DELETE FROM rpd WHERE kode_unik_item = ? AND tahun = ?");
    $stmt_delete->bind_param("si", $kode_unik_item, $tahun);
    $stmt_delete->execute();
    $stmt_delete->close();
    
    // Langkah B: Masukkan data RPD yang baru
    $sql_insert = "INSERT INTO rpd (kode_unik_item, tahun, bulan, jumlah) VALUES (?, ?, ?, ?)";
    $stmt_insert = $koneksi->prepare($sql_insert);
    
    foreach ($bulan_rpd_clean as $bulan => $jumlah) {
        if ($jumlah > 0) {
            $stmt_insert->bind_param("siid", $kode_unik_item, $tahun, $bulan, $jumlah);
            $stmt_insert->execute();
        }
    }
    $stmt_insert->close();
    
    $koneksi->commit();
    
    $_SESSION['flash_message'] = "Rencana Penarikan Dana berhasil disimpan.";
    $_SESSION['flash_message_type'] = "success";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['flash_message'] = "Terjadi kesalahan. Pesan: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
}

// =========================================================================
// REVISI: ARAHKAN PENGGUNA KEMBALI KE LANGKAH 2
// =========================================================================
// Daripada ke halaman laporan, kita kembali ke halaman tambah_rpd.
// Halaman tambah_rpd akan otomatis menampilkan Langkah 2 karena konteksnya tersimpan di session.
header("Location: ../pages/tambah_rpd.php?tahun=" . $tahun);
exit();
?>