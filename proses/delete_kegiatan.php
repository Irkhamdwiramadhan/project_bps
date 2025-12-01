<?php
session_start();
include '../includes/koneksi.php';

// Cek hak akses dasar (Sesuaikan dengan kebutuhan Anda)
$user_roles = $_SESSION['user_role'] ?? [];
$allowed_roles = ['super_admin', 'ketua_tim'];
$is_allowed = false;
foreach ($user_roles as $role) {
    if (in_array($role, $allowed_roles)) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    echo "<script>alert('Akses Ditolak!'); window.history.back();</script>";
    exit;
}

// 1. Tangkap Data (Bisa dari POST array atau GET single)
$ids_to_delete = [];

if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    // Jika dari Checkbox (Bulk Delete)
    $ids_to_delete = $_POST['ids'];
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    // Jika dari Tombol Hapus Satuan
    $ids_to_delete = [$_GET['id']];
}

// Ambil URL Redirect (Jika ada dikirim dari form, prioritas utama)
$redirect_url = $_POST['redirect_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '../pages/rekap_kegiatan_tim.php');

// Validasi jika kosong
if (empty($ids_to_delete)) {
    header('Location: ' . $redirect_url . (strpos($redirect_url, '?') ? '&' : '?') . 'status=error&message=' . urlencode('Tidak ada data yang dipilih.'));
    exit;
}

try {
    // 2. Mulai Transaksi
    $koneksi->begin_transaction();

    // Persiapan variabel untuk query dinamis (WHERE IN (?,?,?))
    $count = count($ids_to_delete);
    $placeholders = implode(',', array_fill(0, $count, '?'));
    $types = str_repeat('i', $count);

    // -----------------------------------------------------------------
    // 3. Ambil ID Parent (mitra_survey_id) sebelum menghapus anaknya
    //    Kita perlu array mitra_survey_id untuk dihapus juga
    // -----------------------------------------------------------------
    $sql_get_parents = "SELECT mitra_survey_id FROM honor_mitra WHERE id IN ($placeholders)";
    $stmt_get = $koneksi->prepare($sql_get_parents);
    $stmt_get->bind_param($types, ...$ids_to_delete);
    $stmt_get->execute();
    $result_parents = $stmt_get->get_result();
    
    $parent_ids = [];
    while ($row = $result_parents->fetch_assoc()) {
        if (!empty($row['mitra_survey_id'])) {
            $parent_ids[] = $row['mitra_survey_id'];
        }
    }
    $stmt_get->close();

    if (empty($parent_ids) && empty($ids_to_delete)) {
        throw new Exception("Data tidak ditemukan.");
    }

    // -----------------------------------------------------------------
    // 4. Hapus Data Honor (Anak)
    // -----------------------------------------------------------------
    $sql_del_honor = "DELETE FROM honor_mitra WHERE id IN ($placeholders)";
    $stmt_del_honor = $koneksi->prepare($sql_del_honor);
    $stmt_del_honor->bind_param($types, ...$ids_to_delete);
    
    if (!$stmt_del_honor->execute()) {
        throw new Exception("Gagal menghapus data honor.");
    }
    $stmt_del_honor->close();

    // -----------------------------------------------------------------
    // 5. Hapus Data Survey (Induk)
    //    Hanya jika ada parent ID yang ditemukan
    // -----------------------------------------------------------------
    if (!empty($parent_ids)) {
        // Karena parent_ids mungkin ada duplikat (satu survey punya banyak honor?), kita unikkan
        $parent_ids = array_unique($parent_ids);
        
        $count_p = count($parent_ids);
        $placeholders_p = implode(',', array_fill(0, $count_p, '?'));
        $types_p = str_repeat('i', $count_p);

        $sql_del_survey = "DELETE FROM mitra_surveys WHERE id IN ($placeholders_p)";
        $stmt_del_survey = $koneksi->prepare($sql_del_survey);
        $stmt_del_survey->bind_param($types_p, ...$parent_ids);
        
        if (!$stmt_del_survey->execute()) {
            throw new Exception("Gagal menghapus data survey.");
        }
        $stmt_del_survey->close();
    }

    // 6. Commit Transaksi
    $koneksi->commit();

    // Bersihkan URL redirect dari parameter status lama agar tidak menumpuk
    $redirect_url = strtok($redirect_url, '?');
    
    // Tambahkan query string yang ada sebelumnya (misal: tim_id, bulan, tahun)
    $query_string = parse_url($_POST['redirect_url'] ?? $_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    if ($query_string) {
        $redirect_url .= '?' . $query_string;
    }

    // Tambahkan status success
    $separator = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    header("Location: " . $redirect_url . $separator . "status=success&message=" . urlencode("$count data berhasil dihapus."));
    exit;

} catch (Exception $e) {
    // Rollback jika error
    $koneksi->rollback();

    // Handling Redirect Error
    $redirect_url = $_POST['redirect_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '../pages/rekap_kegiatan_tim.php');
    $redirect_url = strtok($redirect_url, '?'); // Bersih-bersih URL
    
    header("Location: " . $redirect_url . "?status=error&message=" . urlencode($e->getMessage()));
    exit;
} finally {
    if (isset($koneksi)) {
        $koneksi->close();
    }
}
?>