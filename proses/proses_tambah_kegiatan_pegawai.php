<?php
session_start();
include '../includes/koneksi.php';

// Cek method post
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/kegiatan/kegiatan_pegawai.php');
    exit;
}

// 1. Ambil Data dari Form (Gunakan ?? '' agar tidak error jika input tidak ada)
$tanggal         = $_POST['tanggal'] ?? '';
$jenis_aktivitas = trim($_POST['jenis_aktivitas'] ?? '');
$aktivitas       = trim($_POST['aktivitas'] ?? '');
$waktu_mulai     = $_POST['waktu_mulai'] ?? '';
$waktu_selesai   = $_POST['waktu_selesai'] ?? '';
$tempat          = trim($_POST['tempat'] ?? '');
$nama_tim        = trim($_POST['nama_tim'] ?? '');      
$nama_peserta    = trim($_POST['nama_peserta'] ?? '');  
$jumlah_peserta  = isset($_POST['jumlah_peserta']) ? (int)$_POST['jumlah_peserta'] : 1;

// 2. Validasi Detail (Agar Anda tahu kolom mana yang kosong)
$errors = [];
if (empty($tanggal)) $errors[] = "Tanggal";
if (empty($jenis_aktivitas)) $errors[] = "Jenis Aktivitas";
if (empty($aktivitas)) $errors[] = "Nama Aktivitas";
if (empty($waktu_mulai)) $errors[] = "Waktu Mulai";
if (empty($nama_tim)) $errors[] = "Nama Tim";
if (empty($nama_peserta)) $errors[] = "Daftar Peserta";

if (!empty($errors)) {
    $list_error = implode(", ", $errors);
    echo "<script>
            alert('Gagal! Kolom berikut wajib diisi: $list_error.\\n\\nPastikan Anda sudah memperbarui file formulir (tambah_kegiatan.php) agar sesuai dengan kode proses baru.'); 
            window.history.back();
          </script>";
    exit;
}

/* === PENTING: UPDATE STRUKTUR DATABASE ===
   Pastikan kolom DB sudah diubah tipenya menjadi VARCHAR/TEXT:
   1. tim_kerja_id -> VARCHAR(255)
   2. peserta_ids -> TEXT
   3. waktu_selesai -> VARCHAR(20)
*/

// 3. Query Insert
$query = "INSERT INTO kegiatan_pegawai 
          (aktivitas, jenis_aktivitas, tanggal, waktu_mulai, waktu_selesai, tempat, tim_kerja_id, jumlah_peserta, peserta_ids) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $koneksi->prepare($query);

// Bind Parameter
$stmt->bind_param("sssssssis", 
    $aktivitas, 
    $jenis_aktivitas, 
    $tanggal, 
    $waktu_mulai, 
    $waktu_selesai, 
    $tempat, 
    $nama_tim,        
    $jumlah_peserta, 
    $nama_peserta     
);

// 4. Eksekusi & Redirect
if ($stmt->execute()) {
    $_SESSION['success'] = "Kegiatan berhasil ditambahkan!";
    header('Location: ../pages/kegiatan_pegawai.php');
} else {
    // Tampilkan error database jika gagal
    echo "<h3>Gagal Menyimpan Data ke Database!</h3>";
    echo "Pesan Error SQL: " . $stmt->error;
    echo "<br><br><b>Solusi:</b><br>";
    echo "1. Pastikan kolom <code>waktu_selesai</code> di database bertipe <b>VARCHAR</b> (bukan TIME).<br>";
    echo "2. Pastikan kolom <code>tim_kerja_id</code> bertipe <b>VARCHAR</b> (bukan INT).<br>";
    echo "3. Pastikan kolom <code>peserta_ids</code> bertipe <b>TEXT</b>.<br>";
    echo "<br><a href='javascript:history.back()'>Kembali ke Form</a>";
}

$stmt->close();
$koneksi->close();
?>