<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];
$has_access_for_action = !empty(array_intersect($user_roles, $allowed_roles_for_action));

// Ambil tahun dari filter
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Ambil daftar tahun unik
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_item ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
if (empty($daftar_tahun)) {
    $daftar_tahun[] = $tahun_filter;
} else if (!in_array($tahun_filter, $daftar_tahun)) {
    $tahun_filter = $daftar_tahun[0];
}

// LOGIKA PAGINATION
$items_per_page = 50;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$sql_count = "SELECT COUNT(id) AS total FROM master_item WHERE tahun = ?";
$stmt_count = $koneksi->prepare($sql_count);
$stmt_count->bind_param("i", $tahun_filter);
$stmt_count->execute();
$total_items = (int)$stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;
$stmt_count->close();

// Query utama untuk mengambil data hierarki per halaman
$sql_hierarchy = "SELECT
    mp.kode AS program_kode, mp.nama AS program_nama,
    mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
    mo.kode AS output_kode, mo.nama AS output_nama,
    mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
    mkom.kode AS komponen_kode, mkom.nama AS komponen_nama,
    msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
    ma.kode AS akun_kode, ma.nama AS akun_nama,
    mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
FROM master_item mi
LEFT JOIN master_akun ma ON mi.akun_id = ma.id
LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id
LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
LEFT JOIN master_output mo ON mso.output_id = mo.id
LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
LEFT JOIN master_program mp ON mk.program_id = mp.id
WHERE mi.tahun = ?
ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.nama_item ASC
LIMIT ? OFFSET ?";

$stmt_hierarchy = $koneksi->prepare($sql_hierarchy);
$stmt_hierarchy->bind_param("iii", $tahun_filter, $items_per_page, $offset);
$stmt_hierarchy->execute();
$result_hierarchy = $stmt_hierarchy->get_result();
$data_anggaran_page = [];
$item_kode_uniks_on_page = [];
while ($row = $result_hierarchy->fetch_assoc()) {
    $data_anggaran_page[] = $row;
    if (!empty($row['kode_unik'])) {
        $item_kode_uniks_on_page[] = $row['kode_unik'];
    }
}
$stmt_hierarchy->close();

// Query untuk mengambil data RPD hanya untuk item di halaman ini
$rpd_data = [];
if (!empty($item_kode_uniks_on_page)) {
    $placeholders = implode(',', array_fill(0, count($item_kode_uniks_on_page), '?'));
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $types = 'i' . str_repeat('s', count($item_kode_uniks_on_page));
    $params = array_merge([$tahun_filter], $item_kode_uniks_on_page);
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
    }
    $stmt_rpd->close();
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/*
================================================
REVISI CSS TOTAL UNTUK TAMPILAN MODERN
================================================
*/
:root {
    --primary-blue: #0A2E5D;
    --primary-blue-light: #E6EEF7;
    --border-color: #DEE2E6;
    --text-dark: #212529;
    --text-light: #6C757D;
    --background-light: #F8F9FA;
    --background-page: #F7F9FC;
    --warning-bg: #FFFBE6;
    --warning-border: #FFC107;
    --font-family-sans-serif: 'Inter', sans-serif;
    --border-radius: 0.5rem; /* 8px */
}

body {
    font-family: var(--font-family-sans-serif);
    background-color: var(--background-page);
}

.main-content { 
    padding: 30px; 
}

/* Header Halaman */
.page-header {
    background: #fff;
    padding: 20px 25px;
    border-radius: var(--border-radius);
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    margin-bottom: 25px;
}

.header-container { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    flex-wrap: wrap; 
    gap: 15px; 
    margin-bottom: 20px;
}
.section-title { 
    font-size:1.75rem; 
    font-weight:700; 
    margin:0; 
    color: var(--primary-blue); 
}

/* Filter Tahun */
.filter-container { 
    display: flex; 
    gap: 10px; 
    align-items: center; 
}
.filter-container label {
    margin-bottom: 0;
    font-weight: 500;
    color: var(--text-light);
}
.filter-container .btn { 
    border: 1px solid var(--border-color); 
    color: var(--primary-blue); 
    background-color: #fff; 
    border-radius: 6px; 
    padding: 6px 14px; 
    text-decoration: none; 
    font-size: 0.9rem; 
    font-weight: 500;
    transition: all 0.2s ease;
}
.filter-container .btn:hover {
    background-color: var(--primary-blue-light);
    border-color: var(--primary-blue);
}
.filter-container .btn.active { 
    background-color: var(--primary-blue); 
    color: #fff; 
    border-color: var(--primary-blue); 
}

