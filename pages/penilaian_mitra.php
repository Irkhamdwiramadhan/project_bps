<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_count = isset($_GET['filter_count']) ? intval($_GET['filter_count']) : 0;
$user_roles = $_SESSION['user_role'] ?? [];

try {
    // 1. REVISI: Hitung jumlah penilaian unik (bukan total survey)
    $sql_unique_counts = "
        SELECT COUNT(mpk.id) AS jumlah_penilaian
        FROM mitra_penilaian_kinerja mpk
        JOIN mitra_surveys ms ON mpk.mitra_survey_id = ms.id
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
        sort($unique_counts); // Urutkan agar rapi
    }

    // 2. Query utama penilaian
    // REVISI: Menghapus u.nama (nama_penilai) dari SELECT
    $sql_penilaian = "
        SELECT
            m.id,
            m.nama_lengkap AS nama_mitra,
            m.no_telp,
            m.alamat_detail,
            -- u.nama AS nama_penilai, -- DIHAPUS
            
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
        -- LEFT JOIN pegawai AS u ON mpk.penilai_id = u.id -- JOIN ini tidak diperlukan lagi jika nama tidak diambil
        WHERE
            m.nama_lengkap LIKE ?
        -- REVISI: Menghapus u.nama dari GROUP BY
        GROUP BY
            m.id, m.nama_lengkap, m.no_telp, m.alamat_detail
    ";

    if ($filter_count > 0) {
        $sql_penilaian .= " HAVING COUNT(mpk.id) = ?"; 
    }

    $sql_penilaian .= " ORDER BY rata_rata_penilaian DESC";

    $stmt_penilaian = $koneksi->prepare($sql_penilaian);
    if (!$stmt_penilaian) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }

    $search_param = '%' . $search_query . '%';
    
    if ($filter_count > 0) {
        $stmt_penilaian->bind_param("si", $search_param, $filter_count);
    } else {
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
    /* CSS ANDA (TETAP SAMA) */
    body { font-family: 'Poppins', sans-serif; background: #eef2f5; }
    .content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
    @media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
    .page-actions-top { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
    @media (min-width: 768px) { .page-actions-top { flex-direction: row; justify-content: space-between; } }
    .search-form { display: flex; gap: 0.5rem; flex-grow: 1; }
    .search-form input, .search-form button { padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.5rem; }
    .search-form button { background-color: #2563eb; color: white; font-weight: 600; cursor: pointer; }
    .search-form button:hover { background-color: #1d4ed8; }
    .btn-tambah { background-color: #28a745; color: #fff; padding: 0.75rem 1rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
    .btn-tambah:hover { background-color: #218838; }
    .btn-tambah.disabled { background-color: #6c757d; cursor: not-allowed; opacity: 0.7; }
    .filter-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
    .filter-btn { padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 500; color: #4b5563; border: 1px solid #d1d5db; background-color: #f9fafb; }
    .filter-btn.active, .filter-btn:hover { background-color: #e5e7eb; border-color: #9ca3af; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 1rem; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background-color: #f9fafb; font-weight: 600; color: #374151; }
    tr:hover { background-color: #f3f4f6; }
    .btn-action { padding: 0.4rem 0.8rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; color: #fff; }
    .btn-detail { background-color: #3b82f6; }
    .btn-detail:hover { background-color: #2563eb; }
    .btn-delete { background-color: #ef4444; }
    .btn-delete:hover { background-color: #dc2626; }
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
                   onclick="alert('Peran super_admin tidak diizinkan menambah penilaian mitra.'); return false;">
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
                <a href="?filter_count=<?= $count; ?>&search=<?= htmlspecialchars($search_query); ?>"
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
                            <th>No</th>
                            <th>Nama Mitra</th>
                            <th>No. Telp</th>
                            <th>Alamat</th>
                            <th>Jml Penilaian</th>
                            <th>Skor Akhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($row = $result_penilaian->fetch_assoc()) : ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($row['nama_mitra']); ?></td>
                                <td><?= htmlspecialchars($row['no_telp']); ?></td>
                                <td><?= htmlspecialchars($row['alamat_detail']); ?></td>
                                <td><?= htmlspecialchars($row['jumlah_survei']); ?></td>
                                <td><strong><?= number_format($row['rata_rata_penilaian'], 2); ?></strong></td>
                                <td>
                                    <a href="detail_penilaian.php?mitra_id=<?= htmlspecialchars($row['id']) ?>" class="btn-action btn-detail">Detail</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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