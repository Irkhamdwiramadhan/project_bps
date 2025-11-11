<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'ketua_tim'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// Ambil tahun dari query (default sekarang)
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// ==========================
// CEK BATAS WAKTU ISI RPD
// ==========================
$today = date("Y-m-d");

// Ambil batas waktu untuk tahun yang sedang difilter
$stmt_waktu = $koneksi->prepare("SELECT mulai, selesai FROM rpd_setting_waktu WHERE tahun = ? LIMIT 1");
$stmt_waktu->bind_param("i", $tahun_filter);
$stmt_waktu->execute();
$result_waktu = $stmt_waktu->get_result();
$waktu_rpd = $result_waktu->fetch_assoc();
$stmt_waktu->close();

// Jika belum ada pengaturan waktu, anggap masih tertutup
if (!$waktu_rpd) {
    echo '<main class="main-content">
            <div class="card card-access-denied">
              <h2 class="text-center text-danger">Pengisian RPD Belum Dibuka</h2>
              <p class="text-center">Admin Dipaku belum mengatur waktu pengisian RPD. Silakan hubungi Admin Dipaku.</p>
            </div>
          </main>';
    include '../includes/footer.php';
    exit;
}

// Jika di luar rentang waktu, tampilkan pesan ditolak
if ($today < $waktu_rpd['mulai'] || $today > $waktu_rpd['selesai']) {
    echo '<main class="main-content">
            <div class="card card-access-denied">
              <h2 class="text-center text-danger">Pengisian RPD Ditutup</h2>
              <p class="text-center">Maaf pengisian RPD Sudah ditutup.
                Pengisian RPD hanya dapat dilakukan antara 
                <strong>' . date("d-m-Y", strtotime($waktu_rpd['mulai'])) . '</strong> 
                sampai 
                <strong>' . date("d-m-Y", strtotime($waktu_rpd['selesai'])) . '</strong>.<br><br>
                Silakan hubungi <b>Admin Dipaku</b> untuk memperbarui jadwal pengisian.
              </p>
            </div>
          </main>';
    include '../includes/footer.php';
    exit;
}

// Ambil daftar tahun existing
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_output ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
if (empty($daftar_tahun)) $daftar_tahun[] = $tahun_filter;

$show_item_table = false;
$selected_outputs_ids = [];

// --- Tambahan: Ambil dari POST atau GET (agar tetap bisa lanjut setelah redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['selected_outputs'])) {
    $selected_outputs_ids = array_map('intval', $_POST['selected_outputs']);
    $show_item_table = true;
} elseif (!empty($_GET['selected_outputs'])) {
    $selected_outputs_ids = array_map('intval', $_GET['selected_outputs']);
    $show_item_table = true;
} else {
    // Tidak ada selected outputs => tetap di Langkah 1
    $selected_outputs_ids = [];
    $show_item_table = false;
}

