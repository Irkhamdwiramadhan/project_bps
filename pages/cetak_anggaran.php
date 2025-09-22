<?php
// cetak_anggaran.php (revisi sintaks yang benar)
session_start();
include '../includes/koneksi.php';

/* helper ambil session */
function session_get($keys, $default = null) {
    foreach ((array)$keys as $k) {
        if (isset($_SESSION[$k])) return $_SESSION[$k];
    }
    return $default;
}

/* roles */
function role_in($role_user, $candidates) {
    foreach ($candidates as $r) {
        if (is_array($role_user) && in_array($r, $role_user)) return true;
        if (is_string($role_user) && strcasecmp($role_user, $r) === 0) return true;
    }
    return false;
}

$user_id_raw = session_get(['user_id','id','id_user','uid']);
$role_raw    = session_get(['role','user_role']);
$user_id     = is_numeric($user_id_raw) ? (int)$user_id_raw : null;
$role_user   = $role_raw;

if (!$user_id || !$role_user) {
    http_response_code(403);
    echo "Akses ditolak — silakan login terlebih dahulu.";
    exit;
}

$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$bulan_filter = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');

/* deteksi kolom di akun_pengelola_tahun */
$apt_col = null;
$col_check = $koneksi->query("SHOW COLUMNS FROM akun_pengelola_tahun");
if ($col_check) {
    while ($c = $col_check->fetch_assoc()) {
        if (in_array($c['Field'], ['id_pengelola','pegawai_id','user_id','pengelola_id'])) {
            $apt_col = $c['Field'];
            break;
        }
    }
    $col_check->free();
}
if (!$apt_col) $apt_col = 'id_pengelola'; // fallback

/* Ambil semua data realisasi untuk tahun yang dipilih untuk efisiensi */
$sql_realisasi = "SELECT
                    r.jumlah,
                    rp.id_item,
                    rp.bulan
                  FROM realisasi r
                  LEFT JOIN rpd rp ON r.id_rpd = rp.id
                  WHERE rp.tahun = ?";
$stmt_realisasi = $koneksi->prepare($sql_realisasi);
$stmt_realisasi->bind_param("i", $tahun_filter);
$stmt_realisasi->execute();
$realisasi_result = $stmt_realisasi->get_result();
$data_realisasi = [];
while ($row = $realisasi_result->fetch_assoc()) {
    if (!isset($data_realisasi[$row['id_item']])) {
        $data_realisasi[$row['id_item']] = [];
    }
    $data_realisasi[$row['id_item']][$row['bulan']] = $row['jumlah'];
}
$stmt_realisasi->close();


/* query utama sesuai role */
$is_admin_like = role_in($role_user, ['super_admin','admin_TU']);

if ($is_admin_like) {
    $sql = "SELECT
                mu.id AS unit_id, mu.nama AS unit_nama,
                mp.id AS program_id, mp.nama AS program_nama,
                mo.id AS output_id, mo.nama AS output_nama,
                mk.id AS komponen_id, mk.nama AS komponen_nama,
                ma.id AS akun_id, ma.nama AS akun_nama,
                mi.id AS item_id, mi.nama_item, mi.satuan,
                mi.pagu
            FROM master_item mi
            LEFT JOIN master_akun ma ON mi.id_akun = ma.id
            LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
            LEFT JOIN master_output mo ON mk.id_output = mo.id
            LEFT JOIN master_program mp ON mo.id_program = mp.id
            LEFT JOIN master_unit mu ON mp.id_unit = mu.id
            WHERE mi.tahun = ?
            ORDER BY mu.nama, mp.nama, mo.nama, mk.nama, ma.nama, mi.nama_item ASC";
    $stmt = $koneksi->prepare($sql);
    if (!$stmt) { die("Prepare error: " . $koneksi->error); }
    $stmt->bind_param("i", $tahun_filter);
} else {
    $sql = "SELECT
                mu.id AS unit_id, mu.nama AS unit_nama,
                mp.id AS program_id, mp.nama AS program_nama,
                mo.id AS output_id, mo.nama AS output_nama,
                mk.id AS komponen_id, mk.nama AS komponen_nama,
                ma.id AS akun_id, ma.nama AS akun_nama,
                mi.id AS item_id, mi.nama_item, mi.satuan,
                mi.pagu
            FROM master_item mi
            INNER JOIN master_akun ma ON mi.id_akun = ma.id
            INNER JOIN master_komponen mk ON ma.id_komponen = mk.id
            INNER JOIN master_output mo ON mk.id_output = mo.id
            INNER JOIN master_program mp ON mo.id_program = mp.id
            INNER JOIN master_unit mu ON mp.id_unit = mu.id
            INNER JOIN akun_pengelola_tahun apt ON mi.id_akun = apt.akun_id AND mi.tahun = apt.tahun
            WHERE mi.tahun = ? AND apt.`$apt_col` = ?
            ORDER BY mu.nama, mp.nama, mo.nama, mk.nama, ma.nama, mi.nama_item ASC";
    $stmt = $koneksi->prepare($sql);
    if (!$stmt) { die("Prepare error: " . $koneksi->error); }
    $stmt->bind_param("ii", $tahun_filter, $user_id);
}

