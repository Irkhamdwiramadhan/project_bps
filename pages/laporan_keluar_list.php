<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil filter tanggal dari form
$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

// Query dasar
$sql = "SELECT lk.id, lk.tanggal_laporan, lk.jam_laporan, p.nama AS nama_pegawai, lk.foto 
        FROM laporan_keluar lk
        LEFT JOIN pegawai p ON lk.pegawai_id = p.id";

// Jika ada filter tanggal
if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    $sql .= " WHERE DATE(lk.tanggal_laporan) BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
}

$sql .= " ORDER BY lk.tanggal_laporan DESC, lk.jam_laporan DESC";
$result = $koneksi->query($sql);
?>

<style>
body {
    background-color: #f4f6f9;
    font-family: 'Poppins', sans-serif;
    overflow-x: hidden;
}
.content-wrapper {
    min-height: 100vh;
    background-color: #f4f6f9;
    margin-left: 250px;
    padding: 40px;
    transition: all 0.3s ease-in-out;
}
body.sidebar-collapse .content-wrapper { margin-left: 80px; }

.content-header h1 {
    font-weight: 600;
    color: #333;
}
.btn-primary {
    background-color: #1b3857ff;
    border: none;
    border-radius: 10px;
    transition: 0.3s;
}
.btn-primary:hover { background-color: #1e3957ff; transform: scale(1.03); }
.btn-success {
    border-radius: 10px;
    transition: 0.3s;
}
.btn-success:hover { transform: scale(1.03); }

.filter-box {
    background: #fff;
    border-radius: 12px;
    padding: 20px 25px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}
.filter-box .form-label { font-weight: 500; }

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 25px;
}

.card-item {
    border-radius: 18px;
    background: #fff;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.card-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.card-item img {
    width: 100%;
    height: 220px;
    object-fit: cover;
}
.card-body {
    padding: 20px;
}
.card-body h5 {
    font-weight: 600;
    color: #1b3857ff;
}
.card-body p {
    margin: 0;
    color: #555;
    font-size: 0.9rem;
}
.card-footer {
    background: #f1f3f5;
    padding: 10px 15px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.btn-detail {
    background: #1b3857ff;
    color: #fff;
    border: none;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 0.9rem;
    text-decoration: none;
    transition: 0.3s;
}
.btn-detail:hover {
    background: #243042;
    transform: scale(1.03);
}
.no-data {
    text-align: center;
    color: #6c757d;
    padding: 40px;
}

@media (max-width: 991px) {
    .content-wrapper { margin-left: 0 !important; padding: 20px; }
}
</style>

<div class="content-wrapper">
    <section class="content-header d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-walking me-2"></i> Daftar Laporan Keluar (Pegawai)</h1>
        <div>
            <a href="laporan_keluar_add.php" class="btn btn-primary me-2 shadow-sm">
                <i class="fas fa-plus"></i> Tambah Laporan
            </a>
            <a href="../proses/proses_download_laporan_keluar.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>"
               class="btn btn-success shadow-sm">
                <i class="fas fa-file-excel"></i> Download Excel
            </a>
        </div>
    </section>

    <!-- Filter -->
    <div class="filter-box mb-4">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-md-4">
                <label for="tgl_mulai" class="form-label">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control"
                       value="<?= htmlspecialchars($tgl_mulai) ?>">
            </div>
            <div class="col-md-4">
                <label for="tgl_selesai" class="form-label">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control"
                       value="<?= htmlspecialchars($tgl_selesai) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                <a href="laporan_keluar_list.php" class="btn btn-secondary ms-2"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Daftar laporan -->
    <section class="content">
        <div class="container-fluid">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="grid-container">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="card-item">
                            <?php if (!empty($row['foto']) && file_exists("../" . $row['foto'])): ?>
                                <img src="../<?= htmlspecialchars($row['foto']) ?>" alt="Foto Laporan">
                            <?php else: ?>
                                <img src="../assets/img/no-image.png" alt="No Image">
                            <?php endif; ?>

                            <div class="card-body">
                                <h5><?= htmlspecialchars($row['nama_pegawai'] ?? 'N/A') ?></h5>
                                <p><i class="fas fa-calendar-day me-1"></i> <?= date('d M Y', strtotime($row['tanggal_laporan'])) ?></p>
                                <p><i class="fas fa-clock me-1"></i> <?= date('H:i', strtotime($row['jam_laporan'])) ?> WIB</p>
                            </div>

                            <div class="card-footer">
                                <small class="text-muted">ID Laporan: <?= $row['id'] ?></small>
                                <a href="laporan_keluar_detail.php?id=<?= $row['id'] ?>" class="btn-detail">
                                    <i class="fas fa-eye me-1"></i> Detail
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada laporan untuk rentang tanggal ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
