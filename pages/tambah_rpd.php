<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// Ambil tahun dari filter
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Ambil daftar tahun unik
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_output ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
if (empty($daftar_tahun)) {
    $daftar_tahun[] = $tahun_filter;
}

$show_item_table = false;

// Cek apakah form pemilihan output sudah di-submit (Langkah 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['selected_outputs'])) {
    $show_item_table = true;
    
    $selected_outputs_ids = array_map('intval', $_POST['selected_outputs']);
    $placeholders = implode(',', array_fill(0, count($selected_outputs_ids), '?'));
    
    $sql_items = "SELECT
        mp.kode AS program_kode, mp.nama AS program_nama, mk.kode AS kegiatan_kode, mk.nama AS kegiatan_nama,
        mo.kode AS output_kode, mo.nama AS output_nama, mso.kode AS sub_output_kode, mso.nama AS sub_output_nama,
        mkom.kode AS komponen_kode, mkom.nama AS komponen_nama, msk.kode AS sub_komponen_kode, msk.nama AS sub_komponen_nama,
        ma.kode AS akun_kode, ma.nama AS akun_nama, mi.nama_item AS item_nama, mi.pagu, mi.kode_unik
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
    $stmt_items->bind_param($types, ...$params);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $flat_data = [];
    while ($row = $result_items->fetch_assoc()) {
        $flat_data[] = $row;
    }
    $stmt_items->close();

    $rpd_data = [];
    $sql_rpd = "SELECT kode_unik_item, SUM(jumlah) as total_rpd FROM rpd WHERE tahun = ? GROUP BY kode_unik_item";
    $stmt_rpd = $koneksi->prepare($sql_rpd);
    $stmt_rpd->bind_param("i", $tahun_filter);
    $stmt_rpd->execute();
    $result_rpd = $stmt_rpd->get_result();
    while ($row = $result_rpd->fetch_assoc()) {
        $rpd_data[$row['kode_unik_item']] = $row['total_rpd'];
    }
    $stmt_rpd->close();

    $hierarki = [];
    foreach ($flat_data as $row) {
        $p_kode = $row['program_kode']; $k_kode = $row['kegiatan_kode']; $o_kode = $row['output_kode']; $so_kode = $row['sub_output_kode'];
        $kom_kode = $row['komponen_kode']; $sk_kode = $row['sub_komponen_kode']; $a_kode = $row['akun_kode'];
        if (!$p_kode) continue;
        if (!isset($hierarki[$p_kode])) { $hierarki[$p_kode] = ['nama' => $row['program_nama'], 'children' => []]; }
        if (!isset($hierarki[$p_kode]['children'][$k_kode])) { $hierarki[$p_kode]['children'][$k_kode] = ['nama' => $row['kegiatan_nama'], 'children' => []]; }
        if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode] = ['nama' => $row['output_nama'], 'children' => []]; }
        if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode] = ['nama' => $row['sub_output_nama'], 'children' => []]; }
        if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode] = ['nama' => $row['komponen_nama'], 'children' => []]; }
        if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode] = ['nama' => $row['sub_komponen_nama'], 'children' => []]; }
        if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode])) { $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode] = ['nama' => $row['akun_nama'], 'items' => []]; }
        $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode]['items'][] = $row;
    }

} else {
    // Langkah 1
    $sql_outputs = "SELECT mo.id, mo.kode, mo.nama 
                    FROM master_output mo
                    WHERE mo.tahun = ?
                    ORDER BY mo.kode ASC";
    $stmt = $koneksi->prepare($sql_outputs);
    $stmt->bind_param("i", $tahun_filter);
    $stmt->execute();
    $result_outputs = $stmt->get_result();
    $outputs = [];
    while ($row = $result_outputs->fetch_assoc()) {
        $outputs[] = $row;
    }
    $stmt->close();
}
?>

