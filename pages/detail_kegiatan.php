<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';


$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'admin_apel'];

// Periksa apakah pengguna memiliki salah satu peran yang diizinkan untuk melihat aksi
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break; // Keluar dari loop setelah menemukan kecocokan
    }
}
// --- FUNGSI HELPER FORMAT PERIODE ---
function formatPeriode($jenis, $nilai) {
    $jenisLower = strtolower($jenis); 
    $jenisLabel = ucfirst($jenis); 

    if (empty($nilai)) return $jenisLabel;

    switch ($jenisLower) {
        case 'bulanan':
            $nama_bulan = [
                1=>'Januari', '01'=>'Januari', 2=>'Februari', '02'=>'Februari',
                3=>'Maret', '03'=>'Maret', 4=>'April', '04'=>'April',
                5=>'Mei', '05'=>'Mei', 6=>'Juni', '06'=>'Juni',
                7=>'Juli', '07'=>'Juli', 8=>'Agustus', '08'=>'Agustus',
                9=>'September', '09'=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
            ];
            $bulanText = $nama_bulan[$nilai] ?? $nilai;
            return "Bulanan ($bulanText)";

        case 'triwulan':
            $map = [1 => 'Jan - Mar', 2 => 'Apr - Jun', 3 => 'Jul - Sep', 4 => 'Okt - Des'];
            $range = isset($map[$nilai]) ? " ({$map[$nilai]})" : "";
            return "Triwulan $nilai" . $range;

        case 'subron':
            $map = [1 => 'Jan - Apr', 2 => 'Mei - Ags', 3 => 'Sep - Des'];
            $range = isset($map[$nilai]) ? " ({$map[$nilai]})" : "";
            return "Sub-Round $nilai" . $range;

        case 'semester':
            $map = [1 => 'Jan - Jun', 2 => 'Jul - Des'];
            $range = isset($map[$nilai]) ? " ({$map[$nilai]})" : "";
            return "Semester $nilai" . $range;

        case 'tahunan':
            return "Tahun $nilai";

        default:
            return "$jenisLabel $nilai";
    }
}

// Array untuk fallback data lama (Bulan biasa)
$list_bulan_biasa = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Pastikan ID mitra dikirim
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: kegiatan.php?status=error&message=ID_mitra_tidak_ditemukan');
    exit;
}

$mitra_id = (int) $_GET['id'];
$user_role = $_SESSION['user_role'] ?? '';

