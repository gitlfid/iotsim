<?php 
include 'config.php';
checkLogin();

// Hanya Superadmin/Admin yang boleh akses
if ($_SESSION['role'] == 'user') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

// --- 1. HANDLE ADD COMPANY ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    $name = $_POST['company_name'];
    $project = $_POST['project_name'];
    $code = $_POST['partner_code'];
    
    // Logika Level & Parent
    if (!empty($_POST['parent_id'])) {
        $parent_id = $_POST['parent_id'];
        $parent_level = $_POST['parent_level'];
        $level = $parent_level + 1; 
    } else {
        $parent_id = NULL;
        $level = 1; // Default Root Level
    }
    
    $stmt = $conn->prepare("INSERT INTO companies (company_name, project_name, partner_code, level, parent_company_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $project, $code, $level, $parent_id);
    
    if($stmt->execute()) {
        header("Location: manage-client.php?msg=added");
        exit();
    }
}

// --- 2. HANDLE DELETE COMPANY ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    
    if($stmt->execute()) {
        header("Location: manage-client.php?msg=deleted");
        exit();
    }
}

// --- 3. FETCH DATA ---

// A. Fetch Users Linked to Companies (NEW LOGIC)
// Kita ambil data user yang terhubung ke company via tabel user_companies
$userMap = [];
$sqlUsers = "SELECT uc.company_id, u.username, u.email, u.role 
             FROM user_companies uc 
             JOIN users u ON uc.user_id = u.id 
             ORDER BY u.username ASC";
$resUsers = $conn->query($sqlUsers);
while($u = $resUsers->fetch_assoc()){
    $userMap[$u['company_id']][] = $u;
}

// B. Fetch Companies & Build Tree
$sql = "SELECT c1.*, c2.company_name as parent_name 
        FROM companies c1 
        LEFT JOIN companies c2 ON c1.parent_company_id = c2.id 
        ORDER BY c1.id ASC";
$result = $conn->query($sql);

$companyTree = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $pid = $row['parent_company_id'] ? $row['parent_company_id'] : 0;
        $companyTree[$pid][] = $row;
    }
}

