<?php
session_start();
include '../includes/koneksi.php';

// Path file untuk menyimpan status rilis
$release_file = 'release_status.json';

// Ambil peran pengguna dari sesi.
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_admin_roles = ['super_admin', 'admin_prestasi'];
$is_admin = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_admin_roles)) {
        $is_admin = true;
        break;
    }
}

// Menyiapkan variabel filter default
$current_year = date('Y');
$current_triwulan = ceil(date('n') / 3);
$filter_tahun = isset($_GET['tahun']) ? htmlspecialchars($_GET['tahun']) : $current_year;
$filter_triwulan = isset($_GET['triwulan']) ? htmlspecialchars($_GET['triwulan']) : $current_triwulan;
$filter_jenis = isset($_GET['jenis']) ? htmlspecialchars($_GET['jenis']) : 'pegawai_prestasi';

// Logika untuk menangani aksi rilis/batal rilis oleh admin
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_tahun = $_POST['tahun'] ?? date('Y');
    $post_triwulan = $_POST['triwulan'] ?? ceil(date('n') / 3);
    $post_jenis = $_POST['jenis'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // Kunci status rilis harus menyertakan jenis penilaian
    $status_key = $post_jenis . '_' . $post_tahun . ($post_jenis === 'pegawai_prestasi' ? '_' . $post_triwulan : '');
    
    $release_status = file_exists($release_file) ? json_decode(file_get_contents($release_file), true) : [];

    if ($action === 'release') {
        $release_status[$status_key] = true;
    } elseif ($action === 'unrelease') {
        unset($release_status[$status_key]);
    }
    file_put_contents($release_file, json_encode($release_status, JSON_PRETTY_PRINT));
    
    // Redirect untuk menghindari form resubmission
    header("Location: hasil_penilaian.php?tahun=$post_tahun&triwulan=$post_triwulan&jenis=$post_jenis");
    exit();
}

include '../includes/header.php';
include '../includes/sidebar.php';

// Cek status rilis
$is_released = false;
$release_status = file_exists($release_file) ? json_decode(file_get_contents($release_file), true) : [];
$status_key = $filter_jenis . '_' . $filter_tahun . ($filter_jenis === 'pegawai_prestasi' ? '_' . $filter_triwulan : '');
if (isset($release_status[$status_key]) && $release_status[$status_key] === true) {
    $is_released = true;
}

// Logika untuk admin: menampilkan daftar penilai yang sudah dan belum
$pegawai_sudah_menilai = [];
$pegawai_belum_menilai = [];

if ($is_admin) {
    $sql_all_pegawai = "SELECT id, nama FROM pegawai WHERE is_active = 1 ORDER BY nama ASC";
    $result_all_pegawai = mysqli_query($koneksi, $sql_all_pegawai);
    $all_pegawai_map = [];
    if ($result_all_pegawai) {
        while ($row = mysqli_fetch_assoc($result_all_pegawai)) {
            $all_pegawai_map[$row['id']] = $row['nama'];
        }
    }

    $sql_rated_pegawai = "
        SELECT DISTINCT p.id, p.nama
        FROM penilaian_triwulan pt
        JOIN calon_triwulan ct ON pt.id_calon = ct.id
        JOIN pegawai p ON pt.id_penilai = p.id
        WHERE pt.jenis_penilaian = ? AND ct.tahun = ? AND p.is_active = 1";

    
    $params = [$filter_jenis, $filter_tahun];
    $types = "si";

    if ($filter_jenis === 'pegawai_prestasi') {
        $sql_rated_pegawai .= " AND ct.triwulan = ?";
        $params[] = $filter_triwulan;
        $types .= "i";
    }

    $stmt_rated = $koneksi->prepare($sql_rated_pegawai);
    $stmt_rated->bind_param($types, ...$params);
    $stmt_rated->execute();
    $result_rated = $stmt_rated->get_result();
    $rated_pegawai_ids = [];
    while ($row = mysqli_fetch_assoc($result_rated)) {
        $pegawai_sudah_menilai[] = $row['nama'];
        $rated_pegawai_ids[] = $row['id'];
    }
    $stmt_rated->close();

    foreach ($all_pegawai_map as $id => $nama) {
        if (!in_array($id, $rated_pegawai_ids)) {
            $pegawai_belum_menilai[] = $nama;
        }
    }
}