/* Card Utama & Tabel */
.card { 
    background:#fff; 
    border: none;
    border-radius: var(--border-radius); 
    box-shadow: 0 4px 20px rgba(0,0,0,0.04); 
}

/* ================================================
REVISI UTAMA UNTUK SCROLLBAR
================================================
*/
.table-responsive {
    border: none;
    overflow-x: auto; /* Sudah ada, ini benar untuk scroll horizontal */

    /* --- TAMBAHAN KUNCI --- */
    max-height: 75vh;   /* Batasi tinggi kontainer, misal 75% dari tinggi layar (viewport height) */
    overflow-y: auto;   /* Tambahkan scroll vertikal jika konten (tabel) melebihi max-height */
    position: relative; /* Ini penting agar 'position: sticky' pada header tabel berfungsi di dalam kontainer ini */
}

/* Styling scrollbar agar lebih modern (opsional) */
.table-responsive::-webkit-scrollbar {
    height: 8px;
    width: 8px; /* Tambahkan lebar untuk scrollbar vertikal */
}
.table-responsive::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 4px;
}
.table-responsive::-webkit-scrollbar-thumb:hover {
    background-color: #a8b0bc;
}
.table-responsive::-webkit-scrollbar-track {
    background-color: #f1f1f1;
}

.rpd-table { 
    font-size: 0.85rem; 
    border-collapse: collapse;
    width: 100%;
}

/* Header Tabel */
.rpd-table thead th { 
    text-align: center; 
    vertical-align: middle; 
    background-color: var(--background-light); 
    position: sticky; 
    top: 0; 
    z-index: 2; /* Naikkan z-index agar di atas konten */
    border-bottom: 2px solid var(--border-color);
    padding: 12px 10px;
    font-weight: 600;
    color: var(--text-dark);
}

/* Body Tabel */
.rpd-table td {
    padding: 12px 10px;
    vertical-align: middle;
    border: none;
    border-bottom: 1px solid #EAECF0;
}
.rpd-table tr:last-child td {
    border-bottom: none;
}
.rpd-table .uraian-col { text-align: left; min-width: 400px; }
.rpd-table .pagu-col { text-align: right; min-width: 130px; }
.rpd-table .bulan-col { text-align: right; min-width: 110px; }

