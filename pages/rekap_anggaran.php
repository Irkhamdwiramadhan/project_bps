<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil tahun dan bulan dari parameter URL, default ke tahun dan bulan saat ini
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$bulan_filter = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date("n");

// Query untuk mengambil semua data yang diperlukan secara hierarki
$sql = "SELECT
            mp.nama AS program_nama, mp.id AS program_id,
            mo.nama AS output_nama, mo.id AS output_id,
            mk.nama AS komponen_nama, mk.id AS komponen_id,
            ma.nama AS akun_nama, ma.id AS akun_id,
            mi.nama_item, mi.pagu, mi.id AS id_item
        FROM master_item mi
        LEFT JOIN master_akun ma ON mi.id_akun = ma.id
        LEFT JOIN master_komponen mk ON ma.id_komponen = mk.id
        LEFT JOIN master_output mo ON mk.id_output = mo.id
        LEFT JOIN master_program mp ON mo.id_program = mp.id
        WHERE mi.tahun = ?
        ORDER BY mp.id, mo.id, mk.id, ma.id, mi.id ASC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $tahun_filter);
$stmt->execute();
$data_master = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ambil semua data realisasi untuk tahun yang dipilih untuk efisiensi
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
    $data_realisasi[$row['id_item']][$row['bulan']] = $row['jumlah'];
}
$stmt_realisasi->close();

// Bangun struktur hierarki dan hitung total
$rekap_data = [];
$grand_total_pagu = 0;
$grand_total_realisasi_lalu = 0;
$grand_total_realisasi_ini = 0;
$grand_total_realisasi_sd = 0;

