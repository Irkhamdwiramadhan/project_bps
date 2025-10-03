<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// ... (Seluruh logika PHP Anda tetap sama persis) ...
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku', 'admin_tu'];
$has_access_for_action = !empty(array_intersect($user_roles, $allowed_roles_for_action));
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
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
$rpd_data = [];
$realisasi_data = [];
if (!empty($item_kode_uniks_on_page)) {
    $placeholders = implode(',', array_fill(0, count($item_kode_uniks_on_page), '?'));
    $types = 'i' . str_repeat('s', count($item_kode_uniks_on_page));
    $params = array_merge([$tahun_filter], $item_kode_uniks_on_page);
    $sql_rpd = "SELECT kode_unik_item, bulan, jumlah FROM rpd WHERE tahun = ? AND kode_unik_item IN ($placeholders)";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $stmt_rpd->bind_param($types, ...$params);
    $stmt_rpd->execute();
    $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']][$row['bulan']] = $row['jumlah'];
    }
    $stmt_rpd->close();
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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ... (CSS utama lainnya tetap sama) ... */
:root {
    --primary-blue: #0A2E5D;
    --primary-blue-light: #E6EEF7;
    --border-color: #DEE2E6;
    --text-dark: #212529;
    --text-light: #6C757D;
    --background-light: #F8F9FA;
    --background-page: #F7F9FC;
    --font-family-sans-serif: 'Inter', sans-serif;
    --border-radius: 0.5rem;
}

body {
    font-family: var(--font-family-sans-serif);
    background-color: var(--background-page);
}

