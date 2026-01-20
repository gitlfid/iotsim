<?php 
include 'config.php';
checkLogin();

// Access Control
if ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. ADD / EDIT USER
    if (isset($_POST['action']) && ($_POST['action'] == 'add' || $_POST['action'] == 'edit')) {
        $username = $_POST['username'];
        $role = $_POST['role'];
        $company_ids = isset($_POST['company_ids']) ? $_POST['company_ids'] : []; // Array
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;

        if ($_POST['action'] == 'add') {
            // Check Duplicate
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                header("Location: manage-users.php?err=Username exists"); exit;
            }

            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Insert User (Default active)
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("sss", $username, $password, $role);
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                // Insert User Companies
                if(!empty($company_ids)){
                    $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                    foreach($company_ids as $cid){
                        $stmt_comp->bind_param("ii", $new_user_id, $cid);
                        $stmt_comp->execute();
                    }
                }
                header("Location: manage-users.php?msg=User created"); exit;
            }
        } 
        else if ($_POST['action'] == 'edit') {
            // Update Basic Info
            $stmt = $conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $role, $user_id);
            $stmt->execute();

            // Update Password if provided
            if (!empty($_POST['password'])) {
                $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $new_pass, $user_id);
                $stmt->execute();
            }

            // Update Companies (Delete old -> Insert new)
            $conn->query("DELETE FROM user_companies WHERE user_id = $user_id");
            if(!empty($company_ids)){
                $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                foreach($company_ids as $cid){
                    $stmt_comp->bind_param("ii", $user_id, $cid);
                    $stmt_comp->execute();
                }
            }
            header("Location: manage-users.php?msg=User updated"); exit;
        }
    }

    // 2. TOGGLE SUSPEND
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $uid = $_POST['user_id'];
        $status = $_POST['status']; // 1 or 0
        $conn->query("UPDATE users SET is_active = $status WHERE id = $uid");
        echo json_encode(['status'=>'success']); exit;
    }

    // 3. DELETE USER
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $uid = $_POST['user_id'];
        if ($uid != $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id = $uid");
            $conn->query("DELETE FROM user_companies WHERE user_id = $uid");
        }
        header("Location: manage-users.php?msg=User deleted"); exit;
    }
}

// --- FETCH DATA ---
$companies = [];
$res = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
while($r = $res->fetch_assoc()) $companies[] = $r;