/* Styling Hierarki yang Diperbarui */
.hierarchy-row td { 
    font-weight:600; 
    background-color: var(--background-light); 
}
.level-program { font-size: 1.05em; color: #000000ff; }
.level-kegiatan { padding-left:25px !important; color: #154360;}
.level-output { padding-left:50px !important; color: #1F618D;}
.level-sub_output { padding-left:75px !important; color: #2980B9;} /* Diperbaiki */
.level-komponen { padding-left:100px !important; color: #5499C7;}
.level-sub_komponen { padding-left:125px !important; color: #7f8c8d;} /* Diperbaiki */
.level-akun { padding-left:150px !important; font-style: italic; color: #27AE60; }
.level-item { font-weight:normal; padding-left:175px !important; }
.level-item:hover { background-color: #fcfcfd; }

.warning-row td { 
    background-color: var(--warning-bg) !important; 
}
.warning-row td:first-child { 
    border-left: 4px solid var(--warning-border); 
}

/* Footer & Pagination */
.card-footer {
    border-top: 1px solid var(--border-color);
    background-color: #fff;
    padding: 10px 25px;
}
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.pagination-info { font-size: 0.9rem; color: var(--text-light); }
.pagination { margin: 0; display: flex; gap: 8px; }

.pagination .page-item .page-link {
    display: flex; justify-content: center; align-items: center;
    width: 38px; height: 38px;
    border: 1px solid var(--border-color);
    border-radius: 6px !important;
    background-color: #fff;
    color: var(--text-dark);
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
}
.pagination .page-item .page-link:hover {
    border-color: var(--primary-blue);
    background-color: var(--primary-blue-light);
}
.pagination .page-item.active .page-link {
    background-color: var(--primary-blue);
    color: #fff;
    border-color: var(--primary-blue);
}
.pagination .page-item.disabled .page-link {
    background-color: var(--background-light);
    color: #adb5bd;
    cursor: not-allowed;
}

/* FIX KOMPREHENSIF UNTUK MENGHILANGKAN TITIK/SIMBOL */
.pagination .page-item {
    list-style-type: none !important;
    list-style: none !important;
}
.pagination .page-item .page-link::before,
.pagination .page-item .page-link::after {
    content: none !important;
    display: none !important;
}
</style>

<main class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <div class="header-container">
                <h2 class="section-title">Laporan RPD - Tahun <?= $tahun_filter ?></h2>

            </div>
            <div class="filter-container">
                <label>Lihat Tahun:</label>
                <?php foreach ($daftar_tahun as $th): ?>
                    <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'active' : '' ?>"><?= $th ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="table-responsive">
                <table class="table rpd-table">
                    <thead class="thead-light">
                        <tr>
                            <th rowspan="2" class="uraian-col">Uraian Anggaran</th>
                            <th rowspan="2" class="pagu-col">Total Pagu</th>
                            <th rowspan="2" class="pagu-col">Sisa Pagu</th>
                            <th colspan="12">Rencana Penarikan per Bulan</th>
                        </tr>
                        <tr>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <th><?= DateTime::createFromFormat('!m', $i)->format('M') ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data_anggaran_page)):
                            $printed_headers = [];
                            foreach ($data_anggaran_page as $row):
                                $levels = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
                                foreach ($levels as $level) {
                                    $kode = $row[$level . '_kode'];
                                    $nama = $row[$level . '_nama'];
                                    
                                    if (!empty($nama) && (!isset($printed_headers[$level]) || $printed_headers[$level] !== $nama)) {
                                        $header_text = "<b>" . htmlspecialchars($kode) . "</b> &nbsp;" . htmlspecialchars($nama);
                                        echo '<tr class="hierarchy-row"><td colspan="15" class="level-'.$level.'">' . $header_text . '</td></tr>';
                                        
                                        $printed_headers[$level] = $nama;
                                        
                                        $child_levels_to_reset = array_slice($levels, array_search($level, $levels) + 1);
                                        foreach ($child_levels_to_reset as $child_level) {
                                            unset($printed_headers[$child_level]);
                                        }
                                    }
                                }
                                
                                $kode_unik_item = $row['kode_unik'];
                                $item_total_rpd = isset($rpd_data[$kode_unik_item]) ? array_sum($rpd_data[$kode_unik_item]) : 0;
                                $sisa_pagu = $row['pagu'] - $item_total_rpd;
                                $row_class = ($sisa_pagu != 0) ? 'warning-row' : '';
                        ?>
                                <tr class="<?= $row_class ?>">
                                    <td class="level-item uraian-col"><?= htmlspecialchars($row['item_nama']) ?></td>
                                    <td class="pagu-col">Rp <?= number_format($row['pagu'], 0, ',', '.') ?></td>
                                    <td class="pagu-col">Rp <?= number_format($sisa_pagu, 0, ',', '.') ?></td>
                                    <?php for ($bulan = 1; $bulan <= 12; $bulan++): 
                                        $jumlah = $rpd_data[$kode_unik_item][$bulan] ?? 0;
                                    ?>
                                        <td class="bulan-col">Rp <?= number_format($jumlah, 0, ',', '.') ?></td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="15" class="text-center text-muted p-5">Tidak ada data anggaran untuk tahun <?= $tahun_filter ?>.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 0): ?>
            <div class="card-footer">
                <div class="pagination-container">
                    <div class="pagination-info">
                        Halaman <strong><?= $current_page ?></strong> dari <strong><?= $total_pages ?></strong> (Total <?= $total_items ?> item)
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navigasi Halaman">
                        <ul class="pagination">
                            <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tahun=<?= $tahun_filter ?>&page=<?= $current_page - 1 ?>" aria-label="Sebelumnya">&lt;</a>
                            </li>
                            <?php
                                $range = 2;
                                for ($i = 1; $i <= $total_pages; $i++) {
                                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                                        echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="?tahun=' . $tahun_filter . '&page=' . $i . '">' . $i . '</a></li>';
                                    }
                                }
                            ?>
                            <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tahun=<?= $tahun_filter ?>&page=<?= $current_page + 1 ?>" aria-label="Selanjutnya">&gt;</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>