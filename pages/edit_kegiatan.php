<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. TANGKAP PARAMETER DARI URL
$old_tim_id       = $_GET['tim_id'] ?? '';
$old_keg_kode     = $_GET['kode_kegiatan'] ?? '';
$old_item_kode    = $_GET['item_kode'] ?? '';
$old_bulan        = $_GET['bulan'] ?? ''; 
$old_tahun        = $_GET['tahun'] ?? '';

if (!$old_tim_id || !$old_keg_kode || !$old_item_kode || !$old_bulan || !$old_tahun) {
    echo "<script>alert('Parameter tidak lengkap!'); window.location.href='rekap_kegiatan_tim.php';</script>";
    exit;
}

// 2. REVERSE LOOKUP (MENCARI ID HIERARKI DAN HARGA DARI MASTER ITEM)
$hierarki = [];
$tahun_anggaran = $old_tahun; 
$harga_item = 0; 

$item_q = $koneksi->query("SELECT * FROM master_item WHERE kode_unik = '$old_item_kode'");
if ($item = $item_q->fetch_assoc()) {
    $harga_item = $item['harga']; 
    $hierarki['item_kode'] = $item['kode_unik'];
    $hierarki['satuan'] = $item['satuan'];
    $hierarki['akun_id'] = $item['akun_id'];
    
    $akun_q = $koneksi->query("SELECT * FROM master_akun WHERE id = '{$item['akun_id']}'");
    if ($akun = $akun_q->fetch_assoc()) {
        $hierarki['sub_komponen_id'] = $akun['sub_komponen_id'];
        
        $sub_komp_q = $koneksi->query("SELECT * FROM master_sub_komponen WHERE id = '{$akun['sub_komponen_id']}'");
        if ($sub_komp = $sub_komp_q->fetch_assoc()) {
            $hierarki['komponen_id'] = $sub_komp['komponen_id'];
            
            $komp_q = $koneksi->query("SELECT * FROM master_komponen WHERE id = '{$sub_komp['komponen_id']}'");
            if ($komp = $komp_q->fetch_assoc()) {
                $hierarki['sub_output_id'] = $komp['sub_output_id'];
                
                $sub_out_q = $koneksi->query("SELECT * FROM master_sub_output WHERE id = '{$komp['sub_output_id']}'");
                if ($sub_out = $sub_out_q->fetch_assoc()) {
                    $hierarki['output_id'] = $sub_out['output_id'];
                    
                    $out_q = $koneksi->query("SELECT * FROM master_output WHERE id = '{$sub_out['output_id']}'");
                    if ($out = $out_q->fetch_assoc()) {
                        $hierarki['kegiatan_id'] = $out['kegiatan_id'];
                        
                        $keg_q = $koneksi->query("SELECT * FROM master_kegiatan WHERE id = '{$out['kegiatan_id']}'");
                        if ($keg = $keg_q->fetch_assoc()) {
                            $hierarki['program_id'] = $keg['program_id'];
                            $tahun_anggaran = $keg['tahun'];
                        }
                    }
                }
            }
        }
    }
}

// 3. AMBIL DATA EKSISTING DARI DATABASE (SUDAH DISESUAIKAN DENGAN NAMA KOLOM ANDA)
$sql_exist = "
    SELECT ms.*, hm.id as hm_id, hm.mitra_id, hm.jumlah_satuan, hm.honor_per_satuan, hm.total_honor, m.nama_lengkap
    FROM honor_mitra hm
    JOIN mitra_surveys ms ON hm.mitra_survey_id = ms.id
    JOIN mitra m ON hm.mitra_id = m.id
    WHERE ms.tim_id = ? AND ms.kegiatan_id = ? AND hm.item_kode_unik = ? 
      AND (hm.bulan_pembayaran = ? OR hm.bulan_pembayaran = ?) AND hm.tahun_pembayaran = ?
";
$stmt = $koneksi->prepare($sql_exist);

$bulan_str = str_pad($old_bulan, 2, '0', STR_PAD_LEFT);
$bulan_int = (int)$old_bulan;

$stmt->bind_param("issssi", $old_tim_id, $old_keg_kode, $old_item_kode, $bulan_str, $bulan_int, $old_tahun);
$stmt->execute();
$res_exist = $stmt->get_result();