// Jika show_item_table true => ambil item untuk output yang dipilih
if ($show_item_table) {
    // safety: jika tidak ada selected outputs (shouldn't happen) -> fallback
    if (empty($selected_outputs_ids)) {
        $show_item_table = false;
    } else {
        // build placeholders
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
        // types: i + N * i
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

        // ambil jumlah rpd per item tahun ini
        $rpd_data = [];
        $sql_rpd = "SELECT kode_unik_item, SUM(jumlah) as total_rpd FROM rpd WHERE tahun = ? GROUP BY kode_unik_item";
        $stmt_rpd = $koneksi->prepare($sql_rpd);
        $stmt_rpd->bind_param("i", $tahun_filter);
        $stmt_rpd->execute();
        $result_rpd = $stmt_rpd->get_result();
        while ($r = $result_rpd->fetch_assoc()) {
            $rpd_data[$r['kode_unik_item']] = $r['total_rpd'];
        }
        $stmt_rpd->close();

        // susun hierarki (program -> kegiatan -> output -> sub_output -> komponen -> subkom -> akun -> items)
        $hierarki = [];
        foreach ($flat_data as $row) {
            $p_kode = $row['program_kode']; $k_kode = $row['kegiatan_kode']; $o_kode = $row['output_kode'];
            $so_kode = $row['sub_output_kode']; $kom_kode = $row['komponen_kode']; $sk_kode = $row['sub_komponen_kode']; $a_kode = $row['akun_kode'];
            if (!$p_kode) continue;

            if (!isset($hierarki[$p_kode])) $hierarki[$p_kode] = ['nama' => $row['program_nama'], 'children' => []];
            if (!isset($hierarki[$p_kode]['children'][$k_kode])) $hierarki[$p_kode]['children'][$k_kode] = ['nama' => $row['kegiatan_nama'], 'children' => []];
            if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode])) $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode] = ['nama' => $row['output_nama'], 'children' => []];
            if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode])) $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode] = ['nama' => $row['sub_output_nama'], 'children' => []];
            if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode])) $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode] = ['nama' => $row['komponen_nama'], 'children' => []];
            if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode])) $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode] = ['nama' => $row['sub_komponen_nama'], 'children' => []];
            if (!isset($hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode])) $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode] = ['nama' => $row['akun_nama'], 'items' => []];

            $hierarki[$p_kode]['children'][$k_kode]['children'][$o_kode]['children'][$so_kode]['children'][$kom_kode]['children'][$sk_kode]['children'][$a_kode]['items'][] = $row;
        }
    }
} else {
    // Langkah 1: ambil daftar output untuk tahun
    $sql_outputs = "SELECT mo.id, mo.kode, mo.nama FROM master_output mo WHERE mo.tahun = ? ORDER BY mo.kode ASC";
    $stmt = $koneksi->prepare($sql_outputs);
    $stmt->bind_param("i", $tahun_filter);
    $stmt->execute();
    $result_outputs = $stmt->get_result();
    $outputs = [];
    while ($row = $result_outputs->fetch_assoc()) $outputs[] = $row;
    $stmt->close();
}

// Untuk membantu pembuatan url-back, siapkan query selected_outputs (array)
$selected_outputs_query = http_build_query(['selected_outputs' => $selected_outputs_ids]);

?>
<!-- Styles kamu tidak diubah, langsung HTML -->
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
.card-access-denied {
  max-width: 600px;
  margin: 80px auto;
  padding: 40px;
  text-align: center;
  border: 1px solid #f5c2c7;
  border-radius: 12px;
  background-color: #fff0f0;
  box-shadow: 0 0 10px rgba(255, 0, 0, 0.1);
}

</style>

