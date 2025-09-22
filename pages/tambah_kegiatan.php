<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil daftar mitra
$sql_mitra = "SELECT id, nama_lengkap FROM mitra ORDER BY nama_lengkap ASC";
$result_mitra = $koneksi->query($sql_mitra);
$mitra_list = [];
if ($result_mitra && $result_mitra->num_rows > 0) {
    while ($row = $result_mitra->fetch_assoc()) {
        $mitra_list[] = $row;
    }
}

// Ambil daftar survei, termasuk nama satuan
$sql_surveys = "SELECT id, nama_survei, singkatan_survei, satuan FROM surveys ORDER BY nama_survei ASC";
$result_surveys = $koneksi->query($sql_surveys);
$surveys_list = [];
if ($result_surveys && $result_surveys->num_rows > 0) {
    while ($row = $result_surveys->fetch_assoc()) {
        $surveys_list[] = $row;
    }
}
?>

<style>
/* --- DESAIN TAMPILAN MODERN --- */
body {
    background-color: #e2e8f0;
}
.main-content {
    padding: 2rem;
}
.card {
    background-color: #ffffff;
    padding: 2.5rem;
    border-radius: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
.form-group {
    margin-bottom: 1.5rem;
}
label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #4a5568;
}
.form-input, .form-select, .select-search-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    background-color: #f7fafc;
    transition: all 0.2s ease-in-out;
}
.form-input:focus, .form-select:focus, .select-search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}
.select-search-container {
    position: relative;
}
.select-search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 20;
    background-color: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    display: none;
}
.select-search-dropdown-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
}
.select-search-dropdown-item:hover {
    background-color: #eef2ff;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}
/* Tambahan CSS untuk layout bulan dan tahun dalam satu baris */
.bulan-tahun-input {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}
.bulan-tahun-input > div {
    flex-grow: 1;
}
.alert {
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
    display: flex;
    gap: 1rem;
}
.alert-danger {
    background-color: #fee2e2;
    color: #991b1b;
}
.alert-warning {
    background-color: #fef3c7;
    color: #92400e;
}
.btn-primary, .btn-secondary {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
    border: none;
}
.btn-primary {
    background-color: #3b82f6;
    color: #ffffff;
}
.btn-primary:hover {
    background-color: #2563eb;
}
.btn-secondary {
    background-color: #e5e7eb;
    color: #4b5563;
}
.btn-secondary:hover {
    background-color: #d1d5db;
}
.btn-group {
    margin-top: 1.5rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}
.btn-add-mitra {
    background-color: #28a745;
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: background-color 0.2s;
    cursor: pointer;
    border: none;
}
.btn-add-mitra:hover {
    background-color: #218838;
}
.mitra-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}
.mitra-input-group .input-wrapper-mitra {
    flex-grow: 1;
}
.mitra-input-group .input-wrapper-jumlah {
    width: 300px;
    flex-shrink: 0;
}
.honor-status-message {
    font-size: 0.875rem;
    margin-top: 0.5rem;
    font-weight: 500;
    color: #dc2626; /* red-600 */
}
</style>

