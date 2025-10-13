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
body {
    background-color: #f4f6f9;
    font-family: 'Poppins', sans-serif;
    overflow-x: hidden;
}

/* Offset agar tidak tertutup sidebar */
.content-wrapper {
    margin-left: 250px;
    padding: 40px;
    transition: all 0.3s ease-in-out;
    min-height: 100vh;
    background: #f4f6f9;
}
body.sidebar-collapse .content-wrapper {
    margin-left: 80px;
}

/* Card Form */
.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    background: #fff;
    padding: 30px;
}

.card h3 {
    font-weight: 600;
    color: #333;
    margin-bottom: 25px;
}

.form-control, .form-select {
    border-radius: 10px;
    border: 1px solid #ccc;
    transition: all 0.3s ease;
}
.form-control:focus, .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.15rem rgba(0,123,255,0.25);
}

.btn-primary {
    border-radius: 10px;
    background: #007bff;
    border: none;
    padding: 10px 20px;
    transition: 0.3s;
}
.btn-primary:hover {
    background: #0056b3;
    transform: scale(1.03);
}

.alert {
    border-radius: 10px;
}

/* Responsif */
@media (max-width: 991px) {
    .content-wrapper {
        margin-left: 0 !important;
        padding: 20px;
    }
}
</style>

<div class="content-wrapper">
    <section class="content-header mb-4 d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-user-plus me-2"></i> Tambah Data Tamu</h1>
        <a href="tamu.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <h3><i class="fas fa-user-edit me-2"></i> Form Input Tamu</h3>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" action="../proses/proses_tambah_tamu.php" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="nama" class="form-label">Nama</label>
                            <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama tamu" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="asal" class="form-label">Asal</label>
                            <input type="text" class="form-control" id="asal" name="asal" placeholder="Masukkan asal tamu" required>
                        </div>
                        <div class="col-md-6">
                            <label for="petugas" class="form-label">Petugas Penerima</label>
                            <input type="text" class="form-control" id="petugas" name="petugas" placeholder="Masukkan nama petugas" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="keperluan" class="form-label">Keperluan</label>
                        <textarea class="form-control" id="keperluan" name="keperluan" rows="3" placeholder="Tuliskan keperluan tamu" required></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="jam_datang" class="form-label">Jam Datang</label>
                            <input type="time" class="form-control" id="jam_datang" name="jam_datang" required>
                        </div>
                        <div class="col-md-6">
                            <label for="jam_pulang" class="form-label">Jam Pulang</label>
                            <input type="time" class="form-control" id="jam_pulang" name="jam_pulang">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="foto" class="form-label">Upload Foto</label>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        <small class="text-muted">Format yang diizinkan: JPG, PNG, GIF</small>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
