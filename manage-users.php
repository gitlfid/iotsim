<?php 
include 'config.php';
checkLogin();

// --- ACCESS CONTROL LEVEL 1: Halaman ini hanya untuk Superadmin & Admin ---
if ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

// Helper: Ambil Scope Perusahaan User yang Sedang Login
$currentUserScope = [];
if ($_SESSION['role'] !== 'superadmin') {
    $currentUserScope = getClientIdsForUser($_SESSION['user_id']); // Mengembalikan Array ID atau 'ALL'
    // Jika user admin tapi tidak punya company, dia tidak bisa lihat apa-apa
    if (empty($currentUserScope) || $currentUserScope === 'NONE') {
        $currentUserScope = []; 
    }
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. ADD / EDIT USER
    if (isset($_POST['action']) && ($_POST['action'] == 'add' || $_POST['action'] == 'edit')) {
        $username = $_POST['username'];
        $email = $_POST['email']; 
        $role = $_POST['role'];
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
        
        // Security: Admin tidak boleh bikin Superadmin
        if ($_SESSION['role'] !== 'superadmin' && $role === 'superadmin') {
            header("Location: manage-users.php?msg=Error: Unauthorized role creation&type=error"); exit;
        }

        // Security: Hanya Superadmin boleh set Global Access
        $access_all = (isset($_POST['access_all']) && $_SESSION['role'] === 'superadmin') ? 1 : 0;
        
        // Ambil Company IDs dari form
        $company_ids = ($access_all == 0 && isset($_POST['company_ids'])) ? $_POST['company_ids'] : [];

        // Security: Admin hanya boleh assign ke company miliknya
        if ($_SESSION['role'] !== 'superadmin') {
            // Filter company_ids agar hanya berisi ID yang diizinkan untuk Admin ini
            $company_ids = array_intersect($company_ids, $currentUserScope);
            
            // Jika admin mencoba membuat user tanpa company (atau company di luar haknya)
            if (empty($company_ids) && $access_all == 0) {
                header("Location: manage-users.php?msg=Error: You must assign at least one valid company&type=error"); exit;
            }
        }

        if ($_POST['action'] == 'add') {
            // Check Duplicate
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                header("Location: manage-users.php?msg=Error: Username or Email already exists&type=error"); exit;
            }

            // AUTO GENERATE PASSWORD
            $plain_password = !empty($_POST['password']) ? $_POST['password'] : generateStrongPassword(8);
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
            
            // Set force_reset = 1
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active, access_all_companies, force_reset) VALUES (?, ?, ?, ?, 1, ?, 1)");
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $access_all);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                
                // Assign Company
                if($access_all == 0 && !empty($company_ids)){
                    $stmt_comp = $conn->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
                    foreach($company_ids as $cid){
                        $stmt_comp->bind_param("ii", $new_user_id, $cid);
                        $stmt_comp->execute();
                    }
                }

                // --- SEND EMAIL ---
                $subject = "Welcome to IoT Platform - Account Credentials";
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #4F46E5;'>Welcome, $username!</h2>
                    <p>Your account has been created successfully.</p>
                    <div style='background-color: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Username:</strong> $username</p>
                        <p style='margin: 5px 0;'><strong>Email:</strong> $email</p>
                        <p style='margin: 5px 0;'><strong>Password:</strong> <span style='font-family: monospace; background: #e0e7ff; color: #4338ca; padding: 2px 6px; rounded: 4px;'>$plain_password</span></p>
                        <p style='margin: 5px 0;'><strong>Role:</strong> ".ucfirst($role)."</p>
                    </div>
                    <p>Please login and change your password immediately.</p>
                </div>";

                $mailRes = sendEmail($email, $subject, $body);
                $mailMsg = $mailRes['status'] ? "Email sent." : "Email failed: " . $mailRes['msg'];

                header("Location: manage-users.php?msg=User created. $mailMsg&type=success"); exit;
            }
        } 
        else if ($_POST['action'] == 'edit') {
            // Security: Admin tidak boleh edit Superadmin atau user di luar scope
            // (Validasi tambahan bisa ditaruh disini, tapi query list user di bawah sudah membatasi tampilan)

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

    // 2. RESET PASSWORD
    if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $uid = $_POST['user_id'];
        
        $qUser = $conn->query("SELECT username, email FROM users WHERE id='$uid'");
        if ($qUser->num_rows > 0) {
            $uData = $qUser->fetch_assoc();
            $email = $uData['email'];
            $username = $uData['username'];

            $new_password = generateStrongPassword(10); 
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $conn->query("UPDATE users SET password='$hashed_password', force_reset=1 WHERE id='$uid'");

            $subject = "Password Reset - IoT Platform";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #F59E0B;'>Password Reset</h2>
                <p>Hello <strong>$username</strong>,</p>
                <p>Your password has been reset by Administrator.</p>
                <div style='background-color: #FFFBEB; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #FEF3C7;'>
                    <p style='margin: 5px 0; color: #92400E;'><strong>New Password:</strong></p>
                    <p style='margin: 5px 0; font-size: 18px; font-family: monospace; font-weight: bold; color: #D97706;'>$new_password</p>
                </div>
                <p>Use this password to login. You will be asked to create a new password immediately.</p>
            </div>";

            $mailRes = sendEmail($email, $subject, $body);
            $msgStatus = $mailRes['status'] ? "New password sent to email." : "Password reset but email failed: ".$mailRes['msg'];
            
            header("Location: manage-users.php?msg=$msgStatus&type=success"); exit;
        } else {
            header("Location: manage-users.php?msg=User not found&type=error"); exit;
        }
    }

    // 3. TOGGLE SUSPEND
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
        $uid = $_POST['user_id'];
        $status = $_POST['status']; 
        $conn->query("UPDATE users SET is_active = $status WHERE id = $uid");
        echo json_encode(['status'=>'success']); exit;
    }

    // 4. DELETE USER
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $uid = $_POST['user_id'];
        if ($uid != $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id = $uid");
            $conn->query("DELETE FROM user_companies WHERE user_id = $uid");
        }
        header("Location: manage-users.php?msg=User deleted successfully&type=success"); exit;
    }
}

