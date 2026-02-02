<?php 
include 'config.php';
checkLogin();

if ($_SESSION['role'] !== 'superadmin') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

$msg = "";
$msgType = "";

// --- HANDLE SAVE SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_smtp'])) {
    $host = $_POST['host'];
    $port = $_POST['port'];
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $enc  = $_POST['encryption'];
    $from = $_POST['from_email'];
    $name = $_POST['from_name'];

    $sql = "UPDATE smtp_settings SET host=?, port=?, username=?, password=?, encryption=?, from_email=?, from_name=? WHERE id=1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisssss", $host, $port, $user, $pass, $enc, $from, $name);
    
    if ($stmt->execute()) {
        $msg = "SMTP Configuration saved successfully.";
        $msgType = "success";
    } else {
        $msg = "Failed to save configuration.";
        $msgType = "error";
    }
}

// --- HANDLE TEST EMAIL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email'])) {
    $target = $_POST['test_target'];
    $subject = "SMTP Test from IoT Platform";
    $body = "<h1>It Works!</h1><p>This is a test email from your IoT Platform SMTP configuration.</p>";
    
    $result = sendEmail($target, $subject, $body);
    
    if ($result['status']) {
        $msg = "Test email sent successfully to $target";
        $msgType = "success";
    } else {
        $msg = "Test Failed: " . $result['msg'];
        $msgType = "error";
    }
}

// Get Current Settings
$curr = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMTP Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#4F46E5', darkcard: '#24303F' } } }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-gray-900 text-slate-600 dark:text-slate-300 font-sans">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-4xl mx-auto">
                    
                    <div class="mb-8">
                        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Email Configuration</h1>
                        <p class="text-sm text-slate-500 mt-1">Configure SMTP settings to enable auto-email for new user accounts.</p>
                    </div>

                    <?php if($msg): ?>
                        <div class="mb-6 p-4 rounded-xl border flex items-center gap-2 <?= $msgType == 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
                            <i class="ph <?= $msgType == 'success' ? 'ph-check-circle' : 'ph-warning' ?> text-xl"></i> 
                            <?= $msg ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <div class="md:col-span-2 bg-white dark:bg-darkcard rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-6 flex items-center gap-2">
                                <i class="ph ph-gear"></i> Server Settings
                            </h3>
                            
                            <form method="POST">
                                <input type="hidden" name="save_smtp" value="1">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">SMTP Host</label>
                                        <input type="text" name="host" value="<?= $curr['host'] ?>" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none" placeholder="smtp.gmail.com">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Port</label>
                                        <input type="number" name="port" value="<?= $curr['port'] ?>" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none" placeholder="587">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Encryption</label>
                                        <select name="encryption" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none">
                                            <option value="tls" <?= $curr['encryption'] == 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                                            <option value="ssl" <?= $curr['encryption'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value="" <?= $curr['encryption'] == '' ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">SMTP Username</label>
                                        <input type="text" name="username" value="<?= $curr['username'] ?>" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">SMTP Password</label>
                                        <div class="relative">
                                            <input type="password" name="password" value="<?= $curr['password'] ?>" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none">
                                            <i class="ph ph-eye absolute right-3 top-2.5 text-slate-400 cursor-pointer" onclick="let i = this.previousElementSibling; i.type = i.type === 'password' ? 'text' : 'password'"></i>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2 border-t border-slate-100 dark:border-slate-700 my-2"></div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Sender Email (From)</label>
                                        <input type="email" name="from_email" value="<?= $curr['from_email'] ?>" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Sender Name</label>
                                        <input type="text" name="from_name" value="<?= $curr['from_name'] ?>" class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none">
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end">
                                    <button type="submit" class="bg-primary hover:bg-indigo-600 text-white px-6 py-2.5 rounded-lg shadow-lg font-bold transition-all">
                                        Save Configuration
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="md:col-span-1">
                            <div class="bg-white dark:bg-darkcard rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 sticky top-6">
                                <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                                    <i class="ph ph-paper-plane-tilt"></i> Test Delivery
                                </h3>
                                <p class="text-xs text-slate-500 mb-4">Send a test email to ensure your configuration is working correctly.</p>
                                
                                <form method="POST">
                                    <input type="hidden" name="test_email" value="1">
                                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Target Email</label>
                                    <input type="email" name="test_target" required class="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:border-primary outline-none mb-4" placeholder="your@email.com">
                                    
                                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg shadow-md font-bold transition-all flex justify-center items-center gap-2">
                                        <i class="ph ph-paper-plane-right"></i> Send Test
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>