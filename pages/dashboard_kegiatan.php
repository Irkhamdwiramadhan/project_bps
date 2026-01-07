<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 1. FILTER
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

$nama_bulan = [
    1=>"Januari", 2=>"Februari", 3=>"Maret", 4=>"April", 5=>"Mei", 6=>"Juni",
    7=>"Juli", 8=>"Agustus", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Desember"
];

// ============================================================================
// GRAFIK 1 & 2: DATA TIM
// ============================================================================
$sql_tim_stats = "SELECT 
                    t.nama_tim, 
                    COUNT(k.id) AS jumlah_kegiatan,
                    COALESCE(SUM(k.target), 0) AS total_target, 
                    COALESCE(SUM(k.realisasi), 0) AS total_realisasi
                  FROM tim t
                  LEFT JOIN kegiatan k 
                    ON t.id = k.tim_id 
                    AND MONTH(k.batas_waktu) = ? 
                    AND YEAR(k.batas_waktu) = ?
                  WHERE t.is_active = 1  -- REVISI: Hanya ambil tim aktif
                  GROUP BY t.id
                  ORDER BY t.nama_tim ASC";

$stmt1 = $koneksi->prepare($sql_tim_stats);
$stmt1->bind_param("ii", $bulan, $tahun);
$stmt1->execute();
$res1 = $stmt1->get_result();

$label_tim = [];
$data_target_tim = [];
$data_realisasi_tim = [];
$data_volume_tim = [];

while($row = $res1->fetch_assoc()) {
    $label_tim[] = $row['nama_tim'];
    $data_target_tim[] = (float)$row['total_target'];
    $data_realisasi_tim[] = (float)$row['total_realisasi'];
    $data_volume_tim[] = (int)$row['jumlah_kegiatan'];
}
$stmt1->close();

// ============================================================================
// GRAFIK 3: INDIVIDU (GROUPED STACKED BAR)
// ============================================================================

$sql_anggota_raw = "
    SELECT 
        member_name,
        nama_tim,
        COALESCE(SUM(target_individu), 0) as total_target,
        COALESCE(SUM(realisasi_individu), 0) as total_realisasi
    FROM (
        -- Pegawai
        SELECT 
            p.nama as member_name, 
            t.nama_tim,
            ka.target_anggota as target_individu, 
            ka.realisasi_anggota as realisasi_individu
        FROM kegiatan_anggota ka
        JOIN anggota_tim at ON ka.anggota_id = at.id
        JOIN tim t ON at.tim_id = t.id
        JOIN pegawai p ON at.member_id = p.id
        JOIN kegiatan k ON ka.kegiatan_id = k.id
        WHERE t.is_active = 1  -- REVISI: Filter Tim Aktif
          AND LOWER(at.member_type) = 'pegawai' 
          AND MONTH(k.batas_waktu) = ? AND YEAR(k.batas_waktu) = ?

        UNION ALL

        -- Mitra
        SELECT 
            m.nama_lengkap as member_name, 
            t.nama_tim,
            ka.target_anggota as target_individu, 
            ka.realisasi_anggota as realisasi_individu
        FROM kegiatan_anggota ka
        JOIN anggota_tim at ON ka.anggota_id = at.id
        JOIN tim t ON at.tim_id = t.id
        JOIN mitra m ON at.member_id = m.id
        JOIN kegiatan k ON ka.kegiatan_id = k.id
        WHERE t.is_active = 1  -- REVISI: Filter Tim Aktif
          AND LOWER(at.member_type) = 'mitra' 
          AND MONTH(k.batas_waktu) = ? AND YEAR(k.batas_waktu) = ?
    ) as combined_data
    GROUP BY member_name, nama_tim
";

$stmt3 = $koneksi->prepare($sql_anggota_raw);
// Parameter tetap 4 (bulan, tahun, bulan, tahun)
$stmt3->bind_param("iiii", $bulan, $tahun, $bulan, $tahun); 
$stmt3->execute();
$res3 = $stmt3->get_result();


$raw_data = [];
$member_totals = []; 
$unique_teams = [];

while($row = $res3->fetch_assoc()) {
    $nama = $row['member_name'];
    $tim  = $row['nama_tim'];
    $tgt  = (float)$row['total_target'];
    $rea  = (float)$row['total_realisasi'];

    $raw_data[$nama][$tim] = ['target' => $tgt, 'realisasi' => $rea];

    if (!isset($member_totals[$nama])) $member_totals[$nama] = 0;
    $member_totals[$nama] += $tgt;

    if (!in_array($tim, $unique_teams)) $unique_teams[] = $tim;
}
$stmt3->close();

// Sorting
arsort($member_totals); 
$sorted_members = array_keys($member_totals);

// Dataset Construction
$datasets = [];
$base_colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1', '#14b8a6'];
$color_idx = 0;

