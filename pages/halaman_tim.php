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
$allowed_roles_for_action = ['super_admin', 'ketua_tim']; 

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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

:root {
  --primary-color: #324057;
  --primary-hover: #455873;
  --background-color: #f9fafb;
  --card-bg: #ffffff;
  --border-color: #e5e7eb;
  --text-dark: #1f2937;
  --text-muted: #6b7280;
}

/* ====== GLOBAL ====== */
body {
  font-family: 'Poppins', sans-serif;
  background-color: var(--background-color);
  color: var(--text-dark);
  margin: 0;
  padding: 0;
}

/* ====== HEADER ====== */
.header-content {
  background-color: var(--card-bg);
  padding: 1rem 2rem;
  border-bottom: 1px solid var(--border-color);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.header-content h2 {
  font-weight: 600;
  margin: 0;
}
.header-content .btn {
  background-color: var(--primary-color);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1.2rem;
  font-weight: 500;
  transition: all 0.3s ease;
}
.header-content .btn:hover {
  background-color: var(--primary-hover);
  transform: translateY(-1px);
}

/* ====== CARD ====== */
.card {
  background-color: var(--card-bg);
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border: 1px solid var(--border-color);
  overflow: hidden;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
}
.card-header h5 {
  font-weight: 600;
  margin: 0;
}

/* ====== SEARCH & FILTER ====== */
.search-box input {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.6rem 1rem;
  width: 260px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}
.search-box input:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px rgba(50, 64, 87, 0.2);
}

.btn-outline-primary,
.btn-outline-success,
.btn-outline-secondary {
  border-radius: 8px;
  padding: 0.5rem 0.9rem;
  font-weight: 500;
  font-size: 0.9rem;
  transition: all 0.3s ease;
}

/* ====== TABLE ====== */
.table-responsive {
  overflow-x: auto;
}
.table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 700px;
}
.table thead {
  background-color: #f1f5f9;
}
.table th {
  padding: 1rem 1rem;
  text-transform: uppercase;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
  color: #475569;
  border-bottom: 2px solid var(--border-color);
  text-align: left;
}
.table td {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}
.table tbody tr:hover {
  background-color: #f9fafc;
}

/* ====== BUTTON ACTION ====== */
/* ====== BUTTON ACTION ====== */
.btn-action-group {
  display: flex;
  flex-wrap: wrap;
  gap: 2px; /* REVISI: Dikecilkan dari 6px menjadi 2px agar lebih rapat */
  align-items: center; /* Menjaga agar tombol sejajar vertikal */
}

/* Tambahan: Pastikan form di dalam grup tidak menambah jarak */
.btn-action-group form {
  margin: 0;
  padding: 0;
  display: flex; /* Agar tombol di dalam form pas */
}

.btn-action-group .btn {
  width: 32px; /* Opsional: Sedikit dikecilkan dari 36px jika ingin lebih compact */
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px; /* Radius disesuaikan dengan ukuran baru */
  border: none;
  transition: all 0.3s ease;
  font-size: 0.85rem; /* Ukuran icon sedikit disesuaikan */
}

.btn-toggle-status {
    width: 32px; /* Samakan ukuran dengan tombol lain */
    height: 32px;
    /* ... style lainnya tetap sama ... */
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}
.btn-toggle-status.active {
  background-color: #22c55e;
  color: #fff;
}
.btn-toggle-status.inactive {
  background-color: #6b7280;
  color: #fff;
}
.btn-toggle-status:hover {
  opacity: 0.9;
}

/* ====== RESPONSIVE ====== */
@media (max-width: 992px) {
  .header-content {
    padding: 1rem;
  }
  .search-box input {
    width: 100%;
  }
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .table {
    font-size: 0.9rem;
  }
}

