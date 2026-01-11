<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil parameter
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_count = isset($_GET['filter_count']) ? intval($_GET['filter_count']) : 0;
// Default tahun kosong (Semua Tahun) atau tahun ini
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : ''; 
$user_roles = $_SESSION['user_role'] ?? [];

try {
    // 0. AMBIL DAFTAR TAHUN (Untuk Dropdown Filter)
    // Asumsi tabel mitra_penilaian_kinerja punya kolom tanggal_penilaian
    $sql_years = "SELECT DISTINCT YEAR(tanggal_penilaian) as tahun FROM mitra_penilaian_kinerja ORDER BY tahun DESC";
    $res_years = $koneksi->query($sql_years);
    $available_years = [];
    if($res_years){
        while($r = $res_years->fetch_assoc()){
            $available_years[] = $r['tahun'];
        }
    }

    // Persiapkan Clause WHERE untuk Tahun (Dipakai di kedua query di bawah)
    $year_condition = "";
    if (!empty($filter_tahun)) {
        $year_condition = " AND YEAR(mpk.tanggal_penilaian) = " . intval($filter_tahun) . " ";
    }

    // 1. REVISI: Hitung jumlah penilaian unik (Sesuai Tahun Terpilih)
    $sql_unique_counts = "
        SELECT COUNT(mpk.id) AS jumlah_penilaian
        FROM mitra_penilaian_kinerja mpk
        JOIN mitra_surveys ms ON mpk.mitra_survey_id = ms.id
        WHERE 1=1 $year_condition
        GROUP BY ms.mitra_id
        ORDER BY jumlah_penilaian ASC
    ";
    $result_unique_counts = $koneksi->query($sql_unique_counts);
    $unique_counts = [];
    if ($result_unique_counts) {
        while ($row = $result_unique_counts->fetch_assoc()) {
            $unique_counts[] = $row['jumlah_penilaian'];
        }
        $unique_counts = array_unique($unique_counts);
        sort($unique_counts); 
    }

    // 2. Query Utama Penilaian (Sesuai Tahun Terpilih)
    $sql_penilaian = "
        SELECT
            m.id,
            m.nama_lengkap AS nama_mitra,
            m.no_telp,
            m.alamat_detail,
            COUNT(mpk.id) AS jumlah_survei, 
            AVG(mpk.beban_kerja) AS rata_rata_beban_kerja,
            AVG(mpk.kualitas) AS rata_rata_kualitas,
            AVG(mpk.volume_pemasukan) AS rata_rata_volume_pemasukan,
            AVG(mpk.perilaku) AS rata_rata_perilaku,
            AVG((mpk.kualitas + mpk.volume_pemasukan + mpk.perilaku) / 3) AS rata_rata_penilaian
        FROM
            mitra_penilaian_kinerja AS mpk
        JOIN
            mitra_surveys AS ms ON mpk.mitra_survey_id = ms.id
        JOIN
            mitra AS m ON ms.mitra_id = m.id
        WHERE
            m.nama_lengkap LIKE ? 
            $year_condition  -- Filter Tahun Disisipkan Di Sini
        GROUP BY
            m.id, m.nama_lengkap, m.no_telp, m.alamat_detail
    ";

    // Filter HAVING untuk jumlah survey (count buttons)
    if ($filter_count > 0) {
        $sql_penilaian .= " HAVING COUNT(mpk.id) = ?"; 
    }

    $sql_penilaian .= " ORDER BY rata_rata_penilaian DESC";

    $stmt_penilaian = $koneksi->prepare($sql_penilaian);
    if (!$stmt_penilaian) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }

    $search_param = '%' . $search_query . '%';
    
    // Binding parameters dinamis
    if ($filter_count > 0) {
        // s = search (string), i = having count (integer)
        $stmt_penilaian->bind_param("si", $search_param, $filter_count);
    } else {
        // s = search (string)
        $stmt_penilaian->bind_param("s", $search_param);
    }

    $stmt_penilaian->execute();
    $result_penilaian = $stmt_penilaian->get_result();

} catch (Exception $e) {
    echo "<div class='content-wrapper'><div class='card p-6 text-center text-red-500 font-semibold'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    include '../includes/footer.php';
    exit;
}
?>

