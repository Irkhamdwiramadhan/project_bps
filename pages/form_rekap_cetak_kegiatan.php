<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil daftar Tim untuk filter
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}
?>

<style>
    body { background-color: #e2e8f0; }
    .main-content { padding: 2rem; }
    .card { background-color: #ffffff; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #4a5568; }
    .form-select, .form-input { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; }
    .btn-success { background-color: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: bold; border: none; cursor: pointer; }
    .btn-success:hover { background-color: #059669; }
    
    /* Style khusus untuk multi-select biasa jika tidak pakai Select2 */
    select[multiple] {
        height: 150px;
    }
    .help-text { font-size: 0.85rem; color: #64748b; margin-top: 5px; }
</style>

<div class="main-content">
    <div class="card">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: bold; color: #1e293b;">
            Cetak Rekap Honor (Excel)
        </h2>

        <form action="../proses/export_excel_rekap.php" method="POST" target="_blank">
            
            <div class="form-group">
                <label>Bulan Pembayaran</label>
                <select name="bulan" class="form-select" required>
                    <option value="">-- Pilih Bulan --</option>
                    <option value="01">Januari</option><option value="02">Februari</option><option value="03">Maret</option>
                    <option value="04">April</option><option value="05">Mei</option><option value="06">Juni</option>
                    <option value="07">Juli</option><option value="08">Agustus</option><option value="09">September</option>
                    <option value="10">Oktober</option><option value="11">November</option><option value="12">Desember</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tahun Anggaran</label>
                <input type="number" name="tahun" class="form-input" value="<?= date('Y') ?>" required>
            </div>

            <div class="form-group">
                <label>Pilih Tim (Bisa lebih dari satu)</label>
                <select name="tim_id[]" class="form-select" multiple required>
                    <?php foreach ($tim_list as $tim) : ?>
                        <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">* Tahan tombol <strong>CTRL</strong> (Windows) atau <strong>Command</strong> (Mac) untuk memilih beberapa tim sekaligus.</p>
            </div>

            <div style="margin-top: 2rem;">
                <button type="submit" class="btn-success">
                    Download Excel
                </button>
            </div>

        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>