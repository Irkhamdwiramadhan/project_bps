<?php
session_start();

// Cek apakah pengguna sudah login dan memiliki peran super_admin
$user_roles = $_SESSION['user_role'] ?? [];
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !is_array($user_roles) || !in_array('super_admin', $user_roles)) {
    header('Location: ../login.php');
    exit;
}

// Tambahkan file koneksi
include '../includes/koneksi.php';

$success_message = '';
$error_message = '';

// Proses saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_roles = $_POST['roles'] ?? [];

    // Ambil semua ID pegawai yang aktif di database
    $sql_all_pegawai_ids = "SELECT id FROM pegawai WHERE is_active ='1'";
    $result_all_pegawai_ids = $koneksi->query($sql_all_pegawai_ids);
    $all_pegawai_ids = [];
    if ($result_all_pegawai_ids) {
        while ($row = $result_all_pegawai_ids->fetch_assoc()) {
            $all_pegawai_ids[] = $row['id'];
        }
    }

    // Mulai transaksi
    $koneksi->begin_transaction();
    $all_success = true;

    try {
        // Siapkan pernyataan untuk menghapus dan memasukkan peran
        $stmt_delete = $koneksi->prepare("DELETE FROM pegawai_roles WHERE pegawai_id = ?");
        $stmt_insert = $koneksi->prepare("INSERT INTO pegawai_roles (pegawai_id, role_id) VALUES (?, ?)");

        if (!$stmt_delete || !$stmt_insert) {
            throw new Exception("Gagal menyiapkan statement: " . $koneksi->error);
        }

        // Ambil ID peran dari tabel 'roles'
        $sql_role_ids = "SELECT id, nama FROM roles";
        $result_role_ids = $koneksi->query($sql_role_ids);
        $role_ids_map = [];
        while ($row = $result_role_ids->fetch_assoc()) {
            $role_ids_map[$row['nama']] = $row['id'];
        }

        foreach ($all_pegawai_ids as $pegawai_id) {
            // Hapus peran yang ada untuk setiap pegawai
            $stmt_delete->bind_param("i", $pegawai_id);
            if (!$stmt_delete->execute()) {
                throw new Exception("Gagal menghapus peran untuk pegawai ID " . $pegawai_id);
            }

            // Masukkan peran baru hanya jika ada yang dipilih dari form
            if (isset($submitted_roles[$pegawai_id]) && !empty($submitted_roles[$pegawai_id])) {
                foreach ($submitted_roles[$pegawai_id] as $role_name) {
                    $role_id_terpilih = $role_ids_map[$role_name] ?? null;
                    if ($role_id_terpilih) {
                        $stmt_insert->bind_param("ii", $pegawai_id, $role_id_terpilih);
                        if (!$stmt_insert->execute()) {
                            throw new Exception("Gagal memasukkan peran baru untuk pegawai ID " . $pegawai_id);
                        }
                    }
                }
            }
        }

        // Tutup statement
        $stmt_delete->close();
        $stmt_insert->close();

        // Jika semua operasi berhasil, commit transaksi
        $koneksi->commit();
        $success_message = "Akses berhasil diperbarui.";

    } catch (Exception $e) {
        $koneksi->rollback();
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
    }
}
// ... (Bagian lainnya tetap sama)

// Ambil semua data pegawai
$sql_pegawai = "SELECT id, nama, jabatan FROM pegawai WHERE is_active ='1' ORDER BY nama ASC";
$result_pegawai = $koneksi->query($sql_pegawai);

$all_pegawai = [];
if ($result_pegawai->num_rows > 0) {
    while ($row = $result_pegawai->fetch_assoc()) {
        $all_pegawai[] = $row;
    }
}

// Ambil semua data peran yang tersedia
$sql_available_roles = "SELECT nama FROM roles";
$result_available_roles = $koneksi->query($sql_available_roles);
$available_roles = [];
while ($row = $result_available_roles->fetch_assoc()) {
    $available_roles[] = $row['nama'];
}

