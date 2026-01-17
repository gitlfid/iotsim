<header class="sticky top-0 z-40 flex w-full bg-white/80 backdrop-blur-md dark:bg-[#1A222C]/80 shadow-soft transition-all duration-300 border-b border-slate-100 dark:border-slate-800">
    <div class="flex flex-grow items-center justify-between px-4 py-4 md:px-6 2xl:px-11 h-20">
        
        <div class="flex items-center gap-4 sm:gap-6">
            
            <button id="sidebarToggle" class="z-50 block rounded-lg p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                 <i class="ph ph-list text-2xl"></i>
            </button>

            <div class="hidden sm:block lg:w-80">
                <!-- <form action="#" method="POST">
                    <div class="relative group">
                        <button class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-hover:text-indigo-500 transition-colors">
                            <i class="ph ph-magnifying-glass text-xl"></i>
                        </button>
                        <input type="text" placeholder="Type to search..." class="w-full rounded-xl bg-slate-50 dark:bg-slate-900 border-transparent py-2.5 pl-10 pr-4 font-medium text-slate-600 dark:text-slate-300 placeholder-slate-400 outline-none focus:border-indigo-300 focus:bg-white dark:focus:bg-[#1A222C] focus:ring-2 focus:ring-indigo-100/50 transition-all" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-1.5 py-0.5 text-xs font-semibold text-slate-400 rounded-md shadow-sm">
                            ⌘ K
                        </span>
                    </div>
                </form> -->
            </div>
        </div>

        <div class="flex items-center gap-3 2xsm:gap-6">
            <ul class="flex items-center gap-2">
                
                <li>
                    <button id="darkModeToggle" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all">
                        <i class="ph ph-moon text-xl dark:hidden"></i>
                        <i class="ph ph-sun text-xl hidden dark:block"></i>
                    </button>
                </li>

                <!-- <li class="relative">
                    <button id="notificationBtn" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all">
                        <i class="ph ph-bell text-xl"></i>
                        <span class="absolute top-2 right-2 z-1 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-[#1A222C]"></span>
                    </button>

                    <div id="notificationDropdown" class="hidden absolute right-0 mt-4 flex w-80 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-soft-lg z-50 overflow-hidden transition-all origin-top-right">
                        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                            <h5 class="text-base font-bold text-slate-800 dark:text-white">Notification</h5>
                            <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                                 <i class="ph ph-x text-xl"></i>
                            </button>
                        </div>
                        <ul class="flex flex-col max-h-[360px] overflow-y-auto no-scrollbar">
                            <li>
                                <a href="#" class="flex gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 border-b border-slate-50 dark:border-slate-700/50 transition-colors">
                                    <div class="relative h-11 w-11 shrink-0">
                                        <img src="https://i.pravatar.cc/150?u=10" alt="User" class="h-full w-full rounded-full object-cover">
                                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-[#24303F]"></span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <span class="font-bold text-slate-800 dark:text-white">Terry Franci</span> requests permission to change <span class="font-bold text-slate-800 dark:text-white">Project - Nganter App</span>
                                        </p>
                                        <p class="mt-1 text-xs font-medium text-slate-400">Project <span class="mx-1 text-slate-300">•</span> 5 min ago</p>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a href="#" class="flex gap-4 px-5 py-4 bg-slate-50/60 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700 border-b border-slate-50 dark:border-slate-700/50 transition-colors">
                                     <div class="relative h-11 w-11 shrink-0">
                                        <img src="https://i.pravatar.cc/150?u=22" alt="User" class="h-full w-full rounded-full object-cover">
                                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-[#24303F]"></span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                                            <span class="font-bold text-slate-800 dark:text-white">Alena Franci</span> requests permission to change <span class="font-bold text-slate-800 dark:text-white">Project - Nganter App</span>
                                        </p>
                                        <p class="mt-1 text-xs font-medium text-slate-400">Project <span class="mx-1 text-slate-300">•</span> 8 min ago</p>
                                    </div>
                                </a>
                            </li>
                        </ul>
                        <div class="p-4 border-t border-slate-100 dark:border-slate-700">
                            <button class="flex w-full items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700/50 py-2.5 text-sm font-bold text-slate-700 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm">
                                View All Notification
                            </button>
                        </div>
                    </div>
                </li> -->
            </ul>

            <div class="relative">
                <div id="profileBtn" class="flex items-center gap-3 cursor-pointer pl-4 border-l border-slate-100 dark:border-slate-700 transition-colors">
                    <span class="hidden text-right lg:block">
                        <span class="block text-sm font-bold text-slate-800 dark:text-white"><?= $_SESSION['username'] ?? 'User' ?></span>
                        <span class="block text-xs font-medium text-slate-400"><?= ucfirst($_SESSION['role'] ?? 'Guest') ?></span>
                    </span>
                    <div class="h-11 w-11 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 ring-2 ring-slate-100 dark:ring-slate-800 shadow-sm transition-all group-hover:ring-indigo-100">
                        <img src="https://ui-avatars.com/api/?name=<?= $_SESSION['username'] ?? 'User' ?>&background=random" alt="User" class="object-cover w-full h-full">
                    </div>
                    <i class="ph ph-caret-down text-slate-400 text-sm hidden lg:block"></i>
                </div>

                <div id="profileDropdown" class="hidden absolute right-0 mt-4 flex w-64 flex-col rounded-xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-[#24303F] shadow-soft-lg z-50 overflow-hidden transition-all origin-top-right">
                    
                    <div class="px-6 py-5">
                        <p class="text-sm font-bold text-slate-800 dark:text-white"><?= $_SESSION['username'] ?? 'User' ?></p>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5"><?= $_SESSION['email'] ?? 'user@example.com' ?></p>
                    </div>

                    <ul class="flex flex-col gap-1 px-4">
                        <li>
                            <a href="#" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white transition-colors">
                                <i class="ph ph-user text-xl"></i>
                                Edit profile
                            </a>
                        </li>
                        <li>
                            <a href="#" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white transition-colors">
                                <i class="ph ph-gear text-xl"></i>
                                Account settings
                            </a>
                        </li>
                         <li>
                            <a href="#" class="flex items-center gap-3.5 rounded-lg px-2 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white transition-colors">
                                <i class="ph ph-info text-xl"></i>
                                Support
                            </a>
                        </li>
                    </ul>

                    <div class="px-4 my-2">
                         <div class="border-t border-slate-100 dark:border-slate-700"></div>
                    </div>

                    <div class="px-4 pb-4">
                         <a href="logout.php" class="flex items-center gap-3.5 rounded-lg px-2 py-2 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-white transition-colors">
                            <i class="ph ph-sign-out text-xl"></i>
                            Sign out
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</header>