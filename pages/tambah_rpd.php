<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. Cek Hak Akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'ketua_tim'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// 2. Tentukan Tahun Filter (Default tahun ini)
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$today = date("Y-m-d");

// 3. Cek Status Waktu untuk Tahun Terpilih
$status_akses = 'open'; // Default
$pesan_akses = '';
$waktu_rpd = null;

$stmt_waktu = $koneksi->prepare("SELECT mulai, selesai FROM rpd_setting_waktu WHERE tahun = ? LIMIT 1");
$stmt_waktu->bind_param("i", $tahun_filter);
$stmt_waktu->execute();
$result_waktu = $stmt_waktu->get_result();
$waktu_rpd = $result_waktu->fetch_assoc();
$stmt_waktu->close();

// Logika Penentuan Status
if (!$waktu_rpd) {
    $status_akses = 'not_set'; // Belum diatur admin
} elseif ($today < $waktu_rpd['mulai'] || $today > $waktu_rpd['selesai']) {
    $status_akses = 'closed'; // Sudah tutup atau belum mulai
}

// 4. Ambil Daftar Tahun Existing (Untuk Navigasi Tombol)
// PENTING: Ini ditaruh di atas agar tombol tetap muncul meski akses ditutup
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_output ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
// Pastikan tahun filter masuk dalam daftar jika belum ada
if (!in_array($tahun_filter, $daftar_tahun)) {
    array_unshift($daftar_tahun, $tahun_filter);
    $daftar_tahun = array_unique($daftar_tahun);
    rsort($daftar_tahun); // Urutkan descending
}

// 5. Logika Step Flow (Langkah 1 atau 2)
$show_item_table = false;
$selected_outputs_ids = [];

if ($status_akses === 'open') {
    // Hanya proses logika item jika akses terbuka
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['selected_outputs'])) {
        $selected_outputs_ids = array_map('intval', $_POST['selected_outputs']);
        $show_item_table = true;
    } elseif (!empty($_GET['selected_outputs'])) {
        $selected_outputs_ids = array_map('intval', $_GET['selected_outputs']);
        $show_item_table = true;
    }
    
    // --- LOGIKA PENGAMBILAN DATA ITEM (Sama seperti sebelumnya) ---
    if ($show_item_table) {
        if (empty($selected_outputs_ids)) {
            $show_item_table = false;
        } else {
            // Query Item Hierarchy
            $placeholders = implode(',', array_fill(0, count($selected_outputs_ids), '?'));
            $sql_items = "SELECT
                mp.kode AS program_kode, mp.nama AS program_nama,
                mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
                mo.id AS output_id, mo.kode AS output_kode, mo.nama AS output_nama,
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
            WHERE mi.tahun = ? AND mo.id IN ($placeholders)
            ORDER BY mp.kode, mk.kode, mo.kode, mso.kode, mkom.kode, msk.kode, ma.kode, mi.nama_item ASC";

            $stmt_items = $koneksi->prepare($sql_items);
            $types = 'i' . str_repeat('i', count($selected_outputs_ids));
            $params = array_merge([$tahun_filter], $selected_outputs_ids);
            // Fix bind_param call array
            $stmt_items->bind_param($types, ...$params);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            $flat_data = [];
            while ($row = $result_items->fetch_assoc()) {
                $flat_data[] = $row;
            }
            $stmt_items->close();

            // Ambil Data RPD Existing
            $rpd_data = [];
            $stmt_rpd = $koneksi->prepare("SELECT kode_unik_item, SUM(jumlah) as total_rpd FROM rpd WHERE tahun = ? GROUP BY kode_unik_item");
            $stmt_rpd->bind_param("i", $tahun_filter);
            $stmt_rpd->execute();
            $result_rpd = $stmt_rpd->get_result();
            while ($r = $result_rpd->fetch_assoc()) {
                $rpd_data[$r['kode_unik_item']] = $r['total_rpd'];
            }
            $stmt_rpd->close();

            // Susun Hierarki
            $hierarki = [];
            foreach ($flat_data as $row) {
                $p = $row['program_kode']; $k = $row['kegiatan_kode']; $o = $row['output_kode'];
                $so = $row['sub_output_kode']; $kom = $row['komponen_kode']; $sk = $row['sub_komponen_kode']; $a = $row['akun_kode'];
                if (!$p) continue;
                
                if (!isset($hierarki[$p])) $hierarki[$p] = ['nama' => $row['program_nama'], 'children' => []];
                if (!isset($hierarki[$p]['children'][$k])) $hierarki[$p]['children'][$k] = ['nama' => $row['kegiatan_nama'], 'children' => []];
                if (!isset($hierarki[$p]['children'][$k]['children'][$o])) $hierarki[$p]['children'][$k]['children'][$o] = ['nama' => $row['output_nama'], 'children' => []];
                if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so] = ['nama' => $row['sub_output_nama'], 'children' => []];
                if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom] = ['nama' => $row['komponen_nama'], 'children' => []];
                if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk] = ['nama' => $row['sub_komponen_nama'], 'children' => []];
                if (!isset($hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a])) $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a] = ['nama' => $row['akun_nama'], 'items' => []];

                $hierarki[$p]['children'][$k]['children'][$o]['children'][$so]['children'][$kom]['children'][$sk]['children'][$a]['items'][] = $row;
            }
        }
    } else {
        // Ambil Output List (Langkah 1)
        $stmt = $koneksi->prepare("SELECT mo.id, mo.kode, mo.nama FROM master_output mo WHERE mo.tahun = ? ORDER BY mo.kode ASC");
        $stmt->bind_param("i", $tahun_filter);
        $stmt->execute();
        $result_outputs = $stmt->get_result();
        $outputs = [];
        while ($row = $result_outputs->fetch_assoc()) $outputs[] = $row;
        $stmt->close();
    }
}
?>

