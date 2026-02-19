<?php
include 'config.php';

// Jika sudah login, cek status reset
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['force_reset']) && $_SESSION['force_reset'] == 1) {
        header("Location: reset-password.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_input = $_POST['login_input'] ?? $_POST['email'] ?? $_POST['username'] ?? ''; 
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role, company_id, force_reset FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['company_id'] = $row['company_id'];
                $_SESSION['force_reset'] = $row['force_reset']; 

                if ($row['force_reset'] == 1) {
                    header("Location: reset-password.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid credential or password!";
            }
        } else {
            $error = "Account not found!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - IoT Platform</title>
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
                        primary: '#4F46E5', 
                        primaryHover: '#4338ca', 
                        dark: '#0F172A', 
                        darkcard: '#1E293B' 
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
        .glass-effect {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .pattern-grid {
            background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-dark text-slate-600 dark:text-slate-300 font-sans antialiased">
    <div class="flex h-screen w-full flex-wrap">
        
        <div class="hidden h-screen w-full lg:block lg:w-1/2 relative overflow-hidden bg-slate-900">
             
             <div class="absolute top-0 -left-4 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
             <div class="absolute top-0 -right-4 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
             <div class="absolute -bottom-8 left-20 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-4000"></div>
             
             <div class="absolute inset-0 pattern-grid z-0 opacity-30"></div>
             
             <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/20 to-transparent z-0"></div>

             <div class="flex h-full flex-col items-center justify-center p-12 text-center relative z-10 text-white">
                
                <div class="mb-8 p-6 glass-effect rounded-3xl shadow-2xl animate-float">
                    <i class="ph ph-circuitry text-6xl text-indigo-400"></i>
                </div>
                
                <h2 class="mb-6 text-5xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-indigo-200 via-white to-indigo-200 drop-shadow-sm">
                    IoT Connectivity
                </h2>
                
                <p class="max-w-md text-lg text-slate-300 leading-relaxed font-light">
                    Seamlessly manage SIM cards, monitor real-time data usage, and control your connected ecosystem from a single, powerful dashboard.
                </p>

                <div class="mt-10 flex gap-3 text-xs font-medium text-slate-400 uppercase tracking-widest">
                    <span class="px-4 py-2 glass-effect rounded-full">Secure</span>
                    <span class="px-4 py-2 glass-effect rounded-full">Real-time</span>
                    <span class="px-4 py-2 glass-effect rounded-full">Scalable</span>
                </div>
             </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 bg-white dark:bg-dark">
            <div class="w-full max-w-[420px]">
                
                <div class="mb-10">
                    <div class="h-12 w-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center text-primary mb-6 lg:hidden">
                        <i class="ph ph-lightning text-2xl"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2 tracking-tight">Welcome Back</h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">Please enter your credentials to access the dashboard.</p>
                </div>

                <?php if($error): ?>
                    <div class="mb-6 flex items-start gap-3 p-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20 animate-fade-in-up" role="alert">
                        <i class="ph ph-warning-circle text-lg shrink-0 mt-0.5"></i>
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-5">
                    
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                            Identity
                        </label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph ph-user text-lg text-slate-400 group-focus-within:text-primary transition-colors"></i>
                            </div>
                            <input type="text" 
                                   name="login_input" 
                                   placeholder="Username or email address" 
                                   required 
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-darkcard py-3.5 pl-11 pr-4 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all shadow-sm placeholder:text-slate-400 font-medium">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                Password
                            </label>
                            </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph ph-lock-key text-lg text-slate-400 group-focus-within:text-primary transition-colors"></i>
                            </div>
                            <input type="password" 
                                   name="password" 
                                   id="passwordInput"
                                   placeholder="Enter your password" 
                                   required 
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-darkcard py-3.5 pl-11 pr-12 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all shadow-sm placeholder:text-slate-400 font-medium">
                            
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors cursor-pointer focus:outline-none">
                                <i class="ph ph-eye text-lg" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full group relative flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primaryHover py-3.5 px-6 text-sm font-bold text-white transition-all shadow-lg shadow-indigo-500/30 active:scale-95 overflow-hidden">
                        <span class="relative z-10">Sign In to Dashboard</span>
                        <i class="ph ph-arrow-right font-bold relative z-10 group-hover:translate-x-1 transition-transform"></i>
                        <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                    </button>

                </form>

                <div class="mt-10 pt-6 border-t border-slate-100 dark:border-slate-800 text-center">
                    <p class="text-xs text-slate-400">
                        &copy; <?= date('Y') ?> IoT Connectivity Platform. <br>Secure & Reliable Connection.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('eyeIcon');
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('ph-eye', 'ph-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('ph-eye-slash', 'ph-eye');
            }
        }
    </script>
</body>
</html>