try {
    // Ambil data mitra
    $sql_mitra = "SELECT nama_lengkap, alamat_detail, domisili_sama FROM mitra WHERE id = ?";
    $stmt_mitra = $koneksi->prepare($sql_mitra);
    $stmt_mitra->bind_param("i", $mitra_id);
    $stmt_mitra->execute();
    $mitra = $stmt_mitra->get_result()->fetch_assoc();
    $stmt_mitra->close();

    if (!$mitra) {
        header('Location: kegiatan.php?status=error&message=Data_mitra_tidak_ditemukan');
        exit;
    }

    // --- QUERY DATA HONOR (ANTI DUPLIKAT & FIX UNSIGNED ERROR) ---
    $sql_kegiatan = "
    SELECT
        hm.id AS honor_id,
        hm.mitra_survey_id,
        
        -- Ambil Data Periode dari tabel mitra_surveys
        ms.periode_jenis,
        ms.periode_nilai,
        
        -- SUBQUERY #1: Ambil Nama KEGIATAN
        (
            SELECT mk.nama
            FROM master_kegiatan mk
            WHERE mk.kode = (SELECT ms_inner.kegiatan_id FROM mitra_surveys ms_inner WHERE ms_inner.id = hm.mitra_survey_id LIMIT 1)
            -- FIX ERROR: Gunakan CAST AS SIGNED agar bisa menghitung selisih negatif
            ORDER BY ABS(CAST(mk.tahun AS SIGNED) - CAST(hm.tahun_pembayaran AS SIGNED)) ASC
            LIMIT 1
        ) AS nama_kegiatan,
        
        -- SUBQUERY #2: Ambil Nama Item
        (
            SELECT mi.nama_item
            FROM master_item mi
            WHERE mi.kode_unik = hm.item_kode_unik
            -- FIX ERROR: Gunakan CAST AS SIGNED
            ORDER BY ABS(CAST(mi.tahun AS SIGNED) - CAST(hm.tahun_pembayaran AS SIGNED)) ASC
            LIMIT 1 
        ) AS nama_item,
        
        -- SUBQUERY #3: Ambil Satuan
        (
            SELECT mi.satuan
            FROM master_item mi
            WHERE mi.kode_unik = hm.item_kode_unik
            -- FIX ERROR: Gunakan CAST AS SIGNED
            ORDER BY ABS(CAST(mi.tahun AS SIGNED) - CAST(hm.tahun_pembayaran AS SIGNED)) ASC
            LIMIT 1 
        ) AS satuan,

        hm.jumlah_satuan,
        hm.total_honor,
        hm.bulan_pembayaran,
        hm.tahun_pembayaran,
        hm.tanggal_input
        
    FROM honor_mitra hm
    -- JOIN ke mitra_surveys untuk ambil periode
    LEFT JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    
    WHERE hm.mitra_id = ?
    ORDER BY hm.tanggal_input DESC, hm.id DESC
    ";

    $stmt_kegiatan = $koneksi->prepare($sql_kegiatan);
    if (!$stmt_kegiatan) throw new Exception("Gagal prepare: " . $koneksi->error);
    
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

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Poppins', sans-serif; background: #f0f4f8; }
    .content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
    @media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
    .card { background: #fff; border-radius: 1rem; padding: 2.5rem; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
    .badge-green { background: #d1f7e3; color: #28a745; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; }
    .badge-red { background: #fce8e8; color: #dc3545; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
    thead th { background: #e2e8f0; color: #4a5568; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem; text-align: left; }
    tbody td { padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
    tbody tr:hover { background: #f9fafb; }
    .btn-delete { background: #ef4444; color: #fff; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 500; }
    .btn-delete:hover { background: #dc2626; }
    .btn-back { display: inline-flex; align-items: center; padding: 0.5rem; border-radius: 9999px; background-color: #e5e7eb; color: #4b5563; transition: background-color 0.2s, color 0.2s; }
    .btn-back:hover { background-color: #d1d5db; color: #111827; }
    .btn-back svg { height: 1rem; width: 1rem; }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center mb-6">
            <a href="kegiatan.php" class="btn-back mr-4" title="Kembali">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Detail Mitra</h1>
        </div>

        <div class="card space-y-8">
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Informasi Mitra</h2>
                <div class="space-y-4">
                    <div class="flex items-center space-x-4"><p class="text-gray-500 w-40">Nama Mitra:</p><p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($mitra['nama_lengkap']) ?></p></div>
                    <div class="flex items-center space-x-4"><p class="text-gray-500 w-40">Alamat:</p><p class="font-medium text-lg text-gray-900"><?= htmlspecialchars($mitra['alamat_detail'] ?? '-') ?></p></div>
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
                                    <th>Periode</th> 
                                    <th>Tahun</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;
                                foreach ($kegiatan_list as $keg): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($keg['nama_kegiatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['nama_item'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['satuan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($keg['jumlah_satuan'] ?? '-') ?></td>
                                        <td><?= number_format($keg['total_honor'] ?? 0, 0, ',', '.') ?></td>
                                        
                                        <td>
                                            <?php 
                                                if (!empty($keg['periode_jenis'])) {
                                                    echo htmlspecialchars(formatPeriode($keg['periode_jenis'], $keg['periode_nilai']));
                                                } else {
                                                    echo htmlspecialchars($list_bulan_biasa[(int)$keg['bulan_pembayaran']] ?? '-');
                                                }
                                            ?>
                                        </td>
                                        
                                        <td><?= htmlspecialchars($keg['tahun_pembayaran'] ?? '-') ?></td>
                                        <?php if ($has_access_for_action): ?>
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
                <div class="text-center text-gray-500 py-4">
                    <p>Mitra ini belum memiliki kegiatan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$koneksi->close();
include '../includes/footer.php';
?>