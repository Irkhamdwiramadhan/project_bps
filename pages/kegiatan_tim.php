<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$user_roles = $_SESSION['user_role'] ?? []; $allowed_roles_for_action = ['super_admin', 'admin_simpedu']; $has_access_for_action = false; foreach ($user_roles as $role) { if (in_array($role, $allowed_roles_for_action)) { $has_access_for_action = true; break; } }
$filter_bulan = $_GET['bulan'] ?? date('m'); $filter_tahun = $_GET['tahun'] ?? date('Y'); $where_clause = "";
if (!empty($filter_bulan) && !empty($filter_tahun)) { $filter_bulan = intval($filter_bulan); $filter_tahun = intval($filter_tahun); $where_clause = "WHERE MONTH(k.batas_waktu) = $filter_bulan AND YEAR(k.batas_waktu) = $filter_tahun"; }
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    :root { --primary-color: #4A90E2; --background-color: #f4f7f9; --card-bg-color: #ffffff; --border-color: #e2e8f0; --shadow-color: rgba(0, 0, 0, 0.05); } 
    body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); } 
    .header-content { background-color: var(--card-bg-color); padding: 20px 30px; border-bottom: 1px solid var(--border-color); } 
    .header-content h2 { font-weight: 600; margin-bottom: 0; } 
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px var(--shadow-color); } 
    .filter-container { padding: 1.5rem; } 
    .form-select { border-radius: 8px; } 
    .table-responsive { overflow: hidden; } 
    .table thead { background-color: #f8fafc; } 
    .table th { font-weight: 600; color: #475569; border-bottom: 2px solid var(--border-color); padding: 1rem 1.25rem; font-size: 0.9rem; white-space: nowrap; } 
    .table td { padding: 1rem 1.25rem; vertical-align: middle; border-top: 1px solid var(--border-color); }
    .table tbody tr:hover { background-color: #f1f5f9; }
    .btn-action-group { display: flex; gap: 8px; } 
    .btn-action-group .btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
</style>

<main class="main-content">
    <div class="header-content">
        <div class="d-flex justify-content-between align-items-center">
            <h2>KEGIATAN TIM</h2>
            <?php if ($has_access_for_action): ?>
                <a href="tambah_kegiatan_tim.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Tambah Kegiatan</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="filter-container">
                <form action="" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select name="bulan" id="bulan" class="form-select">
                            <?php $nama_bulan_arr = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"]; for ($i = 1; $i <= 12; $i++) { $selected = ($i == $filter_bulan) ? 'selected' : ''; echo "<option value='$i' $selected>" . $nama_bulan_arr[$i-1] . "</option>"; } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="tahun" id="tahun" class="form-select">
                            <?php $tahun_sekarang = date('Y'); for ($i = $tahun_sekarang + 1; $i >= $tahun_sekarang - 5; $i--) { $selected = ($i == $filter_tahun) ? 'selected' : ''; echo "<option value='$i' $selected>$i</option>"; } ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-secondary">Tampilkan</button>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table" id="kegiatanTable">
                    <thead>
                        <tr>
                            <th>No.</th> <th>Nama Kegiatan</th> <th>Asal Kegiatan</th> <th>Target</th> <th>Realisasi</th> <th>Satuan</th> <th>Batas Waktu</th> <th>Tgl Realisasi</th> <th>Keterangan</th> <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT k.*, t.nama_tim AS asal_kegiatan FROM kegiatan k LEFT JOIN tim t ON k.tim_id = t.id $where_clause ORDER BY k.batas_waktu ASC";
                        $result = $koneksi->query($sql);
                        $nomor = 1;
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= $nomor++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_kegiatan']) ?></td>
                                    <td><?= htmlspecialchars($row['asal_kegiatan'] ?? 'N/A') ?></td>
                                    <td><?= number_format($row['target'], 2, ',', '.') ?></td>
                                    <td><?= number_format($row['realisasi'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['satuan']) ?></td>
                                    <td><?= date('d M Y', strtotime($row['batas_waktu'])) ?></td>
                                    <td><?= $row['tgl_realisasi'] ? date('d M Y', strtotime($row['tgl_realisasi'])) : '' ?></td>
                                    <td><?= htmlspecialchars($row['keterangan'] ?? '') ?></td>
                                    <td>
                                        <div class="btn-action-group">
                                            <a href="detail_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <?php if ($has_access_for_action): ?>
                                                <a href="edit_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Kegiatan"><i class="bi bi-pencil-square"></i></a>
                                                <a href="../proses/proses_hapus_kegiatan_tim.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus Kegiatan" onclick="return confirm('Anda yakin ingin menghapus kegiatan ini?');"><i class="bi bi-trash"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo "<tr><td colspan='10' class='text-center p-5'>Tidak ada data kegiatan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>