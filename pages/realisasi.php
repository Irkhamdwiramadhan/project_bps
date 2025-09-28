<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil user ID dan role dari session
$id_user   = $_SESSION['user_id'] ?? null;
$role_user = $_SESSION['role'] ?? null;

// Tentukan tahun filter. Jika tidak ada, gunakan tahun saat ini.
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");

// Ambil daftar tahun
$daftar_tahun = [];
if ($role_user === 'super_admin' || $role_user === 'admin_TU') {
    $tahun_query = "SELECT DISTINCT tahun FROM master_item UNION SELECT DISTINCT tahun FROM rpd ORDER BY tahun DESC";
    $stmt_tahun  = $koneksi->prepare($tahun_query);
} else {
    $tahun_query = "SELECT DISTINCT mi.tahun 
                    FROM master_item mi
                    INNER JOIN akun_pengelola_tahun apt 
                            ON mi.id_akun = apt.akun_id AND apt.tahun = mi.tahun
                    WHERE apt.id_pengelola = ? 
                    ORDER BY mi.tahun DESC";
    $stmt_tahun  = $koneksi->prepare($tahun_query);
    $stmt_tahun->bind_param("i", $id_user);
}
$stmt_tahun->execute();
$tahun_result = $stmt_tahun->get_result();
while ($row = $tahun_result->fetch_assoc()) {
    $daftar_tahun[] = $row['tahun'];
}
$stmt_tahun->close();

// Validasi tahun
if (empty($daftar_tahun)) {
    $tahun_filter = null;
} elseif (!in_array($tahun_filter, $daftar_tahun)) {
    $tahun_filter = $daftar_tahun[0];
}

