<?php
// Pastikan sesi dimulai di setiap halaman yang membutuhkan data sesi
session_start();

// Memastikan koneksi dan komponen utama terpasang.
include '../includes/koneksi.php';

// Mengambil total pegawai dari database
$query_total = "SELECT COUNT(*) AS total_pegawai FROM pegawai";
$result_total = $koneksi->query($query_total);
$data_total = $result_total->fetch_assoc();

// Mengambil total pegawai aktif
$query_active = "SELECT COUNT(*) AS total_active FROM pegawai WHERE is_active = 1";
$result_active = $koneksi->query($query_active);
$data_active = $result_active->fetch_assoc();

// Mengambil total pegawai nonaktif
$query_inactive = "SELECT COUNT(*) AS total_inactive FROM pegawai WHERE is_active = 0";
$result_inactive = $koneksi->query($query_inactive);
$data_inactive = $result_inactive->fetch_assoc();

// Menentukan salam sapaan berdasarkan waktu
date_default_timezone_set('Asia/Jakarta');
$jam = date('H');
$salam = "Selamat Malam";
if ($jam >= 5 && $jam < 12) {
    $salam = "Selamat Pagi";
} elseif ($jam >= 12 && $jam < 17) {
    $salam = "Selamat Siang";
} elseif ($jam >= 17 && $jam < 20) {
    $salam = "Selamat Sore";
}

// Mengambil nama pengguna dari sesi untuk ditampilkan di header
$nama_tampil = '';
$role_tampil = $_SESSION['user_role'] ?? 'Pengguna';