.main-content { padding: 30px; }
.page-header { background: #fff; padding: 20px 25px; border-radius: var(--border-radius); box-shadow: 0 4px 20px rgba(0,0,0,0.04); margin-bottom: 25px; }
.header-container { display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
.header-actions { display: flex; align-items: center; gap: 10px; }
.section-title { font-size:1.75rem; font-weight:700; margin:0; color: var(--primary-blue); }
.filter-container { display: flex; gap: 10px; align-items: center; }
.filter-container label { margin-bottom: 0; font-weight: 500; color: var(--text-light); }
.filter-container .btn { border: 1px solid var(--border-color); color: var(--primary-blue); background-color: #fff; border-radius: 6px; padding: 6px 14px; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: all 0.2s ease; }
.filter-container .btn:hover { background-color: var(--primary-blue-light); border-color: var(--primary-blue); }
.filter-container .btn.active { background-color: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }
.card { background:#fff; border: none; border-radius: var(--border-radius); box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
.table-wrapper { position: relative; }
.table-scroll-btn { position: absolute; top: 50%; transform: translateY(-50%); z-index: 10; background-color: rgba(255, 255, 255, 0.9); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.2s ease; }
.table-scroll-btn:hover { background-color: #fff; transform: translateY(-50%) scale(1.1); }
.table-scroll-btn i { color: var(--primary-blue); }
#scroll-left-btn { left: 15px; }
#scroll-right-btn { right: 15px; }
.table-responsive { max-height: 75vh !important; overflow: auto !important; }
.table-responsive::-webkit-scrollbar { height: 8px; width: 8px; }
.table-responsive::-webkit-scrollbar-thumb { background-color: #d1d5db; border-radius: 4px; }
.table-responsive::-webkit-scrollbar-thumb:hover { background-color: #a8b0bc; }
.table-responsive::-webkit-scrollbar-track { background-color: #f1f1f1; }

/* REVISI NAMA KELAS */
.realisasi-table { font-size: 0.8rem; border-collapse: collapse; width: 100%; }
.realisasi-table thead th { text-align: center; vertical-align: middle; background-color: var(--background-light); position: sticky; top: 0; z-index: 2; border-bottom: 2px solid var(--border-color); padding: 10px 8px; font-weight: 600; color: var(--text-dark); }
.realisasi-table .uraian-col { text-align: left; min-width: 400px; position: sticky; left: 0; z-index: 1; background-color: #fff; }
.realisasi-table tbody .uraian-col { box-shadow: 5px 0 5px -5px rgba(0,0,0,0.1); }
.realisasi-table td { padding: 10px 8px; vertical-align: middle; border: none; border-bottom: 1px solid #EAECF0; }
.realisasi-table tr:last-child td { border-bottom: none; }
.realisasi-table .pagu-col { text-align: right; min-width: 130px; }
.realisasi-table .bulan-col { text-align: right; min-width: 120px; }

.sub-col { font-size: 0.75rem; font-weight: 600; color: #6C757D; }
.rpd-val { color: #007bff; }
.realisasi-val { color: #28a745; font-weight: bold; }
.hierarchy-row td { font-weight:600; background-color: var(--background-light); }
.level-program { font-size: 1.05em; color: #000000ff; }
.level-kegiatan { padding-left:25px !important; color: #154360;}
.level-output { padding-left:50px !important; color: #1F618D;}
.level-sub_output { padding-left:75px !important; color: #2980B9;}
.level-komponen { padding-left:100px !important; color: #5499C7;}
.level-sub_komponen { padding-left:125px !important; color: #7f8c8d;}
.level-akun { padding-left:150px !important; font-style: italic; color: #27AE60; }
.level-item { font-weight:normal; padding-left:175px !important; }
.level-item:hover { background-color: #fcfcfd; }
.card-footer { border-top: 1px solid var(--border-color); background-color: #fff; padding: 10px 25px; border-bottom-left-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius); }
.pagination-container { display: flex; justify-content: space-between; align-items: center; }
.pagination-info { font-size: 0.9rem; color: var(--text-light); }
.pagination { margin: 0; display: flex; gap: 8px; }
.pagination .page-item .page-link { display: flex; justify-content: center; align-items: center; width: 38px; height: 38px; border: 1px solid var(--border-color); border-radius: 6px !important; background-color: #fff; color: var(--text-dark); font-weight: 500; text-decoration: none; transition: all 0.2s ease-in-out; }
.pagination .page-item .page-link:hover { border-color: var(--primary-blue); background-color: var(--primary-blue-light); }
.pagination .page-item.active .page-link { background-color: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }
.pagination .page-item.disabled .page-link { background-color: var(--background-light); color: #adb5bd; cursor: not-allowed; }
.pagination .page-item { list-style-type: none !important; }
.pagination .page-item .page-link::before,
.pagination .page-item .page-link::after { content: none !important; }
</style>

<main class="main-content">
  <div class="container-fluid">
    <div class="page-header">
        <div class="header-container">
            <h2 class="section-title">Laporan Realisasi Anggaran - <?= $tahun_filter ?></h2>

        </div>
        <div class="filter-container">
            <label>Lihat Tahun:</label>
            <?php foreach ($daftar_tahun as $th): ?>
                <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'active' : '' ?>"><?= $th ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="card">
      <div class="table-wrapper">
        <div class="table-responsive">
            <table class="table realisasi-table"> 
              <thead class="thead-light">
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
                      $levels = ['program', 'kegiatan', 'output', 'sub_output', 'komponen', 'sub_komponen', 'akun'];
                      foreach ($levels as $level) {
                          $kode = $row[$level . '_kode'];
                          $nama = $row[$level . '_nama'];
                          if (!empty($nama) && (!isset($printed_headers[$level]) || $printed_headers[$level] !== $nama)) {
                              $header_text = "<b>" . htmlspecialchars($kode) . "</b> &nbsp;" . htmlspecialchars($nama);
                              echo '<tr class="hierarchy-row"><td colspan="26" class="level-'.$level.'">' . $header_text . '</td></tr>';
                              $printed_headers[$level] = $nama;
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

        <button id="scroll-left-btn" class="table-scroll-btn" title="Geser ke Kiri"><i class="fas fa-arrow-left"></i></button>
        <button id="scroll-right-btn" class="table-scroll-btn" title="Geser ke Kanan"><i class="fas fa-arrow-right"></i></button>

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
                      $range = 1;
                      $dots = false;
                      for ($i = 1; $i <= $total_pages; $i++) {
                          if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                              if ($dots) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                  $dots = false;
                              }
                              echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="?tahun=' . $tahun_filter . '&page=' . $i . '">' . $i . '</a></li>';
                          } elseif (!$dots) {
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
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const scrollLeftBtn = document.getElementById('scroll-left-btn');
        const scrollRightBtn = document.getElementById('scroll-right-btn');
        const tableContainer = document.querySelector('.table-responsive');

        if (scrollLeftBtn && scrollRightBtn && tableContainer) {
            const scrollAmount = 400;
            scrollRightBtn.addEventListener('click', function() {
                tableContainer.scrollLeft += scrollAmount;
            });
            scrollLeftBtn.addEventListener('click', function() {
                tableContainer.scrollLeft -= scrollAmount;
            });
            console.log("Skrip scroll final (dengan penundaan) berhasil diaktifkan.");
        } else {
            console.error("ERROR FINAL: Salah satu elemen tidak ditemukan bahkan setelah penundaan.");
        }
    }, 200);
});
</script>

<?php include '../includes/footer.php'; ?>