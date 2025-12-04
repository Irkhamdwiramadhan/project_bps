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

// Ambil Data Session
$id_pegawai = $_SESSION['user_id'] ?? 0;
$user_roles = $_SESSION['user_role'] ?? [];

// 1. CEK ROLE ADMIN
$is_admin = in_array('super_admin', $user_roles) || in_array('admin_pegawai', $user_roles);

// 2. LOGIKA FILTER DEFAULT (Bulan & Tahun Saat Ini)
// Jika $_GET['bulan'] tidak ada, pakai date('n') (bulan sekarang 1-12)
$bulan_filter = isset($_GET['bulan']) && $_GET['bulan'] != "" ? (int)$_GET['bulan'] : (int)date('n');

// Jika $_GET['tahun'] tidak ada, pakai date('Y') (tahun sekarang)
$tahun_filter = isset($_GET['tahun']) && $_GET['tahun'] != "" ? (int)$_GET['tahun'] : (int)date('Y');

$nama_bulan = [
    1=>"Januari", 2=>"Februari", 3=>"Maret", 4=>"April", 5=>"Mei", 6=>"Juni",
    7=>"Juli", 8=>"Agustus", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Desember"
];

// ============================================================
// LOGIKA QUERY DATA TAHUN (Untuk Dropdown)
// ============================================================
// Mengambil daftar tahun yang tersedia di database kegiatan_harian
$query_tahun = "SELECT DISTINCT YEAR(tanggal) as tahun FROM kegiatan_harian ORDER BY tahun DESC";
$result_tahun = mysqli_query($koneksi, $query_tahun);
$available_years = [];

while ($row_t = mysqli_fetch_assoc($result_tahun)) {
    $available_years[] = $row_t['tahun'];
}

// Jika database kosong, setidaknya tampilkan tahun saat ini agar dropdown tidak error
if (empty($available_years)) {
    $available_years[] = date('Y');
}

// ============================================================
// LOGIKA QUERY DATA UTAMA
// ============================================================

// Base Condition
if ($is_admin) {
    $where_clause = "1=1"; // Admin lihat semua data
} else {
    $where_clause = "k.pegawai_id = '$id_pegawai'"; // Pegawai lihat sendiri
}

// Terapkan Filter Bulan & Tahun
$where_clause .= " AND MONTH(k.tanggal) = '$bulan_filter' AND YEAR(k.tanggal) = '$tahun_filter'";

// Query Data
$query = "SELECT k.*, p.nama AS nama_pegawai 
          FROM kegiatan_harian k
          JOIN pegawai p ON k.pegawai_id = p.id
          WHERE $where_clause
          ORDER BY k.tanggal DESC, k.jam_mulai DESC";

