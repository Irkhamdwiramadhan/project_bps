<?php
// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}
// Ambil info pengguna dari sesi
$nama_tampil = $_SESSION['user_nama'] ?? 'Admin';
$role_tampil = $_SESSION['user_role'] ?? ['pegawai']; // Ambil sebagai array
$foto_user = $_SESSION['user_foto'] ?? null;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- REVISI FINAL: TAMPILAN LEBIH KEREN & CANTIK --- */
        :root {
            --sidebar-width: 240px; /* Sedikit lebih lebar untuk ruang */
            /* EFEK KACA (GLASSMORPHISM) */
            --sidebar-bg: rgba(1, 17, 32, 0.85);
            --sidebar-blur: 1px;

            --body-bg: #f0f2f5; /* Latar belakang konten utama */
            --sidebar-text: #dde4ebff;
            --sidebar-text-hover: #ffffff;
            --accent-color: #00aaff; /* Biru elektrik yang lebih cerah */
            --glow-color: rgba(0, 170, 255, 0.2);
            --border-color: rgba(255, 255, 255, 0.1);
            --logout-color: #ff4d4d;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--body-bg);
            /* Latar belakang dengan sedikit gradien untuk nuansa */
            background-image: radial-gradient(circle at top left, #ffffff, #eef2f7);
        }

        /* Container utama sidebar dengan efek kaca */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            backdrop-filter: blur(var(--sidebar-blur));
            -webkit-backdrop-filter: blur(var(--sidebar-blur));
            border-right: 1px solid var(--border-color);
            color: var(--sidebar-text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
        }

        /* --- STYLING HEADER (LOGO & BRAND) --- */
        @keyframes logo-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        @keyframes text-gradient-pan {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .sidebar-top {
            padding: 20px 25px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        .sidebar-logo a { text-decoration: none; }
.sidebar-logo img {
    width: 70px;
    height: auto;
    
    /* TAMBAHKAN DUA BARIS INI */
    display: block;
    margin: 0 auto 10px auto; /* 0 atas, auto kanan-kiri, 10px bawah */

    animation: logo-float 6s infinite ease-in-out;
    transition: transform 0.3s ease;
}
        .sidebar-logo:hover img {
            transform: scale(1.1);
        }
        .sidebar-logo .brand {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(90deg, #ffffff, var(--accent-color), #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 250% auto;
            animation: text-gradient-pan 8s linear infinite;
        }
        .sidebar-logo .tagline {
            display: block;
            font-size: 0.8rem;
            color: var(--sidebar-text);
            margin-top: 5px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        /* --- STYLING NAVIGASI MENU DENGAN ANIMASI --- */
        @keyframes menu-item-fade-in {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .sidebar-nav { flex-grow: 1; padding: 15px 0; overflow-y: auto; }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { 
            margin: 0 15px; 
            /* ANIMASI SAAT MEMUAT */
            animation: menu-item-fade-in 0.5s ease-out forwards;
            opacity: 0; /* Mulai dari transparan */
        }
        
        /* Memberi jeda animasi untuk setiap item */
        <?php for ($i=1; $i<=10; $i++): ?>
        .sidebar-nav li:nth-child(<?php echo $i; ?>) { animation-delay: <?php echo $i * 0.07; ?>s; }
        <?php endfor; ?>

        .nav-item {
            display: flex; align-items: center; gap: 15px; padding: 12px 15px;
            text-decoration: none; color: var(--sidebar-text);
            border-radius: 8px; margin-bottom: 5px;
            transition: all 0.25s ease; position: relative;
            font-weight: 500; font-size: 0.95rem;
        }
        .nav-item:hover, .nav-item.active {
            color: var(--sidebar-text-hover);
            background: var(--glow-color);
            /* EFEK GLOW DENGAN BOX-SHADOW */
            box-shadow: 0 0 15px var(--glow-color);
        }
        .nav-item i.menu-icon { font-size: 1rem; width: 20px; text-align: center; transition: transform 0.3s ease; }
        .nav-item:hover i.menu-icon { transform: scale(1.2); }

        .nav-item.active::before { /* Indikator aktif tetap sama, sudah bagus */ }

        /* Sub-Menu */
        .has-sub summary { list-style: none; cursor: pointer; }
        .has-sub summary::-webkit-details-marker { display: none; }
        .has-sub .caret { margin-left: auto; transition: transform 0.2s ease; }
        .has-sub details[open] > summary .caret { transform: rotate(90deg); }
        .sub-menu {
            list-style: none; padding: 5px 0 5px 28px; margin-top: 5px;
            border-left: 1px dashed var(--border-color);
        }
        .sub-menu a {
            display: block; padding: 9px 10px; font-size: 0.88rem;
            color: var(--sidebar-text); text-decoration: none;
            border-radius: 6px; transition: all 0.2s ease;
        }
        .sub-menu a:hover, .sub-menu a.active {
            color: var(--sidebar-text-hover);
            background: rgba(255,255,255,0.05);
        }
        
        /* Footer Sidebar */
        .sidebar-footer { /* Styling footer tetap sama, sudah kompak */ }

        /* Custom Scrollbar */
        .sidebar-nav::-webkit-scrollbar { width: 5px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background-color: var(--accent-color); border-radius: 10px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        
        /* Styling lain yang sudah ada sebelumnya... */
        .sidebar-footer { padding: 15px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
        a.user-profile { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-grow: 1; overflow: hidden; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid var(--border-color); flex-shrink: 0; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-avatar .fa-user-circle { font-size: 40px; color: #ffffffff; }
        .user-info .user-name { color: #fff; font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
        .user-info .user-role { color: var(--sidebar-text); font-size: 0.75rem; margin: 0; }
        .logout-btn { color: var(--sidebar-text); font-size: 1.2rem; text-decoration: none; padding: 5px; border-radius: 4px; transition: all 0.2s ease; }
        .logout-btn:hover { color: var(--logout-color); background-color: rgba(255,255,255,0.1); }
        @media (max-width: 768px) { /* Kode responsive Anda... */ }
        .sidebar-toggle-btn, .sidebar-close-btn {
    display: none; /* Sembunyikan di desktop */
    position: fixed;
    z-index: 1001;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(5px);
    color: #1e2a38;
    border: none;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease, background-color 0.3s ease;
}
.sidebar-toggle-btn:hover, .sidebar-close-btn:hover {
    transform: scale(1.1);
    background-color: #fff;
}

.sidebar-toggle-btn {
    top: 15px;
    left: 15px;
}
.sidebar-close-btn {
    /* Tombol close di dalam sidebar */
    position: absolute;
    top: 15px;
    right: -50px; /* Sembunyikan di desktop */
    opacity: 0;
    transition: all 0.4s ease;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

/* Aturan untuk layar kecil (mobile) */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar-toggle-btn {
        display: block; /* Tampilkan tombol hamburger */
    }
    .sidebar-close-btn {
        display: block; /* Tampilkan tombol close */
    }
    
    /* Saat sidebar terbuka di mobile */
    body.sidebar-open .sidebar {
        transform: translateX(0);
        box-shadow: 5px 0 25px rgba(0,0,0,0.3);
    }
    body.sidebar-open .sidebar-overlay {
        display: block;
        opacity: 1;
    }
    body.sidebar-open .sidebar-close-btn {
        right: 15px; /* Pindahkan tombol close ke dalam sidebar */
        opacity: 1;
    }
}
    </style>
</head>
<body>
      <button id="sidebarToggle" class="sidebar-toggle-btn"><i class="fas fa-bars"></i></button>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar" aria-label="Sidebar navigation">
        <div class="sidebar-top">
            <div class="sidebar-logo">
                 <a href="dashboard.php">
                     <img src="../assets/img/logo/Sitik (6).png" alt="Logo BPS">
                     <span class="brand">Sitik BPS</span>
                     <span class="tagline">Secuil Aplikasi Pegawai Statistik</span>
                 </a>
            </div>
        </div>
        
        <nav class="sidebar-nav" role="navigation">
            <ul>
                <li><a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt menu-icon"></i><span class="nav-text">Dashboard</span></a></li>
                
                <?php if (is_array($role_tampil) && in_array('super_admin', $role_tampil)): ?>
                    <li><a href="kelola_akses.php" class="nav-item"><i class="fas fa-user-shield menu-icon"></i><span class="nav-text">Kelola Akses</span></a></li>
                <?php endif; ?>
                
                <li><a href="pegawai.php" class="nav-item"><i class="fas fa-users menu-icon"></i><span class="nav-text">Data Pegawai</span></a></li>
                <li><a href="apel.php" class="nav-item"><i class="fas fa-calendar-check menu-icon"></i><span class="nav-text">SIAP</span></a></li>
                
                <li class="has-sub">
                    <details>
                        <summary class="nav-item">
                            <i class="fas fa-wallet menu-icon"></i>
                            <span class="nav-text">Dipaku</span>
                            <i class="fas fa-chevron-right caret"></i>
                        </summary>
                        <ul class="sub-menu">
                            <li><a href="master_data.php"><i class="fas fa-book"></i> Anggaran</a></li>
                            <li><a href="rpd.php"><i class="fas fa-calendar-alt"></i> Rpd</a></li>
                            <li><a href="realisasi.php"><i class="fas fa-chart-line"></i> Realisasi</a></li>
                            <?php
                            $allowed_roles = ['super_admin', 'admin_dipaku']; 

                            if (is_array($role_tampil) && array_intersect($allowed_roles, $role_tampil)) {
                                echo '<li><a href="upload.php"><i class="fas fa-upload"></i> Upload</a></li>';
                            }
                            if (is_array($role_tampil) && array_intersect($allowed_roles, $role_tampil)) {
                                echo '<li><a href="cetak.php"><i class="fas fa-print"></i> Cetak</a></li>';
                            }
                            ?>

                            
                            
                          
                        
                        </ul>
                    </details>
                </li>
                <li class="has-sub">
                    <details>
                        <summary class="nav-item">
                            <i class="fas fa-store menu-icon"></i>
                            <span class="nav-text">B-S Mart</span>
                            <i class="fas fa-chevron-right caret"></i>
                        </summary>
                        <ul class="sub-menu">
                            <li><a href="tambah_penjualan.php"><i class="fas fa-plus-circle"></i> Tambah Penjualan</a></li>
                            <li><a href="barang_tersedia.php"><i class="fas fa-box-open"></i> Stok Barang</a></li>
                            <li><a href="history_penjualan.php"><i class="fas fa-history"></i> History Penjualan</a></li>
                            <li><a href="rekap_transaksi.php"><i class="fas fa-receipt"></i> Rekap Transaksi</a></li>
                        </ul>
                    </details>
                </li>
                <li class="has-sub">
                    <details class="prestasi-menu">
                        <summary class="nav-item">
                            <i class="fas fa-trophy"></i>
                            <span class="nav-text">Prestasi</span>
                            <i class="fas fa-chevron-right caret"></i>
                        </summary>
                        <ul class="sub-menu">
                            <li><a href="calon_berprestasi.php"><i class="fas fa-user-plus"></i> Daftar Calon</a></li>
                            <li><a href="form_penilaian.php"><i class="fas fa-clipboard-check"></i> Form Penilaian</a></li>
                            <li><a href="hasil_penilaian.php"><i class="fas fa-chart-line"></i> Hasil Penilaian</a></li>
                        </ul>
                    </details>
                </li>
                <li class="has-sub">
                    <details class="pms-menu">
                        <summary class="nav-item">
                            <i class="fas fa-clipboard-list"></i>
                            <span class="nav-text">PMS</span>
                            <i class="fas fa-chevron-right caret"></i>
                        </summary>
                        <ul class="sub-menu">
                            <li><a href="mitra.php"><i class="fas fa-users-cog"></i> Mitra</a></li>
                            <?php 
                            // Logika baru yang lebih solid untuk menampilkan menu "Kelola Akses"
                            // Menu ini akan muncul jika user memiliki peran 'super_admin' atau 'admin_pegawai'
                            if (is_array($role_tampil) && (in_array('super_admin', $role_tampil) )) {
                                echo '<li><a href="manage_batas_honor.php"><i class="fas fa-chart-line"></i>Batas Honor</a></li>';
                            }
                            ?>
                            <li><a href="kegiatan.php"><i class="fas fa-tasks"></i> Kegiatan</a></li>
                            <li><a href="jenis_surveys.php"><i class="fas fa-poll"></i> Jenis Survey</a></li>
                            <li><a href="penilaian_mitra.php"><i class="fas fa-star-half-alt"></i> Penilaian Mitra</a></li>
                            <li><a href="rekap_honor.php"><i class="fas fa-receipt"></i> Rekap Honor</a></li>
                            
                        </ul>
                    </details>
                </li>
    
                <li class="has-sub">
                    <details>
                        <summary class="nav-item">
                            <i class="fas fa-briefcase menu-icon"></i>
                            <span class="nav-text">Small Simpedu</span>
                            <i class="fas fa-chevron-right caret"></i>
                        </summary>
                        <ul class="sub-menu">
                            <li><a href="halaman_tim.php"><i class="fas fa-users"></i> Tim</a></li>
                            <li><a href="kegiatan_tim.php"><i class="fas fa-clipboard-list"></i> Kegiatan</a></li>
                        </ul>
                    </details>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <a href="#" class="user-profile"> 
                <div class="user-avatar">
                     <?php if (!empty($foto_user) && !in_array('super_admin', $role_tampil) && !in_array('admin', $role_tampil)):
                        $foto_path = '../assets/img/pegawai/' . htmlspecialchars($foto_user);
                        echo '<img src="' . $foto_path . '" alt="Foto Pengguna">';
                    else:
                        echo '<i class="fas fa-user-circle"></i>';
                    endif; ?>
                </div>
                <div class="user-info">
                    <?php
                        if (is_array($role_tampil)) {
                            $display_roles_formatted = array_map(fn($role) => ucfirst(str_replace('_', ' ', $role)), $role_tampil);
                            $display_role = implode(' & ', $display_roles_formatted);
                        } else {
                            $display_role = ucfirst(str_replace('_', ' ', $role_tampil));
                        }
                    ?>
                    <p class="user-name"><?php echo htmlspecialchars($nama_tampil); ?></p>
                    <p class="user-role"><?php echo $display_role; ?></p>
                </div>
            </a>
            <a href="../logout.php" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- FUNGSI UNTUK MENANDAI MENU AKTIF SECARA OTOMATIS ---
        const currentLocation = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.sidebar-nav a');

        navLinks.forEach(link => {
            const linkPath = link.getAttribute('href').split('/').pop();
            if (linkPath === currentLocation) {
                link.classList.add('active');
                
                // Jika link aktif ada di dalam sub-menu, buka parent <details>
                const parentDetails = link.closest('details');
                if (parentDetails) {
                    parentDetails.setAttribute('open', '');
                    // Tandai juga summary-nya sebagai aktif
                    parentDetails.querySelector('summary.nav-item').classList.add('active');
                }
            }
        });

        // --- KODE UNTUK TOGGLE SIDEBAR MOBILE ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    // Fungsi untuk membuka sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            body.classList.add('sidebar-open');
        });
    }

    // Fungsi untuk menutup sidebar
    function closeSidebar() {
        body.classList.remove('sidebar-open');
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    });
    </script>
</body>
</html>