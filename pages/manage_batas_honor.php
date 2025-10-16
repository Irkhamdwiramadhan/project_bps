<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil tahun dari GET request atau gunakan nilai default (tahun saat ini)
$tahun_filter = $_GET['tahun'] ?? date('Y');

// Siapkan array untuk nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Siapkan array kosong untuk menampung data batas honor
$batas_honor_data = [];

try {
    // Ambil data batas honor dari database untuk tahun yang difilter
    $sql_get_limits = "SELECT id, bulan, batas_honor FROM batas_honor WHERE tahun = ? ORDER BY bulan ASC";
    $stmt_get_limits = $koneksi->prepare($sql_get_limits);
    if (!$stmt_get_limits) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }
    $stmt_get_limits->bind_param("s", $tahun_filter);
    $stmt_get_limits->execute();
    $result_get_limits = $stmt_get_limits->get_result();
    
    // Perbarui array dengan nilai dari database jika ada
    if ($result_get_limits->num_rows > 0) {
        while ($row = $result_get_limits->fetch_assoc()) {
            $bulan_formatted = sprintf('%02d', $row['bulan']);
            $batas_honor_data[] = [
                'id' => $row['id'],
                'bulan' => $row['bulan'],
                'nama_bulan' => $nama_bulan[$bulan_formatted] ?? 'Tidak Ditemukan',
                'batas_honor' => $row['batas_honor'],
            ];
        }
    } else {
        $message = "Tidak ada data batas honor untuk tahun " . htmlspecialchars($tahun_filter) . ".";
    }
    $stmt_get_limits->close();

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $batas_honor_data = [];
}

$koneksi->close();
?>

<style>
/* --- FONT & WARNA DASAR --- */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f4f8;
    color: #4a5568;
}

/* --- TATA LETAK UTAMA --- */
.content-wrapper {
    margin-left: 16rem;
    padding: 2rem;
    transition: margin-left 0.3s ease, padding 0.3s ease;
}

.main-content {
    max-width: 960px; /* Nilai setara max-w-4xl */
    margin: 0 auto;  /* Nilai setara mx-auto */
    padding: 2rem;   /* Nilai setara py-8 */
}

.responsive-padding {
    padding-left: 1rem;
    padding-right: 1rem;
}

@media (min-width: 640px) { /* sm */
    .responsive-padding {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
}

@media (min-width: 1024px) { /* lg */
    .responsive-padding {
        padding-left: 2rem;
        padding-right: 2rem;
    }
}

/* --- MEDIA QUERIES UNTUK RESPONSIVITAS --- */
@media (max-width: 640px) {
    .content-wrapper {
        margin-left: 0;
        padding: 1rem;
    }
    .flex-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .btn-secondary {
        width: 100%;
        text-align: center;
    }
    .section-header h1 {
        font-size: 1.5rem;
    }
}

/* --- KOMPONEN UMUM --- */
.card {
    background-color: #ffffff;
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.table-container {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}
thead th {
    background-color: #e2e8f0;
    color: #4a5568;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem 1.5rem;
    text-align: left;
    white-space: nowrap;
}
tbody td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
tbody tr:last-child td {
    border-bottom: none;
}
tbody tr:hover {
    background-color: #f9fafb;
}

/* --- AREA FORM FILTER --- */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 2rem;
}

.filter-group {
    flex: 1 1 200px;
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.4rem;
}

.form-input {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    width: 100%;
    box-sizing: border-box;
}

.btn-primary {
    padding: 0.75rem 1.8rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
    background-image: linear-gradient(to right, #6366f1 0%, #4f46e5 100%);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
    transition: all 0.3s ease;
    flex-shrink: 0; /* tombol tidak mengecil */
}

.btn-primary:hover {
    background-image: linear-gradient(to right, #4f46e5 0%, #6366f1 100%);
    transform: translateY(-2px);
}

/* --- RESPONSIVE FIX --- */
@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }

    .btn-primary {
        width: 100%;
        text-align: center;
    }

    .btn-secondary {
        width: 100%;
        text-align: center;
    }

    .flex-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .flex-container h1 {
        font-size: 1.5rem;
    }
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
    background-image: linear-gradient(to right, #6366f1 0%, #4f46e5 100%);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
    transition: all 0.3s ease;
}
.btn-primary:hover {
    background-image: linear-gradient(to right, #4f46e5 0%, #6366f1 100%);
    box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
    transform: translateY(-2px);
}
.action-link {
    color: #4f46e5;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}
.action-link:hover {
    color: #4338ca;
}
.flex-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
.btn-secondary {
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    color: #4f46e5;
    background-color: white;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-secondary:hover {
    background-color: #eef2ff;
}

.card.space-y-8 > * + * {
    margin-top: 2rem;
}

</style>

<div class="content-wrapper">
    <div class="main-content responsive-padding">
        <div class="flex-container">
                <a href="rekap_honor.php" class="btn-secondary">
            ← Kembali
        </a>
            <h1 class="text-3xl font-bold text-gray-800">Riwayat Batas Honor</h1>
            <a href="tambah_batas_honor.php" class="btn-secondary">
                + Tambah Batas Honor
            </a>
        </div>
        <h2 class="text-xl text-gray-600 mb-6">Tahun <?= htmlspecialchars($tahun_filter) ?></h2>

        <form action="" method="GET" class="filter-form">
    <div class="filter-group">
        <label for="tahun">Pilih Tahun</label>
        <input type="number" id="tahun" name="tahun" class="form-input"
               value="<?= htmlspecialchars($tahun_filter) ?>" required>
    </div>
    <button type="submit" class="btn-primary">Tampilkan</button>
</form>


        <div class="card space-y-8">
            <div class="table-container">
                <?php if (!empty($batas_honor_data)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Bulan</th>
                                <th>Tahun</th>
                                <th>Batas Honor</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($batas_honor_data as $data) : ?>
                                <tr>
                                    <td><?= $counter ?></td>
                                    <td><?= htmlspecialchars($data['nama_bulan']) ?></td>
                                    <td><?= htmlspecialchars($tahun_filter) ?></td>
                                    <td>Rp <?= number_format($data['batas_honor'], 0, ',', '.') ?></td>
                                    <td>
                                        <a href="edit_batas_honor.php?id=<?= htmlspecialchars($data['id']) ?>" class="action-link">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php $counter++; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-gray-500 py-4">Tidak ada data batas honor untuk tahun ini. Silakan tambahkan data baru.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>