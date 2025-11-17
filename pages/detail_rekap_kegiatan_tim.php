<?php
session_start();
include "../includes/koneksi.php";
include "../includes/header.php";
include "../includes/sidebar.php";

$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'ketua_tim'];

// Periksa apakah pengguna memiliki salah satu peran yang diizinkan untuk melihat aksi
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break; // Keluar dari loop setelah menemukan kecocokan
    }
}

// 1. Ambil Parameter dari URL
$tim_id = $_GET['tim_id'] ?? '';
$kode_kegiatan = $_GET['kode_kegiatan'] ?? '';
$item_kode = $_GET['item_kode'] ?? '';
$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? '';

// Validasi
if (empty($tim_id) || empty($kode_kegiatan) || empty($item_kode)) {
    echo "<div class='main-content'><div class='card'><h3 class='text-danger'>Error: Parameter tidak lengkap.</h3><a href='rekap_kegiatan_tim.php' class='btn-secondary'>Kembali</a></div></div>";
    include "../includes/footer.php";
    exit;
}

// 2. Ambil Informasi Header (Untuk Judul Halaman)
$sql_info = "
    SELECT 
        t.nama_tim,
        (SELECT nama FROM master_kegiatan WHERE kode = ? AND tahun = ? LIMIT 1) AS nama_kegiatan,
        (SELECT nama_item FROM master_item WHERE kode_unik LIKE CONCAT(?, '%') AND tahun = ? ORDER BY LENGTH(kode_unik) DESC LIMIT 1) AS nama_item,
        (SELECT satuan FROM master_item WHERE kode_unik LIKE CONCAT(?, '%') AND tahun = ? ORDER BY LENGTH(kode_unik) DESC LIMIT 1) AS satuan
    FROM tim t
    WHERE t.id = ?
";

$stmt_info = $koneksi->prepare($sql_info);
$stmt_info->bind_param(
    "sisisii",
    $kode_kegiatan,
    $tahun,
    $item_kode,
    $tahun,
    $item_kode,
    $tahun,
    $tim_id
);
$stmt_info->execute();
$info = $stmt_info->get_result()->fetch_assoc();

$nama_tim = $info['nama_tim'] ?? 'Tim Tidak Ditemukan';
$nama_kegiatan = $info['nama_kegiatan'] ?? $kode_kegiatan;
$nama_item = $info['nama_item'] ?? $item_kode;
$satuan = $info['satuan'] ?? '-';


// 3. Ambil Daftar Mitra (Rincian)
$sql_detail = "
    SELECT 
        hm.id AS honor_id, -- Penting untuk tombol hapus
        m.nama_lengkap,
        m.norek,
        m.bank,
        hm.jumlah_satuan,
        hm.honor_per_satuan,
        hm.total_honor,
        hm.tanggal_input,
        hm.bulan_pembayaran
    FROM honor_mitra hm
    JOIN mitra m ON hm.mitra_id = m.id
    JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    WHERE ms.tim_id = ? 
      AND ms.kegiatan_id = ? 
      AND hm.tahun_pembayaran = ?
      
      -- LOGIKA MATCHING ITEM (2 ARAH)
      AND (
          hm.item_kode_unik = ? 
          OR hm.item_kode_unik LIKE CONCAT(?, '%') 
          OR ? LIKE CONCAT(hm.item_kode_unik, '%')
      )
";

$types = "isisss";
$params = [$tim_id, $kode_kegiatan, $tahun, $item_kode, $item_kode, $item_kode];

if (!empty($bulan)) {
    $sql_detail .= " AND hm.bulan_pembayaran = ? ";
    $types .= "s";
    $params[] = $bulan;
}

$sql_detail .= " ORDER BY hm.tanggal_input DESC, m.nama_lengkap ASC";

$stmt = $koneksi->prepare($sql_detail);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$nama_bulan_str = !empty($bulan) ? date('F', mktime(0, 0, 0, (int)$bulan, 1)) : "Semua Bulan";
?>

<style>
    .table-detail {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    .table-detail th,
    .table-detail td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
    }

    .table-detail th {
        background-color: #f8f9fa;
        font-weight: bold;
        color: #495057;
    }

    .info-box {
        background: #eef2ff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-left: 5px solid #4f46e5;
    }

    .info-row {
        display: flex;
        margin-bottom: 0.5rem;
    }

    .info-label {
        width: 150px;
        font-weight: 600;
        color: #555;
    }

    .info-val {
        font-weight: 500;
        color: #000;
    }

    .btn-back {
        display: inline-block;
        padding: 8px 15px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        margin-bottom: 1rem;
    }

    .btn-back:hover {
        background: #5a6268;
    }

    .text-right {
        text-align: right !important;
    }

    .text-center {
        text-align: center !important;
    }

    /* Style Tombol Hapus */
    .btn-delete {
        display: inline-block;
        padding: 5px 10px;
        background: #dc3545;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: bold;
    }

    .btn-delete:hover {
        background: #c82333;
    }
</style>

<div class="main-content">
    <div class="card">

        <a href="rekap_kegiatan_tim.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&tim_id=<?= $tim_id ?>" class="btn-back">← Kembali ke Rekap</a>

        <h3>Rincian Honor Mitra</h3>

        <div class="info-box">
            <div class="info-row">
                <div class="info-label">Tim Pelaksana</div>
                <div class="info-val">: <?= htmlspecialchars($nama_tim) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Kegiatan</div>
                <div class="info-val">: <?= htmlspecialchars($nama_kegiatan) ?> (<?= htmlspecialchars($kode_kegiatan) ?>)</div>
            </div>
            <div class="info-row">
                <div class="info-label">Uraian Item</div>
                <div class="info-val">: <?= htmlspecialchars($nama_item) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Periode</div>
                <div class="info-val">: <?= $nama_bulan_str ?> <?= $tahun ?></div>
            </div>
        </div>

        <table class="table-detail">
            <thead>
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th>Nama Mitra</th>
                    <th class="text-right">Volume</th>
                    <th class="text-right">Honor Satuan</th>
                    <th class="text-right">Total Diterima</th>

                    <?php

                    if ($has_access_for_action):
                    ?>
                        <th width="10%" class="text-center">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $grand_total = 0;
                $total_volume = 0;

                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $grand_total += $row['total_honor'];
                        $total_volume += $row['jumlah_satuan'];
                ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong></td>
                            <td class="text-right"><?= number_format($row['jumlah_satuan'], 0, ',', '.') ?></td>
                            <td class="text-right">Rp <?= number_format($row['honor_per_satuan'], 0, ',', '.') ?></td>
                            <td class="text-right" style="font-weight:bold;">Rp <?= number_format($row['total_honor'], 0, ',', '.') ?></td>

                            <?php

                            if ($has_access_for_action):
                            ?>
                                <td class="text-center">
                                    <a href="../proses/delete_kegiatan.php?id=<?= htmlspecialchars($row['honor_id']) ?>"
                                        class="btn-delete"
                                        onclick="return confirm('Yakin ingin menghapus data honor ini?');">
                                        Hapus
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="<?= ($user_role == 'super_admin') ? 6 : 5 ?>" class="text-center" style="padding: 20px; color: #777;">
                            Data tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #fff3cd; font-weight: bold;">
                    <td colspan="2" class="text-right">TOTAL KESELURUHAN</td>
                    <td class="text-right"><?= number_format($total_volume, 0, ',', '.') ?></td>
                    <td></td>
                    <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>

                    <?php

                    if ($has_access_for_action):
                    ?><td></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>

    </div>
</div>

<?php
$stmt_info->close();
$stmt->close();
$koneksi->close();
include "../includes/footer.php";
?>