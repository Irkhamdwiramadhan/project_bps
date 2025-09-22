<?php
session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Ambil peran pengguna dari sesi. Jika tidak ada, atur sebagai array kosong.
$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran mana saja yang diizinkan untuk mengakses fitur ini
$allowed_roles_for_action = ['super_admin', 'admin_pegawai'];
// Periksa apakah pengguna memiliki salah satu peran yang diizinkan untuk melihat aksi
$has_access_for_action = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break; // Keluar dari loop setelah menemukan kecocokan
    }
}
// Pastikan pengguna sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Menentukan kondisi WHERE berdasarkan permintaan filter
$filter = $_GET['filter'] ?? 'active'; // Default filter: 'active'
$where_clause = "";
if ($filter === 'active') {
    $where_clause = "WHERE is_active = 1";
} elseif ($filter === 'inactive') {
    $where_clause = "WHERE is_active = 0";
}
// Jika filter adalah 'all', where_clause tetap kosong
?>

<main class="main-content">
    <div class="header-content" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 15px 20px;">
        <h2>Data Pegawai</h2>
        <?php if ($has_access_for_action): ?>
            <a href="tambah_pegawai.php" class="btn btn-primary">Tambah Pegawai</a>
        <?php endif; ?>
    </div>
   


        <div class="filter-buttons" style="display: flex; justify-content: flex-start; gap: 5px; flex-wrap: wrap; margin-top: 10px;">
            <a href="pegawai.php?filter=all" class="btn btn-secondary <?php echo $filter === 'all' ? 'active-filter' : ''; ?>">Semua Pegawai</a>
            <a href="pegawai.php?filter=active" class="btn btn-secondary <?php echo $filter === 'active' ? 'active-filter' : ''; ?>">Pegawai Aktif</a>
            <a href="pegawai.php?filter=inactive" class="btn btn-secondary <?php echo $filter === 'inactive' ? 'active-filter' : ''; ?>">Pegawai Nonaktif</a>
        </div>
    

    <div class="card">
        <div style="display: flex; justify-content: flex-end; padding: 10px;">
            <input type="text" id="searchInput" placeholder="Cari pegawai..." 
                   style="padding: 8px; width: 300px; border: 1px solid #ccc; border-radius: 5px;"
                   onkeyup="filterPegawai()">
        </div>

        <style>
            .pegawai-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
                padding: 15px;
            }
            .pegawai-card {
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
                transition: transform 0.2s ease;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .pegawai-card:hover {
                transform: translateY(-3px);
            }
            .pegawai-card.inactive {
                opacity: 0.6;
                filter: grayscale(100%);
            }
            .pegawai-photo {
                width: 100%;
                height: 180px;
                object-fit: contain;
                background: #f5f5f5;
                padding: 5px;
            }
            .pegawai-body {
                padding: 15px;
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .pegawai-name {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .pegawai-info {
                font-size: 14px;
                color: #555;
                margin-bottom: 3px;
            }
            .btn-action-group {
                display: flex;
                justify-content: center;
                gap: 5px;
                margin-top: 10px;
            }
            .btn-action {
                padding: 6px 10px;
                border-radius: 5px;
                font-size: 13px;
                text-decoration: none;
                color: #fff;
                margin: 0 5px;
                text-align: center;
            }
            .btn-action.detail { background: #3498db; }
            .btn-action.edit { background: #f1c40f; color: #000; }
            .btn-action.deactivate { background: #e74c3c; }
            .btn-action.activate { background: #2ecc71; }
            .btn.btn-secondary {
                background-color: #f1f5f9;
                color: #475569;
                border: 1px solid #cbd5e1;
                padding: 8px 16px;
                border-radius: 6px;
                text-decoration: none;
                transition: background-color 0.2s;
            }
            .btn.btn-secondary:hover {
                background-color: #e2e8f0;
            }
            .btn.btn-secondary.active-filter {
                background-color: #3b82f6;
                color: #fff;
                border-color: #3b82f6;
            }
        </style>

        <div class="pegawai-grid" id="pegawaiGrid">
            <?php
            // Mengambil data pegawai dengan kondisi yang sudah diperbarui
            $sql = "SELECT * FROM pegawai " . $where_clause . " ORDER BY no_urut ASC";
            $result = $koneksi->query($sql);
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $nama = htmlspecialchars($row['nama']);
                    $nip_bps = htmlspecialchars($row['nip_bps']);
                    $jabatan = htmlspecialchars($row['jabatan']);
                    $seksi = htmlspecialchars($row['seksi']);
                    $is_active = $row['is_active'];
                    $id = $row['id'];
                    $card_class = $is_active ? '' : 'inactive';

                    $foto = !empty($row['foto']) ? "../assets/img/pegawai/" . $row['foto'] : "../assets/img/pegawai/default.png";

                    echo "<div class='pegawai-card $card_class' data-search='" . strtolower("$nama $nip_bps $jabatan $seksi") . "'>";
                    
                    // Foto
                    echo "<img src='" . htmlspecialchars($foto) . "' alt='Foto $nama' class='pegawai-photo'>";

                    // Body
                    echo "<div class='pegawai-body'>";
                    echo "<div>";
                    echo "<div class='pegawai-name'>$nama</div>";
                    echo "<div class='pegawai-info'>NIP BPS: $nip_bps</div>";
                    echo "<div class='pegawai-info'>Jabatan: $jabatan</div>";
                    echo "<div class='pegawai-info'>Seksi: $seksi</div>";
                    echo "</div>";
                    
                    // Kontrol Aksi berdasarkan role
                    echo "<div class='btn-action-group'>";
                    echo "<a href='detail_pegawai.php?id=$id' class='btn-action detail'>Detail</a>";
                    if ($has_access_for_action) {
                        echo "<a href='edit_pegawai.php?id=$id' class='btn-action edit'>Edit</a>";
                        if ($is_active) {
                            echo "<a href='../proses/proses_status_pegawai.php?id=$id&status=0' class='btn-action deactivate' onclick='return confirm(\"Apakah Anda yakin ingin menonaktifkan data ini?\")'>Nonaktifkan</a>";
                        } else {
                            echo "<a href='../proses/proses_status_pegawai.php?id=$id&status=1' class='btn-action activate' onclick='return confirm(\"Apakah Anda yakin ingin mengaktifkan data ini?\")'>Aktifkan</a>";
                        }
                    }
                    echo "</div>"; // end btn-action-group

                    echo "</div>"; // end body

                    echo "</div>"; // end card
                }
            } else {
                echo "<p style='padding: 15px;'>Tidak ada data pegawai yang tersedia.</p>";
            }
            ?>
        </div>

        <script>
            function filterPegawai() {
                let input = document.getElementById('searchInput').value.toLowerCase();
                let cards = document.querySelectorAll('#pegawaiGrid .pegawai-card');
                cards.forEach(card => {
                    let keyword = card.getAttribute('data-search');
                    card.style.display = keyword.includes(input) ? '' : 'none';
                });
            }
        </script>
    </div>
</main>

<?php include '../includes/footer.php'; ?>