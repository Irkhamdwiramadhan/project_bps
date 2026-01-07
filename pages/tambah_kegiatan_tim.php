<?php
session_start();
include '../includes/koneksi.php';

// AJAX Handler untuk mengambil anggota (Sama seperti sebelumnya, tapi kita butuh strukturnya di JS)
if (isset($_GET['action']) && $_GET['action'] == 'get_anggota') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { http_response_code(403); exit; }
    
    $tim_id = (int)$_GET['tim_id'];
    $anggota_list = [];
    $sql_anggota = "(SELECT at.member_id as id, p.nama AS nama_lengkap, 'pegawai' as type 
                     FROM anggota_tim AS at JOIN pegawai AS p ON at.member_id = p.id 
                     WHERE at.member_type = 'pegawai' AND at.tim_id = ?) 
                    UNION ALL 
                    (SELECT at.member_id as id, m.nama_lengkap, 'mitra' as type 
                     FROM anggota_tim AS at JOIN mitra AS m ON at.member_id = m.id 
                     WHERE at.member_type = 'mitra' AND at.tim_id = ?) 
                    ORDER BY nama_lengkap ASC";
    
    $stmt = $koneksi->prepare($sql_anggota);
    $stmt->bind_param("ii", $tim_id, $tim_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) { while ($row = $result->fetch_assoc()) { $anggota_list[] = $row; } }
    echo json_encode($anggota_list);
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';

// Cek Login & Akses (Sama)
$user_roles = $_SESSION['user_role'] ?? []; 
$allowed_roles = ['super_admin', 'ketua_tim']; 
$has_access = false; 
foreach ($user_roles as $role) { if (in_array($role, $allowed_roles)) { $has_access = true; break; } }
if (!$has_access) { header('Location: kegiatan_tim.php'); exit; }

// Ambil data Tim
$tim_list = [];
$result_tim = $koneksi->query("SELECT id, nama_tim FROM tim ORDER BY nama_tim ASC");
if ($result_tim) { while($row = $result_tim->fetch_assoc()) { $tim_list[] = $row; } }
?>

