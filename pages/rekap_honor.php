<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil bulan dan tahun dari GET request atau gunakan nilai default (bulan dan tahun saat ini)
$bulan_filter = $_GET['bulan'] ?? date('m');
$tahun_filter = $_GET['tahun'] ?? date('Y');

// Array untuk nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// --- REVISI: Ambil Daftar Tahun Unik dari Database ---
$tahun_list = [];
$sql_tahun = "SELECT DISTINCT tahun_pembayaran FROM honor_mitra ORDER BY tahun_pembayaran DESC";
$res_tahun = $koneksi->query($sql_tahun);
if ($res_tahun) {
    while ($row = $res_tahun->fetch_assoc()) {
        $tahun_list[] = $row['tahun_pembayaran'];
    }
}
// Jika database kosong, tetap tampilkan tahun sekarang
if (empty($tahun_list)) {
    $tahun_list[] = date('Y');
}
// ----------------------------------------------------

// Ambil batas honor (honor cap) untuk bulan dan tahun yang sama
try {
    $sql_limit = "SELECT batas_honor FROM batas_honor WHERE bulan = ? AND tahun = ?";
    $stmt_limit = $koneksi->prepare($sql_limit);
    if (!$stmt_limit) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }
    $stmt_limit->bind_param("ss", $bulan_filter, $tahun_filter);
    $stmt_limit->execute();
    $result_limit = $stmt_limit->get_result();
    $row_limit = $result_limit->fetch_assoc();
    $honor_limit = $row_limit['batas_honor'] ?? 2500000; // Default jika tidak ada batasan
    $stmt_limit->close();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error mengambil batas honor: " . htmlspecialchars($e->getMessage()) . "</div>";
    $honor_limit = 2500000; // Set default pada kasus error
}


try {
    // Query untuk mengambil data rekap honor per bulan
    // Menggunakan kolom bulan_pembayaran dan tahun_pembayaran
    $sql_rekap_honor = "SELECT
                            m.id AS mitra_id,
                            m.nama_lengkap,
                            SUM(hm.total_honor) AS total_honor_bulan_ini
                        FROM
                            honor_mitra AS hm
                        JOIN
                            mitra AS m ON hm.mitra_id = m.id
                        WHERE
                            hm.bulan_pembayaran = ? AND hm.tahun_pembayaran = ?
                        GROUP BY
                            m.id
                        ORDER BY
                            total_honor_bulan_ini DESC";

    $stmt_rekap = $koneksi->prepare($sql_rekap_honor);
    if (!$stmt_rekap) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }
    $stmt_rekap->bind_param("ss", $bulan_filter, $tahun_filter);
    $stmt_rekap->execute();
    $result_rekap = $stmt_rekap->get_result();
    
    $rekap_list = [];
    if ($result_rekap) {
        while ($row = $result_rekap->fetch_assoc()) {
            $rekap_list[] = $row;
        }
    }
    $stmt_rekap->close();

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $rekap_list = []; // Inisialisasi array kosong jika terjadi error
}

?>

