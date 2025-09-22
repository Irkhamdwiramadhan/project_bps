<?php
// Mulai sesi dan sertakan file koneksi database, header, dan sidebar
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil semua data dari tabel surveys
$sql_surveys = "SELECT id, nama_survei, singkatan_survei, satuan, seksi_terdahulu, nama_tim_sekarang FROM surveys ORDER BY id ASC";
$result_surveys = $koneksi->query($sql_surveys);

$surveys_list = [];
if ($result_surveys && $result_surveys->num_rows > 0) {
    while ($row = $result_surveys->fetch_assoc()) {
        $surveys_list[] = $row;
    }
}

// Ambil daftar dan jumlah survei berdasarkan 'nama_tim_sekarang'
$sql_surveys_by_team = "SELECT nama_tim_sekarang, COUNT(*) AS jumlah FROM surveys GROUP BY nama_tim_sekarang ORDER BY nama_tim_sekarang ASC";
$result_surveys_by_team = $koneksi->query($sql_surveys_by_team);

$surveys_by_team = [];
if ($result_surveys_by_team && $result_surveys_by_team->num_rows > 0) {
    while ($row = $result_surveys_by_team->fetch_assoc()) {
        $surveys_by_team[] = $row;
    }
}

// Periksa status dari URL untuk notifikasi
$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
// Ambil peran pengguna dari sesi. Jika tidak ada, atur sebagai array kosong.
$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'admin_mitra'];
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
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    body {
        font-family: 'Poppins', sans-serif;
        background: #eef2f5;
        color: #374151;
    }
    .content-wrapper {
        padding: 1rem;
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
        padding: 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }
    thead th {
        background-color: #f9fafb;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.5rem;
        text-align: left;
        border-bottom: 2px solid #e5e7eb;
    }
    tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    tbody tr:last-child td {
        border-bottom: none;
    }
    tbody tr:hover {
        background-color: #f9fafb;
    }
    .form-select {
        display: block;
        width: 100%;
        max-width: 300px;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 1rem;
        background-color: #fff;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
    }
    .btn-primary {
        background-color: #2563eb;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-primary:hover {
        background-color: #1d4ed8;
    }
    .btn-action {
        background-color: #e5e7eb;
        color: #4b5563;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        transition: background-color 0.2s;
    }
    .btn-action:hover {
        background-color: #d1d5db;
    }
    .hidden {
        display: none;
    }
    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        font-weight: 500;
    }
    .alert.success {
        background-color: #d1fae5;
        color: #065f46;
    }
    .alert.error {
        background-color: #fee2e2;
        color: #991b1b;
    }
</style>

<div class="content-wrapper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Daftar Jenis Survei</h1>
            <?php if ($has_access_for_action): ?>
            <a href="tambah_survey.php" class="btn-primary">

                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Tambah Survei
            </a>
            <?php endif; ?>
        </div>

        <?php if ($status == 'success' && !empty($message)) : ?>
            <div class="alert success">
                <?= htmlspecialchars(str_replace('_', ' ', $message)) ?>
            </div>
        <?php elseif ($status == 'error' && !empty($message)) : ?>
            <div class="alert error">
                <?= htmlspecialchars(str_replace('_', ' ', $message)) ?>
            </div>
        <?php endif; ?>
        
        <div class="mb-6">
            <label for="filter-tim" class="block text-gray-700 font-medium mb-2">Filter berdasarkan Tim:</label>
            <select id="filter-tim" class="form-select w-64">
                <option value="semua">Semua Tim</option>
                <?php foreach ($surveys_by_team as $team) : ?>
                    <option value="<?= htmlspecialchars($team['nama_tim_sekarang']) ?>">
                        <?= htmlspecialchars($team['nama_tim_sekarang']) ?> (<?= htmlspecialchars($team['jumlah']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="card">
            <div class="table-container">
                <table id="surveys-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Survei</th>
                            <th>Singkatan</th>
                            <th>Satuan</th>
                            <th>Seksi Dulu</th>
                            <th>Tim Sekarang</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($surveys_list as $survei) : ?>
                            <tr data-team="<?= htmlspecialchars($survei['nama_tim_sekarang']) ?>">
                                <td><?= htmlspecialchars($survei['id']) ?></td>
                                <td><?= htmlspecialchars($survei['nama_survei']) ?></td>
                                <td><?= htmlspecialchars($survei['singkatan_survei']) ?></td>
                                <td><?= htmlspecialchars($survei['satuan']) ?></td>
                                <td><?= htmlspecialchars($survei['seksi_terdahulu']) ?></td>
                                <td><?= htmlspecialchars($survei['nama_tim_sekarang']) ?></td>
                                <td>
                                    <a href="detail_survey.php?id=<?= htmlspecialchars($survei['id']) ?>" class="btn-action">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const filterSelect = document.getElementById('filter-tim');
    const tableRows = document.querySelectorAll('#surveys-table tbody tr');

    filterSelect.addEventListener('change', (event) => {
        const filterValue = event.target.value;
        
        tableRows.forEach(row => {
            const teamName = row.getAttribute('data-team');
            if (filterValue === 'semua' || teamName === filterValue) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
</script>

<?php 
$result_surveys->close();
$result_surveys_by_team->close();
$koneksi->close();
include '../includes/footer.php'; 
?>