<?php
include 'config.php';
checkLogin();

// Hanya Superadmin yang boleh akses halaman pengaturan Role ini
if ($_SESSION['role'] !== 'superadmin') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

// Daftar Menu/Halaman yang tersedia di Sidebar
$available_pages = [
    'dashboard'     => 'Dashboard',
    'sim_list'      => 'SIM Monitor',
    'sync_data'     => 'Sync Data',
    'manage_client' => 'Manage Client',
    'manage_users'  => 'Manage Users',
    'manage_role'   => 'Manage Role'
];

$roles_list = ['superadmin', 'admin', 'sub-admin', 'user'];
$selected_role = isset($_GET['role']) ? $_GET['role'] : 'admin';

// HANDLE SAVE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $target_role = $_POST['target_role'];
    $selected_pages = isset($_POST['pages']) ? $_POST['pages'] : [];

    // 1. Hapus permission lama untuk role ini
    $del = $conn->prepare("DELETE FROM role_permissions WHERE role = ?");
    $del->bind_param("s", $target_role);
    $del->execute();

    // 2. Insert permission baru
    if (!empty($selected_pages)) {
        $stmt = $conn->prepare("INSERT INTO role_permissions (role, page_key) VALUES (?, ?)");
        foreach ($selected_pages as $page_key) {
            $stmt->bind_param("ss", $target_role, $page_key);
            $stmt->execute();
        }
    }
    
    echo "<script>alert('Permissions updated for $target_role'); window.location='manage-role.php?role=$target_role';</script>";
    exit();
}

// AMBIL PERMISSION SAAT INI
$current_perms = [];
$q = $conn->prepare("SELECT page_key FROM role_permissions WHERE role = ?");
$q->bind_param("s", $selected_role);
$q->execute();
$res = $q->get_result();
while($row = $res->fetch_assoc()) {
    $current_perms[] = $row['page_key'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Roles</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { colors: { primary: '#4F46E5', darkcard: '#24303F' } }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-gray-900 text-slate-600 dark:text-slate-300">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-4xl mx-auto">
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">Manage Role Permissions</h2>

                    <div class="flex border-b border-slate-200 dark:border-slate-700 mb-6">
                        <?php foreach($roles_list as $r): 
                            $active = ($selected_role == $r) ? 'border-primary text-primary' : 'border-transparent hover:text-slate-700 dark:hover:text-white';
                        ?>
                        <a href="?role=<?= $r ?>" class="px-6 py-3 text-sm font-bold uppercase border-b-2 transition-colors <?= $active ?>">
                            <?= $r ?>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" class="bg-white dark:bg-darkcard rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
                        <input type="hidden" name="target_role" value="<?= $selected_role ?>">
                        <input type="hidden" name="save_permissions" value="1">

                        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4">
                            Access Control for: <span class="text-primary uppercase"><?= $selected_role ?></span>
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach($available_pages as $key => $label): 
                                $checked = in_array($key, $current_perms) ? 'checked' : '';
                            ?>
                            <label class="flex items-center p-4 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors">
                                <input type="checkbox" name="pages[]" value="<?= $key ?>" class="w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary" <?= $checked ?>>
                                <span class="ml-3 font-medium text-slate-700 dark:text-white"><?= $label ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-8 flex justify-end">
                            <button type="submit" class="bg-primary hover:bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg shadow-indigo-500/30 transition-all">
                                <i class="ph ph-floppy-disk mr-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>