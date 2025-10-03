<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// ... (logika PHP Anda tetap sama, tidak perlu diubah) ...
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];
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

$sql = "SELECT
            mp.nama AS program_nama, mk.nama AS kegiatan_nama, mo.nama AS output_nama,
            mso.nama AS sub_output_nama, mkom.nama AS komponen_nama, msk.nama AS sub_komponen_nama,
            ma.id AS akun_id, ma.nama AS akun_nama, mi.id AS id_item, mi.nama_item AS item_nama,
            mi.satuan, mi.volume, mi.harga, mi.pagu, mi.tahun
        FROM master_item mi
        LEFT JOIN master_akun ma ON mi.akun_id = ma.id
        LEFT JOIN master_sub_komponen msk ON ma.sub_komponen_id = msk.id
        LEFT JOIN master_komponen mkom ON msk.komponen_id = mkom.id
        LEFT JOIN master_sub_output mso ON mkom.sub_output_id = mso.id
        LEFT JOIN master_output mo ON mso.output_id = mo.id
        LEFT JOIN master_kegiatan mk ON mo.kegiatan_id = mk.id
        LEFT JOIN master_program mp ON mk.program_id = mp.id
        WHERE mi.tahun = ?
        ORDER BY mp.nama, mk.nama, mo.nama, mso.nama, mkom.nama, msk.nama, ma.nama, mi.nama_item ASC";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $tahun_filter);
$stmt->execute();
$result = $stmt->get_result();
$data_master = [];
$total_pagu = 0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['pagu'] = (float) $row['pagu'];
        $data_master[] = $row;
        $total_pagu += $row['pagu'];
    }
}
?>