// Cek apakah semua pegawai sudah menilai
$all_rated = false;
if (!empty($all_pegawai_map) && count($pegawai_sudah_menilai) >= count($all_pegawai_map)) {
    $all_rated = true;
}

// Query utama untuk mengambil data pegawai berprestasi
$sql = "
    SELECT
        p.id,
        p.nama,
        p.nip,
        p.foto,
        ct.triwulan,
        ct.tahun,
        SUM(pt.total_skor) AS total_skor,
        AVG(pt.total_skor) AS rata_rata_skor,
        COUNT(DISTINCT pt.id_penilai) AS jumlah_penilai,
        pt.jenis_penilaian
    FROM penilaian_triwulan pt
    JOIN calon_triwulan ct ON pt.id_calon = ct.id
    JOIN pegawai p ON ct.id_pegawai = p.id
    WHERE pt.jenis_penilaian = ? AND ct.tahun = ? AND p.is_active = 1";
    
$params = [$filter_jenis, $filter_tahun];
$types = "si";

if ($filter_jenis === 'pegawai_prestasi') {
    $sql .= " AND ct.triwulan = ?";
    $params[] = $filter_triwulan;
    $types .= "i";
}

$sql .= " GROUP BY p.id, ct.tahun, pt.jenis_penilaian";
if ($filter_jenis === 'pegawai_prestasi') {
    $sql .= ", ct.triwulan";
}
$sql .= " ORDER BY total_skor DESC";

$stmt_data = $koneksi->prepare($sql);
$stmt_data->bind_param($types, ...$params);
$stmt_data->execute();
$result_data = $stmt_data->get_result();
$data_penilaian = [];
if ($result_data) {
    while ($row = mysqli_fetch_assoc($result_data)) {
        $data_penilaian[] = $row;
    }
}
$stmt_data->close();

// Query untuk mengambil data tahun dan triwulan yang tersedia untuk filter
$sql_filter = "SELECT DISTINCT tahun, triwulan FROM calon_triwulan ORDER BY tahun DESC, triwulan ASC";
$result_filter = mysqli_query($koneksi, $sql_filter);
?>

