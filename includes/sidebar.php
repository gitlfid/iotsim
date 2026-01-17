<?php
// Mendapatkan nama file halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);

// Definisi Style Class
// Active: Background Indigo muda, Teks Indigo, Icon Filled
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm ring-1 ring-indigo-200 dark:ring-transparent font-semibold";

// Inactive: Abu-abu, Hover effect
$inactive_link_style = "text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";

// Fungsi Helper untuk Icon Active (Menambahkan class ph-fill jika aktif)
function getIconClass($page_name, $current_page, $icon_name) {
    // Jika halaman aktif, tambahkan 'ph-fill' agar icon menjadi solid/berisi
    $fill = ($current_page == $page_name) ? 'ph-fill' : '';
    return "ph {$fill} {$icon_name} text-xl";
}
?>

<aside id="sidebar" class="group fixed left-0 top-0 z-50 flex h-screen w-[280px] flex-col overflow-y-hidden bg-white dark:bg-[#24303F] duration-300 ease-in-out lg:static lg:translate-x-0 -translate-x-full border-r border-slate-100 dark:border-slate-800 shrink-0 shadow-soft dark:shadow-none transition-all">
    
    <div class="flex items-center justify-between gap-2 px-6 py-5.5 lg:py-6.5 h-24 lg:h-20 shrink-0 group-[.is-collapsed]:justify-center group-[.is-collapsed]:px-0">
        <a href="dashboard.php" class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-primary flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-indigo-500/20 group-[.is-collapsed]:w-10 group-[.is-collapsed]:h-10 transition-all">
                <i class="ph ph-lightning-fill"></i>
            </div>
            <span class="text-xl font-bold text-slate-800 dark:text-white tracking-tight group-[.is-collapsed]:hidden whitespace-nowrap opacity-100 duration-300">
                IoT Platform
            </span>
        </a>
        <button id="closeSidebarMobile" class="block lg:hidden text-slate-400 hover:text-indigo-600 p-1">
            <i class="ph ph-x text-xl"></i>
        </button>
    </div>

    <div class="flex flex-col overflow-y-auto no-scrollbar flex-1 py-4 group-[.is-collapsed]:items-center">
        <nav class="mt-2 w-full px-4 lg:px-6 group-[.is-collapsed]:px-2">
            
            <div class="mb-6">
                <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest group-[.is-collapsed]:hidden">
                    OVERVIEW
                </h3>
                
                <ul class="flex flex-col gap-2">
                    <li>
                        <a href="dashboard.php" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'dashboard.php') ? $active_link_style : $inactive_link_style; ?>">
                            
                            <i class="<?php echo getIconClass('dashboard.php', $current_page, 'ph-squares-four'); ?>"></i>
                            
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Dashboard</span>
                        </a>
                    </li>

                    <li>
                         <div class="group-[.is-collapsed]:hidden px-4 py-2 mt-4 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase">SIM CARD MANAGEMENT</div>
                    </li>

                    
                    <li>
                        <a href="sim-list.php" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sim-list.php') ? $active_link_style : $inactive_link_style; ?>">
                            
                            <i class="<?php echo getIconClass('sim-list.php', $current_page, 'ph-sim-card'); ?>"></i>
                            
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM List Monitor</span>
                        </a>
                    </li>


                    <li>
                        <a href="sync-data.php" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sync-data.php') ? $active_link_style : $inactive_link_style; ?>">
                            
                            <i class="<?php echo getIconClass('sync-data.php', $current_page, 'ph-arrows-clockwise'); ?>"></i>
                            
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Sync Data</span>
                        </a>
                    </li>
                        

                    <li>
                        <a href="upload-sim.php" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'upload-sim.php') ? $active_link_style : $inactive_link_style; ?>">
                            
                            <i class="<?php echo getIconClass('upload-sim.php', $current_page, 'ph-upload-simple'); ?>"></i>
                            
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Upload SIM</span>
                        </a>
                    </li>
                
                </ul>
            </div>

            <div class="mb-6">
                <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest group-[.is-collapsed]:hidden">
                    ADMINISTRATION
                </h3>
                <ul class="flex flex-col gap-2">
                    <li>
                        <a href="manage-client.php" 
                           class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-client.php') ? $active_link_style : $inactive_link_style; ?>">
                            
                            <i class="<?php echo getIconClass('manage-client.php', $current_page, 'ph-buildings'); ?>"></i>
                            
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Client</span>
                        </a>
                    </li>

                    <li>
                        <a href="manage-users.php" 
                        class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-users.php') ? $active_link_style : $inactive_link_style; ?>">
                            
                            <i class="<?php echo getIconClass('manage-users.php', $current_page, 'ph-users-three'); ?>"></i>
                            
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Manage Users</span>
                        </a>
                    </li>
                </ul>
            </div>

        </nav>
    </div>
</aside>