// Ambil data anggaran
$data_anggaran = [];
if ($tahun_filter !== null) {
    if ($role_user === 'super_admin' || $role_user === 'admin_TU') {
        $sql = "SELECT
                    mi.id AS id_item,
                    mi.nama_item,
                    mi.pagu,
                    mu.nama AS unit_nama,
                    mp.nama AS program_nama,
                    mo.nama AS output_nama,
                    ma.nama AS akun_nama,
                    mk.nama AS komponen_nama,
                    COALESCE(SUM(rpd.jumlah),0) AS total_rpd,
                    COALESCE(SUM(realisasi.jumlah),0) AS total_realisasi
                FROM master_item mi
                LEFT JOIN master_akun ma     ON mi.id_akun = ma.id
                LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
                LEFT JOIN master_output mo   ON mk.id_output = mo.id
                LEFT JOIN master_program mp  ON mo.id_program = mp.id
                LEFT JOIN master_unit mu   ON mp.id_unit = mu.id
                LEFT JOIN rpd ON mi.id = rpd.id_item AND rpd.tahun = mi.tahun
                LEFT JOIN realisasi ON rpd.id = realisasi.id_rpd
                WHERE mi.tahun = ?
                GROUP BY mi.id, mu.nama, mp.nama, mo.nama, mk.nama, ma.nama
                ORDER BY mu.nama, mp.nama, mo.nama, mk.nama, ma.nama, mi.nama_item ASC";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("i", $tahun_filter);
    } else {
        $sql = "SELECT
                    mi.id AS id_item,
                    mi.nama_item,
                    mi.pagu,
                    mu.nama AS unit_nama,
                    mp.nama AS program_nama,
                    mo.nama AS output_nama,
                    ma.nama AS akun_nama,
                    mk.nama AS komponen_nama,
                    COALESCE(SUM(rpd.jumlah),0) AS total_rpd,
                    COALESCE(SUM(realisasi.jumlah),0) AS total_realisasi
                FROM master_item mi
                LEFT JOIN master_akun ma     ON mi.id_akun = ma.id
                LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
                LEFT JOIN master_output mo   ON mk.id_output = mo.id
                LEFT JOIN master_program mp  ON mo.id_program = mp.id
                LEFT JOIN master_unit mu   ON mp.id_unit = mu.id
                LEFT JOIN rpd ON mi.id = rpd.id_item AND rpd.tahun = mi.tahun
                LEFT JOIN realisasi ON rpd.id = realisasi.id_rpd
                INNER JOIN akun_pengelola_tahun apt 
                        ON mi.id_akun = apt.akun_id AND apt.tahun = mi.tahun
                WHERE mi.tahun = ? AND apt.id_pengelola = ?
                GROUP BY mi.id, mu.nama, mp.nama, mo.nama, mk.nama, ma.nama
                ORDER BY mu.nama, mp.nama, mo.nama, mk.nama, ma.nama, mi.nama_item ASC";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("ii", $tahun_filter, $id_user);
    }

    $stmt->execute();
    $data_anggaran = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<style>
.main-content { padding:30px; background:#f7f9fc; }
.header-container { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px; }
.section-title { font-size:1.5rem; font-weight:700; margin:0; }
.card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.table-responsive { overflow:auto; max-height:70vh; }
.data-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.data-table th, .data-table td { padding:10px; border-bottom:1px solid #dee2e6; text-align:center; }
.data-table thead tr th { background:#f7f9fc; font-weight:600; position:sticky; z-index:10; }
.data-table thead tr:first-child th { top:0; }
.data-table thead tr:nth-child(2) th { top:40px; }
.data-table thead tr:nth-child(3) th { top:80px; }
.data-table td.col-left { text-align:left; }
.data-table .total-cell, .data-table .pagu-cell, .data-table .sisa-cell { font-weight:bold; }
.text-muted { color:#6c757d; }
.year-buttons { display:flex; gap:5px; align-items:center; margin-bottom:15px; }
.year-buttons .btn { border:1px solid #e0e0e0; color:#333; background:#f8f9fa; border-radius:5px; padding:8px 15px; text-decoration:none; font-size:0.9rem; }
.year-buttons .btn.active { background:#007bff; color:#fff; border-color:#007bff; }
.add-rpd-button-container { text-align:right; margin-bottom:20px; }

/* Tambahan CSS untuk Hierarki */
.hierarchy-row td { font-weight: bold; border-bottom: none; }
.level-unit { color:#004d99; }
.level-program { color:#196f3d; padding-left:20px !important; }
.level-output { color:#d68910; padding-left:40px !important; }
.level-komponen { color:#5b2c6f; padding-left:60px !important; }
.level-akun { color:#515a5a; padding-left:80px !important; }
.level-item { font-weight:normal; padding-left:100px !important; }
</style>

<main class="main-content">
    <div class="container">
        <div class="header-container">
            <h2 class="section-title">Realisasi Anggaran</h2>
            <div class="add-rpd-button-container">
                <a href="tambah_realisasi.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Realisasikan
                </a>
            </div>
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
                    <thead>
                        <tr>
                            <th rowspan="3">Uraian Anggaran</th>
                            <th colspan="24">Rencana Per Bulan</th>
                            <th rowspan="3">Total RPD</th>
                            <th rowspan="3">Total Realisasi</th>
                            <th rowspan="3">Pagu Anggaran</th>
                            <th rowspan="3">Sisa Anggaran</th>
                        </tr>
                        <tr>
                            <?php for ($i=1;$i<=12;$i++): ?>
                                <th colspan="2"><?= date('M', mktime(0,0,0,$i,1)) ?></th>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <?php for ($i=1;$i<=12;$i++): ?>
                                <th>RPD</th><th>Real</th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data_anggaran)):
                            $prev_unit = $prev_program = $prev_output = $prev_komponen = $prev_akun = null;
                            foreach ($data_anggaran as $row):
                                // Cek dan cetak baris hierarki untuk Unit
                                if ($row['unit_nama'] !== $prev_unit): ?>
                                    <tr class="hierarchy-row"><td colspan="29" class="col-left level-unit"><?= htmlspecialchars($row['unit_nama']) ?></td></tr>
                                    <?php $prev_unit = $row['unit_nama'];
                                    $prev_program = $prev_output = $prev_komponen = $prev_akun = null;
                                endif;

                                // Cek dan cetak baris hierarki untuk Program
                                if ($row['program_nama'] !== $prev_program): ?>
                                    <tr class="hierarchy-row"><td colspan="29" class="col-left level-program"><?= htmlspecialchars($row['program_nama']) ?></td></tr>
                                    <?php $prev_program = $row['program_nama'];
                                    $prev_output = $prev_komponen = $prev_akun = null;
                                endif;

                                // Cek dan cetak baris hierarki untuk Output
                                if ($row['output_nama'] !== $prev_output): ?>
                                    <tr class="hierarchy-row"><td colspan="29" class="col-left level-output"><?= htmlspecialchars($row['output_nama']) ?></td></tr>
                                    <?php $prev_output = $row['output_nama'];
                                    $prev_komponen = $prev_akun = null;
                                endif;

                                // Cek dan cetak baris hierarki untuk Komponen
                                if ($row['komponen_nama'] !== $prev_komponen): ?>
                                    <tr class="hierarchy-row"><td colspan="29" class="col-left level-komponen">Komponen: <?= htmlspecialchars($row['komponen_nama']) ?></td></tr>
                                    <?php $prev_komponen = $row['komponen_nama'];
                                endif;

                                // Cek dan cetak baris hierarki untuk Akun
                                if ($row['akun_nama'] !== $prev_akun): ?>
                                    <tr class="hierarchy-row"><td colspan="29" class="col-left level-akun">Akun: <?= htmlspecialchars($row['akun_nama']) ?></td></tr>
                                    <?php $prev_akun = $row['akun_nama'];
                                endif;
                            ?>
                            <tr>
                                <td class="col-left level-item"><?= htmlspecialchars($row['nama_item']) ?></td>
                                <?php
                                $sql_detail = "SELECT rpd.bulan,
                                                    COALESCE(SUM(rpd.jumlah),0) AS jumlah_rpd,
                                                    COALESCE(SUM(realisasi.jumlah),0) AS jumlah_realisasi
                                                FROM rpd
                                                LEFT JOIN realisasi ON rpd.id = realisasi.id_rpd
                                                WHERE rpd.id_item = ? AND rpd.tahun = ?
                                                GROUP BY rpd.bulan ORDER BY rpd.bulan";
                                $stmt_detail = $koneksi->prepare($sql_detail);
                                $stmt_detail->bind_param("ii", $row['id_item'], $tahun_filter);
                                $stmt_detail->execute();
                                $detail_result = $stmt_detail->get_result();
                                $data_bulan = [];
                                while ($d = $detail_result->fetch_assoc()) {
                                    $data_bulan[$d['bulan']] = $d;
                                }
                                $stmt_detail->close();

                                for ($b=1;$b<=12;$b++):
                                    $rpd  = $data_bulan[$b]['jumlah_rpd'] ?? 0;
                                    $real = $data_bulan[$b]['jumlah_realisasi'] ?? 0;
                                ?>
                                    <td class="text-primary"><?= number_format($rpd,0,',','.') ?></td>
                                    <td class="text-success"><?= number_format($real,0,',','.') ?></td>
                                <?php endfor; ?>
                                <td class="total-cell"><?= number_format($row['total_rpd'],0,',','.') ?></td>
                                <td class="total-cell"><?= number_format($row['total_realisasi'],0,',','.') ?></td>
                                <td class="pagu-cell"><?= number_format($row['pagu'],0,',','.') ?></td>
                                <td class="sisa-cell"><?= number_format($row['pagu'] - $row['total_realisasi'],0,',','.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="29" class="text-center text-muted">Tidak ada data anggaran untuk tahun ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>