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
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
        
        // Logika Access All
        $access_all = isset($_POST['access_all']) ? 1 : 0;
        
        // Jika Access All dicentang, abaikan company_ids. Jika tidak, ambil array-nya.
        $company_ids = ($access_all == 0 && isset($_POST['company_ids'])) ? $_POST['company_ids'] : [];

        if ($_POST['action'] == 'add') {
            // Check Duplicate
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                header("Location: manage-users.php?msg=Error: Username already exists"); exit;
            }

            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Insert User
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_active, access_all_companies) VALUES (?, ?, ?, 1, ?)");
            $stmt->bind_param("sssi", $username, $password, $role, $access_all);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                
                // Hanya insert ke user_companies jika TIDAK access all
                if($access_all == 0 && !empty($company_ids)){
                    $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                    foreach($company_ids as $cid){
                        $stmt_comp->bind_param("ii", $new_user_id, $cid);
                        $stmt_comp->execute();
                    }
                }
                header("Location: manage-users.php?msg=User created successfully"); exit;
            }
        } 
        else if ($_POST['action'] == 'edit') {
            // Update Basic Info & Access All Flag
            $stmt = $conn->prepare("UPDATE users SET username=?, role=?, access_all_companies=? WHERE id=?");
            $stmt->bind_param("ssii", $username, $role, $access_all, $user_id);
            $stmt->execute();

            // Update Password if provided
            if (!empty($_POST['password'])) {
                $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $new_pass, $user_id);
                $stmt->execute();
            }

            // Update Companies Relation
            // Selalu hapus dulu relasi lama
            $conn->query("DELETE FROM user_companies WHERE user_id = $user_id");
            
            // Insert baru HANYA jika access_all == 0
            if($access_all == 0 && !empty($company_ids)){
                $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                foreach($company_ids as $cid){
                    $stmt_comp->bind_param("ii", $user_id, $cid);
                    $stmt_comp->execute();
                }
            }
            header("Location: manage-users.php?msg=User updated successfully"); exit;
        }
    }

    // 2. TOGGLE SUSPEND (Active/Inactive)
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $uid = $_POST['user_id'];
        $status = $_POST['status']; // 1 or 0
        $conn->query("UPDATE users SET is_active = $status WHERE id = $uid");
        echo json_encode(['status'=>'success']); exit;
    }

    // 3. DELETE USER
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $uid = $_POST['user_id'];
        if ($uid != $_SESSION['user_id']) { // Prevent self-delete
            $conn->query("DELETE FROM users WHERE id = $uid");
            $conn->query("DELETE FROM user_companies WHERE user_id = $uid");
        }
        header("Location: manage-users.php?msg=User deleted successfully"); exit;
    }
}

// --- FETCH DATA ---
$companies = [];
$res = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
while($r = $res->fetch_assoc()) $companies[] = $r;