foreach ($unique_teams as $tim) {
    $data_t = [];
    $data_r = [];
    
    $color = $base_colors[$color_idx % count($base_colors)];
    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    $color_alpha = "rgba($r, $g, $b, 0.4)"; 

    foreach ($sorted_members as $member) {
        $data_t[] = isset($raw_data[$member][$tim]['target']) ? $raw_data[$member][$tim]['target'] : 0;
        $data_r[] = isset($raw_data[$member][$tim]['realisasi']) ? $raw_data[$member][$tim]['realisasi'] : 0;
    }

    // TARGET
    $datasets[] = [
        'label' => "$tim (Target)",
        'team_name' => $tim,
        'type_data' => 'Target',
        'data' => $data_t,
        'backgroundColor' => $color_alpha,
        'borderColor' => $color,
        'borderWidth' => 1,
        'stack' => 'stack_target', 
        'barPercentage' => 0.6,
        'categoryPercentage' => 0.8,
        'isFirstTarget' => ($color_idx === 0) 
    ];

    // REALISASI
    $datasets[] = [
        'label' => "$tim (Realisasi)",
        'team_name' => $tim,
        'type_data' => 'Realisasi',
        'data' => $data_r,
        'backgroundColor' => $color,
        'stack' => 'stack_realisasi', 
        'barPercentage' => 0.6,
        'categoryPercentage' => 0.8,
        'isFirstRealisasi' => ($color_idx === 0)
    ];

    $color_idx++;
}

$chart_width_px = max(1000, count($sorted_members) * 120); 
?>

