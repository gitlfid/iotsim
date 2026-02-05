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
    // Ambil input (bisa berupa email atau username)
    $login_input = $_POST['login_input']; 
    $password = $_POST['password'];

    // UPDATE QUERY: Cek apakah input cocok dengan email ATAU username
    $stmt = $conn->prepare("SELECT id, username, password, role, company_id, force_reset FROM users WHERE email = ? OR username = ?");
    
    // Bind parameter dua kali untuk masing-masing tanda tanya (?)
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if (password_verify($password, $row['password'])) {
            // Set Session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username']; // Tetap simpan username asli di session
            $_SESSION['role'] = $row['role'];
            $_SESSION['company_id'] = $row['company_id'];
            $_SESSION['force_reset'] = $row['force_reset']; 

            // Cek Arah Redirect
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
                extend: { colors: { primary: '#4F46E5', primaryHover: '#4338ca', dark: '#1A222C', darkcard: '#24303F' } }
            }
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-dark text-slate-600 dark:text-slate-300 font-sans antialiased">
    <div class="flex h-screen w-full flex-wrap">
        
        <div class="hidden h-screen w-full lg:block lg:w-1/2 relative overflow-hidden">
             <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1639322537228-f710d846310a?q=80&w=2832&auto=format&fit=crop');"></div>
             <div class="absolute inset-0 bg-indigo-900/80 mix-blend-multiply"></div>
             
             <div class="flex h-full flex-col items-center justify-center p-12 text-center relative z-10 text-white">
                <div class="mb-6 rounded-2xl bg-white/10 p-4 backdrop-blur-sm border border-white/20">
                    <i class="ph ph-lightning text-5xl text-yellow-300"></i>
                </div>
                <h2 class="mb-4 text-4xl font-bold tracking-tight">IoT Connectivity Platform</h2>
                <p class="max-w-md text-lg text-indigo-100 opacity-90">Manage your SIM cards, monitor data usage, and control your connected devices in real-time.</p>
             </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-[450px]">
                
                <div class="mb-10 text-center lg:text-left">
                    <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-2">Welcome Back!</h2>
                    <p class="text-slate-500 dark:text-slate-400">Please sign in to access your dashboard.</p>
                </div>

                <?php if($error): ?>
                    <div class="mb-6 flex items-center gap-3 p-4 text-sm text-red-700 bg-red-50 border border-red-100 rounded-xl dark:bg-red-900/20 dark:text-red-300 dark:border-red-800 animate-fade-in-up" role="alert">
                        <i class="ph ph-warning-circle text-xl"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-6">
                    
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Username or Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph ph-user text-xl text-slate-400"></i>
                            </div>
                            <input type="text" 
                                   name="login_input" 
                                   placeholder="Enter your username or email" 
                                   required 
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-darkcard py-3.5 pl-11 pr-4 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                                Password
                            </label>
                            </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph ph-lock-key text-xl text-slate-400"></i>
                            </div>
                            <input type="password" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required 
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-darkcard py-3.5 pl-11 pr-4 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm placeholder:text-slate-400">
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-primary hover:bg-primaryHover py-3.5 px-6 text-sm font-bold text-white transition-all shadow-lg shadow-indigo-500/20 active:scale-95 flex items-center justify-center gap-2">
                        <span>Sign In</span>
                        <i class="ph ph-arrow-right font-bold"></i>
                    </button>

                </form>

                <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    &copy; <?= date('Y') ?> IoT Platform. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</body>
</html>