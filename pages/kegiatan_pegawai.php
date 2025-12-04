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

// 1. CEK ROLE ADMIN
$user_roles = $_SESSION['user_role'] ?? [];
$is_admin = in_array('super_admin', $user_roles) || in_array('admin_pegawai', $user_roles);

// ============================================================
// LOGIKA FILTER (Bulan & Tahun) - Disamakan dengan kegiatan_saya
// ============================================================

// Default: Bulan & Tahun Saat Ini
$bulan_filter = isset($_GET['bulan']) && $_GET['bulan'] != "" ? (int)$_GET['bulan'] : (int)date('n');
$tahun_filter = isset($_GET['tahun']) && $_GET['tahun'] != "" ? (int)$_GET['tahun'] : (int)date('Y');

$nama_bulan = [
    1=>"Januari", 2=>"Februari", 3=>"Maret", 4=>"April", 5=>"Mei", 6=>"Juni",
    7=>"Juli", 8=>"Agustus", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Desember"
];

// Ambil Tahun yang tersedia di database (Tabel kegiatan_pegawai)
$query_tahun = "SELECT DISTINCT YEAR(tanggal) as tahun FROM kegiatan_pegawai ORDER BY tahun DESC";
$result_tahun = mysqli_query($koneksi, $query_tahun);
$available_years = [];

while ($row_t = mysqli_fetch_assoc($result_tahun)) {
    $available_years[] = $row_t['tahun'];
}
// Fallback jika data kosong
if (empty($available_years)) {
    $available_years[] = date('Y');
}

// ============================================================
// QUERY DATA UTAMA
// ============================================================

// Filter berdasarkan Bulan dan Tahun yang dipilih
$where_clause = "MONTH(tanggal) = '$bulan_filter' AND YEAR(tanggal) = '$tahun_filter'";

$query = "SELECT * FROM kegiatan_pegawai 
          WHERE $where_clause
          ORDER BY tanggal DESC, waktu_mulai DESC";

