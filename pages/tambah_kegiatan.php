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

// Ambil daftar tahun anggaran unik dari master_item
$sql_tahun_item = "SELECT DISTINCT tahun FROM master_item ORDER BY tahun DESC";
$result_tahun_item = $koneksi->query($sql_tahun_item);
$tahun_list = [];
if ($result_tahun_item && $result_tahun_item->num_rows > 0) {
    while ($row = $result_tahun_item->fetch_assoc()) {
        $tahun_list[] = $row['tahun'];
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
.small-muted {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 0.25rem;
}
</style>

<div class="main-content">
    <div class="card">
        <h3>Tambah Kegiatan Baru</h3>
        <p class="text-sm text-gray-500">Pilih Item anggaran (berdasarkan tahun), lalu isi mitra & jumlah.</p>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'error') : ?>
            <div class="alert alert-danger">
                <strong>Error!</strong> <?= htmlspecialchars(str_replace('_',' ', $_GET['message'])); ?>
            </div>
        <?php endif; ?>

        <form action="../proses/proses_tambah_kegiatan.php" method="POST" id="kegiatan-form">
            <!-- Pilih Tahun Anggaran (filter utama) -->
            
    <div class="grid">
        <div class="form-group">
            <label for="tahun_anggaran">Tahun Anggaran</label>
            <select id="tahun_anggaran" name="tahun_anggaran" class="form-select" required>
                <option value="">-- Pilih Tahun --</option>
                <?php
                $tahun_list = $koneksi->query("SELECT DISTINCT tahun FROM master_program ORDER BY tahun DESC");
                while ($th = $tahun_list->fetch_assoc()) {
                    echo "<option value='{$th['tahun']}'>{$th['tahun']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="program_id">Program</label>
            <select id="program_id" name="program_id" class="form-select" disabled required>
                <option value="">-- Pilih Program --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="kegiatan_id">Kegiatan</label>
            <select id="kegiatan_id" name="kegiatan_id" class="form-select" disabled required>
                <option value="">-- Pilih Kegiatan --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="output_id">Output</label>
            <select id="output_id" name="output_id" class="form-select" disabled required>
                <option value="">-- Pilih Output --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="sub_output_id">Sub Output</label>
            <select id="sub_output_id" name="sub_output_id" class="form-select" disabled required>
                <option value="">-- Pilih Sub Output --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="komponen_id">Komponen</label>
            <select id="komponen_id" name="komponen_id" class="form-select" disabled required>
                <option value="">-- Pilih Komponen --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="sub_komponen_id">Sub Komponen</label>
            <select id="sub_komponen_id" name="sub_komponen_id" class="form-select" disabled required>
                <option value="">-- Pilih Sub Komponen --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="akun_id">Akun</label>
            <select id="akun_id" name="akun_id" class="form-select" disabled required>
                <option value="">-- Pilih Akun --</option>
            </select>
        </div>

        <div class="form-group">
            <label for="item_id">Item</label>
            <select id="item_id" name="item_id" class="form-select" disabled required>
                <option value="">-- Pilih Item --</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="harga_per_satuan">Harga Honor (Otomatis dari Item)</label>
        <input type="number" id="harga_per_satuan" name="harga_per_satuan" class="form-input" readonly required>
    </div>



            <div class="form-group">
                <label for="satuan_item">Satuan</label>
                <input type="text" id="satuan_item" class="form-input" readonly placeholder="Satuan dari master_item">
            </div>


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
                <div class="small-muted">Per mitra akan divalidasi agar tidak melebihi batas honor Rp 2.500.000 / bulan.</div>
            </div>


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

// === Setup select-search untuk mitra (reuse dari kode lama) ===
function setupSelectSearch(input, dropdown, hidden, isMitra) {
    const items = dropdown.querySelectorAll('.select-search-dropdown-item');

    input.addEventListener('focus', () => { dropdown.style.display = 'block'; });
    input.addEventListener('blur', () => { setTimeout(()=> dropdown.style.display='none', 150); });

    input.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        items.forEach(it => {
            const txt = (it.getAttribute('data-name') || '').toLowerCase();
            if (txt.includes(q)) {
                it.style.display = 'block';
            } else { it.style.display = 'none'; }
        });
    });

    items.forEach(item => {
        item.addEventListener('mousedown', function(e) {
            e.preventDefault();
            const selectedId = item.getAttribute('data-id');
            const selectedName = item.getAttribute('data-name');
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

            input.value = selectedName;
            hidden.value = selectedId;
            dropdown.style.display = 'none';

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



function validateHonor(parentGroup) {
    const mitraId = parentGroup.querySelector('[name="mitra_id[]"]').value;
    const jumlahSatuan = parentGroup.querySelector('[name="jumlah_satuan[]"]').value;
    const bulanPembayaran = document.getElementById('bulan_pembayaran').value;
    const tahunPembayaran = document.getElementById('tahun_pembayaran').value;

    // Ambil harga per satuan langsung dari input utama form
    const hargaPerSatuan = parseFloat(document.getElementById('harga_per_satuan').value) || 0;

    // Pastikan semua field terisi sebelum lanjut
    if (!mitraId || !jumlahSatuan || !bulanPembayaran || !tahunPembayaran || hargaPerSatuan === 0) {
        return;
    }

    // Hitung total honor dari harga × jumlah
    const totalHonor = hargaPerSatuan * parseInt(jumlahSatuan);

    // Kirim ke backend untuk validasi batas honor
    fetch('check_honor_limit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            mitraId: mitraId,
            currentTotalHonor: totalHonor,
            bulanPembayaran: bulanPembayaran,
            tahunPembayaran: tahunPembayaran
        })
    })
    .then(res => res.json())
    .then(data => {
        let statusElement = parentGroup.querySelector('.honor-status');
        if (!statusElement) {
            statusElement = document.createElement('span');
            statusElement.classList.add('honor-status');
            parentGroup.appendChild(statusElement);
        }

        if (data.exceeds) {
            alert(`Honor untuk mitra melebihi batas honor untuk bulan ini, mohon untuk ganti mitra atau kurangi jumlah satuan!`);
            statusElement.textContent = "⚠️ Melebihi Batas";
            statusElement.style.color = "red";
            honorStatus[mitraId] = false;
        } else {
            statusElement.textContent = "✅ Honor Aman";
            statusElement.style.color = "green";
            honorStatus[mitraId] = true;
        }
    });
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

// ======= CASCADE DROPDOWN (tahun -> program -> kegiatan -> ... -> item) =======
// Utility untuk reset descendant selects
function resetSelect(selectEl, placeholderText) {
    selectEl.innerHTML = `<option value="">-- ${placeholderText} --</option>`;
    selectEl.disabled = true;
}

// Fetch helper (returns JSON)
function fetchJson(url) {
    return fetch(url).then(r => {
        if (!r.ok) throw new Error('Network response not ok');
        return r.json();
    });
}

document.addEventListener('DOMContentLoaded', function() {
        const tahunSelect = document.getElementById('tahun_anggaran');
        const programSelect = document.getElementById('program_id');
        const kegiatanSelect = document.getElementById('kegiatan_id');
        const outputSelect = document.getElementById('output_id');
        const subOutputSelect = document.getElementById('sub_output_id');
        const komponenSelect = document.getElementById('komponen_id');
        const subKomponenSelect = document.getElementById('sub_komponen_id');
        const akunSelect = document.getElementById('akun_id');
        const itemSelect = document.getElementById('item_id');
        const hargaInput = document.getElementById('harga_per_satuan');
        const firstInput = document.getElementById('mitra-search-input-0');
    const firstDropdown = document.getElementById('mitra-dropdown-0');
    const firstHidden = document.getElementById('mitra_id-0');
    setupSelectSearch(firstInput, firstDropdown, firstHidden, true);

        tahunSelect.addEventListener('change', () => {
            if (tahunSelect.value) {
                fetch(`get_data.php?type=program&tahun=${tahunSelect.value}`)
                    .then(res => res.json())
                    .then(data => {
                        programSelect.innerHTML = '<option value="">-- Pilih Program --</option>';
                        data.forEach(p => {
                            programSelect.innerHTML += `<option value="${p.id}">${p.kode} - ${p.nama}</option>`;
                        });
                        programSelect.disabled = false;
                    });
            }
        });

        programSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=kegiatan&program_id=${programSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
                    data.forEach(d => {
                        kegiatanSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                    });
                    kegiatanSelect.disabled = false;
                });
        });

        kegiatanSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=output&kegiatan_id=${kegiatanSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    outputSelect.innerHTML = '<option value="">-- Pilih Output --</option>';
                    data.forEach(d => {
                        outputSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                    });
                    outputSelect.disabled = false;
                });
        });

        outputSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=sub_output&output_id=${outputSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    subOutputSelect.innerHTML = '<option value="">-- Pilih Sub Output --</option>';
                    data.forEach(d => {
                        subOutputSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                    });
                    subOutputSelect.disabled = false;
                });
        });

        subOutputSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=komponen&sub_output_id=${subOutputSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    komponenSelect.innerHTML = '<option value="">-- Pilih Komponen --</option>';
                    data.forEach(d => {
                        komponenSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                    });
                    komponenSelect.disabled = false;
                });
        });

        komponenSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=sub_komponen&komponen_id=${komponenSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    subKomponenSelect.innerHTML = '<option value="">-- Pilih Sub Komponen --</option>';
                    data.forEach(d => {
                        subKomponenSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                    });
                    subKomponenSelect.disabled = false;
                });
        });

        subKomponenSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=akun&sub_komponen_id=${subKomponenSelect.value}`)
                .then(res => res.json())
                .then(data => {
                    akunSelect.innerHTML = '<option value="">-- Pilih Akun --</option>';
                    data.forEach(d => {
                        akunSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                    });
                    akunSelect.disabled = false;
                });
        });

akunSelect.addEventListener('change', () => {
    fetch(`get_data.php?type=item&akun_id=${akunSelect.value}`)
        .then(res => res.json())
        .then(data => {
            itemSelect.innerHTML = '<option value="">-- Pilih Item --</option>';
            data.forEach(d => {
                itemSelect.innerHTML += `<option value="${d.kode_unik}" data-harga="${d.harga}" data-satuan="${d.satuan}">${d.nama_item} (${d.satuan})</option>`;
            });
            itemSelect.disabled = false;
        });
});


                itemSelect.addEventListener('change', () => {
            const selected = itemSelect.options[itemSelect.selectedIndex];
            hargaInput.value = selected.getAttribute('data-harga') || '';
            document.getElementById('satuan_item').value = selected.getAttribute('data-satuan') || '';
        });

    });
</script>

<?php
if ($result_mitra instanceof mysqli_result) { $result_mitra->free(); }
if ($result_tahun_item instanceof mysqli_result) { $result_tahun_item->free(); }

include '../includes/footer.php';
?>