<div class="main-content">
    <div class="card">
        <h3>Tambah Kegiatan Baru</h3>
        <p class="text-sm text-gray-500">Isi formulir di bawah ini untuk menambahkan kegiatan baru.</p>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'error') : ?>
            <div class="alert alert-danger">
                <strong>Error!</strong> <?= htmlspecialchars(str_replace('_',' ', $_GET['message'])); ?>
            </div>
        <?php endif; ?>

        <form action="../proses/proses_tambah_kegiatan.php" method="POST" id="kegiatan-form">
            <div class="form-group select-search-container">
                <label for="survey-search-input">Jenis Survei</label>
                <input type="text" id="survey-search-input" class="select-search-input" placeholder="Cari Jenis Survei..." autocomplete="off" required>
                <input type="hidden" name="survei_id" id="survei_id">
                <div id="survey-dropdown" class="select-search-dropdown">
                    <?php foreach ($surveys_list as $survey) : ?>
                        <div class="select-search-dropdown-item" data-id="<?= htmlspecialchars($survey['id']) ?>" data-name="<?= htmlspecialchars($survey['nama_survei']) ?>" data-abbr="<?= htmlspecialchars($survey['singkatan_survei']) ?>" data-satuan="<?= htmlspecialchars($survey['satuan']) ?>">
                            <?= htmlspecialchars($survey['nama_survei']) ?> (<?= htmlspecialchars($survey['singkatan_survei']) ?>)
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="satuan_input">Nama Satuan Survei</label>
                <input type="text" id="satuan_input" name="satuan" class="form-input" readonly>
            </div>

            <div class="form-group">
                <label for="periode_nilai_main">Periode Survei</label>
                <select name="periode_nilai_main" id="periode_nilai_main" class="form-select" required>
                    <option value="">-- Pilih Periode --</option>
                    <option value="Tahunan">Tahunan</option>
                    <option value="4 Bulanan">Subround</option>
                    <option value="Triwulan">Triwulan</option>
                    <option value="Bulanan">Bulanan</option>
                </select>
            </div>

            <div id="dynamic_periode_input" class="mb-6" style="display:none;"></div>

            <div class="form-group">
                <label>Bulan dan Tahun Pembayaran</label>
                <div class="bulan-tahun-input">
                    <div class="input-wrapper-bulan">
                        <select name="bulan_pembayaran" id="bulan_pembayaran" class="form-select" required>
                            <option value="">-- Pilih Bulan --</option>
                            <option value="01">Januari</option>
                            <option value="02">Februari</option>
                            <option value="03">Maret</option>
                            <option value="04">April</option>
                            <option value="05">Mei</option>
                            <option value="06">Juni</option>
                            <option value="07">Juli</option>
                            <option value="08">Agustus</option>
                            <option value="09">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>
                    <div class="input-wrapper-tahun">
                        <input type="number" id="tahun_pembayaran" name="tahun_pembayaran" class="form-input" placeholder="Tahun" value="<?= date('Y') ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="harga_per_satuan">Harga Honor per Satuan Survei</label>
                <input type="number" id="harga_per_satuan" name="harga_per_satuan" class="form-input" placeholder="Misal: 500000" required>
            </div>

            <div class="form-group">
                <label class="form-label">Nama Mitra dan Jumlah Satuan yang diikuti mitra</label>
                <div id="mitra-container">
                    <div class="mitra-input-group mb-2" data-id="0">
                        <div class="select-search-container input-wrapper-mitra">
                            <input type="text" id="mitra-search-input-0" class="select-search-input" placeholder="Cari Nama Mitra..." autocomplete="off" required>
                            <input type="hidden" name="mitra_id[]" id="mitra_id-0">
                            <div id="mitra-dropdown-0" class="select-search-dropdown">
                                <?php foreach ($mitra_list as $mitra) : ?>
                                    <div class="select-search-dropdown-item" data-id="<?= htmlspecialchars($mitra['id']) ?>" data-name="<?= htmlspecialchars($mitra['nama_lengkap']) ?>">
                                        <?= htmlspecialchars($mitra['nama_lengkap']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="input-wrapper-jumlah">
                            <input type="number" name="jumlah_satuan[]" class="form-input jumlah-satuan-input" placeholder="Jumlah Satuan" required>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeMitraInput(this)">-</button>
                    </div>
                </div>
                <button type="button" class="btn-add-mitra mt-2" onclick="addMitraInput()">+ Tambah Mitra</button>
            </div>

            <input type="hidden" name="periode_jenis" id="periode_jenis">
            <input type="hidden" name="periode_nilai" id="periode_nilai">

            <div class="btn-group">
                <button type="button" onclick="window.location.href='kegiatan.php'" class="btn-secondary">Batal</button>
                <button type="submit" class="btn-primary">Simpan Kegiatan</button>
            </div>
        </form>
    </div>
</div>

<script>
let mitraCounter = 1;
let honorStatus = {}; // Objek untuk menyimpan status honor setiap mitra

// Helper untuk setup select-search dan validasi honor
function setupSelectSearch(input, dropdown, hidden, isMitra) {
    const items = dropdown.querySelectorAll('.select-search-dropdown-item');

    input.addEventListener('focus', () => { dropdown.style.display = 'block'; });
    input.addEventListener('blur', () => { setTimeout(()=> dropdown.style.display='none', 150); });

    input.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        items.forEach(it => {
            const txt = (it.getAttribute('data-name') || '').toLowerCase();
            const ab = (it.getAttribute('data-abbr') || '').toLowerCase();
            if (txt.includes(q) || ab.includes(q)) {
                it.style.display = 'block';
            } else { it.style.display = 'none'; }
        });
    });

    items.forEach(item => {
        item.addEventListener('mousedown', function(e) {
            e.preventDefault();
            const selectedId = item.getAttribute('data-id');
            const selectedName = item.getAttribute('data-name');
            const selectedAbbr = item.getAttribute('data-abbr');
            const selectedSatuan = item.getAttribute('data-satuan');
            const parentGroup = input.closest('.mitra-input-group');
            const oldMitraId = hidden.value;

            if (isMitra) {
                // Hapus status honor lama jika mitra diganti
                if (oldMitraId && oldMitraId !== selectedId) {
                    delete honorStatus[oldMitraId];
                }
                
                const allMitraIds = Array.from(document.querySelectorAll('input[name="mitra_id[]"]')).map(el => el.value);
                if (allMitraIds.includes(selectedId)) {
                    alert('Nama mitra ini sudah dipilih. Silakan pilih nama mitra yang berbeda.');
                    return;
                }
            }

            input.value = selectedName + (selectedAbbr ? ' ('+selectedAbbr+')' : '');
            hidden.value = selectedId;
            dropdown.style.display = 'none';

            if (!isMitra && selectedSatuan) {
                document.getElementById('satuan_input').value = selectedSatuan;
            }

            if (isMitra && parentGroup) {
                // Tambahkan event listener untuk jumlah satuan di grup ini
                const jumlahInput = parentGroup.querySelector('.jumlah-satuan-input');
                if (jumlahInput) {
                    jumlahInput.addEventListener('input', () => validateHonor(parentGroup));
                }
                validateHonor(parentGroup);
            }
        });
    });
}

