<?php
session_start();
include "../includes/koneksi.php";
include "../includes/header.php";
include "../includes/sidebar.php";

// 1. Ambil daftar tim untuk dropdown
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim && $result_tim->num_rows > 0) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}

// 2. Ambil filter dari GET
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : '';
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$filter_tim   = isset($_GET['tim_id']) ? $_GET['tim_id'] : '';

// ===========================================================
// QUERY UTAMA (REVISI: MENAMBAHKAN BULAN PEMBAYARAN AGAR SPESIFIK)
// ===========================================================
$sql = "
SELECT
    t.id AS tim_id,
    t.nama_tim,
    p.nama AS ketua_tim,
    
    (
        SELECT mk.nama
        FROM master_kegiatan mk
        WHERE mk.kode = ms.kegiatan_id 
          AND mk.tahun = hm.tahun_pembayaran
        LIMIT 1
    ) AS nama_kegiatan,
    ms.kegiatan_id AS kode_kegiatan,

    (
        SELECT mi.nama_item
        FROM master_item mi
        WHERE mi.kode_unik LIKE CONCAT(hm.item_kode_unik, '%')
          AND mi.tahun = hm.tahun_pembayaran
        ORDER BY LENGTH(mi.kode_unik) DESC 
        LIMIT 1
    ) AS nama_item,
    hm.item_kode_unik,
    hm.bulan_pembayaran, -- PENTING: Ambil data bulan aktual dari database
    hm.tahun_pembayaran, -- PENTING: Ambil data tahun aktual dari database

    SUM(hm.total_honor) AS total_honor

FROM honor_mitra hm
LEFT JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
LEFT JOIN tim t ON ms.tim_id = t.id
LEFT JOIN pegawai p ON t.ketua_tim_id = p.id

WHERE 1=1
";

$params = [];
$types = '';

// Filter Bulan
if (!empty($filter_bulan)) {
    $sql .= " AND (hm.bulan_pembayaran = ? OR hm.bulan_pembayaran = ?)";
    $bulan_str = str_pad($filter_bulan, 2, '0', STR_PAD_LEFT); 
    $bulan_int = (int)$filter_bulan; 
    $params[] = $bulan_str;
    $params[] = $bulan_int;
    $types .= 'si'; 
}

// Filter Tahun
if (!empty($filter_tahun)) {
    $sql .= " AND hm.tahun_pembayaran = ?";
    $params[] = $filter_tahun;
    $types .= 'i';
}

// Filter Tim
if (!empty($filter_tim)) {
    $sql .= " AND t.id = ?";
    $params[] = $filter_tim;
    $types .= 'i';
}

// GROUP BY (REVISI: Grouping dipecah per bulan agar tombol Edit/Detail akurat)
$sql .= "
GROUP BY 
    t.id, t.nama_tim, p.nama, 
    ms.kegiatan_id, 
    hm.item_kode_unik,
    hm.bulan_pembayaran,
    hm.tahun_pembayaran
ORDER BY t.nama_tim ASC, nama_kegiatan ASC, hm.bulan_pembayaran ASC
";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
.table-wrapper { overflow-x: auto; margin-top: 1rem; }
.table { width: 100%; border-collapse: collapse; min-width: 700px; }
.table th, .table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
.table th { background-color: #f3f3f3; }
.table tr:nth-child(even) { background-color: #fafafa; }
.table tr:hover { background-color: #f1f1f1; }
.form-select, .form-input, .btn-primary, .btn-success { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; }
.btn-primary { background-color: #007bff; color: #fff; border: none; cursor: pointer; }
.btn-primary:hover { background-color: #0056b3; }
.btn-detail { background-color: #17a2b8; color: #fff; padding: 5px 10px; border-radius: 4px; text-decoration: none; }
.btn-detail:hover { background-color: #117a8b; }
.btn-edit { background-color: #ffc107; color: #212529; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-left: 5px; }
.btn-edit:hover { background-color: #e0a800; color: #212529; }
.btn-secondary { background-color: #6c757d; color: #fff; text-decoration: none; padding: 6px 10px; border-radius: 4px;}
.btn-secondary:hover { background-color: #545b62; }
</style>

<div class="main-content">
    <div class="card">
        <a href="kegiatan.php" class="btn-secondary">← Kembali</a>
        <h3>Kegiatan Tim</h3>
        
        <form action="rekap_kegiatan_tim.php" method="GET" class="mb-4">
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                
                <div>
                    <label>Bulan</label>
                    <select name="bulan" class="form-select">
                        <option value="">-- Semua Bulan --</option>
                        <?php
                        for ($m=1; $m<=12; $m++) {
                            $val = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $nama = date('F', mktime(0,0,0,$m,1));
                            $isSelected = ($filter_bulan == $val || $filter_bulan == $m) ? 'selected' : '';
                            echo "<option value='$val' $isSelected>$nama</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label>Tahun</label>
                    <input type="number" name="tahun" value="<?= htmlspecialchars($filter_tahun) ?>" class="form-input">
                </div>
                
                <div>
                    <label>Tim</label>
                    <select name="tim_id" class="form-select">
                        <option value="">-- Semua Tim --</option>
                        <?php foreach ($tim_list as $tim): ?>
                            <option value="<?= $tim['id'] ?>" <?= ($filter_tim == $tim['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tim['nama_tim']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="align-self:end;">
                    <button type="submit" class="btn-primary">Filter</button>
                </div>
            </div>
        </form>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Nama Tim</th>
                        <th>Ketua Tim</th>
                        <th>Nama Kegiatan</th>
                        <th>Nama Item</th>
                        <th>Jumlah Honor Dibayarkan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><span style="background:#e9ecef; padding:4px 8px; border-radius:4px; font-weight:bold; font-size:0.85rem;"><?= date('M', mktime(0,0,0,(int)$row['bulan_pembayaran'],1)) ?></span></td>
                                <td><?= htmlspecialchars($row['nama_tim'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['ketua_tim'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_kegiatan'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_item'] ?? '-') ?></td>
                                <td>Rp <?= number_format($row['total_honor'], 0, ',', '.') ?></td>
                                <td style="white-space: nowrap;">
    <div style="display: flex; gap: 8px; align-items: center;">
        <a href="detail_rekap_kegiatan_tim.php?tim_id=<?= urlencode($row['tim_id']) ?>&kode_kegiatan=<?= urlencode($row['kode_kegiatan']) ?>&item_kode=<?= urlencode($row['item_kode_unik']) ?>&bulan=<?= urlencode($row['bulan_pembayaran']) ?>&tahun=<?= urlencode($row['tahun_pembayaran']) ?>" class="btn-detail">Detail</a>
        
        <a href="edit_kegiatan.php?tim_id=<?= urlencode($row['tim_id']) ?>&kode_kegiatan=<?= urlencode($row['kode_kegiatan']) ?>&item_kode=<?= urlencode($row['item_kode_unik']) ?>&bulan=<?= urlencode($row['bulan_pembayaran']) ?>&tahun=<?= urlencode($row['tahun_pembayaran']) ?>" class="btn-edit" style="margin-left: 0;">Edit</a>
    </div>
</td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">
                                Tidak ada data ditemukan. <br>
                                <small>Coba ubah filter bulan atau tahun.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$stmt->close();
$koneksi->close();
include "../includes/footer.php";
?>