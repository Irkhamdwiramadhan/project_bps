<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek Login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Cek ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: memo_keluar_kantor.php');
    exit;
}

$id = (int)$_GET['id'];

// 1. Ambil Data Memo dari tabel memo_satpam
$query_memo = "SELECT * FROM memo_satpam WHERE id = ?";
$stmt = $koneksi->prepare($query_memo);
$stmt->bind_param("i", $id);
$stmt->execute();
$result_memo = $stmt->get_result();
$data = $result_memo->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Data memo tidak ditemukan.");
}

// 2. Siapkan Array Pegawai Terpilih
// Karena di tabel memo_satpam kolom pegawai_id isinya string "1,2,3", kita explode jadi array
$selected_pegawai = !empty($data['pegawai_id']) ? explode(',', $data['pegawai_id']) : [];

// 3. Ambil Data Master Pegawai & Petugas
$pegawai = mysqli_query($koneksi, "SELECT id, nama FROM pegawai WHERE is_active = 1 ORDER BY nama ASC");
$petugas_pppk = mysqli_query($koneksi, "SELECT id, nama FROM pegawai WHERE jabatan LIKE 'PPPK%' ORDER BY nama ASC");
?>

<!-- Tambahkan CDN Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* 🌟 ====== STYLE MODERN ====== 🌟 */
body { background-color: #f4f6f9; font-family: 'Poppins', sans-serif; }

.content-wrapper {
    min-height: 100vh; margin-left: 250px; padding: 40px;
    background-color: #f4f6f9; transition: all 0.3s ease-in-out;
}
body.sidebar-collapse .content-wrapper { margin-left: 80px; }

/* Form Container */
form {
    background: #ffffff; border-radius: 16px; padding: 40px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); transition: all 0.3s ease;
}
/* Label & Inputs */
label { font-weight: 600; color: #333; margin-bottom: 6px; }
.form-control, .select2-container .select2-selection--multiple, .select2-selection--single {
    border-radius: 10px; border: 1px solid #ced4da; padding: 10px 14px;
    font-size: 0.95rem; width: 100% !important; min-height: 45px;
}
/* Select2 Adjustments */
.select2-container .select2-selection--single {
    height: 45px; display: flex; align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { top: 10px; }
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff; border: none; color: white; border-radius: 8px;
    padding: 4px 8px; margin-top: 6px;
}
/* Focus */
.form-control:focus, .select2-container--focus .select2-selection--multiple {
    border-color: #007bff; box-shadow: 0 0 8px rgba(0, 123, 255, 0.25); outline: none;
}
/* Button */
.btn-primary {
    background: linear-gradient(90deg, #007bff, #0056b3); border: none;
    border-radius: 12px; padding: 10px 22px; font-size: 1rem; color: #fff;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3); transition: all 0.3s ease;
}
.btn-primary:hover {
    background: linear-gradient(90deg, #0069d9, #004c9b); transform: translateY(-2px);
}
/* Preview Foto */
.current-photo-box {
    margin-top: 10px; padding: 10px; border: 1px dashed #ced4da;
    border-radius: 10px; background: #f8f9fa; text-align: center;
}
.current-photo-img {
    max-height: 150px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .content-wrapper { margin-left: 0; padding: 20px; }
    form { padding: 25px; }
}
</style>

<div class="content-wrapper">
    <section class="content-header mb-4 d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-edit me-2 text-primary"></i> Edit Memo Satpam</h1>
        <a href="memo_keluar_kantor.php" class="btn btn-outline-primary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </section>

    <section class="content">
        <div class="container">
            
            <!-- Arahkan ke file proses_edit -->
            <form action="../proses/proses_edit_memo_keluar.php" method="POST" enctype="multipart/form-data">
                
                <!-- ID Hidden -->
                <input type="hidden" name="id" value="<?= $data['id'] ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required value="<?= htmlspecialchars($data['tanggal']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Nama Pegawai</label>
                        <select name="pegawai_id[]" class="form-control select2" multiple="multiple" required>
                            <?php 
                            // Reset pointer
                            mysqli_data_seek($pegawai, 0);
                            while ($row = mysqli_fetch_assoc($pegawai)): 
                                // Cek apakah ID ada di array yang sudah di-explode
                                $isSelected = in_array($row['id'], $selected_pegawai) ? 'selected' : '';
                            ?>
                                <option value="<?= $row['id'] ?>" <?= $isSelected ?>>
                                    <?= htmlspecialchars($row['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Pegawai yang sudah dipilih sebelumnya otomatis tertanda.</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Keperluan</label>
                    <textarea name="keperluan" class="form-control" rows="3" required><?= htmlspecialchars($data['keperluan']) ?></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Jam Pergi</label>
                        <input type="time" name="jam_pergi" class="form-control" required value="<?= htmlspecialchars($data['jam_pergi']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Jam Pulang (Opsional)</label>
                        <input type="time" name="jam_pulang" class="form-control" value="<?= htmlspecialchars($data['jam_pulang']) ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Petugas (Jabatan PPPK)</label>
                        <select name="petugas" class="form-control select2" required>
                            <option value="">-- Pilih Petugas PPPK --</option>
                            <?php 
                            mysqli_data_seek($petugas_pppk, 0);
                            while ($p = mysqli_fetch_assoc($petugas_pppk)): 
                                $isPetugas = ($p['id'] == $data['petugas']) ? 'selected' : '';
                            ?>
                                <option value="<?= $p['id'] ?>" <?= $isPetugas ?>>
                                    <?= htmlspecialchars($p['nama']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Ganti Foto (biarkan jika foto tidak ingin di rubah)</label>
                        <input type="file" name="foto" class="form-control" accept="image/*">
                        
                   
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Inisialisasi Select2
    $('.select2').select2({
        placeholder: "Cari atau pilih...",
        allowClear: true,
        width: '100%'
    });
});
</script>

<?php include '../includes/footer.php'; ?>