@media (max-width: 768px) {
  .header-content h2 {
    font-size: 1.4rem;
  }
  .header-content .btn {
    width: 100%;
    text-align: center;
  }
  .card-header {
    align-items: stretch;
  }
  .card-header .d-flex {
    flex-direction: column;
    align-items: stretch;
    width: 100%;
  }
  .btn-outline-primary,
  .btn-outline-success,
  .btn-outline-secondary {
    width: 100%;
  }
  .btn-action-group {
    justify-content: flex-start;
  }
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
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5>Daftar Tim</h5>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="search-box">
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari tim atau ketua..." onkeyup="filterTabel()">
                    </div>
                    <div>
                        <button class="btn btn-outline-primary btn-sm" onclick="filterStatus('all')">Semua</button>
                        <button class="btn btn-outline-success btn-sm" onclick="filterStatus('1')">Aktif</button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="filterStatus('0')">Nonaktif</button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table" id="tabelTim">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Tim</th>
                            <th>Deskripsi</th>
                            <th>Ketua Tim</th>
                            <th>Jumlah Anggota</th>
                            <th>Status</th>
                            <?php if ($has_access_for_action): ?>
                                <th style="width: 20%;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT 
                                    t.id, 
                                    t.nama_tim,
                                    t.deskripsi, 
                                    t.is_active,
                                    p.nama AS nama_ketua,
                                    (SELECT COUNT(*) FROM anggota_tim WHERE tim_id = t.id) AS jumlah_anggota
                                FROM tim t
                                LEFT JOIN pegawai p ON t.ketua_tim_id = p.id
                                ORDER BY t.nama_tim ASC";
                        $result = $koneksi->query($sql);

                        if ($result->num_rows > 0) {
                            $nomor = 1;
                            while($row = $result->fetch_assoc()) {
                                $id_tim = $row['id'];
                                $nama_tim = htmlspecialchars($row['nama_tim']);
                                $deskripsi = htmlspecialchars($row['deskripsi'] ?? '-');
                                $is_active = $row['is_active'];

                                $nama_ketua_raw = $row['nama_ketua'];
                                $nama_ketua = $nama_ketua_raw ? htmlspecialchars($nama_ketua_raw) : '<span class="text-muted">Belum Ditentukan</span>';
                                $jumlah_anggota = $row['jumlah_anggota'];
                                $search_data = strtolower($nama_tim . ' ' . $nama_ketua_raw);

                                echo "<tr data-search='$search_data' data-status='$is_active'>";
                                echo "<td>" . $nomor++ . "</td>";
                                echo "<td class='team-name'>$nama_tim</td>";
                                echo "<td>$deskripsi</td>";
                                echo "<td>$nama_ketua</td>";
                                echo "<td>$jumlah_anggota orang</td>";
                                echo "<td>" . ($is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>') . "</td>";

                                // Tombol Aksi
                                echo '<td>
                                        <div class="btn-action-group">';
                                echo '<a href="detail_tim.php?id='.$id_tim.'" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>';

                                if ($has_access_for_action) {
                                    echo '<a href="edit_tim.php?id='.$id_tim.'" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-pencil-alt"></i></a>';
                                    // --- Tombol Hapus (Icon disesuaikan) ---
echo '<a href="../proses/proses_hapus_tim.php?id='.$id_tim.'" 
        class="btn btn-danger btn-sm" 
        title="Hapus" 
        onclick="return confirm(\'Yakin ingin menghapus tim ini? Data anggota dan target kinerja terkait mungkin akan ikut terhapus atau kehilangan induknya.\');">
        <i class="fas fa-trash-alt"></i>
      </a>';

                                    // Tombol toggle status
                                    echo '<form action="../proses/proses_toggle_status_tim.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="tim_id" value="'.$id_tim.'">
                                            <button type="submit" class="btn-toggle-status '.($is_active ? 'btn-secondary' : 'btn-success').'" title="'.($is_active ? 'Nonaktifkan' : 'Aktifkan').'">
                                                <i class="fas fa-toggle-on"></i>
                                            </button>
                                        </form>';
                                }

                                echo '  </div></td>';
                                echo "</tr>";
                            }
                        } else {
                            $kolom_span = $has_access_for_action ? 7 : 6;
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
    
    for (let i = 1; i < rows.length; i++) {
        let row = rows[i];
        let keyword = row.getAttribute('data-search');
        if (keyword) {
            row.style.display = keyword.includes(input) ? '' : 'none';
        }
    }
}

// Filter berdasarkan status: 'all', '1', '0'
function filterStatus(status) {
    let table = document.getElementById('tabelTim');
    let rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        let row = rows[i];
        let rowStatus = row.getAttribute('data-status');
        if (status === 'all') {
            row.style.display = '';
        } else {
            row.style.display = (rowStatus === status) ? '' : 'none';
        }
    }
}
</script>


<?php include '../includes/footer.php'; ?>