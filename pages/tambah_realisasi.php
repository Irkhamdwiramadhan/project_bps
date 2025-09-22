<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil user ID dan role dari session
$id_user = $_SESSION['user_id'] ?? null;
$role_user = $_SESSION['role'] ?? null;
$tahun_filter = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date("Y");
$akun_filter = isset($_GET['akun_id']) ? (int)$_GET['akun_id'] : null;

// Ambil daftar tahun unik dari master_item dan rpd
$tahun_query = "SELECT DISTINCT tahun FROM master_item UNION SELECT DISTINCT tahun FROM rpd ORDER BY tahun DESC";
$stmt_tahun = $koneksi->prepare($tahun_query);
$stmt_tahun->execute();
$tahun_result = $stmt_tahun->get_result();
$daftar_tahun = [];
while ($row = $tahun_result->fetch_assoc()) {
    $daftar_tahun[] = $row['tahun'];
}
$stmt_tahun->close();

// Ambil daftar akun yang memiliki RPD untuk tahun yang dipilih
$akun_query = "SELECT DISTINCT ma.id, ma.nama FROM master_akun ma
               LEFT JOIN master_item mi ON ma.id = mi.id_akun
               LEFT JOIN rpd ON mi.id = rpd.id_item AND rpd.tahun = ?
               WHERE rpd.jumlah IS NOT NULL AND rpd.jumlah > 0
               ORDER BY ma.nama ASC";
$stmt_akun = $koneksi->prepare($akun_query);
$stmt_akun->bind_param("i", $tahun_filter);
$stmt_akun->execute();
$akun_result = $stmt_akun->get_result();
$daftar_akun = $akun_result->fetch_all(MYSQLI_ASSOC);
$stmt_akun->close();

// Ambil data RPD dan Realisasi jika akun sudah dipilih
$data_rpd_realisasi = [];
if ($akun_filter !== null && $tahun_filter !== null) {
    // Query untuk mengambil data master item yang memiliki RPD untuk akun dan tahun yang dipilih
    $sql_master = "SELECT
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
                   WHERE mi.id_akun = ? AND mi.tahun = ? AND mi.id IN (SELECT DISTINCT id_item FROM rpd WHERE tahun = ?)
                   ORDER BY mk.nama, ma.nama, mi.nama_item ASC";

    $stmt_master = $koneksi->prepare($sql_master);
    $stmt_master->bind_param("iii", $akun_filter, $tahun_filter, $tahun_filter);
    $stmt_master->execute();
    $data_master = $stmt_master->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_master->close();

    // Loop untuk mengambil data RPD dan Realisasi per item per bulan
    foreach ($data_master as $row) {
        $sql_detail = "SELECT
                           rpd.bulan,
                           rpd.jumlah AS jumlah_rpd,
                           COALESCE(realisasi.jumlah, 0) AS jumlah_realisasi
                       FROM rpd
                       LEFT JOIN realisasi ON rpd.id = realisasi.id_rpd
                       WHERE rpd.id_item = ? AND rpd.tahun = ?
                       ORDER BY rpd.bulan ASC";
        $stmt_detail = $koneksi->prepare($sql_detail);
        $stmt_detail->bind_param("ii", $row['id_item'], $tahun_filter);
        $stmt_detail->execute();
        $detail_result = $stmt_detail->get_result();
        $data_bulan = [];
        while ($detail_row = $detail_result->fetch_assoc()) {
            $data_bulan[$detail_row['bulan']] = [
                'rpd' => $detail_row['jumlah_rpd'],
                'realisasi' => $detail_row['jumlah_realisasi']
            ];
        }
        $row['data_bulan'] = $data_bulan;
        $data_rpd_realisasi[] = $row;
        $stmt_detail->close();
    }
}

$current_month = date('n');
$current_year = date('Y');
?>