// Fungsi untuk validasi honor via AJAX
function validateHonor(mitraGroupElement) {
    const mitraId = mitraGroupElement.querySelector('input[name="mitra_id[]"]').value;
    const bulanPembayaran = document.getElementById('bulan_pembayaran').value;
    const tahunPembayaran = document.getElementById('tahun_pembayaran').value;
    const honorPerSatuan = document.getElementById('harga_per_satuan').value;
    const jumlahSatuan = mitraGroupElement.querySelector('.jumlah-satuan-input').value;

    if (!mitraId || !bulanPembayaran || !tahunPembayaran || !honorPerSatuan || !jumlahSatuan) {
        return;
    }

    const currentTotalHonor = parseInt(honorPerSatuan) * parseInt(jumlahSatuan);

    const xhr = new XMLHttpRequest();
    // Path AJAX yang disesuaikan
    xhr.open("GET", `./check_honor_limit.php?mitra_id=${mitraId}&bulan=${bulanPembayaran}&tahun=${tahunPembayaran}&current_honor=${currentTotalHonor}`, true);
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                let statusMessage = mitraGroupElement.querySelector('.honor-status-message');
                if (!statusMessage) {
                    statusMessage = document.createElement('div');
                    statusMessage.className = 'honor-status-message';
                    mitraGroupElement.appendChild(statusMessage);
                }

                if (!response.is_within_limit) {
                    honorStatus[mitraId] = false;
                    statusMessage.innerHTML = `⚠️ kurangi jumlah satuan atau ganti mitra, mitra ini akan melebihi batas (Rp ${numberWithCommas(response.total_honor)})`;
                } else {
                    honorStatus[mitraId] = true;
                    statusMessage.innerHTML = ''; // Clear message if within limit
                }
            } catch (e) {
                console.error("Gagal parse JSON response:", e);
            }
        }
    };
    xhr.send();
}

