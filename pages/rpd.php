<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Mendefinisikan peran super_admin
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

$id_pengelola = $_SESSION['user_id'];
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Ambil daftar tahun unik, disesuaikan untuk super_admin
$tahun_query_parts = ["SELECT DISTINCT mi.tahun FROM master_item mi"];
if (!$is_super_admin) {
    $tahun_query_parts[] = "LEFT JOIN akun_pengelola_tahun apt ON mi.id_akun = apt.akun_id AND apt.tahun = mi.tahun WHERE apt.id_pengelola = ?";
}
$tahun_query_parts[] = "ORDER BY mi.tahun DESC";
$tahun_query = implode(" ", $tahun_query_parts);

$stmt_tahun = $koneksi->prepare($tahun_query);
if (!$is_super_admin) {
    $stmt_tahun->bind_param("i", $id_pengelola);
}
$stmt_tahun->execute();
$tahun_result = $stmt_tahun->get_result();
$daftar_tahun = [];
while ($row = $tahun_result->fetch_assoc()) {
    $daftar_tahun[] = $row['tahun'];
}
$stmt_tahun->close();

// Perbaikan: Query untuk menghitung sisa anggaran secara real-time, disesuaikan untuk super_admin
$sql_parts = [
    "SELECT
        mi.id AS id_item,
        mi.nama_item,
        mi.pagu,
        ma.nama AS akun_nama,
        mo.nama AS output_nama,
        mk.nama AS komponen_nama,
        COALESCE(SUM(rpd.jumlah), 0) AS total_rpd,
        (mi.pagu - COALESCE(SUM(rpd.jumlah), 0)) AS sisa_anggaran"
];

if ($is_super_admin) {
    $sql_parts[] = ", (SELECT u.nama FROM users u JOIN akun_pengelola_tahun apt_sub ON apt_sub.id_pengelola = u.id WHERE apt_sub.akun_id = ma.id LIMIT 1) as nama_pengelola";
}

$sql_parts[] = "FROM master_item mi
LEFT JOIN master_akun ma ON mi.id_akun = ma.id
LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
LEFT JOIN master_output mo ON mk.id_output = mo.id
LEFT JOIN master_program mp ON mo.id_program = mp.id
LEFT JOIN akun_pengelola_tahun apt ON ma.id = apt.akun_id AND apt.tahun = mi.tahun
LEFT JOIN rpd ON mi.id = rpd.id_item AND rpd.tahun = mi.tahun";

$where_clauses = [];
$bind_types = "";
$bind_params = [];

if (!$is_super_admin) {
    $where_clauses[] = "apt.id_pengelola = ?";
    $bind_types .= "i";
    $bind_params[] = $id_pengelola;
}
$where_clauses[] = "mi.tahun = ?";
$bind_types .= "i";
$bind_params[] = $tahun_filter;

if (!empty($where_clauses)) {
    $sql_parts[] = "WHERE " . implode(" AND ", $where_clauses);
}

$sql_parts[] = "GROUP BY mi.id";
if ($is_super_admin) {
    $sql_parts[] = ", nama_pengelola";
}
$sql_parts[] = "ORDER BY mk.nama, ma.nama, mi.nama_item ASC";