<style>
/* (Style CSS tidak ada perubahan) */
:root {
    --primary-blue: #0A2E5D; --secondary-blue: #007bff; --light-blue-bg: #f8f9fa;
    --text-dark: #212529; --text-light: #6c757d; --border-color: #dee2e6;
}
.main-content { padding: 30px; background-color: var(--light-blue-bg); }
.section-title { font-size: 1.8rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 25px; }
.card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 6px 25px rgba(0, 0, 0, 0.07); padding: 25px; }
.card-title { font-weight: 600; color: var(--primary-blue); }
.year-buttons { display: flex; gap: 5px; align-items: center; margin-bottom: 15px; }
.year-buttons .btn { border-radius: 5px; text-decoration: none; font-size: 0.9rem; }
.output-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 20px; }
.form-check-label { display: block; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s ease-in-out; }
.form-check-label:hover { background-color: #f8f9fa; border-color: var(--secondary-blue); }
.form-check-input { display: none; }
.form-check-input:checked + .form-check-label { background-color: var(--primary-blue); color: #fff; border-color: var(--primary-blue); box-shadow: 0 4px 15px rgba(10, 46, 93, 0.2); }
.btn-primary { background: var(--primary-blue); border: none; padding: 12px 25px; font-weight: 600; transition: all 0.2s; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(10, 46, 93, 0.3); }
.data-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
.data-table th { background:#f7f9fc; font-weight:600; text-align: center; }
.data-table td.col-right { text-align:right; }
.hierarchy-row td { font-weight:bold; background-color: #f8f9fa; }
.level-program { font-size: 1.1em; color: #0A2E5D; }
.level-kegiatan { padding-left: 25px !important; color: #154360; }
.level-output { padding-left: 50px !important; color: #1F618D; }
.level-sub-output { padding-left: 75px !important; color: #2980B9; }
.level-komponen { padding-left: 100px !important; color: #5499C7; }
.level-sub-komponen { padding-left: 125px !important; color: #7f8c8d; }
.level-akun { padding-left: 150px !important; font-style: italic; color: #27AE60; }
.level-item { font-weight: normal; padding-left: 175px !important; }
</style>

<main class="main-content">
  <div class="container-fluid">
    <h2 class="section-title">Tambah/Edit RPD - Tahun <?= $tahun_filter ?></h2>
    
    <?php if (!$show_item_table): ?>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Langkah 1: Pilih Output</h5>
        <a href="rpd.php" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>
        <p class="text-muted">Pilih satu atau beberapa Output dari tahun anggaran yang aktif, lalu klik "Lanjutkan".</p>
        <div class="year-buttons">
          <label class="mb-0 mr-2">Tahun:</label>
          <?php foreach ($daftar_tahun as $th): ?>
            <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'btn-primary' : 'btn-light' ?>"><?= $th ?></a>
          <?php endforeach; ?>
        </div>
        <hr>
        <form action="tambah_rpd.php?tahun=<?= $tahun_filter ?>" method="POST">
          <div class="form-group">
            <div class="output-grid">
              <?php if (!empty($outputs)): ?>
                <?php foreach ($outputs as $output): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="selected_outputs[]" value="<?= $output['id'] ?>" id="output_<?= $output['id'] ?>">
                    <label class="form-check-label" for="output_<?= $output['id'] ?>">
                      <b><?= htmlspecialchars($output['kode']) ?></b><br>
                      <span><?= htmlspecialchars($output['nama']) ?></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-muted">Tidak ada data Output ditemukan untuk tahun <?= $tahun_filter ?>.</p>
              <?php endif; ?>
            </div>
          </div>
          <hr>
          <button type="submit" class="btn btn-primary" <?= empty($outputs) ? 'disabled' : '' ?>>Lanjutkan <i class="fas fa-arrow-right ml-2"></i></button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Langkah 2: Isi RPD per Item</h5>
        <p class="text-muted">Klik tombol "Tambah RPD" pada item yang diinginkan untuk mengisi rencana penarikan dana 12 bulan.</p>
        <a href="tambah_rpd.php?tahun=<?= $tahun_filter ?>" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 55%;">Uraian Anggaran</th>
                <th style="width: 15%;">Jumlah Pagu</th>
                <th style="width: 15%;">Jumlah RPD</th>
                <th style="width: 15%;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($hierarki)): ?>
                  <?php foreach ($hierarki as $p_kode => $program): ?>
                      <tr class="hierarchy-row"><td colspan="4" class="level-program"><b><?= htmlspecialchars($p_kode) ?></b> - <?= htmlspecialchars($program['nama']) ?></td></tr>
                      <?php foreach ($program['children'] as $k_kode => $kegiatan): ?>
                          <tr class="hierarchy-row"><td colspan="4" class="level-kegiatan"><b><?= htmlspecialchars($k_kode) ?></b> - <?= htmlspecialchars($kegiatan['nama']) ?></td></tr>
                          <?php foreach ($kegiatan['children'] as $o_kode => $output): ?>
                              <tr class="hierarchy-row"><td colspan="4" class="level-output"><b><?= htmlspecialchars($o_kode) ?></b> - <?= htmlspecialchars($output['nama']) ?></td></tr>
                               <?php foreach ($output['children'] as $so_kode => $sub_output): ?>
                                  <tr class="hierarchy-row"><td colspan="4" class="level-sub-output"><b><?= htmlspecialchars($so_kode) ?></b> - <?= htmlspecialchars($sub_output['nama']) ?></td></tr>
                                  <?php foreach ($sub_output['children'] as $kom_kode => $komponen): ?>
                                      <tr class="hierarchy-row"><td colspan="4" class="level-komponen"><b><?= htmlspecialchars($kom_kode) ?></b> - <?= htmlspecialchars($komponen['nama']) ?></td></tr>
                                      <?php foreach ($komponen['children'] as $sk_kode => $sub_komponen): ?>
                                          <tr class="hierarchy-row"><td colspan="4" class="level-sub-komponen"><b><?= htmlspecialchars($sk_kode) ?></b> - <?= htmlspecialchars($sub_komponen['nama']) ?></td></tr>
                                          <?php foreach ($sub_komponen['children'] as $a_kode => $akun): ?>
                                              <tr class="hierarchy-row"><td colspan="4" class="level-akun"><b><?= htmlspecialchars($a_kode) ?></b> - <?= htmlspecialchars($akun['nama']) ?></td></tr>
                                              <?php foreach ($akun['items'] as $item): ?>
                                                  <tr class="item-row">
                                                    <td class="level-item"><?= htmlspecialchars($item['item_nama']) ?></td>
                                                    <td class="col-right">Rp <?= htmlspecialchars(number_format($item['pagu'], 0, ',', '.')) ?></td>
                                                    <td class="col-right">Rp <?= htmlspecialchars(number_format($rpd_data[$item['kode_unik']] ?? 0, 0, ',', '.')) ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $total_rpd_item = $rpd_data[$item['kode_unik']] ?? 0;
                                                        $pagu_item = (float)$item['pagu'];
                                                        if ($pagu_item <= $total_rpd_item) {
                                                            echo '<span class="text-success font-weight-bold"><i class="fas fa-check-circle"></i> Selesai</span>';
                                                        } else {
                                                            echo '<a href="isi_rpd_per_item.php?kode_unik='.urlencode($item['kode_unik']).'" class="btn btn-sm btn-info">';
                                                            echo '  <i class="fas fa-plus"></i> Tambah RPD';
                                                            echo '</a>';
                                                        }
                                                        ?>
                                                    </td>
                                                  </tr>
                                              <?php endforeach; ?>
                                          <?php endforeach; ?>
                                      <?php endforeach; ?>
                                  <?php endforeach; ?>
                              <?php endforeach; ?>
                          <?php endforeach; ?>
                      <?php endforeach; ?>
                  <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted">Tidak ada item yang ditemukan.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<?php include '../includes/footer.php'; ?>