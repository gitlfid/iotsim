<?php
// Mendapatkan nama file halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);

// Definisi Style Class
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm ring-1 ring-indigo-200 dark:ring-transparent font-semibold";
$inactive_link_style = "text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";

// Fungsi Helper untuk Icon Active (Menambahkan class ph-fill jika aktif)
function getIconClass($page_name, $current_page, $icon_name) {
    $fill = ($current_page == $page_name) ? 'ph-fill' : '';
    return "ph {$fill} {$icon_name} text-xl";
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

<aside id="sidebar" style="font-family: 'Inter', sans-serif;" class="group fixed left-0 top-0 z-50 flex h-screen w-[280px] flex-col overflow-y-hidden bg-white dark:bg-[#24303F] duration-300 ease-in-out lg:static lg:translate-x-0 -translate-x-full border-r border-slate-100 dark:border-slate-800">
    
    <div class="flex items-center justify-between gap-2 px-6 pt-10 pb-6 lg:pt-12 lg:pb-8">
        <a href="dashboard.php" class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="ph ph-lightning text-2xl"></i>
            </div>
            <span class="text-xl font-bold text-slate-800 dark:text-white opacity-100 duration-300 group-[.is-collapsed]:opacity-0">
                IoT Platform
            </span>
        </a>
        <button id="sidebar-toggle" class="block lg:hidden text-slate-500 hover:text-indigo-600">
            <i class="ph ph-arrow-left text-2xl"></i>
        </button>
    </div>

    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
        <nav class="mt-2 px-4 lg:mt-4 lg:px-6 pb-10">
            
            <div>
                <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">
                    MENU
                </h3>
                <ul class="flex flex-col gap-2">
                    
                    <?php if(hasAccess('dashboard')): ?>
                    <li>
                        <a href="dashboard" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'dashboard') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('dashboard', $current_page, 'ph-squares-four'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('sim_list')): ?>
                    <li>
                        <a href="sim-list" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sim-list' || $current_page == 'sim-detail') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('sim-list', $current_page, 'ph-sim-card'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM Monitor</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('sync_data')): ?>
                    <li>
                        <a href="sync-data" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sync-data') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('sync-data', $current_page, 'ph-arrows-clockwise'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Sync Data</span>
                        </a>
                    </li>
                    <?php endif; ?>

                </ul>
            </div>

            <div class="mt-8">
                <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">
                    ADMINISTRATION
                </h3>
                <ul class="flex flex-col gap-2">
                    
                    <?php if(hasAccess('manage_client')): ?>
                    <li>
                        <a href="manage-client" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-client') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-client', $current_page, 'ph-buildings'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Client</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('manage_users')): ?>
                    <li>
                        <a href="manage-users" 
                        class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-users') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-users', $current_page, 'ph-users-three'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Users</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('manage_role')): ?>
                    <li>
                        <a href="manage-role" 
                        class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-role') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-role', $current_page, 'ph-shield-check'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Roles</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasAccess('manage_role')): ?> 
                    <li>
                        <a href="manage-smtp" 
                        class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-smtp') ? $active_link_style : $inactive_link_style; ?>">
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