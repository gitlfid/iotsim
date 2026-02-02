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
        $email = $_POST['email']; // Field Baru
        $role = $_POST['role'];
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
        $access_all = isset($_POST['access_all']) ? 1 : 0;
        $company_ids = ($access_all == 0 && isset($_POST['company_ids'])) ? $_POST['company_ids'] : [];

        if ($_POST['action'] == 'add') {
            // Check Duplicate Username/Email
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                header("Location: manage-users.php?msg=Error: Username or Email already exists&type=error"); exit;
            }

            // AUTO GENERATE PASSWORD
            // Jika kosong, buat password acak. Jika diisi, pakai inputan.
            $plain_password = !empty($_POST['password']) ? $_POST['password'] : generateStrongPassword(8);
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active, access_all_companies) VALUES (?, ?, ?, ?, 1, ?)");
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $access_all);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                
                // Assign Company Scope
                if($access_all == 0 && !empty($company_ids)){
                    $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                    foreach($company_ids as $cid){
                        $stmt_comp->bind_param("ii", $new_user_id, $cid);
                        $stmt_comp->execute();
                    }
                }

                // --- SEND EMAIL NOTIFICATION ---
                $subject = "Welcome to IoT Platform - Account Credentials";
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #4F46E5;'>Welcome, $username!</h2>
                    <p>Your account has been successfully created. You can now access the IoT Platform dashboard.</p>
                    
                    <div style='background-color: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Username:</strong> $username</p>
                        <p style='margin: 5px 0;'><strong>Email:</strong> $email</p>
                        <p style='margin: 5px 0;'><strong>Password:</strong> <span style='font-family: monospace; background: #e0e7ff; color: #4338ca; padding: 2px 6px; rounded: 4px;'>$plain_password</span></p>
                        <p style='margin: 5px 0;'><strong>Role:</strong> " . ucfirst($role) . "</p>
                    </div>

                    <p>Please login and change your password immediately for security reasons.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #6b7280;'>This is an automated message, please do not reply directly.</p>
                </div>
                ";

                $mailRes = sendEmail($email, $subject, $body);
                $mailMsg = $mailRes['status'] ? "Email sent." : "Email failed: " . $mailRes['msg'];

                header("Location: manage-users.php?msg=User created successfully. $mailMsg&type=success"); exit;
            }
        } 
        else if ($_POST['action'] == 'edit') {
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, access_all_companies=? WHERE id=?");
            $stmt->bind_param("sssii", $username, $email, $role, $access_all, $user_id);
            $stmt->execute();

            if (!empty($_POST['password'])) {
                $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $new_pass, $user_id);
                $stmt->execute();
            }

            $conn->query("DELETE FROM user_companies WHERE user_id = $user_id");
            if($access_all == 0 && !empty($company_ids)){
                $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                foreach($company_ids as $cid){
                    $stmt_comp->bind_param("ii", $user_id, $cid);
                    $stmt_comp->execute();
                }
            }
            header("Location: manage-users.php?msg=User updated successfully&type=success"); exit;
        }
    }

    // Toggle Suspend
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $uid = $_POST['user_id'];
        $status = $_POST['status']; 
        $conn->query("UPDATE users SET is_active = $status WHERE id = $uid");
        echo json_encode(['status'=>'success']); exit;
    }

    // Delete User
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $uid = $_POST['user_id'];
        if ($uid != $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id = $uid");
            $conn->query("DELETE FROM user_companies WHERE user_id = $uid");
        }
        header("Location: manage-users.php?msg=User deleted successfully&type=success"); exit;
    }
}

// Fetch Data Logic (Hierarchy)
$raw_companies = [];
$res = $conn->query("SELECT id, company_name, level, parent_id FROM companies ORDER BY company_name ASC");
while($r = $res->fetch_assoc()) {
    $raw_companies[$r['id']] = $r;
    $raw_companies[$r['id']]['children'] = [];
}
$tree = [];
foreach ($raw_companies as $id => &$node) {
    if ($node['parent_id'] && isset($raw_companies[$node['parent_id']])) {
        $raw_companies[$node['parent_id']]['children'][] = &$node;
    } else {
        $tree[] = &$node;
    }
}
unset($node);
$companies = [];
function flattenTree($branch, &$output, $depth = 0) {
    foreach ($branch as $node) {
        $node['depth'] = $depth;
        $output[] = $node;
        if (!empty($node['children'])) flattenTree($node['children'], $output, $depth + 1);
    }
}
flattenTree($tree, $companies); 