$result = mysqli_query($koneksi, $query);
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .content-wrapper { margin-left: 250px; padding: 30px; transition: 0.3s; }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }

    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .page-title h2 { font-weight: 800; color: #1f2937; margin: 0; }

    .filter-card {
        background: #fff; padding: 20px; border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px;
        border: 1px solid #e2e8f0; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;
    }

    .card-table {
        background: #fff; border-radius: 10px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb; overflow: hidden;
    }
    
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    thead { background-color: #f9fafb; border-bottom: 2px solid #e5e7eb; }
    th { padding: 15px; text-align: left; font-weight: 700; color: #374151; text-transform: uppercase; font-size: 0.8rem; }
    td { padding: 15px; border-bottom: 1px solid #f3f4f6; color: #4b5563; vertical-align: top; }
    tr:hover { background-color: #f8fafc; }
    
    .badge { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-bottom: 5px; }
    .bg-dinas { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
    .bg-rapat { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
    .bg-kantor { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
    .bg-lain { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }

    .btn-action-header { padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem; transition: all 0.2s; border: none; cursor: pointer; }
    .btn-add { background-color: #2563eb; color: white; }
    .btn-add:hover { background-color: #1d4ed8; }
    .btn-export { background-color: #10b981; color: white; }
    .btn-export:hover { background-color: #059669; }
    .btn-dark { background: #1f2937; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
    .btn-delete-sm { color: #dc2626; background: #fef2f2; padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; transition: 0.2s; border: 1px solid #fee2e2; display: inline-flex; align-items: center; gap: 5px; }
    .btn-delete-sm:hover { border-color: #dc2626; background: #dc2626; color: white; }
    .text-center { text-align: center; }
    .form-control, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; background-color: #fff; }
    .form-label { font-weight: 600; color: #6b7280; font-size: 0.8rem; margin-bottom: 5px; display: block; }

    @media (max-width: 768px) { .content-wrapper { margin-left: 0; padding: 15px; } .filter-card { flex-direction: column; align-items: stretch; } }
</style>

<div class="content-wrapper">
    
    <div class="page-header">
        <div class="page-title">
            <h2>
                Log Kegiatan Harian 
                <?php if($is_admin): ?>
                    <span style="font-size: 0.6em; background: #e0e7ff; color: #3730a3; padding: 4px 8px; border-radius: 4px; vertical-align: middle;">MODE ADMIN</span>
                <?php endif; ?>
            </h2>
            <p style="color:#6b7280; font-size:0.9rem; margin-top:5px;">
                <?= $is_admin ? "Monitoring seluruh aktivitas pegawai." : "Rekam jejak aktivitas harian Anda." ?>
            </p>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="../proses/export_excel_kegiatan_saya.php?export_all=true" target="_blank" class="btn-action-header btn-export">
                <i class="fas fa-file-excel"></i> Cetak Semua Data
            </a>

            <a href="tambah_kegiatan_saya.php" class="btn-action-header btn-add">
                <i class="fas fa-plus"></i> Tambah
            </a>
        </div>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-success" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($_GET['message'] ?? 'Aksi berhasil.') ?>
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
                <?php 
                // REVISI: Menggunakan data tahun dari database
                foreach($available_years as $y): 
                    $sel = ($y == $tahun_filter) ? 'selected' : '';
                ?>
                    <option value="<?= $y ?>" <?= $sel ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="width: auto;">
            <button type="submit" class="btn-dark">
                <i class="fas fa-filter"></i> Tampilkan
            </button>
        </div>
    </form>

    <div class="card-table">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="20%">Nama Pegawai</th>
                        <th width="15%">Tanggal</th>
                        <th>Jenis & Uraian Kegiatan</th>
                        <th width="10%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $no = 1; while ($row = $result->fetch_assoc()): 
                            $jenis = strtolower($row['jenis_kegiatan']);
                            $bgClass = 'bg-lain';
                            if (strpos($jenis, 'dinas') !== false) $bgClass = 'bg-dinas';
                            elseif (strpos($jenis, 'rapat') !== false) $bgClass = 'bg-rapat';
                            elseif (strpos($jenis, 'kantor') !== false) $bgClass = 'bg-kantor';
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td style="font-weight: 600; color: #111827;">
                                <?= htmlspecialchars($row['nama_pegawai']) ?>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: #374151;">
                                    <?= date('d M Y', strtotime($row['tanggal'])) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $bgClass ?>"><?= htmlspecialchars($row['jenis_kegiatan']) ?></span>
                                <div style="margin-top: 8px; font-size: 0.9rem; color: #4b5563; line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($row['uraian'])) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($is_admin || $row['pegawai_id'] == $id_pegawai): ?>
                                    <a href="../proses/proses_hapus_kegiatan_saya.php?id=<?= $row['id'] ?>" 
                                       class="btn-delete-sm" 
                                       onclick="return confirm('Yakin ingin menghapus kegiatan ini?');"
                                       title="Hapus">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <p style="color: #6b7280; margin:0; font-weight: 500;">Tidak ada kegiatan pada bulan ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php 
include '../includes/footer.php'; 
?>