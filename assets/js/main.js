document.addEventListener('DOMContentLoaded', () => {
    
    // Elements
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const closeSidebarMobile = document.getElementById('closeSidebarMobile');
    
    // Overlay Mobile
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-40 bg-slate-900/50 hidden transition-opacity duration-300 opacity-0 lg:hidden';
    document.body.appendChild(overlay);

    function toggleSidebar() {
        if (window.innerWidth >= 1024) {
            // --- LOGIC DESKTOP (MINI SIDEBAR) ---
            
            if (sidebar.classList.contains('lg:w-[280px]')) {
                // ACTION: COLLAPSE (Mengecil)
                sidebar.classList.remove('lg:w-[280px]');
                sidebar.classList.add('lg:w-[90px]'); // Lebar mini
                sidebar.classList.add('is-collapsed'); // Marker class untuk styling konten
                sidebar.classList.add('px-3'); // Adjust padding container
            } else {
                // ACTION: EXPAND (Melebar Normal)
                sidebar.classList.add('lg:w-[280px]');
                sidebar.classList.remove('lg:w-[90px]');
                sidebar.classList.remove('is-collapsed');
                sidebar.classList.remove('px-3');
            }

        } else {
            // --- LOGIC MOBILE (SLIDE IN/OUT) ---
            const isClosed = sidebar.classList.contains('-translate-x-full');
            if (isClosed) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }
    }

    // Default State Load
    if (window.innerWidth >= 1024) {
        sidebar.classList.add('lg:w-[280px]');
    }

    // Event Listeners
    if(sidebarToggle) sidebarToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleSidebar(); });
    if(closeSidebarMobile) closeSidebarMobile.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    // Handle Resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            overlay.classList.add('hidden');
            sidebar.classList.remove('-translate-x-full'); 
            // Pastikan reset ke full width jika resize dari mobile ke desktop
            if(!sidebar.classList.contains('lg:w-[90px]')) {
                sidebar.classList.add('lg:w-[280px]');
            }
        } else {
            sidebar.classList.add('-translate-x-full');
        }
    });

    // Dark Mode Logic (Tetap dipertahankan)
    const darkModeToggle = document.getElementById('darkModeToggle');
    const html = document.documentElement;
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    }
    if(darkModeToggle) {
        darkModeToggle.addEventListener('click', () => {
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
        });
    }

    // Dropdown Logic (Tetap dipertahankan)
    function setupDropdown(btnId, dropdownId) {
        const btn = document.getElementById(btnId);
        const dropdown = document.getElementById(dropdownId);
        if(!btn || !dropdown) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('[id$="Dropdown"]').forEach(el => { if(el.id !== dropdownId) el.classList.add('hidden'); });
            dropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !btn.contains(e.target)) dropdown.classList.add('hidden');
        });
    }
    setupDropdown('notificationBtn', 'notificationDropdown');
    setupDropdown('profileBtn', 'profileDropdown');
});