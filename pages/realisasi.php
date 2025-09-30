<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku', 'admin_tu'];
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

// =========================================================================
// LOGIKA PAGINATION
// =========================================================================
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

// Query untuk mengambil data RPD & Realisasi hanya untuk item di halaman ini
$rpd_data = [];
$realisasi_data = [];
if (!empty($item_kode_uniks_on_page)) {
    $placeholders = implode(',', array_fill(0, count($item_kode_uniks_on_page), '?'));
    $types = 'i' . str_repeat('s', count($item_kode_uniks_on_page));
    $params = array_merge([$tahun_filter], $item_kode_uniks_on_page);

    // Ambil RPD
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
    }
    $stmt_rpd->close();
    
    // Ambil Realisasi
    $sql_realisasi = "SELECT kode_unik_item, bulan, jumlah_realisasi FROM realisasi WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_realisasi = $koneksi->prepare($sql_realisasi);
    $stmt_realisasi->bind_param($types, ...$params);
    $stmt_realisasi->execute();
    $result_realisasi = $stmt_realisasi->get_result();
    while ($row = $result_realisasi->fetch_assoc()) {
        $realisasi_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah_realisasi'];
    }
    $stmt_realisasi->close();
}
?>

<style>
/* REVISI: Penyesuaian CSS Global & Tabel */
:root { 
    --primary-blue: #0A2E5D; 
    --light-blue-bg: #f8f9fa; 
    --border-color: #dee2e6;
}
.main-content { padding: 30px; background-color: #f7f9fc; }
.header-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;}
.section-title { font-size: 1.8rem; font-weight: 700; color: var(--primary-blue); margin: 0; }
.card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 6px 25px rgba(0,0,0,0.07); }
.data-table { font-size: 0.8rem; white-space: nowrap; } /* Ukuran font disesuaikan */
.data-table thead th { text-align: center; vertical-align: middle; background-color: var(--light-blue-bg); position: sticky; top: 0; z-index: 1;}
.data-table .uraian-col { text-align: left; min-width: 400px; position: sticky; left: 0; background-color: #fff; z-index: 2;}
.data-table tbody .uraian-col { box-shadow: 5px 0 5px -5px rgba(0,0,0,0.1); } /* Shadow pemisah */
.data-table .pagu-col { text-align: right; min-width: 130px; }
.data-table .bulan-col { text-align: right; min-width: 120px; }
.sub-col { font-size: 0.75rem; font-weight: 600; color: #666; }
.rpd-val { color: #007bff; }
.realisasi-val { color: #28a745; font-weight: bold; }

/* REVISI: STYLING HIERARKI DENGAN INDENTASI & WARNA */
.hierarchy-row td { font-weight:bold; background-color: #f8f9fa; }
.level-program { font-size: 1.1em; color: #0A2E5D; }
.level-kegiatan { padding-left: 25px !important; color: #154360; }
.level-output { padding-left: 50px !important; color: #1F618D; }
.level-sub_output { padding-left: 75px !important; color: #2980B9; }
.level-komponen { padding-left: 100px !important; color: #5499C7; }
.level-sub_komponen { padding-left: 125px !important; color: #7f8c8d; }
.level-akun { padding-left: 150px !important; color: #27AE60; font-style: italic; }
.level-item { padding-left: 175px !important; font-weight: normal; }

/* REVISI: TAMPILAN PAGINATION MODERN */
.pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-top: 1px solid var(--border-color); }
.pagination { margin: 0; display: flex; gap: 5px; }
.pagination .page-item .page-link {
    display: flex; justify-content: center; align-items: center;
    min-width: 38px; height: 38px; padding: 0 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px !important;
    background-color: #fff; color: var(--primary-blue);
    font-weight: 500; text-decoration: none;
    transition: all 0.2s ease-in-out;
}
.pagination .page-item .page-link:hover { border-color: var(--primary-blue); background-color: #e9ecef; }
.pagination .page-item.active .page-link { background-color: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }
.pagination .page-item.disabled .page-link { background-color: #f8f9fa; color: #adb5bd; cursor: not-allowed; }
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
    <div class="header-container">
      <h2 class="section-title">Laporan Realisasi Anggaran - Tahun <?= $tahun_filter ?></h2>
      <div>
        <?php if ($has_access_for_action): ?>
            <a href="tambah_realisasi.php" class="btn btn-primary"><i class="fas fa-upload mr-2"></i>Upload Realisasi</a>
        <?php endif; ?>
        <a href="../proses/cetak_realisasi_pdf.php?tahun=<?= $tahun_filter ?>" class="btn btn-secondary" target="_blank"><i class="fas fa-file-pdf mr-2"></i>Download PDF</a>
      </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="form-inline">
                <label for="tahun" class="mr-2 font-weight-bold">Lihat Tahun:</label>
                <select class="form-control col-md-2" id="tahun" name="tahun" onchange="this.form.submit()">
                    <?php foreach ($daftar_tahun as $th): ?>
                        <option value="<?= $th ?>" <?= ($th == $tahun_filter) ? 'selected' : '' ?>><?= $th ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    
    <div class="card">
      <div class="card-body p-0"> <div class="table-responsive">
          <table class="table table-bordered data-table mb-0"> <thead>
              <tr>
                <th class="uraian-col" rowspan="2">Uraian Anggaran</th>
                <th class="pagu-col" rowspan="2">Jumlah Pagu</th>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                  <th colspan="2"><?= DateTime::createFromFormat('!m', $i)->format('F') ?></th>
                <?php endfor; ?>
              </tr>
              <tr>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                  <th class="bulan-col sub-col">RPD</th>
                  <th class="bulan-col sub-col">Realisasi</th>
                <?php endfor; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($data_anggaran_page)):
                  $printed_headers = [];
                  foreach ($data_anggaran_page as $row):
                      // Logika untuk menampilkan hierarki
                      $levels = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
                      foreach ($levels as $level) {
                          $kode = $row[$level . '_kode'];
                          $nama = $row[$level . '_nama'];
                          if (!empty($nama) && (!isset($printed_headers[$level]) || $printed_headers[$level] !== $nama)) {
                              $header_text = "<b>" . htmlspecialchars($kode) . "</b> - " . htmlspecialchars($nama);
                              // 2 (Pagu) + 12*2 (Bulan) = 26 Kolom
                              echo '<tr class="hierarchy-row"><td colspan="26" class="level-'.$level.'">' . $header_text . '</td></tr>';
                              $printed_headers[$level] = $nama;
                              // Reset level di bawahnya
                              $child_levels_to_reset = array_slice($levels, array_search($level, $levels) + 1);
                              foreach ($child_levels_to_reset as $child_level) {
                                  unset($printed_headers[$child_level]);
                              }
                          }
                      }
              ?>
                      <tr>
                        <td class="level-item uraian-col"><?= htmlspecialchars($row['item_nama']) ?></td>
                        <td class="pagu-col">Rp <?= number_format($row['pagu'], 0, ',', '.') ?></td>
                        <?php for ($bulan = 1; $bulan <= 12; $bulan++): 
                          $rpd_val = $rpd_data[$row['kode_unik']][$bulan] ?? 0;
                          $realisasi_val = $realisasi_data[$row['kode_unik']][$bulan] ?? 0;
                        ?>
                          <td class="bulan-col rpd-val">Rp <?= number_format($rpd_val, 0, ',', '.') ?></td>
                          <td class="bulan-col realisasi-val">Rp <?= number_format($realisasi_val, 0, ',', '.') ?></td>
                        <?php endfor; ?>
                      </tr>
                    <?php endforeach;
                  else: ?>
                  <tr><td colspan="26" class="text-center text-muted p-5">Tidak ada data anggaran ditemukan untuk tahun ini.</td></tr>
                <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($total_pages > 0): ?>
        <div class="pagination-container">
            <div class="text-muted">
                Halaman <strong><?= $current_page ?></strong> dari <strong><?= $total_pages ?></strong> (Total <?= $total_items ?> item)
            </div>
            
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tahun=<?= $tahun_filter ?>&page=<?= $current_page - 1 ?>" aria-label="Sebelumnya">&lt;</a>
                    </li>
                    <?php
                    $range = 1;
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                            if (isset($dots) && $dots) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                $dots = false;
                            }
                            echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="?tahun=' . $tahun_filter . '&page=' . $i . '">' . $i . '</a></li>';
                        } elseif (!isset($dots) || $dots == false) {
                            $dots = true;
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
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include '../includes/footer.php'; ?>