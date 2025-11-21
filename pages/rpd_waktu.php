<?php
session_start();
include '../includes/koneksi.php';
// Pastikan header.php memuat CSS Bootstrap. Jika tidak, tabel akan jelek.
include '../includes/header.php';
include '../includes/sidebar.php';

// 1. Cek Akses
if (!in_array('admin_dipaku', $_SESSION['user_role'] ?? [])) {
    die("<div class='alert alert-danger m-4'>Akses ditolak! Halaman ini khusus Admin Dipaku.</div>");
}

// 2. Proses Simpan (Insert / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahun   = (int)$_POST['tahun'];
    $mulai   = $_POST['mulai'];
    $selesai = $_POST['selesai'];
    $user    = $_SESSION['username'] ?? 'Admin';

    if ($mulai > $selesai) {
        echo "<script>alert('❌ Tanggal Mulai tidak boleh lebih besar dari Selesai!');</script>";
    } else {
        $stmt = $koneksi->prepare("
            INSERT INTO rpd_setting_waktu (tahun, mulai, selesai, dibuat_oleh) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            mulai = VALUES(mulai), 
            selesai = VALUES(selesai),
            dibuat_oleh = VALUES(dibuat_oleh),
            dibuat_pada = CURRENT_TIMESTAMP
        ");

        if ($stmt) {
            $stmt->bind_param("isss", $tahun, $mulai, $selesai, $user);
            if ($stmt->execute()) {
                echo "<script>alert('✅ Berhasil menyimpan jadwal tahun $tahun'); window.location='rpd_waktu.php';</script>";
            } else {
                echo "<script>alert('❌ Gagal database: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('❌ Error SQL: " . $koneksi->error . "');</script>";
        }
    }
}

// 3. Ambil Data
$result = $koneksi->query("SELECT * FROM rpd_setting_waktu ORDER BY tahun DESC");
?>

<style>
    /* Styling Tabel Agar Rapi */
    .table-custom {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .table-custom thead th {
        background-color: #0A2E5D; /* Warna Biru Tua */
        color: white;
        text-align: center;
        padding: 12px;
        font-weight: 600;
        border-bottom: 2px solid #ddd;
    }
    .table-custom tbody td {
        padding: 12px;
        text-align: center;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover {
        background-color: #f1f1f1;
    }

    /* Card Styling */
    .main-content { background-color: #f4f6f9; min-height: 85vh; padding: 20px; }
    .card-custom { 
        background: #fff; 
        border-radius: 10px; 
        padding: 25px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        margin-bottom: 20px;
    }

    /* Badge Status */
    .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
    .bg-open { background-color: #28a745; color: white; } /* Hijau */
    .bg-closed { background-color: #dc3545; color: white; } /* Merah */
    .bg-wait { background-color: #ffc107; color: #333; } /* Kuning */

    /* Modal Fix (Agar tidak tertutup sidebar) */
    .modal { z-index: 10000 !important; } 
    .modal-backdrop { z-index: 9999 !important; }
</style>

<main class="main-content">
    <div class="container-fluid">
        <h3 class="mb-4" style="color: #333; font-weight: bold;"><i class="fas fa-clock mr-2"></i>Pengaturan Waktu RPD</h3>
         <a href="rpd.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Kembali ke RPD
                    </a>

        <div class="card-custom">
            <h5 class="mb-3" style="border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-plus-circle text-primary"></i> Tambah / Atur Jadwal
            </h5>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label style="font-weight: bold;">Tahun Anggaran</label>
                        <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>" required placeholder="Contoh: 2025" style="padding: 10px; width: 100%; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label style="font-weight: bold;">Tanggal Mulai</label>
                        <input type="date" name="mulai" class="form-control" required style="padding: 10px; width: 100%; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label style="font-weight: bold;">Tanggal Selesai</label>
                        <input type="date" name="selesai" class="form-control" required style="padding: 10px; width: 100%; border: 1px solid #ccc; border-radius: 5px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fas fa-save"></i> Simpan Jadwal
                </button>
            </form>
        </div>

        <div class="card-custom">
            <h5 class="mb-3">Riwayat Jadwal</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-custom">
                    <thead>
                        <tr>
                            <th>Tahun anggaran</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $now = date('Y-m-d');
                                if ($now < $row['mulai']) {
                                    $status = "<span class='status-badge bg-wait'>Belum Mulai</span>";
                                } elseif ($now >= $row['mulai'] && $now <= $row['selesai']) {
                                    $status = "<span class='status-badge bg-open'>Sedang Buka</span>";
                                } else {
                                    $status = "<span class='status-badge bg-closed'>Sudah Tutup</span>";
                                }
                            ?>
                            <tr>
                                <td style="font-weight: bold; color: #0A2E5D;"><?= $row['tahun'] ?></td>
                                <td><?= date('d M Y', strtotime($row['mulai'])) ?></td>
                                <td><?= date('d M Y', strtotime($row['selesai'])) ?></td>
                                <td><?= $status ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info btn-edit"
                                        data-tahun="<?= $row['tahun'] ?>"
                                        data-mulai="<?= $row['mulai'] ?>"
                                        data-selesai="<?= $row['selesai'] ?>"
                                        style="background-color: #17a2b8; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="color: #888;">Belum ada data jadwal.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
            
            <div class="modal-header" style="background-color: #0A2E5D; color: white; padding: 15px 20px;">
                <h5 class="modal-title" style="font-size: 1rem; font-weight: bold;">
                    <i class="fas fa-edit mr-2"></i> Edit Jadwal
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form method="POST">
                <div class="modal-body" style="padding: 25px;">
                    <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; font-size: 0.85rem; margin-bottom: 20px; border-left: 4px solid #ffeeba;">
                        <i class="fas fa-info-circle mr-1"></i> Anda sedang mengedit jadwal untuk tahun terpilih.
                    </div>
                    
                    <div class="form-group mb-3">
                        <label style="font-weight: 600; font-size: 0.9rem; color: #333;">Tahun Anggaran</label>
                        <input type="number" name="tahun" id="edit_tahun" class="form-control bg-light" readonly 
                               style="border: 1px solid #ddd; background-color: #f8f9fa;">
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-0">
                                <label style="font-weight: 600; font-size: 0.9rem; color: #333;">Tanggal Mulai</label>
                                <input type="date" name="mulai" id="edit_mulai" class="form-control" required style="border: 1px solid #ddd;">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-0">
                                <label style="font-weight: 600; font-size: 0.9rem; color: #333;">Tanggal Selesai</label>
                                <input type="date" name="selesai" id="edit_selesai" class="form-control" required style="border: 1px solid #ddd;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background-color: #f8f9fa; padding: 15px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="background-color: #0A2E5D; border: none;">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* 1. Paksa Modal di atas Sidebar (Z-Index Tinggi) */
    .modal { z-index: 100050 !important; }
    .modal-backdrop { z-index: 100040 !important; }
    
    /* 2. Atur Lebar Modal agar tidak kegedean */
    .modal-dialog {
        max-width: 500px !important; /* Lebar maksimal 500px */
        margin: 1.75rem auto;
        width: 90%; /* Agar responsif di HP */
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    
    // === SOLUSI UTAMA: PINDAHKAN MODAL KE BODY ===
    // Script ini memindahkan HTML modal keluar dari sidebar/main-content
    // dan menaruhnya langsung di bawah tag <body> agar tidak tertutup.
    $('#modalEdit').appendTo("body");

    // Handler Tombol Edit
    $('.btn-edit').on('click', function(e) {
        e.preventDefault();
        
        var tahun = $(this).data('tahun');
        var mulai = $(this).data('mulai');
        var selesai = $(this).data('selesai');

        $('#edit_tahun').val(tahun);
        $('#edit_mulai').val(mulai);
        $('#edit_selesai').val(selesai);

        $('#modalEdit').modal('show');
    });

    // Fix untuk tombol close jika bootstrap bentrok
    $('.close, .btn-secondary').on('click', function() {
        $('#modalEdit').modal('hide');
    });
});
</script>