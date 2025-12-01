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

// Ambil ID Pegawai dari Session
$id_pegawai = $_SESSION['user_id'] ?? 0; 

// Filter Bulan & Tahun
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Query Data (Diupdate dengan JOIN untuk mengambil Nama Pegawai)
$query = "SELECT k.*, p.nama AS nama_pegawai 
          FROM kegiatan_harian k
          JOIN pegawai p ON k.pegawai_id = p.id
          WHERE k.pegawai_id = '$id_pegawai' 
          AND MONTH(k.tanggal) = '$bulan' AND YEAR(k.tanggal) = '$tahun'
          ORDER BY k.tanggal DESC, k.jam_mulai DESC";

$result = mysqli_query($koneksi, $query);
?>

<style>
    body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
    .content-wrapper { margin-left: 250px; padding: 30px; transition: 0.3s; }
    body.sidebar-collapse .content-wrapper { margin-left: 80px; }

    /* Header */
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .page-title h2 { font-weight: 800; color: #1f2937; margin: 0; }

    /* Filter Box */
    .filter-box {
        background: #fff; padding: 15px; border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;
        margin-bottom: 20px; display: flex; gap: 10px; align-items: center;
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

    /* Tombol Edit Kecil */
    .btn-edit-sm {
        color: #2563eb; background: #eff6ff; padding: 5px 10px;
        border-radius: 6px; font-size: 0.8rem; text-decoration: none;
        transition: 0.2s; border: 1px solid transparent;
    }
    .btn-edit-sm:hover { border-color: #2563eb; background: #2563eb; color: white; }

    @media (max-width: 768px) {
        .content-wrapper { margin-left: 0; padding: 15px; }
    }
</style>

<div class="content-wrapper">
    
    <div class="page-header">
    <div class="page-title">
        <h2>Log Kegiatan Harian</h2>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <a href="../proses/export_excel_kegiatan_saya.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn btn-success" style="background-color: #10b981; border:none; padding: 10px 20px; border-radius: 8px; color: white; text-decoration: none; font-weight: 600;">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>

        <a href="tambah_kegiatan_saya.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Tambah
        </a>
    </div>
</div>

    <form method="GET" class="filter-box">
        <select name="bulan" class="form-control" style="width: auto;">
            <?php for($i=1; $i<=12; $i++): ?>
                <option value="<?= $i ?>" <?= $i==$bulan ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$i,10)) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" style="width: 100px;">
        <button type="submit" class="btn btn-dark"><i class="fas fa-search"></i></button>
    </form>

    <div class="card-table">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th>Nama Pegawai</th>
                        <th width="15%">Tanggal</th>
                        <th>Jenis Kegiatan</th>
                     
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): 
                            // Styling Badge
                            $jenis = strtolower($row['jenis_kegiatan']);
                            $bgClass = 'bg-lain';
                            if (strpos($jenis, 'dinas') !== false) $bgClass = 'bg-dinas';
                            elseif (strpos($jenis, 'rapat') !== false) $bgClass = 'bg-rapat';
                            elseif (strpos($jenis, 'kantor') !== false) $bgClass = 'bg-kantor';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td style="font-weight: 600; color: #111827;"><?= htmlspecialchars($row['nama_pegawai']) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($row['tanggal'])) ?> <br>
                               
                            </td>
                            <td>
                                <span class="badge <?= $bgClass ?>"><?= htmlspecialchars($row['jenis_kegiatan']) ?></span>
                                <div style="margin-top: 5px; font-size: 0.85rem; color: #4b5563;">
                                    <?= htmlspecialchars($row['uraian']) ?>
                                </div>
                            </td>
                           
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px;">
                                <p style="color: #6b7280; margin:0;">Tidak ada data kegiatan pada periode ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>