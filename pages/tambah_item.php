<?php
// jangan ada spasi / output di atas PHP tag
session_start();
include '../includes/koneksi.php';

// validasi role (lakukan sebelum ada output HTML supaya redirect aman)
if (!isset($_SESSION['user_role']) || !in_array('super_admin', $_SESSION['user_role'])) {
    // jika ingin redirect ke login, gunakan header() lalu exit; kita tampilkan pesan saja
    die("Akses ditolak. Anda tidak memiliki izin.");
}

// ambil id_akun dan tahun (tahun optional dari GET; default current year)
$id_akun = isset($_GET['id_akun']) ? (int) $_GET['id_akun'] : 0;
$tahun  = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

if ($id_akun <= 0) {
    die("Parameter id_akun tidak valid.");
}

// ambil info akun (jangan keluarkan header/footer sebelum proses insert)
$akun = null;
if ($stmtA = $koneksi->prepare("SELECT id, nama FROM master_akun WHERE id = ? LIMIT 1")) {
    $stmtA->bind_param("i", $id_akun);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    if ($resA && $resA->num_rows > 0) {
        $akun = $resA->fetch_assoc();
    }
    $stmtA->close();
}
if (!$akun) {
    die("Data akun tidak ditemukan.");
}

// proses simpan (POST) — lakukan sebelum include header.php agar header() aman
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ambil input
    $nama_item_raw = $_POST['nama_item'] ?? '';
    $satuan_raw    = $_POST['satuan'] ?? '';
    $volume_raw    = $_POST['volume'] ?? '0';
    $harga_raw     = $_POST['harga'] ?? '0';

    $nama_item = trim($nama_item_raw);
    $satuan    = trim($satuan_raw);
    $volume    = (int) $volume_raw;

    // normalisasi harga: terima format 150000, 150.000, 150,000
    $harga_clean = str_replace(['.', ' '], '', $harga_raw); // hapus titik & spasi
    // jika ada koma dan tidak ada titik, kemungkinan "150,50" -> ganti koma dengan titik
    if (strpos($harga_clean, ',') !== false && strpos($harga_clean, '.') === false) {
        $harga_clean = str_replace(',', '.', $harga_clean);
    } else {
        // jika masih ada koma setelah penghapusan titik, ganti dengan titik
        $harga_clean = str_replace(',', '.', $harga_clean);
    }
    // pastikan numeric
    $harga = (float) $harga_clean;

    // hitung pagu (server-side)
    $pagu = $volume * $harga;

    // validasi
    if ($nama_item === '') {
        $error = "Nama item wajib diisi.";
    } elseif ($volume < 0) {
        $error = "Volume tidak valid.";
    } elseif ($harga < 0) {
        $error = "Harga tidak valid.";
    } else {
        // simpan ke DB (prepared statement)
        $sql = "INSERT INTO master_item (id_akun, tahun, nama_item, satuan, volume, harga, pagu) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $koneksi->prepare($sql)) {
            // bind: id_akun (i), tahun (i), nama_item (s), satuan (s), volume (i), harga (d), pagu (d)
            $stmt->bind_param("iissidd", $id_akun, $tahun, $nama_item, $satuan, $volume, $harga, $pagu);
            if ($stmt->execute()) {
                $stmt->close();
                // redirect kembali ke halaman master_data (lokasi relatif; sesuaikan jika perlu)
                header("Location: master_data.php?tahun=" . $tahun);
                exit;
            } else {
                $error = "Gagal menyimpan: " . $koneksi->error;
                $stmt->close();
            }
        } else {
            $error = "Gagal menyiapkan query: " . $koneksi->error;
        }
    }
}

// setelah proses POST selesai / jika GET, sekarang boleh include header dan render form
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<style>
.form-container { background:#fff; padding:22px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.05); margin-top:20px; }
.form-group { margin-bottom:12px; }
</style>

<main class="main-content">
  <div class="container">
    <div class="form-container">
      <h3>Tambah Item untuk Akun: <?= htmlspecialchars($akun['nama']) ?></h3>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <!-- tahun tidak ditampilkan sebagai input, server akan gunakan $tahun (GET atau current year) -->
        <div class="form-group">
          <label>Tahun</label>
          <div><strong><?= htmlspecialchars($tahun) ?></strong></div>
        </div>

        <div class="form-group">
          <label for="nama_item">Nama Item <span style="color:#d00">*</span></label>
          <input type="text" name="nama_item" id="nama_item" class="form-control" required value="<?= isset($_POST['nama_item']) ? htmlspecialchars($_POST['nama_item']) : '' ?>">
        </div>

        <div class="form-group">
          <label for="satuan">Satuan</label>
          <input type="text" name="satuan" id="satuan" class="form-control" value="<?= isset($_POST['satuan']) ? htmlspecialchars($_POST['satuan']) : '' ?>">
        </div>

        <div class="form-group">
          <label for="volume">Volume</label>
          <input type="number" name="volume" id="volume" class="form-control" required min="0" step="1" value="<?= isset($_POST['volume']) ? (int)$_POST['volume'] : '0' ?>">
        </div>

        <div class="form-group">
          <label for="harga">Harga Satuan (Rp)</label>
          <input type="text" name="harga" id="harga" class="form-control" required value="<?= isset($_POST['harga']) ? htmlspecialchars($_POST['harga']) : '' ?>">
          <small class="form-text text-muted">Contoh: 150000 atau 150.000 atau 150,000</small>
        </div>

        <div class="form-group">
          <label for="pagu">Total Pagu</label>
          <input type="text" id="pagu" class="form-control" readonly value="Rp 0">
        </div>

        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="master_data.php?tahun=<?= $tahun ?>" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</main>

<script>
// Auto-hitung pagu (client-side) — hanya untuk preview UX
document.addEventListener('DOMContentLoaded', function() {
    const volumeEl = document.getElementById('volume');
    const hargaEl  = document.getElementById('harga');
    const paguEl   = document.getElementById('pagu');

    function parseHargaInput(val) {
        if (!val) return 0;
        // hapus titik/spasi ribuan, ganti koma ke titik
        let cleaned = val.replace(/\s/g, '').replace(/\./g, '');
        if (cleaned.indexOf(',') !== -1 && cleaned.indexOf('.') === -1) {
            cleaned = cleaned.replace(',', '.');
        } else {
            cleaned = cleaned.replace(',', '.');
        }
        let num = parseFloat(cleaned);
        return isNaN(num) ? 0 : num;
    }

    function formatIDR(n) {
        return new Intl.NumberFormat('id-ID').format(n);
    }

    function hitung() {
        const v = parseInt(volumeEl.value) || 0;
        const h = parseHargaInput(hargaEl.value) || 0;
        const total = v * h;
        paguEl.value = 'Rp ' + formatIDR(total);
    }

    volumeEl.addEventListener('input', hitung);
    hargaEl.addEventListener('input', hitung);

    // hitung awal jika ada nilai
    hitung();
});
</script>

<?php include '../includes/footer.php'; ?>
