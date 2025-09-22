<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil peran pengguna dari sesi. Jika tidak ada, atur sebagai array kosong.
$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];

$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}

// Ambil tahun dari filter (GET), default tahun sekarang
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Ambil daftar tahun unik
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_item ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result && $tahun_result->num_rows > 0) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}

// Ambil daftar pegawai untuk dropdown ketua/pengelola
$pegawai_result = $koneksi->query("SELECT id, nama FROM pegawai ORDER BY nama ASC");
$daftar_pegawai = [];
if ($pegawai_result && $pegawai_result->num_rows > 0) {
    while ($row = $pegawai_result->fetch_assoc()) {
        $daftar_pegawai[$row['id']] = $row['nama'];
    }
}

// Query data
$sql = "SELECT
            mu.nama AS unit_nama,
            mp.nama AS program_nama,
            mo.nama AS output_nama,
            mk.nama AS komponen_nama,
            ma.id AS akun_id,
            ma.nama AS akun_nama,
            apt.id_ketua,
            apt.id_pengelola,
            mi.id AS id_item,
            mi.nama_item AS item_nama,
            mi.satuan,
            mi.volume,
            mi.harga,
            mi.pagu,
            ikt.realisasi,
            ikt.sisa_anggaran,
            mi.tahun
        FROM master_item mi
        LEFT JOIN master_akun ma ON mi.id_akun = ma.id
        LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
        LEFT JOIN master_output mo ON mk.id_output = mo.id
        LEFT JOIN master_program mp ON mo.id_program = mp.id
        LEFT JOIN master_unit mu ON mp.id_unit = mu.id
        LEFT JOIN akun_pengelola_tahun apt ON ma.id = apt.akun_id AND apt.tahun = mi.tahun
        LEFT JOIN item_keuangan_tahun ikt ON mi.id = ikt.id_item AND ikt.tahun = mi.tahun
        WHERE mi.tahun = {$tahun_filter}
        ORDER BY mu.nama, mp.nama, mo.nama, mk.nama, ma.nama, mi.nama_item ASC";

$result = $koneksi->query($sql);
$data_master = [];
$total_pagu = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['volume'] = (float) $row['volume'];
        $row['harga'] = (float) $row['harga'];
        $row['pagu'] = (float) $row['pagu'];
        $row['realisasi'] = (float) $row['realisasi'];
        $row['sisa_anggaran'] = (float) $row['sisa_anggaran'];
        $data_master[] = $row;
        $total_pagu += $row['pagu'];
    }
}
?>