foreach ($data_master as $row) {
    // Hitung realisasi untuk item saat ini
    $realisasi_lalu_item = 0;
    $realisasi_ini_item = 0;
    $realisasi_sd_item = 0;
    
    if (isset($data_realisasi[$row['id_item']])) {
        foreach ($data_realisasi[$row['id_item']] as $bulan => $jumlah) {
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

    $sisa_anggaran_item = $row['pagu'] - $realisasi_sd_item;
    $persentase_item = ($row['pagu'] > 0) ? ($realisasi_sd_item / $row['pagu']) * 100 : 0;

    // Tambahkan item ke struktur hierarki
    if (!isset($rekap_data[$row['program_id']])) {
        $rekap_data[$row['program_id']] = ['nama' => $row['program_nama'], 'children' => [], 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0];
    }
    if (!isset($rekap_data[$row['program_id']]['children'][$row['output_id']])) {
        $rekap_data[$row['program_id']]['children'][$row['output_id']] = ['nama' => $row['output_nama'], 'children' => [], 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0];
    }
    if (!isset($rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']])) {
        $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']] = ['nama' => $row['komponen_nama'], 'children' => [], 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0];
    }
    if (!isset($rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']])) {
        $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']] = ['nama' => $row['akun_nama'], 'children' => [], 'pagu' => 0, 'realisasi_lalu' => 0, 'realisasi_ini' => 0, 'realisasi_sd' => 0];
    }

    $item_data = [
        'nama' => $row['nama_item'],
        'pagu' => $row['pagu'],
        'realisasi_lalu' => $realisasi_lalu_item,
        'realisasi_ini' => $realisasi_ini_item,
        'realisasi_sd' => $realisasi_sd_item,
        'sisa_anggaran' => $sisa_anggaran_item,
        'persentase' => $persentase_item
    ];

    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']]['children'][] = $item_data;

    // Hitung total ke atas
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']]['pagu'] += $item_data['pagu'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']]['realisasi_lalu'] += $item_data['realisasi_lalu'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']]['realisasi_ini'] += $item_data['realisasi_ini'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['children'][$row['akun_id']]['realisasi_sd'] += $item_data['realisasi_sd'];

    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['pagu'] += $item_data['pagu'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['realisasi_lalu'] += $item_data['realisasi_lalu'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['realisasi_ini'] += $item_data['realisasi_ini'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['children'][$row['komponen_id']]['realisasi_sd'] += $item_data['realisasi_sd'];

    $rekap_data[$row['program_id']]['children'][$row['output_id']]['pagu'] += $item_data['pagu'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['realisasi_lalu'] += $item_data['realisasi_lalu'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['realisasi_ini'] += $item_data['realisasi_ini'];
    $rekap_data[$row['program_id']]['children'][$row['output_id']]['realisasi_sd'] += $item_data['realisasi_sd'];
    
    $rekap_data[$row['program_id']]['pagu'] += $item_data['pagu'];
    $rekap_data[$row['program_id']]['realisasi_lalu'] += $item_data['realisasi_lalu'];
    $rekap_data[$row['program_id']]['realisasi_ini'] += $item_data['realisasi_ini'];
    $rekap_data[$row['program_id']]['realisasi_sd'] += $item_data['realisasi_sd'];

    $grand_total_pagu += $item_data['pagu'];
    $grand_total_realisasi_lalu += $item_data['realisasi_lalu'];
    $grand_total_realisasi_ini += $item_data['realisasi_ini'];
    $grand_total_realisasi_sd += $item_data['realisasi_sd'];
}

// Fungsi untuk mencetak baris hierarki
// Fungsi untuk mencetak baris hierarki dengan indentasi pada kolom Uraian
function printHierarchy($data, $level = 0) {
    foreach ($data as $id => $item) {
        // Hitung persentase & sisa anggaran
        $sisa_anggaran_level = $item['pagu'] - $item['realisasi_sd'];
        $persentase_level = ($item['pagu'] > 0) ? ($item['realisasi_sd'] / $item['pagu']) * 100 : 0;

        // Buat indentasi sesuai level
        $indent = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $level);

        // Tambahkan ikon level biar lebih jelas
        $prefix = "";
        switch ($level) {
            case 0: $prefix = "<strong>Program:</strong> "; break;
            case 1: $prefix = "<strong>Output:</strong> "; break;
            case 2: $prefix = "<strong>Komponen:</strong> "; break;
            case 3: $prefix = "<strong>Akun:</strong> "; break;
        }

        // Cetak baris level program/output/komponen/akun
        echo "<tr class='hierarchy-row'>";
        echo "<td style='text-align:left;'>{$indent}{$prefix}" . htmlspecialchars($item['nama']) . "</td>";
        echo "<td>" . number_format($item['pagu'], 0, ',', '.') . "</td>";
        echo "<td>0</td>";
        echo "<td>" . number_format($item['realisasi_lalu'], 0, ',', '.') . "</td>";
        echo "<td>" . number_format($item['realisasi_ini'], 0, ',', '.') . "</td>";
        echo "<td>" . number_format($item['realisasi_sd'], 0, ',', '.') . "</td>";
        echo "<td>" . number_format($persentase_level, 2, ',', '.') . "%</td>";
        echo "<td>" . number_format($sisa_anggaran_level, 0, ',', '.') . "</td>";
        echo "</tr>";

        // Cetak children
        if (!empty($item['children'])) {
            if ($level < 3) {
                printHierarchy($item['children'], $level + 1);
            } else {
                foreach ($item['children'] as $child) {
                    $indent_item = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $level + 1);
                    echo "<tr>";
                    echo "<td style='text-align:left;'>{$indent_item} - " . htmlspecialchars($child['nama']) . "</td>";
                    echo "<td>" . number_format($child['pagu'], 0, ',', '.') . "</td>";
                    echo "<td>0</td>";
                    echo "<td>" . number_format($child['realisasi_lalu'], 0, ',', '.') . "</td>";
                    echo "<td>" . number_format($child['realisasi_ini'], 0, ',', '.') . "</td>";
                    echo "<td>" . number_format($child['realisasi_sd'], 0, ',', '.') . "</td>";
                    echo "<td>" . number_format($child['persentase'], 2, ',', '.') . "%</td>";
                    echo "<td>" . number_format($child['sisa_anggaran'], 0, ',', '.') . "</td>";
                    echo "</tr>";
                }
            }
        }
    }
}

?>

<style>
/* Styling tambahan untuk halaman rekap */
.main-content { padding: 30px; background: #f7f9fc; }
.card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); }
.rekap-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.rekap-table th, .rekap-table td { padding: 10px; border: 1px solid #dee2e6; text-align: right; }
.rekap-table thead th { background: #007bff; color: #fff; font-weight: bold; text-transform: uppercase; }
.rekap-table tbody th, .rekap-table tbody td { text-align: left; }
.rekap-table .total-row td, .rekap-table .total-row th { background: #e2f2ff; font-weight: bold; }
.hierarchy-row { font-weight: bold; }
.row-program { padding-left: 10px; background-color: #f0f4f7; }
.row-output { padding-left: 20px; background-color: #f8f9fa; }
.row-komponen { padding-left: 30px; }
.row-akun { padding-left: 40px; }
.row-item { padding-left: 50px; }
@media print {
  /* Hilangkan tombol, sidebar, header yang tidak perlu */
  .btn, .sidebar, .header-container {
    display: none !important;
  }

  /* Pastikan tabel full width */
  .rekap-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .rekap-table th, .rekap-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  /* Warna background tidak selalu muncul di print → pakai garis & bold */
  .rekap-table thead th {
    background: #fff !important;
    font-weight: bold;
    border: 2px solid #000;
  }

  /* Agar hierarki lebih jelas */
  .row-program td { font-weight: bold; }
  .row-output td { padding-left: 20px; }
  .row-komponen td { padding-left: 40px; }
  .row-akun td { padding-left: 60px; }
  .row-item td { padding-left: 80px; }
}

</style>

<main class="main-content">
    <div class="container">
        <h2 class="section-title">Rekap Anggaran</h2>
        <div class="card">
            <form action="" method="GET" class="form-inline mb-4">
                <div class="form-group mr-3">
                    <label for="tahun" class="mr-2">Tahun:</label>
                    <input type="number" id="tahun" name="tahun" class="form-control" value="<?= htmlspecialchars($tahun_filter) ?>" min="2000" max="2100">
                </div>
                <div class="form-group mr-3">
                    <label for="bulan" class="mr-2">Bulan:</label>
                    <select id="bulan" name="bulan" class="form-control">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $bulan_filter ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </form>
<div class="d-flex mb-3">
    <a href="cetak_anggaran.php" target="_blank" class="btn btn-success ms-auto">
        <i class="fas fa-print"></i> Cetak Anggaran
    </a>
</div>


            <div class="table-responsive">
                <table class="rekap-table">
                    <thead>
                        <tr style="background-color: #007bff; color: white;">
                            <th rowspan="2" style="text-align: left;">Uraian</th>
                            <th rowspan="2">Pagu Revisi</th>
                            <th rowspan="2">Lock Pagu</th>
                            <th colspan="4">Realisasi TA <?= htmlspecialchars($tahun_filter) ?></th>
                            <th rowspan="2">SISA ANGGARAN</th>
                        </tr>
                        <tr style="background-color: #007bff; color: white;">
                            <th>Periode Lalu</th>
                            <th>Periode Ini</th>
                            <th>s.d. Periode</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="total-row" style="background-color: #5bc0de;">
                            <td style="text-align: left;">JUMLAH SELURUHNYA</td>
                            <td><?= number_format($grand_total_pagu, 0, ',', '.') ?></td>
                            <td>0</td>
                            <td><?= number_format($grand_total_realisasi_lalu, 0, ',', '.') ?></td>
                            <td><?= number_format($grand_total_realisasi_ini, 0, ',', '.') ?></td>
                            <td><?= number_format($grand_total_realisasi_sd, 0, ',', '.') ?></td>
                            <td><?= $grand_total_pagu > 0 ? number_format(($grand_total_realisasi_sd / $grand_total_pagu) * 100, 2, ',', '.') . '%' : '0.00%' ?></td>
                            <td><?= number_format($grand_total_pagu - $grand_total_realisasi_sd, 0, ',', '.') ?></td>
                        </tr>
                        <?php printHierarchy($rekap_data); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>