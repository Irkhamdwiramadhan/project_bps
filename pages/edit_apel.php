<?php
// Masukkan file koneksi database dan layout utama
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. AMBIL ID DARI URL DAN DATA APEL
// ------------------------------------------------
// Periksa apakah 'id' ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<main class='main-content'>Data apel tidak ditemukan. <a href='apel.php'>Kembali</a></main>";
    include '../includes/footer.php';
    exit;
}

$id_apel = $_GET['id'];

// Ambil data apel yang spesifik dari database
$sql_apel = "SELECT * FROM apel WHERE id = ?";
$stmt_apel = $koneksi->prepare($sql_apel);
$stmt_apel->bind_param("i", $id_apel);
$stmt_apel->execute();
$result_apel = $stmt_apel->get_result();

if ($result_apel->num_rows === 0) {
    echo "<main class='main-content'>Data apel dengan ID $id_apel tidak ditemukan. <a href='apel.php'>Kembali</a></main>";
    include '../includes/footer.php';
    exit;
}

// Simpan data apel ke variabel
$apel = $result_apel->fetch_assoc();

// 2. PROSES DATA KEHADIRAN (JSON)
// ------------------------------------------------
// Decode data JSON kehadiran
$kehadiran_json = json_decode($apel['kehadiran'], true);
$kehadiran_map = [];

// Ubah array JSON menjadi map agar mudah dicari berdasarkan id_pegawai
if (is_array($kehadiran_json)) {
    foreach ($kehadiran_json as $item) {
        $kehadiran_map[$item['id_pegawai']] = [
            'status' => $item['status'] ?? '',
            'catatan' => $item['catatan'] ?? ''
        ];
    }
}

// 3. AMBIL DATA PEGAWAI (SAMA SEPERTI TAMBAH_APEL.PHP)
// ------------------------------------------------
$sql_pegawai = "SELECT id, nama FROM pegawai WHERE is_active = '1' ORDER BY nama ASC";
$result_pegawai = $koneksi->query($sql_pegawai);
$pegawai_list = [];
if ($result_pegawai->num_rows > 0) {
    while($row = $result_pegawai->fetch_assoc()) {
        $pegawai_list[] = $row;
    }
}

// Helper function kecil untuk memilih dropdown
function is_selected($value1, $value2) {
    return ($value1 == $value2) ? 'selected' : '';
}

// Helper function kecil untuk mencentang radio button
function is_checked($value1, $value2) {
    return ($value1 == $value2) ? 'checked' : '';
}
?>