<style>
    body { font-family: 'Poppins', sans-serif; background: #eef2f5; }
    .content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
    @media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
    
    /* Header Actions */
    .page-actions-top { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
    @media (min-width: 992px) { 
        .page-actions-top { flex-direction: row; justify-content: space-between; align-items: center; } 
    }
    
    /* Search & Filter Group */
    .filter-group { display: flex; gap: 0.5rem; flex-wrap: wrap; flex-grow: 1; justify-content: flex-end; }
    
    .search-input { padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; min-width: 200px; }
    .year-select { padding: 0.75rem 2rem 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background-color: white; cursor: pointer; }
    
    .btn-cari { background-color: #2563eb; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
    .btn-cari:hover { background-color: #1d4ed8; }

    /* Buttons */
    .btn-tambah { background-color: #28a745; color: #fff; padding: 0.75rem 1rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; width: fit-content; }
    .btn-tambah:hover { background-color: #218838; }
    .btn-tambah.disabled { background-color: #6c757d; cursor: not-allowed; opacity: 0.7; }

    /* Quick Filter Counts */
    .filter-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
    .filter-btn { padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 500; color: #4b5563; border: 1px solid #d1d5db; background-color: #f9fafb; transition: all 0.2s; }
    .filter-btn.active, .filter-btn:hover { background-color: #e5e7eb; border-color: #9ca3af; color: #111827; }

    /* Table */
    .table-container { overflow-x: auto; background: #fff; border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    table { width: 100%; border-collapse: collapse; min-width: 800px; }
    th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background-color: #f9fafb; font-weight: 600; color: #374151; }
    tr:hover { background-color: #f3f4f6; }
    
    .btn-action { padding: 0.4rem 0.8rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; color: #fff; display: inline-block; }
    .btn-detail { background-color: #3b82f6; }
    .btn-detail:hover { background-color: #2563eb; }
    
    .badge-year { background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; margin-left: 5px; }
</style>

<div class="content-wrapper">
    <div class="main-content-inner">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">
            Penilaian Kinerja Mitra 
            <?php if(!empty($filter_tahun)): ?>
                <span class="text-lg font-normal text-gray-500">(Tahun <?= htmlspecialchars($filter_tahun) ?>)</span>
            <?php endif; ?>
        </h1>

        <div class="page-actions-top">
            <?php if (!in_array('super_admin', $user_roles)) : ?>
                <a href="tambah_penilaian_mitra.php" class="btn-tambah">
                    <i class="fas fa-plus"></i> Tambah Penilaian
                </a>
            <?php else : ?>
                <a href="#" class="btn-tambah disabled" onclick="alert('Admin tidak diizinkan menambah.'); return false;">
                    <i class="fas fa-plus"></i> Tambah Penilaian
                </a>
            <?php endif; ?>

            <form action="penilaian_mitra.php" method="GET" class="filter-group">
                <input type="hidden" name="filter_count" value="<?= htmlspecialchars($filter_count); ?>">
                
                <select name="tahun" class="year-select" onchange="this.form.submit()">
                    <option value="">Semua Tahun</option>
                    <?php foreach($available_years as $yr): ?>
                        <option value="<?= $yr ?>" <?= $filter_tahun == $yr ? 'selected' : '' ?>>
                            Tahun <?= $yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="search" class="search-input" placeholder="Cari nama mitra..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn-cari">Cari</button>
            </form>
        </div>

        <div class="filter-buttons">
            <a href="?tahun=<?= $filter_tahun ?>&search=<?= htmlspecialchars($search_query); ?>" 
               class="filter-btn <?= $filter_count === 0 ? 'active' : '' ?>">
               Semua
            </a>
            <?php foreach ($unique_counts as $count) : ?>
                <a href="?filter_count=<?= $count; ?>&tahun=<?= $filter_tahun ?>&search=<?= htmlspecialchars($search_query); ?>"
                   class="filter-btn <?= $filter_count === $count ? 'active' : '' ?>">
                    <?= $count; ?> Penilaian
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($result_penilaian->num_rows > 0) : ?>
            <div class="table-container mt-4">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 25%;">Nama Mitra</th>
                            <th>No. Telp</th>
                            <th>Jml Survei <?= !empty($filter_tahun) ? "($filter_tahun)" : "" ?></th>
                            <th>Rata-rata Skor</th>
                            <th>Kualitas</th> <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $result_penilaian->fetch_assoc()) : ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td>
                                    <div class="font-semibold"><?= htmlspecialchars($row['nama_mitra']); ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($row['alamat_detail']); ?></div>
                                </td>
                                <td><?= htmlspecialchars($row['no_telp']); ?></td>
                                <td class="text-center">
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded font-bold">
                                        <?= htmlspecialchars($row['jumlah_survei']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-lg font-bold text-blue-600">
                                        <?= number_format($row['rata_rata_penilaian'], 2); ?>
                                    </div>
                                </td>
                                <td><?= number_format($row['rata_rata_kualitas'], 2); ?></td>
                                <td>
                                    <a href="detail_penilaian.php?mitra_id=<?= htmlspecialchars($row['id']) ?>&tahun=<?= $filter_tahun ?>" class="btn-action btn-detail">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="text-center text-gray-500 py-10 bg-white rounded-lg mt-4 shadow-sm">
                <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
                <p>Tidak ada data penilaian yang ditemukan 
                   <?= !empty($filter_tahun) ? "pada tahun <b>$filter_tahun</b>" : "" ?>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$stmt_penilaian->close();
$koneksi->close();
include '../includes/footer.php';
?>