function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Fungsi untuk menambahkan input mitra baru
function addMitraInput() {
    const container = document.getElementById('mitra-container');
    const newMitraGroup = document.createElement('div');
    newMitraGroup.className = 'mitra-input-group mb-2';
    newMitraGroup.dataset.id = mitraCounter;
    newMitraGroup.innerHTML = `
        <div class="select-search-container input-wrapper-mitra">
            <input type="text" id="mitra-search-input-${mitraCounter}" class="select-search-input" placeholder="Cari Nama Mitra..." autocomplete="off" required>
            <input type="hidden" name="mitra_id[]" id="mitra_id-${mitraCounter}">
            <div id="mitra-dropdown-${mitraCounter}" class="select-search-dropdown">
                <?php foreach ($mitra_list as $mitra) : ?>
                    <div class="select-search-dropdown-item" data-id="<?= htmlspecialchars($mitra['id']) ?>" data-name="<?= htmlspecialchars($mitra['nama_lengkap']) ?>">
                        <?= htmlspecialchars($mitra['nama_lengkap']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="input-wrapper-jumlah">
            <input type="number" name="jumlah_satuan[]" class="form-input jumlah-satuan-input" placeholder="Jumlah Satuan" required>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeMitraInput(this)">-</button>
    `;
    container.appendChild(newMitraGroup);
    
    const input = document.getElementById(`mitra-search-input-${mitraCounter}`);
    const dropdown = document.getElementById(`mitra-dropdown-${mitraCounter}`);
    const hidden = document.getElementById(`mitra_id-${mitraCounter}`);
    setupSelectSearch(input, dropdown, hidden, true);

    // Tambahkan event listener untuk input jumlah satuan baru
    newMitraGroup.querySelector('.jumlah-satuan-input').addEventListener('input', function() {
        validateHonor(newMitraGroup);
    });
    
    mitraCounter++;
}

// Fungsi untuk menghapus input mitra
function removeMitraInput(button) {
    const container = button.closest('.mitra-input-group');
    const hiddenInput = container.querySelector('input[type="hidden"]');
    const mitraId = hiddenInput.value;
    
    if (mitraId) {
        delete honorStatus[mitraId];
    }
    container.remove();
}

