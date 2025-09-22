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

if ($role_tampil === 'admin') {
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
    <title>Dashboard BPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .main-content {
            background-color: #f3f4f6;
        }
        /* Style untuk kartu yang lebih elegan */
        .elegant-card {
            border: 1px solid #e5e7eb;
            background: linear-gradient(145deg, #ffffff, #f9fafb);
        }
        /* Style baru untuk bagian kontak & media sosial */
        .contact-card, .social-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .contact-card:hover, .social-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        .contact-card .icon, .social-card .icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 24px;
        }
        .social-card .icon {
            font-size: 28px;
        }
        .icon-map { background-color: #e0f2fe; color: #3b82f6; } /* Tailwind blue-100 & blue-500 */
        .icon-phone { background-color: #e0f7f2; color: #059669; } /* Tailwind emerald-100 & emerald-600 */
        .icon-email { background-color: #fef3e0; color: #ea580c; } /* Tailwind orange-100 & orange-600 */
        .icon-whatsapp { background-color: #dcfce7; color: #22c55e; } /* Tailwind green-100 & green-500 */
        .icon-website { background-color: #e0f2fe; color: #3b82f6; }
        .icon-instagram { background-color: #fef2f2; color: #ec4899; } /* Tailwind pink-100 & pink-500 */
        .icon-youtube { background-color: #fee2e2; color: #ef4444; } /* Tailwind red-100 & red-500 */
        .icon-facebook { background-color: #eff6ff; color: #2563eb; } /* Tailwind blue-50 & blue-600 */
        .icon-tiktok { background-color: #f3f4f6; color: #1f2937; } /* Tailwind gray-100 & gray-800 */
        .icon-twitter { background-color: #e5f4fa; color: #38a3e0; } /* Tailwind light-blue & blue */
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content p-8 md:p-12 transition-all duration-300">
            <div class="header-content flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-800"><?php echo $salam; ?>, <?php echo htmlspecialchars($nama_tampil); ?>!</h2>
                <p class="text-gray-600 mt-2 md:mt-0 text-sm md:text-base">Badan Pusat Statistik Kabupaten Tegal</p>
            </div>

            <div class="card elegant-card p-6 rounded-lg shadow-md flex flex-col lg:flex-row gap-8">
                
                <div class="w-full lg:w-1/2 flex-shrink-0 rounded-lg overflow-hidden">
                    <img src="../assets/img/logo/profil.jpeg" alt="Kantor BPS Kabupaten Tegal" class="w-full h-auto object-cover rounded-lg shadow-md">
                </div>
                
                <div class="w-full lg:w-1/2 flex flex-col">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Tentang BPS Kabupaten Tegal</h3>
                        <p class="text-gray-600 text-justify mb-4">
                            Badan Pusat Statistik (BPS) Kabupaten Tegal adalah lembaga pemerintah non-kementerian yang bertanggung jawab menyediakan data statistik dasar untuk membantu pemerintah dalam perencanaan dan evaluasi pembangunan. BPS berkomitmen untuk menghasilkan data yang akurat, mutakhir, dan relevan.
                        </p>
                      <div class="contact-card p-6 flex items-start space-x-4">
                        <div class="icon-map icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h5 class="font-semibold text-gray-800">Alamat</h5>
                            <p class="text-gray-600 text-sm">Jl. Ade Irma Suryani No.1, Slawi, Tegal</p>
                        </div>
                    </div>
                        

                        <div class="w-full h-auto mb-8 rounded-lg shadow-md overflow-hidden">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d5110.019010034412!2d109.12797947604594!3d-6.9932661684917266!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e6fbefcd5cf446d%3A0x149c7e96decb5ca8!2sBPS%20Kabupaten%20Tegal!5e1!3m2!1sid!2sid!4v1757475232800!5m2!1sid!2sid"
                                width="100%" 
                                height="300" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>

                    </div>
                
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full mt-4">
                        <div class="card card-statistic bg-white p-6 rounded-lg shadow-md text-center">
                            <div class="flex flex-col items-center">
                                <svg class="h-12 w-12 text-blue-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h-5v-5a2 2 0 012-2h2a2 2 0 012 2v5zM4 15h3a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2a2 2 0 012-2zM9 20v-5a2 2 0 012-2h2a2 2 0 012 2v5M4 11h3a2 2 0 012-2V7a2 2 0 01-2-2H4a2 2 0 01-2 2v4a2 2 0 012 2zM9 11v-4a2 2 0 012-2h2a2 2 0 012 2v4M17 11h3a2 2 0 012-2v-2a2 2 0 01-2-2h-3a2 2 0 01-2 2v2a2 2 0 012 2z"></path></svg>
                                <div>
                                    <h5 class="text-xl font-semibold text-gray-700">Total Pegawai</h5>
                                    <p class="count-number text-5xl font-bold text-blue-600 mt-2"><?php echo $data_total['total_pegawai']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="card card-statistic bg-white p-6 rounded-lg shadow-md text-center">
                            <div class="flex flex-col items-center">
                                <svg class="h-12 w-12 text-green-500 mb-4" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                                <div>
                                    <h5 class="text-xl font-semibold text-gray-700">Pegawai Aktif</h5>
                                    <p class="count-number text-5xl font-bold text-green-600 mt-2"><?php echo $data_active['total_active']; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="card card-statistic bg-white p-6 rounded-lg shadow-md text-center">
                            <div class="flex flex-col items-center">
                                <svg class="h-12 w-12 text-red-500 mb-4" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM5.293 6.707a1 1 0 011.414-1.414L10 8.586l3.293-3.293a1 1 0 011.414 1.414L11.414 10l3.293 3.293a1 1 0 01-1.414 1.414L10 11.414l-3.293 3.293a1 1 0 01-1.414-1.414L8.586 10 5.293 6.707z" clip-rule="evenodd"></path></svg>
                                <div>
                                    <h5 class="text-xl font-semibold text-gray-700">Pegawai Nonaktif</h5>
                                    <p class="count-number text-5xl font-bold text-red-600 mt-2"><?php echo $data_inactive['total_inactive']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="header-content flex justify-between items-center mb-4 mt-8">
                <h3 class="text-2xl font-bold text-gray-800">Kontak Kami</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
              
                
                <div class="contact-card p-6 flex items-start space-x-4">
                    <div class="icon-phone icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Telp & Faks</h5>
                        <p class="text-gray-600 text-sm">(0283) 4561190</p>
                    </div>
                </div>
                
                <div class="contact-card p-6 flex items-start space-x-4">
                    <div class="icon-email icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Email</h5>
                        <p class="text-gray-600 text-sm">bps3328@bps.go.id</p>
                    </div>
                </div>
                
             
                
                <div class="contact-card p-6 flex items-start space-x-4">
                    <div class="icon-whatsapp icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Whatsapp Layanan (Operator)</h5>
                        <p class="text-gray-600 text-sm">082138887474</p>
                    </div>
                </div>
            </div>

            <div class="header-content flex justify-between items-center mb-4 mt-8">
                <h3 class="text-2xl font-bold text-gray-800">Media Sosial</h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <a href="https://tegalkab.bps.go.id/id" target="_blank" class="social-card p-4 flex items-center space-x-4">
                    <div class="icon-website icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Website Resmi</h5>
                        <p class="text-gray-600 text-sm">tegal.bps.go.id</p>
                    </div>
                </a>
                
                <a href="https://www.instagram.com/bps_kabtegal" target="_blank" class="social-card p-4 flex items-center space-x-4">
                    <div class="icon-instagram icon">
                        <i class="fab fa-instagram"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Instagram</h5>
                        <p class="text-gray-600 text-sm">@bps_kabtegal</p>
                    </div>
                </a>

                <a href="https://www.youtube.com/channel/UC...Tegal" target="_blank" class="social-card p-4 flex items-center space-x-4">
                    <div class="icon-youtube icon">
                        <i class="fab fa-youtube"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">YouTube</h5>
                        <p class="text-gray-600 text-sm">Bps Kab Tegal</p>
                    </div>
                </a>

                <a href="https://www.facebook.com/bpstegal" target="_blank" class="social-card p-4 flex items-center space-x-4">
                    <div class="icon-facebook icon">
                        <i class="fab fa-facebook"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Facebook</h5>
                        <p class="text-gray-600 text-sm">Bps Kab Tegal</p>
                    </div>
                </a>
                
                <a href="https://www.tiktok.com/@bpskabtegal" target="_blank" class="social-card p-4 flex items-center space-x-4">
                    <div class="icon-tiktok icon">
                        <i class="fab fa-tiktok"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">TikTok</h5>
                        <p class="text-gray-600 text-sm">@bpskabtegal</p>
                    </div>
                </a>

                <a href="https://twitter.com/bpskabtegal" target="_blank" class="social-card p-4 flex items-center space-x-4">
                    <div class="icon-twitter icon">
                        <i class="fab fa-twitter"></i>
                    </div>
                    <div>
                        <h5 class="font-semibold text-gray-800">Twitter / X</h5>
                        <p class="text-gray-600 text-sm">@bpskabtegal</p>
                    </div>
                </a>
            </div>
        </main>
    </div>
    <script>
        // JavaScript untuk sidebar (diambil dari file sidebar.php Anda)
        const collapseToggle = document.getElementById('collapse-toggle');
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.getElementById('menu-toggle');
        const backdrop = document.getElementById('backdrop');

        collapseToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });

        // Function to handle mobile sidebar
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