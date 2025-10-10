<?php
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil data pegawai dari database untuk dropdown
$query_pegawai = "SELECT id, nama, nip FROM pegawai WHERE is_active = '1' ORDER BY nama ASC";
$result_pegawai = mysqli_query($koneksi, $query_pegawai);
$pegawai_options = '';
while ($row = mysqli_fetch_assoc($result_pegawai)) {
    $pegawai_options .= '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nama']) . ' (' . htmlspecialchars($row['nip']) . ')</option>';
}
$current_year = date('Y');
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Styling tambahan untuk tampilan yang lebih rapi */
    .dynamic-input-group {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }
    .dynamic-input-group .select2-container {
        flex: 3;
    }
    .dynamic-input-group .form-control[type="number"] {
        flex: 1;
        min-width: 100px;
    }
    .dynamic-input-group .btn {
        height: 38px; /* Menyesuaikan tinggi dengan input */
    }
</style>

<div class="main-content">
    <section class="content-header">
        <h1>
            <i class="fas fa-trophy"></i> Tambah Calon
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Formulir Tambah Calon</h3>
                    </div>
                    <div class="box-body">
                        <form id="form_tambah_calon" method="POST" action="../proses/proses_tambah_calon.php">
                            <div class="form-group">
                                <label for="jenis_penilaian">Jenis Penilaian:</label>
                                <select name="jenis_penilaian" id="jenis_penilaian" class="form-control" required>
                                    <option value="pegawai_prestasi">Pegawai Prestasi</option>
                                    <option value="can">CAN</option>
                                </select>
                            </div>

                            
                            <div class="form-group" id="triwulan_form_group">
                                <label for="triwulan">Triwulan:</label>
                                <select name="triwulan" id="triwulan" class="form-control" required>
                                    <option value="">-- Pilih Triwulan --</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tahun">Tahun:</label>
                                <select name="tahun" id="tahun" class="form-control" required>
                                    <?php for ($y = $current_year + 1; $y >= 2020; $y--): ?>
                                        <option value="<?= $y ?>" <?= ($y == $current_year) ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Pilih Pegawai dan Jumlah:</label>
                                    <div id="pegawai_container">
                                        <div class="dynamic-input-group">
                                            <select name="calon_data[0][id_pegawai]" class="form-control select2-pegawai" required>
                                                <option value="">-- Cari Nama Pegawai --</option>
                                                <?= $pegawai_options; ?>
                                            </select>
    
                                            <button type="button" class="btn btn-success" id="tambah_baris"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>
                                </div>
                                

                            <div class="box-footer">
                                <button type="submit" class="btn btn-success">Simpan</button>
                                <a href="calon_berprestasi.php" class="btn btn-default">Kembali</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inisialisasi Select2 pada dropdown pertama
    $('.select2-pegawai').select2({
        placeholder: "-- Cari Nama Pegawai --",
        allowClear: true,
    });

    // Fungsi untuk menyembunyikan/menampilkan triwulan
    function toggleTriwulan() {
        var jenisPenilaian = $('#jenis_penilaian').val();
        var triwulanGroup = $('#triwulan_form_group');
        var triwulanSelect = $('#triwulan');

        if (jenisPenilaian === 'can') {
            triwulanGroup.hide();
            triwulanSelect.prop('required', false);
            triwulanSelect.val('');
        } else {
            triwulanGroup.show();
            triwulanSelect.prop('required', true);
        }
    }
    
    toggleTriwulan();
    $('#jenis_penilaian').on('change', function() {
        toggleTriwulan();
    });

    // Logika untuk menambah dan menghapus baris input dinamis
    var counter = 0;
    $('#tambah_baris').on('click', function() {
        counter++;
        var newRow = `
            <div class="dynamic-input-group">
                <select name="calon_data[${counter}][id_pegawai]" class="form-control select2-pegawai" required>
                    <option value="">-- Cari Nama Pegawai --</option>
                    <?= $pegawai_options; ?>
                </select>
                <button type="button" class="btn btn-danger hapus-baris"><i class="fas fa-times"></i></button>
            </div>
        `;
        $('#pegawai_container').append(newRow);

        // Inisialisasi Select2 pada elemen yang baru ditambahkan
        $('.select2-pegawai').last().select2({
            placeholder: "-- Cari Nama Pegawai --",
            allowClear: true,
        });
    });

    // Handle klik tombol "-" (hapus)
    $(document).on('click', '.hapus-baris', function() {
        $(this).closest('.dynamic-input-group').remove();
    });
});
</script>

<?php 
include '../includes/footer.php'; 
mysqli_close($koneksi);
?><?php
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil data pegawai dari database untuk dropdown
$query_pegawai = "SELECT id, nama, nip FROM pegawai WHERE is_active = '1' ORDER BY nama ASC";
$result_pegawai = mysqli_query($koneksi, $query_pegawai);
$pegawai_options = '';
while ($row = mysqli_fetch_assoc($result_pegawai)) {
    $pegawai_options .= '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nama']) . ' (' . htmlspecialchars($row['nip']) . ')</option>';
}
$current_year = date('Y');
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Styling tambahan untuk tampilan yang lebih rapi */
    .dynamic-input-group {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }
    .dynamic-input-group .select2-container {
        flex: 3;
    }
    .dynamic-input-group .form-control[type="number"] {
        flex: 1;
        min-width: 100px;
    }
    .dynamic-input-group .btn {
        height: 38px; /* Menyesuaikan tinggi dengan input */
    }
</style>



<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inisialisasi Select2 pada dropdown pertama
    $('.select2-pegawai').select2({
        placeholder: "-- Cari Nama Pegawai --",
        allowClear: true,
    });

    // Fungsi untuk menyembunyikan/menampilkan triwulan
    function toggleTriwulan() {
        var jenisPenilaian = $('#jenis_penilaian').val();
        var triwulanGroup = $('#triwulan_form_group');
        var triwulanSelect = $('#triwulan');

        if (jenisPenilaian === 'can') {
            triwulanGroup.hide();
            triwulanSelect.prop('required', false);
            triwulanSelect.val('');
        } else {
            triwulanGroup.show();
            triwulanSelect.prop('required', true);
        }
    }
    
    toggleTriwulan();
    $('#jenis_penilaian').on('change', function() {
        toggleTriwulan();
    });

    // Logika untuk menambah dan menghapus baris input dinamis
    var counter = 0;
    $('#tambah_baris').on('click', function() {
        counter++;
        var newRow = `
            <div class="dynamic-input-group">
                <select name="calon_data[${counter}][id_pegawai]" class="form-control select2-pegawai" required>
                    <option value="">-- Cari Nama Pegawai --</option>
                    <?= $pegawai_options; ?>
                </select>
                <button type="button" class="btn btn-danger hapus-baris"><i class="fas fa-times"></i></button>
            </div>
        `;
        $('#pegawai_container').append(newRow);

        // Inisialisasi Select2 pada elemen yang baru ditambahkan
        $('.select2-pegawai').last().select2({
            placeholder: "-- Cari Nama Pegawai --",
            allowClear: true,
        });
    });

    // Handle klik tombol "-" (hapus)
    $(document).on('click', '.hapus-baris', function() {
        $(this).closest('.dynamic-input-group').remove();
    });
});
</script>

<?php 
include '../includes/footer.php'; 
mysqli_close($koneksi);
?>