// Fungsi Rekursif Render Row
function renderCompanyRows($parentId, $tree, $userMap) {
    if (!isset($tree[$parentId])) return;

    foreach ($tree[$parentId] as $row) {
        $users = isset($userMap[$row['id']]) ? $userMap[$row['id']] : [];
        
        // --- VISUAL SETUP ---
        $padding = ($row['level'] - 1) * 32; 
        $iconClass = match($row['level']) {
            1 => 'ph-buildings text-indigo-600 dark:text-indigo-400',
            2 => 'ph-factory text-emerald-600 dark:text-emerald-400',
            3 => 'ph-storefront text-amber-600 dark:text-amber-400',
            default => 'ph-building text-slate-400'
        };
        $rowBg = $row['level'] == 1 ? 'bg-slate-50/50 dark:bg-slate-800/30' : '';
        ?>
        <tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border-b border-slate-100 dark:border-slate-800 <?= $rowBg ?>">
            
            <td class="px-6 py-4 text-xs font-mono text-slate-400">#<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></td>
            
            <td class="px-6 py-4">
                <div class="flex items-center" style="padding-left: <?= $padding ?>px;">
                    <?php if ($row['level'] > 1): ?>
                        <div class="text-slate-300 dark:text-slate-600 mr-2 flex items-center h-full">
                            <i class="ph ph-arrow-elbow-down-right text-lg"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shadow-sm">
                            <i class="ph <?= $iconClass ?> text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($row['company_name']) ?></p>
                            <?php if($row['parent_name']): ?>
                                <p class="text-[10px] text-slate-400">via <?= htmlspecialchars($row['parent_name']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </td>

            <td class="px-6 py-4">
                <?php if(!empty($users)): ?>
                    <div class="flex -space-x-2 overflow-hidden">
                        <?php 
                        $maxShow = 3;
                        $count = 0;
                        foreach($users as $usr): 
                            if($count >= $maxShow) break;
                            $initial = strtoupper(substr($usr['username'], 0, 1));
                        ?>
                        <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white dark:ring-slate-900 bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300 relative group/avatar cursor-help">
                            <?= $initial ?>
                            <span class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover/avatar:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                <?= $usr['username'] ?>
                            </span>
                        </div>
                        <?php $count++; endforeach; ?>
                        
                        <?php if(count($users) > $maxShow): ?>
                            <div class="inline-block h-8 w-8 rounded-full ring-2 ring-white dark:ring-slate-900 bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-xs font-medium text-slate-500">
                                +<?= count($users) - $maxShow ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <span class="text-xs text-slate-400 italic">No users assigned</span>
                <?php endif; ?>
            </td>

            <td class="px-6 py-4">
                <div class="flex flex-col">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($row['project_name']) ?></span>
                    <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($row['partner_code']) ?></span>
                </div>
            </td>
            
            <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 shadow-sm">
                    Level <?= $row['level'] ?>
                </span>
            </td>
            
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-1">
                    <?php if($row['level'] < 4): ?>
                    <button onclick="openModal('sub', '<?= $row['id'] ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= $row['level'] ?>')" 
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors"
                            title="Add Sub-Company">
                        <i class="ph ph-plus-circle text-lg"></i>
                    </button>
                    <?php endif; ?>

                    <a href="manage-client.php?delete_id=<?= $row['id'] ?>" 
                       onclick="return confirm('Deleting this company will delete all its children hierarchy! Continue?');" 
                       class="w-8 h-8 flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                       title="Delete">
                        <i class="ph ph-trash text-lg"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php 
        renderCompanyRows($row['id'], $tree, $userMap);
    }
}
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
                            <h1 class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">Client Management</h1>
                            <p class="text-sm text-slate-500 mt-1">Organize company hierarchy, projects, and partner codes.</p>
                        </div>
                        <button onclick="openModal('root')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-500/20 flex items-center gap-2 transition-all active:scale-95 font-medium border border-transparent">
                            <i class="ph ph-buildings text-lg"></i>
                            <span>Add New Client</span>
                        </button>
                    </div>

                    <?php if(isset($_GET['msg'])): ?>
                        <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800">
                            <div class="p-2 bg-white dark:bg-darkcard rounded-full shadow-sm"><i class="ph ph-check-circle text-xl"></i></div>
                            <span class="font-medium text-sm">Operation successful.</span>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 backdrop-blur-sm">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-20">ID</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Company Hierarchy</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Assigned Users</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Project / Code</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Level</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right w-32">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    <?php 
                                    if(empty($companyTree)) {
                                        echo '<tr><td colspan="6" class="p-12 text-center text-slate-400"><i class="ph ph-briefcase text-4xl mb-2 block"></i>No companies found. Create a root client to start.</td></tr>';
                                    } else {
                                        renderCompanyRows(0, $companyTree, $userMap); 
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <div id="companyModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeModal()"></div>
        
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-darkcard w-full max-w-lg rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim flex flex-col relative z-10" id="modalContent">
                
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-white dark:bg-darkcard rounded-t-2xl">
                    <div>
                        <h3 id="modalTitle" class="text-lg font-bold text-slate-800 dark:text-white">Add New Client</h3>
                        <p id="modalSubtitle" class="text-xs text-slate-500 mt-0.5">Create a new root company.</p>
                    </div>
                    <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors">
                        <i class="ph ph-x text-lg"></i>
                    </button>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="add_company" value="1">
                    <input type="hidden" name="parent_id" id="inputIdParent">
                    <input type="hidden" name="parent_level" id="inputIdLevel">

                    <div class="space-y-4">
                        <div id="parentInfoBox" class="hidden p-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl flex items-center gap-3">
                            <div class="p-2 bg-white dark:bg-indigo-900/50 rounded-lg text-indigo-600 dark:text-indigo-400">
                                <i class="ph ph-arrow-elbow-down-right text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-indigo-600 dark:text-indigo-300 font-bold uppercase">Parent Company</p>
                                <p id="parentNameDisplay" class="text-sm font-bold text-slate-800 dark:text-white">PT Parent</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Company Name</label>
                            <div class="relative">
                                <i class="ph ph-buildings absolute left-3 top-3 text-slate-400"></i>
                                <input type="text" name="company_name" required class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:text-white" placeholder="e.g. PT Maju Jaya">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Project Name</label>
                                <div class="relative">
                                    <i class="ph ph-folder-notch absolute left-3 top-3 text-slate-400"></i>
                                    <input type="text" name="project_name" required class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:text-white" placeholder="e.g. IoT Smart City">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Partner Code</label>
                                <div class="relative">
                                    <i class="ph ph-tag absolute left-3 top-3 text-slate-400"></i>
                                    <input type="text" name="partner_code" required class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:text-white" placeholder="e.g. IDN001">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-sm">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold shadow-lg shadow-indigo-500/30 transition-all active:scale-95 text-sm flex items-center gap-2">
                            <i class="ph ph-check-circle text-lg"></i> Save Client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('companyModal');
        const modalBackdrop = document.getElementById('modalBackdrop');
        const modalContent = document.getElementById('modalContent');
        
        // Modal Elements
        const modalTitle = document.getElementById('modalTitle');
        const modalSubtitle = document.getElementById('modalSubtitle');
        const parentInfoBox = document.getElementById('parentInfoBox');
        const parentNameDisplay = document.getElementById('parentNameDisplay');
        const inputIdParent = document.getElementById('inputIdParent');
        const inputIdLevel = document.getElementById('inputIdLevel');

        function openModal(mode, parentId = '', parentName = '', parentLevel = '') {
            modal.classList.remove('hidden');
            // Reset Animation
            setTimeout(() => {
                modalBackdrop.classList.remove('opacity-0');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Logic: Root vs Sub
            if (mode === 'root') {
                modalTitle.innerText = "Add Root Client";
                modalSubtitle.innerText = "Create a top-level company (Level 1).";
                parentInfoBox.classList.add('hidden');
                inputIdParent.value = "";
                inputIdLevel.value = "";
            } else {
                modalTitle.innerText = "Add Sub-Company";
                modalSubtitle.innerText = "Create a child company under the selected parent.";
                parentInfoBox.classList.remove('hidden');
                parentNameDisplay.innerText = parentName;
                inputIdParent.value = parentId;
                inputIdLevel.value = parentLevel;
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
    </script>
</body>
</html>