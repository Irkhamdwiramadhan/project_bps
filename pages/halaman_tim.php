<?php
// halaman_tim.php

session_start();
include '../includes/koneksi.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Ambil peran pengguna dari sesi
$user_roles = $_SESSION['user_role'] ?? [];

// Tentukan peran yang diizinkan untuk melakukan aksi (tambah, edit, hapus)
$allowed_roles_for_action = ['super_admin', 'admin_simpedu']; 

// Periksa apakah pengguna memiliki hak akses
$has_access_for_action = false;
foreach ((array)$user_roles as $role) {
    if (in_array($role, $allowed_roles_for_action)) {
        $has_access_for_action = true;
        break;
    }
}
?>

<style>
    /* Mengimpor font dan variabel warna untuk konsistensi */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
    :root {
        --primary-color: #4A90E2;
        --background-color: #f4f7f9;
        --card-bg-color: #ffffff;
        --header-text-color: #4A4A4A;
        --border-color: #e2e8f0;
        --shadow-color: rgba(0, 0, 0, 0.05);
    }
    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--background-color);
    }

    /* Header Utama */
    .header-content {
        background-color: var(--card-bg-color);
        padding: 20px 30px;
        border-bottom: 1px solid var(--border-color);
        box-shadow: 0 2px 4px var(--shadow-color);
    }
    .header-content h2 {
        font-weight: 600;
        color: var(--header-text-color);
        margin: 0;
    }

    /* Kartu Konten */
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px var(--shadow-color);
        background-color: var(--card-bg-color);
    }
    .card-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .card-header h5 {
        font-weight: 600;
        margin: 0;
    }
    .search-box {
        position: relative;
    }
    #searchInput {
        border-radius: 8px;
        border: 1px solid var(--border-color);
        padding: 8px 12px;
        width: 280px;
    }

    /* Tabel Modern */
    .table-responsive {
        padding: 0;
    }
    .table {
        border-collapse: separate;
        border-spacing: 0;
    }
    .table thead {
        background-color: #f8fafc;
    }
    .table th {
        font-weight: 600;
        color: #475569;
        border-bottom: 2px solid var(--border-color);
        padding: 1rem 1.25rem;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }
    .table td {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--border-color);
    }
     .table tbody tr:last-child td {
        border-bottom: none;
    }
    .table tbody tr:hover {
        background-color: #f1f5f9;
    }
    .team-name {
        font-weight: 500;
    }

    /* Grup Tombol Aksi */
    .btn-action-group {
        display: flex;
        gap: 8px;
    }
    .btn-action-group .btn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }
</style>

<main class="main-content">
    <div class="header-content d-flex justify-content-between align-items-center">
        <h2>Manajemen Tim</h2>
        <?php if ($has_access_for_action): ?>
            <a href="tambah_tim.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Tambah Tim
            </a>
        <?php endif; ?>
    </div>

    <div class="p-4">
        <div class="card">
            <div class="card-header">
                <h5>Daftar Tim</h5>
                <div class="search-box">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari tim atau ketua..." onkeyup="filterTabel()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="tabelTim">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Tim</th>
                            <th>Ketua Tim</th>
                            <th>Jumlah Anggota</th>
                            <?php if ($has_access_for_action): ?>
                                <th style="width: 15%;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT 
                                    t.id, 
                                    t.nama_tim, 
                                    p.nama AS nama_ketua,
                                    (SELECT COUNT(*) FROM anggota_tim WHERE tim_id = t.id) AS jumlah_anggota
                                FROM 
                                    tim t
                                LEFT JOIN 
                                    pegawai p ON t.ketua_tim_id = p.id
                                ORDER BY 
                                    t.nama_tim ASC";
                        
                        $result = $koneksi->query($sql);
                        
                        if ($result->num_rows > 0) {
                            $nomor = 1;
                            while($row = $result->fetch_assoc()) {
                                $id_tim = $row['id'];
                                $nama_tim = htmlspecialchars($row['nama_tim']);
                                $nama_ketua_raw = $row['nama_ketua'];
                                $nama_ketua = $nama_ketua_raw ? htmlspecialchars($nama_ketua_raw) : '<span class="text-muted">Belum Ditentukan</span>';
                                $jumlah_anggota = $row['jumlah_anggota'];
                                $search_data = strtolower($nama_tim . ' ' . $nama_ketua_raw);

                                echo "<tr data-search='$search_data'>";
                                echo "<td>" . $nomor++ . "</td>";
                                echo "<td class='team-name'>$nama_tim</td>";
                                echo "<td>$nama_ketua</td>";
                                echo "<td>$jumlah_anggota orang</td>";
                                
                      // Selalu tampilkan kolom Aksi dan tombol Detail untuk semua pengguna
                            echo '<td>
                                    <div class="btn-action-group">
                                        <a href="detail_tim.php?id='.$id_tim.'" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>';

                            // Jika pengguna memiliki hak akses, tampilkan juga tombol Edit dan Hapus
                            if ($has_access_for_action) {
                                echo '<a href="edit_tim.php?id='.$id_tim.'" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-pencil-alt"></i></a>
                                    <a href="../proses/proses_hapus_tim.php?id='.$id_tim.'" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm(\'Yakin ingin menghapus tim ini?\')"><i class="fas fa-trash"></i></a>';
                            }

                            echo '  </div>
                                </td>';
                            echo "</tr>";
                            }
                        } else {
                            $kolom_span = $has_access_for_action ? 5 : 4;
                            echo "<tr><td colspan='$kolom_span' class='text-center p-5'>Tidak ada data tim yang tersedia.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    function filterTabel() {
        let input = document.getElementById('searchInput').value.toLowerCase();
        let table = document.getElementById('tabelTim');
        let rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) { // Mulai dari 1 untuk lewati header
            let row = rows[i];
            let keyword = row.getAttribute('data-search');
            if (keyword) {
                row.style.display = keyword.includes(input) ? '' : 'none';
            }
        }
    }
</script>

<?php include '../includes/footer.php'; ?>