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

// 2. Ambil Parameter Filter, Sorting, & Search
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_tim   = $_GET['tim'] ?? ''; 
$filter_sort  = $_GET['sort'] ?? 'newest'; 
$search_keyword = $_GET['search'] ?? ''; // Parameter Search Baru

// 3. Logika Sorting
switch ($filter_sort) {
    case 'newest': $order_by = "k.id DESC"; break;
    case 'oldest': $order_by = "k.id ASC"; break;
    case 'deadline_asc': $order_by = "k.batas_waktu ASC"; break;
    case 'deadline_desc': $order_by = "k.batas_waktu DESC"; break;
    default: $order_by = "k.id DESC"; break;
}

// 4. Susun WHERE Clause (Menggunakan Prepared Statement nanti)
$conditions = [];
$params = [];
$types = "";

// Wajib: Tim Aktif
$conditions[] = "t.is_active = 1"; 

// Filter Bulan
if (!empty($filter_bulan)) {
    $conditions[] = "MONTH(k.batas_waktu) = ?";
    $params[] = $filter_bulan;
    $types .= "i";
}
// Filter Tahun
if (!empty($filter_tahun)) {
    $conditions[] = "YEAR(k.batas_waktu) = ?";
    $params[] = $filter_tahun;
    $types .= "i";
}
// Filter Tim
if (!empty($filter_tim)) {
    $conditions[] = "k.tim_id = ?";
    $params[] = $filter_tim;
    $types .= "i";
}
// Filter SEARCH (Nama Kegiatan) - BARU
if (!empty($search_keyword)) {
    $conditions[] = "k.nama_kegiatan LIKE ?";
    $params[] = "%" . $search_keyword . "%";
    $types .= "s";
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
/* CSS Import & Variables */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
:root { --primary-color: #4A90E2; --background-color: #f4f7f9; --card-bg-color: #ffffff; --border-color: #e2e8f0; --shadow-color: rgba(0, 0, 0, 0.05); }
body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); }

