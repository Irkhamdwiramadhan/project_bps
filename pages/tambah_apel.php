<?php
// Masukkan file koneksi database dan layout utama
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil data pegawai dari database untuk dropdown
$sql_pegawai = "SELECT id, nama FROM pegawai WHERE is_active = '1' ORDER BY nama ASC";
$result_pegawai = $koneksi->query($sql_pegawai);
$pegawai_list = [];
if ($result_pegawai->num_rows > 0) {
    while($row = $result_pegawai->fetch_assoc()) {
        $pegawai_list[] = $row;
    }
}
?>

<main class="main-content">
    <div class="header-content" style="display: flex; align-items: center; justify-content: center; gap: 15px; position: relative;">
        <a href="apel.php" class="btn-back" style="font-size: 1.5rem; color: #333; position: absolute; left: 0;">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 style="margin: 0;">Tambah Data Apel</h2>
    </div>

    <div class="card">
        <form action="../proses/proses_apel.php" method="POST" enctype="multipart/form-data">
            <label for="tanggal">Tanggal Apel:</label>
            <input type="date" id="tanggal" name="tanggal" required>

            <label for="kondisi_apel">Kondisi Apel:</label>
            <select id="kondisi_apel" name="kondisi_apel" class="form-control" onchange="toggleFormFields()">
                <option value="ada">Apel Dilaksanakan</option>
                <option value="tidak_ada">Apel Tidak Dilaksanakan</option>
                <option value="lupa_didokumentasikan" selected>Apel Lupa Didokumentasikan</option>
            </select>

            <div id="form_tidak_ada" style="display: none;">
                <label for="alasan_tidak_ada">Alasan Apel Tidak Dilaksanakan:</label>
                <textarea id="alasan_tidak_ada" name="alasan_tidak_ada" rows="4"></textarea>
            </div>

            <div id="form_ada_lupa">
                <label for="pembina_apel">Pembina Apel:</label>
                <select id="pembina_apel" name="pembina_apel" class="form-control">
                    <option value="">-- Pilih Pembina Apel --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>"><?= htmlspecialchars($pegawai['nama']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="komando">Komando:</label>
                <select id="komando" name="komando" class="form-control">
                    <option value="">-- Pilih Komandan --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>"><?= htmlspecialchars($pegawai['nama']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="petugas">Pemandu Yel-yel:</label>
                <select id="petugas" name="petugas" class="form-control">
                    <option value="">-- Pilih Petugas --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>"><?= htmlspecialchars($pegawai['nama']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="pemimpin_doa">Pembaca BerAkhlak:</label>
                <select id="pemimpin_doa" name="pemimpin_doa" class="form-control">
                    <option value="">-- Pilih Pemimpin Doa --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>"><?= htmlspecialchars($pegawai['nama']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="keterangan">Catatan Umum:</label>
                <textarea id="keterangan" name="keterangan" rows="4"></textarea>

                <div id="form_foto">
                    <label for="foto_bukti">Foto Bukti Apel:</label>
                    <input type="file" id="foto_bukti" name="foto_bukti">
                </div>

                <h3>Status Kehadiran Pegawai</h3>
                <div class="kehadiran-container">
                    <?php foreach ($pegawai_list as $pegawai): ?>
                    <div class="status-card">
                        <input type="hidden" name="kehadiran[<?= $pegawai['id'] ?>][id_pegawai]" value="<?= $pegawai['id'] ?>">
                        <strong><?= htmlspecialchars($pegawai['nama']); ?></strong>
                        <div class="status-options">
                            <?php
                            $statuses = [
                                'hadir_awal' => 'Hadir Awal',
                                'hadir' => 'Hadir',
                                'telat_1' => 'Telat 1',
                                'telat_2' => 'Telat 2',
                                'telat_3' => 'Telat 3',
                                'izin' => 'Izin',
                                'absen' => 'Absen',
                                'dinas_luar' => 'Dinas Luar',
                                'sakit' => 'Sakit',
                                'cuti' => 'Cuti',
                                'tugas' => 'Tugas'
                            ];
                            foreach ($statuses as $key => $label):
                            ?>
                                <input type="radio" id="status-<?= $pegawai['id'] ?>-<?= $key ?>" name="kehadiran[<?= $pegawai['id'] ?>][status]" value="<?= $key ?>" class="status-radio">
                                <label for="status-<?= $pegawai['id'] ?>-<?= $key ?>" class="status-label"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="kehadiran[<?= $pegawai['id'] ?>][catatan]" placeholder="Catatan (opsional)" class="catatan-input">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <br>
            <button type="submit" class="btn btn-primary">Simpan Data Apel</button>
        </form>
    </div>
</main>

<style>
.kehadiran-container {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.status-card {
  border: 1px solid #ddd;
  border-radius: 10px;
  padding: 10px;
  background: #fff;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.status-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}
.status-radio {
  display: none;
}
.status-label {
  flex: 1 1 calc(33.33% - 8px);
  text-align: center;
  background: #f8f9fa;
  border: 1px solid #ccc;
  border-radius: 6px;
  padding: 8px 0;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s;
}
.status-label:hover {
  background-color: #e9ecef;
}
.status-radio:checked + .status-label {
  background-color: #007bff;
  color: white;
  border-color: #007bff;
}
.catatan-input {
  width: 100%;
  margin-top: 6px;
  border: 1px solid #ccc;
  border-radius: 6px;
  padding: 6px;
}
@media (max-width: 600px) {
  .status-label {
    flex: 1 1 calc(48% - 8px);
    font-size: 12px;
  }
}
.btn-back {
  text-decoration: none;
  transition: color 0.2s;
}
.btn-back:hover {
  color: #007bff !important;
}
</style>

<script>
function toggleFormFields() {
    var kondisiApel = document.getElementById('kondisi_apel').value;
    var formFoto = document.getElementById('form_foto');
    var formTidakAda = document.getElementById('form_tidak_ada');
    var formAdaLupa = document.getElementById('form_ada_lupa');
    var tabelKehadiran = document.querySelector('.kehadiran-container');

    // Reset tampilan
    formTidakAda.style.display = 'none';
    formAdaLupa.style.display = 'none';
    formFoto.style.display = 'none';
    tabelKehadiran.style.display = 'none';

    if (kondisiApel === 'tidak_ada') {
        formTidakAda.style.display = 'block';
    } else {
        formAdaLupa.style.display = 'block';
        tabelKehadiran.style.display = 'block';
        if (kondisiApel === 'ada') {
            formFoto.style.display = 'block';
        }
    }
}
document.addEventListener('DOMContentLoaded', toggleFormFields);
</script>

<?php include '../includes/footer.php'; ?>
