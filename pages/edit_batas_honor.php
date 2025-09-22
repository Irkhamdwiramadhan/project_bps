<?php
session_start();
include '../includes/koneksi.php';

$message = '';
$message_type = '';
$data_honor = null;
$item_id = $_GET['id'] ?? null;

// Logika untuk memproses update data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_id = $_POST['id'] ?? null;
    $updated_bulan = $_POST['bulan'] ?? null;
    $updated_tahun = $_POST['tahun'] ?? null;
    $updated_batas_honor = $_POST['batas_honor'] ?? null;

    if ($updated_id && is_numeric($updated_id) && is_numeric($updated_bulan) && is_numeric($updated_tahun) && is_numeric($updated_batas_honor) && $updated_batas_honor >= 0) {
        $updated_bulan = sprintf('%02d', (int) $updated_bulan);
        $updated_tahun = (int) $updated_tahun;
        $updated_batas_honor = (int) $updated_batas_honor;

        try {
            // Cek duplikasi, pastikan tidak ada data lain dengan bulan & tahun yang sama
            $sql_check = "SELECT id FROM batas_honor WHERE bulan = ? AND tahun = ? AND id != ?";
            $stmt_check = $koneksi->prepare($sql_check);
            $stmt_check->bind_param("sii", $updated_bulan, $updated_tahun, $updated_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $message = "Gagal: Batas honor untuk bulan " . htmlspecialchars($updated_bulan) . " tahun " . htmlspecialchars($updated_tahun) . " sudah ada.";
                $message_type = "error";
            } else {
                // Lakukan UPDATE
                $sql_update = "UPDATE batas_honor SET bulan = ?, tahun = ?, batas_honor = ?, updated_at = NOW() WHERE id = ?";
                $stmt_update = $koneksi->prepare($sql_update);
                $stmt_update->bind_param("siii", $updated_bulan, $updated_tahun, $updated_batas_honor, $updated_id);
                
                if ($stmt_update->execute()) {
                    // Redirect setelah berhasil
                    header("Location: riwayat_batas_honor.php");
                    exit();
                } else {
                    $message = "Gagal memperbarui data: " . $stmt_update->error;
                    $message_type = "error";
                }
                $stmt_update->close();
            }
            $stmt_check->close();
        } catch (Exception $e) {
            $message = "Terjadi kesalahan: " . htmlspecialchars($e->getMessage());
            $message_type = "error";
        }
    } else {
        $message = "Input tidak valid. Harap isi semua kolom dengan benar.";
        $message_type = "error";
    }
}

// Logika untuk mengambil data dari database untuk ditampilkan di formulir
if (!$item_id || !is_numeric($item_id)) {
    $message = "ID data tidak valid.";
    $message_type = "error";
    $data_honor = null;
} else {
    try {
        $sql_get_data = "SELECT id, bulan, tahun, batas_honor FROM batas_honor WHERE id = ?";
        $stmt_get_data = $koneksi->prepare($sql_get_data);
        if (!$stmt_get_data) {
            throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
        }
        $stmt_get_data->bind_param("i", $item_id);
        $stmt_get_data->execute();
        $result_get_data = $stmt_get_data->get_result();
        
        if ($result_get_data->num_rows > 0) {
            $data_honor = $result_get_data->fetch_assoc();
        } else {
            $message = "Data tidak ditemukan.";
            $message_type = "error";
        }
        $stmt_get_data->close();

    } catch (Exception $e) {
        $message = "Error: " . htmlspecialchars($e->getMessage());
        $message_type = "error";
    }
}

$koneksi->close();

include '../includes/header.php';
include '../includes/sidebar.php';

// Array untuk nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>

<style>
/* --- DESAIN TAMPILAN MODERN --- */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f4f8;
    margin: 0; /* Menghilangkan margin default dari body */
}

/* --- Penyesuaian Wrapper Utama --- */
.content-wrapper {
    padding: 2rem 1rem; /* Padding atas-bawah dan samping */
    transition: margin-left 0.3s ease;
}

