<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. Ambil daftar mitra (UNTUK MODE MANUAL)
$sql_mitra = "SELECT id, nama_lengkap FROM mitra ORDER BY nama_lengkap ASC";
$result_mitra = $koneksi->query($sql_mitra);
$mitra_list = [];
if ($result_mitra && $result_mitra->num_rows > 0) {
    while ($row = $result_mitra->fetch_assoc()) {
        $mitra_list[] = $row;
    }
}

// 2. Ambil daftar TIM (Sumber utama kelompok mitra)
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim && $result_tim->num_rows > 0) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}
?>

<style>
    /* --- DESAIN TAMPILAN --- */
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
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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

    .form-input,
    .form-select,
    .select-search-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        background-color: #f7fafc;
        transition: all 0.2s;
    }

    .form-input:disabled,
    .form-select:disabled,
    .form-input[disabled] {
        background-color: #e9ecef;
        opacity: 0.7;
        cursor: not-allowed;
    }

    .form-input:focus,
    .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }

    /* Searchable Dropdown */
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
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: none;
    }

    .select-search-dropdown-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
    }

    .select-search-dropdown-item:hover {
        background-color: #eef2ff;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .bulan-tahun-input {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
    }

    .bulan-tahun-input>div {
        flex-grow: 1;
    }

    /* Buttons */
    .btn-group {
        margin-top: 1.5rem;
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }

    .btn-primary,
    .btn-secondary,
    .btn-add-mitra,
    .btn-danger {
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: bold;
        cursor: pointer;
        border: none;
        transition: background 0.3s;
    }

    .btn-primary {
        background-color: #3b82f6;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #2563eb;
    }

    .btn-secondary {
        background-color: #e5e7eb;
        color: #4b5563;
    }

    .btn-add-mitra {
        background-color: #28a745;
        color: #fff;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .btn-danger {
        background-color: #dc3545;
        color: #fff;
        padding: 0.5rem 0.8rem;
    }

    /* Mitra Input Group */
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

    .honor-status {
        font-size: 0.875rem;
        margin-top: 0.5rem;
        font-weight: 500;
        color: #dc2626;
    }

    .small-muted {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }

    /* Checkbox Custom */
    .checkbox-container {
        display: flex;
        align-items: center;
        position: relative;
        padding-left: 30px;
        cursor: pointer;
        font-size: 1rem;
        user-select: none;
    }

    .checkbox-container input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        height: 0;
        width: 0;
    }

    .checkbox-container .checkmark {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        height: 20px;
        width: 20px;
        background-color: #f0f0f0;
        border: 2px solid #cbd5e0;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .checkbox-container:hover .checkmark {
        border-color: #6366f1;
    }

    .checkbox-container input:checked~.checkmark {
        background-color: #6366f1;
        border-color: #6366f1;
    }

    .checkbox-container .checkmark:after {
        content: "";
        position: absolute;
        display: none;
        left: 6px;
        top: 2px;
        width: 5px;
        height: 10px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }

    .checkbox-container input:checked~.checkmark:after {
        display: block;
    }

    /* Mode Otomatis Styles */
    #mitra-otomatis-list {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        background-color: #f8fafc;
        max-height: 400px;
        overflow-y: auto;
        padding: 1rem;
    }

    .mitra-otomatis-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem;
        border-bottom: 1px solid #e2e8f0;
        transition: all 0.3s;
    }

    .mitra-otomatis-item:last-child {
        border-bottom: none;
    }

    .mitra-otomatis-item span {
        font-weight: 500;
        color: #334155;
        flex-grow: 1;
    }

    .mitra-otomatis-jumlah {
        width: 120px;
        flex-shrink: 0;
        margin-left: 1rem;
    }

    .mitra-otomatis-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        background-color: #f7fafc;
        text-align: center;
    }

    .mitra-otomatis-item .honor-status {
        font-size: 0.8rem;
        font-weight: bold;
        margin-left: 1rem;
        flex-shrink: 0;
        width: 110px;
        text-align: right;
    }

    /* --- BARU: Style untuk Mitra yang Di-Soft Delete --- */
    .mitra-otomatis-item.deleted {
        background-color: #ffe4e6;
        /* Merah muda */
        opacity: 0.6;
    }

    .mitra-otomatis-item.deleted span {
        text-decoration: line-through;
        color: #991b1b;
    }

    /* Tombol Hapus Biasa */
    .btn-action-item {
        background-color: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
        border-radius: 4px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        margin-left: 10px;
        transition: all 0.2s;
    }

    .btn-action-item:hover {
        background-color: #dc2626;
        color: white;
    }

    /* Tombol Restore (Undo) */
    .btn-action-item.restore {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .btn-action-item.restore:hover {
        background-color: #166534;
        color: white;
    }

    /* Search Filter */
    .search-filter-box {
        margin-bottom: 10px;
        position: relative;
    }

    .search-filter-input {
        width: 100%;
        padding: 0.5rem 0.5rem 0.5rem 2.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        font-size: 0.9rem;
    }

    .search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    /* --- STYLE BARU: LAYOUT 2 KOLOM --- */
    .date-grid-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        /* Bagi 2 kolom sama besar */
        gap: 2rem;
        margin-bottom: 1.5rem;
        background-color: #f8fafc;
        /* Warna latar sedikit beda biar fokus */
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 1.5rem;
    }

    .date-column {
        display: flex;
        flex-direction: column;
    }

    .date-header {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #cbd5e1;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* STYLE CHECKBOX GRID (Agar rapi 3 kolom per baris) */
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        background: white;
        padding: 6px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        font-size: 0.85rem;
    }

    .checkbox-item:hover {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }

    .checkbox-item input {
        margin-right: 8px;
        cursor: pointer;
    }
