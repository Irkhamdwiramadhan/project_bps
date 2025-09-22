<?php
// File: tambah_rpd.php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$id_pengelola = $_SESSION['user_id'];
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Ambil daftar tahun unik yang terkait dengan pengelola
$tahun_query = "SELECT DISTINCT mi.tahun FROM master_item mi
                 LEFT JOIN akun_pengelola_tahun apt ON mi.id_akun = apt.akun_id AND apt.tahun = mi.tahun
                 WHERE apt.id_pengelola = ? ORDER BY mi.tahun DESC";
$stmt_tahun = $koneksi->prepare($tahun_query);
$stmt_tahun->bind_param("i", $id_pengelola);
$stmt_tahun->execute();
$tahun_result = $stmt_tahun->get_result();
$daftar_tahun = [];
while ($row = $tahun_result->fetch_assoc()) {
    $daftar_tahun[] = $row['tahun'];
}
$stmt_tahun->close();

// Query untuk mengambil data master item yang dikelola oleh user yang login
$sql = "SELECT
            mi.id AS id_item,
            mi.nama_item,
            mi.pagu,
            ma.nama AS akun_nama,
            mo.nama AS output_nama,
            mk.nama AS komponen_nama
        FROM master_item mi
        LEFT JOIN master_akun ma ON mi.id_akun = ma.id
        LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
        LEFT JOIN master_output mo ON mk.id_output = mo.id
        LEFT JOIN master_program mp ON mo.id_program = mp.id
        LEFT JOIN akun_pengelola_tahun apt ON ma.id = apt.akun_id AND apt.tahun = mi.tahun
        WHERE apt.id_pengelola = ? AND mi.tahun = ?
        ORDER BY mk.nama, ma.nama, mi.nama_item ASC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ii", $id_pengelola, $tahun_filter);
$stmt->execute();
$data_master = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Query untuk mengambil data RPD yang sudah ada untuk pra-pengisian
$sql_rpd = "SELECT id_item, bulan, jumlah FROM rpd WHERE id_pengaju = ? AND tahun = ?";
$stmt_rpd = $koneksi->prepare($sql_rpd);
$stmt_rpd->bind_param("ii", $id_pengelola, $tahun_filter);
$stmt_rpd->execute();
$rpd_result = $stmt_rpd->get_result();
$data_rpd = [];
while ($row = $rpd_result->fetch_assoc()) {
    $data_rpd[$row['id_item']][$row['bulan']] = $row['jumlah'];
}
$stmt_rpd->close();
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
.data-table .total-cell, .data-table .pagu-cell { font-weight:bold; }
.data-table input[type="text"] { width: 80px; text-align: right; }
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
</style>

<main class="main-content">
  <div class="container">
    <div class="header-container">
      <h2 class="section-title">Tambah/Edit Rencana Pengambilan Dana (RPD)</h2>
      <a href="rpd.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Kembali ke RPD
      </a>
    </div>

    <div class="year-buttons">
      <label class="mb-0">Tahun:</label>
      <?php foreach ($daftar_tahun as $th): ?>
        <a href="?tahun=<?= $th ?>" class="btn <?= $th == $tahun_filter ? 'active' : '' ?>">
          <?= $th ?>
        </a>
      <?php endforeach; ?>
    </div>
    
    <div class="card">
      <form id="rpdForm" action="../proses/proses_simpan_rpd.php" method="POST">
        <input type="hidden" name="tahun" value="<?= htmlspecialchars($tahun_filter) ?>">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th rowspan="2">Uraian Anggaran</th>
                <th colspan="12">Rencana Per Bulan</th>
                <th rowspan="2">Total RPD</th>
                <th rowspan="2">Pagu Anggaran</th>
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
                  $total_rpd_item = 0;
                  $current_month = date('n');
                  $current_year = date('Y');
                  for ($bulan = 1; $bulan <= 12; $bulan++):
                    $jumlah_rpd = $data_rpd[$row['id_item']][$bulan] ?? 0;
                    $total_rpd_item += (float)$jumlah_rpd;
                    $is_disabled = ($tahun_filter < $current_year || ($tahun_filter == $current_year && $bulan < $current_month));
                  ?>
                    <td>
                      <input type="text" 
                             name="rpd[<?= $row['id_item'] ?>][<?= $bulan ?>]" 
                             value="<?= htmlspecialchars((int)$jumlah_rpd) ?>" 
                             class="form-control form-control-sm text-right rpd-input"
                             data-pagu="<?= htmlspecialchars($row['pagu']) ?>"
                             data-bulan="<?= $bulan ?>"
                             data-tahun="<?= $tahun_filter ?>"
                             <?= $is_disabled ? 'disabled' : '' ?>>
                    </td>
                  <?php endfor; ?>
                  <td class="total-cell" data-total-for="<?= $row['id_item'] ?>"><?= number_format($total_rpd_item, 0, ',', '.') ?></td>
                  <td class="pagu-cell"><?= number_format($row['pagu'], 0, ',', '.') ?></td>
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
        <div class="mt-3 text-right">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Simpan RPD
            </button>
        </div>
      </form>
    </div>
  </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
    function calculateTotalRpd(row) {
        let total = 0;
        row.find('.rpd-input').each(function() {
            // Include disabled fields in the calculation
            let value = parseFloat($(this).val().replace(/\./g, '')) || 0;
            total += value;
        });
        return total;
    }

    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    // Hitung total saat halaman dimuat
    $('tbody tr').each(function() {
        const row = $(this);
        const total = calculateTotalRpd(row);
        const itemId = row.find('.rpd-input').first().attr('name').match(/\[(.*?)\]/)[1];
        $(`[data-total-for="${itemId}"]`).text(formatNumber(total));
    });

    // Event handler saat input berubah
    $('.rpd-input').on('keyup change', function() {
        const row = $(this).closest('tr');
        const total = calculateTotalRpd(row);
        const pagu = parseFloat($(this).data('pagu'));

        const itemId = $(this).attr('name').match(/\[(.*?)\]/)[1];
        const totalCell = $(`[data-total-for="${itemId}"]`);
        totalCell.text(formatNumber(total));

        // Cek jika total melebihi pagu
        if (total > pagu) {
            totalCell.css('color', 'red');
            $(this).addClass('is-invalid');
        } else {
            totalCell.css('color', '');
            $(this).removeClass('is-invalid');
        }
    });

    // Mencegah karakter non-angka dan non-titik
    $('.rpd-input').on('keypress', function(e) {
        if (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) {
            e.preventDefault();
        }
    });

    // Perbaikan: Hapus logika AJAX yang tidak sinkron
    // Biarkan form disubmit secara standar
});
</script>

<?php include '../includes/footer.php'; ?>
