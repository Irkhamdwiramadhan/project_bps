<?php
session_start();
include '../includes/koneksi.php';

// 1. Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../../login.php');
    exit;
}

$id_pegawai = $_SESSION['user_id'];

// 2. Terima Data dari Form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data input
    $tanggal_multi = $_POST['tanggal_multi'] ?? ''; // Format: "2023-10-01, 2023-10-02"
    $jenis_kegiatan = $_POST['jenis_kegiatan'] ?? '';
    $uraian = mysqli_real_escape_string($koneksi, $_POST['uraian'] ?? '');
    
    // Validasi data kosong
    if (empty($tanggal_multi) || empty($jenis_kegiatan) || empty($uraian)) {
        header("Location: ../pages/tambah_kegiatan_saya.php?status=error&message=Data tidak lengkap!");
        exit;
    }

    // Pecah string tanggal menjadi array
    // Flatpickr mengirim tanggal dipisah koma dan spasi (misal: "2025-01-01, 2025-01-02")
    $dates = explode(', ', $tanggal_multi);
    
    $success_count = 0;
    $failed_dates = []; // Untuk menampung tanggal yang gagal

    // 3. Loop untuk setiap tanggal yang dipilih
    foreach ($dates as $tgl) {
        $tgl = trim($tgl); // Bersihkan spasi
        
        // --- LOGIKA UTAMA: CEK DUPLIKAT ---
        // Cek apakah user sudah punya kegiatan di tanggal ini
        $query_cek = "SELECT id FROM kegiatan_harian 
                      WHERE pegawai_id = '$id_pegawai' 
                      AND tanggal = '$tgl'";
        $result_cek = mysqli_query($koneksi, $query_cek);
        
        $sudah_ada_kegiatan = (mysqli_num_rows($result_cek) > 0);

        // ATURAN:
        // Jika sudah ada kegiatan, DAN kegiatan baru ini BUKAN 'Rapat', maka BLOCK.
        // (Artinya: Kalau kegiatan baru adalah 'Rapat', tetap boleh masuk meski tanggal sudah terisi)
        if ($sudah_ada_kegiatan && $jenis_kegiatan !== 'Rapat') {
            $failed_dates[] = $tgl; // Masukkan ke daftar gagal
            continue; // Skip, jangan insert ke database
        }

        // --- PROSES INSERT ---
        // Jika lolos validasi di atas, masukkan data
        $query_insert = "INSERT INTO kegiatan_harian (pegawai_id, tanggal, jam_mulai, jam_selesai, jenis_kegiatan, uraian) 
                         VALUES ('$id_pegawai', '$tgl', '07:30:00', '16:00:00', '$jenis_kegiatan', '$uraian')";
        
        if (mysqli_query($koneksi, $query_insert)) {
            $success_count++;
        }
    }

    // 4. Redirect dengan Pesan
    if (!empty($failed_dates)) {
        // Jika ada yang gagal
        $list_gagal = implode(", ", $failed_dates);
        $msg = "Berhasil simpan $success_count data. Gagal pada tanggal: $list_gagal (anda sudah melakukan kegiatan di tgl ini coba cek tgl yang anda input).";
        
        // Redirect dengan status warning/error
        // Note: Kamu mungkin perlu handle status 'warning' di file tampilan jika ingin warna kuning
        header("Location: ../pages/tambah_kegiatan_saya.php?status=error&message=" . urlencode($msg));
    } else {
        // Jika sukses semua
        header("Location: ../pages/kegiatan_saya.php?status=success&message=Kegiatan berhasil disimpan!");
    }

} else {
    // Jika akses bukan POST
    header("Location: ../pages/kegiatan_saya.php");
}
?>