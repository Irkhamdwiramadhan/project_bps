<?php
session_start();
include '../includes/koneksi.php';

// =================== START: BLOK LOGIKA API ===================
// Cek apakah ini adalah permintaan data dari JavaScript (AJAX/Fetch)
if (isset($_GET['type']) && isset($_GET['tahun'])) {
    
    // Set header agar browser tahu ini adalah response JSON
    header('Content-Type: application/json');

    $type = $_GET['type'];
    $tahun = $_GET['tahun'];
    $parentId = $_GET['parent_id'] ?? null;
    
    $data = [];
    $sql = "";
    $stmt = null;

    // Siapkan query berdasarkan 'type' yang diminta
    switch ($type) {
        case 'program':
            $sql = "SELECT id, kode, nama FROM master_program WHERE tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("s", $tahun);
            break;
        case 'kegiatan':
            $sql = "SELECT id, kode, nama FROM master_kegiatan WHERE program_id = ? AND tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("is", $parentId, $tahun);
            break;
        case 'output':
            $sql = "SELECT id, kode, nama FROM master_output WHERE kegiatan_id = ? AND tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("is", $parentId, $tahun);
            break;
        case 'sub_output':
            $sql = "SELECT id, kode, nama FROM master_sub_output WHERE output_id = ? AND tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("is", $parentId, $tahun);
            break;
        case 'komponen':
            $sql = "SELECT id, kode, nama FROM master_komponen WHERE sub_output_id = ? AND tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("is", $parentId, $tahun);
            break;
        case 'sub_komponen':
            $sql = "SELECT id, kode, nama FROM master_sub_komponen WHERE komponen_id = ? AND tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("is", $parentId, $tahun);
            break;
        case 'akun':
            $sql = "SELECT id, kode, nama FROM master_akun WHERE sub_komponen_id = ? AND tahun = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("is", $parentId, $tahun);
            break;
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
    
    // Kembalikan data dalam format JSON dan hentikan eksekusi skrip
    echo json_encode($data);
    exit; // Wajib ada!
}
// =================== END: BLOK LOGIKA API ===================


// --- JIKA BUKAN REQUEST API, LANJUTKAN RENDER HALAMAN HTML SEPERTI BIASA ---

include '../includes/header.php';
include '../includes/sidebar.php';

// Cek hak akses
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'admin_dipaku', 'admin_tu', 'pegawai'];
if (empty(array_intersect($user_roles, $allowed_roles))) {
    die("Akses ditolak.");
}

// Ambil tahun unik
$tahun_result = $koneksi->query("SELECT DISTINCT tahun FROM master_program ORDER BY tahun DESC");
$daftar_tahun = [];
while ($row = $tahun_result->fetch_assoc()) {
    $daftar_tahun[] = $row['tahun'];
}
if (empty($daftar_tahun)) {
    $daftar_tahun[] = date('Y');
}
?>

<main class="main-content">
<div class="container-fluid py-5">
    <div class="card shadow-lg border-0 rounded-3">
        </div>
</div>
</main>