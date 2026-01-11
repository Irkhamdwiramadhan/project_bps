<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. Cek Akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'ketua_tim'];
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}

// 2. Ambil Parameter Filter
// 1. Ambil Parameter Filter
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_tim   = $_GET['tim'] ?? ''; 

// 2. Susun WHERE Clause
$conditions = [];

// Filter Wajib: Hanya Tim Aktif
// Pastikan di query utama nanti ada: JOIN tim t ON ...
$conditions[] = "t.is_active = 1"; 

if (!empty($filter_bulan)) {
    $conditions[] = "MONTH(k.batas_waktu) = " . intval($filter_bulan);
}
if (!empty($filter_tahun)) {
    $conditions[] = "YEAR(k.batas_waktu) = " . intval($filter_tahun);
}
if (!empty($filter_tim)) {
    $conditions[] = "k.tim_id = " . intval($filter_tim);
}

// Gabungkan kondisi
$where_clause = "";
if (count($conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Ambil Daftar Tim (Hanya yang Aktif)
$sql_tim = "SELECT id, nama_tim FROM tim WHERE is_active = 1 ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
?>

<style>
/* ... (Style CSS Anda tetap sama, tidak perlu diubah) ... */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
:root { --primary-color: #4A90E2; --background-color: #f4f7f9; --card-bg-color: #ffffff; --border-color: #e2e8f0; --shadow-color: rgba(0, 0, 0, 0.05); }
body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); }
.header-content { background-color: var(--card-bg-color); padding: 20px 30px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
.header-content h2 { font-weight: 600; margin-bottom: 0; }
.card { border: none; border-radius: 12px; box-shadow: 0 4px 12px var(--shadow-color); overflow: hidden; }
.filter-container { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
.filter-container form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
.form-select { border-radius: 8px; padding: 0.5rem 1rem; border: 1px solid #ced4da; }
.table-responsive { width: 100%; overflow-x: auto; scrollbar-width: thin; }
.table { width: 100%; border-collapse: collapse; }
.table thead { background-color: #f8fafc; }
.table th { font-weight: 600; color: #475569; border-bottom: 2px solid var(--border-color); padding: 1rem 1.25rem; font-size: 0.9rem; white-space: nowrap; }
.table td { padding: 1rem 1.25rem; vertical-align: middle; border-top: 1px solid var(--border-color); font-size: 0.9rem; }
.table tbody tr:hover { background-color: #f1f5f9; }
/* Update CSS untuk Tombol Aksi */
.btn-action-group {
    display: flex;           /* Kunci agar sejajar */
    gap: 5px;                /* Jarak antar tombol */
    align-items: center;     /* Sejajar secara vertikal */
    justify-content: start;  /* Rata kiri (bisa ganti 'center' jika mau tengah) */
    flex-wrap: nowrap;       /* Mencegah tombol turun ke bawah */
}

.btn-action-group .btn-sm {
    width: 32px;             /* Lebar fix agar kotak */
    height: 32px;            /* Tinggi fix */
    padding: 0;              /* Reset padding */
    display: inline-flex;    /* Agar icon di tengah tombol */
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

/* Opsional: Agar kolom aksi tidak menyempit */
.table th:last-child, 
.table td:last-child {
    white-space: nowrap;
    width: 1%; /* Trik agar kolom menyesuaikan konten minimal */
}
@media (max-width: 768px) { .header-content { flex-direction: column; align-items: flex-start; gap: 10px; } .filter-container form { flex-direction: column; align-items: stretch; } .filter-container .form-select, .filter-container .btn { width: 100%; } .btn-action-group { justify-content: center; } .table th, .table td { padding: 0.75rem; font-size: 0.8rem; } }
/* Tambahan Style untuk tombol export */
.btn-success-export { background-color: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; font-weight: 500; }
.btn-success-export:hover { background-color: #059669; color: white; }
</style>

<main class="main-content">
    <div class="header-content">
        <h2>KEGIATAN TIM</h2>
        <?php if ($has_access_for_action): ?>
            <a href="tambah_kegiatan_tim.php" class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none;">
                <i class="bi bi-plus-circle me-2"></i>Tambah Kegiatan
            </a>
        <?php endif; ?>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="filter-container">
                <form action="" method="GET">
                    <select name="bulan" id="bulan" class="form-select">
                        <option value="">-- Semua Bulan --</option>
                        <?php 
                        $nama_bulan_arr = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", 
                                           "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                        for ($i = 1; $i <= 12; $i++) { 
                            $selected = ($i == $filter_bulan) ? 'selected' : ''; 
                            echo "<option value='$i' $selected>{$nama_bulan_arr[$i-1]}</option>"; 
                        } ?>
                    </select>

                    <select name="tahun" id="tahun" class="form-select">
                        <option value="">-- Semua Tahun --</option>
                        <?php 
                        $tahun_sekarang = date('Y');
                        for ($i = $tahun_sekarang + 1; $i >= $tahun_sekarang - 5; $i--) { 
                            $selected = ($i == $filter_tahun) ? 'selected' : ''; 
                            echo "<option value='$i' $selected>$i</option>"; 
                        } ?>
                    </select>

                    <select name="tim" id="tim" class="form-select">
                        <option value="">-- Semua Tim --</option>
                        <?php 
                        if ($result_tim && $result_tim->num_rows > 0) {
                            while($row_tim = $result_tim->fetch_assoc()) {
                                $selected = ($row_tim['id'] == $filter_tim) ? 'selected' : '';
                                echo "<option value='{$row_tim['id']}' $selected>{$row_tim['nama_tim']}</option>";
                            }
                        }
                        ?>
                    </select>

                    <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem; border-radius: 8px;">
                        <i class="bi bi-search"></i> Tampilkan
                    </button>

                    <a href="../proses/export_excel_kegiatan_tim.php?bulan=<?= $filter_bulan ?>&tahun=<?= $filter_tahun ?>&tim=<?= $filter_tim ?>" target="_blank" class="btn-success-export">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </a>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table" id="kegiatanTable">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Kegiatan</th>
                           <th>Tim</th>
                            <th>Target</th>
                            <th>Realisasi</th>
                            <th>Satuan</th>
                            <th>Batas Waktu</th>
                            <th>Tgl Realisasi</th>
                          
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT k.*, t.nama_tim AS asal_kegiatan 
                                FROM kegiatan k 
                                LEFT JOIN tim t ON k.tim_id = t.id 
                                $where_clause 
                                ORDER BY k.batas_waktu ASC";
                        $result = $koneksi->query($sql);
                        $nomor = 1;
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= $nomor++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_kegiatan']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($row['asal_kegiatan'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($row['target'], 2, ',', '.') ?></td>
                                    <td><?= number_format($row['realisasi'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['satuan']) ?></td>
                                    <td><?= date('d M Y', strtotime($row['batas_waktu'])) ?></td>
                                    <td><?= !empty($row['updated_at']) ? date('d M Y', strtotime($row['updated_at'])) : '-' ?></td>
                                
                                    <td>
    <div class="btn-action-group">
        <a href="detail_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail">
            <i class="bi bi-eye-fill"></i>
        </a>

        <?php if ($has_access_for_action): ?>
            <a href="../proses/proses_copy_kegiatan.php?id=<?= $row['id'] ?>" 
               class="btn btn-sm btn-outline-secondary" 
               title="Duplikat Kegiatan"
               onclick="return confirm('Apakah Anda yakin ingin menduplikat kegiatan ini? Kegiatan baru akan dibuat dengan status realisasi 0.');">
                <i class="bi bi-files"></i>
            </a>
        <?php endif; ?>

        <a href="edit_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
            <i class="bi bi-pencil-square"></i>
        </a>

        <?php if ($has_access_for_action): ?>
            <a href="../proses/proses_hapus_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Anda yakin ingin menghapus kegiatan ini?');">
                <i class="bi bi-trash"></i>
            </a>
        <?php endif; ?>
    </div>
</td>
                                </tr>
                            <?php }
                        } else {
                            echo "<tr><td colspan='10' class='text-center p-5'>Tidak ada data kegiatan sesuai filter.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>