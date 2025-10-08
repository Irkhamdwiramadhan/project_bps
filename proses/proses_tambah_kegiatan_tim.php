<?php
// ../proses/proses_tambah_kegiatan.php (REVISI FINAL)

session_start();
include '../includes/koneksi.php';

// Keamanan: Pastikan metode request adalah POST dan pengguna memiliki akses
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die("Akses tidak sah.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_simpedu'];
if (!array_intersect($allowed_roles, $user_roles)) {
    $_SESSION['error_message'] = "Anda tidak memiliki izin untuk melakukan aksi ini.";
    header('Location: ../pages/kegiatan_tim.php');
    exit;
}

// ===================================================================
// PENGAMBILAN & PEMBERSIHAN DATA DARI FORMULIR
// ===================================================================
$nama_kegiatan = trim($_POST['nama_kegiatan']);
$tim_id = (int)$_POST['tim_id'];
$target = (float)$_POST['target'];
$satuan = trim($_POST['satuan']);
$batas_waktu = $_POST['batas_waktu'];
$keterangan = trim($_POST['keterangan']);

// Data anggota (akan berbentuk array)
$anggota_ids = $_POST['anggota_id'] ?? [];
$target_anggotas = $_POST['target_anggota'] ?? [];


// ===================================================================
// VALIDASI DATA
// ===================================================================
if (empty($nama_kegiatan) || empty($tim_id) || !isset($_POST['target']) || empty($satuan) || empty($batas_waktu) || empty($anggota_ids)) {
    $_SESSION['error_message'] = "Semua field wajib diisi, dan minimal harus ada satu anggota.";
    header('Location: ../pages/tambah_kegiatan_tim.php');
    exit;
}

// Mulai Transaksi Database untuk memastikan integritas data
$koneksi->begin_transaction();

try {
    // ===================================================================
    // LANGKAH 1: SIMPAN DATA UTAMA KE TABEL `kegiatan`
    // ===================================================================
    $stmt_kegiatan = $koneksi->prepare(
        "INSERT INTO kegiatan (nama_kegiatan, tim_id, target, satuan, batas_waktu, keterangan) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    // bind_param: s=string, i=integer, d=double
    $stmt_kegiatan->bind_param("sidsss", $nama_kegiatan, $tim_id, $target, $satuan, $batas_waktu, $keterangan);

    if (!$stmt_kegiatan->execute()) {
        // Jika gagal, lemparkan error untuk memicu rollback
        throw new Exception("Gagal menyimpan data kegiatan utama: " . $stmt_kegiatan->error);
    }

    // Ambil ID dari kegiatan yang baru saja dibuat
    $kegiatan_id = $koneksi->insert_id;
    $stmt_kegiatan->close();


    // ===================================================================
    // LANGKAH 2: SIMPAN DATA ANGGOTA KE TABEL `kegiatan_anggota`
    // ===================================================================
    $stmt_anggota = $koneksi->prepare(
        "INSERT INTO kegiatan_anggota (kegiatan_id, anggota_id, target_anggota) 
         VALUES (?, ?, ?)"
    );

    // Looping untuk setiap anggota yang dikirim dari form
    foreach ($anggota_ids as $key => $anggota_id) {
        $target_individu = (float)$target_anggotas[$key];
        
        $stmt_anggota->bind_param("iid", $kegiatan_id, $anggota_id, $target_individu);
        
        if (!$stmt_anggota->execute()) {
            // Jika salah satu anggota gagal disimpan, lemparkan error
            throw new Exception("Gagal menyimpan data anggota: " . $stmt_anggota->error);
        }
    }
    $stmt_anggota->close();

    // ===================================================================
    // LANGKAH 3: JIKA SEMUA BERHASIL, COMMIT TRANSAKSI
    // ===================================================================
    $koneksi->commit();
    $_SESSION['success_message'] = "Kegiatan baru dan data anggota berhasil ditambahkan!";

} catch (Exception $e) {
    // ===================================================================
    // JIKA ADA KEGAGALAN, ROLLBACK SEMUA PERUBAHAN
    // ===================================================================
    $koneksi->rollback();
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
} finally {
    // Tutup koneksi
    $koneksi->close();
}

// Alihkan kembali ke halaman utama kegiatan
header('Location: ../pages/kegiatan_tim.php');
exit;
?>