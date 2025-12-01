<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil filter tanggal dari URL
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : '';
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : '';

// Bangun klausa WHERE
$where = "";
if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    $tgl_mulai = mysqli_real_escape_string($koneksi, $tgl_mulai);
    $tgl_selesai = mysqli_real_escape_string($koneksi, $tgl_selesai);
    $where = "WHERE ms.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
}

// Query utama — mempertahankan logic asli Anda
$query = "
    SELECT 
        MAX(ms.id) AS id,
        ms.tanggal,
        ms.keperluan,
        ms.jam_pergi,
        ms.jam_pulang,
        p2.nama AS nama_petugas,
        ms.foto,
        GROUP_CONCAT(p.nama ORDER BY p.nama SEPARATOR ', ') AS nama_pegawai
    FROM memo_satpam ms
    LEFT JOIN pegawai p ON FIND_IN_SET(p.id, ms.pegawai_id)
    LEFT JOIN pegawai p2 ON ms.petugas = p2.id
    $where
    GROUP BY ms.tanggal, ms.keperluan, p2.nama, ms.foto
    ORDER BY ms.tanggal DESC
";

$result = mysqli_query($koneksi, $query);
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
    padding: 15px 25px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 25px;
}

.memo-card {
    border-radius: 18px;
    background: #fff;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.memo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}
.memo-card .card-header {
    background: linear-gradient(90deg, #182c41ff, #174270ff);
    color: #fff;
    padding: 12px 20px;
    font-weight: 600;
}
.memo-card .card-body { padding: 25px; text-align: center; }
.memo-photo {
    width: 200px;
    height: 200px;
    border-radius: 12px;
    border: 3px solid #007bff;
    object-fit: cover;
    box-shadow: 0 3px 6px rgba(0,0,0,0.1);
}
.card-footer {
    background: #f1f3f5;
    padding: 10px 15px;
    border-top: 1px solid #ddd;
}
.badge { padding: 6px 10px; border-radius: 8px; font-size: 0.85rem; }

/* --- TAMBAHAN STYLE TOMBOL EDIT --- */
.btn-edit-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #ffc107; /* Kuning */
    color: #fff;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    border: 2px solid rgba(255,255,255,0.2);
    font-size: 12px;
    margin-left: 10px;
}
.btn-edit-circle:hover {
    background-color: #e0a800;
    color: #fff;
    transform: scale(1.1);
}

@media (max-width: 991px) {
    .content-wrapper { margin-left: 0 !important; padding: 20px; }
}
</style>

<div class="content-wrapper">
    <section class="content-header d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-door-open me-2"></i> Memo Keluar Kantor (Satpam)</h1>
        <div>
            <a href="tambah_memo_keluar.php" class="btn btn-primary me-2 shadow-sm">
                <i class="fas fa-plus"></i> Tambah Memo
            </a>
            <a href="../proses/proses_download_memo_excel.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" 
               class="btn btn-success shadow-sm">
                <i class="fas fa-file-excel"></i> Download Excel
            </a>
        </div>
    </section>

    <div class="filter-box mb-4">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-md-4">
                <label for="tgl_mulai" class="form-label fw-semibold">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control"
                       value="<?= htmlspecialchars($tgl_mulai) ?>">
            </div>
            <div class="col-md-4">
                <label for="tgl_selesai" class="form-label fw-semibold">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control"
                       value="<?= htmlspecialchars($tgl_selesai) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                <a href="memo_keluar_kantor.php" class="btn btn-secondary ms-2"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="grid-container">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <div class="card memo-card">
                            <!-- CARD HEADER dengan Tombol Edit -->
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div style="flex: 1; min-width: 0; margin-right: 10px;">
                                    <h5 class="mb-0 text-truncate" title="<?= htmlspecialchars($row['nama_pegawai']) ?>">
                                        <?= htmlspecialchars($row['nama_pegawai']) ?>
                                    </h5>
                                </div>
                                <div class="d-flex align-items-center">
                                    <small class="me-1" style="opacity: 0.9; white-space: nowrap;">
                                        <?= date('d M Y', strtotime($row['tanggal'])) ?>
                                    </small>
                                    
                                    <!-- TOMBOL EDIT -->
                                    <a href="edit_memo_keluar.php?id=<?= $row['id'] ?>" class="btn-edit-circle" title="Edit Data">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="card-body text-center">
                                <img src="<?= (!empty($row['foto']) && file_exists('../' . $row['foto'])) 
                                    ? '../' . htmlspecialchars($row['foto']) 
                                    : '../assets/img/no-image.png' ?>" 
                                    alt="Foto Memo" class="memo-photo mb-3">
                                
                                <p class="mb-1"><strong>Keperluan:</strong> <?= htmlspecialchars($row['keperluan']) ?></p>
                                <p class="mb-1"><strong>Petugas:</strong> <?= htmlspecialchars($row['nama_petugas'] ?? '-') ?></p>

                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span class="badge bg-success"><i class="far fa-clock me-1"></i> Pergi: <?= htmlspecialchars($row['jam_pergi']) ?></span>
                                <span class="badge bg-secondary"><i class="far fa-clock me-1"></i> Pulang: <?= htmlspecialchars($row['jam_pulang'] ?: '-') ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-muted mt-5">
                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                    <p>Tidak ada data memo pada rentang tanggal ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>