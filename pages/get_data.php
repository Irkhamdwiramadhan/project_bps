<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'admin_tu', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// Ambil tahun unik
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_program ORDER BY tahun DESC");
$daftar_tahun = [];
while ($row = $tahun_result->fetch_assoc()) {
    $daftar_tahun[] = $row['tahun'];
}
if (empty($daftar_tahun)) {
    $daftar_tahun[] = date('Y');
}
?>

<main class="main-content">
<div class="container-fluid py-5">
    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header text-white bg-primary">
            <h4 class="mb-0"><i class="fas fa-cloud-download-alt me-2"></i>Menu Cetak Laporan</h4>
        </div>
        <div class="card-body p-4">
            <form id="cetakForm" method="GET" target="_blank">

                <div class="mb-3">
                    <label for="laporan">Jenis Laporan</label>
                    <select id="laporan" name="laporan" class="form-control" required>
                        <option value="">-- Pilih Jenis Laporan --</option>
                        <option value="rpd">1. Laporan RPD</option>
                        <option value="realisasi_saja">2. Laporan Realisasi Saja</option>
                        <option value="realisasi_gabungan">3. Laporan Realisasi vs RPD</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="tahun">Tahun</label>
                    <select id="tahun" name="tahun" class="form-control" required>
                        <option value="">-- Pilih Tahun --</option>
                        <?php foreach ($daftar_tahun as $thn): ?>
                            <option value="<?= htmlspecialchars($thn) ?>"><?= htmlspecialchars($thn) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dropdown Berjenjang -->
                <div class="mb-3">
                    <label for="program_kode">Program</label>
                    <select id="program_kode" name="program_kode" class="form-control">
                        <option value="">-- Pilih Program --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="kegiatan_kode">Kegiatan</label>
                    <select id="kegiatan_kode" name="kegiatan_kode" class="form-control">
                        <option value="">-- Pilih Kegiatan --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="output_kode">Output</label>
                    <select id="output_kode" name="output_kode" class="form-control">
                        <option value="">-- Pilih Output --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="sub_output_kode">Sub Output</label>
                    <select id="sub_output_kode" name="sub_output_kode" class="form-control">
                        <option value="">-- Pilih Sub Output --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="komponen_kode">Komponen</label>
                    <select id="komponen_kode" name="komponen_kode" class="form-control">
                        <option value="">-- Pilih Komponen --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="sub_komponen_kode">Sub Komponen</label>
                    <select id="sub_komponen_kode" name="sub_komponen_kode" class="form-control">
                        <option value="">-- Pilih Sub Komponen --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="akun_kode">Akun</label>
                    <select id="akun_kode" name="akun_kode" class="form-control">
                        <option value="">-- Pilih Akun --</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="format">Format</label>
                    <select id="format" name="format" class="form-control" required>
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>

                <!-- Pilih Variabel -->
                <div class="mb-3">
                    <label><strong>Pilih Variabel Ditampilkan:</strong></label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="level_detail[]" value="program" id="chk_program">
                        <label class="form-check-label" for="chk_program">Program</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="level_detail[]" value="kegiatan" id="chk_kegiatan">
                        <label class="form-check-label" for="chk_kegiatan">Kegiatan</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="level_detail[]" value="output" id="chk_output">
                        <label class="form-check-label" for="chk_output">Output</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="level_detail[]" value="item" id="chk_item">
                        <label class="form-check-label" for="chk_item">Item</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="downloadBtn" disabled>
                    <i class="fas fa-download me-2"></i>Unduh Laporan
                </button>

            </form>
        </div>
    </div>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tahun = document.getElementById('tahun');
    const downloadBtn = document.getElementById('downloadBtn');
    const laporanSelect = document.getElementById('laporan');
    const formatSelect = document.getElementById('format');
    const checkboxes = document.querySelectorAll('input[name="level_detail[]"]');
    const form = document.getElementById('cetakForm');

    // Dropdown berjenjang berbasis KODE
    const chain = [
        {id: 'program_kode', next: 'kegiatan_kode', type: 'program', param: 'program_id'},
        {id: 'kegiatan_kode', next: 'output_kode', type: 'kegiatan', param: 'kegiatan_id'},
        {id: 'output_kode', next: 'sub_output_kode', type: 'output', param: 'output_id'},
        {id: 'sub_output_kode', next: 'komponen_kode', type: 'sub_output', param: 'sub_output_id'},
        {id: 'komponen_kode', next: 'sub_komponen_kode', type: 'komponen', param: 'komponen_id'},
        {id: 'sub_komponen_kode', next: 'akun_kode', type: 'sub_komponen', param: 'sub_komponen_id'}
    ];

    // Fungsi load data berdasarkan kode
    function loadData(type, param, kode, tahunVal, targetId) {
        let url = `../ajax/get_data.php?type=${type}&tahun=${tahunVal}`;
        if (param && kode) url += `&${param}=${encodeURIComponent(kode)}`;
        fetch(url)
        .then(res => res.json())
        .then(data => {
            const target = document.getElementById(targetId);
            target.innerHTML = `<option value="">-- Pilih ${type.replace('_', ' ')} --</option>`;
            data.forEach(row => {
                target.innerHTML += `<option value="${row.kode}">${row.kode} - ${row.nama}</option>`;
            });
        });
    }

    // Reset dropdown di bawahnya
    function resetBelow(startId) {
        let found = false;
        chain.forEach(c => {
            if (found && c.id) {
                const el = document.getElementById(c.id);
                el.innerHTML = `<option value="">-- Pilih ${c.id.replace('_', ' ')} --</option>`;
            }
            if (c.id === startId) found = true;
        });
    }

    // Event untuk memuat program berdasarkan tahun
    tahun.addEventListener('change', () => {
        loadData('program', '', '', tahun.value, 'program_kode');
        resetBelow('program_kode');
    });

    // Event tiap level
    chain.forEach(c => {
        document.getElementById(c.id).addEventListener('change', function() {
            const kode = this.value;
            resetBelow(c.id);
            if (c.next && kode) {
                loadData(c.next.replace('_kode', ''), c.param, kode, tahun.value, c.next);
            }
        });
    });

    // Tentukan file cetak otomatis
    const fileMap = {
        'rpd': 'cetak_rpd',
        'realisasi_saja': 'cetak_hanya_realisasi',
        'realisasi_gabungan': 'cetak_realisasi'
    };

    function updateFormAction() {
        const jenis = laporanSelect.value;
        const format = formatSelect.value;
        const checked = Array.from(checkboxes).some(c => c.checked);
        if (jenis && format && checked) {
            const base = fileMap[jenis];
            const suffix = (format === 'excel') ? '_excel' : '_pdf';
            form.action = `../proses/${base}${suffix}.php`;
            downloadBtn.disabled = false;
        } else {
            downloadBtn.disabled = true;
        }
    }

    laporanSelect.addEventListener('change', updateFormAction);
    formatSelect.addEventListener('change', updateFormAction);
    checkboxes.forEach(c => c.addEventListener('change', updateFormAction));
});
</script>
