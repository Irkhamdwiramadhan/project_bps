<?php
include '../includes/koneksi.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$tahun = $_GET['tahun'] ?? null;

// Fungsi untuk escape parameter agar aman
function escape($koneksi, $val) {
    return $koneksi->real_escape_string($val);
}

switch ($type) {
    case 'program':
        $tahun_safe = escape($koneksi, $tahun);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_program WHERE tahun='$tahun_safe' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'kegiatan':
        $program_id = escape($koneksi, $_GET['program_id'] ?? 0);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_kegiatan WHERE program_id='$program_id' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'output':
        $kegiatan_id = escape($koneksi, $_GET['kegiatan_id'] ?? 0);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_output WHERE kegiatan_id='$kegiatan_id' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'sub_output':
        $output_id = escape($koneksi, $_GET['output_id'] ?? 0);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_sub_output WHERE output_id='$output_id' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'komponen':
        $sub_output_id = escape($koneksi, $_GET['sub_output_id'] ?? 0);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_komponen WHERE sub_output_id='$sub_output_id' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'sub_komponen':
        $komponen_id = escape($koneksi, $_GET['komponen_id'] ?? 0);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_sub_komponen WHERE komponen_id='$komponen_id' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'akun':
        $sub_komponen_id = escape($koneksi, $_GET['sub_komponen_id'] ?? 0);
        $q = $koneksi->query("SELECT id, kode, nama FROM master_akun WHERE sub_komponen_id='$sub_komponen_id' ORDER BY kode");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    case 'item':
        $akun_id = escape($koneksi, $_GET['akun_id'] ?? 0);
        $q = $koneksi->query("SELECT kode_unik, nama_item, satuan, harga FROM master_item WHERE akun_id='$akun_id' ORDER BY nama_item");
        echo json_encode($q->fetch_all(MYSQLI_ASSOC));
        break;

    default:
        echo json_encode([]);
}