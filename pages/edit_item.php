<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses, sama seperti di halaman utama
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];
$has_access = !empty(array_intersect($user_roles, $allowed_roles_for_action));

if (!$has_access) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: manajemen_anggaran.php");
    exit();
}

// 1. Validasi dan ambil ID item dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: ID item tidak valid atau tidak ditemukan.");
}
$id_item = (int)$_GET['id'];

// 2. Ambil data item dari database (termasuk tahun)
// REVISI: Mengambil 'tahun' dalam satu query yang aman
$sql = "SELECT nama_item, satuan, volume, harga, pagu, tahun FROM master_item WHERE id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $id_item);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Item dengan ID {$id_item} tidak ditemukan di database.");
}
$item = $result->fetch_assoc();
$item_tahun = $item['tahun'];

// Bagi 1000 untuk menampilkan nilai asli (dalam ribuan).
$harga_asli = $item['harga'] / 1000;
$pagu_asli = $item['pagu'] / 1000;
?>

<style>
:root {
    --primary-blue: #0A2E5D;
    --light-blue-bg: #E6EEF7;
}
.main-content { padding: 30px; background:#f7f9fc; }
.section-title { font-size:1.5rem; font-weight:700; margin-bottom:20px; color: var(--primary-blue); }
.card { background:#fff; padding:30px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
.form-group label { font-weight: 600; color: #555; }
.btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); }
.btn-secondary { background-color: #6c757d; border-color: #6c757d; }
/* REVISI: Style untuk input pagu yang readonly */
#pagu {
    background-color: #e9ecef; /* Warna latar abu-abu */
    cursor: not-allowed; /* Ubah cursor untuk menandakan tidak bisa di-edit */
}
</style>

<main class="main-content">
  <div class="container">
    <h2 class="section-title">Edit Item Anggaran</h2>
    <div class="card">
      <form action="../proses/proses_edit_item.php" method="POST">
        
        <input type="hidden" name="id_item" value="<?= htmlspecialchars($id_item) ?>">
        <input type="hidden" name="tahun" value="<?= htmlspecialchars($item_tahun) ?>">
        
        <div class="form-group">
          <label for="nama_item">Nama Item/Uraian</label>
          <input type="text" class="form-control" id="nama_item" name="nama_item" value="<?= htmlspecialchars($item['nama_item']) ?>" required>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="volume">Volume</label>
            <input type="number" class="form-control" id="volume" name="volume" value="<?= htmlspecialchars($item['volume']) ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label for="satuan">Satuan</label>
            <input type="text" class="form-control" id="satuan" name="satuan" value="<?= htmlspecialchars($item['satuan']) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="harga">Harga Satuan (dalam Ribuan)</label>
            <input type="text" class="form-control" id="harga" name="harga" value="<?= number_format($harga_asli, 0, ',', '.') ?>" required>
            <small class="form-text text-muted">Contoh: Ketik <b>500</b> atau <b>500.000</b>.</small>
          </div>
          <div class="form-group col-md-6">
            <label for="pagu">Jumlah Biaya/Pagu (Otomatis)</label>
            <input type="text" class="form-control" id="pagu" name="pagu" value="<?= number_format($pagu_asli, 0, ',', '.') ?>" required readonly>
            <small class="form-text text-muted">Akan terisi otomatis dari Volume x Harga.</small>
          </div>
        </div>

        <hr>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
        <a href="../pages/master_data.php?tahun=<?= htmlspecialchars($item_tahun) ?>" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ambil elemen input
    const volumeInput = document.getElementById('volume');
    const hargaInput = document.getElementById('harga');
    const paguInput = document.getElementById('pagu');

    // Fungsi untuk membersihkan angka (menghapus titik atau karakter non-numerik)
    function cleanNumber(value) {
        // Jika input kosong atau tidak valid, kembalikan 0
        if (!value || typeof value !== 'string') {
            return 0;
        }
        // Hapus semua karakter kecuali digit
        return parseInt(value.replace(/[^0-9]/g, ''), 10) || 0;
    }

    // Fungsi untuk memformat angka dengan pemisah ribuan (titik)
    function formatNumber(value) {
        return new Intl.NumberFormat('id-ID').format(value);
    }

    // Fungsi utama untuk menghitung pagu
    function calculatePagu() {
        const volume = cleanNumber(volumeInput.value);
        const harga = cleanNumber(hargaInput.value);
        
        const pagu = volume * harga;
        
        // Tampilkan hasil yang sudah diformat ke input pagu
        paguInput.value = formatNumber(pagu);
    }

    // Tambahkan 'event listener' ke input volume dan harga
    // 'input' akan berjalan setiap kali ada ketikan
    volumeInput.addEventListener('input', calculatePagu);
    hargaInput.addEventListener('input', calculatePagu);

    // Menambahkan listener untuk memformat input harga saat pengguna selesai mengetik
    hargaInput.addEventListener('blur', function() {
        const cleanedValue = cleanNumber(this.value);
        this.value = formatNumber(cleanedValue);
    });
});
</script>

<?php include '../includes/footer.php'; ?>