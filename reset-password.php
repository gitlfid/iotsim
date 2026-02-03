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
                extend: { colors: { primary: '#4F46E5', dark: '#1A222C', darkcard: '#24303F' } }
            }
        }
    </script>
</head>
<body class="bg-white dark:bg-dark text-slate-600 dark:text-slate-300 font-sans antialiased">
    <div class="flex h-screen flex-wrap items-center justify-center lg:justify-between">
        
        <div class="hidden h-screen w-full lg:block lg:w-1/2 bg-slate-50 dark:bg-darkcard relative overflow-hidden">
             <div class="flex h-full flex-col items-center justify-center p-12 text-center relative z-10">
                <div class="mb-6 p-4 bg-indigo-100 dark:bg-indigo-900/30 rounded-full text-primary">
                    <i class="ph ph-shield-check text-4xl"></i>
                </div>
                <h2 class="mb-4 text-2xl font-bold text-slate-800 dark:text-white">Secure Your Account</h2>
                <p class="text-slate-500 max-w-md">For your security, please update your password before accessing the dashboard.</p>
             </div>
        </div>

        <div class="w-full lg:w-1/2 p-4 sm:p-12 xl:p-20">
            <div class="mx-auto w-full max-w-[450px] bg-white dark:bg-darkcard p-8 sm:p-10 rounded-2xl border border-slate-100 dark:border-slate-700 lg:border-none">
                
                <div class="mb-8">
                    <h2 class="mb-2 text-2xl font-bold text-slate-800 dark:text-white">Change Password</h2>
                    <p class="text-sm text-slate-500">Please create a new, strong password.</p>
                </div>

                <?php if($error): ?>
                    <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="mb-4">
                        <label class="mb-2.5 block font-medium text-slate-700 dark:text-slate-200">New Password</label>
                        <div class="relative">
                            <input type="password" name="new_password" placeholder="Min. 6 characters" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-transparent py-3 pl-6 pr-10 text-slate-700 dark:text-white outline-none focus:border-primary">
                            <span class="absolute right-4 top-3.5 text-xl text-slate-400"><i class="ph ph-lock"></i></span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="mb-2.5 block font-medium text-slate-700 dark:text-slate-200">Confirm Password</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" placeholder="Re-enter new password" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-transparent py-3 pl-6 pr-10 text-slate-700 dark:text-white outline-none focus:border-primary">
                            <span class="absolute right-4 top-3.5 text-xl text-slate-400"><i class="ph ph-check-circle"></i></span>
                        </div>
                    </div>

                    <div class="mb-5">
                        <button type="submit" class="w-full cursor-pointer rounded-xl bg-primary py-3 px-5 font-bold text-white transition hover:bg-opacity-90 shadow-lg shadow-indigo-500/30">
                            Update Password & Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>