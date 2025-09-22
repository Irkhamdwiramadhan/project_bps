<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Tangkap parameter pencarian dan filter dari URL
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_count = isset($_GET['filter_count']) ? intval($_GET['filter_count']) : 0;

// Dapatkan peran pengguna dari sesi
$user_roles = $_SESSION['user_role'] ?? [];

try {
    // Kueri untuk mendapatkan jumlah survei unik untuk filter
    $sql_unique_counts = "SELECT
                                COUNT(ms.id) AS jumlah_survei
                              FROM
                                mitra_surveys AS ms
                              GROUP BY
                                ms.mitra_id
                              ORDER BY
                                jumlah_survei ASC";
    $result_unique_counts = $koneksi->query($sql_unique_counts);

    $unique_counts = [];
    if ($result_unique_counts) {
        while ($row = $result_unique_counts->fetch_assoc()) {
            $unique_counts[] = $row['jumlah_survei'];
        }
        $unique_counts = array_unique($unique_counts);
    }
    
    // Kueri SQL utama untuk menggabungkan data penilaian mitra
    $sql_penilaian = "SELECT
                                m.id,
                                m.nama_lengkap AS nama_mitra,
                                m.no_telp,
                                m.alamat_detail,
                                COUNT(ms.id) AS jumlah_survei,
                                AVG(mpk.beban_kerja) AS rata_rata_beban_kerja,
                                AVG(mpk.kualitas) AS rata_rata_kualitas,
                                AVG(mpk.volume_pemasukan) AS rata_rata_volume_pemasukan,
                                AVG(mpk.perilaku) AS rata_rata_perilaku,
                                AVG((mpk.beban_kerja + mpk.kualitas + mpk.volume_pemasukan + mpk.perilaku) / 4) AS rata_rata_penilaian
                             FROM
                                mitra_penilaian_kinerja AS mpk
                             JOIN
                                mitra_surveys AS ms ON mpk.mitra_survey_id = ms.id
                             JOIN
                                mitra AS m ON ms.mitra_id = m.id
                             WHERE
                                m.nama_lengkap LIKE ?
                             GROUP BY
                                m.id, m.nama_lengkap, m.no_telp, m.alamat_detail";

    // Tambahkan klausa HAVING jika filter jumlah survei dipilih
    if ($filter_count > 0) {
        $sql_penilaian .= " HAVING COUNT(ms.id) = ?";
    }
    
    // Tambahkan pengurutan
    $sql_penilaian .= " ORDER BY nama_mitra ASC";

    $stmt_penilaian = $koneksi->prepare($sql_penilaian);
    
    if (!$stmt_penilaian) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }

    // Binding parameter berdasarkan filter
    if ($filter_count > 0) {
        $search_param = '%' . $search_query . '%';
        $stmt_penilaian->bind_param("si", $search_param, $filter_count);
    } else {
        $search_param = '%' . $search_query . '%';
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
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body { font-family: 'Poppins', sans-serif; background: #eef2f5; }
    .content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
    @media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
    .page-actions-top {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .search-form {
        display: flex;
        gap: 0.5rem;
        flex-grow: 1;
    }
    .search-form input, .search-form button {
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
    }
    .search-form input { flex-grow: 1; }
    .search-form button {
        background-color: #2563eb;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .search-form button:hover { background-color: #1d4ed8; }
    .btn-tambah {
        background-color: #28a745;
        color: #fff;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: background-color 0.2s;
    }
    .btn-tambah:hover { background-color: #218838; }
    .btn-tambah.disabled {
        background-color: #6c757d; /* Warna abu-abu */
        cursor: not-allowed;
        opacity: 0.7;
    }
    .btn-tambah.disabled:hover {
        background-color: #6c757d;
    }
    .filter-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    .filter-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-weight: 500;
        color: #4b5563;
        border: 1px solid #d1d5db;
        background-color: #f9fafb;
        transition: all 0.2s;
    }
    .filter-btn.active, .filter-btn:hover {
        background-color: #e5e7eb;
        border-color: #9ca3af;
    }
    @media (min-width: 768px) {
        .page-actions-top {
            flex-direction: row;
            justify-content: space-between;
        }
        .search-form {
            width: auto;
        }
    }
    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    .card-modern {
        background-color: #ffffff;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        position: relative;
    }
    /* === CSS YANG DIUBAH === */
    .mitra-icon-container {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        margin-bottom: 1rem;
        background-color: #f3f4f6;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 3px solid #e5e7eb;
    }
    .mitra-icon-container i {
        font-size: 3.5rem; /* Ukuran ikon */
    }
    /* === AKHIR CSS YANG DIUBAH === */
    .mitra-name {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }
    .mitra-info {
        font-size: 0.9rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
    .rating-badge {
        background-color: #e5e7eb;
        color: #4b5563;
        font-size: 1.25rem;
        font-weight: 700;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
    }
    .actions-row {
        margin-top: 1rem;
        width: 100%;
        display: flex;
        justify-content: center;
        gap: 0.75rem;
    }
    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-align: center;
        text-decoration: none;
        transition: background-color 0.2s;
    }
    .btn-detail { background-color: #3b82f6; color: #fff; }
    .btn-detail:hover { background-color: #2563eb; }
    .btn-delete { background-color: #ef4444; color: #fff; }
    .btn-delete:hover { background-color: #dc2626; }
    .survey-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: #2563eb;
        color: white;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 10;
    }
</style>

<div class="content-wrapper">
    <div class="main-content-inner">
        <h1 class="page-title">Penilaian Kinerja Mitra</h1>
        <div class="page-actions-top">
            <?php if (!in_array('super_admin', $user_roles)) : ?>
                <a href="tambah_penilaian_mitra.php" class="btn-tambah">
                    <i class="fas fa-plus"></i> Tambah Penilaian
                </a>
            <?php else : ?>
                <a href="#" class="btn-tambah disabled"
                   onclick="alert('Maaf, peran super_admin tidak diizinkan untuk menambah penilaian mitra.'); return false;">
                   <i class="fas fa-plus"></i> Tambah Penilaian
                </a>
            <?php endif; ?>
            <form action="penilaian_mitra.php" method="GET" class="search-form">
                <input type="hidden" name="filter_count" value="<?= htmlspecialchars($filter_count); ?>">
                <input type="text" name="search" placeholder="Cari nama mitra..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit">Cari</button>
            </form>
        </div>

        <div class="filter-buttons">
            <a href="?search=<?= htmlspecialchars($search_query); ?>" class="filter-btn <?= $filter_count === 0 ? 'active' : '' ?>">Semua</a>
            <?php foreach ($unique_counts as $count) : ?>
                <a href="?filter_count=<?= $count; ?>&search=<?= htmlspecialchars($search_query); ?>" class="filter-btn <?= $filter_count === $count ? 'active' : '' ?>">
                    <?= $count; ?> Survei
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($result_penilaian->num_rows > 0) : ?>
            <div class="card-grid mt-4">
                <?php while ($row = $result_penilaian->fetch_assoc()) : ?>
                    <div class="card-modern">
                        <span class="survey-badge"><?= htmlspecialchars($row['jumlah_survei']); ?> Survei</span>
                        
                        <div class="mitra-icon-container">
                            <i class="fas fa-user"></i> </div>
                        <h3 class="mitra-name"><?= htmlspecialchars($row['nama_mitra']); ?></h3>
                        <p class="mitra-info">Telp: <?= htmlspecialchars($row['no_telp']); ?></p>
                        <p class="mitra-info"><?= htmlspecialchars($row['alamat_detail']); ?></p>
                        <div class="rating-badge">
                            <?= number_format($row['rata_rata_penilaian'], 2); ?>
                        </div>
                        <div class="actions-row">
                            <a href="detail_penilaian.php?id=<?= htmlspecialchars($row['id']) ?>" class="btn-action btn-detail">Detail</a>
                            <a href="../proses/delete_penilaian.php?id=<?= htmlspecialchars($row['id']) ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus semua penilaian untuk mitra ini?');" class="btn-action btn-delete">Hapus</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <div class="text-center text-gray-500 py-10">
                <p>Tidak ada data penilaian yang ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$stmt_penilaian->close();
$koneksi->close();
include '../includes/footer.php';
?>