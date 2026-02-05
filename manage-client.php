<?php 
include 'config.php';
checkLogin();

// Hanya Superadmin/Admin
if ($_SESSION['role'] == 'user') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

// --- AJAX HANDLER: GET COMPANY DETAIL & RECURSIVE CHILDREN ---
if (isset($_GET['action']) && $_GET['action'] == 'get_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $cid = intval($_GET['id']);
    
    // 1. Get Company Info + Parent Name
    $sql = "SELECT c.*, p.company_name as parent_name 
            FROM companies c 
            LEFT JOIN companies p ON c.parent_company_id = p.id 
            WHERE c.id = $cid";
    $info = $conn->query($sql)->fetch_assoc();

    // 2. Get Recursive Children
    $allCompanies = [];
    $resAll = $conn->query("SELECT * FROM companies ORDER BY company_name ASC");
    while($row = $resAll->fetch_assoc()) {
        $allCompanies[] = $row;
    }

    function getDescendants($parentId, $sourceArray, &$outputArray, $depth = 0) {
        foreach ($sourceArray as $node) {
            if ($node['parent_company_id'] == $parentId) {
                $node['depth'] = $depth;
                $outputArray[] = $node;
                getDescendants($node['id'], $sourceArray, $outputArray, $depth + 1);
            }
        }
    }

    $hierarchy = [];
    getDescendants($cid, $allCompanies, $hierarchy);

    // 3. Count SIMs
    $simSql = "SELECT COUNT(*) as total, SUM(CASE WHEN status = '1' THEN 1 ELSE 0 END) as active FROM sims WHERE company_id = $cid";
    $simStats = $conn->query($simSql)->fetch_assoc();

    echo json_encode([
        'info' => $info,
        'children' => $hierarchy, 
        'sims' => $simStats
    ]);
    exit();
}

// --- HANDLE ADD COMPANY ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    $name = $_POST['company_name'];
    $code = $_POST['partner_code'];
    $project_name = "-"; 
    
    $pic_name = $_POST['pic_name'] ?? '';
    $pic_email = $_POST['pic_email'] ?? '';
    $pic_phone = $_POST['pic_phone'] ?? '';
    
    if (!empty($_POST['parent_id'])) {
        $parent_id = $_POST['parent_id'];
        $parent_level = $_POST['parent_level'];
        $level = $parent_level + 1; 
    } else {
        $parent_id = NULL;
        $level = 1; 
    }
    
    $stmt = $conn->prepare("INSERT INTO companies (company_name, partner_code, level, parent_company_id, pic_name, pic_email, pic_phone, project_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiissss", $name, $code, $level, $parent_id, $pic_name, $pic_email, $pic_phone, $project_name);
    
    if($stmt->execute()) {
        header("Location: manage-client.php?msg=added"); exit();
    }
}

// --- HANDLE EDIT PIC (SUPERADMIN ONLY) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_company_pic'])) {
    if ($_SESSION['role'] === 'superadmin') {
        $id = $_POST['edit_id'];
        $pic_name = $_POST['edit_pic_name'];
        $pic_email = $_POST['edit_pic_email'];
        $pic_phone = $_POST['edit_pic_phone'];
        
        $stmt = $conn->prepare("UPDATE companies SET pic_name=?, pic_email=?, pic_phone=? WHERE id=?");
        $stmt->bind_param("sssi", $pic_name, $pic_email, $pic_phone, $id);
        
        if($stmt->execute()) {
            header("Location: manage-client.php?msg=updated"); exit();
        }
    } else {
        echo "<script>alert('Unauthorized action.'); window.location='manage-client.php';</script>";
        exit();
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    if($stmt->execute()) {
        header("Location: manage-client.php?msg=deleted"); exit();
    }
}

// --- FETCH DATA ---
$userMap = [];
$sqlUsers = "SELECT uc.company_id, u.username, u.email FROM user_companies uc JOIN users u ON uc.user_id = u.id ORDER BY u.username ASC";
$resUsers = $conn->query($sqlUsers);
while($u = $resUsers->fetch_assoc()){ $userMap[$u['company_id']][] = $u; }

