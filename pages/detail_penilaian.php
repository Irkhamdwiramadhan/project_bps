<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil ID mitra dari URL
$mitra_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($mitra_id === 0) {
    header('Location: penilaian_mitra.php?status=error&message=ID_mitra_tidak_valid');
    exit;
}

try {
    // Kueri untuk mengambil semua detail penilaian untuk ID mitra tertentu
    // PERUBAHAN: Tambahkan mpk.id untuk kebutuhan aksi hapus
    $sql_details = "SELECT
                        mpk.id,
                        mpk.tanggal_penilaian,
                        mpk.beban_kerja,
                        mpk.kualitas,
                        mpk.volume_pemasukan,
                        mpk.perilaku,
                        mpk.keterangan,
                        m.nama_lengkap AS nama_mitra,
                        m.foto AS foto_mitra,
                        p.nama AS nama_penilai,
                        s.nama_survei AS jenis_survei,
                        ms.survey_ke_berapa AS urutan_survei,
                        (mpk.kualitas + mpk.kualitas + mpk.volume_pemasukan + mpk.perilaku) / 4 AS rata_rata_penilaian
                    FROM
                        mitra_penilaian_kinerja AS mpk
                    JOIN
                        mitra_surveys AS ms ON mpk.mitra_survey_id = ms.id
                    JOIN
                        mitra AS m ON ms.mitra_id = m.id
                    JOIN
                        pegawai AS p ON mpk.penilai_id = p.id
                    JOIN
                        surveys AS s ON ms.survei_id = s.id
                    WHERE
                        m.id = ?
                    ORDER BY
                        mpk.tanggal_penilaian DESC";

    $stmt_details = $koneksi->prepare($sql_details);

    if (!$stmt_details) {
        throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
    }
    
    $stmt_details->bind_param("i", $mitra_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    
    // Jika tidak ada data, alihkan kembali atau tampilkan pesan
    if ($result_details->num_rows === 0) {
        header('Location: penilaian_mitra.php?status=error&message=Data_penilaian_tidak_ditemukan');
        exit;
    }

    // Ambil data pertama untuk info mitra
    $first_row = $result_details->fetch_assoc();
    $result_details->data_seek(0); // Kembali ke awal hasil untuk perulangan

} catch (Exception $e) {
    echo "<div class='content-wrapper'><div class='card p-6 text-center text-red-500 font-semibold'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    include '../includes/footer.php';
    exit;
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body { font-family: 'Poppins', sans-serif; background: #eef2f5; }
    .content-wrapper { 
        padding: 1rem; 
        transition: margin-left 0.3s ease; 
        margin-left: 0;
    }
    @media (min-width: 640px) { 
        .content-wrapper { 
            margin-left: 16rem;
            padding-top: 2rem; 
        } 
    }
    .card-detail {
        background-color: #fff;
        border-radius: 1rem;
        padding: 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        margin-bottom: 2rem;
    }
    .profile-header {
        display: flex;
        align-items: center;
        margin-bottom: 2rem;
        gap: 2rem;
    }
    .profile-photo {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #e5e7eb;
    }
    .profile-info h1 {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1f2937;
    }
    .table-container {
        overflow-x: auto;
    }
    .table-details {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }
    .table-details th, .table-details td {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
    }
    .table-details th {
        background-color: #f3f4f6;
        font-weight: 600;
        color: #4b5563;
        white-space: nowrap;
    }
    .table-details tbody tr:last-child td {
        border-bottom: none;
    }
    .rating-cell {
        font-weight: 600;
        color: #1f2937;
    }
    .btn-delete {
        background-color: #ef4444;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-weight: 500;
        transition: background-color 0.2s;
        white-space: nowrap;
    }
    .btn-delete:hover {
        background-color: #dc2626;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="penilaian_mitra.php" class="text-blue-600 hover:text-blue-800 mb-4 inline-block font-medium">
            &larr; Kembali ke Daftar Penilaian
        </a>
        
        <div class="card-detail">
            <div class="profile-header">
                <?php
                    $photo_path = !empty($first_row['foto_mitra']) ? '../uploads/' . htmlspecialchars($first_row['foto_mitra']) : 'https://via.placeholder.com/150';
                    $alt_text = !empty($first_row['foto_mitra']) ? 'Foto ' . $first_row['nama_mitra'] : 'Gambar Default';
                ?>
                <img src="<?= htmlspecialchars($photo_path); ?>" alt="<?= htmlspecialchars($alt_text); ?>" class="profile-photo">
                <div class="profile-info">
                    <h1><?= htmlspecialchars($first_row['nama_mitra']); ?></h1>
                </div>
            </div>
        </div>

        <div class="card-detail">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Riwayat Penilaian</h2>
            
            <div class="table-container">
                <table class="table-details">
                    <thead>
                        <tr>
                            <th>Penilai</th>
                            <th>Tanggal</th>
                            <th>Jenis Survei</th>
                            <th>Survey Ke</th>
                            <th>Beban Kerja</th>
                            <th>Kualitas</th>
                            <th>Volume Pemasukan</th>
                            <th>Perilaku</th>
                            <th>Keterangan</th>
                            <th>Rata-rata</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result_details->fetch_assoc()) : ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_penilai']); ?></td>
                                <td><?= htmlspecialchars($row['tanggal_penilaian']); ?></td>
                                <td><?= htmlspecialchars($row['jenis_survei']); ?></td>
                                <td><?= htmlspecialchars($row['urutan_survei']); ?></td>
                                <td class="rating-cell"><?= htmlspecialchars($row['beban_kerja']); ?></td>
                                <td class="rating-cell"><?= htmlspecialchars($row['kualitas']); ?></td>
                                <td class="rating-cell"><?= htmlspecialchars($row['volume_pemasukan']); ?></td>
                                <td class="rating-cell"><?= htmlspecialchars($row['perilaku']); ?></td>
                                <td><?= htmlspecialchars($row['keterangan']); ?></td>
                                <td class="rating-cell"><?= number_format($row['rata_rata_penilaian'], 2); ?></td>
                                <td>
                                    <a href="../proses/delete_penilaian.php?id=<?= htmlspecialchars($row['id']) ?>"
                                       class="btn-delete"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus penilaian ini?');">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
$stmt_details->close();
$koneksi->close();
include '../includes/footer.php'; 
?>