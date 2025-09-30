<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses, pastikan hanya peran yang diizinkan yang bisa akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_tu'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    // Redirect atau tampilkan pesan error jika tidak punya akses
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../dashboard.php"); // Arahkan ke dashboard
    exit();
}

// Ambil daftar tahun unik dari master_item untuk dropdown
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_item ORDER BY tahun DESC");
$daftar_tahun = [];
if ($tahun_result) {
    while ($row = $tahun_result->fetch_assoc()) {
        $daftar_tahun[] = $row['tahun'];
    }
}
if (empty($daftar_tahun)) {
    $daftar_tahun[] = date("Y");
}

?>

<style>
:root {
    --primary-blue: #0A2E5D;
    --light-blue-bg: #f8f9fa;
    --border-color: #dee2e6;
}
.main-content { padding: 30px; background-color: var(--light-blue-bg); }
.section-title { font-size: 1.8rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 25px; }
.card {
    background: #fff; border: none; border-radius: 12px;
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.07); padding: 25px;
}
.card-title { font-weight: 600; color: var(--primary-blue); }
.form-label { font-weight: 600; }
.custom-file-label::after {
    content: "Pilih File";
    background-color: var(--primary-blue);
    color: white;
}
.btn-primary {
    background: var(--primary-blue); border: none; padding: 12px 25px;
    font-weight: 600; transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(10, 46, 93, 0.3);
}
</style>

<main class="main-content">
  <div class="container-fluid">

    <h2 class="section-title">Upload Data Realisasi Bulanan</h2>
    <a href="realisasi.php" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Kembali</a>

    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Formulir Upload</h5>
        <p class="text-muted">Silakan pilih tahun dan bulan, lalu unggah file Excel realisasi yang sesuai.</p>
        
        <?php
        if (isset($_SESSION['flash_message'])) {
            // REVISI: Pengecekan yang lebih aman untuk tipe pesan
            $message_type = isset($_SESSION['flash_message_type']) ? $_SESSION['flash_message_type'] : 'success';
            $alert_class = ($message_type == 'danger') ? 'alert-danger' : 'alert-success';

            echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
            echo '  ' . htmlspecialchars($_SESSION['flash_message']);
            echo '  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            echo '</div>';

            // Hapus session setelah ditampilkan
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_message_type']);
        }
        ?>

        <form action="../proses/proses_realisasi.php" method="POST" enctype="multipart/form-data">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="tahun" class="form-label">Tahun Anggaran</label>
              <select class="form-control" id="tahun" name="tahun" required>
                <?php foreach ($daftar_tahun as $th): ?>
                    <option value="<?= $th ?>"><?= $th ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label for="bulan" class="form-label">Bulan Realisasi</label>
              <select class="form-control" id="bulan" name="bulan" required>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>"><?= DateTime::createFromFormat('!m', $i)->format('F') ?></option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="file_excel" class="form-label">File Excel Realisasi</label>
            <div class="custom-file">
              <input type="file" class="custom-file-input" id="file_excel" name="file_excel" required accept=".xls, .xlsx">
              <label class="custom-file-label" for="file_excel">Pilih file...</label>
            </div>
          </div>
          <hr>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload mr-2"></i>Upload dan Proses
          </button>
        </form>
      </div>
    </div>
  </div>
</main>

<script>
// Skrip sederhana untuk menampilkan nama file di input file bootstrap
document.querySelector('.custom-file-input').addEventListener('change', function(e) {
  var fileName = document.getElementById("file_excel").files[0].name;
  var nextSibling = e.target.nextElementSibling;
  nextSibling.innerText = fileName;
});
</script>

<?php include '../includes/footer.php'; ?>