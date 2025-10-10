<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan ID mitra dikirim
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: kegiatan.php?status=error&message=ID_mitra_tidak_ditemukan');
    exit;
}

$mitra_id = (int) $_GET['id'];
$user_role = $_SESSION['user_role'] ?? '';

try {
    // Ambil data mitra
    $sql_mitra = "SELECT nama_lengkap, nama_kecamatan, domisili_sama FROM mitra WHERE id = ?";
    $stmt_mitra = $koneksi->prepare($sql_mitra);
    $stmt_mitra->bind_param("i", $mitra_id);
    $stmt_mitra->execute();
    $mitra = $stmt_mitra->get_result()->fetch_assoc();
    $stmt_mitra->close();

    if (!$mitra) {
        header('Location: kegiatan.php?status=error&message=Data_mitra_tidak_ditemukan');
        exit;
    }

    // Ambil daftar kegiatan dan item: gunakan subquery terkorrelasi untuk ambil 1 item yang cocok
    $sql_kegiatan = "
        SELECT
            hm.id AS honor_id,
            ms.id AS mitra_survey_id,
            mk.nama AS nama_kegiatan,
            -- Ambil nama_item dari master_item: cari baris pertama yang kode_uniknya diawali oleh hm.item_kode_unik
            (
                SELECT mi2.nama_item
                FROM master_item mi2
                WHERE mi2.kode_unik LIKE CONCAT(hm.item_kode_unik, '%')
                LIMIT 1
            ) AS nama_item,
            (
                SELECT mi3.satuan
                FROM master_item mi3
                WHERE mi3.kode_unik LIKE CONCAT(hm.item_kode_unik, '%')
                LIMIT 1
            ) AS satuan,
            hm.jumlah_satuan,
            hm.total_honor,
            hm.bulan_pembayaran,
            hm.tahun_pembayaran,
            hm.tanggal_input
        FROM honor_mitra hm
        LEFT JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
        LEFT JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode
        WHERE hm.mitra_id = ?
        ORDER BY hm.tanggal_input DESC, hm.id DESC
    ";

    $stmt_kegiatan = $koneksi->prepare($sql_kegiatan);
    if (!$stmt_kegiatan) {
        throw new Exception("Gagal prepare statement: " . $koneksi->error);
    }
    $stmt_kegiatan->bind_param("i", $mitra_id);
    $stmt_kegiatan->execute();
    $result_kegiatan = $stmt_kegiatan->get_result();
    $kegiatan_list = $result_kegiatan->fetch_all(MYSQLI_ASSOC);
    $stmt_kegiatan->close();

    $jumlah_kegiatan = count($kegiatan_list);
    $status_partisipasi = ($jumlah_kegiatan > 0) ? 'Sudah Ikut Kegiatan' : 'Belum Ikut Kegiatan';

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit;
}
?>

<!-- (CSS sama seperti sebelumnya) -->
<style>
/* ... (tetap gunakan CSS yang sudah ada di file asli) ... */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
body { font-family: 'Poppins', sans-serif; background: #f0f4f8; }
.content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
@media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
.card { background: #fff; border-radius: 1rem; padding: 2.5rem; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.badge-green { background: #d1f7e3; color: #28a745; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; }
.badge-red { background: #fce8e8; color: #dc3545; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; }
.table-container { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
thead th { background: #e2e8f0; color: #4a5568; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem; text-align: left; }
tbody td { padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
tbody tr:hover { background: #f9fafb; }
.btn-delete { background: #ef4444; color: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 500; }
.btn-delete:hover { background: #dc2626; }
.btn-back {
        display: inline-flex;
        align-items: center;
        /* Mengurangi padding untuk ukuran yang lebih kecil */
        padding: 0.5rem; 
        border-radius: 9999px;
        background-color: #e5e7eb;
        color: #4b5563;
        transition: background-color 0.2s, color 0.2s;
    }
    .btn-back:hover {
        background-color: #d1d5db;
        color: #111827;
    }
    .btn-back svg {
        /* Mengurangi ukuran SVG ikon */
        height: 1rem;
        width: 1rem;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center mb-6">
            <a href="kegiatan.php" class="btn-back mr-4" title="Kembali">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Detail Mitra</h1>
        </div>

        <div class="card space-y-8">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Informasi Mitra</h2>
                <div class="space-y-4">
                    <div class="flex items-center space-x-4"><p class="text-gray-500 w-40">Nama Mitra:</p><p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($mitra['nama_lengkap']) ?></p></div>
                    <div class="flex items-center space-x-4"><p class="text-gray-500 w-40">Kecamatan:</p><p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($mitra['nama_kecamatan']) ?></p></div>
                    <div class="flex items-center space-x-4"><p class="text-gray-500 w-40">Status Partisipasi:</p><span class="badge <?= ($status_partisipasi == 'Sudah Ikut Kegiatan') ? 'badge-green' : 'badge-red' ?>"><?= htmlspecialchars($status_partisipasi) ?></span></div>
                    <div class="flex items-center space-x-4"><p class="text-gray-500 w-40">Jumlah Kegiatan:</p><p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($jumlah_kegiatan) ?></p></div>
                </div>
            </div>

            <?php if ($jumlah_kegiatan > 0): ?>
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Daftar Kegiatan</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Kegiatan</th>
                                    <th>Nama Item</th>
                                    <th>Satuan</th>
                                    <th>Jumlah</th>
                                    <th>Total Honor</th>
                                    <th>Bulan</th>
                                    <th>Tahun</th>
                                    <?php if (in_array($user_role, ['super_admin'])): ?><th>Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($kegiatan_list as $keg): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($keg['nama_kegiatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['nama_item'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['satuan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['jumlah_satuan'] ?? '-') ?></td>
                                        <td><?= number_format($keg['total_honor'] ?? 0, 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($keg['bulan_pembayaran'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['tahun_pembayaran'] ?? '-') ?></td>
                                        <?php if (in_array($user_role, ['super_admin'])): ?>
                                            <td>
                                                <a href="../proses/delete_kegiatan.php?id=<?= htmlspecialchars($keg['honor_id']) ?>" class="btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini?');">Hapus</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500 py-4"><p>Mitra ini belum memiliki kegiatan.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$koneksi->close();
include '../includes/footer.php';
?>