/* Layout & Card */
.header-content { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px; }
.header-title h2 { margin: 0; font-weight: 700; color: #333; }
.card { border: none; border-radius: 12px; box-shadow: 0 4px 12px var(--shadow-color); overflow: hidden; background: var(--card-bg-color); }

/* Buttons & Actions */
.header-actions { display: flex; gap: 10px; }
.btn-action { padding: 0.6rem 1.2rem; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; transition: transform 0.2s; border: none; }
.btn-action:hover { transform: translateY(-2px); text-decoration: none; color: white; }
.btn-excel { background-color: #198754; color: white; }
.btn-excel:hover { background-color: #157347; }
.btn-success-export { background-color: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; font-weight: 500; transition: background 0.2s; }
.btn-success-export:hover { background-color: #059669; color: white; }

/* Filter Section */
.filter-container { padding: 1.5rem; border-bottom: 1px solid var(--border-color); background-color: #f8fafc; }
.filter-container form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
.form-select, .form-control { border-radius: 8px; padding: 0.5rem 1rem; border: 1px solid #ced4da; }
.form-select { min-width: 150px; }
.search-box { flex-grow: 1; min-width: 200px; } /* Agar search bar fleksibel lebarnya */

/* Table */
.table-responsive { width: 100%; overflow-x: auto; scrollbar-width: thin; }
.table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.table thead { background-color: #f1f5f9; }
.table th { font-weight: 600; color: #475569; border-bottom: 2px solid var(--border-color); padding: 1rem 1.25rem; font-size: 0.9rem; white-space: nowrap; }
.table td { padding: 1rem 1.25rem; vertical-align: middle; border-top: 1px solid var(--border-color); font-size: 0.9rem; }
.table tbody tr:hover { background-color: #f8fafc; }

/* Action Group in Table */
.btn-action-group { display: flex; gap: 5px; align-items: center; justify-content: start; flex-wrap: nowrap; }
.btn-action-group .btn-sm { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; }

/* Responsive */
@media (max-width: 768px) { 
    .header-content { flex-direction: column; align-items: flex-start; } 
    .filter-container form { flex-direction: column; align-items: stretch; } 
    .form-select, .form-control, .btn { width: 100%; } 
}
</style>

<main class="main-content">
    <div class="header-content">
        <div class="header-title">
            <h2>KEGIATAN TIM</h2>
        </div>

        <div class="header-actions">
            <a href="import_kegiatan_lengkap.php" class="btn btn-excel btn-action">
                <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>Import Data
            </a>

            <?php if ($has_access_for_action): ?>
                <a href="tambah_kegiatan_tim.php" class="btn btn-primary btn-action">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tambah Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="filter-container">
                <form action="" method="GET">
                    
                    <div class="search-box">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama kegiatan..." value="<?= htmlspecialchars($search_keyword) ?>">
                        </div>
                    </div>

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
                            $result_tim->data_seek(0); 
                            while($row_tim = $result_tim->fetch_assoc()) {
                                $selected = ($row_tim['id'] == $filter_tim) ? 'selected' : '';
                                echo "<option value='{$row_tim['id']}' $selected>{$row_tim['nama_tim']}</option>";
                            }
                        }
                        ?>
                    </select>

                    <select name="sort" id="sort" class="form-select" style="border-color: #4A90E2; background-color: #f0f8ff;">
                        <option value="newest" <?= $filter_sort == 'newest' ? 'selected' : '' ?>>📝 Input Terbaru</option>
                        <option value="oldest" <?= $filter_sort == 'oldest' ? 'selected' : '' ?>>📝 Input Terlama</option>
                        <option value="deadline_asc" <?= $filter_sort == 'deadline_asc' ? 'selected' : '' ?>>⏱️ Batas Waktu (Terdekat)</option>
                        <option value="deadline_desc" <?= $filter_sort == 'deadline_desc' ? 'selected' : '' ?>>⏱️ Batas Waktu (Terjauh)</option>
                    </select>

                    <button type="submit" class="btn btn-secondary" style="padding: 0.5rem 1rem; border-radius: 8px;">
                        Tampilkan
                    </button>

                    <a href="../proses/export_excel_kegiatan_tim.php?bulan=<?= $filter_bulan ?>&tahun=<?= $filter_tahun ?>&tim=<?= $filter_tim ?>&search=<?= urlencode($search_keyword) ?>" target="_blank" class="btn-success-export" title="Export data yang tampil saat ini">
                        <i class="bi bi-file-earmark-excel"></i>
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
                        // Query Utama (Menggunakan Prepared Statement untuk Keamanan Pencarian)
                        $sql = "SELECT k.*, t.nama_tim AS asal_kegiatan 
                                FROM kegiatan k 
                                LEFT JOIN tim t ON k.tim_id = t.id 
                                $where_clause 
                                ORDER BY $order_by";
                        
                        $stmt = $koneksi->prepare($sql);
                        
                        // Bind Parameter Dinamis
                        if (!empty($params)) {
                            $stmt->bind_param($types, ...$params);
                        }
                        
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $nomor = 1;
                        
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) { 
                                $persen = ($row['target'] > 0) ? ($row['realisasi'] / $row['target']) * 100 : 0;
                                $color_class = 'text-danger';
                                if($persen >= 100) $color_class = 'text-success';
                                elseif($persen >= 50) $color_class = 'text-warning';
                                ?>
                                <tr>
                                    <td><?= $nomor++ ?></td>
                                    <td>
                                        <div class="fw-bold">
                                            <?php 
                                            // Highlight kata kunci pencarian
                                            if(!empty($search_keyword)) {
                                                echo preg_replace('/(' . preg_quote($search_keyword, '/') . ')/i', '<span style="background-color: #fff3cd;">$1</span>', htmlspecialchars($row['nama_kegiatan']));
                                            } else {
                                                echo htmlspecialchars($row['nama_kegiatan']);
                                            }
                                            ?>
                                        </div>
                                        <?php if(!empty($row['keterangan'])): ?>
                                            <small class="text-muted d-block" style="font-size: 0.8em; max-width: 200px; white-space: normal;">
                                                <?= htmlspecialchars($row['keterangan']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($row['asal_kegiatan'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($row['target'], 0, ',', '.') ?></td>
                                    <td class="<?= $color_class ?> fw-bold"><?= number_format($row['realisasi'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['satuan']) ?></td>
                                    <td><?= date('d M Y', strtotime($row['batas_waktu'])) ?></td>
                                    <td><?= !empty($row['updated_at']) && $row['realisasi'] > 0 ? date('d M Y', strtotime($row['updated_at'])) : '-' ?></td>
                                    
                                    <td>
                                        <div class="btn-action-group">
                                            <a href="detail_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>

                                            <?php if ($has_access_for_action): ?>
                                                <a href="../proses/proses_copy_kegiatan.php?id=<?= $row['id'] ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   title="Duplikat Kegiatan"
                                                   onclick="return confirm('Apakah Anda yakin ingin menduplikat kegiatan ini?');">
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
                            echo "<tr><td colspan='9' class='text-center p-5 text-muted'><i class='bi bi-inbox fs-1 d-block mb-2'></i>Tidak ada data kegiatan sesuai filter.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>