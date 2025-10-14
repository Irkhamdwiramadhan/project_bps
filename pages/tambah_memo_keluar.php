<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil daftar semua pegawai untuk multiple select
$pegawai = mysqli_query($koneksi, "SELECT id, nama FROM pegawai WHERE is_active = 1 ORDER BY nama ASC");

// Ambil pegawai yang jabatannya PPPK untuk dropdown petugas
$petugas_pppk = mysqli_query($koneksi, "SELECT id, nama FROM pegawai WHERE jabatan LIKE 'PPPK%' ORDER BY nama ASC");

?>

<!-- Tambahkan CDN Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
/* 🌟 ====== STYLE MODERN UNTUK FORM TAMBAH MEMO SATPAM ====== 🌟 */
body {
    background-color: #f4f6f9;
    font-family: 'Poppins', sans-serif;
}

.content-wrapper {
    min-height: 100vh;
    margin-left: 250px;
    padding: 40px;
    background-color: #f4f6f9;
    transition: all 0.3s ease-in-out;
}

body.sidebar-collapse .content-wrapper {
    margin-left: 80px;
    transition: all 0.3s ease-in-out;
}

.content-header h1 {
    font-weight: 600;
    color: #333;
}

/* ===== Form Container ===== */
form {
    background: #ffffff;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

form:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
}

/* ===== Label ===== */
label {
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}

/* ===== Input & Select ===== */
.form-control, .select2-container .select2-selection--multiple {
    border-radius: 10px;
    border: 1px solid #ced4da;
    padding: 10px 14px;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100% !important;
}

.select2-container--default .select2-selection--multiple {
    min-height: 45px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #007bff;
    border: none;
    color: white;
    border-radius: 8px;
    padding: 4px 8px;
    margin-top: 6px;
}

/* ===== Focus Effect ===== */
.form-control:focus, .select2-container--focus .select2-selection--multiple {
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.25);
    outline: none;
}

/* ===== Button ===== */
.btn-primary {
    background: linear-gradient(90deg, #007bff, #0056b3);
    border: none;
    border-radius: 12px;
    padding: 10px 22px;
    font-size: 1rem;
    transition: all 0.3s ease;
    color: #fff;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(90deg, #0069d9, #004c9b);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 123, 255, 0.4);
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
    .content-wrapper {
        margin-left: 0;
        padding: 20px;
    }
    form {
        padding: 25px;
    }
}
</style>

<div class="content-wrapper">
    <section class="content-header mb-4 d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-shield-alt me-2 text-primary"></i> Tambah Memo Satpam</h1>
        <a href="memo_keluar_kantor.php" class="btn btn-outline-primary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </section>

    <section class="content">
        <div class="container">
            <form action="../proses/proses_tambah_memo_keluar.php" method="POST" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Nama Pegawai</label>
                        <select name="pegawai_id[]" class="form-control select2" multiple="multiple" required>
                            <?php while ($row = mysqli_fetch_assoc($pegawai)): ?>
                                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nama']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">pilih beberapa pegawai yang terlibat</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Keperluan</label>
                    <textarea name="keperluan" class="form-control" rows="3" required></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Jam Pergi</label>
                        <input type="time" name="jam_pergi" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label>Jam Pulang (Opsional)</label>
                        <input type="time" name="jam_pulang" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Petugas (Jabatan PPPK)</label>
                        <select name="petugas" class="form-control select2" required>
                            <option value="">-- Pilih Petugas PPPK --</option>
                            <?php while ($p = mysqli_fetch_assoc($petugas_pppk)): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Foto</label>
                        <input type="file" name="foto" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Simpan Memo
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Cari atau pilih pegawai...",
        allowClear: true,
        width: '100%'
    });
});
</script>
