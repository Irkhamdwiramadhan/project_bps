<?php
// ../proses/proses_tambah_memo_satpam.php
session_start();
include '../includes/koneksi.php';

// Ambil data dari form
$tanggal    = $_POST['tanggal'] ?? '';
$pegawai_ids = $_POST['pegawai_id'] ?? []; // NOTE: sekarang bisa array
$keperluan  = trim($_POST['keperluan'] ?? '');
$jam_pergi  = $_POST['jam_pergi'] ?? '';
$jam_pulang = !empty($_POST['jam_pulang']) ? $_POST['jam_pulang'] : null;
$petugas    = trim($_POST['petugas'] ?? '');
$foto_path  = null;

// Validasi dasar
if (empty($tanggal) || empty($pegawai_ids) || empty($keperluan) || empty($jam_pergi) || empty($petugas)) {
    // Bisa ganti menjadi redirect dengan session message
    die("Field wajib belum lengkap. Pastikan tanggal, minimal 1 pegawai, keperluan, jam pergi dan petugas terisi.");
}

// Pastikan $pegawai_ids adalah array
if (!is_array($pegawai_ids)) {
    $pegawai_ids = [$pegawai_ids];
}

// 1) Proses upload foto (hanya sekali)
if (isset($_FILES['foto']) && isset($_FILES['foto']['name']) && $_FILES['foto']['name'] !== '') {
    $upload_dir = '../uploads/memo_satpam/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];
    if (!in_array($ext, $allowed)) {
        die("Format foto tidak valid. Gunakan JPG/JPEG/PNG/GIF.");
    }
    $file_name = 'memo_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $upload_path = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
        // Simpan relative path sesuai konvensi project
        $foto_path = 'uploads/memo_satpam/' . $file_name;
    } else {
        die("Gagal mengupload foto.");
    }
}

// 2) Siapkan statement insert (single prepared statement, di-reuse)
$stmt = $koneksi->prepare("INSERT INTO memo_satpam (tanggal, pegawai_id, keperluan, jam_pergi, jam_pulang, petugas, foto) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $koneksi->error);
}

// Tipe bind: tanggal(s), pegawai_id(i), keperluan(s), jam_pergi(s), jam_pulang(s|null), petugas(s), foto(s|null)
// jadi types = "sisssss" (second param integer)
foreach ($pegawai_ids as $pid_raw) {
    $pid = (int)$pid_raw;

    // Jika kamu ingin melewatkan baris kosong (mis. pegawai id = 0), skip
    if ($pid <= 0) continue;

    // Untuk bind_param butuh variabel, tidak langsung literal
    $bind_tanggal = $tanggal;
    $bind_pegawai_id = $pid;
    $bind_keperluan = $keperluan;
    $bind_jam_pergi = $jam_pergi;
    // Untuk jam_pulang bisa null; pastikan variabelnya ada
    $bind_jam_pulang = $jam_pulang !== null ? $jam_pulang : null;
    $bind_petugas = $petugas;
    $bind_foto = $foto_path !== null ? $foto_path : null;

    // bind_param: gunakan "sisssss" (s, i, s, s, s, s, s)
    // note: mysqli akan mengirim NULL jika var bernilai null
    $ok = $stmt->bind_param(
        "sisssss",
        $bind_tanggal,
        $bind_pegawai_id,
        $bind_keperluan,
        $bind_jam_pergi,
        $bind_jam_pulang,
        $bind_petugas,
        $bind_foto
    );

    if (!$ok) {
        // debug jika perlu:
        // die("Bind param failed: " . $stmt->error);
        continue;
    }

    if (!$stmt->execute()) {
        // Jika satu insert gagal, kamu bisa rollback atau catat error.
        // Di sini kita hentikan dan laporkan.
        $stmt->close();
        $koneksi->close();
        die("Gagal menyimpan memo untuk pegawai ID {$pid}: " . $stmt->error);
    }
}

// Semua berhasil
$stmt->close();
$koneksi->close();

// Redirect kembali ke halaman daftar (ubah path sesuai strukturmu)
header("Location: ../pages/memo_keluar_kantor.php");
exit;