// Get Users Data
$users = [];
$q = $conn->query("SELECT * FROM users ORDER BY id DESC");
while($u = $q->fetch_assoc()) {
    // Check specific companies only if NOT access_all
    $assigned = [];
    $assigned_ids = [];
    
    if ($u['access_all_companies'] == 0) {
        $uc = $conn->query("SELECT c.id, c.company_name FROM user_companies uc JOIN companies c ON uc.company_id = c.id WHERE uc.user_id = " . $u['id']);
        while($c = $uc->fetch_assoc()) {
            $assigned[] = $c['company_name'];
            $assigned_ids[] = $c['id'];
        }
    }
    
    $u['assigned_companies'] = $assigned;
    $u['assigned_ids'] = $assigned_ids;
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
        .comp-check:checked + div { background-color: #EEF2FF; border-color: #4F46E5; color: #4F46E5; }
        .dark .comp-check:checked + div { background-color: #312E81; border-color: #6366F1; color: #C7D2FE; }
        
        /* Disabled state for list when 'All' is checked */
        .list-disabled { opacity: 0.5; pointer-events: none; filter: grayscale(1); }
        
        .modal-anim { transition: all 0.3s ease-out; }
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
                            <p class="text-sm text-slate-500 mt-1">Manage system access, roles, and company scope.</p>
                        </div>
                        <button onclick="openModal('add')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 flex items-center gap-2 transition-all active:scale-95 font-medium">
                            <i class="ph ph-user-plus text-lg"></i> Add New User
                        </button>
                    </div>

                    <?php if(isset($_GET['msg'])): ?>
                        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-xl border border-green-200 dark:border-green-800 flex items-center gap-2 animate-pulse">
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
                            
                            // Tentukan Tampilan Scope
                            if ($user['access_all_companies'] == 1) {
                                $scopeDisplay = '<span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 text-xs font-bold border border-amber-200 dark:border-amber-800">ALL COMPANIES</span>';
                                $companyText = "Can access all registered companies.";
                            } else {
                                $count = count($user['assigned_companies']);
                                if ($count == 0) {
                                    $scopeDisplay = '<span class="text-red-400 text-xs italic">Unassigned</span>';
                                    $companyText = "No specific company assigned.";
                                } else {
                                    $scopeDisplay = '<span class="text-xs font-bold text-slate-500">'.$count.' Selected</span>';
                                    $companyText = implode(', ', $user['assigned_companies']);
                                }
                            }
                            
                            $isActive = $user['is_active'];
                        ?>
                        <div class="bg-white dark:bg-darkcard rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 hover:shadow-md transition-all relative group flex flex-col h-full">
                            
                            <div class="absolute top-6 right-6" title="<?= $isActive ? 'Active' : 'Suspended' ?>">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="sr-only peer" onchange="toggleStatus(<?= $user['id'] ?>, this)" <?= $isActive ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>

                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xl font-bold text-slate-600 dark:text-slate-400 uppercase">
                                    <?= substr($user['username'], 0, 1) ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white text-lg truncate max-w-[150px]" title="<?= $user['username'] ?>"><?= $user['username'] ?></h3>
                                    <span class="inline-block mt-1 px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide <?= $badge ?>">
                                        <?= $user['role'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-6 flex-1">
                                <div class="flex justify-between items-end mb-1">
                                    <p class="text-xs font-bold text-slate-400 uppercase">Access Scope</p>
                                    <?= $scopeDisplay ?>
                                </div>
                                <div class="p-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700 h-16 overflow-hidden">
                                    <div class="flex items-start gap-2">
                                        <i class="ph ph-buildings text-slate-400 mt-0.5 flex-shrink-0"></i>
                                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 line-clamp-3 leading-relaxed">
                                            <?= $companyText ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2 pt-4 border-t border-slate-100 dark:border-slate-800 mt-auto">
                                <button onclick='openEdit(<?= json_encode($user) ?>)' class="flex-1 py-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-sm font-bold transition-colors dark:bg-indigo-900/20 dark:hover:bg-indigo-900/40 dark:text-indigo-400 flex items-center justify-center gap-2">
                                    <i class="ph ph-pencil-simple"></i> Edit
                                </button>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('Delete this user?');" class="flex-shrink-0">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="w-10 h-full flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors dark:bg-red-900/20 dark:hover:bg-red-900/40 dark:text-red-400" title="Delete User">
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

    <div id="userModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-darkcard w-full max-w-lg rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim flex flex-col max-h-[90vh]">
            
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-white dark:bg-darkcard rounded-t-2xl z-10">
                <div>
                    <h3 id="modalTitle" class="text-xl font-bold text-slate-800 dark:text-white">Add New User</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Configure access and permissions.</p>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>
            
            <div class="overflow-y-auto p-6">
                <form method="POST" id="userForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="user_id" id="userId">

                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Username</label>
                            <div class="relative">
                                <i class="ph ph-user absolute left-3.5 top-3 text-slate-400 text-lg"></i>
                                <input type="text" name="username" id="inputUsername" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-primary outline-none dark:text-white transition-all shadow-sm" placeholder="e.g. john_doe">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Password</label>
                            <div class="relative">
                                <i class="ph ph-lock-key absolute left-3.5 top-3 text-slate-400 text-lg"></i>
                                <input type="password" name="password" id="inputPassword" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-primary outline-none dark:text-white transition-all shadow-sm" placeholder="••••••••">
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1" id="passHelp">Required for new user.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Role Permissions</label>
                            <div class="relative">
                                <i class="ph ph-shield-check absolute left-3.5 top-3 text-slate-400 text-lg"></i>
                                <select name="role" id="inputRole" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary focus:border-primary outline-none dark:text-white cursor-pointer shadow-sm appearance-none">
                                    <option value="user">User (Viewer Only)</option>
                                    <option value="sub-admin">Sub-Admin</option>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Superadmin</option>
                                </select>
                                <i class="ph ph-caret-down absolute right-4 top-3.5 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-3">Company Access Scope</label>
                            
                            <label class="flex items-center p-3 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800/50 cursor-pointer mb-3 transition-colors hover:bg-amber-100 dark:hover:bg-amber-900/20">
                                <input type="checkbox" name="access_all" id="checkAccessAll" class="w-5 h-5 text-amber-600 rounded focus:ring-amber-500 border-gray-300" onchange="toggleCompanyList(this)">
                                <div class="ml-3">
                                    <span class="block text-sm font-bold text-amber-800 dark:text-amber-500">Access All Companies</span>
                                    <span class="block text-xs text-amber-600/80 dark:text-amber-500/70">User can view data from ANY company.</span>
                                </div>
                            </label>

                            <div id="specificCompanyList" class="transition-all duration-300">
                                <p class="text-xs text-slate-400 mb-2">Or select specific companies:</p>
                                <div class="max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl p-2 bg-slate-50 dark:bg-slate-800/50 grid grid-cols-1 gap-1">
                                    <?php foreach($companies as $c): ?>
                                    <label class="cursor-pointer relative group">
                                        <input type="checkbox" name="company_ids[]" value="<?= $c['id'] ?>" class="comp-check sr-only peer">
                                        <div class="px-3 py-2.5 rounded-lg border border-transparent text-sm font-medium text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-800 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all peer-checked:bg-indigo-50 peer-checked:text-indigo-600 peer-checked:border-indigo-200 dark:peer-checked:bg-indigo-900/30 dark:peer-checked:text-indigo-300 dark:peer-checked:border-indigo-800 flex items-center justify-between shadow-sm">
                                            <?= $c['company_name'] ?>
                                            <i class="ph ph-check-circle hidden peer-checked:block text-lg text-primary"></i>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-500/30 transition-all active:scale-95">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const modalBox = modal.querySelector('.modal-anim');
        const listContainer = document.getElementById('specificCompanyList');
        const checkAll = document.getElementById('checkAccessAll');

        function toggleCompanyList(checkbox) {
            if(checkbox.checked) {
                listContainer.classList.add('list-disabled');
                // Optional: Uncheck everything below visually or keep state? Keeping state is safer if they untoggle.
            } else {
                listContainer.classList.remove('list-disabled');
            }
        }

        function openModal(mode) {
            modal.classList.remove('hidden');
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
                
                checkAll.checked = false;
                toggleCompanyList(checkAll);
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

            // Set Access All State
            checkAll.checked = (user.access_all_companies == 1);
            toggleCompanyList(checkAll);

            // Set Specific Companies
            document.querySelectorAll('.comp-check').forEach(cb => cb.checked = false);
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