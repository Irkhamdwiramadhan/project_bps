<?php
// Pastikan sesi sudah dimulai di awal file
session_start();

// Panggil file koneksi database
include 'includes/koneksi.php';

// Inisialisasi pesan error
$error_message = '';

// Proses saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_type = $_POST['role'] ?? '';

    // Logika untuk login Admin/Super Admin
    if ($role_type === 'admin') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $sql = "SELECT id, username, password, role FROM admin_users WHERE username = ?";
        $stmt = $koneksi->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if ($password === $user['password']) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nama'] = $user['username'];
                    
                    // PERHATIKAN: Peran admin/super_admin disimpan sebagai array
                    // Ini untuk mengatasi kesalahan 'in_array()' di halaman lain.
                    $_SESSION['user_role'] = [$user['role']]; 
                    
                    $_SESSION['user_foto'] = null; 

                    // Arahkan ke dashboard admin
                    header('Location: pages/dashboard.php');
                    exit();
                }
            }
        }
        $error_message = "Username atau password salah.";
    } 
    
    // Logika untuk login Pegawai
    elseif ($role_type === 'pegawai') {
        $nama_pangkat = trim($_POST['nama_pangkat']);
        $nip_bps = trim($_POST['nip_bps']);

        $sql_pegawai = "SELECT id, nama, nip_bps, foto FROM pegawai WHERE nama = ? AND nip_bps = ?";
        $stmt_pegawai = $koneksi->prepare($sql_pegawai);
        
        if ($stmt_pegawai) {
            $stmt_pegawai->bind_param("ss", $nama_pangkat, $nip_bps);
            $stmt_pegawai->execute();
            $result_pegawai = $stmt_pegawai->get_result();
            
            if ($result_pegawai->num_rows === 1) {
                $pegawai = $result_pegawai->fetch_assoc();
                
                // Ambil semua peran dari tabel pegawai_roles
                $sql_roles = "SELECT r.nama FROM pegawai_roles pr JOIN roles r ON pr.role_id = r.id WHERE pr.pegawai_id = ?";
                $stmt_roles = $koneksi->prepare($sql_roles);
                $stmt_roles->bind_param("i", $pegawai['id']);
                $stmt_roles->execute();
                $result_roles = $stmt_roles->get_result();
                
                $user_roles = [];
                while ($row_role = $result_roles->fetch_assoc()) {
                    $user_roles[] = $row_role['nama'];
                }
                
                // Jika tidak ada peran admin, tambahkan peran 'pegawai' sebagai default
                if (empty($user_roles)) {
                    $user_roles[] = 'pegawai';
                }

                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $pegawai['id'];
                $_SESSION['user_nama'] = $pegawai['nama'];
                $_SESSION['user_role'] = $user_roles; // Simpan semua peran sebagai array
                $_SESSION['user_foto'] = $pegawai['foto'];
                
                // Arahkan ke dashboard pegawai
                header('Location: pages/dashboard.php');
                exit();
            }
        }
        $error_message = "Nama atau NIP BPS tidak cocok atau tidak terdaftar.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login BPS Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-image: url('assets/img/logo/logo_backgorund_login.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h2 {
            margin-bottom: 25px;
            color: #333;
        }
        .logo {
            width: 100px;
            height: 100px;
        }
        .input-group {
            position: relative;
            margin-bottom: 15px;
        }
        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 12px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background: #fff;
        }
        .input-group select {
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3E%3C/svg%3E") no-repeat right 12px center;
            background-size: 12px;
        }
        .input-group input:focus, .input-group select:focus {
            border-color: #007bff;
            outline: none;
        }
        .input-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 15px;
        }
        .btn-login:hover {
            background-color: #0056b3;
        }
        .error {
            color: #e74c3c;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="assets/img/logo/logo-login.jpg" alt="Logo BPS" class="logo">
        <h3>Login</h3>
        <h4>Sistem Informasi Statistik Kabupaten Tegal</h4>
        <?php if ($error_message): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="input-group">
                <i class="fas fa-users icon"></i>
                <select id="roleSelect" name="role" onchange="toggleInputs()">
                    <option value="admin">Admin</option>
                    <option value="pegawai" selected>Pegawai</option>
                </select>
            </div>

            <div class="input-group" id="usernameAdminGroup">
                <i class="fas fa-user icon"></i>
                <input type="text" name="username" placeholder="Username Admin">
            </div>
            
            <div class="input-group" id="passwordGroup">
                <i class="fas fa-lock icon"></i>
                <input type="password" name="password" placeholder="Password">
            </div>

            <div class="input-group" id="namaPangkatGroup">
                <i class="fas fa-user-tag icon"></i>
                <input type="text" name="nama_pangkat" placeholder="Nama Lengkap dan Pangkat (e.g., John Doe III/c)">
            </div>

            <div class="input-group" id="nipBpsGroup">
                <i class="fas fa-id-card icon"></i>
                <input type="text" name="nip_bps" placeholder="NIP BPS">
            </div>

            <button type="submit" class="btn-login">Masuk</button>
        </form>
    </div>

    <script>
        function toggleInputs() {
            const role = document.getElementById('roleSelect').value;
            const usernameAdminGroup = document.getElementById('usernameAdminGroup');
            const passwordGroup = document.getElementById('passwordGroup');
            const namaPangkatGroup = document.getElementById('namaPangkatGroup');
            const nipBpsGroup = document.getElementById('nipBpsGroup');
            
            const usernameInput = usernameAdminGroup.querySelector('input');
            const passwordInput = passwordGroup.querySelector('input');
            const namaPangkatInput = namaPangkatGroup.querySelector('input');
            const nipBpsInput = nipBpsGroup.querySelector('input');

            if (role === 'admin') {
                usernameAdminGroup.style.display = 'block';
                passwordGroup.style.display = 'block';
                namaPangkatGroup.style.display = 'none';
                nipBpsGroup.style.display = 'none';

                usernameInput.setAttribute('required', '');
                passwordInput.setAttribute('required', '');
                namaPangkatInput.removeAttribute('required');
                nipBpsInput.removeAttribute('required');
            } else { // 'pegawai'
                usernameAdminGroup.style.display = 'none';
                passwordGroup.style.display = 'none';
                namaPangkatInput.style.display = 'block';
                nipBpsInput.style.display = 'block';

                usernameInput.removeAttribute('required');
                passwordInput.removeAttribute('required');
                namaPangkatInput.setAttribute('required', '');
                nipBpsInput.setAttribute('required', '');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleInputs();
        });
    </script>
</body>
</html>
