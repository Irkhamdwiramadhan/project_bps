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
    $_SESSION['error_message'] = "Data tidak lengkap. Pastikan Tim dan Kegiatan diisi.";
    header("Location: ../pages/tambah_kegiatan_tim.php");
    exit;
}

// Mulai Transaksi
$koneksi->begin_transaction();

try {
    // Siapkan Prepared Statement UNTUK KEGIATAN
    $sql_kegiatan = "INSERT INTO kegiatan (nama_kegiatan, tim_id, target, realisasi, satuan, batas_waktu, keterangan, created_at) VALUES (?, ?, ?, 0, ?, ?, ?, NOW())";
    $stmt_kegiatan = $koneksi->prepare($sql_kegiatan);

    // Siapkan Prepared Statement UNTUK ANGGOTA (Target Individu)
    $sql_anggota = "INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota, realisasi_anggota) VALUES (?, ?, ?, 0)";
    $stmt_anggota = $koneksi->prepare($sql_anggota);

    // Variabel Binding (Agar bisa di-reuse dalam loop)
    $bind_nama = "";
    $bind_tim_id = $tim_id;
    $bind_target_total = 0.0;
    $bind_satuan = "";
    $bind_batas = "";
    $bind_ket = "";
    
    // Bind Parameter Kegiatan (sekali saja di luar loop)
    // s=string, i=int, d=double
    $stmt_kegiatan->bind_param("sidsss", $bind_nama, $bind_tim_id, $bind_target_total, $bind_satuan, $bind_batas, $bind_ket);

    // Variabel Binding Anggota
    $bind_keg_id = 0;
    $bind_ang_id = 0;
    $bind_ang_target = 0.0;

    // Bind Parameter Anggota (sekali saja di luar loop)
    $stmt_anggota->bind_param("iid", $bind_keg_id, $bind_ang_id, $bind_ang_target);

    // --- LOOPING KEGIATAN ---
    foreach ($kegiatan_list as $data) {
        // 1. Set Variabel untuk Kegiatan
        $bind_nama   = trim($data['nama']);
        $bind_satuan = trim($data['satuan']);
        $bind_batas  = $data['batas_waktu'];
        $bind_ket    = trim($data['keterangan'] ?? '');
        $targets     = $data['targets'] ?? []; // Array [anggota_tim_id => target_value]

        // Hitung total target otomatis dari input anggota
        $bind_target_total = 0;
        foreach($targets as $val) { $bind_target_total += (float)$val; }

        if ($bind_target_total <= 0) {
            // Opsional: Jika ingin memaksa ada target. Jika boleh 0, hapus blok ini.
            // throw new Exception("Target total untuk kegiatan '$bind_nama' harus lebih dari 0.");
        }

        // Eksekusi Insert Kegiatan
        if (!$stmt_kegiatan->execute()) {
            throw new Exception("Gagal menyimpan kegiatan: " . $stmt_kegiatan->error);
        }
        
        // Ambil ID Kegiatan yang baru dibuat
        $bind_keg_id = $koneksi->insert_id;

        // 2. Loop Anggota untuk Simpan Target Individu
        foreach ($targets as $member_id => $target_val) {
            $bind_ang_id = $member_id;          // Ini sekarang adalah ID dari tabel anggota_tim (berkat revisi frontend)
            $bind_ang_target = (float)$target_val;
            
            // Eksekusi Insert Anggota
            if (!$stmt_anggota->execute()) {
                throw new Exception("Gagal menyimpan target anggota ID: $member_id");
            }
        }
    }

    $stmt_kegiatan->close();
    $stmt_anggota->close();

    $koneksi->commit();
    $_SESSION['success_message'] = "Berhasil menambahkan " . count($kegiatan_list) . " kegiatan baru beserta target pegawai!";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: ../pages/tambah_kegiatan_tim.php");
    exit;
}

header("Location: ../pages/kegiatan_tim.php");
exit;
?>