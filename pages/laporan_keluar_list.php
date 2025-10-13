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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
:root {
    --primary: #324057;
    --light: #f5f6fa;
    --text-dark: #1f2937;
    --text-muted: #6b7280;
    --card-bg: #fff;
    --border: #e5e7eb;
}
body {
    font-family: 'Poppins', sans-serif;
    background: var(--light);
    color: var(--text-dark);
}
.content-wrapper {
    margin-left: 250px;
    padding: 30px;
}
h2 {
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--primary);
}
.filter-form {
    background: var(--card-bg);
    padding: 18px 22px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}
.filter-form .form-label {
    font-weight: 500;
    color: var(--text-dark);
}
.filter-form input[type="date"] {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 10px;
}
.filter-form button {
    background-color: var(--primary);
    border: none;
    color: #fff;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: 0.3s;
}
.filter-form button:hover {
    background-color: #243042;
}
.card-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}
.card-item {
    background: var(--card-bg);
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}
.card-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}
.card-img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: #f0f0f0;
}
.card-body {
    padding: 16px 18px;
}
.card-body h5 {
    font-size: 1.05rem;
    font-weight: 600;
    margin-bottom: 6px;
}
.card-body p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-muted);
}
.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    border-top: 1px solid var(--border);
    background: #fafafa;
}
.btn-detail {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.9rem;
    text-decoration: none;
    transition: background 0.3s;
}
.btn-detail:hover {
    background: #243042;
}
.no-data {
    text-align: center;
    color: var(--text-muted);
    padding: 40px;
}
</style>

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clipboard-data me-2"></i> Daftar Laporan Keluar</h2>
        <div>
            <a href="laporan_keluar.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle me-1"></i>Tambah Laporan</a>
            <a href="../proses/proses_download_laporan_keluar.php" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Download Excel</a>
        </div>
    </div>

    <!-- Filter tanggal -->
    <form method="GET" class="filter-form row g-3 align-items-end">
        <div class="col-md-4">
            <label for="tgl_mulai" class="form-label">Dari Tanggal</label>
            <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tgl_mulai) ?>">
        </div>
        <div class="col-md-4">
            <label for="tgl_selesai" class="form-label">Sampai Tanggal</label>
            <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control" value="<?= htmlspecialchars($tgl_selesai) ?>">
        </div>
        <div class="col-md-4">
            <button type="submit"><i class="bi bi-filter-circle me-1"></i> Filter</button>
            <a href="laporan_keluar_list.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-repeat me-1"></i> Reset</a>
        </div>
    </form>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="card-container">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="card-item">
                    <?php if (!empty($row['foto']) && file_exists("../" . $row['foto'])): ?>
                        <img src="../<?= htmlspecialchars($row['foto']) ?>" class="card-img" alt="Foto Laporan">
                    <?php else: ?>
                        <img src="../assets/img/no-image.png" class="card-img" alt="No Image">
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5><?= htmlspecialchars($row['nama_pegawai'] ?? 'N/A') ?></h5>
                        <p><i class="bi bi-calendar-check me-1"></i> <?= date('d M Y', strtotime($row['tanggal_laporan'])) ?></p>
                        <p><i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($row['jam_laporan'])) ?> WIB</p>
                    </div>
                    
                    <div class="card-footer">
                        <small class="text-muted">ID Laporan: <?= $row['id'] ?></small>
                        <a href="laporan_keluar_detail.php?id=<?= $row['id'] ?>" class="btn-detail">
                            <i class="bi bi-eye-fill me-1"></i> Detail
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="bi bi-inbox display-6 mb-2"></i>
            <p>Belum ada laporan untuk rentang tanggal ini.</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