<style>
/* Perbaikan CSS untuk membuat header tabel sticky */
.main-content { padding: 30px; background:#f7f9fc; }
.header-container { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap: wrap; gap: 15px; }
.section-title { font-size:1.5rem; font-weight:700; margin:0; }
.card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.table-responsive {
    overflow: auto;
    max-height: 70vh;
}
.data-table {
    width:100%;
    border-collapse:collapse;
    font-size:0.9rem;
}
.data-table th, .data-table td {
    padding:10px;
    border-bottom:1px solid #dee2e6;
    text-align:center;
}
.data-table thead tr th {
    background:#f7f9fc;
    font-weight:600;
    position: sticky;
    z-index: 10;
}
.data-table thead tr:first-child th {
    top: 0;
}
.data-table thead tr:nth-child(2) th {
    top: 40px;
}
.data-table thead tr:nth-child(3) th {
    top: 80px;
}
.data-table td.col-left { text-align:left; }
.data-table .total-cell, .data-table .pagu-cell, .data-table .sisa-cell { font-weight:bold; }
.text-muted { color: #6c7d7d; }
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
.info-box { background-color: #e9f5ff; border-left: 5px solid #007bff; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
.add-rpd-button-container { text-align: right; margin-bottom: 20px; }
/* CSS tambahan untuk hierarki yang lebih jelas */
.hierarchy-row {
    background-color: #f0f0f0;
    font-weight: bold;
}
.hierarchy-row-unit {
    background-color: #e9ecef;
    font-size: 1.1rem;
    padding-left: 10px !important;
}
.hierarchy-row-program {
    background-color: #f8f9fa;
    font-size: 1rem;
    padding-left: 25px !important;
}
.hierarchy-row-output {
    background-color: #f0f4f7;
    font-style: italic;
    padding-left: 40px !important;
}
.hierarchy-row-komponen {
    background-color: #fcfcfc;
    font-weight: normal;
    padding-left: 55px !important;
}
.hierarchy-row-akun {
    font-style: italic;
    padding-left: 70px !important;
}
.data-item-row {
    padding-left: 85px !important;
}
.realisasi-input {
    width: 80px;
    text-align: right;
}
.btn-back {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
}
.btn-back:hover {
    background-color: #5a6268;
    border-color: #545b62;
}
</style>

<main class="main-content">
    <div class="container">
        <a href="javascript:history.back()" class="btn btn-back">Kembali</a>
        <h2 class="section-title">Realisasi Anggaran</h2>
        <div class="card">
            <form action="" method="GET" class="form-realisasi">
                <div class="form-group">
                    <label for="tahun">Tahun:</label>
                    <select class="form-control" id="tahun" name="tahun">
                        <?php foreach ($daftar_tahun as $th): ?>
                            <option value="<?= $th ?>" <?= $th == $tahun_filter ? 'selected' : '' ?>><?= $th ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="akun">Pilih Akun:</label>
                    <select class="form-control" id="akun" name="akun_id">
                        <option value="">-- Pilih Akun --</option>
                        <?php foreach ($daftar_akun as $akun): ?>
                            <option value="<?= $akun['id'] ?>" <?= $akun['id'] == $akun_filter ? 'selected' : '' ?>><?= htmlspecialchars($akun['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Filter</button>
            </form>

            <?php if ($akun_filter !== null && !empty($data_rpd_realisasi)): ?>
                <form id="realisasiForm" action="../proses/proses_simpan_realisasi.php" method="POST">
                    <input type="hidden" name="tahun" value="<?= htmlspecialchars($tahun_filter) ?>">
                    <div class="table-responsive">
                        <table class="data-table mt-4">
                            <thead>
                                <tr>
                                    <th rowspan="2">Uraian Anggaran</th>
                                    <th colspan="24">Rencana & Realisasi Per Bulan</th>
                                    <th rowspan="2">Total RPD</th>
                                    <th rowspan="2">Total Realisasi</th>
                                    <th rowspan="2">Pagu Anggaran</th>
                                    <th rowspan="2">Sisa Anggaran</th>
                                </tr>
                                <tr>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <th colspan="2"><?= date('M', mktime(0, 0, 0, $i, 1)) ?></th>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <th></th>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <th>RPD</th>
                                        <th>Real</th>
                                    <?php endfor; ?>
                                    <th></th><th></th><th></th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $prev_komponen = null;
                                $prev_akun = null;
                                foreach ($data_rpd_realisasi as $row):
                                    if ($row['komponen_nama'] !== $prev_komponen): ?>
                                        <tr class="hierarchy-row"><td colspan="27" class="col-left text-muted" style="padding-left:10px; font-weight:normal; font-style:italic;">Komponen: <?= htmlspecialchars($row['komponen_nama']) ?></td></tr>
                                        <?php $prev_komponen = $row['komponen_nama'];
                                    endif;
                                    if ($row['akun_nama'] !== $prev_akun): ?>
                                        <tr class="hierarchy-row"><td colspan="27" class="col-left text-muted" style="padding-left:30px; font-weight:normal;">Akun: <?= htmlspecialchars($row['akun_nama']) ?></td></tr>
                                        <?php $prev_akun = $row['akun_nama'];
                                    endif;
                                ?>
                                <tr>
                                    <td class="col-left" style="padding-left:50px;"><?= htmlspecialchars($row['nama_item']) ?></td>
                                    <?php
                                    $total_rpd_item = 0;
                                    $total_realisasi_item = 0;
                                    for ($bulan = 1; $bulan <= 12; $bulan++):
                                        $jumlah_rpd = $row['data_bulan'][$bulan]['rpd'] ?? 0;
                                        $jumlah_realisasi = $row['data_bulan'][$bulan]['realisasi'] ?? 0;

                                        $total_rpd_item += (float)$jumlah_rpd;
                                        $total_realisasi_item += (float)$jumlah_realisasi;

                                        $is_disabled = ($tahun_filter < $current_year || ($tahun_filter == $current_year && $bulan < $current_month));
                                    ?>
                                        <td><?= number_format($jumlah_rpd, 0, ',', '.') ?></td>
                                        <td>
                                            <input type="text"
                                                   name="realisasi[<?= $row['id_item'] ?>][<?= $bulan ?>]"
                                                   value="<?= number_format($jumlah_realisasi, 0, ',', '.') ?>"
                                                   class="form-control form-control-sm text-right realisasi-input"
                                                   data-rpd-bulan="<?= htmlspecialchars($jumlah_rpd) ?>"
                                                   <?= $is_disabled ? 'disabled' : '' ?>>
                                        </td>
                                    <?php endfor; ?>
                                    <td class="total-cell" data-total-rpd-for="<?= $row['id_item'] ?>"><?= number_format($total_rpd_item, 0, ',', '.') ?></td>
                                    <td class="total-cell" data-total-realisasi-for="<?= $row['id_item'] ?>"><?= number_format($total_realisasi_item, 0, ',', '.') ?></td>
                                    <td class="pagu-cell"><?= number_format($row['pagu'], 0, ',', '.') ?></td>
                                    <td class="sisa-cell"><?= number_format($row['pagu'] - $total_realisasi_item, 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-right">
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Realisasi
                        </button>
                    </div>
                </form>
            <?php elseif ($akun_filter !== null && empty($data_rpd_realisasi)): ?>
                <div class="alert alert-info mt-3">Tidak ada data RPD yang ditemukan untuk akun dan tahun yang dipilih.</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
    function calculateTotalRealization(row) {
        let total = 0;
        row.find('.realisasi-input').each(function() {
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
        const total = calculateTotalRealization(row);
        const itemId = row.find('.realisasi-input').first().attr('name').match(/\[(.*?)\]/)[1];
        $(`[data-total-realisasi-for="${itemId}"]`).text(formatNumber(total));
    });

    // Event handler saat input berubah
    $('.realisasi-input').on('keyup change', function() {
        const row = $(this).closest('tr');
        const total = calculateTotalRealization(row);
        const pagu = parseFloat(row.find('.pagu-cell').text().replace(/\./g, ''));

        const itemId = $(this).attr('name').match(/\[(.*?)\]/)[1];
        const totalRealCell = $(`[data-total-realisasi-for="${itemId}"]`);
        const sisaCell = row.find('.sisa-cell');

        totalRealCell.text(formatNumber(total));
        sisaCell.text(formatNumber(pagu - total));

        // Cek jika total melebihi pagu
        if (total > pagu) {
            totalRealCell.css('color', 'red');
            sisaCell.css('color', 'red');
        } else {
            totalRealCell.css('color', '');
            sisaCell.css('color', '');
        }
    });

    // Validasi input saat input berubah
    $('.realisasi-input').on('blur', function() {
        const input = $(this);
        let value = parseFloat(input.val().replace(/\./g, '')) || 0;
        const max_rpd = parseFloat(input.data('rpd-bulan'));

        if (value > max_rpd) {
            alert('Jumlah realisasi tidak boleh melebihi RPD untuk bulan ini.');
            value = max_rpd;
        }

        if (value < 0) {
            alert('Jumlah realisasi tidak boleh negatif.');
            value = 0;
        }
        
        input.val(formatNumber(value));
    });
    
    // Tangani pengiriman form via AJAX
    $('#realisasiForm').on('submit', function(e) {
        e.preventDefault(); // Mencegah pengiriman form default
        
        const form = $(this);
        const url = form.attr('action');
        const formData = form.serialize();

        // Kirim data menggunakan AJAX
        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('Data realisasi berhasil disimpan!');
                    window.location.reload(); 
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Terjadi kesalahan saat menyimpan data.');
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>