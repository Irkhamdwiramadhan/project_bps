<?php
session_start();
include '../includes/koneksi.php';

// Validasi Login & Method
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { header('Location: ../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Akses ditolak."); }

// Ambil Data Utama
$tim_id = $_POST['tim_id'];
$kegiatan_list = $_POST['kegiatan'] ?? []; // Array dari form repeater

if (empty($tim_id) || empty($kegiatan_list)) {
    $_SESSION['error_message'] = "Data tidak lengkap.";
    header("Location: ../pages/tambah_kegiatan_tim.php");
    exit;
}

// Mulai Transaksi
$koneksi->begin_transaction();

try {
    // Siapkan Prepared Statement (Supaya efisien di dalam loop)
    
    // 1. Insert ke tabel `kegiatan`
    $stmt_kegiatan = $koneksi->prepare("INSERT INTO kegiatan (nama_kegiatan, tim_id, target, realisasi, satuan, batas_waktu, keterangan, created_at) VALUES (?, ?, ?, 0, ?, ?, ?, NOW())");

    // 2. Insert ke tabel `kegiatan_anggota`
    $stmt_anggota = $koneksi->prepare("INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota, realisasi_anggota) VALUES (?, ?, ?, 0)");

    foreach ($kegiatan_list as $data) {
        $nama   = trim($data['nama']);
        $satuan = trim($data['satuan']);
        $batas  = $data['batas_waktu'];
        $ket    = trim($data['keterangan'] ?? '');
        $targets = $data['targets'] ?? []; // Array [member_id => value]

        // Hitung total target dari input anggota
        $total_target = 0;
        foreach($targets as $val) { $total_target += (float)$val; }

        if ($total_target <= 0) {
            throw new Exception("Target total untuk kegiatan '$nama' harus lebih dari 0.");
        }

        // Eksekusi Insert Kegiatan
        $stmt_kegiatan->bind_param("sidsss", $nama, $tim_id, $total_target, $satuan, $batas, $ket);
        if (!$stmt_kegiatan->execute()) {
            throw new Exception("Gagal menyimpan kegiatan: " . $stmt_kegiatan->error);
        }
        
        $kegiatan_id = $koneksi->insert_id;

        // Eksekusi Insert Anggota
        foreach ($targets as $member_id => $target_val) {
            $val_float = (float)$target_val;
            // Simpan semua anggota, meskipun targetnya 0 (agar muncul di list realisasi nanti)
            // Atau Anda bisa membatasi: if($val_float > 0) { ... }
            $stmt_anggota->bind_param("iid", $kegiatan_id, $member_id, $val_float);
            if (!$stmt_anggota->execute()) {
                throw new Exception("Gagal menyimpan target anggota.");
            }
        }
    }

    $stmt_kegiatan->close();
    $stmt_anggota->close();

    $koneksi->commit();
    $_SESSION['success_message'] = "Berhasil menambahkan " . count($kegiatan_list) . " kegiatan baru!";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: ../pages/tambah_kegiatan_tim.php");
    exit;
}

header("Location: ../pages/kegiatan_tim.php");
exit;
?>