<main class="main-content">
    <div class="header-content" style="display: flex; align-items: center; justify-content: center; gap: 15px; position: relative;">
        <a href="apel.php" class="btn-back" style="font-size: 1.5rem; color: #333; position: absolute; left: 0;">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 style="margin: 0;">Edit Data Apel</h2>
    </div>

    <div class="card">
        <form action="../proses/proses_edit_apel.php" method="POST" enctype="multipart/form-data">
            
            <input type="hidden" name="id_apel" value="<?= htmlspecialchars($apel['id']); ?>">

            <label for="tanggal">Tanggal Apel:</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= htmlspecialchars($apel['tanggal']); ?>" required>

            <label for="kondisi_apel">Kondisi Apel:</label>
            <select id="kondisi_apel" name="kondisi_apel" class="form-control" onchange="toggleFormFields()">
                <option value="ada" <?= is_selected('ada', $apel['kondisi_apel']); ?>>Apel Dilaksanakan</option>
                <option value="tidak_ada" <?= is_selected('tidak_ada', $apel['kondisi_apel']); ?>>Apel Tidak Dilaksanakan</option>
                <option value="lupa_didokumentasikan" <?= is_selected('lupa_didokumentasikan', $apel['kondisi_apel']); ?>>Apel Lupa Didokumentasikan</option>
            </select>

            <div id="form_tidak_ada" style="display: none;">
                <label for="alasan_tidak_ada">Alasan Apel Tidak Dilaksanakan:</label>
                <textarea id="alasan_tidak_ada" name="alasan_tidak_ada" rows="4"><?= htmlspecialchars($apel['alasan_tidak_ada'] ?? ''); ?></textarea>
            </div>

            <div id="form_ada_lupa">
                <label for="pembina_apel">Pembina Apel:</label>
                <select id="pembina_apel" name="pembina_apel" class="form-control">
                    <option value="">-- Pilih Pembina Apel --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>" <?= is_selected($pegawai['nama'], $apel['pembina_apel']); ?>>
                            <?= htmlspecialchars($pegawai['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="komando">Komando:</label>
                <select id="komando" name="komando" class="form-control">
                    <option value="">-- Pilih Komandan --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>" <?= is_selected($pegawai['nama'], $apel['komando'] ?? ''); ?>>
                            <?= htmlspecialchars($pegawai['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="petugas">Pemandu Yel-yel:</label>
                <select id="petugas" name="petugas" class="form-control">
                    <option value="">-- Pilih Petugas --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>" <?= is_selected($pegawai['nama'], $apel['petugas'] ?? ''); ?>>
                            <?= htmlspecialchars($pegawai['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="pemimpin_doa">Pembaca BerAkhlak:</label>
                <select id="pemimpin_doa" name="pemimpin_doa" class="form-control">
                    <option value="">-- Pilih Pemimpin Doa --</option>
                    <?php foreach ($pegawai_list as $pegawai): ?>
                        <option value="<?= htmlspecialchars($pegawai['nama']) ?>" <?= is_selected($pegawai['nama'], $apel['pemimpin_doa'] ?? ''); ?>>
                            <?= htmlspecialchars($pegawai['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="keterangan">Catatan Umum:</label>
                <textarea id="keterangan" name="keterangan" rows="4"><?= htmlspecialchars($apel['keterangan'] ?? ''); ?></textarea>

                <div id="form_foto">
                    <label for="foto_bukti">Foto Bukti Apel (Kosongkan jika tidak ingin ganti)</label>
                    <input type="file" id="foto_bukti" name="foto_bukti">
                    
                    <?php if (!empty($apel['foto_bukti'])): ?>
                        <div style="margin-top: 10px;">
                            <p>Foto Saat Ini:</p>
                            <img src="../uploads/apel/<?= htmlspecialchars($apel['foto_bukti']); ?>" alt="Foto Bukti" style="max-width: 250px; border-radius: 8px;">
                            <input type="hidden" name="foto_lama" value="<?= htmlspecialchars($apel['foto_bukti']); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <h3>Status Kehadiran Pegawai</h3>
                <div class="kehadiran-container">
                    <?php foreach ($pegawai_list as $pegawai): 
                        $pegawai_id = $pegawai['id'];
                        // Ambil status & catatan yang tersimpan untuk pegawai ini dari map
                        $saved_status = $kehadiran_map[$pegawai_id]['status'] ?? '';
                        $saved_catatan = $kehadiran_map[$pegawai_id]['catatan'] ?? '';
                    ?>
                    <div class="status-card">
                        <input type="hidden" name="kehadiran[<?= $pegawai_id ?>][id_pegawai]" value="<?= $pegawai_id ?>">
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
                                <input type="radio" id="status-<?= $pegawai_id ?>-<?= $key ?>" name="kehadiran[<?= $pegawai_id ?>][status]" value="<?= $key ?>" class="status-radio" <?= is_checked($key, $saved_status); ?>>
                                <label for="status-<?= $pegawai_id ?>-<?= $key ?>" class="status-label"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                        <input type="text" name="kehadiran[<?= $pegawai_id ?>][catatan]" placeholder="Catatan (opsional)" class="catatan-input" value="<?= htmlspecialchars($saved_catatan); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <br>
            <button type="submit" class="btn btn-primary">Update Data Apel</button>
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
// Script ini SAMA PERSIS dengan 'tambah_apel.php'
// Script ini akan otomatis berjalan saat halaman dimuat
// dan menampilkan/menyembunyikan field berdasarkan 'kondisi_apel' yang sudah dipilih.
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
    
    // Periksa null sebelum mengatur style
    if (tabelKehadiran) {
        tabelKehadiran.style.display = 'none';
    }

    if (kondisiApel === 'tidak_ada') {
        formTidakAda.style.display = 'block';
    } else {
        formAdaLupa.style.display = 'block';
        if (tabelKehadiran) {
            tabelKehadiran.style.display = 'flex'; // 'flex' agar sesuai style
        }
        if (kondisiApel === 'ada') {
            formFoto.style.display = 'block';
        }
    }
}
// Panggil fungsi ini saat halaman selesai dimuat
document.addEventListener('DOMContentLoaded', toggleFormFields);
</script>

<?php include '../includes/footer.php'; ?>