<main class="main-content">
  <div class="container-fluid">
    <h2 class="section-title">Tambah/Edit RPD - Tahun <?= htmlspecialchars($tahun_filter) ?></h2>

    <?php if (!$show_item_table): ?>
      <div class="card">
        <h5>Langkah 1: Pilih Output</h5>
        <a href="rpd.php" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>
        <p class="text-m  uted">Pilih satu atau beberapa Output lalu klik Lanjutkan.</p>

        <div class="year-buttons mb-3">
          <label class="me-2">Tahun:</label>
          <?php foreach ($daftar_tahun as $th): ?>
            <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'btn-primary' : 'btn-light' ?>"><?= $th ?></a>
          <?php endforeach; ?>
        </div>

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
              <p class="text-muted">Tidak ada Output untuk tahun <?= $tahun_filter ?>.</p>
            <?php endif; ?>
          </div>

          <hr>
          <button type="submit" class="btn btn-primary" <?= empty($outputs) ? 'disabled' : '' ?>>Lanjutkan</button>
        </form>
      </div>

    <?php else: ?>
      <div class="card">
        <h5>Langkah 2: Isi RPD per Item</h5>
        <p class="text-muted">Klik Tambah/Edit RPD pada item yang ingin diisi.</p>

        <?php
        // Tombol Back ke Langkah 2 (pilih output tetap tercentang)
        $back_url = "tambah_rpd.php?tahun=" . urlencode($tahun_filter);
        ?>
        <a href="<?= $back_url ?>" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>

        <div class="table-responsive">
          <table class="data-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th>Uraian Anggaran</th>
                <th style="width:140px;">Jumlah Pagu</th>
                <th style="width:160px;">Jumlah RPD</th>
                <th style="width:160px;">Aksi</th>
              </tr>
            </thead>
         <tbody>
    <?php if (!empty($hierarki)): ?>
        <?php foreach ($hierarki as $p_kode => $program): ?>
            <tr class="hierarchy-row"><td colspan="4"><strong><?= htmlspecialchars($p_kode) ?></strong> - <?= htmlspecialchars($program['nama']) ?></td></tr>
            <?php foreach ($program['children'] as $k_kode => $kegiatan): ?>
                <tr class="hierarchy-row"><td colspan="4" style="padding-left:12px;"><strong><?= htmlspecialchars($k_kode) ?></strong> - <?= htmlspecialchars($kegiatan['nama']) ?></td></tr>
                <?php foreach ($kegiatan['children'] as $o_kode => $output): ?>
                    <tr class="hierarchy-row"><td colspan="4" style="padding-left:24px;"><strong><?= htmlspecialchars($o_kode) ?></strong> - <?= htmlspecialchars($output['nama']) ?></td></tr>
                    <?php foreach ($output['children'] as $so_kode => $sub_output): ?>
                        <tr class="hierarchy-row"><td colspan="4" style="padding-left:36px;"><strong><?= htmlspecialchars($so_kode) ?></strong> - <?= htmlspecialchars($sub_output['nama']) ?></td></tr>
                        <?php foreach ($sub_output['children'] as $kom_kode => $komponen): ?>
                            <tr class="hierarchy-row"><td colspan="4" style="padding-left:48px;"><strong><?= htmlspecialchars($kom_kode) ?></strong> - <?= htmlspecialchars($komponen['nama']) ?></td></tr>
                            <?php foreach ($komponen['children'] as $sk_kode => $sub_komponen): ?>
                                <tr class="hierarchy-row"><td colspan="4" style="padding-left:60px;"><strong><?= htmlspecialchars($sk_kode) ?></strong> - <?= htmlspecialchars($sub_komponen['nama']) ?></td></tr>
                                <?php foreach ($sub_komponen['children'] as $a_kode => $akun): ?>
                                    <tr class="hierarchy-row"><td colspan="4" style="padding-left:72px;"><strong><?= htmlspecialchars($a_kode) ?></strong> - <?= htmlspecialchars($akun['nama']) ?></td></tr>
                                    <?php foreach ($akun['items'] as $item): 
                                        $total_rpd_item = (float)($rpd_data[$item['kode_unik']] ?? 0);
                                        $pagu_item = (float)$item['pagu'];
                                        $item_link = "isi_rpd_per_item.php?kode_unik=" . urlencode($item['kode_unik'])
                                                    . "&" . http_build_query(['selected_outputs' => $selected_outputs_ids])
                                                    . "&tahun=" . urlencode($tahun_filter)
                                                    . "&step=2";

                                        // ==========================================================
                                        // REVISI LOGIKA UTAMA ADA DI SINI
                                        // ==========================================================
                                        // Cek apakah data RPD untuk item ini sudah ada di database.
                                        // `isset` lebih akurat daripada `> 0` karena bisa jadi RPD pernah diisi lalu dinolkan.
                                        $rpd_exists = isset($rpd_data[$item['kode_unik']]);
                                    ?>
                                        <tr>
                                            <td style="padding-left:84px;"><?= htmlspecialchars($item['item_nama']) ?></td>
                                            <td style="text-align:right;">Rp <?= number_format($pagu_item, 0, ',', '.') ?></td>
                                            <td style="text-align:right;">Rp <?= number_format($total_rpd_item, 0, ',', '.') ?></td>
                                            <td style="text-align:center;">
                                                <a href="<?= $item_link ?>" class="btn btn-sm <?= $rpd_exists ? 'btn-warning' : 'btn-info' ?>">
                                                    <i class="fas <?= $rpd_exists ? 'fa-edit' : 'fa-plus' ?>"></i>
                                                    <?= $rpd_exists ? 'Update RPD' : 'Tambah RPD' ?>
                                                </a>
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
        <tr><td colspan="4" class="text-center text-muted">Tidak ada item ditemukan.</td></tr>
    <?php endif; ?>
</tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php include '../includes/footer.php'; ?>
