<?php
session_start();
include '../includes/koneksi.php';

// =========================================================================
// VALIDASI AKSES
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Akses tidak diizinkan.");
}

$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles_for_action = ['super_admin', 'admin_dipaku'];
$has_access = !empty(array_intersect($user_roles, $allowed_roles_for_action));

if (!$has_access) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../manajemen_anggaran.php");
    exit();
}

if (!isset($_POST['tahun']) || !filter_var($_POST['tahun'], FILTER_VALIDATE_INT)) {
    $_SESSION['flash_message'] = "Tahun anggaran tidak valid.";
    $_SESSION['flash_message_type'] = "danger";
    header("Location: ../manajemen_anggaran.php");
    exit();
}
$tahun_anggaran = (int)$_POST['tahun'];

// =========================================================================
// PENGHAPUSAN DATA
// =========================================================================

// urutkan dari tabel paling bawah → ke atas (anak → induk)
$tables_to_delete = [
    'rpd_sakti',            // <- DITAMBAHKAN (harus paling awal!)
    'master_item',
    'master_akun',
    'master_sub_komponen',
    'master_komponen',
    'master_sub_output',
    'master_output',
    'master_kegiatan',
    'master_program'
];

$koneksi->begin_transaction();

try {
    foreach ($tables_to_delete as $table) {
        $query = "DELETE FROM {$table} WHERE tahun = ?";
        $stmt = $koneksi->prepare($query);

        if ($stmt === false) {
            throw new Exception("Gagal prepare query tabel {$table}: " . $koneksi->error);
        }

        $stmt->bind_param("i", $tahun_anggaran);
        $stmt->execute();

        if ($stmt->error) {
            throw new Exception("Error hapus tabel {$table}: " . $stmt->error);
        }

        $stmt->close();
    }

    $koneksi->commit();
    $_SESSION['flash_message'] = "Data anggaran & RPD tahun {$tahun_anggaran} berhasil dihapus.";
    $_SESSION['flash_message_type'] = "success";

} catch (Exception $e) {
    $koneksi->rollback();
    $_SESSION['flash_message'] = "Gagal menghapus data. Pesan: " . $e->getMessage();
    $_SESSION['flash_message_type'] = "danger";
}

header("Location: ../pages/master_data.php?tahun=" . $tahun_anggaran);
exit();
?>