<style>
    /* Tambahan style untuk badge hampir limit */
    .badge.bg-yellow-100 {
        background-color: #fffac8;
        color: #8a6d3b;
    }
    /* Style lainnya yang sudah ada */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #f0f4f8;
    }
    .content-wrapper {
        padding: 1rem;
        transition: margin-left 0.3s ease;
    }
    @media (min-width: 640px) {
        .content-wrapper {
            margin-left: 16rem;
            padding-top: 2rem;
        }
    }
    .card {
        background-color: #ffffff;
        border-radius: 1rem;
        padding: 2.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }
    thead th {
        background-color: #e2e8f0;
        color: #4a5568;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.5rem;
        text-align: left;
    }
    tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
    }
    tbody tr:last-child td {
        border-bottom: none;
    }
    tbody tr:hover {
        background-color: #f9fafb;
    }
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .badge.bg-green-100 {
        background-color: #d1f7e3;
        color: #28a745;
    }
    .badge.bg-red-100 {
        background-color: #fcebeb;
        color: #dc2626;
    }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-end;
        margin-bottom: 2rem;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    .filter-group label {
        color: #4a5568;
        font-weight: 500;
        margin-bottom: 0.25rem;
    }
    .filter-group select {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.75rem;
        font-size: 0.95rem;
        background-color: #fff;
        transition: border-color 0.2s;
    }
    .filter-group select:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
    }
    .filter-form button {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        color: white;
        cursor: pointer;
        background-image: linear-gradient(to right, #6366f1 0%, #4f46e5 100%);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        transition: all 0.3s ease;
    }
    .filter-form button:hover {
        background-image: linear-gradient(to right, #4f46e5 0%, #6366f1 100%);
        box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
        transform: translateY(-2px);
    }
    .filter-form button:active {
        transform: translateY(0);
    }
    .action-link {
        color: #4f46e5;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s ease;
    }
    .action-link:hover {
        color: #4338ca;
    }
       .manage-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background-color: #6c757d; /* Warna abu-abu sekunder */
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.3s ease;
    }
    .manage-button:hover {
        background-color: #5a6268;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Rekapitulasi Honor Mitra</h1>
            <a href="manage_batas_honor.php" class="manage-button">
                    <i class="fas fa-user-cog"></i> Kelola Batas Honor
                </a>

        <form action="" method="GET" class="filter-form">
            <div class="filter-group">
                <label for="bulan">Bulan</label>
                <select id="bulan" name="bulan">
                    <?php
                    foreach ($nama_bulan as $num => $name) {
                        $selected = ($num == $bulan_filter) ? 'selected' : '';
                        echo "<option value=\"$num\" $selected>$name</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="tahun">Tahun</label>
                <select id="tahun" name="tahun">
                    <?php foreach ($tahun_list as $thn) : ?>
                        <option value="<?= $thn ?>" <?= ($thn == $tahun_filter) ? 'selected' : '' ?>>
                            <?= $thn ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Tampilkan</button>
        </form>
        
        <div class="card space-y-8">
            <p class="text-lg font-semibold text-gray-700">Batas Honor Bulan Ini: <span class="text-blue-600">Rp <?= number_format($honor_limit, 0, ',', '.') ?></span></p>

            <?php if (count($rekap_list) > 0) : ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Mitra</th>
                                <th>Total Honor Bulan Ini</th>
                                <th>Kondisi Honor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($rekap_list as $rekap) : ?>
                                <?php
                                $total_honor = $rekap['total_honor_bulan_ini'];
                                $status = 'Aman';
                                $badge_class = 'bg-green-100';

                                if ($total_honor >= $honor_limit * 0.8 && $total_honor < $honor_limit) {
                                    $status = 'Hampir Limit';
                                    $badge_class = 'bg-yellow-100';
                                } elseif ($total_honor >= $honor_limit) {
                                    $status = 'Melebihi Limit'; // Sesuaikan teks status
                                    $badge_class = 'bg-red-100';
                                }
                                ?>
                                <tr>
                                    <td><?= $counter ?></td>
                                    <td><?= htmlspecialchars($rekap['nama_lengkap']) ?></td>
                                    <td>Rp <?= number_format($total_honor, 0, ',', '.') ?></td>
                                    <td>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= $status ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="detail_honor.php?mitra_id=<?= htmlspecialchars($rekap['mitra_id']) ?>&bulan=<?= htmlspecialchars($bulan_filter) ?>&tahun=<?= htmlspecialchars($tahun_filter) ?>" class="action-link">
                                            Lihat Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php $counter++; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="text-center text-gray-500 py-4">
                    <p>Tidak ada data honor untuk bulan dan tahun yang dipilih.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if (isset($result_rekap) && $result_rekap instanceof mysqli_result) { $result_rekap->free(); }
if (isset($result_limit) && $result_limit instanceof mysqli_result) { $result_limit->free(); }
$koneksi->close();
include '../includes/footer.php';
?>