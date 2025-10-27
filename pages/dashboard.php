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
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .stat-card.blue {
            border-left: 5px solid #3b82f6;
        }

        .stat-card.green {
            border-left: 5px solid #22c55e;
        }

        .stat-card.red {
            border-left: 5px solid #ef4444;
        }

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
                <p class="text-gray-600 mt-1">Selamat datang di dasboard Sitik BPS Kabupaten Tegal.</p>
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
                        <p class="text-gray-600 text-justify mt-2 mb-6 text-sm text-bold">
                            Sitik BPS, sebuah aplikasi yang lahir dari kolaborasi apik antara BPS Kabupaten Tegal dan talenta muda Sekolah Tinggi Teknologi Terpadu Nurul Fikri, hadir sebagai solusi cerdas untuk meningkatkan efisiensi dan efektivitas kerja para pegawai. Layaknya tetesan air yang menyegarkan dan menghidupi, Sitik BPS dirancang untuk mempermudah berbagai aspek pekerjaan, dari pengelolaan kinerja hingga pemantauan keuangan.

                        </p>

                        <div class="space-y-5">
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/simpedu.jpg" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">Small Simpedu (Sistem Informasi Pekerjaan Terpadu)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Memfasilitasi penentuan ketua tim dengan mudah.</li>
                                        <li>Menampilkan target dan realisasi kinerja tim secara terpadu setiap bulan.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/dipaku.png" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">Dipaku (Dipa Aku)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Aplikasi keuangan untuk memantau Rencana Penarikan Dana (RPD) setiap bulan.</li>
                                        <li>Memudahkan pemantauan realisasi anggaran, memastikan penggunaan dana yang efektif.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/prestasi.png" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">Prestasi (Pegawai Teladan Berdedikasi)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Aplikasi untuk mengapresiasi dan memilih pegawai teladan setiap triwulan.</li>
                                        <li>Memfasilitasi pemilihan Change Agent Network (CAN) secara akuntabel.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/bs_mart.png" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">BS-Mart (Bina Sejati Mart)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Aplikasi belanja khusus untuk pegawai BPS Kabupaten Tegal.</li>
                                        <li>Menyediakan rekap transaksi dan belanja per pegawai, memudahkan pengelolaan keuangan koperasi termasuk stok barang yang ada.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/siap.png" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">SIAP (Sistem Informasi Apel Pagi)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Mencatat kehadiran peserta apel pagi secara digital.</li>
                                        <li>Menyediakan bahan evaluasi kedisiplinan pegawai berdasarkan data kehadiran apel.</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/pms.png" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">PMS (Penilaian Mitra Statistik)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Memfasilitasi penilaian kinerja mitra statistik secara objektif dan terukur.</li>
                                        <li>Memungkinkan pemilihan kegiatan permitra, menentukan besaran honor yang diterima secara adil.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="feature-icon bg-gray-200">
                                    <img src="../assets/img/logo/sapa.png" alt="Deskripsi Foto" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">Sapa (Satpam Siaga)</p>
                                    <ul class="list-disc list-inside text-sm text-gray-500 mt-1">
                                        <li>Merekam pergerakan keluar masuk pegawai/tamu di lingkungan BPS.</li>
                                        <li>Memudahkan pegawai dalam mengajukan izin keluar kantor.</li>
                                    </ul>
                                </div>
                            </div>

                        </div>


                    </div>

                </div>
                <p class="text-gray-600 text-justify mt-6 pt-4 border-t border-gray-200 text-center">
                    Sitik BPS bukan sekadar kumpulan aplikasi, melainkan representasi semangat kolaborasi, inovasi, dan komitmen untuk meningkatkan kualitas pelayanan dan kinerja BPS Kabupaten Tegal. Dengan Sitik BPS, setiap tetes informasi menjadi kekuatan untuk mencapai tujuan bersama.
                </p>
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
                menuBtn.setAttribute('aria-expanded', 'true');
            } else {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-open');
                backdrop.hidden = true;
                menuBtn.setAttribute('aria-expanded', 'false');
            }
        }
        menuBtn.addEventListener('click', () => {
            openSidebarMobile(!sidebar.classList.contains('active'));
        });
        backdrop.addEventListener('click', () => openSidebarMobile(false));
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') openSidebarMobile(false);
        });
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