<style>
    /* Style Dasar */
    :root { --primary: #4A90E2; --bg-body: #f4f7f9; --border: #e2e8f0; }
    body { font-family: 'Poppins', sans-serif; background: var(--bg-body); }
    
    .main-container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
    .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; overflow: hidden; }
    .card-header { background: white; padding: 20px 30px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .card-body { padding: 30px; }

    /* Sticky Team Selector */
    .sticky-team-select {
        position: sticky; top: 0; z-index: 100; background: #fff;
        padding: 20px; border-bottom: 2px solid var(--primary);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: -30px -30px 20px -30px;
    }

    /* Activity Card Styling */
    .activity-card {
        background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;
        padding: 20px; margin-bottom: 20px; position: relative;
        transition: all 0.3s;
    }
    .activity-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: var(--primary); }
    
    .remove-activity-btn {
        position: absolute; top: 15px; right: 15px;
        background: #fee2e2; color: #ef4444; border: none;
        width: 32px; height: 32px; border-radius: 50%;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .remove-activity-btn:hover { background: #ef4444; color: white; }

    /* Grid Layout for Form */
    .form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px; align-items: start; margin-bottom: 15px; }
    
    /* Member List inside Activity */
    .member-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px; background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border);
    }
    .member-item { display: flex; flex-direction: column; font-size: 0.85rem; }
    .member-item label { margin-bottom: 4px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .form-control, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; }
    .btn { padding: 10px 20px; border-radius: 6px; cursor: pointer; border: none; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-success { background: #10b981; color: white; }
    .btn-secondary { background: #64748b; color: white; }

    @media (max-width: 992px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<datalist id="opsi-satuan">
    <option value="Dokumen">
    <option value="Responden">
    <option value="Layanan">
    <option value="Paket Kegiatan">
    <option value="Buah">
    <option value="Konten">
    <option value="Orang/Bulan">
    <option value="Laporan">
</datalist>

<div class="main-container">
    <div class="card">
        <div class="card-header">
            <h2 style="margin:0;"><i class="bi bi-layers"></i> Input Kegiatan Massal</h2>
            <a href="kegiatan_tim.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        </div>

        <div class="card-body">
            <form action="../proses/proses_tambah_kegiatan_tim.php" method="POST" id="bulkForm">
                
                <div class="sticky-team-select">
                    <label style="font-weight:bold; display:block; margin-bottom:5px;">Langkah 1: Pilih Tim Penanggung Jawab</label>
                    <select class="form-select" id="tim_id" name="tim_id" required style="font-size: 1.1rem; padding: 10px;">
                        <option value="" selected>-- Pilih Tim --</option>
                        <?php foreach ($tim_list as $tim): ?>
                            <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="loading-indicator" style="display:none; text-align:center; padding:20px;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p>Memuat anggota tim...</p>
                </div>

                <div id="activities-container" style="display:none;">
                    <p class="text-muted mb-4"><i class="bi bi-info-circle"></i> Anda bisa menambahkan banyak kegiatan sekaligus untuk tim yang dipilih.</p>
                    
                    <div id="activities-list"></div>

                    <div style="margin-top: 20px; display:flex; gap:10px;">
                        <button type="button" id="add-more-btn" class="btn btn-success">
                            <i class="bi bi-plus-lg"></i> Tambah Baris Kegiatan Lain
                        </button>
                        <button type="submit" class="btn btn-primary" style="margin-left:auto;">
                            <i class="bi bi-save"></i> Simpan Semua Kegiatan
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const timSelect = document.getElementById('tim_id');
    const loading = document.getElementById('loading-indicator');
    const container = document.getElementById('activities-container');
    const list = document.getElementById('activities-list');
    const addBtn = document.getElementById('add-more-btn');
    
    let currentMembers = []; // Menyimpan data anggota tim yang sedang dipilih
    let activityCount = 0;   // Index unik untuk name attribute

    // Fungsi membuat Kartu Kegiatan Baru
    function createActivityCard(index) {
        const card = document.createElement('div');
        card.className = 'activity-card';
        card.id = `activity-${index}`;

        // Generate input target untuk setiap anggota
        let memberInputsHTML = '';
        if (currentMembers.length > 0) {
            memberInputsHTML = '<label style="font-weight:600; display:block; margin-top:10px; margin-bottom:5px;">Target Per Anggota:</label><div class="member-grid">';
            
            // Helper tombol "Bagi Rata" atau "Set Semua"
            memberInputsHTML += `
                <div style="grid-column: 1 / -1; margin-bottom:5px; font-size:0.8rem; color:#666;">
                   Isi target individu di bawah. Total target akan dihitung otomatis.
                </div>
            `;

            currentMembers.forEach(m => {
                memberInputsHTML += `
                    <div class="member-item">
                        <label title="${m.nama_lengkap}">${m.nama_lengkap}</label>
                        <input type="number" step="0.01" class="form-control member-target-input" 
                               name="kegiatan[${index}][targets][${m.id}]" 
                               data-activity-index="${index}"
                               placeholder="0" value="0">
                         <input type="hidden" name="kegiatan[${index}][types][${m.id}]" value="${m.type}">
                    </div>
                `;
            });
            memberInputsHTML += '</div>';
        } else {
            memberInputsHTML = '<div class="alert alert-warning">Tidak ada anggota di tim ini.</div>';
        }

        // HTML Struktur Kartu
        card.innerHTML = `
            <button type="button" class="remove-activity-btn" onclick="removeActivity(${index})" title="Hapus baris ini"><i class="bi bi-x-lg"></i></button>
            <h5 style="margin-top:0; color:var(--primary);">Kegiatan #${index + 1}</h5>
            
            <div class="form-grid">
                <div>
                    <label class="form-label">Nama Kegiatan</label>
                    <input type="text" class="form-control" name="kegiatan[${index}][nama]" placeholder="Nama Kegiatan" required>
                </div>
                <div>
                    <label class="form-label">Satuan</label>
                    <input list="opsi-satuan" class="form-control" name="kegiatan[${index}][satuan]" placeholder="Pilih/Ketik..." required>
                </div>
                <div>
                    <label class="form-label">Batas Waktu</label>
                    <input type="date" class="form-control" name="kegiatan[${index}][batas_waktu]" required>
                </div>
                 <div>
                    <label class="form-label">Total Target</label>
                    <input type="number" class="form-control total-target-display" id="total-${index}" value="0" readonly style="background:#e2e8f0;">
                </div>
            </div>

            <div>
                <label class="form-label">Keterangan (Opsional)</label>
                <input type="text" class="form-control" name="kegiatan[${index}][keterangan]" placeholder="Catatan...">
            </div>

            ${memberInputsHTML}
        `;

        return card;
    }

    // Event Listener Ganti Tim
    timSelect.addEventListener('change', function() {
        const timId = this.value;
        list.innerHTML = ''; // Reset list kegiatan
        container.style.display = 'none';
        
        if(!timId) return;

        loading.style.display = 'block';

        // Fetch Anggota
        fetch(`?action=get_anggota&tim_id=${timId}`)
            .then(res => res.json())
            .then(data => {
                loading.style.display = 'none';
                currentMembers = data;
                container.style.display = 'block';
                activityCount = 0;
                
                // Tambahkan 1 kartu default
                addBtn.click();
            })
            .catch(err => {
                loading.style.display = 'none';
                alert('Gagal memuat anggota tim.');
            });
    });

    // Event Tambah Kartu
    addBtn.addEventListener('click', function() {
        const card = createActivityCard(activityCount++);
        list.appendChild(card);
    });

    // Event Hapus Kartu (Global Function)
    window.removeActivity = function(index) {
        const item = document.getElementById(`activity-${index}`);
        if(item) item.remove();
        // Cek jika kosong, tambah satu lagi biar ga kosong melompong
        if(list.children.length === 0) addBtn.click();
    };

    // Event Hitung Total Target (Delegation)
    list.addEventListener('input', function(e) {
        if(e.target.classList.contains('member-target-input')) {
            const index = e.target.dataset.activityIndex;
            const card = document.getElementById(`activity-${index}`);
            const inputs = card.querySelectorAll('.member-target-input');
            let total = 0;
            
            inputs.forEach(inp => total += parseFloat(inp.value || 0));
            
            document.getElementById(`total-${index}`).value = total.toFixed(2);
        }
    });
});
</script>