<style>
    .dashboard-header { margin-bottom: 2rem; }
    .dashboard-header h2 { font-weight: 800; color: #1f2937; margin: 0; }
    .dashboard-header p { color: #6b7280; margin-top: 5px; }

    .filter-bar {
        background: #fff; padding: 1.5rem; border-radius: 12px; 
        box-shadow: 0 2px 15px rgba(0,0,0,0.03); margin-bottom: 2rem;
        display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;
        border-left: 5px solid #2563eb;
    }
    .form-control { padding: 0.6rem 1rem; border: 1px solid #e5e7eb; border-radius: 8px; }
    .btn-filter { background: #2563eb; color: white; border: none; padding: 0.65rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-filter:hover { background: #1d4ed8; }

    .chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
    .chart-full { grid-column: span 2; }
    @media (max-width: 992px) { .chart-row { grid-template-columns: 1fr; } .chart-full { grid-column: span 1; } }

    .chart-card {
        background: #fff; padding: 1.5rem; border-radius: 16px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #f3f4f6;
        position: relative;
    }
    .chart-header { 
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; 
        border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem;
    }
    .chart-title { font-size: 1.1rem; font-weight: 700; color: #374151; }
    .chart-subtitle { font-size: 0.85rem; color: #9ca3af; font-weight: 500; }

    .scrollable-chart-container { overflow-x: auto; overflow-y: hidden; width: 100%; padding-bottom: 10px; }
</style>

<main class="main-content">
    <div class="p-4">
        
        <div class="dashboard-header">
            <h2>Dashboard Monitoring</h2>
            <p>Pantau kinerja tim dan anggota secara real-time.</p>
        </div>

        <form method="GET" class="filter-bar">
            <div style="flex: 1; max-width: 200px;">
                <label style="font-weight:600; font-size:0.9rem; margin-bottom:5px; display:block;">Bulan</label>
                <select name="bulan" class="form-control">
                    <?php for($i=1;$i<=12;$i++): ?>
                        <option value="<?= $i ?>" <?= $i==$bulan?'selected':'' ?>><?= $nama_bulan[$i] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="flex: 1; max-width: 150px;">
                <label style="font-weight:600; font-size:0.9rem; margin-bottom:5px; display:block;">Tahun</label>
                <input type="number" name="tahun" value="<?= $tahun ?>" class="form-control">
            </div>
            <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill"></i> Filter</button>
        </form>

        <!-- CHART BARIS 1 -->
        <div class="chart-row">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title"><i class="bi bi-activity text-success me-2"></i>grafik volume kegiatan tim perbulan</div>
                </div>
                <div style="height: 300px;">
                    <canvas id="chartVolume"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title"><i class="bi bi-bar-chart-line text-primary me-2"></i>grafik Kinerja Tim target vs realisasi</div>
                </div>
                <div style="height: 300px;">
                    <canvas id="chartTim"></canvas>
                </div>
            </div>
        </div>

        <!-- CHART 3: INDIVIDU -->
        <div class="chart-row">
            <div class="chart-card chart-full">
                <div class="chart-header">
                    <div>
                        <div class="chart-title"><i class="bi bi-bar-chart-steps text-warning me-2"></i>Target vs Realisasi Individu</div>
                        <span class="chart-subtitle">
                            <span style="margin-right:15px;"><span style="display:inline-block;width:12px;height:12px;background:#ccc;border:1px solid #999;opacity:0.5;"></span> Target</span>
                            <span><span style="display:inline-block;width:12px;height:12px;background:#666;"></span> Realisasi</span>
                        </span>
                    </div>
                </div>
                
                <?php if (empty($sorted_members)): ?>
                    <div style="text-align:center; padding:50px; color:#9ca3af;">
                        <i class="bi bi-inbox" style="font-size:3rem;"></i>
                        <p class="mt-2">Belum ada data kegiatan untuk bulan ini.</p>
                    </div>
                <?php else: ?>
                    <div class="scrollable-chart-container">
                        <div style="width: <?= $chart_width_px ?>px; height: 500px; position: relative;">
                            <canvas id="chartAnggota"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<script>
    Chart.register(ChartDataLabels);

    // 1. TIM
    new Chart(document.getElementById('chartTim'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($label_tim) ?>,
            datasets: [
                { label: 'Target', data: <?= json_encode($data_target_tim) ?>, backgroundColor: '#e5e7eb', borderWidth:0 },
                { label: 'Realisasi', data: <?= json_encode($data_realisasi_tim) ?>, backgroundColor: '#3b82f6', borderWidth:0 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true }, x: { grid: { display: false } } },
            plugins: { legend: { position: 'top' }, datalabels: { display: false } }
        }
    });

    // 2. VOLUME
    new Chart(document.getElementById('chartVolume'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($label_tim) ?>,
            datasets: [{
                label: 'Jml Kegiatan', data: <?= json_encode($data_volume_tim) ?>,
                borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.8)', borderRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true }, x: { grid: { display: false } } },
            plugins: { legend: { display: false }, datalabels: { align: 'top', anchor: 'end', color: '#10b981', formatter: (v) => v > 0 ? v : '' } }
        }
    });

   // 3. INDIVIDU (VERTIKAL + VALUES ON BAR)
    <?php if (!empty($sorted_members)): ?>
    new Chart(document.getElementById('chartAnggota'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($sorted_members) ?>,
            datasets: <?= json_encode($datasets) ?>
        },
        options: {
            indexAxis: 'x',
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { bottom: 30 } },
            scales: {
                x: { 
                    stacked: true, 
                    grid: { display: false }, 
                    ticks: { 
                        autoSkip: false,
                        // --- REVISI DI SINI ---
                        maxRotation: 45,   // Maksimal kemiringan 45 derajat
                        minRotation: 45,   // Minimal kemiringan 45 derajat (memaksa miring)
                        padding: 10,       // Jarak label ke grafik
                        font: { 
                            weight: 'bold', 
                            size: 9        // Ukuran font diperkecil (sebelumnya 11)
                        }
                        // ----------------------
                    } 
                },
                y: { stacked: true, grid: { color: '#f3f4f6' } }
            },
            interaction: {
                mode: 'nearest', intersect: true, axis: 'xy'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    // ... (bagian tooltip biarkan tetap sama seperti sebelumnya) ...
                    filter: function(tooltipItem) { return tooltipItem.raw > 0; },
                    callbacks: {
                        title: function(tooltipItems) { return tooltipItems[0].label; },
                        label: function(context) {
                            let ds = context.dataset;
                            let val = context.raw;
                            let timName = ds.team_name;
                            let typeData = ds.type_data;

                            if (ds.stack === 'stack_target') {
                                return `📋 ${timName} (${typeData}): ${val}`;
                            } else {
                                let datasetIndex = context.datasetIndex;
                                let targetVal = context.chart.data.datasets[datasetIndex - 1].data[context.dataIndex];
                                let persen = targetVal > 0 ? Math.round((val / targetVal) * 100) : 0;
                                return `✅ ${timName} (${typeData}): ${val} (${persen}%)`;
                            }
                        },
                        footer: function(tooltipItems) {
                            let totT = 0; let totR = 0;
                            tooltipItems.forEach(function(item) {
                                if(item.dataset.stack === 'stack_target') totT += item.raw;
                                if(item.dataset.stack === 'stack_realisasi') totR += item.raw;
                            });
                            return `\nTOTAL: Target ${totT} | Realisasi ${totR}`;
                        }
                    },
                    backgroundColor: 'rgba(0,0,0,0.8)', padding: 12, cornerRadius: 8
                },
                datalabels: {
                    color: function(context) {
                        return context.dataset.stack === 'stack_target' ? '#444' : '#fff';
                    },
                    anchor: 'center', 
                    align: 'center',
                    formatter: (val) => val > 0 ? val : '', 
                    font: { weight: 'bold', size: 10 } // Ukuran font datalabels (angka di batang) juga bisa disesuaikan
                }
            }
        }
    });
    
    // LABEL MANUAL: TARGET & REALISASI DI SUMBU X
    Chart.register({
        id: 'bottomLabels',
        afterDraw: function(chart) {
            if (chart.canvas.id !== 'chartAnggota') return;
            const ctx = chart.ctx;
            const yAxis = chart.scales.y;
            ctx.save();
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#888';

            chart.data.labels.forEach((label, index) => {
                const metaTarget = chart.getDatasetMeta(0); 
                const metaReal = chart.getDatasetMeta(1);   
                if (metaTarget.data[index] && metaReal.data[index]) {
                    const xT = metaTarget.data[index].x;
                    const xR = metaReal.data[index].x;
                    const yPos = yAxis.bottom + 15; 
                    ctx.fillText("Target", xT, yPos);
                    ctx.fillText("Real.", xR, yPos);
                }
            });
            ctx.restore();
        }
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>