$result = mysqli_query($koneksi, $query);
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .content-wrapper { margin-left: 250px; padding: 30px; transition: 0.3s; }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }

    /* Header */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .page-title h2 { font-weight: 800; color: #1f2937; margin: 0; }

    /* Filter Box */
    .filter-card {
        background: #fff; padding: 20px; border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
        border: 1px solid #e2e8f0; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;
    }

    /* Table Styling */
    .card-table {
        background: #fff; border-radius: 10px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb; overflow: hidden;
    }
    
    .table-responsive { overflow-x: auto; }
    
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    
    thead { background-color: #f9fafb; border-bottom: 2px solid #e5e7eb; }
    
    th { 
        padding: 12px 15px; text-align: left; font-weight: 600; 
        color: #374151; text-transform: uppercase; font-size: 0.8rem; 
    }
    
    td { 
        padding: 12px 15px; border-bottom: 1px solid #f3f4f6; 
        color: #4b5563; vertical-align: middle;
    }
    
    tr:hover { background-color: #f8fafc; }
    
    /* Badge Jenis Kegiatan */
    .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
    .bg-dinas { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
    .bg-rapat { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .bg-kantor { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
    .bg-lain { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }

    /* Tombol Aksi */
    .btn-action-sm {
        padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; 
        text-decoration: none; transition: 0.2s; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-delete { color: #dc2626; background: #fef2f2; border-color: #fee2e2; }
    .btn-delete:hover { background: #dc2626; color: white; border-color: #dc2626; }

    /* Tombol Header */
    .btn-action-header {
        padding: 10px 20px; border-radius: 8px; font-weight: 600; 
        text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        font-size: 0.9rem; transition: all 0.2s; border: none; cursor: pointer;
    }
    .btn-add { background-color: #2563eb; color: white; }
    .btn-add:hover { background-color: #1d4ed8; }
    .btn-export { background-color: #10b981; color: white; }
    .btn-export:hover { background-color: #059669; }

    /* Form Control */
    .form-control, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; background: #fff; }
    .form-label { font-weight: 600; color: #6b7280; font-size: 0.8rem; margin-bottom: 5px; display: block; }
    .btn-dark { background: #1f2937; color: white; border: none; padding: 9px 15px; border-radius: 6px; cursor: pointer; font-weight: 600; }
    
    /* Pesan Alert */
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    /* Info Sub */
    .info-sub { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 5px; margin-top: 4px; }

    @media (max-width: 768px) {
        .content-wrapper { margin-left: 0; padding: 15px; }
        .filter-card { flex-direction: column; align-items: stretch; }
    }
</style>

<div class="content-wrapper">
    
    <div class="page-header">
        <div class="page-title">
            <h2>Jadwal Kegiatan & Meeting</h2>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="../proses/proses_export_excel_kegiatan_pegawai.php?bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>" target="_blank" class="btn-action-header btn-export">
                <i class="fas fa-file-excel"></i> Export
            </a>

            <a href="tambah_kegiatan_pegawai.php" class="btn-action-header btn-add">
                <i class="fas fa-plus me-2"></i> Tambah
            </a>
        </div>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert <?= $_GET['status'] == 'success' ? 'alert-success' : 'alert-error' ?>">
            <?= htmlspecialchars($_GET['message']) ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="filter-card">
        <div style="flex: 1; min-width: 150px;">
            <label class="form-label">Bulan</label>
            <select name="bulan" class="form-select">
                <?php foreach($nama_bulan as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($k == $bulan_filter) ? 'selected' : '' ?>>
                        <?= $v ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 120px;">
            <label class="form-label">Tahun</label>
            <select name="tahun" class="form-select">
                <?php foreach($available_years as $y): ?>
                    <option value="<?= $y ?>" <?= ($y == $tahun_filter) ? 'selected' : '' ?>>
                        <?= $y ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="width: auto;">
            <button type="submit" class="btn-dark">
                <i class="fas fa-filter me-2"></i> Tampilkan
            </button>
        </div>
    </form>

    <div class="card-table">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="30%">Aktivitas</th>
                        <th width="15%">Waktu</th>
                        <th width="20%">Lokasi & Tim</th>
                        <th width="20%">Peserta</th>
                        <?php if ($is_admin): ?>
                            <th width="10%" style="text-align: center;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): 
                            $jenis = strtolower($row['jenis_aktivitas']);
                            $bgClass = 'bg-lain';
                            if (strpos($jenis, 'dinas') !== false) $bgClass = 'bg-dinas';
                            elseif (strpos($jenis, 'rapat') !== false) $bgClass = 'bg-rapat';
                            elseif (strpos($jenis, 'kantor') !== false) $bgClass = 'bg-kantor';
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td>
                                <div style="font-weight: 700; color: #111827; font-size: 0.95rem; margin-bottom: 5px;">
                                    <?= htmlspecialchars($row['aktivitas']) ?>
                                </div>
                                <span class="badge <?= $bgClass ?>"><?= htmlspecialchars($row['jenis_aktivitas']) ?></span>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= date('d M Y', strtotime($row['tanggal'])) ?></div>
                                <div class="text-muted small mt-1">
                                    <i class="far fa-clock me-1"></i> 
                                    <?= date('H:i', strtotime($row['waktu_mulai'])) ?> - 
                                    <?= ($row['waktu_selesai'] == 'Selesai') ? 'Selesai' : date('H:i', strtotime($row['waktu_selesai'])) ?>
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
                                        <i class="fas fa-user-friends text-success mt-1" style="margin-right: 5px;"></i> 
                                        <span>
                                            <strong>(<?= htmlspecialchars($row['jumlah_peserta']) ?> Orang)</strong><br>
                                            <?= htmlspecialchars($row['peserta_ids']) ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($is_admin): ?>
                                <td style="text-align: center;">
                                    <a href="../proses/proses_hapus_kegiatan_pegawai.php?id=<?= $row['id'] ?>" 
                                       class="btn-action-sm btn-delete" 
                                       onclick="return confirm('Yakin ingin menghapus kegiatan ini? Data yang dihapus tidak dapat dikembalikan.');"
                                       title="Hapus Kegiatan">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ($is_admin) ? 6 : 5 ?>" style="text-align: center; padding: 40px;">
                                <p style="color: #6b7280; margin:0;">Tidak ada jadwal kegiatan pada bulan ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>