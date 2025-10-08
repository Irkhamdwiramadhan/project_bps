<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ambil bulan dan tahun dari GET (filter)
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Daftar nama bulan
$nama_bulan = [
    1=>"Januari",2=>"Februari",3=>"Maret",4=>"April",5=>"Mei",6=>"Juni",
    7=>"Juli",8=>"Agustus",9=>"September",10=>"Oktober",11=>"November",12=>"Desember"
];

// Query jumlah kegiatan per tim
$sql = "SELECT t.nama_tim, COUNT(k.id) AS jumlah_kegiatan
        FROM tim t
        LEFT JOIN kegiatan k 
            ON t.id = k.tim_id 
            AND MONTH(k.created_at) = ? 
            AND YEAR(k.created_at) = ?
        GROUP BY t.id
        ORDER BY t.nama_tim ASC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$result = $stmt->get_result();

$tim_labels = [];
$jumlah_kegiatan = [];
while($row = $result->fetch_assoc()) {
    $tim_labels[] = $row['nama_tim'];
    $jumlah_kegiatan[] = (int)$row['jumlah_kegiatan'];
}
$stmt->close();

// Fallback jika data kosong
if(empty($tim_labels)) {
    $tim_labels = ["Tidak ada data"];
    $jumlah_kegiatan = [0];
}
?>

<main class="main-content">
    <div class="header-content" style="display: flex; align-items: center; gap: 10px; padding: 15px 20px;">
        <h2>Jumlah Kegiatan Tim</h2>
    </div>

    <div class="p-4">
        <div class="card" style="padding: 20px;">
            <!-- Filter Bulan & Tahun -->
            <form method="GET" class="mb-3" style="display:flex; gap:10px; align-items:center;">
                <label>Bulan:</label>
                <select name="bulan" class="form-select" style="width:auto;">
                    <?php for($i=1;$i<=12;$i++): ?>
                        <option value="<?= $i ?>" <?= $i==$bulan?'selected':'' ?>><?= $nama_bulan[$i] ?></option>
                    <?php endfor; ?>
                </select>
                <label>Tahun:</label>
                <input type="number" name="tahun" value="<?= $tahun ?>" class="form-control" style="width:100px;">
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>

            <canvas id="barChart"></canvas>
        </div>
    </div>
</main>

<!-- Plugin ChartDataLabels harus dimuat sebelum inisialisasi chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<script>
const ctx = document.getElementById('barChart').getContext('2d');

const barChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($tim_labels) ?>,
        datasets: [{
            label: 'Jumlah Kegiatan',
            data: <?= json_encode($jumlah_kegiatan) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + ' kegiatan';
                    }
                }
            },
            datalabels: {
                anchor: 'end',
                align: 'top',
                formatter: function(value) {
                    return value;
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Jumlah Kegiatan' }
            },
            x: {
                title: { display: true, text: 'Nama Tim' },
                ticks: { autoSkip: false, maxRotation: 90, minRotation: 45 }
            }
        }
    },
    plugins: [ChartDataLabels]
});
</script>

<?php include '../includes/footer.php'; ?>