/* --- Layout Konten --- */
.main-content {
    max-width: 800px;
    margin: 0 auto; /* Pusatkan konten di tengah */
}

.card {
    background-color: #ffffff;
    border-radius: 1rem;
    padding: 2.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

/* --- Header dan Tombol --- */
.flex-header { 
    display: flex;
    flex-wrap: wrap; /* Izinkan item untuk pindah ke baris baru */
    justify-content: space-between;
    align-items: center;
    gap: 1rem; /* Jarak antar item */
    margin-bottom: 2rem;
}

h1 {
    font-size: 1.875rem; /* Ukuran font judul */
    font-weight: 700;
    color: #1f2937;
}

.btn-primary, .btn-secondary {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    border: none;
    color: white;
    background-image: linear-gradient(to right, #6366f1 0%, #4f46e5 100%);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}
.btn-primary:hover {
    background-image: linear-gradient(to right, #4f46e5 0%, #6366f1 100%);
    box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
    transform: translateY(-2px);
}

.btn-secondary {
    color: #4f46e5;
    background-color: white;
    border: 1px solid #e2e8f0;
}
.btn-secondary:hover {
    background-color: #eef2ff;
}

/* --- Formulir --- */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #4a5568;
    font-weight: 500;
}

.form-input, .form-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-size: 0.95rem;
    background-color: #fff;
    transition: border-color 0.2s;
    box-sizing: border-box; /* Pastikan padding tidak menambah lebar */
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr; /* Default 1 kolom */
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* --- Notifikasi/Alert --- */
.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}
.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* --- Media Queries untuk Responsivitas --- */

/* Tampilan Desktop (lebar > 768px) */
@media (min-width: 768px) {
    .content-wrapper {
        margin-left: 16rem; /* Terapkan margin hanya untuk layar besar */
        padding: 2rem;
    }
    .form-grid {
        grid-template-columns: 1fr 1fr; /* Jadi 2 kolom */
    }
}

/* Tampilan Tablet dan Ponsel (lebar < 768px) */
@media (max-width: 767px) {
    .card {
        padding: 1.5rem; /* Kurangi padding kartu */
    }
    h1 {
        font-size: 1.5rem; /* Kecilkan font judul */
    }
    .flex-header {
        flex-direction: column; /* Susun judul dan tombol ke bawah */
        align-items: flex-start; /* Rata kiri */
    }
}
</style>

<div class="content-wrapper">
    <div class="main-content">
        <div class="flex-header">
            <h1 class="text-3xl font-bold text-gray-800">Edit Batas Honor</h1>
            <a href="manage_batas_honor.php" class="btn-secondary">
                ← Kembali
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($data_honor): ?>
        <div class="card">
            <p class="text-gray-600" style="margin-bottom: 1rem;">
                Ubah batas honor untuk bulan **<?= htmlspecialchars($nama_bulan[sprintf('%02d', $data_honor['bulan'])] ?? '') ?> <?= htmlspecialchars($data_honor['tahun']) ?>**.
            </p>
            
            <form action="" method="POST">
                <input type="hidden" name="id" value="<?= htmlspecialchars($data_honor['id']) ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="bulan">Bulan</label>
                        <select id="bulan" name="bulan" class="form-select" required>
                            <?php
                            foreach ($nama_bulan as $num => $name) {
                                $selected = (intval($num) == $data_honor['bulan']) ? 'selected' : '';
                                echo "<option value=\"$num\" $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tahun">Tahun</label>
                        <input type="number" id="tahun" name="tahun" class="form-input" value="<?= htmlspecialchars($data_honor['tahun']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="batas_honor">Batas Maksimal Honor (Rp)</label>
                    <input type="number" id="batas_honor" name="batas_honor" class="form-input" value="<?= htmlspecialchars($data_honor['batas_honor']) ?>" required>
                </div>
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include '../includes/footer.php';
?>