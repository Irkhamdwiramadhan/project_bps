<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Hanya admin_dipaku yang boleh mengakses halaman ini
if (!in_array('admin_dipaku', $_SESSION['user_role'] ?? [])) {
    echo '<main class="main-content">
            <div class="card card-access-denied">
              <h2 class="text-center text-danger">Akses Ditolak</h2>
              <p class="text-center">Halaman ini hanya dapat diakses oleh <b>Admin Dipaku</b>.</p>
            </div>
          </main>';
    include '../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahun = (int)$_POST['tahun'];
    $mulai = $_POST['mulai'];
    $selesai = $_POST['selesai'];

    $stmt = $koneksi->prepare("
        INSERT INTO rpd_setting_waktu (tahun, mulai, selesai, dibuat_oleh)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE mulai = VALUES(mulai), selesai = VALUES(selesai)
    ");
    $stmt->bind_param("isss", $tahun, $mulai, $selesai, $_SESSION['username']);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Batas waktu RPD berhasil disimpan!'); window.location='rpd_waktu.php';</script>";
}

$tahun_now = date("Y");
$result = $koneksi->query("SELECT * FROM rpd_setting_waktu ORDER BY tahun DESC");
?>

<main class="main-content">
    <div class="container-fluid">
        <h2 class="section-title text-center mb-4">🕓 Pengaturan Waktu Pengisian RPD</h2>
        <a href="rpd.php" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>

        <!-- Form Pengaturan -->
        <div class="card form-card">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tahun</label>
                        <input type="number" name="tahun" class="form-control" value="<?= $tahun_now ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="mulai" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="selesai" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">💾 Simpan Batas Waktu</button>
            </form>
        </div>

        <!-- Daftar Batas Waktu -->
        <div class="card table-card mt-4">
            <h5 class="mb-100px">📅 Daftar Batas Waktu Aktif</h5>
            <table class="table table-bordered mt-2 custom-table">
                <thead>
                    <tr>
                        <th class="batas">Tahun</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                     
                    </tr>
                </thead>
                <tbody>


                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['tahun'] ?></td>
                                <td><?= date('d-m-Y', strtotime($row['mulai'])) ?></td>
                                <td><?= date('d-m-Y', strtotime($row['selesai'])) ?></td>
                               
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada data batas waktu</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Tambahan CSS -->
<style>
    /* === Tambahan khusus jarak antar kolom === */
.custom-table {
  border-collapse: separate !important;
  border-spacing: 0 8px; /* jarak vertikal antar baris */
  width: 100%;
}

.custom-table th, 
.custom-table td {
  padding: 12px 24px; /* tambah padding kiri kanan */
  border: 1px solid #dee2e6;
  background: #fff;
}

.custom-table th {
  background-color: #007bff;
  color: #fff;
  font-weight: 600;
  text-align: center;
}

.custom-table tr td {
  background-color: #f8f9fa;
}

.custom-table tr:hover td {
  background-color: #eaf2ff; /* efek hover lembut */
}

    .batas {
        margin-left: 10px;
    }

    .main-content {
        padding: 30px;
        background-color: #f5f7fa;
        min-height: 100vh;
    }

    .section-title {
        font-size: 1.6rem;
        font-weight: 600;
        color: #2c3e50;
    }

    .card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        padding: 25px;
    }

    .form-card {
        max-width: 700px;
        margin: 0 auto;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
    }

    .form-group label {
        font-weight: 600;
        color: #34495e;
    }

    .form-control {
        border: 1px solid #ced4da;
        border-radius: 8px;
        padding: 8px 10px;
        width: 100%;
        transition: 0.2s;
    }

    .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 4px rgba(0, 123, 255, 0.25);
    }

    .btn-primary {
        background-color: #007bff;
        border: none;
        border-radius: 8px;
        padding: 10px;
        font-weight: 600;
        transition: background-color 0.3s;
    }

    .btn-primary:hover {
        background-color: #0069d9;
    }

    .table-card {
        max-width: 900px;
        margin: 0 auto;
        margin-top: 20px;
    }

    .table {
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }

    .table th,
    .table td {
        text-align: center;
        vertical-align: middle;
    }

    .table-header {


        margin-left: 100px;
    }

    .card-access-denied {
        max-width: 600px;
        margin: 100px auto;
        padding: 40px;
        text-align: center;
        border: 1px solid #f5c2c7;
        border-radius: 12px;
        background-color: #fff0f0;
        box-shadow: 0 0 10px rgba(255, 0, 0, 0.1);
    }

    .text-danger {
        color: #e74c3c !important;
    }
</style>

<?php include '../includes/footer.php'; ?>