$sql = implode(" ", $sql_parts);
$stmt = $koneksi->prepare($sql);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$data_master = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
.main-content { padding: 30px; background:#f7f9fc; }
.header-container { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap: wrap; gap: 15px; }
.section-title { font-size:1.5rem; font-weight:700; margin:0; }
.card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.data-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.data-table th, .data-table td { padding:10px; border-bottom:1px solid #dee2e6; text-align:center; }
.data-table th { background:#f7f9fc; font-weight:600; }
.data-table td.col-left { text-align:left; }
.data-table .total-cell, .data-table .pagu-cell, .data-table .sisa-cell { font-weight:bold; }
.text-muted { color: #6c757d; }
.year-buttons { display: flex; gap: 5px; align-items: center; margin-bottom: 15px; }
.year-buttons .btn {
    border: 1px solid #e0e0e0;
    color: #333;
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 8px 15px;
    text-decoration: none;
    font-size: 0.9rem;
}
.year-buttons .btn.active {
    background-color: #007bff;
    color: #fff;
    border-color: #007bff;
}
.info-box { background-color: #e9f5ff; border-left: 5px solid #007bff; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
.add-rpd-button-container { text-align: right; margin-bottom: 20px; }
</style>

<main class="main-content">
  <div class="container">
    <div class="header-container">
      <h2 class="section-title">Rencana Pengambilan Dana (RPD)</h2>
      <?php if (!$is_super_admin): ?>
        <div class="add-rpd-button-container">
          <a href="tambah_rpd.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah RPD
          </a>
        </div>
      <?php endif; ?>
    </div>

    <div class="year-buttons">
      <label class="mb-0">Tahun:</label>
      <?php foreach ($daftar_tahun as $th): ?>
        <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'active' : '' ?>">
          <?= $th ?>
        </a>
      <?php endforeach; ?>
    </div>
    
    <div class="info-box">
      <strong>Catatan:</strong> Halaman ini hanya menampilkan rencana pengambilan dana yang telah Anda input. Untuk mengubahnya, silakan gunakan halaman tambah/edit RPD.
    </div>
    
    <div class="card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th rowspan="2">Uraian Anggaran</th>
              <th colspan="12">Rencana Per Bulan</th>
              <th rowspan="2">Total RPD</th>
              <th rowspan="2">Pagu Anggaran</th>
              <th rowspan="2">Sisa Anggaran</th>
            </tr>
            <tr>
              <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>Mei</th><th>Jun</th>
              <th>Jul</th><th>Agu</th><th>Sep</th><th>Okt</th><th>Nov</th><th>Des</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($data_master)): ?>
              <?php
              $prev_komponen = null;
              $prev_akun = null;
              foreach ($data_master as $row):
                  if ($row['komponen_nama'] !== $prev_komponen): ?>
                      <tr class="hierarchy-row"><td colspan="15" class="col-left text-muted" style="padding-left:10px; font-weight:normal; font-style:italic;">Komponen: <?= htmlspecialchars($row['komponen_nama']) ?></td></tr>
                      <?php $prev_komponen = $row['komponen_nama'];
                  endif;
                  if ($row['akun_nama'] !== $prev_akun): ?>
                      <tr class="hierarchy-row"><td colspan="15" class="col-left text-muted" style="padding-left:30px; font-weight:normal;">Akun: <?= htmlspecialchars($row['akun_nama']) ?></td></tr>
                      <?php $prev_akun = $row['akun_nama'];
                  endif;
              ?>
              <tr>
                <td class="col-left" style="padding-left:50px;"><?= htmlspecialchars($row['nama_item']) ?></td>
                <?php 
                $sql_rpd = "SELECT bulan, jumlah FROM rpd WHERE id_item = ? AND tahun = ? ORDER BY bulan ASC";
                if ($is_super_admin) {
                    $sql_rpd = "SELECT bulan, jumlah FROM rpd WHERE id_item = ? AND id_pengaju IN (SELECT id_pengelola FROM akun_pengelola_tahun WHERE akun_id = (SELECT id_akun FROM master_item WHERE id = ?)) AND tahun = ? ORDER BY bulan ASC";
                }
                $stmt_rpd = $koneksi->prepare($sql_rpd);
                
                if ($is_super_admin) {
                    $stmt_rpd->bind_param("iii", $row['id_item'], $row['id_item'], $tahun_filter);
                } else {
                    $stmt_rpd->bind_param("ii", $row['id_item'], $tahun_filter);
                }

                $stmt_rpd->execute();
                $rpd_result = $stmt_rpd->get_result();
                $rpd_by_month = [];
                while ($rpd_row = $rpd_result->fetch_assoc()) {
                    $rpd_by_month[$rpd_row['bulan']] = $rpd_row['jumlah'];
                }
                $stmt_rpd->close();

                for ($bulan = 1; $bulan <= 12; $bulan++):
                    $jumlah_rpd = $rpd_by_month[$bulan] ?? 0;
                ?>
                  <td>
                    <?= number_format($jumlah_rpd, 0, ',', '.') ?>
                  </td>
                <?php endfor; ?>
                <td class="total-cell"><?= number_format($row['total_rpd'], 0, ',', '.') ?></td>
                <td class="pagu-cell"><?= number_format($row['pagu'], 0, ',', '.') ?></td>
                <td class="sisa-cell"><?= number_format($row['sisa_anggaran'], 0, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="15" class="text-center text-muted">Tidak ada data anggaran yang Anda kelola untuk tahun ini.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<?php include '../includes/footer.php'; ?>