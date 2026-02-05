<?php
include 'config.php';

// Pastikan user sudah login, tapi BELUM diizinkan akses dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Jika status force_reset sudah 0 (sudah ganti), lempar ke dashboard
if ($_SESSION['force_reset'] == 0) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_pass !== $conf_pass) {
        $error = "Passwords do not match.";
    } else {
        // Update Password & Matikan force_reset
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $uid = $_SESSION['user_id'];

        $stmt = $conn->prepare("UPDATE users SET password = ?, force_reset = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashed, $uid);
        
        if ($stmt->execute()) {
            // Update Session
            $_SESSION['force_reset'] = 0;
            
            // Redirect ke Dashboard
            echo "<script>alert('Password changed successfully!'); window.location='dashboard.php';</script>";
            exit();
        } else {
            $error = "Failed to update password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Password - IoT Platform</title>
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
                    <i class="ph ph-shield-check text-5xl text-emerald-300"></i>
                </div>
                <h2 class="mb-4 text-4xl font-bold tracking-tight">Secure Your Account</h2>
                <p class="max-w-md text-lg text-indigo-100 opacity-90">Please update your password to ensure the security of your IoT dashboard access.</p>
             </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-[450px]">
                
                <div class="mb-10 text-center lg:text-left">
                    <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-2">Set New Password</h2>
                    <p class="text-slate-500 dark:text-slate-400">Your account requires a password reset before continuing.</p>
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
                            New Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph ph-lock text-xl text-slate-400"></i>
                            </div>
                            <input type="password" 
                                   name="new_password" 
                                   placeholder="Min. 6 characters" 
                                   required 
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-darkcard py-3.5 pl-11 pr-4 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm placeholder:text-slate-400">
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph ph-check-circle text-xl text-slate-400"></i>
                            </div>
                            <input type="password" 
                                   name="confirm_password" 
                                   placeholder="Re-enter new password" 
                                   required 
                                   class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-darkcard py-3.5 pl-11 pr-4 text-slate-700 dark:text-white outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-sm placeholder:text-slate-400">
                        </div>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-primary hover:bg-primaryHover py-3.5 px-6 text-sm font-bold text-white transition-all shadow-lg shadow-indigo-500/20 active:scale-95 flex items-center justify-center gap-2">
                        <span>Update Password</span>
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