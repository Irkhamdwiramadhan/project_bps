<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. AMBIL DATA TAHUN YANG TERSEDIA DI DB
$sql_get_years = "SELECT DISTINCT tahun FROM mitra ORDER BY tahun DESC";
$res_years = $koneksi->query($sql_get_years);
$available_years = [];
if ($res_years) {
    while ($r = $res_years->fetch_assoc()) {
        $available_years[] = $r['tahun'];
    }
}

// 2. TENTUKAN TAHUN TERPILIH (DEFAULT: SEMUA TAHUN / KOSONG)
// Jika parameter 'tahun' tidak ada di URL, maka nilainya string kosong
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : ''; 

// Tangkap parameter filter status dan pencarian
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'semua';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // 3. KUERI UTAMA
    $sql_mitra_partisipasi = "SELECT
                                m.id,
                                m.nama_lengkap,
                                m.tahun,
                                COUNT(ms.survei_id) AS jumlah_survei_diikuti,
                                CASE
                                    WHEN COUNT(ms.survei_id) > 0 THEN 'Ikut Kegiatan'
                                    ELSE 'Belum Ikut Kegiatan'
                                END AS status_partisipasi
                             FROM
                                mitra AS m
                             LEFT JOIN
                                mitra_surveys AS ms ON m.id = ms.mitra_id
                             WHERE 1=1 "; // Gunakan WHERE 1=1 agar mudah menyambung kondisi

    // Tambahkan Filter Tahun (Hanya jika tidak kosong)
    if (!empty($filter_tahun)) {
        $sql_mitra_partisipasi .= " AND m.tahun = ?";
    }

    // Tambahkan kondisi pencarian jika ada
    if (!empty($search_query)) {
        $sql_mitra_partisipasi .= " AND m.nama_lengkap LIKE ?";
    }

    $sql_mitra_partisipasi .= " GROUP BY m.id";

    // Tambahkan kondisi HAVING berdasarkan filter status
    if ($filter === 'sudah') {
        $sql_mitra_partisipasi .= " HAVING COUNT(ms.survei_id) > 0";
    } elseif ($filter === 'belum') {
        $sql_mitra_partisipasi .= " HAVING COUNT(ms.survei_id) = 0";
    }

    // Tambahkan pengurutan
    $sql_mitra_partisipasi .= " ORDER BY m.nama_lengkap ASC";
    
    $stmt_mitra = $koneksi->prepare($sql_mitra_partisipasi);
    
    // LOGIC BINDING PARAMETER DINAMIS
    $params = [];
    $types = "";

    if (!empty($filter_tahun)) {
        $types .= "s";
        $params[] = $filter_tahun;
    }
    if (!empty($search_query)) {
        $types .= "s";
        $params[] = "%" . $search_query . "%";
    }

    if (!empty($params)) {
        $stmt_mitra->bind_param($types, ...$params);
    }

    $stmt_mitra->execute();
    $result_mitra = $stmt_mitra->get_result();
    
    // 4. REVISI KUERI STATISTIK (Agar sesuai filter)
    
    // Base Query untuk Statistik
    $sql_sudah_base = "SELECT COUNT(DISTINCT ms.mitra_id) AS jumlah_sudah FROM mitra_surveys ms JOIN mitra m ON ms.mitra_id = m.id WHERE 1=1";
    $sql_total_base = "SELECT COUNT(*) AS total FROM mitra WHERE 1=1";

    if (!empty($filter_tahun)) {
        $sql_sudah_base .= " AND m.tahun = ?";
        $sql_total_base .= " AND tahun = ?";
    }

    // Eksekusi Statistik SUDAH
    $stmt_sudah = $koneksi->prepare($sql_sudah_base);
    if (!empty($filter_tahun)) {
        $stmt_sudah->bind_param("s", $filter_tahun);
    }
    $stmt_sudah->execute();
    $jumlah_sudah = $stmt_sudah->get_result()->fetch_assoc()['jumlah_sudah'];

    // Eksekusi Statistik TOTAL
    $stmt_total = $koneksi->prepare($sql_total_base);
    if (!empty($filter_tahun)) {
        $stmt_total->bind_param("s", $filter_tahun);
    }
    $stmt_total->execute();
    $jumlah_total = $stmt_total->get_result()->fetch_assoc()['total'];
    
    // Hitung BELUM
    $jumlah_belum = $jumlah_total - $jumlah_sudah;

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit;
}

