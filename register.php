<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - TailAdmin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                fontFamily: { sans: ['Inter', 'sans-serif'] },
                extend: {
                    colors: {
                        primary: '#4F46E5', /* Warna Indigo Tombol */
                        dark: '#1A222C',
                        darkcard: '#24303F',
                        inputborder: '#E2E8F0', /* Warna Border Input Halus */
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white dark:bg-dark text-slate-600 dark:text-slate-300 font-sans antialiased overflow-x-hidden">

    <div class="flex min-h-screen w-full flex-wrap">
        
        <div class="hidden w-full lg:block lg:w-1/2 bg-slate-50 dark:bg-darkcard relative">
            <div class="flex h-full flex-col items-center justify-center px-12 text-center relative z-10">
                
                <a href="#" class="flex items-center gap-3 mb-10">
                    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white text-xl shadow-lg shadow-indigo-500/20">
                        <i class="ph ph-lightning-fill"></i>
                    </div>
                    <span class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">TailAdmin</span>
                </a>
                
                <h2 class="mb-4 text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">
                    Start your journey with us
                </h2>
                <p class="mb-10 text-base font-medium text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    Create free account and get access to all features. No credit card required.
                </p>

                <div class="relative w-full max-w-[350px]">
                    <img src="https://cdni.iconscout.com/illustration/premium/thumb/sign-up-8044864-6430773.png" alt="Sign Up Illustration" class="w-full h-auto object-contain drop-shadow-sm opacity-90 hover:scale-105 transition-transform duration-500" />
                </div>

            </div>
            
            <div class="absolute inset-0 bg-gradient-to-t from-white/50 to-transparent dark:from-dark/50 z-0 pointer-events-none"></div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-[450px]">
                
                <div class="mb-8 text-center lg:hidden">
                    <a href="#" class="inline-flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white">
                            <i class="ph ph-lightning-fill"></i>
                        </div>
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">TailAdmin</span>
                    </a>
                </div>

                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">
                        Sign Up
                    </h2>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Create your account to continue
                    </p>
                </div>

                <form action="login.php">
                    
                    <div class="mb-5">
                        <label class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-white">Full Name</label>
                        <div class="relative">
                            <input type="text" placeholder="Enter your full name" 
                                class="w-full rounded-lg border border-inputborder dark:border-slate-600 bg-white dark:bg-slate-800 py-3.5 pl-5 pr-12 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xl text-slate-400">
                                <i class="ph ph-user"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-white">Email</label>
                        <div class="relative">
                            <input type="email" placeholder="Enter your email" 
                                class="w-full rounded-lg border border-inputborder dark:border-slate-600 bg-white dark:bg-slate-800 py-3.5 pl-5 pr-12 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xl text-slate-400">
                                <i class="ph ph-envelope-simple"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-white">Password</label>
                        <div class="relative">
                            <input type="password" placeholder="Create a password" 
                                class="w-full rounded-lg border border-inputborder dark:border-slate-600 bg-white dark:bg-slate-800 py-3.5 pl-5 pr-12 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 cursor-pointer hover:text-slate-600 dark:hover:text-slate-300">
                                <i class="ph ph-lock-key"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="mb-2.5 block text-sm font-medium text-slate-700 dark:text-white">Confirm Password</label>
                        <div class="relative">
                            <input type="password" placeholder="Re-enter password" 
                                class="w-full rounded-lg border border-inputborder dark:border-slate-600 bg-white dark:bg-slate-800 py-3.5 pl-5 pr-12 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500" />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xl text-slate-400 cursor-pointer hover:text-slate-600 dark:hover:text-slate-300">
                                <i class="ph ph-lock-key"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <button type="submit" class="w-full cursor-pointer rounded-lg bg-primary py-3.5 px-5 text-base font-semibold text-white transition hover:bg-opacity-90 hover:shadow-lg hover:shadow-indigo-500/30 active:scale-[0.98]">
                            Sign Up
                        </button>
                    </div>

                    <div class="text-center">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Already have an account? 
                            <a href="login.php" class="text-primary font-bold hover:underline ml-1">Sign In</a>
                        </p>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <button id="darkModeToggle" class="fixed bottom-6 right-6 z-50 flex h-11 w-11 items-center justify-center rounded-full bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 shadow-lg border border-slate-200 dark:border-slate-700 hover:text-primary hover:scale-110 transition-all duration-300">
        <i class="ph ph-moon text-xl dark:hidden"></i>
        <i class="ph ph-sun text-xl hidden dark:block"></i>
    </button>

    <script>
        const toggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;

        // Init Theme
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        }

        // Toggle Event
        toggle.addEventListener('click', () => {
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
        });
    </script>
</body>
</html>