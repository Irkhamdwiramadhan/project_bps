<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil peran pengguna dari sesi. Jika tidak ada, atur sebagai array kosong.
$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'admin_apel'];

// Periksa apakah pengguna memiliki salah satu peran yang diizinkan untuk melihat aksi
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break; // Keluar dari loop setelah menemukan kecocokan
    }
}
?>

<style>
    /* ... (CSS yang sudah ada) ... */
    /* Tambahan CSS untuk formulir download */
    .download-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .download-form label {
        font-weight: 600;
        color: #4b5563;
    }
    .download-form select, .download-form button {
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
    }
    .download-form button {
        background-color: #17a2b8;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .download-form button:hover {
        background-color: #138496;
    }
    .flex-row {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    /* CSS tambahan untuk filter tanggal */
    .filter-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .filter-container input[type="date"] {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
</style>

<main class="main-content">
    <div class="header-content">
        <h2>Data Apel Harian</h2>
        <!-- <?php 
        // Periksa variabel yang kita buat di atas untuk menampilkan tombol "Tambah Apel"
        if ($has_access_for_action): 
        ?>
            <a href="tambah_apel.php" class="btn btn-primary">Tambah Apel</a>
        <?php endif; ?> -->
    </div>
    <?php 
        // Periksa variabel yang kita buat di atas untuk menampilkan tombol "Tambah Apel"
        if ($has_access_for_action): 
        ?>
    <div class="card card-download-report">
        
        <h3><i class="fas fa-download"></i> Unduh Rekap Kehadiran Bulanan</h3>
        <form action="../proses/download_apel_report.php" method="GET" class="download-form">
            <div class="flex-row">
                <label for="month">Bulan:</label>
                <select name="month" id="month" class="form-select">
                    <?php
                    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    foreach ($months as $key => $month) {
                        $m_val = str_pad($key + 1, 2, '0', STR_PAD_LEFT);
                        $selected = ($m_val == date('m')) ? 'selected' : '';
                        echo "<option value='$m_val' $selected>$month</option>";
                    }
                    ?>
                </select>
                <label for="year">Tahun:</label>
                <select name="year" id="year" class="form-select">
                    <?php
                    $current_year = date('Y');
                    for ($y = $current_year; $y >= $current_year - 5; $y--) {
                        $selected = ($y == $current_year) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit">Unduh Rekap Kehadiran</button>
        </form>
    </div>
<?php endif; ?>
    <hr>

    <!-- <div class="card card-description">
        <h3><i class="fas fa-info-circle"></i> Kondisi Apel dan Kehadiran</h3>
        <p>Berikut adalah penjelasan untuk setiap kondisi yang digunakan pada data apel:</p>
        <div class="description-grid">
            <div class="desc-item">
                <strong><i class="fas fa-user-check"></i> Status Kehadiran:</strong>
                <ul>
                    <li><i class="fas fa-star text-success"></i> <span class="status hadir-awal">Hadir Awal:</span> Hadir lebih awal dari jam apel.</li>
                    <li><i class="fas fa-check-circle text-success"></i> <span class="status hadir">Hadir:</span> Hadir tepat waktu saat apel dimulai.</li>
                    <li><i class="fas fa-hourglass-start text-warning"></i> <span class="status telat-1">Telat 1:</span> Datang terlambat, masih mengikuti amanat pembina.</li>
                    <li><i class="fas fa-hourglass-half text-warning"></i> <span class="status telat-2">Telat 2:</span> Datang terlambat, mengikuti apel setelah amanat pembina selesai.</li>
                    <li><i class="fas fa-hourglass-end text-warning"></i> <span class="status telat-3">Telat 3:</span> Datang terlambat, mengikuti apel saat waktu berdoa.</li>
                    <li><i class="fas fa-user-slash text-info"></i> <span class="status izin">Izin:</span> Tidak mengikuti apel dengan alasan yang sudah diketahui.</li>
                    <li><i class="fas fa-user-times text-danger"></i> <span class="status absen">Absen:</span> Tidak ada kabar dan tidak mengikuti apel.</li>
                    <li><i class="fas fa-plane text-primary"></i> <span class="status dinas-luar">Dinas Luar:</span> Tidak ikut apel karena sedang dalam tugas dinas luar.</li>
                    <li><i class="fas fa-bed text-danger"></i> <span class="status sakit">Sakit:</span> Tidak mengikuti apel karena sakit.</li>
                    <li><i class="fas fa-sun text-info"></i> <span class="status cuti">Cuti:</span> Tidak mengikuti apel karena sedang cuti.</li>
                    <li><i class="fas fa-tasks text-primary"></i> <span class="status tugas">Tugas:</span> Tidak mengikuti apel karena ada tugas khusus.</li>
                </ul>
            </div>
            <div class="desc-item">
                <strong><i class="fas fa-bullhorn"></i> Keterangan Apel:</strong>
                <ul>
                    <li><strong>Ada:</strong> Apel pagi dilaksanakan.</li>
                    <li><strong>Tidak Ada:</strong> Apel pagi tidak dilaksanakan.</li>
                    <li><strong>Lupa:</strong> Apel pagi dilaksanakan, namun lupa didokumentasikan.</li>
                </ul>
            </div>
        </div>
    </div> -->

    <div class="card">
        <h3>Tren Kehadiran Hadir Awal Harian</h3> <p>Lihat tren harian dari kehadiran "Hadir Awal" untuk menganalisis peningkatan atau penurunan.</p>
        <div style="width: 100%; height: 500px;"> 
            <canvas id="rekapApelChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Rekapitulasi Status Kehadiran Bermasalah</h3>
        <p>Diagram ini menampilkan kondisi kehadiran yang bermasalah seperti Telat, Izin, dan Absen.</p>
        <div id="kehadiran-charts-container">
        </div>
        
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script> <script>
    <?php
    // Logika untuk mengambil data rekapitulasi kehadiran Hadir Awal harian
    // Perubahan: Mengubah format tanggal menjadi 'Y-m-d' untuk data harian
    $hadir_awal_data = [
        'labels' => [],
        'hadir_awal' => []
    ];
    $sql_kehadiran_trend = "SELECT tanggal, kehadiran FROM apel WHERE kondisi_apel IN ('ada', 'lupa_didokumentasikan') ORDER BY tanggal ASC";
    $result_kehadiran_trend = $koneksi->query($sql_kehadiran_trend);
    $daily_counts = [];
    if ($result_kehadiran_trend->num_rows > 0) {
        while ($row = $result_kehadiran_trend->fetch_assoc()) {
            $date = date('Y-m-d', strtotime($row['tanggal']));
            $kehadiran_json = json_decode($row['kehadiran'], true);
            if (is_array($kehadiran_json)) {
                if (!isset($daily_counts[$date])) {
                    $daily_counts[$date] = 0;
                }
                foreach ($kehadiran_json as $kehadiran_item) {
                    if (isset($kehadiran_item['status']) && $kehadiran_item['status'] === 'hadir_awal') {
                        $daily_counts[$date]++;
                    }
                }
            }
        }
    }
    $hadir_awal_data['labels'] = array_keys($daily_counts);
    $hadir_awal_data['hadir_awal'] = array_values($daily_counts);

    // Logika untuk mengambil data rekapitulasi kehadiran per bulan
    // (Tidak ada perubahan di sini)
    $monthly_kehadiran_data = [];
    $sql_kehadiran_monthly = "SELECT tanggal, kehadiran FROM apel WHERE kondisi_apel IN ('ada', 'lupa_didokumentasikan') ORDER BY tanggal ASC";
    $result_kehadiran_monthly = $koneksi->query($sql_kehadiran_monthly);

    if ($result_kehadiran_monthly->num_rows > 0) {
        while ($row_monthly = $result_kehadiran_monthly->fetch_assoc()) {
            $month = date('Y-m', strtotime($row_monthly['tanggal']));
            $kehadiran_json = json_decode($row_monthly['kehadiran'], true);
            if (is_array($kehadiran_json)) {
                if (!isset($monthly_kehadiran_data[$month])) {
                    $monthly_kehadiran_data[$month] = [
                        'telat_1' => 0, 'telat_2' => 0, 'telat_3' => 0,
                        'izin' => 0, 'absen' => 0, 'dinas_luar' => 0, 'sakit' => 0, 'cuti' => 0, 'tugas' => 0
                    ];
                }
                foreach ($kehadiran_json as $kehadiran_item) {
                    if (isset($kehadiran_item['status'])) {
                        $status = $kehadiran_item['status'];
                        if (array_key_exists($status, $monthly_kehadiran_data[$month])) {
                            $monthly_kehadiran_data[$month][$status]++;
                        }
                    }
                }
            }
        }
    }

    $monthly_kehadiran_datasets = [];
    $kehadiran_status_labels = [
        'telat_1' => 'Telat 1',
        'telat_2' => 'Telat 2', 'telat_3' => 'Telat 3', 'izin' => 'Izin',
        'absen' => 'Absen', 'dinas_luar' => 'Dinas Luar', 'sakit' => 'Sakit',
        'cuti' => 'Cuti', 'tugas' => 'Tugas'
    ];
    $background_colors = [
        '#ffc107', '#fd7e14', '#e83e8c', 
        '#6f42c1', '#dc3545', '#007bff', '#20c997', '#6c757d', '#adb5bd'
    ];
    
    foreach ($monthly_kehadiran_data as $month => $counts) {
        $datasets = [];
        $data_points = array_values($counts);
        $datasets[] = [
            'label' => 'Jumlah Kehadiran',
            'data' => $data_points,
            'backgroundColor' => $background_colors,
            'borderColor' => 'rgba(255, 255, 255, 1)',
            'borderWidth' => 2
        ];
        $monthly_kehadiran_datasets[$month] = [
            'labels' => array_values($kehadiran_status_labels),
            'datasets' => $datasets
        ];
    }
    ?>

    // Mengubah chart utama menjadi Line Chart untuk tren Hadir Awal
    const rekapApelChartData = {
        labels: <?php echo json_encode($hadir_awal_data['labels']); ?>,
        datasets: [
            {
                label: 'Jumlah Hadir Awal',
                data: <?php echo json_encode($hadir_awal_data['hadir_awal']); ?>,
                fill: false,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }
        ]
    };

    const ctxApel = document.getElementById('rekapApelChart').getContext('2d');
    const rekapApelChart = new Chart(ctxApel, {
        type: 'line',
        data: rekapApelChartData,
        options: {
            scales: {
                y: {
                    beginAtZero: true
                },
                x: {
                    type: 'time',
                    time: {
                        unit: 'day'
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Tren Kehadiran Hadir Awal Harian' }
            }
        }
    });
    
    // Kode untuk Pie Chart dan filter tabel (tidak ada perubahan)
    const monthlyKehadiranData = <?php echo json_encode($monthly_kehadiran_datasets); ?>;
    const container = document.getElementById('kehadiran-charts-container');

    for (const month in monthlyKehadiranData) {
        if (monthlyKehadiranData.hasOwnProperty(month)) {
            const chartData = monthlyKehadiranData[month];
            
            const chartWrapper = document.createElement('div');
            chartWrapper.className = 'chart-wrapper';

            const chartCanvas = document.createElement('canvas');
            chartCanvas.id = `kehadiranChart-${month}`;
            chartWrapper.appendChild(chartCanvas);
            container.appendChild(chartWrapper);
            
            const ctx = chartCanvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: `Rekap Kehadiran Bulan ${month}`
                        }
                    }
                }
            });
        }
    }
    
    // Fungsi untuk filter tabel berdasarkan tanggal
    function filterByDate() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const table = document.querySelector('.data-table tbody');
        const rows = table.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const dateCell = rows[i].getElementsByTagName('td')[0];
            if (dateCell) {
                const rowDate = dateCell.textContent;
                // Memastikan format tanggal cocok
                if ((startDate === '' || rowDate >= startDate) && (endDate === '' || rowDate <= endDate)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
</script>

<?php include '../includes/footer.php'; ?>