<style>
    /* CSS & Desain Baru */
    .content-wrapper {
        min-height: calc(100vh - 101px);
        background-color: #f0f2f5;
        margin-left: 250px;
        transition: margin-left .3s ease-in-out;
        padding: 40px;
    }
    .card-modern {
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        padding: 30px;
        margin-bottom: 30px;
    }
    .card-header-modern {
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .calon-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
    }
    .calon-card {
        position: relative;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        text-align: center;
        padding: 30px 20px;
    }
    .calon-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
    }
    .card-rank {
        position: absolute;
        top: 15px;
        left: 15px;
        font-size: 1.5rem;
        font-weight: bold;
        color: #fff;
        background-color: #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
    }
    .card-rank.rank-1 { background-color: #f1c40f; }
    .card-rank.rank-2 { background-color: #bdc3c7; }
    .card-rank.rank-3 { background-color: #cd7f32; }
    .calon-foto {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #f0f4f7;
        margin-bottom: 20px;
    }
    .card-info h3 {
        margin: 0 0 5px 0;
        font-size: 1.5rem;
        color: #2c3e50;
    }
    .card-info p {
        margin: 0;
        font-size: 0.9rem;
        color: #7f8c8d;
    }
    .score-info {
        margin-top: 15px;
    }
    .score-info .total-score {
        font-size: 2.5rem;
        font-weight: 800;
        color: #3498db;
        line-height: 1;
        margin-bottom: 5px;
    }
    .score-info .total-score span {
        font-size: 1rem;
        font-weight: 500;
        color: #7f8c8d;
    }
    .score-info .average-score {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2ecc71;
    }
    .list-status {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .list-item {
        flex: 1;
        min-width: 250px;
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
    }
    .list-item h5 {
        margin-top: 0;
        color: #007bff;
        font-size: 1.1rem;
    }
    .list-item.belum {
        background-color: #fdeaea;
    }
    .list-item.belum h5 {
        color: #dc3545;
    }
    .list-item ul {
        list-style-type: none;
        padding: 0;
        margin-top: 10px;
    }
    .list-item li {
        padding: 8px 0;
        border-bottom: 1px dashed #e9ecef;
    }
    .list-item li:last-child {
        border-bottom: none;
    }
    .btn-release {
        padding: 12px 25px;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 50px;
        background-color: #28a745;
        border: none;
        color: #fff;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .btn-release:hover {
        background-color: #218838;
    }
    .btn-unrelease {
        background-color: #dc3545;
    }
    .btn-unrelease:hover {
        background-color: #c82333;
    }
    /* Responsif untuk tablet dan HP */
@media (max-width: 992px) {
    .content-wrapper {
        margin-left: 0;
        padding: 20px;
    }

    .filter-form-container {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-form-container .form-group {
        width: 100%;
    }

    .list-status {
        flex-direction: column;
        gap: 15px;
    }

    .list-item {
        min-width: auto;
        width: 100%;
    }

    .calon-container {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .calon-card {
        padding: 20px 10px;
    }

    .calon-foto {
        width: 90px;
        height: 90px;
    }

    .card-info h3 {
        font-size: 1.2rem;
    }

    .score-info .total-score {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .content-wrapper {
        padding: 15px;
    }

    .calon-container {
        grid-template-columns: 1fr;
    }

    .btn-release {
        width: 100%;
        border-radius: 12px;
    }
}

</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Hasil Penilaian Pegawai Berprestasi</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card-modern">
                <h4 class="card-header-modern">Filter Hasil</h4>
                <form action="" method="get" class="d-flex align-items-end gap-3 filter-form-container">
                    <div class="form-group flex-grow-1">
                        <label for="jenis">Jenis Penilaian:</label>
                        <select name="jenis" id="jenis" class="form-control" onchange="this.form.submit()">
                            <option value="pegawai_prestasi" <?= $filter_jenis == 'pegawai_prestasi' ? 'selected' : '' ?>>Pegawai Prestasi</option>
                            <option value="can" <?= $filter_jenis == 'can' ? 'selected' : '' ?>>CAN</option>
                        </select>
                    </div>
                    <div class="form-group flex-grow-1" id="triwulan_filter_group" style="display: <?= $filter_jenis === 'can' ? 'none' : 'block' ?>;">
                        <label for="triwulan">Triwulan:</label>
                        <select name="triwulan" id="triwulan" class="form-control">
                            <option value="">Semua</option>
                            <?php foreach ([1, 2, 3, 4] as $q): ?>
                                <option value="<?= $q ?>" <?= ($filter_triwulan == $q) ? 'selected' : '' ?>>Triwulan <?= $q ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group flex-grow-1">
                        <label for="tahun">Tahun:</label>
                        <select name="tahun" id="tahun" class="form-control">
                            <option value="">Semua</option>
                            <?php 
                            $sql_tahun = "SELECT DISTINCT tahun FROM calon_triwulan ORDER BY tahun DESC";
                            $result_tahun = mysqli_query($koneksi, $sql_tahun);
                            $years = [];
                            if ($result_tahun) {
                                while($row = mysqli_fetch_assoc($result_tahun)) {
                                    $years[] = $row['tahun'];
                                }
                            }
                            foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= ($filter_tahun == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>

            <?php if ($is_admin): ?>
            <div class="card-modern">
                <h4 class="card-header-modern">Status & Kontrol Hasil</h4>
                <div class="list-status">
                    <div class="list-item sudah">
                        <h5>Sudah Menilai (<?= count($pegawai_sudah_menilai) ?>)</h5>
                        <ul>
                            <?php if (!empty($pegawai_sudah_menilai)): ?>
                                <?php foreach ($pegawai_sudah_menilai as $nama): ?>
                                    <li><?= htmlspecialchars($nama) ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>Belum ada yang menilai.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="list-item belum">
                        <h5>Belum Menilai (<?= count($pegawai_belum_menilai) ?>)</h5>
                        <ul>
                            <?php if (!empty($pegawai_belum_menilai)): ?>
                                <?php foreach ($pegawai_belum_menilai as $nama): ?>
                                    <li><?= htmlspecialchars($nama) ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>Semua pegawai sudah menilai.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <p class="font-weight-bold">Status Rilis: <span class="badge badge-pill <?= $is_released ? 'bg-success' : 'bg-danger' ?>"><?= $is_released ? 'Sudah Dirilis' : 'Belum Dirilis' ?></span></p>
                    <form action="" method="post" onsubmit="return confirm('Apakah Anda yakin ingin <?= $is_released ? 'membatalkan rilis' : 'merilis' ?> hasil ini?');">
                        <input type="hidden" name="action" value="<?= $is_released ? 'unrelease' : 'release' ?>">
                        <input type="hidden" name="tahun" value="<?= htmlspecialchars($filter_tahun) ?>">
                        <input type="hidden" name="triwulan" value="<?= htmlspecialchars($filter_triwulan) ?>">
                        <input type="hidden" name="jenis" value="<?= htmlspecialchars($filter_jenis) ?>">
                        <button type="submit" class="btn btn-release <?= $is_released ? 'btn-unrelease' : '' ?>">
                            <?= $is_released ? 'Batalkan Rilis Hasil' : 'Rilis Hasil' ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($is_admin || $is_released): ?>
            <div class="calon-container">
                <?php
                if (!empty($data_penilaian)) {
                    $rank = 0;
                    $last_total_score = null;
                    foreach ($data_penilaian as $index => $row) {
                        if ($row['total_skor'] !== $last_total_score) {
                            $rank = $index + 1;
                            $last_total_score = $row['total_skor'];
                        }
                        $foto_url = !empty($row['foto']) && file_exists('../assets/img/pegawai/' . basename($row['foto'])) ? '../assets/img/pegawai/' . basename($row['foto']) : 'https://via.placeholder.com/120?text=No+Foto';
                ?>
                <div class="calon-card">
                    <?php if ($rank <= 3): ?>
                        <div class="card-rank rank-<?= $rank ?>">
                            <?= $rank ?>
                        </div>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars($foto_url) ?>" alt="Foto <?= htmlspecialchars($row['nama']) ?>" class="calon-foto">
                    <div class="card-info">
                        <h3><?= htmlspecialchars($row['nama']) ?></h3>
                        <p>NIP: <?= htmlspecialchars($row['nip']) ?></p>
                    </div>
                    <div class="score-info">
                        <span class="total-score"><?= htmlspecialchars($row['total_skor']) ?></span>
                        <p>Total Skor</p>
                        <span class="average-score">Rata-rata: <?= number_format($row['rata_rata_skor'], 2) ?></span>
                        <p><?= htmlspecialchars($row['jumlah_penilai']) ?> penilai</p>
                    </div>
                </div>
                <?php
                    }
                } else {
                    echo '<div class="col-md-12"><p class="text-center">Belum ada data penilaian untuk filter yang dipilih.</p></div>';
                }
                ?>
            </div>
            <?php else: ?>
            <div class="card-modern">
                <div class="text-center p-5">
                    <i class="fas fa-lock fa-3x text-danger mb-3"></i>
                    <h4 class="text-danger">Hasil Penilaian Belum Dirilis</h4>
                    <p class="text-muted">Hasil untuk periode ini belum dapat diakses. Mohon tunggu informasi lebih lanjut dari admin.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php 
include '../includes/footer.php'; 
mysqli_close($koneksi);
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fungsi untuk menyembunyikan/menampilkan triwulan
        function toggleTriwulan() {
            var jenisPenilaian = document.getElementById('jenis').value;
            var triwulanGroup = document.getElementById('triwulan_filter_group');

            if (jenisPenilaian === 'can') {
                triwulanGroup.style.display = 'none';
            } else {
                triwulanGroup.style.display = 'block';
            }
        }

        // Jalankan fungsi saat halaman dimuat
        toggleTriwulan();
        document.getElementById('jenis').addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>