/* eksekusi */
$rows = [];
if ($stmt->execute()) {
    $res = $stmt->get_result();
    if ($res) {
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
    }
} else {
    $err = $stmt->error;
    $stmt->close();
    die("Error executing query: " . htmlspecialchars($err));
}
$stmt->close();

/* build tree dan hitung total realisasi per item */
$tree = [];
$grand = ['pagu' => 0, 'rpd' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0];

foreach ($rows as $r) {
    // Hitung realisasi per item
    $realisasi_lalu_item = 0;
    $realisasi_ini_item = 0;
    $realisasi_sd_item = 0;
    
    if (isset($data_realisasi[$r['item_id']])) {
        foreach ($data_realisasi[$r['item_id']] as $bulan => $jumlah) {
            if ($bulan < $bulan_filter) {
                $realisasi_lalu_item += $jumlah;
            }
            if ($bulan == $bulan_filter) {
                $realisasi_ini_item += $jumlah;
            }
            if ($bulan <= $bulan_filter) {
                $realisasi_sd_item += $jumlah;
            }
        }
    }
    
    $pagu = isset($r['pagu']) ? (float)$r['pagu'] : 0.0;

    $unit_id = $r['unit_id'] ?? 0;
    $prog_id = $r['program_id'] ?? 0;
    $out_id  = $r['output_id'] ?? 0;
    $komp_id = $r['komponen_id'] ?? 0;
    $akun_id = $r['akun_id'] ?? 0;
    $item_id = $r['item_id'] ?? 0;

    if (!isset($tree[$unit_id])) {
        $tree[$unit_id] = ['nama' => $r['unit_nama'] ?? '-', 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0, 'programs' => []];
    }
    if (!isset($tree[$unit_id]['programs'][$prog_id])) {
        $tree[$unit_id]['programs'][$prog_id] = ['nama' => $r['program_nama'] ?? '-', 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0, 'outputs' => []];
    }
    if (!isset($tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id])) {
        $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id] = ['nama' => $r['output_nama'] ?? '-', 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0, 'komponens' => []];
    }
    if (!isset($tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id])) {
        $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id] = ['nama' => $r['komponen_nama'] ?? '-', 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0, 'akuns' => []];
    }
    if (!isset($tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id])) {
        $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id] = ['nama' => $r['akun_nama'] ?? '-', 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0, 'items' => []];
    }

    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id]['items'][] = [
        'id' => $item_id,
        'nama' => $r['nama_item'] ?? '-',
        'satuan' => $r['satuan'] ?? '',
        'pagu' => $pagu,
        'realisasi_lalu' => $realisasi_lalu_item,
        'realisasi_ini' => $realisasi_ini_item,
        'realisasi_sd' => $realisasi_sd_item,
    ];

    /* propagate totals */
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id]['pagu'] += $pagu;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id]['realisasi_lalu'] += $realisasi_lalu_item;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id]['realisasi_ini'] += $realisasi_ini_item;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['akuns'][$akun_id]['realisasi_sd'] += $realisasi_sd_item;

    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['pagu'] += $pagu;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['realisasi_lalu'] += $realisasi_lalu_item;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['realisasi_ini'] += $realisasi_ini_item;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['komponens'][$komp_id]['realisasi_sd'] += $realisasi_sd_item;

    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['pagu'] += $pagu;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['realisasi_lalu'] += $realisasi_lalu_item;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['realisasi_ini'] += $realisasi_ini_item;
    $tree[$unit_id]['programs'][$prog_id]['outputs'][$out_id]['realisasi_sd'] += $realisasi_sd_item;

    $tree[$unit_id]['programs'][$prog_id]['pagu'] += $pagu;
    $tree[$unit_id]['programs'][$prog_id]['realisasi_lalu'] += $realisasi_lalu_item;
    $tree[$unit_id]['programs'][$prog_id]['realisasi_ini'] += $realisasi_ini_item;
    $tree[$unit_id]['programs'][$prog_id]['realisasi_sd'] += $realisasi_sd_item;

    $tree[$unit_id]['pagu'] += $pagu;
    $tree[$unit_id]['realisasi_lalu'] += $realisasi_lalu_item;
    $tree[$unit_id]['realisasi_ini'] += $realisasi_ini_item;
    $tree[$unit_id]['realisasi_sd'] += $realisasi_sd_item;

    $grand['pagu'] += $pagu;
    $grand['realisasi_lalu'] += $realisasi_lalu_item;
    $grand['realisasi_ini'] += $realisasi_ini_item;
    $grand['realisasi_sd'] += $realisasi_sd_item;
}