<style>
/* Root variables */
:root { --primary-blue: #0A2E5D; --secondary-blue: #007bff; --light-blue-bg: #f8f9fa; --border-color: #dee2e6; }
.main-content { padding: 30px; background-color: var(--light-blue-bg); min-height: 80vh; }
.section-title { font-size: 1.8rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 25px; }
.card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 6px 25px rgba(0, 0, 0, 0.07); padding: 25px; }
.year-buttons { display: flex; gap: 5px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
.output-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 20px; }
.form-check-label { display: block; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s; }
.form-check-label:hover { background-color: #f8f9fa; border-color: var(--secondary-blue); }
.form-check-input { display: none; }
.form-check-input:checked + .form-check-label { background-color: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }
.btn-primary { background: var(--primary-blue); border: none; padding: 12px 25px; font-weight: 600; }
.data-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
.data-table th { background:#f7f9fc; font-weight:600; text-align: center; }
.hierarchy-row td { background-color: #f8f9fa; }
/* Alert Styles */
.alert-custom { border-radius: 12px; padding: 30px; text-align: center; border: 1px solid transparent; }
.alert-denied { background-color: #fff5f5; border-color: #feb2b2; color: #c53030; }
.alert-warning { background-color: #fffaf0; border-color: #fbd38d; color: #c05621; }
</style>

<main class="main-content">
  <div class="container-fluid">
    <h2 class="section-title">Tambah/Edit RPD - Tahun <?= htmlspecialchars($tahun_filter) ?></h2>

    <div class="card mb-4">
        <div class="year-buttons">
          <strong class="me-2">Pilih Tahun Anggaran:</strong>
          <?php foreach ($daftar_tahun as $th): ?>
            <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $th ?>
            </a>
          <?php endforeach; ?>
        </div>
    </div>

    <?php if ($status_akses === 'not_set'): ?>
        <div class="alert-custom alert-warning">
            <h2><i class="fas fa-exclamation-circle"></i> Jadwal Belum Diatur</h2>
            <p>Admin Dipaku belum mengatur jadwal pengisian RPD untuk tahun <b><?= $tahun_filter ?></b>.</p>
            <p>Silakan pilih tahun lain di atas atau hubungi Admin.</p>
        </div>

    <?php elseif ($status_akses === 'closed'): ?>
        <div class="alert-custom alert-denied">
            <h2><i class="fas fa-lock"></i> Pengisian RPD Ditutup</h2>
            <p>Maaf, pengisian RPD untuk tahun <b><?= $tahun_filter ?></b> saat ini ditutup.</p>
            <hr style="width: 50%; margin: 15px auto; opacity: 0.3;">
            <p>
                Jadwal Pengisian: <br>
                <strong><?= date("d M Y", strtotime($waktu_rpd['mulai'])) ?></strong> s/d 
                <strong><?= date("d M Y", strtotime($waktu_rpd['selesai'])) ?></strong>
            </p>
            <p>Silakan hubungi <b>Admin Dipaku</b> jika memerlukan perpanjangan waktu.</p>
        </div>

    <?php else: ?>
        <?php if (!$show_item_table): ?>
            <div class="card">
                <h5>Langkah 1: Pilih Output</h5>
                <p class="text-muted">Pilih satu atau beberapa Output untuk melanjutkan.</p>
                <form action="tambah_rpd.php?tahun=<?= $tahun_filter ?>" method="POST">
                    <div class="output-grid">
                        <?php if (!empty($outputs)): ?>
                            <?php foreach ($outputs as $output): ?>
                                <div class="form-check">
                                    <input id="output_<?= $output['id'] ?>" class="form-check-input" type="checkbox" name="selected_outputs[]" value="<?= $output['id'] ?>"
                                    <?= in_array($output['id'], $selected_outputs_ids) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="output_<?= $output['id'] ?>">
                                        <b><?= htmlspecialchars($output['kode']) ?></b><br>
                                        <span><?= htmlspecialchars($output['nama']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12"><p class="text-muted">Tidak ada data Output ditemukan untuk tahun <?= $tahun_filter ?>.</p></div>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary" <?= empty($outputs) ? 'disabled' : '' ?>>
                        Lanjutkan <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Langkah 2: Isi RPD per Item</h5>
                    <a href="tambah_rpd.php?tahun=<?= urlencode($tahun_filter) ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Ganti Output
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Uraian Anggaran</th>
                                <th style="width:150px;">Pagu</th>
                                <th style="width:150px;">Total RPD</th>
                                <th style="width:140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($hierarki)): ?>
                            <?php foreach ($hierarki as $p_kode => $program): ?>
                                <tr class="hierarchy-row"><td colspan="4"><strong><?= htmlspecialchars($p_kode) ?></strong> - <?= htmlspecialchars($program['nama']) ?></td></tr>
                                <?php foreach ($program['children'] as $k_kode => $kegiatan): ?>
                                    <tr class="hierarchy-row"><td colspan="4" style="padding-left:20px;"><strong><?= htmlspecialchars($k_kode) ?></strong> - <?= htmlspecialchars($kegiatan['nama']) ?></td></tr>
                                    <?php foreach ($kegiatan['children'] as $o_kode => $output): ?>
                                        <tr class="hierarchy-row"><td colspan="4" style="padding-left:40px;"><strong><?= htmlspecialchars($o_kode) ?></strong> - <?= htmlspecialchars($output['nama']) ?></td></tr>
                                        <?php foreach ($output['children'] as $so_kode => $sub_output): ?>
                                             <tr class="hierarchy-row"><td colspan="4" style="padding-left:60px;"><strong><?= htmlspecialchars($so_kode) ?></strong> - <?= htmlspecialchars($sub_output['nama']) ?></td></tr>
                                             <?php foreach ($sub_output['children'] as $kom_kode => $komponen): ?>
                                                <tr class="hierarchy-row"><td colspan="4" style="padding-left:80px;"><strong><?= htmlspecialchars($kom_kode) ?></strong> - <?= htmlspecialchars($komponen['nama']) ?></td></tr>
                                                <?php foreach ($komponen['children'] as $sk_kode => $sub_komponen): ?>
                                                    <tr class="hierarchy-row"><td colspan="4" style="padding-left:100px;"><strong><?= htmlspecialchars($sk_kode) ?></strong> - <?= htmlspecialchars($sub_komponen['nama']) ?></td></tr>
                                                    <?php foreach ($sub_komponen['children'] as $a_kode => $akun): ?>
                                                        <tr class="hierarchy-row"><td colspan="4" style="padding-left:120px;"><strong><?= htmlspecialchars($a_kode) ?></strong> - <?= htmlspecialchars($akun['nama']) ?></td></tr>
                                                        
                                                        <?php foreach ($akun['items'] as $item): 
                                                            $total_rpd_item = (float)($rpd_data[$item['kode_unik']] ?? 0);
                                                            $rpd_exists = isset($rpd_data[$item['kode_unik']]);
                                                            $link = "isi_rpd_per_item.php?kode_unik=" . urlencode($item['kode_unik']) . "&" . http_build_query(['selected_outputs' => $selected_outputs_ids]) . "&tahun=" . urlencode($tahun_filter);
                                                        ?>
                                                            <tr>
                                                                <td style="padding-left:140px;"><?= htmlspecialchars($item['item_nama']) ?></td>
                                                                <td class="text-end"><?= number_format($item['pagu'], 0, ',', '.') ?></td>
                                                                <td class="text-end"><?= number_format($total_rpd_item, 0, ',', '.') ?></td>
                                                                <td class="text-center">
                                                                    <a href="<?= $link ?>" class="btn btn-sm <?= $rpd_exists ? 'btn-warning' : 'btn-primary' ?>">
                                                                        <i class="fas <?= $rpd_exists ? 'fa-edit' : 'fa-plus' ?>"></i>
                                                                        <?= $rpd_exists ? 'Edit' : 'Isi' ?>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?> <?php endforeach; ?> <?php endforeach; ?> <?php endforeach; ?> <?php endforeach; ?> <?php endforeach; ?> <?php endforeach; ?> <?php endforeach; ?> <?php else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Tidak ada data ditemukan.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?> </div>
</main>

<?php include '../includes/footer.php'; ?>