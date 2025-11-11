<?php
// Mulai sesi
session_start();

// TODO: Tambahkan pengecekan peran pengguna di sini jika diperlukan
// if (!isset($_SESSION['user_role']) || !in_array('pegawai', $_SESSION['user_role'])) {
//     header('Location: /login.php');
//     exit();
// }

// Include file-file lain
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Inisialisasi variabel untuk penilai otomatis
$penilai_nama_otomatis = $_SESSION['user_nama'] ?? null;
$penilai_id_otomatis = $_SESSION['user_id'] ?? null;

// =========================================================================
// KUERI DROPDOWN SESUAI LOGIKA ANDA YANG BENAR
// =========================================================================
$sql_mitra_kegiatan_item = "
    SELECT DISTINCT
        ms.id,
        m.nama_lengkap AS nama_mitra,
        mk.nama AS nama_kegiatan,
        (SELECT mi.nama_item FROM master_item mi WHERE mi.kode_unik LIKE CONCAT(hm.item_kode_unik, '%') LIMIT 1) AS nama_item
    FROM honor_mitra hm
    INNER JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    INNER JOIN mitra m ON ms.mitra_id = m.id
    LEFT JOIN master_kegiatan mk ON ms.kegiatan_id = mk.kode
    WHERE hm.item_kode_unik IS NOT NULL AND hm.item_kode_unik != ''
    ORDER BY m.nama_lengkap, mk.nama ASC
";

$result_mitra_kegiatan = $koneksi->query($sql_mitra_kegiatan_item);

$mitra_kegiatan_list = [];
if ($result_mitra_kegiatan) {
    while ($row = $result_mitra_kegiatan->fetch_assoc()) {
        // Gabungkan nama kegiatan dan nama item untuk tampilan yang lebih jelas
        $pekerjaan = $row['nama_kegiatan'];
        if (!empty($row['nama_item']) && $row['nama_item'] !== $row['nama_kegiatan']) {
            $pekerjaan .= ' - ' . $row['nama_item'];
        }
        $row['pekerjaan_lengkap'] = $pekerjaan;
        $mitra_kegiatan_list[] = $row;
    }
}

// =========================================================================
// KUERI RIWAYAT PENILAIAN YANG DIPERBAIKI
// =========================================================================
$sql_penilaian_history = "
    SELECT 
        DISTINCT p.id,
        p.tanggal_penilaian,
        p.beban_kerja,
        p.kualitas,
        p.volume_pemasukan,
        p.perilaku,
        p.keterangan,
        m.nama_lengkap AS nama_mitra,
        mk.nama AS nama_kegiatan,
        peg.nama AS nama_penilai,
        ms.id AS mitra_survey_id
    FROM mitra_penilaian_kinerja p
    INNER JOIN mitra_surveys ms ON p.mitra_survey_id = ms.id
    INNER JOIN mitra m ON ms.mitra_id = m.id
    INNER JOIN pegawai peg ON p.penilai_id = peg.id
    LEFT JOIN master_kegiatan mk 
        ON ms.kegiatan_id = mk.kode 
        AND mk.tahun = YEAR(p.tanggal_penilaian)
    ORDER BY p.tanggal_penilaian DESC
";



$result_penilaian_history = $koneksi->query($sql_penilaian_history);