document.addEventListener('DOMContentLoaded', function() {
    setupSelectSearch(document.getElementById('survey-search-input'), document.getElementById('survey-dropdown'), document.getElementById('survei_id'), false);

    const initialMitraGroup = document.getElementById('mitra-container').querySelector('.mitra-input-group');
    const initialMitraSearchInput = initialMitraGroup.querySelector('.select-search-input');
    const initialMitraDropdown = initialMitraGroup.querySelector('.select-search-dropdown');
    const initialMitraHidden = initialMitraGroup.querySelector('input[type="hidden"]');
    setupSelectSearch(initialMitraSearchInput, initialMitraDropdown, initialMitraHidden, true);

    // Event listener untuk memicu validasi saat bulan, tahun, atau harga honor berubah
    const paymentFields = document.querySelectorAll('#bulan_pembayaran, #tahun_pembayaran, #harga_per_satuan');
    paymentFields.forEach(field => {
        field.addEventListener('change', () => {
            document.querySelectorAll('.mitra-input-group').forEach(group => {
                validateHonor(group);
            });
        });
    });

    // Event listener untuk input jumlah satuan yang sudah ada sejak awal
    initialMitraGroup.querySelector('.jumlah-satuan-input').addEventListener('input', function() {
        validateHonor(this.closest('.mitra-input-group'));
    });

    // Dinamis periode
    const periodeSelect = document.getElementById('periode_nilai_main');
    const dynamicInputDiv = document.getElementById('dynamic_periode_input');

    function renderDynamic(selected) {
        dynamicInputDiv.innerHTML = '';
        dynamicInputDiv.style.display = selected ? 'block' : 'none';
        if (!selected) return;

        let dynamicHtml = '';
        if (selected === 'Tahunan') {
            dynamicHtml = `<div class="form-group"><label for="tahun">Tahun</label><input type="number" id="tahun" name="tahun" class="form-input" placeholder="2025" required></div>`;
        } else if (selected === '4 Bulanan') {
            dynamicHtml = `<div class="form-group"><label for="four_month">4-Bulanan (pilih group)</label><select id="four_month" name="four_month" class="form-select" required><option value="">-- Pilih --</option><option value="1">Subround I </option><option value="2">Subround II</option><option value="3">Subround III</option></select></div><div class="form-group"><label for="tahun_4b">Tahun</label><input type="number" id="tahun_4b" name="tahun_4b" class="form-input" placeholder="2025" required></div>`;
        } else if (selected === 'Triwulan') {
            dynamicHtml = `<div class="form-group"><label for="triwulan">Triwulan</label><select id="triwulan" name="triwulan" class="form-select" required><option value="">-- Pilih Triwulan --</option><option value="1">Triwulan I</option><option value="2">Triwulan II</option><option value="3">Triwulan III</option><option value="4">Triwulan IV</option></select></div><div class="form-group"><label for="tahun_trw">Tahun</label><input type="number" id="tahun_trw" name="tahun_trw" class="form-input" placeholder="2025" required></div>`;
        } else if (selected === 'Bulanan') {
            dynamicHtml = `<div class="form-group"><label for="bulan_bulanan">Bulan</label><select id="bulan_bulanan" name="bulan_bulanan" class="form-select" required><option value="">-- Pilih Bulan --</option><option value="Januari">Januari</option><option value="Februari">Februari</option><option value="Maret">Maret</option><option value="April">April</option><option value="Mei">Mei</option><option value="Juni">Juni</option><option value="Juli">Juli</option><option value="Agustus">Agustus</option><option value="September">September</option><option value="Oktober">Oktober</option><option value="November">November</option><option value="Desember">Desember</option></select></div><div class="form-group"><label for="tahun_bln">Tahun</label><input type="number" id="tahun_bln" name="tahun_bln" class="form-input" placeholder="2025" required></div>`;
        }
        dynamicInputDiv.innerHTML = dynamicHtml;
    }

    periodeSelect.addEventListener('change', function() {
        renderDynamic(this.value);
    });

    document.getElementById('kegiatan-form').addEventListener('submit', function(e) {
        // Cek apakah ada honor yang melebihi batas sebelum submit
        const hasOverLimit = Object.values(honorStatus).some(status => status === false);
        if (hasOverLimit) {
            e.preventDefault();
            alert('Ada honor mitra yang melebihi batas. Silakan periksa kembali data Anda.');
            return;
        }

        const surveiId = document.getElementById('survei_id').value;
        const hargaPerSatuan = document.getElementById('harga_per_satuan').value;
        const bulanPembayaran = document.getElementById('bulan_pembayaran').value;
        const tahunPembayaran = document.getElementById('tahun_pembayaran').value;
        const periodeMain = document.getElementById('periode_nilai_main').value;
        
        const mitraInputs = document.querySelectorAll('input[name="mitra_id[]"]');
        const jumlahSatuanInputs = document.querySelectorAll('input[name="jumlah_satuan[]"]');
        
        let isFormValid = true;

        if (!surveiId || !hargaPerSatuan || !bulanPembayaran || !tahunPembayaran || !periodeMain) {
            isFormValid = false;
        }

        if (mitraInputs.length === 0) {
            isFormValid = false;
        } else {
            mitraInputs.forEach((input, index) => {
                if (!input.value || !jumlahSatuanInputs[index].value || jumlahSatuanInputs[index].value <= 0) {
                    isFormValid = false;
                }
            });
        }
        
        if (!isFormValid) {
            e.preventDefault();
            alert('Harap lengkapi semua data wajib pada formulir.');
            return;
        }

        // build periode_jenis & periode_nilai
        document.getElementById('periode_jenis').value = periodeMain;
        let nilai = '';
        switch (periodeMain) {
            case 'Tahunan':
                nilai = document.getElementById('tahun') ? document.getElementById('tahun').value : '';
                break;
            case '4 Bulanan':
                const four = document.getElementById('four_month') ? document.getElementById('four_month').value : '';
                const tahun4 = document.getElementById('tahun_4b') ? document.getElementById('tahun_4b').value : '';
                if (four && tahun4) nilai = `4B-${four} / ${tahun4}`;
                break;
            case 'Triwulan':
                const tr = document.getElementById('triwulan') ? document.getElementById('triwulan').value : '';
                const tahun_tr = document.getElementById('tahun_trw') ? document.getElementById('tahun_trw').value : '';
                if (tr && tahun_tr) nilai = `Q${tr} / ${tahun_tr}`;
                break;
            case 'Bulanan':
                const bl = document.getElementById('bulan_bulanan') ? document.getElementById('bulan_bulanan').value : '';
                const tahun_b = document.getElementById('tahun_bln') ? document.getElementById('tahun_bln').value : '';
                if (bl && tahun_b) nilai = `${bl} / ${tahun_b}`;
                break;
        }

        if (!nilai) {
            e.preventDefault();
            alert('Harap lengkapi detail Periode yang dipilih.');
            return;
        }
        document.getElementById('periode_nilai').value = nilai;
    });
});
</script>

<?php
if ($result_mitra instanceof mysqli_result) { $result_mitra->free(); }
if ($result_surveys instanceof mysqli_result) { $result_surveys->free(); }

include '../includes/footer.php';
?>