<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil daftar Tim untuk filter (Digunakan di semua form)
$sql_tim = "SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC";
$result_tim = $koneksi->query($sql_tim);
$tim_list = [];
if ($result_tim) {
    while ($row = $result_tim->fetch_assoc()) {
        $tim_list[] = $row;
    }
}
?>

<style>
    body { background-color: #f1f5f9; }
    .main-content { padding: 2rem; }
    
    /* Card Styling */
    .report-card { 
        background-color: #ffffff; 
        border-radius: 1rem; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
        height: 100%;
        transition: transform 0.2s;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .report-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
    
    /* Header Card Colors */
    .card-header { padding: 1.5rem; color: white; text-align: center; }
    .header-honor { background: linear-gradient(135deg, #10b981, #059669); } /* Hijau */
    .header-mitra { background: linear-gradient(135deg, #3b82f6, #2563eb); } /* Biru */
    .header-nilai { background: linear-gradient(135deg, #f59e0b, #d97706); } /* Orange */

    .card-body { padding: 2rem; }
    
    .card-icon { font-size: 2.5rem; margin-bottom: 0.5rem; display: block; }
    .card-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0; }

    /* Form Elements */
    .form-group { margin-bottom: 1.2rem; }
    label { display: block; margin-bottom: 0.4rem; font-weight: 600; color: #475569; font-size: 0.9rem; }
    .form-select, .form-input { 
        width: 100%; padding: 0.6rem; 
        border: 1px solid #cbd5e1; border-radius: 0.5rem; 
        font-size: 0.9rem;
    }
    
    /* Multi Select */
    select[multiple] { height: 120px; background-color: #f8fafc; }
    .help-text { font-size: 0.75rem; color: #64748b; margin-top: 4px; font-style: italic; }

    /* Buttons */
    .btn-submit { 
        width: 100%; padding: 0.75rem; border-radius: 0.5rem; 
        font-weight: bold; border: none; cursor: pointer; color: white; 
        margin-top: 1rem; transition: opacity 0.2s;
    }
    .btn-submit:hover { opacity: 0.9; }
    .btn-honor { background-color: #059669; }
    .btn-mitra { background-color: #2563eb; }
    .btn-nilai { background-color: #d97706; }

    /* Grid Layout */
    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
    }
</style>

<div class="main-content">
    <h2 style="margin-bottom: 2rem; font-size: 1.8rem; font-weight: 800; color: #1e293b; text-align: center;">
        <i class="fas fa-print mr-2"></i> Pusat Cetak Laporan
    </h2>

    <div class="report-grid">
        
        <div class="report-card">
            <div class="card-header header-honor">
                <i class="fas fa-money-bill-wave card-icon"></i>
                <h3 class="card-title">Rekap Honor</h3>
            </div>
            <div class="card-body">
                <form action="../proses/export_excel_rekap.php" method="POST">
                    
                    <div class="form-group">
                        <label>Bulan Pembayaran</label>
                        <select name="bulan" class="form-select" required>
                            <option value="">-- Pilih Bulan --</option>
                            <?php 
                            $bulanIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                            foreach($bulanIndo as $k => $v) {
                                echo "<option value='$k'>$v</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tahun Anggaran</label>
                        <input type="number" name="tahun" class="form-input" value="<?= date('Y') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Tim Pelaksana</label>
                        <select name="tim_id[]" class="form-select" multiple required>
                            <?php foreach ($tim_list as $tim) : ?>
                                <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-text">* Tahan CTRL untuk memilih banyak tim.</p>
                    </div>

                    <button type="submit" class="btn-submit btn-honor">
                        <i class="fas fa-file-excel mr-1"></i> Download Excel
                    </button>
                </form>
            </div>
        </div>

        <div class="report-card">
            <div class="card-header header-mitra">
                <i class="fas fa-users card-icon"></i>
                <h3 class="card-title">Database Mitra</h3>
            </div>
            <div class="card-body">
                <form action="../proses/cetak_data_mitra_excel.php" method="POST">
                    
                    <div class="form-group">
                        <label>Tahun Kegiatan</label>
                        <input type="number" name="tahun" class="form-input" value="<?= date('Y') ?>" required>
                        <p class="help-text">Mengambil data mitra yang aktif di tahun ini.</p>
                    </div>

                    <div class="form-group">
                        <label>Tim (History Penugasan)</label>
                        <select name="tim_id[]" class="form-select" multiple required>
                            <?php foreach ($tim_list as $tim) : ?>
                                <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-text">* Pilih Tim untuk memfilter mitra terkait.</p>
                    </div>

                    <div style="height: 74px;"></div> 

                    <button type="submit" class="btn-submit btn-mitra">
                        <i class="fas fa-file-excel mr-1"></i> Download Excel
                    </button>
                </form>
            </div>
        </div>

        <div class="report-card">
            <div class="card-header header-nilai">
                <i class="fas fa-star card-icon"></i>
                <h3 class="card-title">Penilaian Kinerja</h3>
            </div>
            <div class="card-body">
                <form action="../proses/cetak_penilaian_excel.php" method="POST">
                    
                    <div class="form-group">
                        <label>Tahun Penilaian</label>
                        <input type="number" name="tahun" class="form-input" value="<?= date('Y') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Tim Penilai</label>
                        <select name="tim_id[]" class="form-select" multiple required>
                            <?php foreach ($tim_list as $tim) : ?>
                                <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-text">* Tahan CTRL untuk memilih banyak tim.</p>
                    </div>

                    <div style="height: 74px;"></div>

                    <button type="submit" class="btn-submit btn-nilai">
                        <i class="fas fa-file-excel mr-1"></i> Download Excel
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>