<?php
session_start();
include '../includes/koneksi.php';

$message = '';
$message_type = '';

// Logika untuk menyimpan data baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil bulan dan tahun dari form
    $bulan_input = $_POST['bulan'] ?? null;
    $tahun_input = $_POST['tahun'] ?? null;
    $batas_honor_input = $_POST['batas_honor'] ?? null;
    
    // Validasi input
    if (is_numeric($bulan_input) && is_numeric($tahun_input) && is_numeric($batas_honor_input) && $batas_honor_input >= 0) {
        $bulan_input = sprintf('%02d', (int) $bulan_input); // Pastikan format bulan 2 digit
        $tahun_input = (int) $tahun_input;
        $batas_honor_input = (int) $batas_honor_input;

        try {
            // Cek apakah data untuk bulan dan tahun tersebut sudah ada
            $sql_check = "SELECT id FROM batas_honor WHERE bulan = ? AND tahun = ?";
            $stmt_check = $koneksi->prepare($sql_check);
            if (!$stmt_check) {
                throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
            }
            $stmt_check->bind_param("si", $bulan_input, $tahun_input);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                // Jika sudah ada, berikan notifikasi error
                $message = "Maaf, batas honor untuk bulan " . htmlspecialchars($bulan_input) . " tahun " . htmlspecialchars($tahun_input) . " sudah ada. Silakan gunakan halaman edit untuk mengubahnya.";
                $message_type = "error";
            } else {
                // Jika belum ada, lakukan INSERT
                $sql_insert = "INSERT INTO batas_honor (bulan, tahun, batas_honor, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                $stmt_insert = $koneksi->prepare($sql_insert);
                if (!$stmt_insert) {
                    throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
                }
                $stmt_insert->bind_param("sii", $bulan_input, $tahun_input, $batas_honor_input);
                
                if ($stmt_insert->execute()) {
                    // Redirect setelah berhasil
                    header("Location: riwayat_batas_honor.php");
                    exit();
                } else {
                    $message = "Gagal menambahkan data: " . $stmt_insert->error;
                    $message_type = "error";
                }
                $stmt_insert->close();
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
$koneksi->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
/* --- DESAIN TAMPILAN MODERN --- */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f4f8;
}
.content-wrapper {
    padding: 1rem;
    transition: margin-left 0.3s ease;
}
@media (min-width: 640px) {
    .content-wrapper {
        margin-left: 16rem;
        padding-top: 2rem;
    }
}
.card {
    background-color: #ffffff;
    border-radius: 1rem;
    padding: 2.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
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
}
.btn-primary, .btn-secondary {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
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
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-secondary:hover {
    background-color: #eef2ff;
}
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
.flex-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
</style>

<div class="content-wrapper">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex-header">
                        <a href="manage_batas_honor.php" class="btn-secondary">
                ← Kembali
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Tambah Batas Honor Baru</h1>

        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card space-y-6">
            <p class="text-gray-600">
                Gunakan formulir ini untuk menambahkan catatan batas honor baru.
            </p>
            
            <form action="" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="form-group">
                        <label for="bulan">Bulan</label>
                        <select id="bulan" name="bulan" class="form-select" required>
                            <option value="">Pilih Bulan</option>
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tahun">Tahun</label>
                        <input type="number" id="tahun" name="tahun" class="form-input" value="<?= date('Y') ?>" required>
                    </div>
                </div>
                <div class="form-group mb-4">
                    <label for="batas_honor">Batas Maksimal Honor (Rp)</label>
                    <input type="number" id="batas_honor" name="batas_honor" class="form-input" required>
                </div>
                <button type="submit" class="btn-primary">Simpan Batas Honor</button>
            </form>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>