<style>
:root {
    --primary-blue: #0A2E5D;
    --light-blue-bg: #E6EEF7;
    --border-color: #dee2e6;
    --text-dark: #2c3e50;
    --text-light: #7f8c8d;
    --warning-bg: #fff8e1;
    --warning-text: #c09853;
}
.main-content { padding: 30px; background:#f7f9fc; }
.header-container { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap: wrap; gap: 15px; }
.section-title { font-size:1.5rem; font-weight:700; margin:0; color: var(--primary-blue); }
.card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
.card-header-content { padding: 20px; }
.total-box { padding: 15px 20px; border-top: 1px solid var(--border-color); background-color: #f8f9fa; }
.total-box p { margin: 0; font-size: 1.1rem; color: var(--primary-blue); font-weight: 600; }
.action-box { background-color: var(--light-blue-bg); border-left: 5px solid var(--primary-blue); padding: 15px; margin-bottom: 20px; border-radius: 8px; }
.year-buttons { display: flex; gap: 8px; align-items: center; }
.year-buttons label { margin: 0; font-weight: 500; }

.table-responsive { max-height: 65vh; overflow: auto; }
.table-responsive::-webkit-scrollbar { width: 10px; height: 10px; }
.table-responsive::-webkit-scrollbar-thumb { background-color: #d1d5db; border-radius: 5px; border: 2px solid #fff; }
.table-responsive::-webkit-scrollbar-thumb:hover { background-color: #a8b0bc; }

.master-data { border-collapse:collapse; font-size:0.8rem; width: 100%; }
.master-data th, .master-data td { padding:12px 15px; border-bottom:1px solid var(--border-color); vertical-align: middle; }
.master-data tr:last-child td { border-bottom: none; }
.master-data th { 
    background:#f7f9fc; font-weight:600; text-align: center; color: var(--text-dark);
    position: sticky; top: 0; z-index: 2;
    border-bottom: 2px solid var(--border-color);
}
.master-data td.col-right { text-align:right; }

.uraian-col {
    position: sticky;
    left: 0;
    background-color: #fff;
    z-index: 1;
}
.master-data thead .uraian-col {
    z-index: 3;
    background-color: #f7f9fc;
}
.master-data tbody .uraian-col {
    box-shadow: 5px 0 5px -5px rgba(0,0,0,0.1);
}


.hierarchy-row td { font-weight:bold; background-color: #f8f9fa; }
.level-program { color: var(--primary-blue); font-size: 1.1em; }
.level-kegiatan { color: #154360; padding-left:25px !important; }
.level-output { color: #1F618D; padding-left:50px !important; }
.level-sub-output { color: #2980B9; padding-left:75px !important; }
.level-komponen { color: #5499C7; padding-left:100px !important; }
.level-sub-komponen { color: var(--text-light); padding-left:125px !important; }
.level-akun { color: #27AE60; padding-left:150px !important; font-style: italic;}
.level-item { font-weight:normal; padding-left:175px !important; }
.level-placeholder { color: var(--warning-text); background-color: var(--warning-bg); font-style: italic; font-weight: bold; }
</style>

<main class="main-content">
  <div class="container-fluid">
    <div class="header-container">
      <h2 class="section-title">Manajemen Anggaran Tahunan</h2>
    </div>

    <div class="card">
        <div class="card-header-content">
            <?php if ($has_access_for_action): ?>
            <div class="action-box">
                <strong>Tindakan Lanjutan</strong>
                <p class="mb-2">Gunakan fitur ini untuk menghapus seluruh data anggaran pada tahun tertentu.</p>
                <form action="../proses/proses_hapus_anggaran.php" method="POST" onsubmit="return confirm('PERINGATAN: Anda akan menghapus SELURUH data anggaran untuk tahun yang dipilih. Yakin ingin melanjutkan?');" class="form-inline">
                    <label for="tahun_hapus" class="mr-2">Pilih Tahun:</label>
                    <select class="form-control mr-2" id="tahun_hapus" name="tahun" required>
                        <?php foreach ($daftar_tahun as $th): ?>
                            <option value="<?= $th ?>"><?= $th ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus Anggaran</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="year-buttons">
              <label>Lihat Tahun:</label>
              <?php foreach ($daftar_tahun as $th): ?>
                <a href="?tahun=<?= $th ?>" class="btn btn-sm <?= $th == $tahun_filter ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $th ?></a>
              <?php endforeach; ?>
            </div>
        </div>
      
        <div class="table-responsive">
            <table class="master-data">
                <thead>
                    <tr>
                        <th class="uraian-col" style="min-width: 600px; text-align: left;">Uraian Anggaran</th>
                        <th style="min-width: 100px;">Satuan</th>
                        <th style="min-width: 120px;">Volume</th>
                        <th style="min-width: 150px;">Harga</th>
                        <th style="min-width: 150px;">Pagu</th>
                        <th style="min-width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data_master)):
                        $printed_headers = [];
                        foreach ($data_master as $row):
                            $levels = [
                                'program' => 'level-program', 'kegiatan' => 'level-kegiatan', 
                                'output' => 'level-output', 'sub_output' => 'level-sub-output', 
                                'komponen' => 'level-komponen', 'sub_komponen' => 'level-sub-komponen',
                                'akun' => 'level-akun'
                            ];
                            foreach ($levels as $level => $class) {
                                $display_name = !empty($row[$level . '_nama']) ? $row[$level . '_nama'] : '[' . ucfirst($level) . ' Tidak Ditemukan]';
                                $is_defined = !empty($row[$level . '_nama']);
                                if (!isset($printed_headers[$level]) || $printed_headers[$level] !== $display_name) {
                                    $css_class = $is_defined ? $class : 'level-placeholder ' . $class;
                                    $colspan = ($level === 'akun') ? 5 : 6;
                                    
                                    echo '<tr class="hierarchy-row">';
                                    echo "<td colspan='{$colspan}' class='uraian-col {$css_class}'>" . htmlspecialchars($display_name) . "</td>";
                                    if ($level === 'akun') {
                                        echo '<td class="text-center">';
                                        if ($is_defined && $has_access_for_action) {
                                            echo '<a href="tambah_item.php?id_akun='.urlencode($row['akun_id']).'&tahun='.$tahun_filter.'" class="btn btn-sm btn-success"><i class="fas fa-plus"></i></a>';
                                        }
                                        echo '</td>';
                                    }
                                    echo '</tr>';

                                    $printed_headers[$level] = $display_name;
                                    $child_levels_to_reset = array_slice(array_keys($levels), array_search($level, array_keys($levels)) + 1);
                                    foreach ($child_levels_to_reset as $child_level) {
                                        unset($printed_headers[$child_level]);
                                    }
                                }
                            }
                    ?>
                            <tr class="item-row">
                                <td class="level-item uraian-col"><?= htmlspecialchars($row['item_nama']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['satuan']) ?></td>
                                <td class="col-right"><?= number_format((float)$row['volume'], 0, ',', '.') ?></td>
                                <td class="col-right">Rp <?= number_format((float)$row['harga'], 0, ',', '.') ?></td>
                                <td class="col-right">Rp <?= number_format((float)$row['pagu'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($has_access_for_action): ?>
                                        <a href="edit_item.php?id=<?= urlencode($row['id_item']) ?>" class="btn btn-sm btn-warning mr-1"><i class="fas fa-edit"></i></a>
                                        <a href="../proses/proses_hapus_item.php?id_item=<?= urlencode($row['id_item']) ?>&tahun=<?= $tahun_filter ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus item ini?');"><i class="fas fa-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                          <?php endforeach;
                        else: ?>
                        <tr><td colspan="6" class="text-center text-muted p-5">Tidak ada data master ditemukan untuk tahun <?= $tahun_filter ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="total-box">
            <p>Total Pagu Anggaran (Tahun <?= $tahun_filter ?>): Rp <?= number_format($total_pagu, 0, ',', '.') ?></p>
        </div>
    </div>
  </div>
</main>

<?php include '../includes/footer.php'; ?>