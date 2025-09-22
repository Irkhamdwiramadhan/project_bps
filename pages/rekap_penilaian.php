<?php
/* =============================
   FILE: pages/rekap_penilaian.php
   ============================= */
?>
<?php
include_once '../includes/koneksi.php';
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/style.css">
<div class="page-container">
  <div class="page-header">
    <h1>Rekap Penilaian Triwulan</h1>
    <p class="muted">Lihat total skor & jumlah penilai per calon. Urut dari skor tertinggi.</p>
  </div>

  <?php
    $r_triwulan = isset($_GET['triwulan']) ? (int)$_GET['triwulan'] : 1;
    $r_tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
  ?>

  <div class="card">
    <div class="card-body">
      <form method="get" class="form-row">
        <div class="form-group">
          <label>Triwulan</label>
          <select name="triwulan">
            <?php for($i=1;$i<=4;$i++): ?>
              <option value="<?php echo $i; ?>" <?php echo $r_triwulan===$i?'selected':''; ?>>Triwulan <?php echo $i; ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Tahun</label>
          <input type="number" name="tahun" value="<?php echo $r_tahun; ?>" min="2020" max="2100">
        </div>
        <div class="form-group">
          <label>&nbsp;</label>
          <button class="btn">Tampilkan</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mt-lg">
    <div class="card-header flex-between">
      <h3>Hasil Rekap – Triwulan <?php echo $r_triwulan; ?> / <?php echo $r_tahun; ?></h3>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nama Calon</th>
              <th>Jumlah Penilai</th>
              <th>Total Skor</th>
              <th>Rata-rata</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $stmt = $koneksi->prepare(
                "SELECT ct.id AS id_calon, p.nama,
                        COUNT(DISTINCT pt.nama_penilai) AS jml_penilai,
                        SUM(pt.total_skor) AS total_skor
                 FROM penilaian_triwulan pt
                 JOIN calon_triwulan ct ON ct.id = pt.id_calon
                 JOIN pegawai p ON p.id = ct.id_pegawai
                 WHERE ct.triwulan=? AND ct.tahun=?
                 GROUP BY ct.id, p.nama
                 ORDER BY total_skor DESC"
              );
              $stmt->bind_param('ii', $r_triwulan, $r_tahun);
              $stmt->execute();
              $rs = $stmt->get_result();
              $rank=1; $empty=true;
              while($row = $rs->fetch_assoc()): $empty=false; ?>
                <tr>
                  <td><?php echo $rank++; ?></td>
                  <td><?php echo htmlspecialchars($row['nama']); ?></td>
                  <td><?php echo (int)$row['jml_penilai']; ?></td>
                  <td><?php echo (int)$row['total_skor']; ?></td>
                  <td><?php echo $row['jml_penilai']>0 ? number_format($row['total_skor']/$row['jml_penilai'],2) : '0.00'; ?></td>
                </tr>
              <?php endwhile; $stmt->close();
              if ($empty): ?>
                <tr><td colspan="5" class="text-center muted">Belum ada data penilaian untuk periode ini.</td></tr>
              <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Breakdown per kategori (opsional) -->
      <h4 class="mt-lg">Rata-rata Per Kategori</h4>
      <div class="table-responsive">
        <table class="table compact">
          <thead>
            <tr>
              <th>Nama Calon</th>
              <th>Berorientasi</th>
              <th>Akuntabel</th>
              <th>Kompeten</th>
              <th>Harmonis</th>
              <th>Loyal</th>
              <th>Adaptif</th>
              <th>Kolaboratif</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $stmt = $koneksi->prepare(
                "SELECT p.nama,
                        AVG(pt.skor_berorientasi) AS a, AVG(pt.skor_akuntabel) AS b, AVG(pt.skor_kompeten) AS c,
                        AVG(pt.skor_harmonis) AS d, AVG(pt.skor_loyal) AS e, AVG(pt.skor_adaptif) AS f, AVG(pt.skor_kolaboratif) AS g
                 FROM penilaian_triwulan pt
                 JOIN calon_triwulan ct ON ct.id = pt.id_calon
                 JOIN pegawai p ON p.id = ct.id_pegawai
                 WHERE ct.triwulan=? AND ct.tahun=?
                 GROUP BY p.nama
                 ORDER BY p.nama ASC"
              );
              $stmt->bind_param('ii', $r_triwulan, $r_tahun);
              $stmt->execute();
              $rs=$stmt->get_result();
              if ($rs->num_rows===0): ?>
                <tr><td colspan="8" class="text-center muted">Belum ada data.</td></tr>
              <?php else:
                while($row=$rs->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                    <td><?php echo number_format($row['a'],2); ?></td>
                    <td><?php echo number_format($row['b'],2); ?></td>
                    <td><?php echo number_format($row['c'],2); ?></td>
                    <td><?php echo number_format($row['d'],2); ?></td>
                    <td><?php echo number_format($row['e'],2); ?></td>
                    <td><?php echo number_format($row['f'],2); ?></td>
                    <td><?php echo number_format($row['g'],2); ?></td>
                  </tr>
                <?php endwhile; endif; $stmt->close(); ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include_once '../includes/footer.php'; ?>