// --- FETCH DATA (LOGIC FILTER ROLE) ---

// 1. Fetch Companies Tree (Filtered by Scope)
$raw_companies = [];
if ($_SESSION['role'] === 'superadmin') {
    // Superadmin lihat semua company
    $res = $conn->query("SELECT id, company_name, level, parent_id FROM companies ORDER BY company_name ASC");
} else {
    // Admin hanya lihat company miliknya & turunannya
    if (!empty($currentUserScope)) {
        $ids_str = implode(',', $currentUserScope);
        $res = $conn->query("SELECT id, company_name, level, parent_id FROM companies WHERE id IN ($ids_str) ORDER BY company_name ASC");
    } else {
        $res = false; // No companies available
    }
}

if ($res) {
    while($r = $res->fetch_assoc()) {
        $raw_companies[$r['id']] = $r;
        $raw_companies[$r['id']]['children'] = [];
    }
}

$tree = [];
// Build tree from flat array
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

// 2. Fetch Users List (Filtered by Role)
$users = [];

if ($_SESSION['role'] === 'superadmin') {
    // Superadmin: Lihat SEMUA user
    $q = $conn->query("SELECT * FROM users ORDER BY id DESC");
} else {
    // Admin: Lihat user yang terhubung dengan company scope dia
    if (!empty($currentUserScope)) {
        $ids_str = implode(',', $currentUserScope);
        // Query untuk ambil user yang punya relasi dengan company di scope admin
        // DAN user tersebut BUKAN superadmin
        $q = $conn->query("SELECT DISTINCT u.* FROM users u 
                           JOIN user_companies uc ON u.id = uc.user_id 
                           WHERE uc.company_id IN ($ids_str) 
                           AND u.role != 'superadmin' 
                           ORDER BY u.id DESC");
    } else {
        $q = false;
    }
}

if ($q) {
    while($u = $q->fetch_assoc()) {
        $assigned_details = [];
        $total_access_count = 0;

        if ($u['access_all_companies'] == 1 || $u['role'] == 'superadmin') {
            $total_access_count = "All"; 
        } else {
            $uc = $conn->query("SELECT c.id, c.company_name, c.level FROM user_companies uc JOIN companies c ON uc.company_id = c.id WHERE uc.user_id = " . $u['id'] . " ORDER BY c.level ASC");
            while($c = $uc->fetch_assoc()) {
                $assigned_details[] = ['name' => $c['company_name'], 'level' => $c['level'], 'id' => $c['id']]; // Need ID for edit mapping
            }
            
            // Hitung total akses (termasuk turunan) untuk user ini
            $u_scope = getClientIdsForUser($u['id']);
            $total_access_count = is_array($u_scope) ? count($u_scope) : 0;
        }
        
        $u['assigned_details'] = $assigned_details;
        $u['total_access_count'] = $total_access_count;
        $users[] = $u;
    }
}

function getLevelBadge($lvl) {
    switch($lvl) {
        case 1: return "bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20";
        case 2: return "bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20";
        case 3: return "bg-teal-50 text-teal-700 border-teal-100 dark:bg-teal-500/10 dark:text-teal-400 dark:border-teal-500/20";
        default: return "bg-slate-50 text-slate-700 border-slate-100 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-700";
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
            theme: {
                fontFamily: { sans: ['Inter', 'sans-serif'] },
                extend: { colors: { primary: '#4F46E5', darkcard: '#24303F', darkbg: '#1A222C' } }
            }
        }
    </script>
    <style>
        .comp-check:checked + div { background-color: #EEF2FF; border-color: #4F46E5; }
        .dark .comp-check:checked + div { background-color: #312E81; border-color: #6366F1; }
        .list-disabled { opacity: 0.5; pointer-events: none; filter: grayscale(1); }
        .modal-anim { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
                <div class="max-w-7xl mx-auto">
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">User Management</h1>
                            <p class="text-sm text-slate-500 mt-1">Manage system access, roles, and automated notifications.</p>
                        </div>
                        <button onclick="openModal('add')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/20 flex items-center gap-2 transition-all active:scale-95 font-medium border border-transparent">
                            <i class="ph ph-user-plus text-lg"></i>
                            <span>Add New User</span>
                        </button>
                    </div>

                    <?php if(isset($_GET['msg'])): $isError = (isset($_GET['type']) && $_GET['type']=='error'); ?>
                        <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 <?= $isError ? 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800' ?> animate-fade-in-up">
                            <div class="p-2 bg-white dark:bg-darkcard rounded-full shadow-sm">
                                <i class="ph <?= $isError ? 'ph-warning' : 'ph-check-circle' ?> text-xl"></i> 
                            </div>
                            <span class="font-medium text-sm"><?= htmlspecialchars($_GET['msg']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">User Details</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Role</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-[35%]">Assigned Scope</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Total Access</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Status</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <?php if(empty($users)): ?>
                                        <tr><td colspan="6" class="p-8 text-center text-slate-400 italic">No users found under your access scope.</td></tr>
                                    <?php endif; ?>

                                    <?php foreach($users as $user): 
                                        $roleColors = [
                                            'superadmin' => 'bg-purple-50 text-purple-700 border-purple-100 dark:bg-purple-500/10 dark:text-purple-400 dark:border-purple-500/20',
                                            'admin' => 'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
                                            'sub-admin' => 'bg-cyan-50 text-cyan-700 border-cyan-100 dark:bg-cyan-500/10 dark:text-cyan-400 dark:border-cyan-500/20',
                                            'user' => 'bg-slate-50 text-slate-700 border-slate-100 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-700'
                                        ];
                                        $badge = $roleColors[$user['role']] ?? $roleColors['user'];
                                        
                                        // Scope Display
                                        if ($user['access_all_companies'] == 1) {
                                            $scopeDisplay = '
                                            <div class="flex items-center gap-2.5 p-2 rounded-lg bg-amber-50 border border-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20 w-fit">
                                                <i class="ph ph-globe-hemisphere-west text-lg"></i>
                                                <div class="leading-tight">
                                                    <span class="block text-xs font-bold uppercase tracking-wide">Global Access</span>
                                                </div>
                                            </div>';
                                        } else {
                                            $count = count($user['assigned_details']);
                                            if ($count == 0) {
                                                $scopeDisplay = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-red-50 text-red-600 text-xs font-medium border border-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800"><i class="ph ph-warning"></i> Unassigned</span>';
                                            } else {
                                                $listHTML = '<div class="flex flex-wrap gap-1.5">';
                                                foreach($user['assigned_details'] as $comp) {
                                                    $lvlClass = getLevelBadge($comp['level']);
                                                    $listHTML .= '
                                                    <div class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 pl-2 pr-1 py-0.5 shadow-sm">
                                                        <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300">'.$comp['name'].'</span>
                                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase tracking-wider '.$lvlClass.'">Lvl '.$comp['level'].'</span>
                                                    </div>';
                                                }
                                                $listHTML .= '</div>';
                                                $scopeDisplay = $listHTML;
                                            }
                                        }
                                        $isActive = $user['is_active'];
                                        $initials = strtoupper(substr($user['username'], 0, 2));
                                    ?>
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors group">
                                        <td class="px-6 py-4 align-middle">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-50 to-slate-100 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center text-primary font-bold text-sm shadow-inner border border-slate-200 dark:border-slate-600">
                                                    <?= $initials ?>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-800 dark:text-white text-sm"><?= $user['username'] ?></p>
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5"><?= $user['email'] ?? '-' ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 align-middle">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide border shadow-sm <?= $badge ?>">
                                                <?= $user['role'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 align-middle">
                                            <?= $scopeDisplay ?>
                                        </td>
                                        <td class="px-6 py-4 align-middle text-center">
                                            <div class="inline-flex flex-col items-center justify-center">
                                                <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                                                    <?= $user['total_access_count'] ?>
                                                </span>
                                                <span class="text-[10px] text-slate-400 uppercase">Companies</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 align-middle text-center">
                                            <label class="relative inline-flex items-center cursor-pointer group-hover:scale-105 transition-transform">
                                                <input type="checkbox" class="sr-only peer" onchange="toggleStatus(<?= $user['id'] ?>, this)" <?= $isActive ? 'checked' : '' ?>>
                                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500 shadow-sm"></div>
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 align-middle text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                
                                                <button onclick='openEdit(<?= json_encode($user) ?>)' class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-500/10 dark:hover:text-indigo-400 transition-all" title="Edit User">
                                                    <i class="ph ph-pencil-simple text-lg"></i>
                                                </button>

                                                <form method="POST" onsubmit="return confirm('Reset password for <?= $user['username'] ?>?');" class="inline-block">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10 dark:hover:text-amber-400 transition-all" title="Reset Password">
                                                        <i class="ph ph-key text-lg"></i>
                                                    </button>
                                                </form>

                                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" onsubmit="return confirm('Delete this user?');" class="inline-block">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400 transition-all" title="Delete User">
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

    <div id="userModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeModal()"></div>
        
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-darkcard w-full max-w-lg rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim flex flex-col max-h-[90vh] relative z-10" id="modalContent">
                
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-white dark:bg-darkcard rounded-t-2xl">
                    <div>
                        <h3 id="modalTitle" class="text-lg font-bold text-slate-800 dark:text-white">Add New User</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Configure access and credentials.</p>
                    </div>
                    <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>
                
                <div class="overflow-y-auto p-6 custom-scrollbar">
                    <form method="POST" id="userForm">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="user_id" id="userId">

                        <div class="space-y-5">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Username</label>
                                    <div class="relative">
                                        <i class="ph ph-user absolute left-3 top-3 text-slate-400"></i>
                                        <input type="text" name="username" id="inputUsername" required class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:text-white" placeholder="john_doe">
                                    </div>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Role</label>
                                    <div class="relative">
                                        <select name="role" id="inputRole" class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none cursor-pointer appearance-none dark:text-white">
                                            <option value="user">User</option>
                                            <option value="sub-admin">Sub-Admin</option>
                                            <option value="admin">Admin</option>
                                            <?php if($_SESSION['role'] === 'superadmin'): ?>
                                            <option value="superadmin">Superadmin</option>
                                            <?php endif; ?>
                                        </select>
                                        <i class="ph ph-caret-down absolute right-3 top-3 text-slate-400 pointer-events-none"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Email Address</label>
                                <div class="relative">
                                    <i class="ph ph-envelope absolute left-3 top-3 text-slate-400"></i>
                                    <input type="email" name="email" id="inputEmail" required class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:text-white" placeholder="email@company.com">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Password</label>
                                <div class="relative">
                                    <i class="ph ph-lock-key absolute left-3 top-3 text-slate-400"></i>
                                    <input type="password" name="password" id="inputPassword" class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:text-white" placeholder="Auto-generated if empty">
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1.5 flex items-center gap-1.5">
                                    <i class="ph ph-info text-primary"></i> 
                                    Leave empty to generate a secure password automatically.
                                </p>
                            </div>

                            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-3">Data Access Scope</label>
                                
                                <?php if($_SESSION['role'] === 'superadmin'): ?>
                                <label class="flex items-start p-3 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800/50 cursor-pointer mb-3 transition-all hover:shadow-sm hover:border-amber-300">
                                    <div class="flex h-5 items-center">
                                        <input type="checkbox" name="access_all" id="checkAccessAll" class="w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500" onchange="toggleCompanyList(this)">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="block font-bold text-amber-800 dark:text-amber-500">Global Access (All Companies)</span>
                                        <span class="block text-xs text-amber-700/70 dark:text-amber-500/70 mt-0.5">User can view hierarchy tree of all companies.</span>
                                    </div>
                                </label>
                                <?php endif; ?>

                                <div id="specificCompanyList" class="transition-all duration-300">
                                    <div class="max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl p-1 bg-slate-50 dark:bg-slate-800/50 space-y-0.5 custom-scrollbar">
                                        <?php 
                                        if (!empty($companies)) {
                                            foreach($companies as $c): 
                                                $lvlBadge = getLevelBadge($c['level']);
                                                $indent = $c['depth'] * 24; 
                                                $connector = ($c['depth'] > 0) ? '<i class="ph ph-arrow-elbow-down-right text-slate-300 mr-2"></i>' : '';
                                        ?>
                                        <label class="cursor-pointer relative group block">
                                            <input type="checkbox" name="company_ids[]" value="<?= $c['id'] ?>" class="comp-check sr-only peer">
                                            
                                            <div class="px-3 py-2 rounded-lg border border-transparent text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm transition-all peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-checked:border-indigo-100 dark:peer-checked:bg-indigo-900/30 dark:peer-checked:text-indigo-300 dark:peer-checked:border-indigo-800 flex items-center justify-between" style="padding-left: <?= ($c['depth'] > 0) ? $indent : '12' ?>px">
                                                <div class="flex items-center">
                                                    <?= $connector ?>
                                                    <span><?= $c['company_name'] ?></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[9px] px-1.5 py-0.5 rounded border font-bold uppercase tracking-wider <?= $lvlBadge ?>">Lvl <?= $c['level'] ?></span>
                                                    <i class="ph ph-check-circle-fill opacity-0 peer-checked:opacity-100 text-indigo-600 dark:text-indigo-400 transition-all scale-0 peer-checked:scale-100"></i>
                                                </div>
                                            </div>
                                        </label>
                                        <?php 
                                            endforeach; 
                                        } else {
                                            echo '<div class="p-4 text-center text-xs text-slate-400 italic">No companies available for assignment.</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-sm">Cancel</button>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-500/30 transition-all active:scale-95 text-sm flex items-center gap-2">
                                <i class="ph ph-paper-plane-right"></i> Save & Send Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const modalBackdrop = document.getElementById('modalBackdrop');
        const modalContent = document.getElementById('modalContent');
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
                modalBackdrop.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            if(mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Add New User';
                document.getElementById('formAction').value = 'add';
                document.getElementById('userForm').reset();
                document.getElementById('userId').value = '';
                document.getElementById('inputPassword').required = false;
                
                if(checkAll) { checkAll.checked = false; toggleCompanyList(checkAll); }
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

            if(checkAll) {
                checkAll.checked = (user.access_all_companies == 1);
                toggleCompanyList(checkAll);
            }

            document.querySelectorAll('.comp-check').forEach(cb => cb.checked = false);
            if(user.assigned_details && user.assigned_details.length > 0) {
                user.assigned_details.forEach(item => {
                    const cb = document.querySelector(`.comp-check[value="${item.id}"]`);
                    if(cb) cb.checked = true;
                });
            }
        }

        function closeModal() {
            modalBackdrop.classList.add('opacity-0');
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300); 
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