<?php
include 'config.php';

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['email']; // Di form input namenya kita set 'email' tapi isinya username/email
    $password = $_POST['password'];

    // Query Cek User
    $stmt = $conn->prepare("SELECT id, username, role, company_id FROM users WHERE username = ? AND password = ?");
    // Catatan: Di production, password harusnya pakai password_verify() (Hash), ini contoh plain text sesuai request simple
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Set Session
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['company_id'] = $row['company_id'];

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password!";
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
                extend: { colors: { primary: '#4F46E5', dark: '#1A222C', darkcard: '#24303F' } }
            }
        }
    </script>
</head>
<body class="bg-white dark:bg-dark text-slate-600 dark:text-slate-300 font-sans antialiased">
    <div class="flex h-screen flex-wrap items-center justify-center lg:justify-between">
        
        <div class="hidden h-screen w-full lg:block lg:w-1/2 bg-slate-50 dark:bg-darkcard relative overflow-hidden">
             <div class="flex h-full flex-col items-center justify-center p-12 text-center relative z-10">
                <h2 class="mb-4 text-2xl font-bold text-slate-800 dark:text-white">IoT Platform Login</h2>
             </div>
        </div>

        <div class="w-full lg:w-1/2 p-4 sm:p-12 xl:p-20">
            <div class="mx-auto w-full max-w-[450px] bg-white dark:bg-darkcard p-8 sm:p-10 rounded-2xl border border-slate-100 dark:border-slate-700 lg:border-none">
                
                <div class="mb-8">
                    <h2 class="mb-2 text-2xl font-bold text-slate-800 dark:text-white">Welcome Back!</h2>
                    <p class="text-sm text-slate-500">Please sign in to manage your SIM cards.</p>
                </div>

                <?php if($error): ?>
                    <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="mb-4">
                        <label class="mb-2.5 block font-medium text-slate-700 dark:text-slate-200">Username</label>
                        <div class="relative">
                            <input type="text" name="email" placeholder="Enter username (e.g. admin)" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-transparent py-3 pl-6 pr-10 text-slate-700 dark:text-white outline-none focus:border-primary">
                            <span class="absolute right-4 top-3.5 text-xl text-slate-400"><i class="ph ph-user"></i></span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="mb-2.5 block font-medium text-slate-700 dark:text-slate-200">Password</label>
                        <div class="relative">
                            <input type="password" name="password" placeholder="Enter password" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-transparent py-3 pl-6 pr-10 text-slate-700 dark:text-white outline-none focus:border-primary">
                            <span class="absolute right-4 top-3.5 text-xl text-slate-400"><i class="ph ph-lock-key"></i></span>
                        </div>
                    </div>

                    <div class="mb-5">
                        <button type="submit" class="w-full cursor-pointer rounded-xl bg-primary py-3 px-5 font-bold text-white transition hover:bg-opacity-90 shadow-lg shadow-indigo-500/30">
                            Sign In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>