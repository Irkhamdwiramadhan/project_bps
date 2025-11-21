<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil daftar TIM
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim) {
    while ($row = $result_tim->fetch_assoc()) $tim_list[] = $row;
}

// Ambil Daftar Tahun
$sql_tahun = "SELECT DISTINCT tahun_pembayaran FROM honor_mitra ORDER BY tahun_pembayaran DESC";
$result_tahun = $koneksi->query($sql_tahun);
$tahun_list = [];
if ($result_tahun) {
    while ($row = $result_tahun->fetch_assoc()) $tahun_list[] = $row['tahun_pembayaran'];
}
if(empty($tahun_list)) $tahun_list[] = date('Y');

// Tangkap Parameter URL (Auto Filter)
$selected_tim_id = isset($_GET['tim_id']) ? $_GET['tim_id'] : '';
$selected_tahun  = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$penilai_nama = $_SESSION['user_nama'] ?? 'Admin';
$penilai_id   = $_SESSION['user_id'] ?? 0;
?>

<style>
    body { background-color: #f3f4f6; font-family: 'Poppins', sans-serif; }
    .main-content { padding: 2rem; }
    .card { background: #fff; border-radius: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 2rem; margin-bottom: 2rem; }
    
    /* --- FILTER SECTION --- */
    .filter-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-label { font-weight: 600; color: #374151; display: block; margin-bottom: 0.5rem; font-size: 0.9rem; }
    .form-select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; background-color: #fff; }
    
    /* --- TABLE STYLES --- */
    .table-responsive { overflow-x: auto; margin-top: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; }
    .custom-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .custom-table th { background: #f9fafb; padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 2px solid #e5e7eb; }
    .custom-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; color: #4b5563; vertical-align: middle; background: #fff; }
    .custom-table tr:hover td { background: #f0f9ff; }
    
    /* --- BADGES & BUTTONS --- */
    .badge { padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .btn-nilai { background: #2563eb; color: white; border: none; padding: 6px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: 0.2s; font-weight: 500; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); }
    .btn-nilai:hover { background: #1d4ed8; transform: translateY(-1px); }
    .btn-nilai:disabled { background: #9ca3af; cursor: not-allowed; transform: none; box-shadow: none; }

    /* --- MODAL FULLSCREEN CENTERING --- */
    .modal-overlay {
        display: none; /* Hidden by default */
        position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.6); /* Black w/ opacity */
        backdrop-filter: blur(4px);
        overflow-y: auto; /* Enable scroll if modal is tall */
        padding: 20px;
        align-items: center; justify-content: center;
    }
    
    /* --- MODAL BOX (THE CARD) --- */
    .modal-box {
        background-color: #fefefe;
        margin: auto;
        border-radius: 12px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        width: 95%;
        max-width: 1100px; /* Lebar maksimal besar */
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: modalFadeIn 0.3s ease-out;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }

    /* --- MODAL HEADER --- */
    .modal-header {
        background: #2563eb; color: white; padding: 1.5rem;
        display: flex; justify-content: space-between; align-items: flex-start;
    }
    .modal-title h3 { margin: 0; font-size: 1.25rem; font-weight: 700; }
    .modal-subtitle { font-size: 0.9rem; opacity: 0.9; margin-top: 4px; }
    .close-btn { color: white; font-size: 2rem; font-weight: bold; cursor: pointer; line-height: 0.8; opacity: 0.7; transition: 0.2s; }
    .close-btn:hover { opacity: 1; }

    /* --- MODAL BODY (SPLIT LAYOUT) --- */
    .modal-body {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Kiri Form, Kanan Panduan */
        gap: 0; /* Border separator handling */
        background: #fff;
    }
    
    @media (max-width: 768px) {
        .modal-body { grid-template-columns: 1fr; } /* Stack on mobile */
    }

    /* --- COLUMN LEFT: FORM --- */
    .col-form { padding: 2rem; }
    .input-group { margin-bottom: 1.5rem; }
    .input-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .form-control { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 1rem; transition: 0.2s; }
    .form-control:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    
    /* --- COLUMN RIGHT: GUIDE --- */
    .col-guide {
        background: #f8fafc;
        padding: 2rem;
        border-left: 1px solid #e2e8f0;
        font-size: 0.85rem;
        max-height: 600px; /* Scrollable if too long */
        overflow-y: auto;
    }
    .guide-title { font-weight: 700; color: #475569; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.8rem; }
    .guide-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; background: white; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; }
    .guide-table th { background: #e0e7ff; color: #3730a3; padding: 6px 10px; text-align: left; font-weight: 600; }
    .guide-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .guide-table tr:last-child td { border-bottom: none; }
    .val-bad { color: #dc2626; font-weight: 600; }
    .val-good { color: #166534; font-weight: 600; }

    /* --- MODAL FOOTER --- */
    .modal-footer {
        padding: 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0;
        display: flex; justify-content: flex-end; gap: 1rem;
    }
</style>

<div class="main-content">
    
    <div class="card">
        <div style="margin-bottom: 1.5rem;">
            <h2 class="text-2xl font-bold text-gray-800">Input Penilaian Kinerja</h2>
            <p class="text-gray-500 text-sm">Pilih Tim dan Tahun untuk memunculkan daftar mitra.</p>
        </div>

        <div class="filter-grid">
            <div class="form-group">
                <label class="form-label">Pilih Tim Pelaksana:</label>
                <select id="filter_tim" class="form-select">
                    <option value="">-- Pilih Tim --</option>
                    <?php foreach ($tim_list as $tim): ?>
                        <option value="<?= $tim['id'] ?>" <?= ($tim['id'] == $selected_tim_id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tim['nama_tim']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tahun:</label>
                <select id="filter_tahun" class="form-select">
                    <?php foreach ($tahun_list as $thn): ?>
                        <option value="<?= $thn ?>" <?= ($thn == $selected_tahun) ? 'selected' : '' ?>>
                            <?= $thn ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="loading-indicator" style="display:none; text-align:center; padding:30px; color:#6b7280;">
            <svg style="width:24px; height:24px; animation:spin 1s linear infinite; margin-bottom:10px;" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <br>Memuat data...
        </div>
        <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>

        <div id="error-message" style="display:none; text-align:center; padding:15px; color:#dc2626; background:#fee2e2; border-radius:8px; margin-top:10px;"></div>

        <div id="mitra-list-container" class="table-responsive" style="display:none;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Nama Mitra</th>
                        <th>Pekerjaan / Kegiatan</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="mitra-table-body"></tbody>
            </table>
        </div>
    </div>

</div>

<div id="modalPenilaian" class="modal-overlay">
    <div class="modal-box">
        
        <div class="modal-header">
            <div class="modal-title">
                <h3 id="modal_nama_mitra">Nama Mitra</h3>
                <div id="modal_detail_tugas" class="modal-subtitle">Detail Tugas...</div>
            </div>
            <span class="close-btn" onclick="tutupModal()">&times;</span>
        </div>

        <div class="modal-body">
            
            <div class="col-form">
                <form action="../proses/proses_tambah_penilaian.php" method="POST" id="formPenilaian">
                    <input type="hidden" name="mitra_survey_id" id="input_mitra_survey_id" required>
                    <input type="hidden" name="penilai_id" value="<?= $penilai_id ?>">
                    
                    <div class="input-group">
                        <label class="form-label" style="font-size:1.1rem; margin-bottom:1rem; color:#1e40af;">Input Nilai (1 - 4)</label>
                        <div class="input-row">
                            <div>
                                <label class="form-label">Kualitas</label>
                                <input type="number" name="kualitas" class="form-control" min="1" max="4" required>
                            </div>
                            <div>
                                <label class="form-label">Volume</label>
                                <input type="number" name="volume_pemasukan" class="form-control" min="1" max="4" required>
                            </div>
                            <div>
                                <label class="form-label">Perilaku</label>
                                <input type="number" name="perilaku" class="form-control" min="1" max="4" required>
                            </div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="form-label">Catatan / Keterangan (Opsional)</label>
                        <textarea name="keterangan" class="form-control" rows="4" placeholder="Contoh: Pekerjaan sangat rapi, tapi sedikit terlambat..."></textarea>
                    </div>
                </form>
            </div>

            <div class="col-guide">
                <div class="guide-title">ℹ️ Panduan: Kualitas</div>
                <table class="guide-table">
                    <tr><th width="40">4</th><td><span class="val-good">Sangat Baik</span> (Benar 100%)</td></tr>
                    <tr><th>3</th><td>Baik (Benar 70% - 90%, Sebagian Salah)</td></tr>
                    <tr><th>2</th><td>Cukup (Benar 50% - 60%, Banyak Salah)</td></tr>
                    <tr><th>1</th><td><span class="val-bad">Kurang</span> (Di bawah 50%, Tidak Layak)</td></tr>
                </table>

                <div class="guide-title">ℹ️ Panduan: Volume / Ketepatan Waktu</div>
                <table class="guide-table">
                    <tr><th width="40">4</th><td><span class="val-good">2 Hari Sebelum Deadline</span></td></tr>
                    <tr><th>3</th><td>Tepat di Hari Deadline</td></tr>
                    <tr><th>2</th><td>Terlambat (Volume Sesuai)</td></tr>
                    <tr><th>1</th><td><span class="val-bad">Terlambat (Volume Tidak Sesuai)</span></td></tr>
                </table>

                <div class="guide-title">ℹ️ Panduan: Perilaku</div>
                <table class="guide-table">
                    <tr><th width="40">4</th><td><span class="val-good">Sangat Memuaskan</span> (Kooperatif, Sopan, Disiplin)</td></tr>
                    <tr><th>3</th><td>Memuaskan (Cukup Kooperatif)</td></tr>
                    <tr><th>2</th><td>Kurang (Pernah menolak perintah/konflik)</td></tr>
                    <tr><th>1</th><td><span class="val-bad">Tidak Memuaskan</span> (Sering menolak, konflik, bolos)</td></tr>
                </table>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="tutupModal()" class="btn-nilai" style="background:#9ca3af;">Batal</button>
            <button type="button" onclick="document.getElementById('formPenilaian').submit()" class="btn-nilai" style="background:#22c55e; font-size:1rem; padding: 0.75rem 2rem;">Simpan Penilaian</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterTim = document.getElementById('filter_tim');
    const filterTahun = document.getElementById('filter_tahun');
    const listContainer = document.getElementById('mitra-list-container');
    const tableBody = document.getElementById('mitra-table-body');
    const loading = document.getElementById('loading-indicator');
    const errorMsg = document.getElementById('error-message');

    // Elemen Modal
    const modal = document.getElementById('modalPenilaian');
    const modalNama = document.getElementById('modal_nama_mitra');
    const modalDetail = document.getElementById('modal_detail_tugas');
    const inputSurveyId = document.getElementById('input_mitra_survey_id');
    const formPenilaian = document.getElementById('formPenilaian');

    function loadDataMitra() {
        const timId = filterTim.value;
        const tahun = filterTahun.value;

        listContainer.style.display = 'none';
        errorMsg.style.display = 'none';
        tableBody.innerHTML = '';

        if (!timId) return;

        loading.style.display = 'block';

        fetch(`get_status_penilaian_tim.php?tim_id=${timId}&tahun=${tahun}&_=${Date.now()}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                loading.style.display = 'none';
                listContainer.style.display = 'block';

                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-4">Tidak ada mitra/kegiatan untuk Tim dan Tahun ini.</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    let statusBadge = item.penilaian_id 
                        ? `<span class="badge badge-success">Sudah Dinilai (Skor: ${item.skor_akhir})</span>` 
                        : `<span class="badge badge-warning">Belum Dinilai</span>`;
                    
                    let btnAction = item.penilaian_id 
                        ? `<button class="btn-nilai" disabled style="opacity:0.5; background:#64748b;">Selesai</button>` 
                        : `<button class="btn-nilai" onclick="bukaModal('${item.survey_id}', '${escapeHtml(item.nama_lengkap)}', '${escapeHtml(item.pekerjaan_lengkap)}', '${item.label_periode}')">Nilai</button>`;

                    row.innerHTML = `
                        <td><strong>${item.nama_lengkap}</strong></td>
                        <td>${item.pekerjaan_lengkap}</td>
                        <td>${item.label_periode}</td>
                        <td>${statusBadge}</td>
                        <td>${btnAction}</td>
                    `;
                    tableBody.appendChild(row);
                });
            })
            .catch(err => {
                console.error(err);
                loading.style.display = 'none';
                errorMsg.textContent = "Gagal memuat data: " + err.message;
                errorMsg.style.display = 'block';
            });
    }

    filterTim.addEventListener('change', loadDataMitra);
    filterTahun.addEventListener('change', loadDataMitra);

    window.escapeHtml = function(text) {
        if (!text) return '';
        return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // --- FUNGSI MODAL ---
    window.bukaModal = function(surveyId, nama, pekerjaan, periode) {
        inputSurveyId.value = surveyId;
        modalNama.textContent = nama;
        modalDetail.textContent = `${pekerjaan} (${periode})`;
        
        // Reset Form
        formPenilaian.reset();
        
        // Tampilkan Modal dengan Flex
        modal.style.display = 'flex';
    }

    window.tutupModal = function() {
        modal.style.display = 'none';
    }

    // Tutup modal jika klik di luar box
    window.onclick = function(event) {
        if (event.target == modal) {
            tutupModal();
        }
    }

    // Auto-trigger jika ada parameter di URL
    if (filterTim.value) {
        loadDataMitra();
    }
});
</script>

<?php include '../includes/footer.php'; ?>