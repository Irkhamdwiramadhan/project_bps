<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. Ambil daftar TIM (Hanya yang Aktif)
$sql_tim = "SELECT id, nama_tim FROM tim WHERE is_active = 1 ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);

$tim_list = [];
if ($result_tim && $result_tim->num_rows > 0) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}

// 2. Ambil daftar Tahun Unik dari Mitra untuk Filter
$sql_tahun = "SELECT DISTINCT tahun FROM mitra ORDER BY tahun DESC";
$result_tahun = $koneksi->query($sql_tahun);
$tahun_list = [];
if ($result_tahun) {
    while ($row = $result_tahun->fetch_assoc()) {
        $tahun_list[] = $row['tahun'];
    }
}
// Default tahun sekarang
$tahun_sekarang = date('Y');
?>

<style>
    /* CSS Tetap Sama + Tambahan untuk Counter */
    body { background-color: #e2e8f0; }
    .main-content { padding: 2rem; }
    .card { background-color: #ffffff; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #4a5568; }
    .form-select, .form-input { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; }
    .btn-primary { padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: bold; cursor: pointer; background-color: #3b82f6; color: #ffffff; border: none; }
    .btn-primary:hover { background-color: #2563eb; }
    .small-muted { font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem; }
    .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; }
    .alert-success { background-color: #dcfce7; color: #166534; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; }

    /* --- CSS DUAL LISTBOX --- */
    .dual-listbox-container { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1rem; }
    .dual-listbox-box { width: 45%; display: flex; flex-direction: column; }
    .dual-listbox-box .list-title { font-weight: 600; margin-bottom: 0.5rem; }
    .dual-listbox-box .search-input { margin-bottom: 0.5rem; }
    .dual-listbox-select { height: 400px; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.5rem; overflow-y: auto; }
    .dual-listbox-select option { padding: 0.5rem; border-radius: 0.25rem; }
    .dual-listbox-select option:hover { background-color: #eef2ff; }
    .dual-listbox-buttons { display: flex; flex-direction: column; justify-content: center; gap: 0.5rem; }
    .dual-listbox-buttons button { background-color: #f3f4f6; border: 1px solid #d1d5db; color: #374151; border-radius: 0.5rem; padding: 0.5rem 0.75rem; cursor: pointer; font-weight: 600; }
    .dual-listbox-buttons button:hover { background-color: #e5e7eb; }
    
    /* Counter Style */
    .list-counter { text-align: right; font-size: 0.85rem; color: #64748b; margin-top: 5px; font-weight: 600; }
</style>

<div class="main-content">
    <div class="card">
        <h3>Kelola Anggota Tim</h3>
        <p class="small-muted">Pilih tim, lalu pindahkan mitra dari daftar "Tersedia" ke "Terpilih".</p>

        <?php if (isset($_GET['status'])): ?>
            <div class="alert <?= $_GET['status'] == 'sukses' ? 'alert-success' : 'alert-danger'; ?>">
                <?= htmlspecialchars($_GET['message']); ?>
            </div>
        <?php endif; ?>

        <form action="../proses/proses_simpan_anggota_tim.php" method="POST" id="form-kelola-tim">
            
            <input type="hidden" name="mitra_terpilih_json" id="mitra_terpilih_json">

            <div class="form-group">
                <label for="tim_id">Pilih Tim</label>
                <select id="tim_id" name="tim_id" class="form-select" required>
                    <option value="">-- Pilih Tim --</option>
                    <?php foreach ($tim_list as $tim) : ?>
                        <option value="<?= htmlspecialchars($tim['id']) ?>">
                            <?= htmlspecialchars($tim['nama_tim']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="dual-listbox-wrapper" style="display: none;">
                
                <div class="dual-listbox-container">
                    <div class="dual-listbox-box">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <label class="list-title" for="search-tersedia" style="margin:0;">Mitra Tersedia</label>
                            
                            <select id="filter_tahun" style="padding: 2px 5px; font-size: 0.85rem; border: 1px solid #cbd5e1; border-radius: 4px;">
                                <?php foreach ($tahun_list as $thn) : ?>
                                    <option value="<?= $thn ?>" <?= ($thn == $tahun_sekarang) ? 'selected' : '' ?>>
                                        Tahun <?= $thn ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <input type="text" id="search-tersedia" class="form-input search-input" placeholder="Cari mitra tersedia...">
                        <select multiple id="list-tersedia" class="dual-listbox-select"></select>
                        <div class="list-counter">Total: <span id="count-tersedia">0</span></div>
                    </div>
                    
                    <div class="dual-listbox-buttons">
                        <button type="button" id="btn-add-all">&gt;&gt;</button>
                        <button type="button" id="btn-add">&gt;</button>
                        <button type="button" id="btn-remove">&lt;</button>
                        <button type="button" id="btn-remove-all">&lt;&lt;</button>
                    </div>
                    
                    <div class="dual-listbox-box">
                        <label class="list-title" for="search-terpilih" style="margin-bottom: 10px;">Anggota Tim Terpilih</label>
                        <input type="text" id="search-terpilih" class="form-input search-input" placeholder="Cari anggota terpilih...">
                        
                        <select multiple id="list-terpilih" class="dual-listbox-select"></select>
                        
                        <div class="list-counter">Total: <span id="count-terpilih">0</span></div>
                    </div>
                </div>

                <div style="margin-top: 2rem; text-align: right;">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Variabel Global
    let mitraPool = []; // Data mitra tersedia (sesuai tahun filter)
    let teamMembers = []; // Data anggota tim saat ini (bisa dari berbagai tahun)
    let anggotaIds = new Set(); // Set ID anggota terpilih (Single Source of Truth)

    const timSelect = document.getElementById('tim_id');
    const filterTahun = document.getElementById('filter_tahun');
    const listboxWrapper = document.getElementById('dual-listbox-wrapper');
    
    const listTersedia = document.getElementById('list-tersedia');
    const listTerpilih = document.getElementById('list-terpilih');
    
    const searchTersedia = document.getElementById('search-tersedia');
    const searchTerpilih = document.getElementById('search-terpilih');
    
    const countTersedia = document.getElementById('count-tersedia');
    const countTerpilih = document.getElementById('count-terpilih');

    const btnAdd = document.getElementById('btn-add');
    const btnAddAll = document.getElementById('btn-add-all');
    const btnRemove = document.getElementById('btn-remove');
    const btnRemoveAll = document.getElementById('btn-remove-all');
    const form = document.getElementById('form-kelola-tim');
    const inputHiddenJson = document.getElementById('mitra_terpilih_json'); // Referensi ke input hidden

    // --- FUNGSI: Load Mitra Tersedia by Tahun ---
    function fetchMitraTersedia(tahun) {
        listTersedia.innerHTML = '<option disabled>Memuat...</option>';
        
        fetch(`get_mitra_all.php?tahun=${tahun}`) 
            .then(res => res.json())
            .then(data => {
                mitraPool = data.map(m => ({
                    ...m,
                    id: parseInt(m.id, 10)
                }));
                populateLists();
            })
            .catch(err => {
                console.error('Gagal memuat mitra:', err);
                alert('Gagal memuat daftar mitra tersedia.');
            });
    }

    // --- FUNGSI: Load Anggota Tim ---
    function fetchAnggotaTim(timId) {
        listTerpilih.innerHTML = '<option disabled>Memuat...</option>';
        
        fetch(`get_mitra_by_tim.php?tim_id=${timId}`)
            .then(res => res.json())
            .then(data => {
                // Simpan data lengkap anggota tim
                teamMembers = data.map(m => ({
                    ...m,
                    id: parseInt(m.id, 10)
                }));
                
                // Update Set ID
                anggotaIds = new Set(teamMembers.map(m => m.id));
                
                // Load mitra tersedia
                fetchMitraTersedia(filterTahun.value);
            })
            .catch(err => {
                console.error('Gagal memuat anggota:', err);
                alert('Gagal memuat data anggota tim.');
            });
    }

    // --- FUNGSI: Populate (Render) List ---
    function populateLists() {
        const fragTersedia = document.createDocumentFragment();
        const fragTerpilih = document.createDocumentFragment();

        const qTersedia = searchTersedia.value.toLowerCase();
        const qTerpilih = searchTerpilih.value.toLowerCase();

        // Map ID -> Nama
        const nameMap = new Map();
        teamMembers.forEach(m => nameMap.set(m.id, m.nama_lengkap));
        mitraPool.forEach(m => nameMap.set(m.id, m.nama_lengkap));

        // 1. Render KANAN (Anggota Tim)
        let countRight = 0;
        anggotaIds.forEach(id => {
            const nama = nameMap.get(id) || `Mitra ID: ${id} (Nama tidak termuat)`;
            
            // Filter Search Kanan (Hanya visual, data asli tetap di Set anggotaIds)
            if (nama.toLowerCase().includes(qTerpilih)) {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = nama;
                fragTerpilih.appendChild(option);
                countRight++;
            }
        });

        // 2. Render KIRI (Tersedia)
        let countLeft = 0;
        mitraPool.forEach(mitra => {
            // Hanya tampilkan jika BELUM menjadi anggota
            if (!anggotaIds.has(mitra.id)) {
                if (mitra.nama_lengkap.toLowerCase().includes(qTersedia)) {
                    const option = document.createElement('option');
                    option.value = mitra.id;
                    option.textContent = mitra.nama_lengkap;
                    fragTersedia.appendChild(option);
                    countLeft++;
                }
            }
        });

        listTersedia.innerHTML = '';
        listTerpilih.innerHTML = '';
        listTersedia.appendChild(fragTersedia);
        listTerpilih.appendChild(fragTerpilih);
        
        countTersedia.textContent = countLeft;
        countTerpilih.textContent = countRight; // Menghitung total data asli di set, bukan cuma yg tampil? Sebaiknya yg tampil.
        // Koreksi: Kode di atas menghitung yg TAMPIL. Jika ingin total asli, gunakan anggotaIds.size
        // Tapi untuk search experience, menghitung yg tampil (filtered) lebih umum. Kita biarkan counter visual ini.
    }

    // --- EVENT LISTENERS ---

    timSelect.addEventListener('change', function() {
        const timId = this.value;
        if (!timId) {
            listboxWrapper.style.display = 'none';
            return;
        }
        listboxWrapper.style.display = 'block';
        fetchAnggotaTim(timId);
    });

    filterTahun.addEventListener('change', function() {
        fetchMitraTersedia(this.value);
    });

    searchTersedia.addEventListener('input', populateLists);
    searchTerpilih.addEventListener('input', populateLists);

    function moveSelectedItems(sourceList, destList, isAdding) {
        const selectedOptions = Array.from(sourceList.selectedOptions);
        if (selectedOptions.length === 0) return; 

        selectedOptions.forEach(option => {
            const id = parseInt(option.value); 
            
            if (isAdding) {
                anggotaIds.add(id);
                // Cache data mitra agar nama tidak hilang
                const mitraData = mitraPool.find(m => m.id === id);
                if(mitraData) {
                    if (!teamMembers.some(m => m.id === id)) {
                        teamMembers.push(mitraData);
                    }
                }
            } else {
                anggotaIds.delete(id);
            }
        });
        populateLists();
    }

    btnAdd.addEventListener('click', () => moveSelectedItems(listTersedia, listTerpilih, true));
    btnRemove.addEventListener('click', () => moveSelectedItems(listTerpilih, listTersedia, false));

    btnAddAll.addEventListener('click', () => {
        // Ambil semua opsi yg terlihat (filtered)
        Array.from(listTersedia.options).forEach(opt => {
            const id = parseInt(opt.value);
            anggotaIds.add(id);
            const mitraData = mitraPool.find(m => m.id === id);
            if(mitraData && !teamMembers.some(m => m.id === id)) teamMembers.push(mitraData);
        });
        populateLists();
    });
    
    btnRemoveAll.addEventListener('click', () => {
        // Hapus semua opsi yg terlihat (filtered)
        Array.from(listTerpilih.options).forEach(opt => anggotaIds.delete(parseInt(opt.value)));
        populateLists();
    });

    listTersedia.addEventListener('dblclick', () => moveSelectedItems(listTersedia, listTerpilih, true));
    listTerpilih.addEventListener('dblclick', () => moveSelectedItems(listTerpilih, listTersedia, false));

    // --- 5. PERBAIKAN LOGIC SUBMIT (PENTING) ---
    form.addEventListener('submit', function() {
        // Konversi Set ID ke Array
        const allSelectedIds = Array.from(anggotaIds);
        
        // Masukkan ke input hidden dalam bentuk string JSON
        inputHiddenJson.value = JSON.stringify(allSelectedIds);
        
        // Debugging di console (bisa dihapus nanti)
        // console.log("Data yang dikirim:", inputHiddenJson.value);
    });

});
</script>

<?php
include '../includes/footer.php';
?>