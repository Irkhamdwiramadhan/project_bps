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

$success = $error = "";

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal = $_POST['tanggal'];
    $nama = trim($_POST['nama']);
    $asal = trim($_POST['asal']);
    $keperluan = trim($_POST['keperluan']);
    $jam_datang = $_POST['jam_datang'];
    $jam_pulang = $_POST['jam_pulang'];
    $petugas = trim($_POST['petugas']);
    $foto = '';

    // Upload foto jika ada
    if (!empty($_FILES['foto']['name'])) {
        $target_dir = "../uploads/tamu/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES['foto']['name']);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $valid_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $valid_extensions)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $file_name;
            } else {
                $error = "Gagal mengupload foto.";
            }
        } else {
            $error = "Format foto harus JPG, JPEG, PNG, atau GIF.";
        }
    }

    if (!$error) {
        $stmt = $koneksi->prepare("INSERT INTO tamu (tanggal, nama, asal, keperluan, jam_datang, jam_pulang, petugas, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $tanggal, $nama, $asal, $keperluan, $jam_datang, $jam_pulang, $petugas, $foto);

        if ($stmt->execute()) {
            $success = "Data tamu berhasil ditambahkan!";
        } else {
            $error = "Terjadi kesalahan saat menyimpan data.";
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

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', 'Poppins', sans-serif; /* Font modern */
    }

    /* Layout Wrapper */
    .content-wrapper {
        margin-left: 250px;
        padding: 40px;
        transition: all 0.3s ease;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }
    @media (max-width: 991px) { .content-wrapper { margin-left: 0; padding: 20px; } }

    /* Main Card Container */
    .modern-card {
        background: var(--surface-color);
        border-radius: 24px;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        overflow: hidden;
        width: 100%;
        max-width: 1100px;
        display: flex;
        flex-wrap: wrap;
    }

    /* Left Panel (Visual Info) */
    .left-panel {
        flex: 1;
        min-width: 300px;
        background: var(--primary-gradient);
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    /* Dekorasi Background Abstrak */
    .left-panel::before {
        content: '';
        position: absolute;
        top: -50px; right: -50px;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }
    .left-panel::after {
        content: '';
        position: absolute;
        bottom: -80px; left: -50px;
        width: 300px; height: 300px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }

    .panel-content { position: relative; z-index: 2; }
    .panel-title { font-size: 2rem; font-weight: 800; margin-bottom: 10px; line-height: 1.2; }
    .panel-subtitle { font-size: 1rem; opacity: 0.8; margin-bottom: 30px; }
    
    .info-box {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        padding: 20px;
        border-radius: 16px;
        margin-top: auto;
        border: 1px solid rgba(255,255,255,0.2);
    }
    .info-time { font-size: 1.5rem; font-weight: 700; }
    .info-date { font-size: 0.9rem; opacity: 0.9; }

    /* Right Panel (Form) */
    .right-panel {
        flex: 1.5;
        min-width: 400px;
        padding: 40px;
    }

    .form-header-mobile { display: none; } /* Hanya muncul di HP jika perlu */

    .btn-back-link {
        display: inline-flex; align-items: center;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 500;
        margin-bottom: 20px;
        font-size: 0.9rem;
        transition: 0.2s;
    }
    .btn-back-link:hover { color: #4f46e5; transform: translateX(-3px); }

    /* Form Styling */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
        display: block;
    }
    
    .form-control {
        background-color: var(--input-bg);
        border: 1px solid var(--input-border);
        border-radius: 12px;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    .form-control:focus {
        background-color: #fff;
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px var(--focus-ring);
        outline: none;
    }

    textarea.form-control { min-height: 100px; resize: vertical; }

    /* Upload Zone Modern */
    .upload-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        background-color: #f8fafc;
        transition: 0.3s;
        cursor: pointer;
        position: relative;
    }
    .upload-zone:hover { border-color: #4f46e5; background-color: #eff6ff; }
    .upload-zone input {
        position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;
    }
    .upload-icon { font-size: 24px; color: #9ca3af; margin-bottom: 5px; }
    .upload-label { font-size: 0.9rem; color: #4b5563; font-weight: 500; }
    .upload-sub { font-size: 0.75rem; color: #9ca3af; }

    /* Buttons */
    .btn-save {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 14px 30px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1rem;
        width: 100%;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
    }

    /* Alert */
    .alert-custom {
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex; align-items: center; gap: 10px;
    }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    @media (max-width: 768px) {
        .modern-card { flex-direction: column; }
        .left-panel { min-height: 200px; padding: 30px; }
        .info-box { display: none; } /* Sembunyikan jam di HP agar tidak penuh */
    }
</style>

<div class="content-wrapper">
    
    <div class="modern-card">
        


        <div class="right-panel">
            
            <a href="tamu.php" class="btn-back-link">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Tamu
            </a>

            <h3 style="font-weight:700; color:#111; margin-bottom:25px;">Input Data Tamu</h3>

            <?php if ($success): ?>
                <div class="alert-custom alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php elseif ($error): ?>
                <div class="alert-custom alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="../proses/proses_tambah_tamu.php" enctype="multipart/form-data">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="tanggal" class="form-label">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="petugas" class="form-label">Petugas Penerima</label>
                        <input type="text" class="form-control" id="petugas" name="petugas" placeholder="Petugas piket" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Tamu" required>
                    </div>
                    <div class="col-md-6">
                        <label for="asal" class="form-label">Instansi / Asal</label>
                        <input type="text" class="form-control" id="asal" name="asal" placeholder="Asal Instansi" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="keperluan" class="form-label">Keperluan Kunjungan</label>
                    <textarea class="form-control" id="keperluan" name="keperluan" rows="2" placeholder="Jelaskan tujuan kunjungan..." required></textarea>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="jam_datang" class="form-label">Jam Datang</label>
                        <input type="time" class="form-control" id="jam_datang" name="jam_datang" required value="<?= date('H:i') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="jam_pulang" class="form-label">Jam Pulang (Opsional)</label>
                        <input type="time" class="form-control" id="jam_pulang" name="jam_pulang">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Foto Dokumentasi</label>
                    <div class="upload-zone">
                        <input type="file" id="foto" name="foto" accept="image/*" onchange="handleFileSelect()">
                        <div id="uploadText">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="upload-label">Klik untuk upload foto</div>
                            <div class="upload-sub">JPG, PNG, GIF (Max 2MB)</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">
                    Simpan Data Tamu <i class="fas fa-paper-plane ms-2"></i>
                </button>

            </form>
        </div>

    </div>
</div>

<script>
    // Jam Realtime
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('realtime-clock').textContent = timeString;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Handle File Upload UI
    function handleFileSelect() {
        const input = document.getElementById('foto');
        const textContainer = document.getElementById('uploadText');
        
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            textContainer.innerHTML = `
                <div class="upload-icon" style="color:#4f46e5"><i class="fas fa-check-circle"></i></div>
                <div class="upload-label" style="color:#4f46e5">${fileName}</div>
                <div class="upload-sub">Siap untuk diupload</div>
            `;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>