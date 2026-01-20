<?php 
include 'config.php';
checkLogin();

// Hanya Superadmin/Admin yang boleh akses halaman ini
if ($_SESSION['role'] == 'user') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

$success = "";
$error = "";

// --- 1. HANDLE ADD USER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password']; // Catatan: Sebaiknya di-hash (password_hash) jika login.php mendukungnya.
    $role = $_POST['role'];
    $company_id = $_POST['company_id'];

    // Cek username kembar
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        $error = "Username already exists!";
    } else {
        // Insert User Baru
        // Encrypt Password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, company_id) VALUES (?, ?, ?, ?)");
        // Gunakan $hashed_password disini
        $stmt->bind_param("sssi", $username, $hashed_password, $role, $company_id);
        
        if ($stmt->execute()) {
            $success = "User created successfully!";
        } else {
            $error = "Failed to create user.";
        }
    }
}

// --- 2. HANDLE DELETE USER ---
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    // Cegah hapus diri sendiri
    if ($del_id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id = '$del_id'");
        $success = "User deleted successfully!";
    } else {
        $error = "You cannot delete your own account!";
    }
}

// --- 3. FETCH DATA ---
// Ambil daftar perusahaan untuk Dropdown
$companies = $conn->query("SELECT * FROM companies ORDER BY level ASC, company_name ASC");

// Ambil daftar Users untuk Table (Join dengan Company agar nama PT muncul)
$users_list = $conn->query("SELECT users.*, companies.company_name, companies.level 
                            FROM users 
                            LEFT JOIN companies ON users.company_id = companies.id 
                            ORDER BY users.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - IoT Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                fontFamily: { sans: ['Inter', 'sans-serif'] },
                extend: { colors: { primary: '#4F46E5', dark: '#1A222C', darkcard: '#24303F', darktext: '#AEB7C0' } }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-dark text-slate-600 dark:text-darktext antialiased overflow-hidden">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        <div id="main-content" class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 relative z-10 py-8">
                <div class="mx-auto max-w-screen-2xl p-4 md:p-6 2xl:p-10">
                    
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">User Management</h2>

                    <?php if($success): ?>
                        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400" role="alert">
                            <span class="font-medium">Success!</span> <?= $success ?>
                        </div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert">
                            <span class="font-medium">Error!</span> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <div class="rounded-xl bg-white dark:bg-darkcard p-6 shadow-soft dark:shadow-none mb-8 border border-slate-100 dark:border-slate-800">
                        <div class="mb-4 border-b border-slate-100 dark:border-slate-700 pb-4">
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white">Create New User</h3>
                            <p class="text-sm text-slate-500">Assign user to a company to set their data visibility scope.</p>
                        </div>

                        <form method="POST">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Username / Email</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                                            <i class="ph ph-user text-slate-400"></i>
                                        </div>
                                        <input type="text" name="username" required class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full ps-10 p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:text-white" placeholder="user@example.com">
                                    </div>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Password</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none">
                                            <i class="ph ph-lock-key text-slate-400"></i>
                                        </div>
                                        <input type="text" name="password" required class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full ps-10 p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:text-white" placeholder="••••••••">
                                    </div>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">User Role</label>
                                    <select name="role" class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                        <option value="user">User (Viewer)</option>
                                        <option value="sub-admin">Sub-Admin</option>
                                        <option value="admin">Admin</option>
                                        <option value="superadmin">Superadmin</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Assign Company</label>
                                    <select name="company_id" required class="bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                        <option value="">-- Select Company Scope --</option>
                                        <?php 
                                        // Reset pointer data companies
                                        $companies->data_seek(0); 
                                        while($row = $companies->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $row['id'] ?>">
                                                <?= $row['company_name'] ?> (Level <?= $row['level'] ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-400">
                                        *If assigned to <b>Level 1</b>, user can view all child companies.
                                    </p>
                                </div>
                            </div>
                            <button type="submit" name="add_user" class="mt-6 text-white bg-primary hover:bg-indigo-700 focus:ring-4 focus:outline-none focus:ring-indigo-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-indigo-600 dark:hover:bg-indigo-700 dark:focus:ring-indigo-800">
                                <i class="ph ph-plus-circle mr-1"></i> Add User
                            </button>
                        </form>
                    </div>

                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg bg-white dark:bg-darkcard border border-slate-100 dark:border-slate-800">
                        <table class="w-full text-sm text-left text-slate-500 dark:text-slate-400">
                            <thead class="text-xs text-slate-700 uppercase bg-slate-50 dark:bg-slate-700 dark:text-slate-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Username</th>
                                    <th scope="col" class="px-6 py-3">Role</th>
                                    <th scope="col" class="px-6 py-3">Assigned Company</th>
                                    <th scope="col" class="px-6 py-3">Visibility Level</th>
                                    <th scope="col" class="px-6 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = $users_list->fetch_assoc()): ?>
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-600">
                                    <th scope="row" class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-300">
                                                <i class="ph ph-user"></i>
                                            </div>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </div>
                                    </th>
                                    <td class="px-6 py-4">
                                        <?php 
                                            $roleColor = match($user['role']) {
                                                'superadmin' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                                                'admin' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                                'sub-admin' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300',
                                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                            };
                                        ?>
                                        <span class="<?= $roleColor ?> text-xs font-medium px-2.5 py-0.5 rounded border border-transparent">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-slate-700 dark:text-slate-300">
                                        <?= $user['company_name'] ? htmlspecialchars($user['company_name']) : '<span class="text-red-400">Unassigned</span>' ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($user['level']): ?>
                                            <span class="bg-indigo-50 text-indigo-600 text-xs px-2 py-1 rounded-md border border-indigo-100 dark:bg-indigo-900/50 dark:text-indigo-300 dark:border-indigo-800">
                                                Level <?= $user['level'] ?> Access
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="manage-users.php?delete_id=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="font-medium text-red-600 dark:text-red-500 hover:underline">
                                            <i class="ph ph-trash text-lg"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>