// Get Users with Their Companies
$users = [];
$q = $conn->query("SELECT * FROM users ORDER BY id DESC");
while($u = $q->fetch_assoc()) {
    // Get assigned companies
    $uc = $conn->query("SELECT c.id, c.company_name FROM user_companies uc JOIN companies c ON uc.company_id = c.id WHERE uc.user_id = " . $u['id']);
    $u['assigned_companies'] = [];
    $u['assigned_ids'] = [];
    while($c = $uc->fetch_assoc()) {
        $u['assigned_companies'][] = $c['company_name'];
        $u['assigned_ids'][] = $c['id'];
    }
    $users[] = $u;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#4F46E5', darkcard: '#24303F' } } }
        }
    </script>
    <style>
        /* Custom Checkbox for Multi Select */
        .comp-check:checked + div { background-color: #EEF2FF; border-color: #4F46E5; color: #4F46E5; }
        .dark .comp-check:checked + div { background-color: #312E81; border-color: #6366F1; color: #C7D2FE; }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-gray-900 text-slate-600 dark:text-slate-300 font-sans">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto">
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">User Management</h1>
                            <p class="text-sm text-slate-500 mt-1">Manage system access, roles, and company assignments.</p>
                        </div>
                        <button onclick="openModal('add')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 flex items-center gap-2 transition-all active:scale-95 font-medium">
                            <i class="ph ph-user-plus text-lg"></i> Add New User
                        </button>
                    </div>

                    <?php if(isset($_GET['msg'])): ?>
                        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-xl border border-green-200 dark:border-green-800 flex items-center gap-2">
                            <i class="ph ph-check-circle text-xl"></i> <?= htmlspecialchars($_GET['msg']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($users as $user): 
                            $roleColors = [
                                'superadmin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
                                'admin' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
                                'sub-admin' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300',
                                'user' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                            ];
                            $badge = $roleColors[$user['role']] ?? $roleColors['user'];
                            $companiesList = implode(', ', $user['assigned_companies']);
                            if(empty($companiesList)) $companiesList = 'No Company Assigned';
                            $isActive = $user['is_active'];
                        ?>
                        <div class="bg-white dark:bg-darkcard rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 hover:shadow-md transition-all relative group">
                            
                            <div class="absolute top-6 right-6">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="sr-only peer" onchange="toggleStatus(<?= $user['id'] ?>, this)" <?= $isActive ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>

                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xl font-bold text-slate-600 dark:text-slate-400">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white text-lg"><?= $user['username'] ?></h3>
                                    <span class="inline-block mt-1 px-2.5 py-0.5 rounded-md text-xs font-bold uppercase <?= $badge ?>">
                                        <?= $user['role'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-6">
                                <p class="text-xs font-bold text-slate-400 uppercase mb-1">Access Scope</p>
                                <div class="flex items-start gap-2">
                                    <i class="ph ph-buildings text-slate-400 mt-0.5"></i>
                                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300 line-clamp-2" title="<?= $companiesList ?>">
                                        <?= $companiesList ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-auto pt-4 border-t border-slate-100 dark:border-slate-800">
                                <button onclick='openEdit(<?= json_encode($user) ?>)' class="flex-1 py-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-sm font-bold transition-colors dark:bg-indigo-900/20 dark:hover:bg-indigo-900/40 dark:text-indigo-400">
                                    Edit Profile
                                </button>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="w-10 h-10 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors dark:bg-red-900/20 dark:hover:bg-red-900/40 dark:text-red-400">
                                        <i class="ph ph-trash text-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <div id="userModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-darkcard w-full max-w-lg rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-bold text-slate-800 dark:text-white">Add New User</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-red-500 transition-colors"><i class="ph ph-x text-2xl"></i></button>
            </div>
            
            <form method="POST" id="userForm" class="p-6">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="user_id" id="userId">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">Username</label>
                        <input type="text" name="username" id="inputUsername" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-primary outline-none dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
                        <input type="password" name="password" id="inputPassword" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-primary outline-none dark:text-white" placeholder="Leave blank to keep current">
                        <p class="text-xs text-slate-400 mt-1" id="passHelp">Required for new user.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5">Role</label>
                        <select name="role" id="inputRole" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-primary outline-none dark:text-white cursor-pointer">
                            <option value="user">User</option>
                            <option value="sub-admin">Sub-Admin</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Assign Companies</label>
                        <div class="max-h-40 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl p-2 bg-slate-50 dark:bg-slate-800/50 grid grid-cols-1 gap-1">
                            <?php foreach($companies as $c): ?>
                            <label class="cursor-pointer relative">
                                <input type="checkbox" name="company_ids[]" value="<?= $c['id'] ?>" class="comp-check sr-only peer">
                                <div class="px-3 py-2 rounded-lg border border-transparent text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 transition-all peer-checked:bg-indigo-50 peer-checked:text-indigo-600 peer-checked:border-indigo-200 dark:peer-checked:bg-indigo-900/30 dark:peer-checked:text-indigo-300 flex items-center justify-between">
                                    <?= $c['company_name'] ?>
                                    <i class="ph ph-check-circle hidden peer-checked:block text-lg"></i>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Select multiple companies for access.</p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-500/30 transition-all">Save User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const modalBox = modal.querySelector('div');

        function openModal(mode) {
            modal.classList.remove('hidden');
            // Animation
            setTimeout(() => {
                modalBox.classList.remove('scale-95', 'opacity-0');
                modalBox.classList.add('scale-100', 'opacity-100');
            }, 10);

            if(mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Add New User';
                document.getElementById('formAction').value = 'add';
                document.getElementById('userForm').reset();
                document.getElementById('userId').value = '';
                document.getElementById('inputPassword').required = true;
                document.getElementById('passHelp').innerText = 'Required for new user.';
                
                // Uncheck all
                document.querySelectorAll('.comp-check').forEach(cb => cb.checked = false);
            }
        }

        function openEdit(user) {
            openModal('edit');
            document.getElementById('modalTitle').innerText = 'Edit User: ' + user.username;
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('inputUsername').value = user.username;
            document.getElementById('inputRole').value = user.role;
            document.getElementById('inputPassword').required = false;
            document.getElementById('passHelp').innerText = 'Leave blank to keep current password.';

            // Reset checks first
            document.querySelectorAll('.comp-check').forEach(cb => cb.checked = false);
            
            // Check assigned companies
            if(user.assigned_ids && user.assigned_ids.length > 0) {
                user.assigned_ids.forEach(id => {
                    const cb = document.querySelector(`.comp-check[value="${id}"]`);
                    if(cb) cb.checked = true;
                });
            }
        }

        function closeModal() {
            modalBox.classList.remove('scale-100', 'opacity-100');
            modalBox.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        function toggleStatus(userId, toggle) {
            const status = toggle.checked ? 1 : 0;
            const fd = new FormData();
            fd.append('action', 'toggle_status');
            fd.append('user_id', userId);
            fd.append('status', status);
            fetch('manage-users.php', { method: 'POST', body: fd });
        }
    </script>
</body>
</html>