// Get Users
$users = [];
$q = $conn->query("SELECT * FROM users ORDER BY id DESC");
while($u = $q->fetch_assoc()) {
    $assigned_details = [];
    if ($u['access_all_companies'] == 0) {
        $uc = $conn->query("SELECT c.id, c.company_name, c.level FROM user_companies uc JOIN companies c ON uc.company_id = c.id WHERE uc.user_id = " . $u['id'] . " ORDER BY c.level ASC");
        while($c = $uc->fetch_assoc()) $assigned_details[] = ['name' => $c['company_name'], 'level' => $c['level'], 'id' => $c['id']];
    }
    $u['assigned_details'] = $assigned_details;
    $users[] = $u;
}

function getLevelBadge($lvl) {
    switch($lvl) {
        case 1: return "bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-900/50 dark:text-indigo-300 dark:border-indigo-800";
        case 2: return "bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/50 dark:text-blue-300 dark:border-blue-800";
        case 3: return "bg-teal-100 text-teal-700 border-teal-200 dark:bg-teal-900/50 dark:text-teal-300 dark:border-teal-800";
        default: return "bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300";
    }
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
        .comp-check:checked + div { background-color: #EEF2FF; border-color: #4F46E5; }
        .dark .comp-check:checked + div { background-color: #312E81; border-color: #6366F1; }
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
                            <p class="text-sm text-slate-500 mt-1">Manage users, access control, and auto-email distribution.</p>
                        </div>
                        <button onclick="openModal('add')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/30 flex items-center gap-2 transition-all active:scale-95 font-medium">
                            <i class="ph ph-user-plus text-lg"></i> Add New User
                        </button>
                    </div>

                    <?php if(isset($_GET['msg'])): $isError = (isset($_GET['type']) && $_GET['type']=='error'); ?>
                        <div class="mb-6 p-4 rounded-xl border flex items-center gap-2 <?= $isError ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200' ?>">
                            <i class="ph <?= $isError ? 'ph-warning' : 'ph-check-circle' ?> text-xl"></i> 
                            <?= htmlspecialchars($_GET['msg']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-darkcard rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead class="bg-slate-50 dark:bg-slate-800 text-xs uppercase font-bold text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-6 py-4">User Details</th>
                                        <th class="px-6 py-4">Role</th>
                                        <th class="px-6 py-4 w-[40%]">Assigned Scope</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <?php foreach($users as $user): 
                                        $roleColors = [
                                            'superadmin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
                                            'admin' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
                                            'sub-admin' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300',
                                            'user' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
                                        ];
                                        $badge = $roleColors[$user['role']] ?? $roleColors['user'];
                                        
                                        if ($user['access_all_companies'] == 1) {
                                            $scopeDisplay = '
                                            <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                                                <i class="ph ph-globe-hemisphere-west text-xl"></i>
                                                <div>
                                                    <span class="block text-xs font-bold uppercase tracking-wide">Global Access</span>
                                                    <span class="text-[11px] opacity-80">Full Hierarchy View</span>
                                                </div>
                                            </div>';
                                        } else {
                                            $count = count($user['assigned_details']);
                                            if ($count == 0) {
                                                $scopeDisplay = '<span class="text-red-400 italic flex items-center gap-1"><i class="ph ph-warning"></i> Unassigned</span>';
                                            } else {
                                                $listHTML = '<div class="flex flex-wrap gap-2">';
                                                foreach($user['assigned_details'] as $comp) {
                                                    $lvlClass = getLevelBadge($comp['level']);
                                                    $listHTML .= '
                                                    <div class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1 shadow-sm">
                                                        <span class="text-xs font-medium text-slate-700 dark:text-slate-300 mr-1">'.$comp['name'].'</span>
                                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border '.$lvlClass.'">Lvl '.$comp['level'].'</span>
                                                    </div>';
                                                }
                                                $listHTML .= '</div>';
                                                $scopeDisplay = $listHTML;
                                            }
                                        }
                                        $isActive = $user['is_active'];
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 font-bold uppercase text-lg shadow-inner">
                                                    <?= substr($user['username'], 0, 1) ?>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-800 dark:text-white"><?= $user['username'] ?></p>
                                                    <p class="text-xs text-slate-400"><?= $user['email'] ?? '-' ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide border border-transparent shadow-sm <?= $badge ?>">
                                                <?= $user['role'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?= $scopeDisplay ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" class="sr-only peer" onchange="toggleStatus(<?= $user['id'] ?>, this)" <?= $isActive ? 'checked' : '' ?>>
                                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick='openEdit(<?= json_encode($user) ?>)' class="p-2 rounded-lg text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-900/30 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors">
                                                    <i class="ph ph-pencil-simple text-lg"></i>
                                                </button>
                                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" onsubmit="return confirm('Delete this user?');" class="inline-block">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:text-slate-400 dark:hover:text-red-400 transition-colors">
                                                        <i class="ph ph-trash text-lg"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                    <p class="text-xs text-slate-500 mt-0.5">Credentials will be emailed automatically.</p>
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
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Username</label>
                                <input type="text" name="username" id="inputUsername" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Role</label>
                                <select name="role" id="inputRole" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary outline-none">
                                    <option value="user">User</option>
                                    <option value="sub-admin">Sub-Admin</option>
                                    <option value="admin">Admin</option>
                                    <option value="superadmin">Superadmin</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Email Address</label>
                            <input type="email" name="email" id="inputEmail" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Password</label>
                            <input type="password" name="password" id="inputPassword" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary outline-none" placeholder="Auto-generated if empty">
                            <p class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                                <i class="ph ph-info"></i> Leave empty to generate secure password automatically.
                            </p>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-3">Company Access</label>
                            
                            <label class="flex items-center p-3 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800/50 cursor-pointer mb-3 transition-colors hover:bg-amber-100 dark:hover:bg-amber-900/20">
                                <input type="checkbox" name="access_all" id="checkAccessAll" class="w-5 h-5 text-amber-600 rounded focus:ring-amber-500 border-gray-300" onchange="toggleCompanyList(this)">
                                <div class="ml-3">
                                    <span class="block text-sm font-bold text-amber-800 dark:text-amber-500">Global Access (All Companies)</span>
                                    <span class="block text-xs text-amber-600/80 dark:text-amber-500/70">User sees all hierarchy.</span>
                                </div>
                            </label>

                            <div id="specificCompanyList" class="transition-all duration-300">
                                <div class="max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl p-2 bg-slate-50 dark:bg-slate-800/50 grid grid-cols-1 gap-1">
                                    <?php foreach($companies as $c): 
                                        $lvlBadge = getLevelBadge($c['level']);
                                        $indent = $c['depth'] * 20; 
                                        $connector = ($c['depth'] > 0) ? '<span class="text-slate-300 mr-2">└─</span>' : '';
                                    ?>
                                    <label class="cursor-pointer relative group">
                                        <input type="checkbox" name="company_ids[]" value="<?= $c['id'] ?>" class="comp-check sr-only peer">
                                        <div class="px-3 py-2.5 rounded-lg border border-transparent text-sm font-medium text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-800 hover:border-indigo-200 dark:hover:border-indigo-800 transition-all peer-checked:bg-indigo-50 peer-checked:text-indigo-600 peer-checked:border-indigo-200 dark:peer-checked:bg-indigo-900/30 dark:peer-checked:text-indigo-300 dark:peer-checked:border-indigo-800 flex items-center justify-between shadow-sm" style="margin-left: <?= $indent ?>px">
                                            <div class="flex items-center">
                                                <?= $connector ?>
                                                <span><?= $c['company_name'] ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] px-1.5 py-0.5 rounded border font-bold <?= $lvlBadge ?>">Lvl <?= $c['level'] ?></span>
                                                <i class="ph ph-check-circle opacity-0 peer-checked:opacity-100 text-indigo-600 dark:text-indigo-400 transition-opacity"></i>
                                            </div>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-500/30 transition-all active:scale-95">Save & Send Email</button>
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
                document.getElementById('inputPassword').required = false;
                
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
            document.getElementById('inputEmail').value = user.email || '';
            document.getElementById('inputRole').value = user.role;
            document.getElementById('inputPassword').required = false;

            checkAll.checked = (user.access_all_companies == 1);
            toggleCompanyList(checkAll);

            document.querySelectorAll('.comp-check').forEach(cb => cb.checked = false);
            if(user.assigned_details && user.assigned_details.length > 0) {
                user.assigned_details.forEach(item => {
                    const cb = document.querySelector(`.comp-check[value="${item.id}"]`);
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