$allComps = $conn->query("SELECT id, parent_company_id FROM companies")->fetch_all(MYSQLI_ASSOC);
$companyChildMap = [];
foreach($allComps as $c) {
    if($c['parent_company_id']) {
        $companyChildMap[$c['parent_company_id']][] = $c['id'];
    }
}

if (!function_exists('countTotalSubs')) {
    function countTotalSubs($parentId, $map) {
        $count = 0;
        if (isset($map[$parentId])) {
            $count += count($map[$parentId]); 
            foreach ($map[$parentId] as $childId) {
                $count += countTotalSubs($childId, $map); 
            }
        }
        return $count;
    }
}

$sql = "SELECT * FROM companies WHERE level = 1 ORDER BY id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Client</title>
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
        .modal-anim { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .hidden-row { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        .dark .custom-scroll::-webkit-scrollbar-thumb { background-color: #475569; }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 font-sans antialiased">
    
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
                <div class="max-w-7xl mx-auto">
                    
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">Client Management</h1>
                            <p class="text-sm text-slate-500 mt-1">Manage root companies and their structures.</p>
                        </div>
                        <button onclick="openModal('root')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/20 flex items-center gap-2 transition-all active:scale-95 font-medium border border-transparent">
                            <i class="ph ph-buildings text-lg"></i>
                            <span>Add New Client</span>
                        </button>
                    </div>

                    <?php if(isset($_GET['msg'])): 
                        $msgType = ($_GET['msg'] == 'updated') ? 'updated successfully' : 'completed successfully';
                    ?>
                        <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800 animate-fade-in-up">
                            <div class="p-2 bg-white dark:bg-darkcard rounded-full shadow-sm"><i class="ph ph-check-circle text-xl"></i></div>
                            <span class="font-medium text-sm">Operation <?= $msgType ?>.</span>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden relative z-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="clientTable">
                                <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 backdrop-blur-sm">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-20">ID</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Company Name</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Assigned Users</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Partner Code</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Total Subs</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Level</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right w-24">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <?php 
                                    if($result->num_rows == 0) {
                                        echo '<tr><td colspan="7" class="p-12 text-center text-slate-400"><i class="ph ph-briefcase text-4xl mb-2 block"></i>No companies found.</td></tr>';
                                    } else {
                                        while($row = $result->fetch_assoc()): 
                                            $users = isset($userMap[$row['id']]) ? $userMap[$row['id']] : [];
                                            $subCount = countTotalSubs($row['id'], $companyChildMap);
                                    ?>
                                    <tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border-b border-slate-100 dark:border-slate-800">
                                        <td class="px-6 py-4 text-xs font-mono text-slate-400">#<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                        
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shadow-sm">
                                                    <i class="ph ph-buildings text-xl"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($row['company_name']) ?></p>
                                                    <p class="text-[10px] text-slate-400"><?= $row['pic_name'] ? 'PIC: ' . htmlspecialchars($row['pic_name']) : 'No PIC' ?></p>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-6 py-4">
                                            <?php if(!empty($users)): ?>
                                                <div class="flex -space-x-2 overflow-visible">
                                                    <?php foreach($users as $usr): $initial = strtoupper(substr($usr['username'], 0, 1)); ?>
                                                    <div class="relative group/tooltip">
                                                        <div class="h-8 w-8 rounded-full ring-2 ring-white dark:ring-slate-900 bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300 cursor-help shadow-sm">
                                                            <?= $initial ?>
                                                        </div>
                                                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-max hidden group-hover/tooltip:block z-50">
                                                            <div class="bg-slate-800 text-white text-xs rounded px-2 py-1 shadow-lg">
                                                                <p class="font-bold"><?= $usr['username'] ?></p>
                                                                <p class="opacity-80"><?= $usr['email'] ?></p>
                                                            </div>
                                                            <div class="w-2 h-2 bg-slate-800 rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"></div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic opacity-50">Unassigned</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4">
                                            <span class="font-mono text-xs text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-200 dark:border-slate-700">
                                                <?= htmlspecialchars($row['partner_code']) ?>
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($subCount > 0): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800">
                                                    <i class="ph ph-tree-structure"></i> <?= $subCount ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-300 dark:text-slate-600 font-mono">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-indigo-50 text-indigo-600 border-indigo-100 dark:bg-indigo-900/20 dark:text-indigo-400 dark:border-indigo-800">
                                                Level 1
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-right">
                                            <button onclick="showDetail(<?= $row['id'] ?>)" 
                                                    class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 hover:text-primary dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:text-white transition-all shadow-sm">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <div id="companyModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeModal()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-darkcard w-full max-w-lg rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim flex flex-col relative z-10 max-h-[90vh]" id="modalContent">
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-white dark:bg-darkcard rounded-t-2xl">
                    <div>
                        <h3 id="modalTitle" class="text-lg font-bold text-slate-800 dark:text-white">Add New Client</h3>
                        <p id="modalSubtitle" class="text-xs text-slate-500 mt-0.5">Create a new root company.</p>
                    </div>
                    <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors"><i class="ph ph-x text-lg"></i></button>
                </div>
                
                <form method="POST" class="flex-1 overflow-y-auto custom-scroll p-6">
                    <input type="hidden" name="add_company" value="1">
                    <input type="hidden" name="parent_id" id="inputIdParent">
                    <input type="hidden" name="parent_level" id="inputIdLevel">

                    <div class="space-y-5">
                        <div id="parentInfoBox" class="hidden p-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl flex items-center gap-3">
                            <div class="p-2 bg-white dark:bg-indigo-900/50 rounded-lg text-indigo-600 dark:text-indigo-400"><i class="ph ph-arrow-elbow-down-right text-lg"></i></div>
                            <div>
                                <p class="text-xs text-indigo-600 dark:text-indigo-300 font-bold uppercase">Parent Company</p>
                                <p id="parentNameDisplay" class="text-sm font-bold text-slate-800 dark:text-white">PT Parent</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold uppercase text-slate-400 mb-3">Company Information</h4>
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Company Name</label>
                                    <input type="text" name="company_name" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="e.g. PT Maju Jaya">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Partner Code</label>
                                    <input type="text" name="partner_code" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="e.g. IDN001">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold uppercase text-slate-400 mb-3 pt-2 border-t border-slate-100 dark:border-slate-800">PIC Information</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">PIC Name</label>
                                    <div class="relative">
                                        <i class="ph ph-user absolute left-3 top-3 text-slate-400"></i>
                                        <input type="text" name="pic_name" class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="Full Name">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Email</label>
                                        <div class="relative">
                                            <i class="ph ph-envelope absolute left-3 top-3 text-slate-400"></i>
                                            <input type="email" name="pic_email" class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="Email">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">Phone</label>
                                        <div class="relative">
                                            <i class="ph ph-phone absolute left-3 top-3 text-slate-400"></i>
                                            <input type="text" name="pic_phone" class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="0812...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-sm">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-500/30 transition-all active:scale-95 text-sm flex items-center gap-2"><i class="ph ph-check-circle text-lg"></i> Save Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="detailModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="detailBackdrop" onclick="closeDetail()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-darkcard w-full max-w-2xl rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim flex flex-col relative z-10 max-h-[90vh]" id="detailContent">
                
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50 rounded-t-2xl">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Company Structure</h3>
                        <p class="text-xs text-slate-500">Managing hierarchy & contact information.</p>
                    </div>
                    <button onclick="closeDetail()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white hover:bg-slate-100 text-slate-400 hover:text-slate-600 border border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-700 transition-colors">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scroll p-6" id="detailBody">
                    <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                        <i class="ph ph-spinner animate-spin text-3xl mb-2 text-primary"></i>
                        <span class="text-sm">Fetching hierarchy data...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>

    <script>const currentUserRole = '<?= $_SESSION['role'] ?>';</script>

    <script>
        const modal = document.getElementById('companyModal');
        const modalBackdrop = document.getElementById('modalBackdrop');
        const modalContent = document.getElementById('modalContent');
        const parentInfoBox = document.getElementById('parentInfoBox');
        
        function openModal(mode, parentId = '', parentName = '', parentLevel = '') {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalBackdrop.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            if (mode === 'root') {
                document.getElementById('modalTitle').innerText = "Add Root Client";
                document.getElementById('modalSubtitle').innerText = "Create a top-level company (Level 1).";
                parentInfoBox.classList.add('hidden');
                document.getElementById('inputIdParent').value = "";
                document.getElementById('inputIdLevel').value = "";
            } else {
                document.getElementById('modalTitle').innerText = "Add Sub-Company";
                document.getElementById('modalSubtitle').innerText = "Create a child company under the selected parent.";
                parentInfoBox.classList.remove('hidden');
                document.getElementById('parentNameDisplay').innerText = parentName;
                document.getElementById('inputIdParent').value = parentId;
                document.getElementById('inputIdLevel').value = parentLevel;
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

        const detailModal = document.getElementById('detailModal');
        const detailBackdrop = document.getElementById('detailBackdrop');
        const detailContent = document.getElementById('detailContent');
        const detailBody = document.getElementById('detailBody');

        function showDetail(id) {
            detailModal.classList.remove('hidden');
            setTimeout(() => {
                detailBackdrop.classList.remove('opacity-0');
                detailContent.classList.remove('scale-95', 'opacity-0');
                detailContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            fetch(`manage-client.php?action=get_detail&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Logic Recursive Children
                    let childrenHTML = '';
                    if(data.children.length > 0) {
                        data.children.forEach(child => {
                            let indent = (child.depth || 0) * 20; 
                            let connector = child.depth > 0 ? '<i class="ph ph-arrow-elbow-down-right text-slate-300 mr-2"></i>' : '';
                            childrenHTML += `
                                <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700 mb-2">
                                    <div class="flex items-center gap-3" style="padding-left: ${indent}px">
                                        ${connector}
                                        <div class="w-8 h-8 rounded bg-white dark:bg-slate-800 flex items-center justify-center text-emerald-600 border border-slate-200 dark:border-slate-700 shrink-0">
                                            <i class="ph ph-buildings"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800 dark:text-white">${child.company_name}</p>
                                            <p class="text-[10px] text-slate-400">${child.partner_code} • Lvl ${child.level}</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button onclick="showDetail(${child.id})" class="text-xs text-blue-600 hover:underline">View</button>
                                        <a href="manage-client.php?delete_id=${child.id}" onclick="return confirm('Delete this sub-company?')" class="text-xs text-red-500 hover:underline">Delete</a>
                                    </div>
                                </div>`;
                        });
                    } else {
                        childrenHTML = '<div class="text-center py-4 text-slate-400 text-sm italic">No sub-companies found.</div>';
                    }

                    // Show Parent Info if exists
                    let parentInfo = '';
                    if(data.info.parent_name) {
                        parentInfo = `
                        <div class="mb-4 p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg flex items-center gap-2">
                            <i class="ph ph-arrow-elbow-up-left text-slate-400"></i>
                            <span class="text-xs text-slate-500">Parent: <strong>${data.info.parent_name}</strong></span>
                        </div>`;
                    }

                    // PIC Display vs Edit Mode (Conditional)
                    let picSection = `
                        <div id="picDisplay">
                            <div class="flex justify-between items-center mb-3 border-b border-slate-100 dark:border-slate-700 pb-2">
                                <h4 class="text-xs font-bold uppercase text-slate-400">PIC Information</h4>
                                ${currentUserRole === 'superadmin' ? `<button onclick="toggleEditMode()" class="text-xs flex items-center gap-1 text-indigo-600 font-bold hover:underline"><i class="ph ph-pencil-simple"></i> Edit</button>` : ''}
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-xs text-slate-400 font-bold uppercase mb-1">Name</p>
                                    <p class="text-slate-700 dark:text-white truncate font-medium">${data.info.pic_name || '-'}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 font-bold uppercase mb-1">Email</p>
                                    <p class="text-slate-700 dark:text-white truncate">${data.info.pic_email || '-'}</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-slate-400 font-bold uppercase mb-1">Phone</p>
                                    <p class="text-slate-700 dark:text-white font-mono">${data.info.pic_phone || '-'}</p>
                                </div>
                            </div>
                        </div>

                        <form id="picEditForm" method="POST" class="hidden">
                            <input type="hidden" name="edit_company_pic" value="1">
                            <input type="hidden" name="edit_id" value="${data.info.id}">
                            
                            <div class="flex justify-between items-center mb-3 border-b border-slate-100 dark:border-slate-700 pb-2">
                                <h4 class="text-xs font-bold uppercase text-indigo-600">Editing Contact</h4>
                                <button type="button" onclick="toggleEditMode()" class="text-xs text-slate-400 hover:text-slate-600">Cancel</button>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Name</label>
                                    <input type="text" name="edit_pic_name" value="${data.info.pic_name || ''}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Email</label>
                                        <input type="email" name="edit_pic_email" value="${data.info.pic_email || ''}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Phone</label>
                                        <input type="text" name="edit_pic_phone" value="${data.info.pic_phone || ''}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none">
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-bold shadow-sm transition-colors mt-2">Save Changes</button>
                            </div>
                        </form>
                    `;

                    detailBody.innerHTML = `
                        <div class="mb-6">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">${data.info.company_name}</h2>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-mono bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-slate-500 border border-slate-200 dark:border-slate-600">${data.info.partner_code}</span>
                                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 border border-indigo-100 text-[10px] font-bold rounded uppercase">Level ${data.info.level}</span>
                                    </div>
                                </div>
                            </div>
                            ${parentInfo}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700">
                                <h4 class="text-xs font-bold uppercase text-slate-400 mb-3 border-b border-slate-200 dark:border-slate-700 pb-2">Operational</h4>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-slate-600 dark:text-slate-300">Total SIMs</span>
                                    <span class="font-bold text-slate-800 dark:text-white">${data.sims.total}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-emerald-600">Active</span>
                                    <span class="font-bold text-emerald-600">${data.sims.active}</span>
                                </div>
                            </div>

                            <div class="p-4 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                ${picSection}
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-xs font-bold uppercase text-slate-400">Subsidiaries / Branches</h4>
                                ${data.info.level < 4 ? `<button onclick="openModal('sub', '${data.info.id}', '${data.info.company_name}', '${data.info.level}')" class="text-xs flex items-center gap-1 text-primary font-bold hover:underline bg-indigo-50 px-2 py-1 rounded border border-indigo-100"><i class="ph ph-plus-circle"></i> Add Sub</button>` : ''}
                            </div>
                            <div class="space-y-1">${childrenHTML}</div>
                        </div>`;
                })
                .catch(err => {
                    detailBody.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load details.</p>';
                });
        }

        function toggleEditMode() {
            const displayDiv = document.getElementById('picDisplay');
            const editForm = document.getElementById('picEditForm');
            
            if (displayDiv.classList.contains('hidden')) {
                displayDiv.classList.remove('hidden');
                editForm.classList.add('hidden');
            } else {
                displayDiv.classList.add('hidden');
                editForm.classList.remove('hidden');
            }
        }

        function closeDetail() {
            detailBackdrop.classList.add('opacity-0');
            detailContent.classList.remove('scale-100', 'opacity-100');
            detailContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { detailModal.classList.add('hidden'); }, 300);
        }
    </script>
</body>
</html>