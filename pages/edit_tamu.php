<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Cek ID di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: tamu.php');
    exit;
}

$id = (int)$_GET['id'];
$success = $error = "";

// Ambil Data Lama
$stmt = $koneksi->prepare("SELECT * FROM tamu WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Data tamu tidak ditemukan.");
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal = $_POST['tanggal'];
    $nama = trim($_POST['nama']);
    $asal = trim($_POST['asal']);
    $keperluan = trim($_POST['keperluan']);
    $jam_datang = $_POST['jam_datang'];
    $jam_pulang = $_POST['jam_pulang'];
    $petugas = trim($_POST['petugas']);
    
    // Default pakai data lama
    $foto_db = $data['foto']; 

    // Cek jika ada upload foto baru
    if (!empty($_FILES['foto']['name'])) {
        // Folder tujuan (relatif dari file ini admin/tamu/)
        // Kita asumsikan folder uploads ada di root project, jadi naik 2 level (../../)
        $target_dir = "../../uploads/tamu/";
        
        // Buat folder jika belum ada
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $new_file_name = time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_file_name;
        
        $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $valid_extensions)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                // Hapus foto lama jika ada
                // Cek path lama (sesuaikan path relatifnya)
                if (!empty($data['foto'])) {
                    $old_file_path = "../../" . $data['foto']; 
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                // Simpan path lengkap agar konsisten (uploads/tamu/namafile.jpg)
                $foto_db = "uploads/tamu/" . $new_file_name;
            } else {
                $error = "Gagal mengupload foto baru.";
            }
        } else {
            $error = "Format foto harus JPG, JPEG, PNG, atau GIF.";
        }
    }

    if (!$error) {
        $update_sql = "UPDATE tamu SET tanggal=?, nama=?, asal=?, keperluan=?, jam_datang=?, jam_pulang=?, petugas=?, foto=? WHERE id=?";
        $stmt = $koneksi->prepare($update_sql);
        $stmt->bind_param("ssssssssi", $tanggal, $nama, $asal, $keperluan, $jam_datang, $jam_pulang, $petugas, $foto_db, $id);

        if ($stmt->execute()) {
            $success = "Data tamu berhasil diperbarui!";
            // Refresh data di variabel agar preview langsung berubah
            $data['foto'] = $foto_db;
            $data['tanggal'] = $tanggal;
            $data['nama'] = $nama;
            $data['asal'] = $asal;
            $data['keperluan'] = $keperluan;
            $data['jam_datang'] = $jam_datang;
            $data['jam_pulang'] = $jam_pulang;
            $data['petugas'] = $petugas;
        } else {
            $error = "Terjadi kesalahan saat menyimpan perubahan.";
        }
        $stmt->close();
    }
}
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        --surface-color: #ffffff;
        --bg-body: #f3f4f6;
        --text-main: #1f2937;
        --text-muted: #6b7280;
        --input-bg: #f9fafb;
        --input-border: #e5e7eb;
        --focus-ring: rgba(79, 70, 229, 0.2);
    }

    body { background-color: var(--bg-body); font-family: 'Inter', 'Poppins', sans-serif; }

    .content-wrapper {
        margin-left: 250px; padding: 40px; transition: all 0.3s ease;
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
    }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }
    @media (max-width: 991px) { .content-wrapper { margin-left: 0; padding: 20px; } }

    .modern-card {
        background: var(--surface-color); border-radius: 24px;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); overflow: hidden;
        width: 100%; max-width: 1100px; display: flex; flex-wrap: wrap;
    }

    .left-panel {
        flex: 1; min-width: 300px; background: var(--primary-gradient);
        padding: 40px; display: flex; flex-direction: column;
        justify-content: space-between; color: white; position: relative; overflow: hidden;
    }
    
    .left-panel::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; }
    .left-panel::after { content: ''; position: absolute; bottom: -80px; left: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%; }

    .panel-content { position: relative; z-index: 2; }
    .panel-title { font-size: 2rem; font-weight: 800; margin-bottom: 10px; line-height: 1.2; }
    .panel-subtitle { font-size: 1rem; opacity: 0.8; margin-bottom: 30px; }
    
    .current-photo-box {
        background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
        padding: 20px; border-radius: 16px; margin-top: auto;
        border: 1px solid rgba(255,255,255,0.2); text-align: center;
    }
    .current-photo-img {
        width: 100%; max-height: 200px; object-fit: cover;
        border-radius: 12px; border: 2px solid rgba(255,255,255,0.5);
    }

    .right-panel { flex: 1.5; min-width: 400px; padding: 40px; }

    .btn-back-link { display: inline-flex; align-items: center; color: var(--text-muted); text-decoration: none; font-weight: 500; margin-bottom: 20px; font-size: 0.9rem; transition: 0.2s; }
    .btn-back-link:hover { color: #4f46e5; transform: translateX(-3px); }

    .form-label { font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
    .form-control { background-color: var(--input-bg); border: 1px solid var(--input-border); border-radius: 12px; padding: 12px 15px; font-size: 0.95rem; transition: all 0.2s ease; }
    .form-control:focus { background-color: #fff; border-color: #4f46e5; box-shadow: 0 0 0 4px var(--focus-ring); outline: none; }
    textarea.form-control { min-height: 100px; resize: vertical; }

    .upload-zone { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; background-color: #f8fafc; transition: 0.3s; cursor: pointer; position: relative; }
    .upload-zone:hover { border-color: #4f46e5; background-color: #eff6ff; }
    .upload-zone input { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
    .upload-icon { font-size: 24px; color: #9ca3af; margin-bottom: 5px; }
    .upload-label { font-size: 0.9rem; color: #4b5563; font-weight: 500; }
    .upload-sub { font-size: 0.75rem; color: #9ca3af; }

    .btn-save { background: var(--primary-gradient); color: white; border: none; padding: 14px 30px; border-radius: 12px; font-weight: 600; font-size: 1rem; width: 100%; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); transition: transform 0.2s, box-shadow 0.2s; }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4); }

    .alert-custom { border-radius: 12px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    @media (max-width: 768px) { .modern-card { flex-direction: column; } .left-panel { min-height: 200px; padding: 30px; } }
</style>

<div class="content-wrapper">
    <div class="modern-card">
        
        <!-- PANEL KIRI -->
        <!-- <div class="left-panel">
            <div class="panel-content">
                <div class="panel-title">Edit Data Tamu</div>
                <div class="panel-subtitle">Perbarui informasi kunjungan tamu jika terdapat kesalahan input.</div>
            </div>

            FIX IMAGE DISPLAY
            <div class="panel-content current-photo-box">
                <p class="mb-2 text-white font-weight-bold" style="font-size: 0.9rem;">
                    <i class="fas fa-image me-2"></i> Foto Saat Ini
                </p>
                
                <?php
                    // Tentukan path gambar yang benar
                    $foto_path = "";
                    if (!empty($data['foto'])) {
                        // Cek apakah path di DB sudah mengandung 'uploads/tamu/'
                        if (strpos($data['foto'], 'uploads/tamu/') !== false) {
                            // Jika ya, pathnya cukup naik 2 level ke root lalu ke path db
                            $foto_path = "../../" . $data['foto'];
                        } else {
                            // Jika DB cuma simpan nama file, tambahkan folder manual
                            $foto_path = "../../uploads/tamu/" . $data['foto'];
                        }
                    }
                ?>

                <?php if (!empty($foto_path) && file_exists($foto_path)): ?>
                    <img src="<?= $foto_path ?>" alt="Foto Tamu" class="current-photo-img">
                <?php else: ?>
                    <div style="padding: 30px 0; color: rgba(255,255,255,0.7);">
                        <i class="fas fa-user-slash fa-3x mb-2"></i>
                        <p class="mb-0">Tidak ada foto / File hilang</p>
                        <?php if(!empty($data['foto'])): ?>
                            <small style="font-size:0.7rem;">(DB: <?= htmlspecialchars($data['foto']) ?>)</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div> -->

        <!-- PANEL KANAN -->
        <div class="right-panel">
            <a href="tamu.php" class="btn-back-link"><i class="fas fa-arrow-left me-2"></i> Kembali</a>
            <h3 style="font-weight:700; color:#111; margin-bottom:25px;">Formulir Perubahan</h3>

            <?php if ($success): ?>
                <div class="alert-custom alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
            <?php elseif ($error): ?>
                <div class="alert-custom alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Tanggal & Petugas -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="tanggal" class="form-label">Tanggal Berkunjung</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required value="<?= htmlspecialchars($data['tanggal']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="petugas" class="form-label">Petugas Penerima</label>
                        <input type="text" class="form-control" id="petugas" name="petugas" required value="<?= htmlspecialchars($data['petugas']) ?>">
                    </div>
                </div>

                <!-- Identitas -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" required value="<?= htmlspecialchars($data['nama']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="asal" class="form-label">Instansi / Asal</label>
                        <input type="text" class="form-control" id="asal" name="asal" required value="<?= htmlspecialchars($data['asal']) ?>">
                    </div>
                </div>

                <!-- Keperluan -->
                <div class="mb-3">
                    <label for="keperluan" class="form-label">Keperluan Kunjungan</label>
                    <textarea class="form-control" id="keperluan" name="keperluan" rows="2" required><?= htmlspecialchars($data['keperluan']) ?></textarea>
                </div>

                <!-- Waktu -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="jam_datang" class="form-label">Jam Datang</label>
                        <input type="time" class="form-control" id="jam_datang" name="jam_datang" required value="<?= htmlspecialchars($data['jam_datang']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="jam_pulang" class="form-label">Jam Pulang</label>
                        <input type="time" class="form-control" id="jam_pulang" name="jam_pulang" value="<?= htmlspecialchars($data['jam_pulang']) ?>">
                    </div>
                </div>

                <!-- Upload Baru -->
                <div class="mb-4">
                    <label class="form-label">Ganti Foto (Opsional)</label>
                    <div class="upload-zone">
                        <input type="file" id="foto" name="foto" accept="image/*" onchange="handleFileSelect()">
                        <div id="uploadText">
                            <div class="upload-icon"><i class="fas fa-camera"></i></div>
                            <div class="upload-label">Klik untuk mengganti foto</div>
                            <div class="upload-sub">Biarkan kosong jika tidak ingin mengubah foto</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i> Simpan Perubahan</button>
            </form>
        </div>
    </div>
</div>

<script>
    function handleFileSelect() {
        const input = document.getElementById('foto');
        const textContainer = document.getElementById('uploadText');
        if (input.files && input.files[0]) {
            textContainer.innerHTML = `<div class="upload-icon" style="color:#4f46e5"><i class="fas fa-check-circle"></i></div><div class="upload-label" style="color:#4f46e5">${input.files[0].name}</div><div class="upload-sub">Foto baru dipilih</div>`;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>