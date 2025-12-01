<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../../login.php');
    exit;
}

// Logika Filter (Revisi: Default Tampilkan Semua jika tidak ada filter)
$where_clause = "1=1"; // Default benar semua
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : '';
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : '';

if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    $where_clause = "tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
}

// Query Data
$query = "
    SELECT * FROM kegiatan_pegawai 
    WHERE $where_clause
    ORDER BY tanggal DESC, waktu_mulai DESC
";

$result = mysqli_query($koneksi, $query);
?>

<style>
    /* Global Font & Background */
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .content-wrapper { min-height: 100vh; margin-left: 250px; padding: 30px; transition: 0.3s; }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }

    /* Header Section (Flexbox untuk Judul & Tombol) */
    .page-header {
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-title h2 { font-weight: 800; color: #1f2937; margin: 0; font-size: 1.8rem; }
    .page-title p { color: #6b7280; margin: 5px 0 0; font-size: 0.9rem; }
    
    /* Tombol Aksi di Header */
    .header-actions { display: flex; gap: 10px; }
    .btn-action-header {
        padding: 10px 20px; border-radius: 8px; font-weight: 600; 
        text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        font-size: 0.9rem; transition: all 0.2s; border: none; cursor: pointer;
    }
    .btn-add { background-color: #2563eb; color: white; }
    .btn-add:hover { background-color: #1d4ed8; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
    
    .btn-export { background-color: #10b981; color: white; }
    .btn-export:hover { background-color: #059669; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }

    /* Filter Card */
    .filter-card {
        background: #fff; padding: 20px; border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
        border: 1px solid #e2e8f0;
    }

    /* Table Styling */
    .card-table {
        background: #ffffff; border-radius: 12px; overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
    }
    .table-responsive { overflow-x: auto; }
    .table-custom { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    
    .table-custom th {
        background-color: #f9fafb; color: #4b5563; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8rem;
        padding: 15px; text-align: left; border-bottom: 2px solid #e2e8f0;
    }
    
    .table-custom td {
        padding: 15px; vertical-align: middle; border-bottom: 1px solid #f3f4f6;
        color: #334155;
    }
    .table-custom tr:last-child td { border-bottom: none; }
    .table-custom tr:hover { background-color: #f8fafc; }

    /* Badge */
    .badge-jenis {
        padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; 
        text-transform: uppercase; display: inline-block;
    }
    .bg-dinas { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
    .bg-lokal { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
    .bg-cuti { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
    .bg-rapat { background: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }
    .bg-lain { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
    
    /* Info Tim & Peserta */
    .info-sub { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 5px; margin-top: 4px; }

    @media (max-width: 768px) { 
        .content-wrapper { margin-left: 0; padding: 15px; } 
        .header-actions { width: 100%; flex-direction: column; }
        .btn-action-header { width: 100%; justify-content: center; }
    }
</style>

<div class="content-wrapper">
    
    <div class="page-header">
        <div class="page-title">
            <h2>Kegiatan Pegawai</h2>
            <p>Rekapitulasi aktivitas harian tim organik BPS.</p>
        </div>
        <div class="header-actions">
            <a href="../proses/proses_export_excel_kegiatan_pegawai.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" target="_blank" class="btn-action-header btn-export">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="tambah_kegiatan_pegawai.php" class="btn-action-header btn-add">
                <i class="fas fa-plus"></i> Tambah Kegiatan
            </a>
        </div>
    </div>

    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-secondary">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-secondary">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100" style="padding: 10px;">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="kegiatan_pegawai.php" class="btn btn-outline-secondary w-100" style="padding: 10px;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="card-table">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="35%">Aktivitas</th>
                        <th width="15%">Waktu</th>
                        <th width="20%">Lokasi & Tim</th>
                        <th width="25%">Peserta Lain</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php 
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)): 
                            // Warna Badge
                            $jenis = strtolower($row['jenis_aktivitas']);
                            $badgeClass = 'bg-lain';
                            if (strpos($jenis, 'dinas') !== false) $badgeClass = 'bg-dinas';
                            elseif (strpos($jenis, 'lokal') !== false) $badgeClass = 'bg-lokal';
                            elseif (strpos($jenis, 'cuti') !== false) $badgeClass = 'bg-cuti';
                            elseif (strpos($jenis, 'rapat') !== false) $badgeClass = 'bg-rapat';

                            // Jam
                            $jam_mulai = date('H:i', strtotime($row['waktu_mulai']));
                            $jam_selesai = ($row['waktu_selesai'] == 'Selesai') ? 'Selesai' : date('H:i', strtotime($row['waktu_selesai']));
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <div style="font-weight: 700; color: #111827; font-size: 0.95rem; margin-bottom: 5px;">
                                    <?= htmlspecialchars($row['aktivitas']) ?>
                                </div>
                                <span class="badge-jenis <?= $badgeClass ?>"><?= htmlspecialchars($row['jenis_aktivitas']) ?></span>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                <div class="text-muted small mt-1">
                                    <i class="far fa-clock me-1"></i> <?= $jam_mulai ?> - <?= $jam_selesai ?>
                                </div>
                            </td>
                            <td>
                                <div class="mb-1">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($row['tempat']) ?>
                                </div>
                                <?php if (!empty($row['tim_kerja_id'])): ?>
                                <div class="info-sub">
                                    <i class="fas fa-users text-primary"></i> <?= htmlspecialchars($row['tim_kerja_id']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['peserta_ids'])): ?>
                                    <div class="info-sub" style="align-items: flex-start;">
                                        <i class="fas fa-user-friends text-success mt-1"></i> 
                                        <span><?= htmlspecialchars($row['peserta_ids']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div style="opacity: 0.5;">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                                    <h6 class="text-secondary">Tidak ada data kegiatan ditemukan.</h6>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>