$penilaian_history_list = [];
if ($result_penilaian_history && $result_penilaian_history->num_rows > 0) {
    while ($row = $result_penilaian_history->fetch_assoc()) {
        $penilaian_history_list[] = $row;
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body { font-family: 'Poppins', sans-serif; background: #eef2f5; }
    .content-wrapper { padding: 1rem; transition: margin-left 0.3s ease; }
    @media (min-width: 640px) { .content-wrapper { margin-left: 16rem; padding-top: 2rem; } }
    .card { background-color: #ffffff; border-radius: 1rem; padding: 2rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
    .form-label { font-weight: 500; color: #4b5563; }
    .form-input, .form-select, .form-textarea {
        display: block;
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 1rem;
        margin-top: 0.5rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
    }
    .form-input[readonly] {
        background-color: #f3f4f6;
        cursor: not-allowed;
    }
    .btn-primary { background-color: #2563eb; color: #fff; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s; border:none; }
    .btn-primary:hover { background-color: #1d4ed8; }
    .btn-secondary { background-color: #6b7280; color: #fff; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s; border:none; }
    .btn-secondary:hover { background-color: #4b5563; }
    .select-search-container {
        position: relative;
        width: 100%;
    }
    .search-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 1rem;
        margin-top: 0.5rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
    }
    .select-dropdown {
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #d1d5db;
        border-top: none;
        border-radius: 0 0 0.5rem 0.5rem;
        position: absolute;
        z-index: 10;
        background-color: #fff;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: none;
    }
    .select-dropdown-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .select-dropdown-item:hover {
        background-color: #f3f4f6;
    }
    .select-dropdown-item.hidden {
        display: none;
    }
    .table-container {
        overflow-x: auto;
        margin-top: 2rem;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    th {
        background-color: #f3f4f6;
        color: #4b5563;
        font-weight: 600;
    }
    tr:nth-child(even) {
        background-color: #f9fafb;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Tambah Penilaian Kinerja Mitra</h1>
        <div class="card">
            <form action="../proses/proses_tambah_penilaian.php" method="POST">
                
                <div class="mb-6">
                    <label for="mitra_survey_id" class="form-label">Nama Mitra & Pekerjaan</label>
                    <div class="select-search-container">
                        <input type="text" class="search-input" placeholder="Cari nama mitra atau pekerjaan..." id="mitra-kegiatan-search-input">
                        
                        <div id="mitra-kegiatan-dropdown" class="select-dropdown">
                            <?php foreach ($mitra_kegiatan_list as $item) : ?>
                                <div class="select-dropdown-item" 
                                     data-id="<?= htmlspecialchars($item['id']) ?>"
                                     data-search-text="<?= htmlspecialchars(strtolower($item['nama_mitra'] . ' ' . $item['pekerjaan_lengkap'])) ?>">
                                     <?= htmlspecialchars($item['nama_mitra']) ?> - <?= htmlspecialchars($item['pekerjaan_lengkap']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="mitra_survey_id" name="mitra_survey_id" required>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="penilai_nama" class="form-label">Nama Penilai</label>
                    <input type="text" 
                           class="form-input" 
                           id="penilai_nama" 
                           value="<?= htmlspecialchars($penilai_nama_otomatis) ?>" 
                           readonly>
                    <input type="hidden" 
                           id="penilai_id" 
                           name="penilai_id" 
                           value="<?= htmlspecialchars($penilai_id_otomatis) ?>" 
                           required>
                </div>
                
                <hr class="my-8">
            
                
             

                <h3 class="text-xl font-semibold text-gray-700 mb-4">Kategori Penilaian (Skala 1-4)</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="kualitas" class="form-label">Kualitas</label>
                        <input type="number" id="kualitas" name="kualitas" class="form-input" min="1" max="4" required>
                    </div>
                    <div>
                        <label for="volume_pemasukan" class="form-label">Volume Pemasukan</label>
                        <input type="number" id="volume_pemasukan" name="volume_pemasukan" class="form-input" min="1" max="4" required>
                    </div>
                    <div>
                        <label for="perilaku" class="form-label">Perilaku</label>
                        <input type="number" id="perilaku" name="perilaku" class="form-input" min="1" max="4" required>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="keterangan" class="form-label">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="form-textarea" rows="4"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <a href="penilaian_mitra.php" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Penilaian</button>
                </div>
            </form>
        </div>

        <div id="history-section" class="card mt-8" style="display: none;">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Riwayat Penilaian</h2>
            <div class="table-container">
                <table id="history-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Penilai</th>
                            <th>Kegiatan yang Dinilai</th>
                          
                            <th>Kualitas</th>
                            <th>Volume</th>
                            <th>Perilaku</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');

        if (status && message) {
            if (status === 'error') {
                alert('Error: ' + decodeURIComponent(message).replace(/\+/g, ' '));
            } else if (status === 'success') {
                alert('Success: ' + decodeURIComponent(message).replace(/\+/g, ' '));
            }
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    const penilaianHistory = <?= json_encode($penilaian_history_list); ?>;
    
    const searchInput = document.getElementById('mitra-kegiatan-search-input');
    const dropdown = document.getElementById('mitra-kegiatan-dropdown');
    const hiddenInput = document.getElementById('mitra_survey_id');
    const dropdownItems = dropdown.querySelectorAll('.select-dropdown-item');

    // Fungsionalitas pencarian
    searchInput.addEventListener('focus', () => { dropdown.style.display = 'block'; });
    searchInput.addEventListener('blur', () => { setTimeout(() => { dropdown.style.display = 'none'; }, 200); });
    searchInput.addEventListener('keyup', () => {
        const filter = searchInput.value.toLowerCase();
        dropdownItems.forEach(item => {
            const searchText = item.getAttribute('data-search-text');
            if (searchText.includes(filter)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
    });
    
    dropdownItems.forEach(item => {
        item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const id = item.getAttribute('data-id');
            const fullText = item.textContent.trim();
            searchInput.value = fullText;
            hiddenInput.value = id;
            dropdown.style.display = 'none';
            displayHistory(id); // Panggil fungsi riwayat
        });
    });

    function displayHistory(mitraSurveyId) {
        const historyTableBody = document.querySelector('#history-table tbody');
        const historySection = document.getElementById('history-section');
        historyTableBody.innerHTML = ''; // Kosongkan tabel
        
        const filteredHistory = penilaianHistory.filter(item => item.mitra_survey_id == mitraSurveyId);

        if (filteredHistory.length > 0) {
            filteredHistory.forEach(item => {
                const row = document.createElement('tr');
                // Update isi baris sesuai dengan data baru dari kueri riwayat
                row.innerHTML = `
                    <td>${item.tanggal_penilaian}</td>
                    <td>${item.nama_penilai}</td>
                    <td>${item.nama_kegiatan || 'N/A'}</td> 
                    <td>${item.kualitas}</td>
                    <td>${item.volume_pemasukan}</td>
                    <td>${item.perilaku}</td>
                    <td>${item.keterangan || '-'}</td>
                `;
                historyTableBody.appendChild(row);
            });
            historySection.style.display = 'block';
        } else {
            // Jika tidak ada riwayat, sembunyikan tabel
            historySection.style.display = 'none';
        }
    }
</script>

<?php 
// Tutup koneksi dan result set
if ($result_mitra_kegiatan) $result_mitra_kegiatan->close();
if ($result_penilaian_history) $result_penilaian_history->close();
$koneksi->close();
include '../includes/footer.php'; 
?>