if (is_array($role_tampil) && in_array('admin', $role_tampil)) {
    $nama_tampil = $_SESSION['user_username'] ?? 'Admin';
} else { // Role 'pegawai'
    $nama_tampil = $_SESSION['user_nama'] ?? 'Pegawai';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sitik BPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS Kustom untuk Tampilan Modern */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f3f4f6;
        }

        /* --- Animasi --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* --- Kartu Statistik --- */
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e5e7eb;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
        }
        .stat-card .icon-bg {
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 80px;
            opacity: 0.08;
            transform: rotate(-15deg);
        }
        .stat-card.blue { border-left: 5px solid #3b82f6; }
        .stat-card.green { border-left: 5px solid #22c55e; }
        .stat-card.red { border-left: 5px solid #ef4444; }

        /* --- Kartu Konten Utama --- */
        .main-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        }
        
        /* --- Daftar Fitur --- */
        .feature-icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 20px;
        }
    </style>
</head>
<body class="antialiased">
    <div class="dashboard-wrapper">
        
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content p-6 md:p-10 transition-all duration-300">
            <div class="header-content mb-8 animate-fade-in-up">
                <h2 class="text-3xl font-bold text-gray-800"><?php echo $salam; ?>, <?php echo htmlspecialchars($nama_tampil); ?>!</h2>
                <p class="text-gray-600 mt-1">Selamat datang di dasbor Sitik BPS Kabupaten Tegal.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="stat-card blue p-6 flex items-center animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="flex-grow">
                        <p class="text-gray-500 font-medium">Total Pegawai</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $data_total['total_pegawai']; ?></p>
                    </div>
                    <i class="fas fa-users text-blue-500 icon-bg"></i>
                </div>
                <div class="stat-card green p-6 flex items-center animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="flex-grow">
                        <p class="text-gray-500 font-medium">Pegawai Aktif</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $data_active['total_active']; ?></p>
                    </div>
                    <i class="fas fa-user-check text-green-500 icon-bg"></i>
                </div>
                <div class="stat-card red p-6 flex items-center animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="flex-grow">
                        <p class="text-gray-500 font-medium">Pegawai Nonaktif</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $data_inactive['total_inactive']; ?></p>
                    </div>
                     <i class="fas fa-user-slash text-red-500 icon-bg"></i>
                </div>
            </div>

            <div class="main-card p-6 md:p-8 animate-fade-in-up" style="animation-delay: 0.4s;">
                 <div class="flex flex-col lg:flex-row gap-8">
                    <div class="w-full lg:w-1/3 flex-shrink-0">
                        <img src="../assets/img/logo/logo7.png" alt="Kantor BPS Kabupaten Tegal" class="w-full h-full object-cover rounded-lg shadow-md">
                    </div>
                    <div class="w-full lg:w-2/3">
                        <h3 class="text-2xl font-bold text-gray-800">Sitik BPS: Setetes Kemanfaatan dalam Genggaman</h3>
                        <p class="text-gray-600 text-justify mt-2 mb-6">
                            Sitik BPS, sebuah aplikasi sederhana namun kaya manfaat, hadir sebagai wujud kolaborasi apik antara BPS Kabupaten Tegal dan talenta muda dari Sekolah Tinggi Teknologi Terpadu Nurul Fikri. Layaknya tetesan air yang menyegarkan, Sitik BPS dirancang untuk memberikan kemudahan dan efisiensi dalam berbagai aspek pekerjaan pegawai.
                        </p>
                        
                        <div class="space-y-5">
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-blue-100 text-blue-600"><i class="fas fa-calendar-check"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">SIAP (Sistem Informasi Apel Pagi)</p>
                                    <p class="text-sm text-gray-500">Memudahkan pengelolaan dan pelaporan kegiatan apel pagi.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-orange-100 text-orange-600"><i class="fas fa-door-open"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">Sapa (Satpam Siaga)</p>
                                    <p class="text-sm text-gray-500">
                                        Aplikasi untuk merekam pergerakan keluar masuk pegawai maupun tamu di lingkungan BPS.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                               <div class="feature-icon bg-indigo-100 text-indigo-600"><i class="fas fa-wallet"></i></div>
                               <div>
                                   <p class="font-semibold text-gray-800">Dipaku (Dipa Aku)</p>
                                   <p class="text-sm text-gray-500">Aplikasi keuangan untuk memantau RPD dan realisasinya.</p>
                               </div>
                           </div>
                             <div class="flex items-start gap-4">
                                <div class="feature-icon bg-green-100 text-green-600"><i class="fas fa-shopping-cart"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">BS-Mart (Bina Sejati Mart)</p>
                                    <p class="text-sm text-gray-500">Aplikasi belanja khusus pegawai dengan penawaran menarik.</p>
                                </div>
                            </div>
                             <div class="flex items-start gap-4">
                                <div class="feature-icon bg-yellow-100 text-yellow-600"><i class="fas fa-star"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">Prestasi (Pegawai Teladan Berdedikasi)</p>
                                    <p class="text-sm text-gray-500">Platform untuk mengapresiasi dan memilih pegawai teladan yang berdedikasi tinggi dan  CAN : Wadah untuk berkolaborasi dan berbagi ide dalam Change Agent Network.</p>
                                </div>
                            </div>
                             
                             <div class="flex items-start gap-4">
                                <div class="feature-icon bg-red-100 text-red-600"><i class="fas fa-users-cog"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">PMS (Penilaian Mitra Statistik)</p>
                                    <p class="text-sm text-gray-500">Memfasilitasi penilaian kinerja mitra statistik secara objektif.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-100 text-gray-600"><i class="fas fa-tasks"></i></div>
                                <div>
                                    <p class="font-semibold text-gray-800">Small Simpedu (Sistem Informasi Pekerjaan Terpadu)</p>
                                    <p class="text-sm text-gray-500">Menampilkan target dan realisasi kinerja tim secara terpadu.</p>
                                </div>
                            </div>

                        </div>

                        <p class="text-gray-600 text-justify mt-6 pt-4 border-t border-gray-200">
                           Sitik BPS bukan sekadar aplikasi, melainkan representasi semangat kolaborasi dan inovasi untuk meningkatkan kualitas pelayanan dan kinerja BPS Kabupaten Tegal.
                        </p>
                    </div>
                 </div>
            </div>

        </main>
    </div>
    <script>
        // JavaScript untuk sidebar (tidak berubah)
        const collapseToggle = document.getElementById('collapse-toggle');
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.getElementById('menu-toggle');
        const backdrop = document.getElementById('backdrop');

        collapseToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });
        function openSidebarMobile(open) {
            if (open) {
                sidebar.classList.add('active');
                document.body.classList.add('sidebar-open');
                backdrop.hidden = false;
                menuBtn.setAttribute('aria-expanded','true');
            } else {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-open');
                backdrop.hidden = true;
                menuBtn.setAttribute('aria-expanded','false');
            }
        }
        menuBtn.addEventListener('click', () => {
            openSidebarMobile(!sidebar.classList.contains('active'));
        });
        backdrop.addEventListener('click', () => openSidebarMobile(false));
        window.addEventListener('keydown', (e) => { if (e.key === 'Escape') openSidebarMobile(false); });
        const MQ = 992;
        window.addEventListener('resize', () => {
            if (window.innerWidth > MQ) openSidebarMobile(false);
        });
        const path = location.pathname.split('/').pop();
        document.querySelectorAll('.sidebar-nav a.nav-item').forEach(a => {
            const href = a.getAttribute('href');
            if (href && href === path) a.classList.add('active');
        });
    </script>
</body>
</html>
<?php
$koneksi->close();
?>