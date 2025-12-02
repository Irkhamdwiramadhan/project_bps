<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Ambil Data Input
    $aktivitas       = trim($_POST['aktivitas']);
    $jenis_aktivitas = $_POST['jenis_aktivitas'];
    $tanggal         = $_POST['tanggal'];
    $waktu_mulai     = $_POST['waktu_mulai'];
    
    // Logic Waktu Selesai
    if (isset($_POST['is_selesai']) && $_POST['is_selesai'] == '1') {
        $waktu_selesai = '23:59:00'; // Set max time untuk logika bentrok
        // Opsional: Jika di database kolomnya VARCHAR, bisa simpan string "Selesai"
        // Tapi untuk validasi SQL (waktu), kita butuh format Time.
        // Kita simpan 23:59 agar validasi berjalan.
    } else {
        $waktu_selesai = $_POST['waktu_selesai'];
    }

    $tim_kerja       = $_POST['tim_kerja_id'];
    $jumlah_peserta  = (int)$_POST['jumlah_peserta'];
    $peserta         = trim($_POST['peserta_ids']);
    
    // Logic Tempat
    $tempat = '';
    if ($jenis_aktivitas == 'Pertemuan Dalam Kantor') {
        $tempat = $_POST['tempat_internal'];
    } else {
        $tempat = $_POST['tempat_external'];
    }

    // Validasi Dasar
    if (empty($aktivitas) || empty($jenis_aktivitas) || empty($tanggal) || empty($waktu_mulai)) {
         header("Location: ../pages/tambah_kegiatan_pegawai.php?status=error&message=" . urlencode("Data wajib tidak lengkap!"));
         exit;
    }

    // Validasi Jam (Hanya jika bukan "Selesai")
    if (!isset($_POST['is_selesai']) && $waktu_mulai >= $waktu_selesai) {
        header("Location: ../pages/tambah_kegiatan_pegawai.php?status=error&message=" . urlencode("Jam selesai harus lebih besar dari jam mulai."));
        exit;
    }

    // ============================================================
    // LOGIKA CEK BENTROK (Hanya jika Dalam Kantor)
    // ============================================================
    if ($jenis_aktivitas == 'Pertemuan Dalam Kantor') {
        // Cek overlap waktu di ruangan yang sama pada tanggal yang sama
        $sql_cek = "SELECT aktivitas, waktu_mulai, waktu_selesai 
                    FROM kegiatan_pegawai 
                    WHERE tanggal = ? 
                    AND tempat = ? 
                    AND (
                        (waktu_mulai < ? AND waktu_selesai > ?) 
                    )";
        
        $stmt_cek = $koneksi->prepare($sql_cek);
        // Parameter: Tanggal, Tempat, Selesai_Baru, Mulai_Baru
        $stmt_cek->bind_param("ssss", $tanggal, $tempat, $waktu_selesai, $waktu_mulai);
        $stmt_cek->execute();
        $res_cek = $stmt_cek->get_result();
        
        if ($res_cek->num_rows > 0) {
            $bentrok = $res_cek->fetch_assoc();
            $jam_b = substr($bentrok['waktu_mulai'], 0, 5) . "-" . substr($bentrok['waktu_selesai'], 0, 5);
            
            $msg = "Gagal! Ruangan '$tempat' sudah dibooking untuk kegiatan: '" . $bentrok['aktivitas'] . "' ($jam_b).";
            header("Location: ../pages/tambah_kegiatan_pegawai.php?status=error&message=" . urlencode($msg));
            exit;
        }
        $stmt_cek->close();
    }

    // ============================================================
    // SIMPAN DATA
    // ============================================================
    // Catatan: Jika Anda ingin menyimpan kata "Selesai" di DB (bukan 23:59),
    // pastikan kolom waktu_selesai tipe datanya VARCHAR. Jika TIME, harus jam.
    // Di sini saya asumsikan simpan jam (23:59) agar aman.
    
    $query = "INSERT INTO kegiatan_pegawai 
              (tanggal, waktu_mulai, waktu_selesai, aktivitas, jenis_aktivitas, tempat, tim_kerja_id, jumlah_peserta, peserta_ids) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("sssssssis", 
        $tanggal, $waktu_mulai, $waktu_selesai, 
        $aktivitas, $jenis_aktivitas, $tempat, 
        $tim_kerja, $jumlah_peserta, $peserta
    );
    
    if ($stmt->execute()) {
        header("Location: ../pages/kegiatan_pegawai.php?status=success&message=" . urlencode("Kegiatan berhasil diajukan!"));
    } else {
        header("Location: ../pages/tambah_kegiatan_pegawai.php?status=error&message=" . urlencode("Database Error: " . $stmt->error));
    }
    $stmt->close();
    $koneksi->close();

} else {
    header("Location: ../pages/tambah_kegiatan_pegawai.php");
    exit;
}
?>