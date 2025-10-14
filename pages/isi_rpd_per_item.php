<?php
// pages/isi_rpd_per_item.php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// =========================================================================
// REVISI: Mengambil dan memvalidasi KODE_UNIK dari URL, bukan ID
// =========================================================================
$kode_unik_item = filter_input(INPUT_GET, 'kode_unik', FILTER_SANITIZE_STRING);
if (empty($kode_unik_item)) {
    die("Error: Kode unik item tidak valid atau tidak ditemukan.");
}

$selected_outputs = $_GET['selected_outputs'] ?? [];
if (!is_array($selected_outputs)) {
    $selected_outputs = [$selected_outputs];
}
$tahun = (int)($_GET['tahun'] ?? date("Y"));


// Ambil data item berdasarkan KODE_UNIK
$sql_item = "SELECT id, nama_item, pagu, tahun FROM master_item WHERE kode_unik = ?";
$stmt_item = $koneksi->prepare($sql_item);
$stmt_item->bind_param("s", $kode_unik_item);
$stmt_item->execute();
$result_item = $stmt_item->get_result();
if ($result_item->num_rows === 0) {
    die("Error: Item dengan kode unik '{$kode_unik_item}' tidak ditemukan.");
}
$item = $result_item->fetch_assoc();
$stmt_item->close();

// Ambil data RPD yang sudah ada berdasarkan KODE_UNIK_ITEM
$rpd_values = array_fill(1, 12, 0);
$sql_rpd = "SELECT bulan, jumlah FROM rpd WHERE kode_unik_item = ? AND tahun = ?";
$stmt_rpd = $koneksi->prepare($sql_rpd);
$stmt_rpd->bind_param("si", $kode_unik_item, $item['tahun']);
$stmt_rpd->execute();
$result_rpd = $stmt_rpd->get_result();
while ($row = $result_rpd->fetch_assoc()) {
    $rpd_values[$row['bulan']] = $row['jumlah'];
}
$stmt_rpd->close();
?>

<style>
:root {
    --primary-blue: #0A2E5D;
}
.main-content { padding: 30px; background:#f7f9fc; }
.section-title { font-size:1.8rem; font-weight:700; color: var(--primary-blue); }
.card { background:#fff; padding:30px; border-radius:12px; box-shadow:0 6px 25px rgba(0,0,0,0.07); }
.form-group label { font-weight: 600; }
.rpd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
.summary-box { 
    margin-top: 20px; padding: 20px; border-radius: 8px; 
    border: 1px solid #dee2e6; text-align: center;
}
.summary-box h5 { font-weight: 700; margin-bottom: 5px; color: #6c757d; text-transform: uppercase; font-size: 0.9rem;}
.summary-box p { font-size: 1.5rem; font-weight: 700; margin: 0; }
.sisa-pagu-aman { color: #28a745; }
.sisa-pagu-kurang { color: #dc3545; }
.btn-primary[disabled] { cursor: not-allowed; opacity: 0.65; }
</style>

<main class="main-content">
  <div class="container-fluid">
    <h2 class="section-title">Isi RPD untuk: <?= htmlspecialchars($item['nama_item']) ?></h2>
    <p class="text-muted">Tahun Anggaran: <?= htmlspecialchars($item['tahun']) ?></p>

    <div class="card">
      <form action="../proses/proses_simpan_rpd_item.php" method="POST" id="rpdForm">
        
        <input type="hidden" name="kode_unik_item" value="<?= htmlspecialchars($kode_unik_item) ?>">
        <input type="hidden" name="tahun" value="<?= $item['tahun'] ?>">
        <?php if (isset($_GET['selected_outputs'])): ?>
        <?php foreach ($_GET['selected_outputs'] as $output): ?>
            <input type="hidden" name="selected_outputs[]" value="<?= htmlspecialchars($output) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

        <div class="summary-box bg-light">
            <h5>Total Pagu Anggaran</h5>
            <p id="totalPaguDisplay" style="color: var(--primary-blue);">Rp <?= number_format($item['pagu'], 0, ',', '.') ?></p>
            <input type="hidden" id="totalPaguValue" value="<?= $item['pagu'] ?>">
        </div>

        <hr>
        <h5 class="mb-3">Rencana Penarikan Dana Bulanan</h5>
        <div class="rpd-grid">
            <?php for ($bulan = 1; $bulan <= 12; $bulan++): 
                $nama_bulan = DateTime::createFromFormat('!m', $bulan)->format('F');
            ?>
                <div class="form-group">
                    <label for="bulan_<?= $bulan ?>"><?= $nama_bulan ?></label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input type="text" class="form-control rpd-input" id="bulan_<?= $bulan ?>" 
                               name="bulan_rpd[<?= $bulan ?>]" 
                               value="<?= number_format($rpd_values[$bulan], 0, ',', '.') ?>"
                               placeholder="0">
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="summary-box">
                    <h5>Total RPD Direncanakan</h5>
                    <p id="totalRpdDisplay">Rp 0</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="summary-box">
                    <h5>Sisa Pagu</h5>
                    <p id="sisaPaguDisplay">Rp 0</p>
                </div>
            </div>
        </div>

        <hr>
        <button type="submit" id="saveButton" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Simpan Rencana RPD</button>
   <a href="tambah_rpd.php?tahun=<?= $tahun ?>&<?= http_build_query(['selected_outputs' => $selected_outputs]) ?>&step=2" class="btn btn-secondary">
    ← Kembali
</a>


      </form>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rpdInputs = document.querySelectorAll('.rpd-input');
    const totalPaguValue = parseFloat(document.getElementById('totalPaguValue').value) || 0;
    const totalRpdDisplay = document.getElementById('totalRpdDisplay');
    const sisaPaguDisplay = document.getElementById('sisaPaguDisplay');
    const saveButton = document.getElementById('saveButton');
    const form = document.getElementById('rpdForm');

    function parseNumber(value) {
        return parseInt(String(value).replace(/[^0-9]/g, ''), 10) || 0;
    }
    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }
    function updateCalculations() {
        let totalRpd = 0;
        rpdInputs.forEach(input => { totalRpd += parseNumber(input.value); });
        const sisaPagu = totalPaguValue - totalRpd;
        totalRpdDisplay.textContent = 'Rp ' + formatNumber(totalRpd);
        sisaPaguDisplay.textContent = 'Rp ' + formatNumber(sisaPagu);
        sisaPaguDisplay.classList.remove('sisa-pagu-aman', 'sisa-pagu-kurang');
        if (sisaPagu === 0) { sisaPaguDisplay.classList.add('sisa-pagu-aman');
        } else { sisaPaguDisplay.classList.add('sisa-pagu-kurang'); }
        saveButton.disabled = (sisaPagu !== 0);
    }
    rpdInputs.forEach(input => {
        input.addEventListener('input', updateCalculations);
        input.addEventListener('blur', function() { this.value = formatNumber(parseNumber(this.value)); });
    });
    form.addEventListener('submit', function() {
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    });
    updateCalculations();
});
</script>

<?php include '../includes/footer.php'; ?>