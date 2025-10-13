<?php
// ../proses/proses_tambah_memo_satpam.php
session_start();
include '../includes/koneksi.php';

// Ambil data dari form
$tanggal     = $_POST['tanggal'] ?? '';
$pegawai_ids = $_POST['pegawai_id'] ?? []; // bisa multiple
$keperluan   = trim($_POST['keperluan'] ?? '');
$jam_pergi   = $_POST['jam_pergi'] ?? '';
$jam_pulang  = !empty($_POST['jam_pulang']) ? $_POST['jam_pulang'] : null;
$petugas_id  = isset($_POST['petugas']) ? (int)$_POST['petugas'] : 0; // ID PPPK
$foto_path   = null;

// 🔒 Validasi dasar
if (empty($tanggal) || empty($pegawai_ids) || empty($keperluan) || empty($jam_pergi) || empty($petugas_id)) {
    die("Field wajib belum lengkap. Pastikan tanggal, minimal 1 pegawai, keperluan, jam pergi dan petugas terisi.");
}

// Pastikan $pegawai_ids adalah array
if (!is_array($pegawai_ids)) {
    $pegawai_ids = [$pegawai_ids];
}

// 1️⃣ Proses upload foto (opsional tapi aman)
if (isset($_FILES['foto']) && isset($_FILES['foto']['name']) && $_FILES['foto']['name'] !== '') {
    $upload_dir = '../uploads/memo_satpam/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($ext, $allowed)) {
        die("Format foto tidak valid. Gunakan JPG/JPEG/PNG/GIF.");
    }

    $file_name = 'memo_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $upload_path = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
        $foto_path = 'uploads/memo_satpam/' . $file_name; // simpan path relatif
    } else {
        die("Gagal mengupload foto.");
    }
}

// 2️⃣ Siapkan statement insert
$stmt = $koneksi->prepare("
    INSERT INTO memo_satpam (tanggal, pegawai_id, keperluan, jam_pergi, jam_pulang, petugas, foto)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Prepare failed: " . $koneksi->error);
}

// 3️⃣ Loop setiap pegawai yang terlibat
foreach ($pegawai_ids as $pid_raw) {
    $pid = (int)$pid_raw;
    if ($pid <= 0) continue;

    $bind_tanggal = $tanggal;
    $bind_pegawai_id = $pid;
    $bind_keperluan = $keperluan;
    $bind_jam_pergi = $jam_pergi;
    $bind_jam_pulang = $jam_pulang !== null ? $jam_pulang : null;
    $bind_petugas = $petugas_id;
    $bind_foto = $foto_path !== null ? $foto_path : null;

    // s = string, i = integer
    // urutan: tanggal(s), pegawai_id(i), keperluan(s), jam_pergi(s), jam_pulang(s), petugas(i), foto(s)
    $ok = $stmt->bind_param(
        "sisssis",
        $bind_tanggal,
        $bind_pegawai_id,
        $bind_keperluan,
        $bind_jam_pergi,
        $bind_jam_pulang,
        $bind_petugas,
        $bind_foto
    );

    if (!$ok) continue;

    if (!$stmt->execute()) {
        $stmt->close();
        $koneksi->close();
        die("Gagal menyimpan memo untuk pegawai ID {$pid}: " . $stmt->error);
    }
}

// 4️⃣ Tutup koneksi
$stmt->close();
$koneksi->close();

// 5️⃣ Redirect ke halaman daftar
header("Location: ../pages/memo_keluar_kantor.php");
exit;
?>
