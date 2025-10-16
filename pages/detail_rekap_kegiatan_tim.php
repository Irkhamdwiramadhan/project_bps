<?php
session_start();
include "../includes/koneksi.php";
include "../includes/header.php";
include "../includes/sidebar.php";

$kode_kegiatan = $_GET['kode_kegiatan'] ?? '';

if (!$kode_kegiatan) {
    echo "<div class='main-content'><h3>Kode kegiatan tidak ditemukan!</h3></div>";
    include "../includes/footer.php";
    exit;
}

// Ambil informasi kegiatan dan tim
$sql_info = "
SELECT 
    mk.nama AS nama_kegiatan,
    t.nama_tim,
    p.nama AS ketua_tim
FROM master_kegiatan mk
LEFT JOIN mitra_surveys ms ON mk.kode = ms.kegiatan_id
LEFT JOIN tim t ON ms.tim_id = t.id
LEFT JOIN pegawai p ON t.ketua_tim_id = p.id
WHERE mk.kode = ?
LIMIT 1
";
$stmt_info = $koneksi->prepare($sql_info);
$stmt_info->bind_param('s', $kode_kegiatan);
$stmt_info->execute();
$info = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

// Ambil daftar mitra dan total honor untuk kegiatan ini
$sql_detail = "
SELECT 
    m.nama_lengkap AS nama_mitra,
    SUM(hm.total_honor) AS total_honor
FROM honor_mitra hm
LEFT JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
LEFT JOIN mitra m ON hm.mitra_id = m.id
WHERE ms.kegiatan_id = ?
GROUP BY m.nama_lengkap
ORDER BY m.nama_lengkap ASC
";
$stmt = $koneksi->prepare($sql_detail);
$stmt->bind_param('s', $kode_kegiatan);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
.table-wrapper {
    overflow-x: auto;
    margin-top: 1rem;
}
.table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}
.table th, .table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}
.table th {
    background-color: #f3f3f3;
}
.table tr:nth-child(even) {
    background-color: #fafafa;
}
.table tr:hover {
    background-color: #f1f1f1;
}
.btn-primary, .btn-secondary {
    padding: 6px 10px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    color: #fff;
    text-decoration: none;
}
.btn-primary {
    background-color: #007bff;
}
.btn-primary:hover {
    background-color: #0056b3;
}
.btn-secondary {
    background-color: #6c757d;
}
.btn-secondary:hover {
    background-color: #545b62;
}
</style>

<div class="main-content">
    <div class="card">
        <h3>Detail Kegiatan Tim</h3>

        <?php if ($info): ?>
            <p><strong>Nama Kegiatan:</strong> <?= htmlspecialchars($info['nama_kegiatan']) ?></p>
            <p><strong>Nama Tim:</strong> <?= htmlspecialchars($info['nama_tim'] ?? '-') ?></p>
            <p><strong>Ketua Tim:</strong> <?= htmlspecialchars($info['ketua_tim'] ?? '-') ?></p>
        <?php else: ?>
            <p style="color:red;">Data kegiatan tidak ditemukan.</p>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Mitra</th>
                        <th>Jumlah Honor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_all = 0;
                    if ($result && $result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $total_all += $row['total_honor'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['nama_mitra']) ?></td>
                            <td>Rp <?= number_format($row['total_honor'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endwhile; ?>
                        <tr style="font-weight:bold; background-color:#f9f9f9;">
                            <td>Total</td>
                            <td>Rp <?= number_format($total_all, 0, ',', '.') ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" style="text-align:center;">Tidak ada data mitra untuk kegiatan ini</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <br>
        <a href="rekap_kegiatan_tim.php" class="btn-secondary">← Kembali</a>
        
    </div>
</div>

<?php
$stmt->close();
$koneksi->close();
include "../includes/footer.php";
?>