<style>
.main-content { padding: 30px; background:#f7f9fc; }
.header-container { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap: wrap; gap: 15px; }
.section-title { font-size:1.5rem; font-weight:700; margin:0; }
.card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.data-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.data-table th, .data-table td { padding:10px; border-bottom:1px solid #dee2e6; }
.data-table th { background:#f7f9fc; font-weight:600; }
.data-table td.col-right { text-align:right; }
.data-table td.col-center, .data-table th.col-center { text-align:center; }
.hierarchy-row td { font-weight:bold; border-bottom:none; }
.level-unit { color:#004d99; }
.level-program { color:#196f3d; padding-left:20px !important; }
.level-output { color:#d68910; padding-left:40px !important; }
.level-komponen { color:#5b2c6f; padding-left:60px !important; }
.level-akun { color:#515a5a; padding-left:80px !important; }
.level-item { font-weight:normal; padding-left:100px !important; }
.total-box { margin-top:15px; font-weight:600; font-size:1rem; color:#2c3e50; }
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
      <h2 class="section-title">Manajemen Anggaran Tahunan </h2>
      <?php if ($has_access_for_action): ?>
        <a href="tambah_master_data.php" class="btn btn-primary">
          <i class="fas fa-plus"></i> Tambah Anggaran Tahun Baru
        </a>
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
    
    <div class="card">
      <div class="table-responsive">
        <table class="data-table">
          <colgroup>
            <col style="width:25%">
            <col style="width:8%">
            <col style="width:10%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:12%">
            <col style="width:9%">
          </colgroup>
          <thead>
            <tr>
              <th>Uraian Anggaran</th>
              <th class="col-center">Satuan</th>
              <th class="col-right">Volume</th>
              <th class="col-right">Harga</th>
              <th class="col-right">Pagu</th>
              <th class="col-center">Ketua Tim</th>
              <th class="col-center">Pengelola</th>
              <th class="col-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($data_master)):
                $prev_unit = $prev_program = $prev_output = $prev_komponen = $prev_akun = null;
                foreach ($data_master as $row):
                    // Logika bersarang yang benar
                    // Jika unit berubah, tampilkan baris unit dan reset semua variabel di bawahnya
                    if ($row['unit_nama'] !== $prev_unit): ?>
                      <tr class="hierarchy-row"><td colspan="8" class="level-unit"><?= htmlspecialchars($row['unit_nama']) ?></td></tr>
                      <?php $prev_unit = $row['unit_nama'];
                      // Reset semua level di bawahnya
                      $prev_program = $prev_output = $prev_komponen = $prev_akun = null;
                    endif;

                    // Jika program berubah, tampilkan baris program dan reset semua variabel di bawahnya
                    if ($row['program_nama'] !== $prev_program): ?>
                      <tr class="hierarchy-row"><td colspan="8" class="level-program"><?= htmlspecialchars($row['program_nama']) ?></td></tr>
                      <?php $prev_program = $row['program_nama'];
                      // Reset semua level di bawahnya
                      $prev_output = $prev_komponen = $prev_akun = null;
                    endif;

                    // Jika output berubah, tampilkan baris output dan reset semua variabel di bawahnya
                    if ($row['output_nama'] !== $prev_output): ?>
                      <tr class="hierarchy-row"><td colspan="8" class="level-output"><?= htmlspecialchars($row['output_nama']) ?></td></tr>
                      <?php $prev_output = $row['output_nama'];
                      // Reset semua level di bawahnya
                      $prev_komponen = $prev_akun = null;
                    endif;

                    // Jika komponen berubah, tampilkan baris komponen dan reset variabel di bawahnya
                    if ($row['komponen_nama'] !== $prev_komponen): ?>
                      <tr class="hierarchy-row"><td colspan="8" class="level-komponen"><?= htmlspecialchars($row['komponen_nama']) ?></td></tr>
                      <?php $prev_komponen = $row['komponen_nama'];
                      // Reset variabel di bawahnya
                      $prev_akun = null;
                    endif;

                    // Jika akun berubah, tampilkan baris akun
                    if ($row['akun_nama'] !== $prev_akun): ?>
                      <tr class="hierarchy-row">
                        <td colspan="5" class="level-akun"><?= htmlspecialchars($row['akun_nama']) ?></td>
                        <td class="col-center">
                          <select name="ketua_tim[<?= $row['akun_id'] ?>]" class="form-control form-control-sm auto-save" data-akun-id="<?= $row['akun_id'] ?>" data-role="ketua" data-tahun="<?= $tahun_filter ?>">
                            <option value="">-- Pilih --</option>
                            <?php foreach ($daftar_pegawai as $id_pg => $nama_pg): ?>
                              <option value="<?= $id_pg ?>" <?= $row['id_ketua'] == $id_pg ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nama_pg) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td class="col-center">
                          <select name="pengelola[<?= $row['akun_id'] ?>]" class="form-control form-control-sm auto-save" data-akun-id="<?= $row['akun_id'] ?>" data-role="pengelola" data-tahun="<?= $tahun_filter ?>">
                            <option value="">-- Pilih --</option>
                            <?php foreach ($daftar_pegawai as $id_pg => $nama_pg): ?>
                              <option value="<?= $id_pg ?>" <?= $row['id_pengelola'] == $id_pg ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nama_pg) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td class="col-center">
                            <?php 
                            if ($has_access_for_action): 
                            ?>
                          <a href="tambah_item.php?id_akun=<?= urlencode($row['akun_id']) ?>&tahun=<?= $tahun_filter ?>" 
                            class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> Item
                          </a>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php $prev_akun = $row['akun_nama'];
                    endif; ?>

                    <tr class="item-row">
                      <td class="level-item"><?= htmlspecialchars($row['item_nama']) ?></td>
                      <td class="col-center"><?= htmlspecialchars($row['satuan']) ?></td>
                      <td class="col-right"><?= number_format($row['volume'], 0, ',', '.') ?></td>
                      <td class="col-right">Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                      <td class="col-right">Rp <?= number_format($row['pagu'], 0, ',', '.') ?></td>
                      <td colspan="2"></td>
                      <td class="col-center">
                        <?php 
                        if ($has_access_for_action): 
                        ?>
                        <a href="../proses/proses_hapus_item.php?id_item=<?= urlencode($row['id_item']) ?>&tahun=<?= $tahun_filter ?>" 
                          class="btn btn-sm btn-danger"
                          onclick="return confirm('Yakin ingin menghapus item ini?');">
                          <i class="fas fa-trash"></i> Hapus
                        </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach;
                else: ?>
                <tr><td colspan="8" class="text-center text-muted">Tidak ada data master ditemukan.</td></tr>
              <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="total-box">
        Total Dana (Tahun <?= $tahun_filter ?>): Rp <?= number_format($total_pagu, 0, ',', '.') ?>
      </div>
    </div>
  </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script>
$(document).ready(function() {
    $('.auto-save').on('change', function() {
        const selectElement = $(this);
        const akunId = selectElement.data('akun-id');
        const role = selectElement.data('role');
        const pegawaiId = selectElement.val();
        const tahun = selectElement.data('tahun');

        $.ajax({
            url: '../proses/proses_simpan_pegawai.php',
            type: 'POST',
            data: {
                akun_id: akunId,
                role: role,
                pegawai_id: pegawaiId,
                tahun: tahun
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    console.log('Data berhasil disimpan: ' + response.message);
                } else {
                    console.error('Gagal menyimpan data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Terjadi kesalahan AJAX:', error);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>