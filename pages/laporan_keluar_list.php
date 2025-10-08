<?php
session_start();
include '../includes/koneksi.php';

// Keamanan: Hanya admin yang boleh melihat daftar laporan ini
$user_roles = $_SESSION['user_role'] ?? [];

include '../includes/header.php';
include '../includes/sidebar.php';

// Logika untuk tombol tambah: Semua user yang login bisa menambah laporan mereka sendiri
$can_add_report = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// Ambil semua data laporan dari database, gabungkan dengan nama pegawai
$sql = "SELECT lk.id, lk.tanggal_laporan, lk.jam_laporan, p.nama AS nama_pegawai
        FROM laporan_keluar lk
        LEFT JOIN pegawai p ON lk.pegawai_id = p.id
        ORDER BY lk.tanggal_laporan DESC, lk.jam_laporan DESC";
$result = $koneksi->query($sql);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    :root { 
        --primary-color: #324057ff; 
        --background-color: #f9fafb; 
        --card-bg: #ffffff; 
        --border-color: #e5e7eb; 
        --text-dark: #1f2937;
        --text-medium: #6b7280;
    } 
    body { 
        font-family: 'Poppins', sans-serif; 
        background-color: var(--background-color); 
    }
    .header-content {
        background-color: var(--card-bg);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border-color);
    }
    .header-content h2 {
        font-weight: 600;
        font-size: 1.5rem;
        margin: 0;
    }
    .card { 
        border: none; 
        border-radius: 12px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
        overflow: hidden;
    }
    .table-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .table-header h5 {
        margin: 0;
        font-weight: 600;
    }
    .table thead th { 
        font-weight: 600; 
        color: var(--text-medium);
        background-color: #f9fafb;
        border-bottom-width: 1px;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 14px 20px !important; /* Tambah jarak antar kolom header */
    }
    .table td { 
        vertical-align: middle; 
        color: var(--text-dark);
        padding: 14px 20px !important; /* Tambah jarak antar kolom isi tabel */
    }
    .table tbody tr:hover { 
        background-color: #f9fafb; 
    }
    .btn-action-group .btn { 
        width: 38px; height: 38px; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center;
        border-radius: 8px;
    }
    .empty-state {
        padding: 4rem;
        text-align: center;
        color: var(--text-medium);
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    /* Tambahan opsional agar kolom tidak terlalu rapat */
    .table th, .table td {
        white-space: nowrap; /* Biar teks tidak turun ke bawah */
    }
    .table td:nth-child(1) { width: 5%; }
    .table td:nth-child(2) { width: 35%; }
    .table td:nth-child(3) { width: 20%; }
    .table td:nth-child(4) { width: 20%; }
    .table td:nth-child(5) { width: 20%; text-align: center; }
</style>

<main class="main-content">
<div class="header-content">
        <div class="d-flex justify-content-between align-items-center">
            <h2>Daftar Laporan Keluar</h2>
            <div class="d-flex gap-2">
 
                <a href="laporan_keluar.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Laporan
                </a>
    
                <a href="../proses/proses_download_laporan_keluar.php" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel me-2"></i>Download Excel
                </a>
            </div>
        </div>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">No.</th>
                                <th>Nama Pegawai</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php $nomor = 1; ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4"><?= $nomor++ ?></td>
                                        <td><?= htmlspecialchars($row['nama_pegawai'] ?? 'N/A') ?></td>
                                        <td><?= date('d M Y', strtotime($row['tanggal_laporan'])) ?></td>
                                        <td><?= date('H:i', strtotime($row['jam_laporan'])) ?> WIB</td>
                                        <td>
                                            <div class="btn-action-group">
                                                <a href="laporan_keluar_detail.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="bi bi-cloud-drizzle"></i>
                                            <p class="mb-0">Belum ada data laporan yang masuk.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