</style>

<div class="main-content">
    <div class="card">
        <h3>Tambah Kegiatan Baru</h3>
        <p class="text-sm text-gray-500">Isi detail kegiatan, pilih sumber mitra (Manual/Tim).</p>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'error') : ?>
            <div class="alert alert-danger" style="background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <strong>Error!</strong> <?= htmlspecialchars(str_replace('_', ' ', $_GET['message'])); ?>
            </div>
        <?php endif; ?>

        <form action="../proses/proses_tambah_kegiatan.php" method="POST" id="kegiatan-form">

            <div class="grid">
                <input type="hidden" id="tim_id_hidden" name="tim_id">

                <div class="form-group">
                    <label for="tim_id">Pilih Tim / Kelompok Mitra</label>
                    <select id="tim_id" name="tim_id_display" class="form-select" required>
                        <option value="">-- Pilih Tim --</option>
                        <?php foreach ($tim_list as $tim) : ?>
                            <option value="<?= htmlspecialchars($tim['id']) ?>">
                                <?= htmlspecialchars($tim['nama_tim']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>


                </div>







                <div class="form-group">
                    <label for="tahun_anggaran">Tahun Anggaran</label>
                    <select id="tahun_anggaran" name="tahun_anggaran" class="form-select" required>
                        <option value="">-- Pilih Tahun --</option>
                        <?php
                        $tahun_list_db = $koneksi->query("SELECT DISTINCT tahun FROM master_program ORDER BY tahun DESC");
                        while ($th = $tahun_list_db->fetch_assoc()) {
                            echo "<option value='{$th['tahun']}'>{$th['tahun']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group"><label>Program</label><select id="program_id" name="program_id" class="form-select" disabled required>
                        <option value="">-- Pilih Program --</option>
                    </select></div>
                <div class="form-group"><label>Kegiatan</label><select id="kegiatan_id" name="kegiatan_id" class="form-select" disabled required>
                        <option value="">-- Pilih Kegiatan --</option>
                    </select></div>
                <div class="form-group"><label>Output</label><select id="output_id" name="output_id" class="form-select" disabled required>
                        <option value="">-- Pilih Output --</option>
                    </select></div>
                <div class="form-group"><label>Sub Output</label><select id="sub_output_id" name="sub_output_id" class="form-select" disabled required>
                        <option value="">-- Pilih Sub Output --</option>
                    </select></div>
                <div class="form-group"><label>Komponen</label><select id="komponen_id" name="komponen_id" class="form-select" disabled required>
                        <option value="">-- Pilih Komponen --</option>
                    </select></div>
                <div class="form-group"><label>Sub Komponen</label><select id="sub_komponen_id" name="sub_komponen_id" class="form-select" disabled required>
                        <option value="">-- Pilih Sub Komponen --</option>
                    </select></div>
                <div class="form-group"><label>Akun</label><select id="akun_id" name="akun_id" class="form-select" disabled required>
                        <option value="">-- Pilih Akun --</option>
                    </select></div>
                <div class="form-group"><label>Item</label><select id="item_id" name="item_id" class="form-select" disabled required>
                        <option value="">-- Pilih Item --</option>
                    </select></div>
                <div class="form-group">
                    <label for="harga_per_satuan">Harga Honor (Otomatis dari Item)</label>
                    <input type="number" id="harga_per_satuan" name="harga_per_satuan" class="form-input" readonly required>
                    
                </div>
                <div class="form-group">
                        <label for="satuan_item">Satuan</label>
                        <input type="text" id="satuan_item" class="form-input" readonly placeholder="Satuan dari master_item">
                    </div>
            </div>





            <div class="date-grid-container">

                <div class="date-column">
                    <div class="date-header">📅 1. Waktu Pelaksanaan</div>

                    <div class="form-group">
                        <label class="small-muted">Tipe Periode:</label>
                        <select id="trigger_periode" name="periode_jenis" class="form-select" required>
                            <option value="">-- Pilih Tipe --</option>
                            <option value="bulanan">Bulanan (Multi)</option>
                            <option value="triwulan">Triwulan</option>
                            <option value="subron">Sub-Round</option>
                            <option value="tahunan">Tahunan</option>
                        </select>
                    </div>

                    <div id="periode-bulanan-wrapper" style="display:none;">
                        <label class="small-muted">Centang Bulan Pelaksanaan:</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="01"> Jan</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="02"> Feb</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="03"> Mar</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="04"> Apr</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="05"> Mei</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="06"> Jun</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="07"> Jul</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="08"> Ags</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="09"> Sep</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="10"> Okt</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="11"> Nov</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="12"> Des</label>
                        </div>
                    </div>

                    <div id="periode-triwulan-wrapper" style="display:none;">
                        <label class="small-muted">Centang Triwulan:</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_triwulan[]" value="1"> TW 1</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_triwulan[]" value="2"> TW 2</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_triwulan[]" value="3"> TW 3</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_triwulan[]" value="4"> TW 4</label>
                        </div>
                    </div>

                    <div id="periode-subron-wrapper" style="display:none;">
                        <label class="small-muted">Centang Sub-Round:</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_subron[]" value="1"> SR 1</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_subron[]" value="2"> SR 2</label>
                            <label class="checkbox-item"><input type="checkbox" name="periode_nilai_subron[]" value="3"> SR 3</label>
                        </div>
                    </div>

                    <div id="periode-tahunan-wrapper" style="display:none;">
                        <label class="small-muted">Tahun:</label>
                        <input type="number" name="periode_nilai_tahunan" class="form-input" value="<?= date('Y') ?>">
                    </div>
                </div>

                <div class="date-column">
                    <div class="date-header">💰 2. Waktu Pembayaran</div>
                    <div class="form-group">
                        <label class="small-muted">Tahun Bayar:</label>
                        <input type="number" id="tahun_pembayaran" name="tahun_pembayaran" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="small-muted">Centang Bulan Pencairan:</label>
                        <div class="checkbox-grid">
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="01"> Jan</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="02"> Feb</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="03"> Mar</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="04"> Apr</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="05"> Mei</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="06"> Jun</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="07"> Jul</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="08"> Ags</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="09"> Sep</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="10"> Okt</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="11"> Nov</label>
                            <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="12"> Des</label>
                        </div>
                    </div>

                </div>

            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="checkbox-container" style="font-weight: normal; color: #ff4800ff;">
                    <strong>Centang jika kegiatan merupakan sensus</strong>
                    <input type="checkbox" id="is_sensus" name="is_sensus" value="1">
                    <span class="checkmark"></span>
                </label>
                <p class="small-muted">Jika dicentang, batas honor tidak berlaku.</p>
                <div style="margin-top: 10px;">
                    <label class="checkbox-container" style="font-weight: normal; color: #ff4800ff;">
                        <input type="checkbox" id="toggle_otomatis">
                        <span class="checkmark"></span>
                        &nbsp; <strong>Load Anggota Tim ini secara Otomatis?</strong>
                    </label>
                </div>
                <p class="small-muted">Centang untuk mengisi daftar mitra otomatis berdasarkan tim yang dipilih.</p>
            </div>


            <div id="mitra-manual-container">
                <div class="form-group">
                    <label class="form-label">Nama Mitra dan Jumlah Satuan (Mode Manual)</label>
                    <div id="mitra-container">
                        <div class="mitra-input-group mb-2" data-id="0">
                            <div class="select-search-container input-wrapper-mitra">
                                <input type="text" id="mitra-search-input-0" class="select-search-input manual-input" placeholder="Cari Nama Mitra..." autocomplete="off" required>
                                <input type="hidden" name="mitra_id[]" id="mitra_id-0" class="manual-input">
                                <div id="mitra-dropdown-0" class="select-search-dropdown">
                                    <?php foreach ($mitra_list as $mitra) : ?>
                                        <div class="select-search-dropdown-item" data-id="<?= htmlspecialchars($mitra['id']) ?>" data-name="<?= htmlspecialchars($mitra['nama_lengkap']) ?>">
                                            <?= htmlspecialchars($mitra['nama_lengkap']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="input-wrapper-jumlah">
                                <input type="number" name="jumlah_satuan[]" class="form-input jumlah-satuan-input manual-input" placeholder="Jumlah Satuan" required min="1">
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeMitraInput(this)">-</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-mitra mt-2" onclick="addMitraInput()">+ Tambah Mitra</button>
                </div>
            </div>

            <div id="mitra-otomatis-container" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Daftar Mitra Otomatis (Jumlah Dapat Diedit)</label>

                    <div class="search-filter-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="filter-mitra-otomatis" class="search-filter-input" placeholder="Ketik nama untuk memfilter list di bawah..." autocomplete="off">
                    </div>

                    <div id="mitra-otomatis-list">
                    </div>

                    <div class="small-muted" style="margin-top: 5px; text-align: right;">
                        Total Mitra Terpilih: <span id="count-mitra-otomatis">0</span>
                    </div>
                </div>
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
    let honorStatus = {};

    // --- HELPER FUNCTIONS ---
    function setupSelectSearch(input, dropdown, hidden, isMitra) {
        const items = dropdown.querySelectorAll('.select-search-dropdown-item');
        input.addEventListener('focus', () => {
            dropdown.style.display = 'block';
        });
        input.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 150);
        });
        input.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            items.forEach(it => {
                const txt = (it.getAttribute('data-name') || '').toLowerCase();
                it.style.display = txt.includes(q) ? 'block' : 'none';
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
                    if (oldMitraId && oldMitraId !== selectedId) {
                        delete honorStatus[oldMitraId];
                    }
                    const allMitraIds = Array.from(document.querySelectorAll('input[name="mitra_id[]"]')).map(el => el.value);
                    if (allMitraIds.includes(selectedId)) {
                        alert('Nama mitra ini sudah dipilih.');
                        return;
                    }
                }
                input.value = selectedName;
                hidden.value = selectedId;
                dropdown.style.display = 'none';
                if (isMitra && parentGroup) {
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
        const mitraIdEl = parentGroup.querySelector('[name="mitra_id[]"]');
        const jumlahSatuanEl = parentGroup.querySelector('[name="jumlah_satuan[]"]');

        if (!mitraIdEl || !jumlahSatuanEl) {
            return;
        }

        // Jika item dihapus (disabled), jangan validasi
        if (mitraIdEl.disabled) return;

        const mitraId = mitraIdEl.value;
        const jumlahSatuan = jumlahSatuanEl.value;
        const bulanPembayaran = getSelectedPaymentMonth();
        const tahunPembayaran = document.getElementById('tahun_pembayaran').value;
        const hargaPerSatuan = parseFloat(document.getElementById('harga_per_satuan').value) || 0;
        const isSensus = document.getElementById('is_sensus').checked;

        let statusElement = parentGroup.querySelector('.honor-status');
        if (!statusElement) {
            statusElement = document.createElement('span');
            statusElement.classList.add('honor-status');
            parentGroup.appendChild(statusElement);
        }

        if (isSensus) {
            statusElement.textContent = "✅ Sensus";
            statusElement.style.color = "blue";
            honorStatus[mitraId] = true;
            return;
        }

        if (!mitraId || !jumlahSatuan || jumlahSatuan < 1) {
            statusElement.textContent = "";
            return;
        }
        if (!bulanPembayaran || !tahunPembayaran || hargaPerSatuan === 0) {
            statusElement.textContent = "Lengkapi Data..";
            statusElement.style.color = "#ca8a04";
            return;
        }

        const totalHonor = hargaPerSatuan * parseInt(jumlahSatuan);
        statusElement.textContent = "Cek...";
        statusElement.style.color = "#6b7280";

        fetch('check_honor_limit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    mitraId: mitraId,
                    currentTotalHonor: totalHonor,
                    bulanPembayaran: bulanPembayaran,
                    tahunPembayaran: tahunPembayaran
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.exceeds) {
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

    function getSelectedPaymentMonth() {
        const checkboxes = document.querySelectorAll('input[name="bulan_pembayaran[]"]:checked');
        if (checkboxes.length > 0) return checkboxes[0].value; // Ambil yg pertama buat validasi
        return '';
    }

    function addMitraInput() {
        const container = document.getElementById('mitra-container');
        const newMitraGroup = document.createElement('div');
        newMitraGroup.className = 'mitra-input-group mb-2';
        newMitraGroup.dataset.id = mitraCounter;
        newMitraGroup.innerHTML = `
        <div class="select-search-container input-wrapper-mitra">
            <input type="text" id="mitra-search-input-${mitraCounter}" class="select-search-input manual-input" placeholder="Cari Nama Mitra..." autocomplete="off" required>
            <input type="hidden" name="mitra_id[]" id="mitra_id-${mitraCounter}" class="manual-input">
            <div id="mitra-dropdown-${mitraCounter}" class="select-search-dropdown">
                <?php foreach ($mitra_list as $mitra) : ?>
                    <div class="select-search-dropdown-item" data-id="<?= htmlspecialchars($mitra['id']) ?>" data-name="<?= htmlspecialchars($mitra['nama_lengkap']) ?>"><?= htmlspecialchars($mitra['nama_lengkap']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="input-wrapper-jumlah">
            <input type="number" name="jumlah_satuan[]" class="form-input jumlah-satuan-input manual-input" placeholder="Jumlah Satuan" required min="1">
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeMitraInput(this)">-</button>
        `;
        container.appendChild(newMitraGroup);
        const input = document.getElementById(`mitra-search-input-${mitraCounter}`);
        const dropdown = document.getElementById(`mitra-dropdown-${mitraCounter}`);
        const hidden = document.getElementById(`mitra_id-${mitraCounter}`);
        setupSelectSearch(input, dropdown, hidden, true);
        newMitraGroup.querySelector('.jumlah-satuan-input').addEventListener('input', function() {
            validateHonor(newMitraGroup);
        });
        mitraCounter++;
    }

    function removeMitraInput(button) {
        const container = button.closest('.mitra-input-group');
        const mitraId = container.querySelector('input[type="hidden"]').value;
        if (mitraId) {
            delete honorStatus[mitraId];
        }
        container.remove();
    }
    // Helper untuk mengambil satu bulan pembayaran (hanya untuk keperluan validasi honor sederhana di JS)
    function getSelectedPaymentMonth() {
        // Ambil semua checkbox bulan pembayaran yang dicentang
        const checkboxes = document.querySelectorAll('input[name="bulan_pembayaran[]"]:checked');
        if (checkboxes.length > 0) {
            // Kembalikan nilai pertama saja agar validasi JS tidak error
            return checkboxes[0].value;
        }
        return ''; // Kosong jika tidak ada yang dicentang
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
        const bulanPembayaranSelect = document.getElementById('bulan_pembayaran');
        const tahunPembayaranInput = document.getElementById('tahun_pembayaran');

        const timSelect = document.getElementById('tim_id');
        const timIdHidden = document.getElementById('tim_id_hidden');
        const triggerPeriodeSelect = document.getElementById('trigger_periode');

        const toggleOtomatis = document.getElementById('toggle_otomatis');
        const mitraManualContainer = document.getElementById('mitra-manual-container');
        const mitraOtomatisContainer = document.getElementById('mitra-otomatis-container');
        const mitraOtomatisList = document.getElementById('mitra-otomatis-list');
        const filterInputOtomatis = document.getElementById('filter-mitra-otomatis');
        const countDisplay = document.getElementById('count-mitra-otomatis');
        // Tambahkan di dalam DOMContentLoaded
        document.querySelectorAll('input[name="bulan_pembayaran[]"]').forEach(cb => {
            cb.addEventListener('change', () => {
                // Trigger validasi ulang (bisa pakai revalidateAutomaticList() atau manual)
                if (typeof revalidateAutomaticList === 'function') revalidateAutomaticList();
            });
        });

        const manualInputFields = document.querySelectorAll('.manual-input');
        const periodeWrappers = {
            bulanan: document.getElementById('periode-bulanan-wrapper'),
            triwulan: document.getElementById('periode-triwulan-wrapper'),
            subron: document.getElementById('periode-subron-wrapper'),
            tahunan: document.getElementById('periode-tahunan-wrapper')
        };
        const periodeInputs = document.querySelectorAll('.periode-input');

        setupSelectSearch(firstInput, firstDropdown, firstHidden, true);

        // --- FUNGSI PENGATUR TAMPILAN ---
        function updateMode() {
            const isOtomatis = toggleOtomatis.checked;
            const timId = timSelect.value;

            timIdHidden.value = timId;

            if (isOtomatis && !timId) {
                alert("Pilih Tim terlebih dahulu sebelum mengaktifkan mode otomatis.");
                toggleOtomatis.checked = false;
                return;
            }

            if (isOtomatis) {
                mitraManualContainer.style.display = 'none';
                mitraOtomatisContainer.style.display = 'block';
                manualInputFields.forEach(field => {
                    field.required = false;
                    field.disabled = true;
                });

                mitraOtomatisList.innerHTML = '<p><i>Memuat daftar anggota tim...</i></p>';

                fetch(`get_mitra_by_tim.php?tim_id=${timId}`)
                    .then(res => res.json())
                    .then(data => {
                        mitraOtomatisList.innerHTML = '';
                        if (data.length === 0) {
                            mitraOtomatisList.innerHTML = '<p style="color: red;">Tidak ada mitra dalam tim ini.</p>';
                            updateCountMitra();
                            return;
                        }

                        const fragment = document.createDocumentFragment();
                        data.forEach(mitra => {
                            const itemDiv = document.createElement('div');
                            itemDiv.className = 'mitra-otomatis-item';

                            // PERUBAHAN: TOMBOL HANYA MENGUBAH STATUS (Soft Delete)
                            itemDiv.innerHTML = `
                                <span>${mitra.nama_lengkap}</span>
                                <input type="hidden" name="mitra_id[]" value="${mitra.id}">
                                <div style="display:flex; align-items:center;">
                                    <div class="mitra-otomatis-jumlah">
                                        <input type="number" name="jumlah_satuan[]" value="1" class="mitra-otomatis-input" required min="1">
                                    </div>
                                    
                                    <button type="button" class="btn-action-item" title="Hapus/Restore">✕</button>
                                </div>`;

                            const inputId = itemDiv.querySelector('input[name="mitra_id[]"]');
                            const inputJumlah = itemDiv.querySelector('.mitra-otomatis-input');
                            const toggleBtn = itemDiv.querySelector('.btn-action-item');

                            inputJumlah.addEventListener('input', () => validateHonor(itemDiv));

                            // LOGIKA TOGGLE HAPUS/RESTORE
                            toggleBtn.addEventListener('click', function() {
                                if (itemDiv.classList.contains('deleted')) {
                                    // RESTORE
                                    itemDiv.classList.remove('deleted');
                                    inputId.disabled = false;
                                    inputJumlah.disabled = false;
                                    this.innerHTML = '✕';
                                    this.classList.remove('restore');
                                    this.title = "Hapus dari daftar";
                                    validateHonor(itemDiv); // Cek validasi lagi saat restore
                                } else {
                                    // DELETE (Soft)
                                    itemDiv.classList.add('deleted');
                                    inputId.disabled = true;
                                    inputJumlah.disabled = true;
                                    this.innerHTML = '↺'; // Ikon Undo
                                    this.classList.add('restore');
                                    this.title = "Kembalikan mitra ini";
                                    // Bersihkan status honor visual
                                    const status = itemDiv.querySelector('.honor-status');
                                    if (status) status.textContent = '';
                                }
                                updateCountMitra();
                            });

                            validateHonor(itemDiv);
                            fragment.appendChild(itemDiv);
                        });
                        mitraOtomatisList.appendChild(fragment);
                        updateCountMitra();
                    });

            } else {
                mitraManualContainer.style.display = 'block';
                mitraOtomatisContainer.style.display = 'none';
                mitraOtomatisList.innerHTML = '';
                manualInputFields.forEach(field => {
                    field.required = true;
                    field.disabled = false;
                });
            }
        }

        function revalidateAutomaticList() {
            if (toggleOtomatis.checked) {
                const allOtomatisItems = document.querySelectorAll('.mitra-otomatis-item');
                // Hanya validasi yang TIDAK dihapus
                allOtomatisItems.forEach(itemDiv => {
                    if (!itemDiv.classList.contains('deleted')) {
                        validateHonor(itemDiv);
                    }
                });
            }
        }

        function updateCountMitra() {
            // Hitung hanya yang tidak punya class 'deleted'
            const items = mitraOtomatisList.querySelectorAll('.mitra-otomatis-item:not(.deleted)');
            const count = items.length;
            if (countDisplay) countDisplay.textContent = count;
        }

        if (filterInputOtomatis) {
            filterInputOtomatis.addEventListener('input', function() {
                const filterValue = this.value.toLowerCase();
                const items = mitraOtomatisList.querySelectorAll('.mitra-otomatis-item');
                items.forEach(item => {
                    const nama = item.querySelector('span').textContent.toLowerCase();
                    // Tampilkan jika nama cocok (walaupun status deleted) agar bisa direstore
                    item.style.display = nama.includes(filterValue) ? 'flex' : 'none';
                });
            });
        }

        timSelect.addEventListener('change', function() {
            timIdHidden.value = this.value;
            if (this.value === "" && toggleOtomatis.checked) {
                toggleOtomatis.checked = false;
                updateMode();
            } else if (toggleOtomatis.checked) {
                updateMode();
            }
        });

        triggerPeriodeSelect.addEventListener('change', function() {
            const tipe = this.value;
            Object.values(periodeWrappers).forEach(wrapper => (wrapper.style.display = 'none'));
            periodeInputs.forEach(input => {
                input.disabled = true;
                input.required = false;
            });

            let targetWrapper = null;
            if (tipe === 'bulanan') targetWrapper = periodeWrappers.bulanan;
            else if (tipe === 'triwulan') targetWrapper = periodeWrappers.triwulan;
            else if (tipe === 'subron') targetWrapper = periodeWrappers.subron;
            else if (tipe === 'tahunan') targetWrapper = periodeWrappers.tahunan;

            if (targetWrapper) {
                targetWrapper.style.display = 'block';
                const inputDiDalam = targetWrapper.querySelector('.periode-input');
                if (inputDiDalam) {
                    inputDiDalam.disabled = false;
                    inputDiDalam.required = true;
                }
            }
        });

        toggleOtomatis.addEventListener('change', updateMode);

        // Cascade listeners (tidak berubah)
        tahunSelect.addEventListener('change', () => {
            if (tahunSelect.value) {
                fetch(`get_data.php?type=program&tahun=${tahunSelect.value}`)
                    .then(res => res.json()).then(data => {
                        programSelect.innerHTML = '<option value="">-- Pilih Program --</option>';
                        data.forEach(p => {
                            programSelect.innerHTML += `<option value="${p.id}">${p.kode} - ${p.nama}</option>`;
                        });
                        programSelect.disabled = false;
                    });
            }
        });
        // ... (Sisa cascade listeners disingkat) ...
        programSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=kegiatan&program_id=${programSelect.value}`).then(res => res.json()).then(data => {
                kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>';
                data.forEach(d => {
                    kegiatanSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                });
                kegiatanSelect.disabled = false;
            });
        });
        kegiatanSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=output&kegiatan_id=${kegiatanSelect.value}`).then(res => res.json()).then(data => {
                outputSelect.innerHTML = '<option value="">-- Pilih Output --</option>';
                data.forEach(d => {
                    outputSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                });
                outputSelect.disabled = false;
            });
        });
        outputSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=sub_output&output_id=${outputSelect.value}`).then(res => res.json()).then(data => {
                subOutputSelect.innerHTML = '<option value="">-- Pilih Sub Output --</option>';
                data.forEach(d => {
                    subOutputSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                });
                subOutputSelect.disabled = false;
            });
        });
        subOutputSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=komponen&sub_output_id=${subOutputSelect.value}`).then(res => res.json()).then(data => {
                komponenSelect.innerHTML = '<option value="">-- Pilih Komponen --</option>';
                data.forEach(d => {
                    komponenSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                });
                komponenSelect.disabled = false;
            });
        });
        komponenSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=sub_komponen&komponen_id=${komponenSelect.value}`).then(res => res.json()).then(data => {
                subKomponenSelect.innerHTML = '<option value="">-- Pilih Sub Komponen --</option>';
                data.forEach(d => {
                    subKomponenSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                });
                subKomponenSelect.disabled = false;
            });
        });
        subKomponenSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=akun&sub_komponen_id=${subKomponenSelect.value}`).then(res => res.json()).then(data => {
                akunSelect.innerHTML = '<option value="">-- Pilih Akun --</option>';
                data.forEach(d => {
                    akunSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`;
                });
                akunSelect.disabled = false;
            });
        });
        akunSelect.addEventListener('change', () => {
            fetch(`get_data.php?type=item&akun_id=${akunSelect.value}`).then(res => res.json()).then(data => {
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
            revalidateAutomaticList();
        });
        bulanPembayaranSelect.addEventListener('change', revalidateAutomaticList);
        tahunPembayaranInput.addEventListener('input', revalidateAutomaticList);
    });
</script>

<?php
if (isset($result_mitra) && $result_mitra instanceof mysqli_result) {
    $result_mitra->free();
}
if (isset($result_tim) && $result_tim instanceof mysqli_result) {
    $result_tim->free();
}
if (isset($result_jenis_mitra) && $result_jenis_mitra instanceof mysqli_result) {
    $result_jenis_mitra->free();
}
include '../includes/footer.php';
?>