// Cek Role (Sama seperti sebelumnya)
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_mitra'];
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body { font-family: 'Poppins', sans-serif; background: #eef2f5; }
    .content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
    @media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
    .card { background-color: #ffffff; border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
    .summary-card { background-color: #eef2f5; border-radius: 0.75rem; padding: 1.5rem; text-align: center; }
    .summary-card-green { background-color: #d1f7e3; border-left: 5px solid #28a745; }
    .summary-card-red { background-color: #fce8e8; border-left: 5px solid #dc3545; }
    .summary-number { font-size: 2.5rem; font-weight: 700; color: #1f2937; }
    .summary-label { font-size: 1rem; font-weight: 500; color: #6b7280; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: separate; border-spacing: 0 0.75rem; }
    thead th { background-color: #e5e7eb; color: #4b5563; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem; text-align: left; }
    tbody td { background-color: #ffffff; padding: 1rem 1.5rem; border-radius: 0.5rem; }
    tbody tr:hover td { background-color: #f9fafb; }
    tbody tr { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }

    .btn-action { padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-align: center; text-decoration: none; transition: background-color 0.2s; }
    .btn-detail { background-color: #3b82f6; color: #fff; }
    .btn-detail:hover { background-color: #2563eb; }
    .btn-add { background-color: #28a745; color: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; transition: background-color 0.2s; }
    .btn-add:hover { background-color: #218838; }

    .filter-btn { padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 500; transition: background-color 0.2s, color 0.2s; text-decoration: none; color: #4b5563; background-color: #e5e7eb; }
    .filter-btn:hover { background-color: #d1d5db; }
    .filter-btn.active { background-color: #2563eb; color: #fff; }
    .search-input { width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
    .search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
    
    /* Style untuk Tahun Dropdown */
    .year-select { padding: 0.5rem 2rem 0.5rem 1rem; border-radius: 0.5rem; border: 1px solid #d1d5db; background-color: white; font-weight: 500; cursor: pointer; }
</style>

<div class="content-wrapper">
    <div class="header-content">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Halaman Kegiatan Mitra</h1>
        <a href="rekap_kegiatan_tim.php" class="btn btn-primary">Rekap Kegiatan Tim</a>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <a href="tambah_kegiatan.php" class="btn-add w-full sm:w-auto text-center">Tambah Kegiatan</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div class="summary-card summary-card-green">
                <div class="summary-number"><?= htmlspecialchars($jumlah_sudah); ?></div>
                <div class="summary-label">
                    Mitra Sudah Ikut Kegiatan 
                    (<?= empty($filter_tahun) ? 'Semua Tahun' : 'Tahun ' . htmlspecialchars($filter_tahun) ?>)
                </div>
            </div>
            <div class="summary-card summary-card-red">
                <div class="summary-number"><?= htmlspecialchars($jumlah_belum); ?></div>
                <div class="summary-label">
                    Mitra Belum Ikut Kegiatan 
                    (<?= empty($filter_tahun) ? 'Semua Tahun' : 'Tahun ' . htmlspecialchars($filter_tahun) ?>)
                </div>
            </div>
        </div>
        <br>
        <div class="mb-4 flex items-center gap-2 bg-white p-3 rounded-lg shadow-sm w-fit">
            <span class="text-gray-600 font-semibold"><i class="fas fa-calendar-alt"></i> Filter Tahun:</span>
            <form action="" method="GET" id="formTahun">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                
                <select name="tahun" class="year-select" onchange="document.getElementById('formTahun').submit()">
                    <option value="" <?= empty($filter_tahun) ? 'selected' : '' ?>>Semua Tahun</option>
                    
                    <?php if (!empty($available_years)): ?>
                        <?php foreach($available_years as $yr): ?>
                            <option value="<?= $yr ?>" <?= ($filter_tahun == $yr) ? 'selected' : '' ?>>
                                <?= $yr ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>
        </div>
<br>
        <div class="flex flex-wrap gap-4 mb-6">
            <a href="kegiatan.php?filter=semua&search=<?= urlencode($search_query) ?>&tahun=<?= $filter_tahun ?>" 
               class="filter-btn <?= ($filter === 'semua') ? 'active' : '' ?>">Semua</a>
            
            <a href="kegiatan.php?filter=sudah&search=<?= urlencode($search_query) ?>&tahun=<?= $filter_tahun ?>" 
               class="filter-btn <?= ($filter === 'sudah') ? 'active' : '' ?>">Sudah Ikut Kegiatan</a>
            
            <a href="kegiatan.php?filter=belum&search=<?= urlencode($search_query) ?>&tahun=<?= $filter_tahun ?>" 
               class="filter-btn <?= ($filter === 'belum') ? 'active' : '' ?>">Belum Ikut Kegiatan</a>
        </div>
<br>
        <div class="mb-6">
            <form action="kegiatan.php" method="GET" class="w-full sm:w-auto">
                <input type="text" name="search" placeholder="Cari nama mitra..." class="search-input" value="<?= htmlspecialchars($search_query); ?>">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                <input type="hidden" name="tahun" value="<?= htmlspecialchars($filter_tahun); ?>">
            </form>
        </div>

        <div class="card">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">
                Detail Partisipasi Mitra 
                (<?= empty($filter_tahun) ? 'Semua Tahun' : htmlspecialchars($filter_tahun) ?>)
            </h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="rounded-l-lg">Nama Mitra</th>
                            <th>Tahun</th>
                            <th>Status Partisipasi</th>
                            <th>Jumlah Survei</th>
                            <th class="rounded-r-lg">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_mitra->num_rows > 0) : ?>
                            <?php while ($row = $result_mitra->fetch_assoc()) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td><?= htmlspecialchars($row['tahun']); ?></td>
                                    <td>
                                        <span class="<?= $row['jumlah_survei_diikuti'] > 0 ? 'text-green-600 font-bold' : 'text-red-500' ?>">
                                            <?= htmlspecialchars($row['status_partisipasi']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['jumlah_survei_diikuti']); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="detail_kegiatan.php?id=<?= htmlspecialchars($row['id']) ?>" class="btn-action btn-detail">Detail</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-4">
                                    Tidak ada data mitra ditemukan 
                                    <?= !empty($filter_tahun) ? "pada tahun " . htmlspecialchars($filter_tahun) : "" ?>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
$stmt_mitra->close();
$stmt_sudah->close();
$stmt_total->close();
$koneksi->close();
include '../includes/footer.php'; 
?>