/* rupiah helper */
function rupiah($v){ return number_format((float)$v,0,',','.'); }

/* get month name */
function get_month_name($month) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $months[$month] ?? '';
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak Anggaran <?= htmlspecialchars($tahun_filter) ?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#222;margin:20px}
.print-button-container { text-align: right; margin-bottom: 20px; }
.print-button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
@media print {
    body{ margin: 0; }
    .print-button-container { display: none; }
}
.title-container { text-align: center; margin-bottom: 20px; }
.title-container h1 { margin: 0; font-size: 16px; font-weight: bold; }
.title-container p { margin: 5px 0 0; font-size: 12px; }

.rekap{width:100%;border-collapse:collapse;margin-top:10px}
.rekap th,.rekap td{border:1px solid #999;padding:6px 8px;font-size:12px;vertical-align:top;text-align:right}
.rekap th{background:#f2f4f7;text-align:center}
.rekap thead th.uraian-col { text-align: left; }
.level-unit td, .level-program td, .level-output td, .level-komponen td, .level-akun td { font-weight: bold; }
.level-unit td { background:#cfeefc; }
.level-program td { background:#eef6ff; }
.level-output td { background:#f7fbff; }
.level-komponen td { background:#fff; }
.level-akun td { background:#fffdfa; }
.level-item td { background:#fff; }
.indent-1 { padding-left: 20px !important; }
.indent-2 { padding-left: 40px !important; }
.indent-3 { padding-left: 60px !important; }
.indent-4 { padding-left: 80px !important; }
.indent-5 { padding-left: 100px !important; }
</style>
</head>
<body>

<div class="print-button-container">
    <button onclick="window.print()" class="print-button">Cetak PDF</button>
</div>

<div class="title-container">
    <h1>LAPORAN KETERSEDIAAN DANA DETAIL TA <?= htmlspecialchars($tahun_filter) ?></h1>
    <p>
        Unit - Program - Output - Komponen - Akun - Detail Item
        <br>
        Periode <?= get_month_name($bulan_filter) ?> <?= htmlspecialchars($tahun_filter) ?>
    </p>
</div>

<table class="rekap">
<thead>
<tr>
    <th rowspan="2" class="uraian-col">Uraian</th>
    <th rowspan="2">Pagu Revisi</th>
    <th rowspan="2">Lock Pagu</th>
    <th colspan="4">Realisasi TA <?= htmlspecialchars($tahun_filter) ?></th>
    <th rowspan="2">SISA ANGGARAN</th>
</tr>
<tr>
    <th>Periode Lalu</th>
    <th>Periode Ini</th>
    <th>s.d. Periode</th>
    <th>%</th>
</tr>
</thead>
<tbody>
<tr style="background:#cfeefc;font-weight:bold">
    <td style="text-align:left;">JUMLAH SELURUHNYA</td>
    <td><?= rupiah($grand['pagu']) ?></td>
    <td>0</td>
    <td><?= rupiah($grand['realisasi_lalu']) ?></td>
    <td><?= rupiah($grand['realisasi_ini']) ?></td>
    <td><?= rupiah($grand['realisasi_sd']) ?></td>
    <td><?= $grand['pagu']>0 ? number_format(($grand['realisasi_sd']/$grand['pagu'])*100,2,',','.') . '%' : '0.00%' ?></td>
    <td><?= rupiah($grand['pagu'] - $grand['realisasi_sd']) ?></td>
</tr>

<?php
if (empty($tree)) {
    echo '<tr><td colspan="8" style="text-align:center;padding:20px">Tidak ada data anggaran untuk tahun ini.</td></tr>';
} else {
    foreach ($tree as $unit) {
        $pagu = $unit['pagu'];
        $real_lalu = $unit['realisasi_lalu'];
        $real_ini = $unit['realisasi_ini'];
        $real_sd = $unit['realisasi_sd'];
        $sisa = $pagu - $real_sd;
        $pct = $pagu > 0 ? ($real_sd / $pagu) * 100 : 0;
        echo '<tr class="level-unit">';
        echo '<td style="text-align:left;"><strong>UNIT: ' . htmlspecialchars($unit['nama']) . '</strong></td>';
        echo '<td>' . rupiah($pagu) . '</td>';
        echo '<td>0</td>';
        echo '<td>' . rupiah($real_lalu) . '</td>';
        echo '<td>' . rupiah($real_ini) . '</td>';
        echo '<td>' . rupiah($real_sd) . '</td>';
        echo '<td>' . number_format($pct,2,',','.') . '%</td>';
        echo '<td>' . rupiah($sisa) . '</td>';
        echo '</tr>';

        foreach ($unit['programs'] as $prog) {
            $pagu = $prog['pagu'];
            $real_lalu = $prog['realisasi_lalu'];
            $real_ini = $prog['realisasi_ini'];
            $real_sd = $prog['realisasi_sd'];
            $sisa = $pagu - $real_sd;
            $pct = $pagu > 0 ? ($real_sd / $pagu) * 100 : 0;
            echo '<tr class="level-program">';
            echo '<td class="indent-1" style="text-align:left;"><strong>Program: ' . htmlspecialchars($prog['nama']) . '</strong></td>';
            echo '<td>' . rupiah($pagu) . '</td>';
            echo '<td>0</td>';
            echo '<td>' . rupiah($real_lalu) . '</td>';
            echo '<td>' . rupiah($real_ini) . '</td>';
            echo '<td>' . rupiah($real_sd) . '</td>';
            echo '<td>' . number_format($pct,2,',','.') . '%</td>';
            echo '<td>' . rupiah($sisa) . '</td>';
            echo '</tr>';

            foreach ($prog['outputs'] as $out) {
                $pagu = $out['pagu'];
                $real_lalu = $out['realisasi_lalu'];
                $real_ini = $out['realisasi_ini'];
                $real_sd = $out['realisasi_sd'];
                $sisa = $pagu - $real_sd;
                $pct = $pagu > 0 ? ($real_sd / $pagu) * 100 : 0;
                echo '<tr class="level-output">';
                echo '<td class="indent-2" style="text-align:left;"><em>Output: ' . htmlspecialchars($out['nama']) . '</em></td>';
                echo '<td>' . rupiah($pagu) . '</td>';
                echo '<td>0</td>';
                echo '<td>' . rupiah($real_lalu) . '</td>';
                echo '<td>' . rupiah($real_ini) . '</td>';
                echo '<td>' . rupiah($real_sd) . '</td>';
                echo '<td>' . number_format($pct,2,',','.') . '%</td>';
                echo '<td>' . rupiah($sisa) . '</td>';
                echo '</tr>';

                foreach ($out['komponens'] as $komp) {
                    $pagu = $komp['pagu'];
                    $real_lalu = $komp['realisasi_lalu'];
                    $real_ini = $komp['realisasi_ini'];
                    $real_sd = $komp['realisasi_sd'];
                    $sisa = $pagu - $real_sd;
                    $pct = $pagu > 0 ? ($real_sd / $pagu) * 100 : 0;
                    echo '<tr class="level-komponen">';
                    echo '<td class="indent-3" style="text-align:left;">Komponen: ' . htmlspecialchars($komp['nama']) . '</td>';
                    echo '<td>' . rupiah($pagu) . '</td>';
                    echo '<td>0</td>';
                    echo '<td>' . rupiah($real_lalu) . '</td>';
                    echo '<td>' . rupiah($real_ini) . '</td>';
                    echo '<td>' . rupiah($real_sd) . '</td>';
                    echo '<td>' . number_format($pct,2,',','.') . '%</td>';
                    echo '<td>' . rupiah($sisa) . '</td>';
                    echo '</tr>';

                    foreach ($komp['akuns'] as $akun) {
                        $pagu = $akun['pagu'];
                        $real_lalu = $akun['realisasi_lalu'];
                        $real_ini = $akun['realisasi_ini'];
                        $real_sd = $akun['realisasi_sd'];
                        $sisa = $pagu - $real_sd;
                        $pct = $pagu > 0 ? ($real_sd / $pagu) * 100 : 0;
                        echo '<tr class="level-akun">';
                        echo '<td class="indent-4" style="text-align:left;"><strong>Akun: ' . htmlspecialchars($akun['nama']) . '</strong></td>';
                        echo '<td>' . rupiah($pagu) . '</td>';
                        echo '<td>0</td>';
                        echo '<td>' . rupiah($real_lalu) . '</td>';
                        echo '<td>' . rupiah($real_ini) . '</td>';
                        echo '<td>' . rupiah($real_sd) . '</td>';
                        echo '<td>' . number_format($pct,2,',','.') . '%</td>';
                        echo '<td>' . rupiah($sisa) . '</td>';
                        echo '</tr>';

                        foreach ($akun['items'] as $it) {
                            $pagu = $it['pagu'];
                            $real_lalu = $it['realisasi_lalu'];
                            $real_ini = $it['realisasi_ini'];
                            $real_sd = $it['realisasi_sd'];
                            $sisa = $pagu - $real_sd;
                            $pct = $pagu > 0 ? ($real_sd / $pagu) * 100 : 0;
                            echo '<tr class="level-item">';
                            echo '<td class="indent-5" style="text-align:left;">- ' . htmlspecialchars($it['nama']) . ' <small>(' . htmlspecialchars($it['satuan']) . ')</small></td>';
                            echo '<td>' . rupiah($pagu) . '</td>';
                            echo '<td>0</td>';
                            echo '<td>' . rupiah($real_lalu) . '</td>';
                            echo '<td>' . rupiah($real_ini) . '</td>';
                            echo '<td>' . rupiah($real_sd) . '</td>';
                            echo '<td>' . number_format($pct,2,',','.') . '%</td>';
                            echo '<td>' . rupiah($sisa) . '</td>';
                            echo '</tr>';
                        }
                    }
                }
            }
        }
    }
}
?>
</tbody>
</table>
</body>
</html>