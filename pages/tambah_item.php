<?php
// Jangan ada spasi atau output apa pun sebelum tag PHP ini
session_start();
include '../includes/koneksi.php';

// =========================================================================
// BAGIAN 1: LOGIKA & PEMROSESAN DATA (SEMUA PHP DI ATAS)
// =========================================================================

// Validasi hak akses pengguna
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    // Anda bisa redirect ke halaman login atau menampilkan pesan error
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

// Ambil dan validasi parameter dari URL (GET)
$id_akun = filter_input(INPUT_GET, 'id_akun', FILTER_VALIDATE_INT);
$tahun   = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT) ?: (int) date('Y'); // Default tahun sekarang jika tidak ada

if (!$id_akun) {
    die("Parameter ID Akun tidak valid atau tidak ditemukan.");
}

// Ambil informasi akun dari database
$akun = null;
$stmt_akun = $koneksi->prepare("SELECT nama FROM master_akun WHERE id = ? LIMIT 1");
$stmt_akun->bind_param("i", $id_akun);
$stmt_akun->execute();
$result_akun = $stmt_akun->get_result();
if ($result_akun->num_rows > 0) {
    $akun = $result_akun->fetch_assoc();
}
$stmt_akun->close();

if (!$akun) {
    die("Data Akun dengan ID {$id_akun} tidak ditemukan.");
}

// Inisialisasi variabel untuk form
$error_message = '';
$nama_item = $satuan = $volume = $harga = '';

// Proses form jika metode adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input dari form
    $nama_item = trim(filter_input(INPUT_POST, 'nama_item', FILTER_SANITIZE_STRING));
    $satuan    = trim(filter_input(INPUT_POST, 'satuan', FILTER_SANITIZE_STRING));
    $volume    = filter_input(INPUT_POST, 'volume', FILTER_VALIDATE_INT);
    $harga_raw = $_POST['harga'] ?? '0';

    // Normalisasi harga: hapus semua karakter kecuali angka dan koma
    $harga_clean = preg_replace('/[^0-9,]/', '', $harga_raw);
    // Ganti koma dengan titik untuk desimal
    $harga = (float) str_replace(',', '.', $harga_clean);
    
    // Kalkulasi pagu di sisi server untuk memastikan keakuratan
    $pagu = $volume * $harga;

    // Validasi input
    if (empty($nama_item)) {
        $error_message = "Nama item wajib diisi.";
    } elseif ($volume === false || $volume < 0) {
        $error_message = "Volume harus berupa angka yang valid dan tidak boleh negatif.";
    } elseif ($harga < 0) {
        $error_message = "Harga harus berupa angka yang valid dan tidak boleh negatif.";
    } else {
        // Jika validasi lolos, simpan ke database
        $sql_insert = "INSERT INTO master_item (akun_id, tahun, nama_item, satuan, volume, harga, pagu) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $koneksi->prepare($sql_insert);
        // Tipe data: i (integer), i, s (string), s, i, d (double), d
        $stmt_insert->bind_param("iissidd", $id_akun, $tahun, $nama_item, $satuan, $volume, $harga, $pagu);

        if ($stmt_insert->execute()) {
            // Jika berhasil, siapkan pesan sukses dan redirect
            $_SESSION['flash_message'] = "Item '{$nama_item}' berhasil ditambahkan.";
            $_SESSION['flash_message_type'] = 'success';
            header("Location: manajemen_anggaran.php?tahun=" . $tahun);
            exit;
        } else {
            // Jika gagal, tampilkan error
            $error_message = "Gagal menyimpan data ke database: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }
}

// =========================================================================
// BAGIAN 2: TAMPILAN HTML (SETELAH SEMUA LOGIKA PHP SELESAI)
// =========================================================================
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
:root {
    --primary-blue: #0A2E5D;
}
.main-content { padding: 30px; background:#f7f9fc; }
.form-card { background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.form-title { font-size:1.5rem; font-weight:700; margin-bottom:5px; color: var(--primary-blue); }
.form-subtitle { margin-bottom: 20px; color: #6c757d; }
.form-group label { font-weight: 600; }
.btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); }
</style>

<main class="main-content">
  <div class="container">
    <div class="form-card">
      <h2 class="form-title">Tambah Item Anggaran</h2>
      <p class="form-subtitle">Untuk Akun: <strong><?= htmlspecialchars($akun['nama']) ?></strong> (Tahun Anggaran <?= htmlspecialchars($tahun) ?>)</p>

      <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>

      <form method="post" id="addItemForm" novalidate>
        <div class="form-group">
          <label for="nama_item">Nama Item / Uraian <span class="text-danger">*</span></label>
          <input type="text" name="nama_item" id="nama_item" class="form-control" required value="<?= htmlspecialchars($nama_item) ?>">
        </div>
        
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="volume">Volume</label>
                <input type="number" name="volume" id="volume" class="form-control" min="0" step="1" value="<?= htmlspecialchars($volume) ?>">
            </div>
            <div class="form-group col-md-6">
                <label for="satuan">Satuan</label>
                <input type="text" name="satuan" id="satuan" class="form-control" value="<?= htmlspecialchars($satuan) ?>">
            </div>
        </div>

        <div class="form-group">
          <label for="harga">Harga Satuan (Rp)</label>
          <input type="text" name="harga" id="harga" class="form-control" value="<?= htmlspecialchars($harga) ?>">
          <small class="form-text text-muted">Contoh: 150000 atau 150.000</small>
        </div>

        <div class="form-group">
          <label for="pagu">Total Pagu (Otomatis)</label>
          <input type="text" id="pagu" class="form-control" readonly style="background-color: #e9ecef; cursor: not-allowed;">
        </div>
        
        <hr>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Item</button>
        <a href="../pages/master_data.php?tahun=<?= $tahun ?>" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const volumeEl = document.getElementById('volume');
    const hargaEl  = document.getElementById('harga');
    const paguEl   = document.getElementById('pagu');
    const form     = document.getElementById('addItemForm');

    // Fungsi untuk membersihkan nilai input harga
    function parseHargaInput(val) {
        if (!val) return 0;
        // Hapus semua karakter kecuali angka dan koma
        const cleaned = val.toString().replace(/[^0-9,]/g, '');
        // Ganti koma dengan titik untuk kalkulasi
        const numericString = cleaned.replace(',', '.');
        return parseFloat(numericString) || 0;
    }

    // Fungsi untuk memformat angka ke format Rupiah
    function formatIDR(n) {
        return new Intl.NumberFormat('id-ID', { 
            style: 'currency', 
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(n);
    }

    // Fungsi utama untuk menghitung pagu
    function calculatePagu() {
        const volume = parseInt(volumeEl.value, 10) || 0;
        const harga = parseHargaInput(hargaEl.value);
        const total = volume * harga;
        paguEl.value = formatIDR(total);
    }

    // Jalankan kalkulasi setiap kali ada input di volume atau harga
    volumeEl.addEventListener('input', calculatePagu);
    hargaEl.addEventListener('input', calculatePagu);

    // Hitung pagu saat halaman pertama kali dimuat
    calculatePagu();

    // Mencegah submit ganda
    form.addEventListener('submit', function() {
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    });
});
</script>

<?php include '../includes/footer.php'; ?>