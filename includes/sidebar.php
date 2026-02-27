<?php
// Mendapatkan nama file halaman saat ini tanpa ekstensi .php untuk Clean URL
$current_page = basename($_SERVER['PHP_SELF'], ".php");

// Definisi Style Class (Menggunakan style dari Anda)
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm ring-1 ring-indigo-200 dark:ring-transparent font-semibold";
$inactive_link_style = "text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";

// Fungsi Helper untuk Icon Active (Menambahkan class ph-fill jika aktif)
function getIconClass($page_name, $current_page, $icon_name) {
    // Penanganan khusus jika menu memiliki sub-halaman (seperti sim-list dan sim-detail)
    if (is_array($page_name)) {
        $fill = in_array($current_page, $page_name) ? 'ph-fill' : 'ph-bold';
    } else {
        $fill = ($current_page == $page_name) ? 'ph-fill' : 'ph-bold';
    }
    return "ph {$fill} {$icon_name} text-xl shrink-0";
}

// Pastikan fungsi hasAccess tersedia (fallback jika config.php belum diupdate user)
if (!function_exists('hasAccess')) {
    function hasAccess($page_key) {
        global $conn;
        if (!isset($_SESSION['role'])) return false;
        $role = $_SESSION['role'];
        if ($role == 'superadmin') return true; // Superadmin default allow all
        
        $stmt = $conn->prepare("SELECT id FROM role_permissions WHERE role = ? AND page_key = ?");
        $stmt->bind_param("ss", $role, $page_key);
        $stmt->execute();
        return ($stmt->get_result()->num_rows > 0);
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<aside id="sidebar" style="font-family: 'Inter', sans-serif;" class="group fixed left-0 top-0 z-[100] flex h-screen w-[280px] [&.is-collapsed]:w-[88px] flex-col overflow-y-hidden bg-white dark:bg-[#24303F] transition-all duration-300 ease-in-out lg:static lg:translate-x-0 -translate-x-full border-r border-slate-100 dark:border-slate-800 shrink-0 shadow-2xl lg:shadow-none font-sans">
    
    <div class="flex items-center justify-between lg:justify-start gap-3 px-6 group-[.is-collapsed]:px-0 group-[.is-collapsed]:justify-center pt-8 pb-6 lg:pt-10 lg:pb-8 transition-all duration-300 shrink-0">
        <a href="dashboard" class="flex items-center gap-3 overflow-hidden">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/20 transition-transform hover:scale-105">
                <i class="ph-bold ph-lightning text-2xl"></i>
            </div>
            <span class="text-xl font-black text-slate-800 dark:text-white tracking-tight group-[.is-collapsed]:opacity-0 group-[.is-collapsed]:hidden whitespace-nowrap transition-all duration-300">
                IoT Platform
            </span>
        </a>
        
        <button id="closeSidebarMobile" class="block lg:hidden text-slate-400 hover:text-red-500 transition-colors ml-auto p-1">
            <i class="ph-bold ph-x text-2xl"></i>
        </button>
    </div>

    <div class="flex flex-col overflow-y-auto no-scrollbar flex-1 py-4 group-[.is-collapsed]:items-center transition-all">
        <nav class="mt-2 w-full px-4 lg:px-6 group-[.is-collapsed]:px-3">
            
            <div class="mb-6">
                <h3 class="mb-3 ml-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest group-[.is-collapsed]:hidden">Menu</h3>
                
                <div class="hidden group-[.is-collapsed]:flex justify-center mb-4">
                     <i class="ph-fill ph-dots-three-outline text-xl text-slate-300 dark:text-slate-600"></i>
                </div>
                
                <ul class="flex flex-col gap-2">
                    <?php if(hasAccess('dashboard')): ?>
                    <li>
                        <a href="dashboard" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'dashboard') ? $active_link_style : $inactive_link_style; ?>" title="Dashboard">
                            <i class="<?php echo getIconClass('dashboard', $current_page, 'ph-squares-four'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('sim_list')): ?>
                    <li>
                        <a href="sim-list" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'sim-list' || $current_page == 'sim-detail') ? $active_link_style : $inactive_link_style; ?>" title="SIM Monitor">
                            <i class="<?php echo getIconClass(['sim-list', 'sim-detail'], $current_page, 'ph-sim-card'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM Monitor</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('sync_data.php')): ?>
                    <li>
                        <a href="sync-data" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'sync-data') ? $active_link_style : $inactive_link_style; ?>" title="Sync Data">
                            <i class="<?php echo getIconClass('sync-data', $current_page, 'ph-arrows-clockwise'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Sync Data</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="mt-8">
                <h3 class="mb-3 ml-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest group-[.is-collapsed]:hidden">Administration</h3>
                
                <div class="hidden group-[.is-collapsed]:flex justify-center mb-4 mt-2 pt-4 border-t border-slate-100 dark:border-slate-700">
                     <i class="ph-fill ph-dots-three-outline text-xl text-slate-300 dark:text-slate-600"></i>
                </div>

                <ul class="flex flex-col gap-2">
                    <?php if(hasAccess('manage_client')): ?>
                    <li>
                        <a href="manage-client" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'manage-client') ? $active_link_style : $inactive_link_style; ?>" title="Manage Client">
                            <i class="<?php echo getIconClass('manage-client', $current_page, 'ph-buildings'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Client</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('manage_users')): ?>
                    <li>
                        <a href="manage-users" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'manage-users') ? $active_link_style : $inactive_link_style; ?>" title="Manage Users">
                            <i class="<?php echo getIconClass('manage-users', $current_page, 'ph-users-three'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Users</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('manage_role')): ?>
                    <li>
                        <a href="manage-role" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'manage-role') ? $active_link_style : $inactive_link_style; ?>" title="Manage Roles">
                            <i class="<?php echo getIconClass('manage-role', $current_page, 'ph-shield-check'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Roles</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('manage_role')): ?> 
                    <li>
                        <a href="manage-smtp" class="relative flex items-center w-full group-[.is-collapsed]:w-12 group-[.is-collapsed]:h-12 gap-3 rounded-xl px-4 py-3 group-[.is-collapsed]:px-0 group-[.is-collapsed]:py-0 transition-all group-[.is-collapsed]:justify-center mx-auto <?php echo ($current_page == 'manage-smtp') ? $active_link_style : $inactive_link_style; ?>" title="SMTP Settings">
                            <i class="<?php echo getIconClass('manage-smtp', $current_page, 'ph-envelope-simple'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SMTP Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
        </nav>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileCloseBtn = document.getElementById('closeSidebarMobile');
        
        // 1. Menutup sidebar menggunakan tombol "X" di mode Mobile
        if (mobileCloseBtn) {
            mobileCloseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.add('-translate-x-full');
            });
        }

        // 2. Global Event Listener untuk tombol Burger di Header
        document.addEventListener('click', function(e) {
            // Deteksi jika yang diklik adalah area atau ikon Burger Menu
            const burgerBtn = e.target.closest('.ph-list, .ph-list-bold, .ph-list-dash, .ph-text-align-justify, #sidebarToggle, .burger-btn');
            
            if (burgerBtn) {
                e.preventDefault();
                e.stopPropagation(); 
                
                if (window.innerWidth < 1024) {
                    // Jika di Mobile/Tablet: Lakukan Slide In/Out
                    sidebar.classList.toggle('-translate-x-full');
                } else {
                    // Jika di Desktop: Kecilkan (Collapse) Sidebar
                    sidebar.classList.toggle('is-collapsed');
                }
            } else {
                // Auto-close sidebar jika pengguna klik di luar area sidebar pada tampilan Mobile
                if (window.innerWidth < 1024 && sidebar && !sidebar.contains(e.target) && !sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        // 3. Mereset state Sidebar ketika ukuran layar ditarik/berubah (Resize)
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.remove('is-collapsed');
                if(!sidebar.classList.contains('-translate-x-full')) {
                     sidebar.classList.add('-translate-x-full');
                }
            }
        });
    });
</script>