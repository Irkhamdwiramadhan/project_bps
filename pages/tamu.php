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

// Filter tanggal
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$where_clause = $filter_tanggal ? "WHERE tanggal = '$filter_tanggal'" : "";
$query = "SELECT * FROM tamu $where_clause ORDER BY tanggal DESC";
$result = mysqli_query($koneksi, $query);
?>

<style>
/* ======= TAMU PAGE STYLING ======= */
body {
    background-color: #f4f6f9;
    font-family: 'Poppins', sans-serif;
    overflow-x: hidden;
}

/* Agar tidak tertutup sidebar */
.content-wrapper {
    min-height: 100vh;
    background-color: #f4f6f9;
    margin-left: 250px;
    padding: 40px;
    transition: all 0.3s ease-in-out;
}

body.sidebar-collapse .content-wrapper {
    margin-left: 80px;
}

/* Header */
.content-header h1 {
    font-weight: 600;
    color: #333;
}

.btn-primary {
    background-color: #13304eff;
    border: none;
    border-radius: 10px;
    transition: 0.3s;
}

.btn-primary:hover {
    background-color: #1a3f66ff;
    transform: scale(1.03);
}

/* Filter */
.filter-form {
    margin-bottom: 25px;
}

.filter-form input[type="date"] {
    border-radius: 8px;
    border: 1px solid #ccc;
    padding: 8px 12px;
}

/* ===== Card Layout ===== */
.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 25px;
}

.tamu-card {
    border: none;
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.tamu-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.tamu-card .card-header {
    background: linear-gradient(90deg, #0f2236ff, #0f3c6dff);
    color: #fff;
    padding: 10px 20px; /* Sedikit diperbesar agar tombol muat */
    font-weight: 900;
}

.tamu-card .card-body {
    padding: 25px;
}

.tamu-photo {
    width: 230px;
    height: 200px;
    object-fit: cover;
    border-radius: 15px;
    border: 3px solid #007bff;
    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
}

.keperluan-text {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 10px;
    color: #333;
    font-size: 0.95rem;
}

.card-footer {
    background: #f1f3f5;
    padding: 12px 15px;
    font-size: 0.9rem;
    border-top: 1px solid #e2e2e2;
}

.badge {
    padding: 8px 12px;
    font-size: 0.85rem;
    border-radius: 10px;
}

/* Tombol Edit Bulat */
.btn-edit-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #ffc107;
    color: #fff;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.btn-edit-circle:hover {
    background-color: #e0a800;
    color: #fff;
    transform: scale(1.1);
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
    <section class="content-header d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-user-friends me-2"></i> Daftar Tamu</h1>
        <div>
            <a href="tambah_tamu.php" class="btn btn-primary shadow-sm me-2">
                <i class="fas fa-plus"></i> Tambah Tamu
            </a>
            <a href="../proses/proses_download_tamu.php<?= $filter_tanggal ? '?tanggal=' . urlencode($filter_tanggal) : '' ?>" 
               class="btn btn-success btn-sm shadow-sm" style="padding: 10px 15px; border-radius: 10px;">
                <i class="fas fa-file-excel"></i> Excel
            </a>
        </div>
    </section>

    <!-- Filter Tanggal -->
    <form method="GET" class="filter-form d-flex align-items-center gap-2 mb-4">
        <label for="tanggal" class="fw-semibold me-2">Filter Tanggal:</label>
        <input type="date" name="tanggal" id="tanggal" value="<?= htmlspecialchars($filter_tanggal) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        <?php if ($filter_tanggal): ?>
            <a href="tamu.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <section class="content">
        <div class="container-fluid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="grid-container">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <div class="card tamu-card">
                            
                            <!-- Header dengan Nama, Tanggal, dan Tombol Edit -->
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div style="flex: 1; min-width: 0; margin-right: 10px;">
                                    <h5 class="mb-0 text-truncate" title="<?= htmlspecialchars($row['nama']) ?>">
                                        <?= htmlspecialchars($row['nama']) ?>
                                    </h5>
                                </div>
                                <div class="d-flex align-items-center">
                                             <small><?= date('d M Y', strtotime($row['tanggal'])) ?></small>
                                    
                                    <!-- TOMBOL EDIT -->
                                    <a href="edit_tamu.php?id=<?= $row['id'] ?>" class="btn-edit-circle" title="Edit Data">
                                        <i class="fas fa-pen" style="font-size: 12px;"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="card-body text-center">
                                <img src="<?= (!empty($row['foto']) && file_exists('../' . $row['foto'])) 
                                    ? '../' . htmlspecialchars($row['foto']) 
                                    : '../assets/img/no-image.png' ?>" 
                                    alt="Foto Tamu" class="tamu-photo mb-3">

                                <p class="mb-1"><strong>Asal:</strong> <?= htmlspecialchars($row['asal']) ?></p>
                                <p class="mb-1"><strong>Petugas:</strong> <?= htmlspecialchars($row['petugas']) ?></p>
                                <p class="mb-2 mt-2"><strong>Keperluan:</strong></p>
                                <p class="keperluan-text"><?= htmlspecialchars($row['keperluan']) ?></p>
                            </div>

                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span class="badge bg-success"><i class="far fa-clock me-1"></i> Datang: <?= htmlspecialchars($row['jam_datang']) ?></span>
                                <span class="badge bg-secondary"><i class="far fa-clock me-1"></i> Pulang: <?= htmlspecialchars($row['jam_pulang'] ?? '-') ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted mt-5">
                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                    <p>Belum ada data tamu yang tercatat<?= $filter_tanggal ? " pada tanggal " . htmlspecialchars($filter_tanggal) : "" ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>