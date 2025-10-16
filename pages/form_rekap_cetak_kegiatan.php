<?php
session_start();
include "../includes/koneksi.php";
include "../includes/header.php";
include "../includes/sidebar.php";

// Ambil daftar tim dari database
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim && $result_tim->num_rows > 0) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}
?>

<style>
.card {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    margin: 20px auto;
    max-width: 700px;
}
h3 {
    margin-bottom: 1rem;
    color: #333;
    text-align: center;
}
form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.form-group {
    display: flex;
    flex-direction: column;
}
label {
    font-weight: bold;
    margin-bottom: 0.3rem;
}
select, input {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 6px;
}
.btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    color: #fff;
    font-weight: 600;
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
.actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 1rem;
}
@media (max-width: 600px) {
    .card {
        padding: 15px;
        margin: 10px;
    }
}
</style>

<div class="main-content">
    <div class="card">
        <h3>Form Cetak Rekap Kegiatan Tim</h3>

        <form method="GET" action="../proses/cetak_kegiatan_tim_excel.php" target="_blank">
            <div class="form-group">
                <label>Pilih Tahun</label>
                <input type="number" name="tahun" value="<?= date('Y') ?>" required>
            </div>

            <div class="form-group">
                <label>Pilih Bulan</label>
                <select name="bulan">
                    <option value="">-- Semua Bulan --</option>
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $bulan_nama = date('F', mktime(0, 0, 0, $m, 1));
                        echo "<option value='$m'>$bulan_nama</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Pilih Tim</label>
                <select name="tim_id">
                    <option value="">-- Semua Tim --</option>
                    <?php foreach ($tim_list as $tim): ?>
                        <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Download Excel</button>
                <a href="rekap_kegiatan_tim.php" class="btn btn-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