// Ambil semua data akses saat ini untuk ditampilkan
$sql_current_roles = "SELECT pr.pegawai_id, r.nama FROM pegawai_roles pr JOIN roles r ON pr.role_id = r.id";
$result_current_roles = $koneksi->query($sql_current_roles);

$current_roles_by_pegawai = [];
while ($row = $result_current_roles->fetch_assoc()) {
    $pegawai_id = $row['pegawai_id'];
    $role_name = $row['nama'];
    $current_roles_by_pegawai[$pegawai_id][] = $role_name;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akses Pegawai</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style_dashboard.css">
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f7f9;
        margin: 0;
        padding: 0;
        display: flex;
    }

    .main-content {
        flex-grow: 1;
        padding: 30px;
        background-color: #fff;
        margin-left: 250px;
        border-left: 1px solid #e0e0e0;
        transition: margin-left 0.3s ease; /* Transisi untuk pergerakan */
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
    }

    h1 {
        color: #333;
        border-bottom: 2px solid #007bff;
        padding-bottom: 10px;
        margin-bottom: 25px;
    }

    .table-responsive {
        overflow-x: auto;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background-color: #fff;
        min-width: 600px; /* Minimal lebar tabel untuk mencegah tumpang tindih */
    }

    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }

    th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #555;
        text-transform: uppercase;
    }

    td {
        font-size: 0.95em;
        color: #444;
    }

    tr:hover {
        background-color: #fdfdfd;
    }

    .role-checkboxes {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .role-checkbox {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .role-checkbox input[type="checkbox"] {
        transform: scale(1.2);
    }

    .btn-update {
        padding: 12px 25px;
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
        font-size: 1em;
        font-weight: bold;
        box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
    }

    .btn-update:hover {
        background-color: #218838;
        transform: translateY(-2px);
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
    }

    .form-action {
        text-align: right;
        padding: 20px 0;
    }

    /* --- Media Queries untuk Responsif --- */
    @media (max-width: 992px) {
        .main-content {
            margin-left: 0; /* Menghapus margin saat layar kecil */
            padding: 20px;
        }

        body {
            flex-direction: column;
        }
    }

    @media (max-width: 768px) {
        h1 {
            font-size: 1.5em;
        }

        th, td {
            padding: 10px;
            font-size: 0.9em;
        }
        
        .role-checkboxes {
            flex-direction: column; /* Centang peran menjadi tumpukan vertikal */
        }
    }
    /* Untuk layar medium seperti iPad (antara 768px dan 1180px) */
@media (max-width: 1180px) and (min-width: 768px) {
    .sidebar {
        position: fixed;
        width: 220px;
        height: 100%;
        z-index: 10;
        left: 0;
        top: 0;
    }

    .main-content {
        margin-left: 220px; /* beri ruang agar tidak ketimpa sidebar */
    }
}
.sidebar.hidden {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.main-content {
    transition: margin-left 0.3s ease;
}


</style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container">
            <h1>Kelola Akses Pegawai</h1>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th>Akses Menu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!empty($all_pegawai)): 
                                $no = 1; // Inisialisasi nomor
                                foreach ($all_pegawai as $pegawai): 
                            ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($pegawai['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($pegawai['jabatan']); ?></td>
                                        <td>
                                            <div class="role-checkboxes">
                                                <?php 
                                                     // Dapatkan peran saat ini sebagai array
                                                     $current_roles = $current_roles_by_pegawai[$pegawai['id']] ?? [];
                                                ?>
                                                <?php foreach ($available_roles as $role): ?>
                                                     <div class="role-checkbox">
                                                         <input type="checkbox"
                                                                id="role-<?php echo $pegawai['id'] . '-' . htmlspecialchars($role); ?>"
                                                                name="roles[<?php echo $pegawai['id']; ?>][]"
                                                                value="<?php echo htmlspecialchars($role); ?>"
                                                                <?php echo in_array($role, $current_roles) ? 'checked' : ''; ?>>
                                                         <label for="role-<?php echo $pegawai['id'] . '-' . htmlspecialchars($role); ?>">
                                                             <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                                                         </label>
                                                     </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="4">Tidak ada data pegawai yang aktif.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-action">
                    <button type="submit" class="btn-update">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>