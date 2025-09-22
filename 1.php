<?php
session_start();
include '../includes/koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = trim($_POST['role']);
    
    if ($role === 'admin') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $sql = "SELECT id, username, password, role FROM admin_users WHERE username = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_nama'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                header("Location: ../pages/dashboard.php");
                exit();
            }
        }
    } elseif ($role === 'pegawai') {
        $nip_bps = trim($_POST['nip_bps']);
        $nama_pangkat = trim($_POST['nama_pangkat']);
        
        // PERBAIKAN: Ambil ID, nama, nip_bps
        $sql = "SELECT id, nip_bps, nama FROM pegawai WHERE nip_bps = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("s", $nip_bps);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $pegawai = $result->fetch_assoc();
            
            if ($pegawai['nama'] === $nama_pangkat) {
                
                // PERBAIKAN: Ambil peran dari tabel 'menu_akses'
                $sql_role = "SELECT peran_menu FROM menu_akses WHERE pegawai_id = ?";
                $stmt_role = $koneksi->prepare($sql_role);
                $stmt_role->bind_param("i", $pegawai['id']);
                $stmt_role->execute();
                $result_role = $stmt_role->get_result();
                
                $user_role = 'pegawai'; // Default role
                if ($result_role->num_rows > 0) {
                    $role_data = $result_role->fetch_assoc();
                    $user_role = $role_data['peran_menu'];
                }
                
                // Simpan data ke sesi
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $pegawai['id'];
                $_SESSION['user_nama'] = $pegawai['nama'];
                $_SESSION['user_role'] = $user_role; // Menggunakan peran dari database
                
                header("Location: ../pages/dashboard.php");
                exit();
            }
        }
    }
    
    // Alihkan kembali ke halaman login jika peran tidak valid
    $_SESSION['error_message'] = "Nama atau NIP salah.";
    header("Location: ../login.php");
    exit();

} else {
    header("Location: ../login.php");
    exit();
}