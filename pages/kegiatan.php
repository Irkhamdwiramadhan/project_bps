<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// ==========================================
// 1. AMBIL DATA TAHUN (Untuk Dropdown)
// ==========================================
// Kita ambil kombinasi tahun dari tabel mitra (registrasi) dan honor (kegiatan) agar lengkap
$sql_get_years = "SELECT tahun FROM mitra WHERE tahun IS NOT NULL 
                  UNION 
                  SELECT YEAR(tanggal_input) FROM honor_mitra WHERE tanggal_input IS NOT NULL 
                  ORDER BY tahun DESC";
$res_years = $koneksi->query($sql_get_years);
$available_years = [];
if ($res_years) {
    while ($r = $res_years->fetch_assoc()) {
        if (!empty($r['tahun'])) {
            $available_years[] = $r['tahun'];
        }
    }
}

// 2. TENTUKAN TAHUN TERPILIH
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : ''; 
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'semua';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // ==========================================
    // 3. STATISTIK CARD (REVISI LOGIKA)
    // ==========================================
    
    // A. HITUNG TOTAL MITRA (BASIS POPULASI)
    // Logika: Jumlah Mitra di Tahun Filter di tabel Mitra
    $sql_total_base = "SELECT COUNT(*) AS total FROM mitra WHERE 1=1";
    $p_stat_total = [];
    $t_stat_total = "";

    if (!empty($filter_tahun)) {
        $sql_total_base .= " AND tahun = ?"; // Filter Angkatan Mitra
        $p_stat_total[] = $filter_tahun;
        $t_stat_total .= "s";
    }

    $stmt_total = $koneksi->prepare($sql_total_base);
    if (!empty($p_stat_total)) {
        $stmt_total->bind_param($t_stat_total, ...$p_stat_total);
    }
    $stmt_total->execute();
    $jumlah_total_populasi = $stmt_total->get_result()->fetch_assoc()['total'];


    // B. HITUNG MITRA YANG SUDAH IKUT (INTERSEKSI)
    // Logika: Mitra Angkatan X yang punya Kegiatan di Tahun X
    $sql_sudah_base = "SELECT COUNT(DISTINCT m.id) AS jumlah_sudah 
                       FROM mitra m
                       JOIN honor_mitra hm ON m.id = hm.mitra_id 
                       WHERE 1=1";
    $p_stat_sudah = [];
    $t_stat_sudah = "";

    if (!empty($filter_tahun)) {
        $sql_sudah_base .= " AND m.tahun = ?";              // Mitra Angkatan Tahun Tersebut
        $sql_sudah_base .= " AND YEAR(hm.tanggal_input) = ?"; // Kegiatan Tahun Tersebut
        
        $p_stat_sudah[] = $filter_tahun;
        $p_stat_sudah[] = $filter_tahun;
        $t_stat_sudah .= "ss";
    }

    $stmt_sudah = $koneksi->prepare($sql_sudah_base);
    if (!empty($p_stat_sudah)) {
        $stmt_sudah->bind_param($t_stat_sudah, ...$p_stat_sudah);
    }
    $stmt_sudah->execute();
    $jumlah_sudah = $stmt_sudah->get_result()->fetch_assoc()['jumlah_sudah'];
    
    // C. HITUNG BELUM
    // Total Angkatan X - Yang Kerja di Tahun X
    $jumlah_belum = $jumlah_total_populasi - $jumlah_sudah;
    // Cegah angka negatif jika ada inkonsistensi data lama
    if($jumlah_belum < 0) $jumlah_belum = 0;


    // ==========================================
    // 4. KUERI UTAMA TABEL (SINKRONISASI)
    // ==========================================
    $params = [];
    $types = "";

    $sql_mitra_partisipasi = "SELECT
                                m.id,
                                m.nama_lengkap,
                                m.tahun as tahun_registrasi, 
                                COUNT(hm.id) AS jumlah_survei_diikuti,
                                CASE
                                    WHEN COUNT(hm.id) > 0 THEN 'Ikut Kegiatan'
                                    ELSE 'Belum Ikut Kegiatan'
                                END AS status_partisipasi
                              FROM
                                mitra AS m
                              LEFT JOIN
                                honor_mitra AS hm ON m.id = hm.mitra_id ";

    // LOGIC JOIN: Filter Tahun Kegiatan honor_mitra
    if (!empty($filter_tahun)) {
        $sql_mitra_partisipasi .= " AND YEAR(hm.tanggal_input) = ? ";
        // Parameter di-push nanti
    }

    $sql_mitra_partisipasi .= " WHERE 1=1 "; 

    // LOGIC WHERE: Filter Tahun Registrasi Mitra (Agar sinkron dengan Card Statistik)
    if (!empty($filter_tahun)) {
        $sql_mitra_partisipasi .= " AND m.tahun = ? ";
    }

    // Filter Pencarian Nama
    if (!empty($search_query)) {
        $sql_mitra_partisipasi .= " AND m.nama_lengkap LIKE ?";
    }

    $sql_mitra_partisipasi .= " GROUP BY m.id";

    // Filter Status (Sudah/Belum)
    if ($filter === 'sudah') {
        $sql_mitra_partisipasi .= " HAVING COUNT(hm.id) > 0";
    } elseif ($filter === 'belum') {
        $sql_mitra_partisipasi .= " HAVING COUNT(hm.id) = 0";
    }

    $sql_mitra_partisipasi .= " ORDER BY m.nama_lengkap ASC";
    
    // BINDING PARAMETER TABEL
    $stmt_mitra = $koneksi->prepare($sql_mitra_partisipasi);
    
    // Urutan Parameter Query:
    // 1. JOIN Honor (jika ada filter)
    // 2. WHERE Mitra (jika ada filter)
    // 3. SEARCH (jika ada search)
    
    if (!empty($filter_tahun)) {
        $params[] = $filter_tahun; // Untuk Join Honor
        $types .= "s";
        
        $params[] = $filter_tahun; // Untuk Where Mitra
        $types .= "s";
    }
    
    if (!empty($search_query)) {
        $params[] = "%" . $search_query . "%";
        $types .= "s";
    }

    if (!empty($params)) {
        $stmt_mitra->bind_param($types, ...$params);
    }

    $stmt_mitra->execute();
    $result_mitra = $stmt_mitra->get_result();
    
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit;
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
                    (<?= empty($filter_tahun) ? 'Semua Tahun' : 'Mitra Angkatan ' . htmlspecialchars($filter_tahun) ?>)
                </div>
            </div>
            <div class="summary-card summary-card-red">
                <div class="summary-number"><?= htmlspecialchars($jumlah_belum); ?></div>
                <div class="summary-label">
                    Mitra Belum Ikut Kegiatan 
                    (<?= empty($filter_tahun) ? 'Semua Tahun' : 'Mitra Angkatan ' . htmlspecialchars($filter_tahun) ?>)
                </div>
            </div>
        </div>
        
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

        <div class="flex flex-wrap gap-4 mb-6">
            <a href="kegiatan.php?filter=semua&search=<?= urlencode($search_query) ?>&tahun=<?= $filter_tahun ?>" 
               class="filter-btn <?= ($filter === 'semua') ? 'active' : '' ?>">Semua</a>
            <a href="kegiatan.php?filter=sudah&search=<?= urlencode($search_query) ?>&tahun=<?= $filter_tahun ?>" 
               class="filter-btn <?= ($filter === 'sudah') ? 'active' : '' ?>">Sudah Ikut Kegiatan</a>
            <a href="kegiatan.php?filter=belum&search=<?= urlencode($search_query) ?>&tahun=<?= $filter_tahun ?>" 
               class="filter-btn <?= ($filter === 'belum') ? 'active' : '' ?>">Belum Ikut Kegiatan</a>
        </div>

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
                (<?= empty($filter_tahun) ? 'Semua Tahun' : 'Angkatan ' . htmlspecialchars($filter_tahun) ?>)
            </h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="rounded-l-lg">Nama Mitra</th>
                            <th>Tahun Registrasi</th>
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
                                    
                                    <td>
                                        <?php 
                                        if (!empty($filter_tahun)) {
                                            // Tampilkan Angkatan Mitra sesuai filter
                                            echo "<span class='font-bold text-gray-700'>" . htmlspecialchars($filter_tahun) . "</span>";
                                        } else {
                                            echo htmlspecialchars($row['tahun_registrasi']);
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <span class="<?= $row['jumlah_survei_diikuti'] > 0 ? 'text-green-600 font-bold' : 'text-red-500' ?>">
                                            <?= htmlspecialchars($row['status_partisipasi']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['jumlah_survei_diikuti']); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="detail_kegiatan.php?id=<?= htmlspecialchars($row['id']) ?>&tahun=<?= htmlspecialchars($filter_tahun) ?>" class="btn-action btn-detail">Detail</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-4">
                                    Tidak ada data mitra ditemukan 
                                    <?= !empty($filter_tahun) ? "pada angkatan " . htmlspecialchars($filter_tahun) : "" ?>.
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