$existing_mitras = [];
$main_data = null;

while ($row = $res_exist->fetch_assoc()) {
    if (!$main_data) {
        $main_data = $row; 
    }
    $existing_mitras[] = $row;
}
$stmt->close();

if (!$main_data) {
    echo "<script>alert('Data tidak ditemukan!'); window.location.href='rekap_kegiatan_tim.php';</script>";
    exit;
}

function getOptions($koneksi, $table, $where_col, $where_val) {
    $html = "<option value=''>-- Pilih --</option>";
    $q = $koneksi->query("SELECT * FROM $table WHERE $where_col = '$where_val'");
    while ($r = $q->fetch_assoc()) {
        if ($table == 'master_item') {
            $html .= "<option value='{$r['kode_unik']}' data-harga='{$r['harga']}' data-satuan='{$r['satuan']}'>{$r['nama_item']} ({$r['satuan']})</option>";
        } else {
            $nama = $r['nama'] ?? '';
            $kode = $r['kode'] ?? '';
            $html .= "<option value='{$r['id']}'>$kode - $nama</option>";
        }
    }
    return $html;
}

$tim_list = $koneksi->query("SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC")->fetch_all(MYSQLI_ASSOC);
$mitra_list = $koneksi->query("SELECT id, nama_lengkap FROM mitra ORDER BY nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    body { background-color: #e2e8f0; } .main-content { padding: 2rem; } .card { background-color: #ffffff; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    .form-group { margin-bottom: 1.5rem; } label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #4a5568; }
    .form-input, .form-select, .select-search-input { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; background-color: #f7fafc; transition: all 0.2s; }
    .form-input:disabled, .form-select:disabled, .form-input[disabled] { background-color: #e9ecef; opacity: 0.7; cursor: not-allowed; }
    .select-search-container { position: relative; } .select-search-dropdown { position: absolute; top: 100%; left: 0; right: 0; z-index: 20; background-color: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); display: none; } .select-search-dropdown-item { padding: 0.75rem 1rem; cursor: pointer; } .select-search-dropdown-item:hover { background-color: #eef2ff; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
    .btn-group { margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end; } .btn-primary { background-color: #3b82f6; color: #fff; padding: 0.75rem 1.5rem; border-radius: 0.5rem; border:none; cursor: pointer; font-weight: bold;} .btn-secondary { background-color: #e5e7eb; color: #4b5563; padding: 0.75rem 1.5rem; border-radius: 0.5rem; border:none; cursor: pointer; text-decoration: none; font-weight: bold;}
    .mitra-input-group { display: flex; gap: 10px; align-items: center; } .input-wrapper-mitra { flex-grow: 1; } .input-wrapper-jumlah { width: 300px; flex-shrink: 0; } .btn-danger { background-color: #dc3545; color: #fff; padding: 0.5rem 0.8rem; border:none; border-radius:4px; cursor:pointer;} .btn-add-mitra { background-color: #28a745; color: #fff; padding: 0.5rem 1rem; border:none; border-radius:4px; cursor:pointer;}
    .date-grid-container { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem; } .date-column { display: flex; flex-direction: column; } .date-header { font-size: 1rem; font-weight: 700; color: #1e293b; border-bottom: 2px solid #cbd5e1; padding-bottom: 0.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .checkbox-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; } .checkbox-item { display: flex; align-items: center; background: white; padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1; cursor: pointer; font-size: 0.85rem; }
    .small-muted { font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem; } .honor-status { font-size: 0.875rem; margin-top: 0.5rem; font-weight: 500; color: #dc2626; margin-left: 10px;}
    .checkbox-container { display: flex; align-items: center; position: relative; padding-left: 30px; cursor: pointer; font-size: 1rem; user-select: none; } .checkbox-container input { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; } .checkbox-container .checkmark { position: absolute; left: 0; top: 50%; transform: translateY(-50%); height: 20px; width: 20px; background-color: #f0f0f0; border: 2px solid #cbd5e0; border-radius: 4px; transition: all 0.2s; } .checkbox-container:hover .checkmark { border-color: #6366f1; } .checkbox-container input:checked ~ .checkmark { background-color: #6366f1; border-color: #6366f1; } .checkbox-container .checkmark:after { content: ""; position: absolute; display: none; left: 6px; top: 2px; width: 5px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); } .checkbox-container input:checked ~ .checkmark:after { display: block; }
    #mitra-otomatis-list { border: 1px solid #e2e8f0; border-radius: 0.5rem; background-color: #f8fafc; max-height: 400px; overflow-y: auto; padding: 1rem; } .mitra-otomatis-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; transition: all 0.3s; } .mitra-otomatis-item.deleted { background-color: #ffe4e6; opacity: 0.6; } .mitra-otomatis-item.deleted span { text-decoration: line-through; color: #991b1b; } .btn-action-item { background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 4px; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer; margin-left: 10px;} .btn-action-item.restore { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } .search-filter-box { margin-bottom: 10px; position: relative; } .search-filter-input { width: 100%; padding: 0.5rem 0.5rem 0.5rem 2.5rem; border: 1px solid #cbd5e1; border-radius: 0.5rem; font-size: 0.9rem; } .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
</style>

<div class="main-content">
    <div class="card">
        <h3>Edit Kegiatan Mitra</h3>
        <p class="text-sm text-gray-500" style="color:#d97706; font-weight:bold;">
            Peringatan: Menyimpan form ini akan memperbarui data kegiatan dan honor mitra.
        </p>

        <form action="../proses/proses_edit_kegiatan.php" method="POST" id="kegiatan-form">
            
            <input type="hidden" name="old_tim_id" value="<?= htmlspecialchars($old_tim_id) ?>">
            <input type="hidden" name="old_keg_kode" value="<?= htmlspecialchars($old_keg_kode) ?>">
            <input type="hidden" name="old_item_kode" value="<?= htmlspecialchars($old_item_kode) ?>">
            <input type="hidden" name="old_bulan" value="<?= htmlspecialchars($old_bulan) ?>">
            <input type="hidden" name="old_tahun" value="<?= htmlspecialchars($old_tahun) ?>">

            <div class="grid">
                <input type="hidden" id="tim_id_hidden" name="tim_id" value="<?= $main_data['tim_id'] ?>">

                <div class="form-group">
                    <label for="tim_id">Pilih Tim / Kelompok Mitra</label>
                    <select id="tim_id" name="tim_id_display" class="form-select" required>
                        <option value="">-- Pilih Tim --</option>
                        <?php foreach ($tim_list as $tim) : ?>
                            <option value="<?= $tim['id'] ?>" <?= ($tim['id'] == $main_data['tim_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tim['nama_tim']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tahun_anggaran">Tahun Anggaran</label>
                    <select id="tahun_anggaran" name="tahun_anggaran" class="form-select" required>
                        <option value="<?= $tahun_anggaran ?>"><?= $tahun_anggaran ?></option>
                    </select>
                </div>

                <div class="form-group"><label>Program</label><select id="program_id" name="program_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_program', 'tahun', $tahun_anggaran) ?>
                        <script>document.getElementById('program_id').value = "<?= $hierarki['program_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Kegiatan</label><select id="kegiatan_id" name="kegiatan_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_kegiatan', 'program_id', $hierarki['program_id'] ?? '') ?>
                        <script>document.getElementById('kegiatan_id').value = "<?= $hierarki['kegiatan_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Output</label><select id="output_id" name="output_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_output', 'kegiatan_id', $hierarki['kegiatan_id'] ?? '') ?>
                        <script>document.getElementById('output_id').value = "<?= $hierarki['output_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Sub Output</label><select id="sub_output_id" name="sub_output_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_sub_output', 'output_id', $hierarki['output_id'] ?? '') ?>
                        <script>document.getElementById('sub_output_id').value = "<?= $hierarki['sub_output_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Komponen</label><select id="komponen_id" name="komponen_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_komponen', 'sub_output_id', $hierarki['sub_output_id'] ?? '') ?>
                        <script>document.getElementById('komponen_id').value = "<?= $hierarki['komponen_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Sub Komponen</label><select id="sub_komponen_id" name="sub_komponen_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_sub_komponen', 'komponen_id', $hierarki['komponen_id'] ?? '') ?>
                        <script>document.getElementById('sub_komponen_id').value = "<?= $hierarki['sub_komponen_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Akun</label><select id="akun_id" name="akun_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_akun', 'sub_komponen_id', $hierarki['sub_komponen_id'] ?? '') ?>
                        <script>document.getElementById('akun_id').value = "<?= $hierarki['akun_id'] ?? '' ?>";</script>
                    </select></div>
                <div class="form-group"><label>Item</label><select id="item_id" name="item_id" class="form-select" required>
                        <?= getOptions($koneksi, 'master_item', 'akun_id', $hierarki['akun_id'] ?? '') ?>
                        <script>document.getElementById('item_id').value = "<?= $hierarki['item_kode'] ?? '' ?>";</script>
                    </select></div>
                
                <div class="form-group">
                    <label for="harga_per_satuan">Harga Honor (Otomatis dari Item)</label>
                    <input type="number" id="harga_per_satuan" name="harga_per_satuan" class="form-input" value="<?= $harga_item ?>" readonly required>
                </div>
                <div class="form-group">
                    <label for="satuan_item">Satuan</label>
                    <input type="text" id="satuan_item" class="form-input" value="<?= $hierarki['satuan'] ?? '' ?>" readonly>
                </div>
            </div>

            <div class="date-grid-container">
                <div class="date-column">
                    <div class="date-header">📅 1. Waktu Pelaksanaan</div>

                    <div class="form-group">
                        <label class="small-muted">Tipe Periode:</label>
                        <select id="trigger_periode" name="periode_jenis" class="form-select" required>
                            <option value="bulanan" <?= $main_data['periode_jenis'] == 'bulanan' ? 'selected' : '' ?>>Bulanan (Multi)</option>
                            <option value="triwulan" <?= $main_data['periode_jenis'] == 'triwulan' ? 'selected' : '' ?>>Triwulan</option>
                            <option value="subron" <?= $main_data['periode_jenis'] == 'subron' ? 'selected' : '' ?>>Sub-Round</option>
                            <option value="tahunan" <?= $main_data['periode_jenis'] == 'tahunan' ? 'selected' : '' ?>>Tahunan</option>
                        </select>
                    </div>

                    <?php 
                    $arr_periode = explode(',', $main_data['periode_nilai']); 
                    ?>
                    
                    <div id="periode-bulanan-wrapper" style="display:<?= $main_data['periode_jenis']=='bulanan'?'block':'none' ?>;">
                        <label class="small-muted">Centang Bulan Pelaksanaan:</label>
                        <div class="checkbox-grid">
                            <?php for($i=1; $i<=12; $i++): $m=str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                <label class="checkbox-item"><input type="checkbox" name="periode_nilai_bulanan[]" value="<?= $m ?>" <?= in_array($m, $arr_periode)?'checked':'' ?>> <?= date('M', mktime(0,0,0,$i,1)) ?></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="periode-triwulan-wrapper" style="display:<?= $main_data['periode_jenis']=='triwulan'?'block':'none' ?>;">
                        <label class="small-muted">Centang Triwulan:</label>
                        <div class="checkbox-grid">
                            <?php for($i=1; $i<=4; $i++): ?>
                                <label class="checkbox-item"><input type="checkbox" name="periode_nilai_triwulan[]" value="<?= $i ?>" <?= in_array($i, $arr_periode)?'checked':'' ?>> TW <?= $i ?></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="periode-subron-wrapper" style="display:<?= $main_data['periode_jenis']=='subron'?'block':'none' ?>;">
                        <label class="small-muted">Centang Sub-Round:</label>
                        <div class="checkbox-grid">
                            <?php for($i=1; $i<=3; $i++): ?>
                                <label class="checkbox-item"><input type="checkbox" name="periode_nilai_subron[]" value="<?= $i ?>" <?= in_array($i, $arr_periode)?'checked':'' ?>> SR <?= $i ?></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="periode-tahunan-wrapper" style="display:<?= $main_data['periode_jenis']=='tahunan'?'block':'none' ?>;">
                        <label class="small-muted">Tahun:</label>
                        <input type="number" name="periode_nilai_tahunan" class="form-input" value="<?= $main_data['periode_jenis']=='tahunan' ? $main_data['periode_nilai'] : date('Y') ?>">
                    </div>
                </div>

                <div class="date-column">
                    <div class="date-header">💰 2. Waktu Pembayaran</div>
                    <div class="form-group">
                        <label class="small-muted">Tahun Bayar:</label>
                        <input type="number" id="tahun_pembayaran" name="tahun_pembayaran" class="form-input" value="<?= $old_tahun ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="small-muted">Centang Bulan Pencairan:</label>
                        <div class="checkbox-grid">
                            <?php for($i=1; $i<=12; $i++): $m=str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                <label class="checkbox-item"><input type="checkbox" name="bulan_pembayaran[]" value="<?= $m ?>" <?= ($m == str_pad($old_bulan, 2, '0', STR_PAD_LEFT)) ? 'checked' : '' ?>> <?= date('M', mktime(0,0,0,$i,1)) ?></label>
                            <?php endfor; ?>
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
                        &nbsp; <strong>Load Ulang Anggota Tim secara Otomatis? (Akan mereset list di bawah)</strong>
                    </label>
                </div>
            </div>

            <div id="mitra-manual-container">
                <div class="form-group">
                    <label class="form-label">Nama Mitra dan Jumlah Satuan Tersimpan</label>
                    <div id="mitra-container">
                        
                        <?php 
                        $counter = 0;
                        foreach($existing_mitras as $em): 
                        ?>
                        <div class="mitra-input-group mb-2" data-id="<?= $counter ?>">
                            <div class="select-search-container input-wrapper-mitra">
                                <input type="text" id="mitra-search-input-<?= $counter ?>" class="select-search-input manual-input" value="<?= htmlspecialchars($em['nama_lengkap']) ?>" placeholder="Cari Nama Mitra..." autocomplete="off" required>
                                <input type="hidden" name="mitra_id[]" id="mitra_id-<?= $counter ?>" value="<?= $em['mitra_id'] ?>" class="manual-input">
                                <div id="mitra-dropdown-<?= $counter ?>" class="select-search-dropdown">
                                    <?php foreach ($mitra_list as $mitra) : ?>
                                        <div class="select-search-dropdown-item" data-id="<?= htmlspecialchars($mitra['id']) ?>" data-name="<?= htmlspecialchars($mitra['nama_lengkap']) ?>">
                                            <?= htmlspecialchars($mitra['nama_lengkap']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="input-wrapper-jumlah">
                                <input type="number" name="jumlah_satuan[]" class="form-input jumlah-satuan-input manual-input" value="<?= $em['jumlah_satuan'] ?>" required min="1">
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeMitraInput(this)">-</button>
                        </div>
                        <?php 
                        $counter++;
                        endforeach; 
                        ?>

                    </div>
                    <button type="button" class="btn-add-mitra mt-2" onclick="addMitraInput()">+ Tambah Mitra Baru</button>
                </div>
            </div>

            <div id="mitra-otomatis-container" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Daftar Mitra Otomatis (Jumlah Dapat Diedit)</label>
                    <div class="search-filter-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="filter-mitra-otomatis" class="search-filter-input" placeholder="Ketik nama untuk memfilter list di bawah..." autocomplete="off">
                    </div>
                    <div id="mitra-otomatis-list"></div>
                    <div class="small-muted" style="margin-top: 5px; text-align: right;">
                        Total Mitra Terpilih: <span id="count-mitra-otomatis">0</span>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <a href="rekap_kegiatan_tim.php" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Update Kegiatan</button>
            </div>
        </form>
    </div>
</div>

<script>
    let mitraCounter = <?= $counter ?>;
    let honorStatus = {};
    const mitraListJson = <?= json_encode($mitra_list) ?>;

    function setupSelectSearch(input, dropdown, hidden, isMitra) {
        const items = dropdown.querySelectorAll('.select-search-dropdown-item');
        input.addEventListener('focus', () => { dropdown.style.display = 'block'; });
        input.addEventListener('blur', () => { setTimeout(() => dropdown.style.display = 'none', 150); });
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
                    if (oldMitraId && oldMitraId !== selectedId) { delete honorStatus[oldMitraId]; }
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
                    if (jumlahInput) jumlahInput.addEventListener('input', () => validateHonor(parentGroup));
                    validateHonor(parentGroup);
                }
            });
        });
    }

    function validateHonor(parentGroup) {
        const mitraIdEl = parentGroup.querySelector('[name="mitra_id[]"]');
        const jumlahSatuanEl = parentGroup.querySelector('[name="jumlah_satuan[]"]');
        if (!mitraIdEl || !jumlahSatuanEl || mitraIdEl.disabled) return;

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
            statusElement.textContent = "✅ Sensus"; statusElement.style.color = "blue";
            honorStatus[mitraId] = true; return;
        }

        if (!mitraId || !jumlahSatuan || jumlahSatuan < 1) { statusElement.textContent = ""; return; }
        if (!bulanPembayaran || !tahunPembayaran || hargaPerSatuan === 0) {
            statusElement.textContent = "Lengkapi Data.."; statusElement.style.color = "#ca8a04"; return;
        }

        const totalHonor = hargaPerSatuan * parseInt(jumlahSatuan);
        statusElement.textContent = "Cek..."; statusElement.style.color = "#6b7280";

        fetch('check_honor_limit.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mitraId: mitraId, currentTotalHonor: totalHonor, bulanPembayaran: bulanPembayaran, tahunPembayaran: tahunPembayaran })
        })
        .then(res => res.json())
        .then(data => {
            if (data.exceeds) {
                statusElement.textContent = "⚠️ Melebihi Batas"; statusElement.style.color = "red"; honorStatus[mitraId] = false;
            } else {
                statusElement.textContent = "✅ Honor Aman"; statusElement.style.color = "green"; honorStatus[mitraId] = true;
            }
        });
    }

    function getSelectedPaymentMonth() {
        const checkboxes = document.querySelectorAll('input[name="bulan_pembayaran[]"]:checked');
        return checkboxes.length > 0 ? checkboxes[0].value : '';
    }

    function addMitraInput() {
        const container = document.getElementById('mitra-container');
        const newMitraGroup = document.createElement('div');
        newMitraGroup.className = 'mitra-input-group mb-2';
        newMitraGroup.dataset.id = mitraCounter;
        
        let dropdownHtml = '';
        mitraListJson.forEach(m => { dropdownHtml += `<div class="select-search-dropdown-item" data-id="${m.id}" data-name="${m.nama_lengkap}">${m.nama_lengkap}</div>`; });

        newMitraGroup.innerHTML = `
        <div class="select-search-container input-wrapper-mitra">
            <input type="text" id="mitra-search-input-${mitraCounter}" class="select-search-input manual-input" placeholder="Cari Nama Mitra..." autocomplete="off" required>
            <input type="hidden" name="mitra_id[]" id="mitra_id-${mitraCounter}" class="manual-input">
            <div id="mitra-dropdown-${mitraCounter}" class="select-search-dropdown">${dropdownHtml}</div>
        </div>
        <div class="input-wrapper-jumlah">
            <input type="number" name="jumlah_satuan[]" class="form-input jumlah-satuan-input manual-input" placeholder="Jumlah Satuan" required min="1">
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeMitraInput(this)">-</button>
        `;
        container.appendChild(newMitraGroup);
        setupSelectSearch(document.getElementById(`mitra-search-input-${mitraCounter}`), document.getElementById(`mitra-dropdown-${mitraCounter}`), document.getElementById(`mitra_id-${mitraCounter}`), true);
        newMitraGroup.querySelector('.jumlah-satuan-input').addEventListener('input', () => validateHonor(newMitraGroup));
        mitraCounter++;
    }

    function removeMitraInput(button) { button.closest('.mitra-input-group').remove(); }

    document.addEventListener('DOMContentLoaded', function() {
        for(let i=0; i < <?= $counter ?>; i++) {
            let inp = document.getElementById('mitra-search-input-'+i);
            let drop = document.getElementById('mitra-dropdown-'+i);
            let hid = document.getElementById('mitra_id-'+i);
            let jum = document.querySelector(`[data-id="${i}"] .jumlah-satuan-input`);
            if(inp && drop && hid) setupSelectSearch(inp, drop, hid, true);
            if(jum) jum.addEventListener('input', () => validateHonor(inp.closest('.mitra-input-group')));
        }

        const triggerPeriodeSelect = document.getElementById('trigger_periode');
        const periodeWrappers = { bulanan: document.getElementById('periode-bulanan-wrapper'), triwulan: document.getElementById('periode-triwulan-wrapper'), subron: document.getElementById('periode-subron-wrapper'), tahunan: document.getElementById('periode-tahunan-wrapper') };
        const periodeInputs = document.querySelectorAll('.periode-input');

        triggerPeriodeSelect.addEventListener('change', function() {
            const tipe = this.value;
            Object.values(periodeWrappers).forEach(w => w.style.display = 'none');
            periodeInputs.forEach(i => { i.disabled = true; i.required = false; });
            if (periodeWrappers[tipe]) {
                periodeWrappers[tipe].style.display = 'block';
                const inputDiDalam = periodeWrappers[tipe].querySelector('.periode-input');
                if (inputDiDalam) { inputDiDalam.disabled = false; inputDiDalam.required = true; }
            }
        });

        const programSelect = document.getElementById('program_id');
        const kegiatanSelect = document.getElementById('kegiatan_id');
        const outputSelect = document.getElementById('output_id');
        const subOutputSelect = document.getElementById('sub_output_id');
        const komponenSelect = document.getElementById('komponen_id');
        const subKomponenSelect = document.getElementById('sub_komponen_id');
        const akunSelect = document.getElementById('akun_id');
        const itemSelect = document.getElementById('item_id');
        const hargaInput = document.getElementById('harga_per_satuan');
        const satuanInput = document.getElementById('satuan_item');
        const toggleOtomatis = document.getElementById('toggle_otomatis');
        const timSelect = document.getElementById('tim_id');
        const timIdHidden = document.getElementById('tim_id_hidden');
        const mitraManualContainer = document.getElementById('mitra-manual-container');
        const mitraOtomatisContainer = document.getElementById('mitra-otomatis-container');
        const mitraOtomatisList = document.getElementById('mitra-otomatis-list');
        const manualInputFields = document.querySelectorAll('.manual-input');
        const filterInputOtomatis = document.getElementById('filter-mitra-otomatis');
        const countDisplay = document.getElementById('count-mitra-otomatis');

        function updateMode() {
            const isOtomatis = toggleOtomatis.checked;
            const timId = timSelect.value;
            timIdHidden.value = timId;

            if (isOtomatis && !timId) { alert("Pilih Tim terlebih dahulu."); toggleOtomatis.checked = false; return; }
            if (isOtomatis) {
                mitraManualContainer.style.display = 'none'; mitraOtomatisContainer.style.display = 'block';
                manualInputFields.forEach(f => { f.required = false; f.disabled = true; });
                mitraOtomatisList.innerHTML = '<p><i>Memuat...</i></p>';
                fetch(`get_mitra_by_tim.php?tim_id=${timId}`).then(res => res.json()).then(data => {
                    mitraOtomatisList.innerHTML = '';
                    if (data.length === 0) { mitraOtomatisList.innerHTML = '<p style="color: red;">Tidak ada mitra.</p>'; updateCountMitra(); return; }
                    const fragment = document.createDocumentFragment();
                    data.forEach(mitra => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'mitra-otomatis-item';
                        itemDiv.innerHTML = `<span>${mitra.nama_lengkap}</span><input type="hidden" name="mitra_id[]" value="${mitra.id}"><div style="display:flex; align-items:center;"><div class="mitra-otomatis-jumlah"><input type="number" name="jumlah_satuan[]" value="1" class="mitra-otomatis-input" required min="1"></div><button type="button" class="btn-action-item" title="Hapus/Restore">✕</button></div>`;
                        const inputJumlah = itemDiv.querySelector('.mitra-otomatis-input');
                        const toggleBtn = itemDiv.querySelector('.btn-action-item');
                        const inputId = itemDiv.querySelector('input[name="mitra_id[]"]');
                        inputJumlah.addEventListener('input', () => validateHonor(itemDiv));
                        toggleBtn.addEventListener('click', function() {
                            if (itemDiv.classList.contains('deleted')) { itemDiv.classList.remove('deleted'); inputId.disabled = false; inputJumlah.disabled = false; this.innerHTML = '✕'; this.classList.remove('restore'); validateHonor(itemDiv); } 
                            else { itemDiv.classList.add('deleted'); inputId.disabled = true; inputJumlah.disabled = true; this.innerHTML = '↺'; this.classList.add('restore'); const status = itemDiv.querySelector('.honor-status'); if(status) status.textContent=''; }
                            updateCountMitra();
                        });
                        validateHonor(itemDiv); fragment.appendChild(itemDiv);
                    });
                    mitraOtomatisList.appendChild(fragment); updateCountMitra();
                });
            } else {
                mitraManualContainer.style.display = 'block'; mitraOtomatisContainer.style.display = 'none'; mitraOtomatisList.innerHTML = '';
                manualInputFields.forEach(f => { f.required = true; f.disabled = false; });
            }
        }
        function updateCountMitra() { const count = mitraOtomatisList.querySelectorAll('.mitra-otomatis-item:not(.deleted)').length; if (countDisplay) countDisplay.textContent = count; }
        if (filterInputOtomatis) filterInputOtomatis.addEventListener('input', function() { const v = this.value.toLowerCase(); mitraOtomatisList.querySelectorAll('.mitra-otomatis-item').forEach(i => { i.style.display = i.querySelector('span').textContent.toLowerCase().includes(v) ? 'flex' : 'none'; }); });
        
        timSelect.addEventListener('change', function() { timIdHidden.value = this.value; if (this.value === "" && toggleOtomatis.checked) { toggleOtomatis.checked = false; updateMode(); } else if (toggleOtomatis.checked) { updateMode(); } });
        toggleOtomatis.addEventListener('change', updateMode);

        programSelect.addEventListener('change', () => { fetch(`get_data.php?type=kegiatan&program_id=${programSelect.value}`).then(res => res.json()).then(data => { kegiatanSelect.innerHTML = '<option value="">-- Pilih Kegiatan --</option>'; data.forEach(d => { kegiatanSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`; }); kegiatanSelect.disabled = false; }); });
        kegiatanSelect.addEventListener('change', () => { fetch(`get_data.php?type=output&kegiatan_id=${kegiatanSelect.value}`).then(res => res.json()).then(data => { outputSelect.innerHTML = '<option value="">-- Pilih Output --</option>'; data.forEach(d => { outputSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`; }); outputSelect.disabled = false; }); });
        outputSelect.addEventListener('change', () => { fetch(`get_data.php?type=sub_output&output_id=${outputSelect.value}`).then(res => res.json()).then(data => { subOutputSelect.innerHTML = '<option value="">-- Pilih Sub Output --</option>'; data.forEach(d => { subOutputSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`; }); subOutputSelect.disabled = false; }); });
        subOutputSelect.addEventListener('change', () => { fetch(`get_data.php?type=komponen&sub_output_id=${subOutputSelect.value}`).then(res => res.json()).then(data => { komponenSelect.innerHTML = '<option value="">-- Pilih Komponen --</option>'; data.forEach(d => { komponenSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`; }); komponenSelect.disabled = false; }); });
        komponenSelect.addEventListener('change', () => { fetch(`get_data.php?type=sub_komponen&komponen_id=${komponenSelect.value}`).then(res => res.json()).then(data => { subKomponenSelect.innerHTML = '<option value="">-- Pilih Sub Komponen --</option>'; data.forEach(d => { subKomponenSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`; }); subKomponenSelect.disabled = false; }); });
        subKomponenSelect.addEventListener('change', () => { fetch(`get_data.php?type=akun&sub_komponen_id=${subKomponenSelect.value}`).then(res => res.json()).then(data => { akunSelect.innerHTML = '<option value="">-- Pilih Akun --</option>'; data.forEach(d => { akunSelect.innerHTML += `<option value="${d.id}">${d.kode} - ${d.nama}</option>`; }); akunSelect.disabled = false; }); });
        akunSelect.addEventListener('change', () => { fetch(`get_data.php?type=item&akun_id=${akunSelect.value}`).then(res => res.json()).then(data => { itemSelect.innerHTML = '<option value="">-- Pilih Item --</option>'; data.forEach(d => { itemSelect.innerHTML += `<option value="${d.kode_unik}" data-harga="${d.harga}" data-satuan="${d.satuan}">${d.nama_item} (${d.satuan})</option>`; }); itemSelect.disabled = false; }); });

        itemSelect.addEventListener('change', () => {
            const selected = itemSelect.options[itemSelect.selectedIndex];
            hargaInput.value = selected.getAttribute('data-harga') || '';
            document.getElementById('satuan_item').value = selected.getAttribute('data-satuan') || '';
        });
    });
</script>

<?php include '../includes/footer.php'; ?>