<?php 
include 'config.php';
checkLogin();

// Hanya Superadmin/Admin
if ($_SESSION['role'] == 'user') {
    echo "<script>alert('Access Denied'); window.location='dashboard.php';</script>";
    exit();
}

// --- AJAX HANDLER: GET COMPANY DETAIL ---
if (isset($_GET['action']) && $_GET['action'] == 'get_detail' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $cid = intval($_GET['id']);
    
    // 1. Get Basic Info & Parent Info
    $sql = "SELECT c.*, p.company_name as parent_name, p.level as parent_level, p.id as parent_id 
            FROM companies c 
            LEFT JOIN companies p ON c.parent_company_id = p.id 
            WHERE c.id = $cid";
    $info = $conn->query($sql)->fetch_assoc();

    // 2. Get Grandparent (if exists - for Level 3)
    $hierarchy = [];
    if ($info['parent_id']) {
        // Cek apakah parent punya parent lagi (Grandparent)
        $gpSql = "SELECT company_name, id FROM companies WHERE id = " . $info['parent_company_id']; // Parent's Parent
        // Simple logic: traverse up. For now, let's build a simple chain.
        // Level 1 (Root) -> Level 2 -> Level 3 (Self)
        
        // Add Parent
        $hierarchy[] = ['name' => $info['parent_name'], 'level' => $info['parent_level']];
    }
    // Add Self
    $hierarchy[] = ['name' => $info['company_name'], 'level' => $info['level'], 'current' => true];

    // 3. Count SIMs
    $simSql = "SELECT COUNT(*) as total, 
               SUM(CASE WHEN status = '1' THEN 1 ELSE 0 END) as active 
               FROM sims WHERE company_id = $cid";
    $simStats = $conn->query($simSql)->fetch_assoc();

    echo json_encode([
        'info' => $info,
        'hierarchy' => $hierarchy,
        'sims' => $simStats
    ]);
    exit();
}

// --- HANDLE ADD COMPANY ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    $name = $_POST['company_name'];
    $code = $_POST['partner_code'];
    // Project Name dihapus dari input, set default empty string atau NULL
    $project = ""; 
    
    if (!empty($_POST['parent_id'])) {
        $parent_id = $_POST['parent_id'];
        $parent_level = $_POST['parent_level'];
        $level = $parent_level + 1; 
    } else {
        $parent_id = NULL;
        $level = 1; 
    }
    
    $stmt = $conn->prepare("INSERT INTO companies (company_name, project_name, partner_code, level, parent_company_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $project, $code, $level, $parent_id);
    
    if($stmt->execute()) {
        header("Location: manage-client.php?msg=added"); exit();
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
// A. Users
$userMap = [];
$sqlUsers = "SELECT uc.company_id, u.username, u.email, u.role FROM user_companies uc JOIN users u ON uc.user_id = u.id ORDER BY u.username ASC";
$resUsers = $conn->query($sqlUsers);
while($u = $resUsers->fetch_assoc()){ $userMap[$u['company_id']][] = $u; }

// B. Companies Tree
$sql = "SELECT c1.*, c2.company_name as parent_name FROM companies c1 LEFT JOIN companies c2 ON c1.parent_company_id = c2.id ORDER BY c1.id ASC";
$result = $conn->query($sql);
$companyTree = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $pid = $row['parent_company_id'] ? $row['parent_company_id'] : 0;
        $companyTree[$pid][] = $row;
    }
}

// Render Function
function renderCompanyRows($parentId, $tree, $userMap) {
    if (!isset($tree[$parentId])) return;

    foreach ($tree[$parentId] as $row) {
        $users = isset($userMap[$row['id']]) ? $userMap[$row['id']] : [];
        $padding = ($row['level'] - 1) * 36; 
        
        // Styling based on level
        $iconClass = match($row['level']) {
            1 => 'ph-buildings text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30',
            2 => 'ph-factory text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30',
            3 => 'ph-storefront text-amber-600 bg-amber-50 dark:bg-amber-900/30',
            default => 'ph-building text-slate-500 bg-slate-50'
        };
        ?>
        <tr class="company-row group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border-b border-slate-100 dark:border-slate-800"
            data-name="<?= strtolower($row['company_name']) ?>" 
            data-code="<?= strtolower($row['partner_code']) ?>"
            data-level="<?= $row['level'] ?>">
            
            <td class="px-6 py-4 text-xs font-mono text-slate-400">#<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></td>
            
            <td class="px-6 py-4">
                <div class="flex items-center" style="padding-left: <?= $padding ?>px;">
                    <?php if ($row['level'] > 1): ?>
                        <div class="text-slate-300 dark:text-slate-600 mr-3 flex items-center">
                            <i class="ph ph-arrow-elbow-down-right text-xl"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl border border-slate-100 dark:border-slate-700 flex items-center justify-center shadow-sm <?= explode(' ', $iconClass)[1] . ' ' . explode(' ', $iconClass)[2] ?>">
                            <i class="ph <?= explode(' ', $iconClass)[0] ?> text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($row['company_name']) ?></p>
                            <?php if($row['parent_name']): ?>
                                <p class="text-[10px] text-slate-400">Sub of: <?= htmlspecialchars($row['parent_name']) ?></p>
                            <?php endif; ?>
                        </div>
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
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border 
                    <?= $row['level'] == 1 ? 'bg-indigo-50 text-indigo-600 border-indigo-100' : 
                       ($row['level'] == 2 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-50 text-slate-600 border-slate-100') ?>">
                    Level <?= $row['level'] ?>
                </span>
            </td>
            
            <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-1">
                    <button onclick="showDetail(<?= $row['id'] ?>)" 
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors"
                            title="View Details & Hierarchy">
                        <i class="ph ph-eye text-lg"></i>
                    </button>

                    <?php if($row['level'] < 4): ?>
                    <button onclick="openModal('sub', '<?= $row['id'] ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= $row['level'] ?>')" 
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors"
                            title="Add Child Company">
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
        .hidden-row { display: none !important; }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
                <div class="max-w-7xl mx-auto">
                    
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">Client Management</h1>
                            <p class="text-sm text-slate-500 mt-1">Organize company hierarchy and partner configurations.</p>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                            <div class="relative w-full sm:w-64">
                                <i class="ph ph-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-lg"></i>
                                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search company..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                            </div>

                            <div class="relative w-full sm:w-40">
                                <i class="ph ph-funnel absolute left-3 top-2.5 text-slate-400 text-lg"></i>
                                <select id="levelFilter" onchange="filterTable()" class="w-full pl-10 pr-8 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none appearance-none cursor-pointer">
                                    <option value="all">All Levels</option>
                                    <option value="1">Level 1 (Root)</option>
                                    <option value="2">Level 2</option>
                                    <option value="3">Level 3</option>
                                </select>
                                <i class="ph ph-caret-down absolute right-3 top-3 text-slate-400 pointer-events-none"></i>
                            </div>

                            <button onclick="openModal('root')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2 rounded-xl shadow-lg shadow-indigo-500/20 flex items-center justify-center gap-2 transition-all active:scale-95 font-medium border border-transparent whitespace-nowrap">
                                <i class="ph ph-plus text-lg"></i>
                                <span>Add Client</span>
                            </button>
                        </div>
                    </div>

                    <?php if(isset($_GET['msg'])): ?>
                        <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800 animate-fade-in-up">
                            <div class="p-2 bg-white dark:bg-darkcard rounded-full shadow-sm"><i class="ph ph-check-circle text-xl"></i></div>
                            <span class="font-medium text-sm">Operation completed successfully.</span>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="clientTable">
                                <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 backdrop-blur-sm">
                                    <tr>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-20">ID</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Company Hierarchy</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Assigned Users</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Partner Code</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Level</th>
                                        <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right w-36">Action</th>
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
                            <div id="noResults" class="hidden p-12 text-center text-slate-400">
                                <i class="ph ph-magnifying-glass text-4xl mb-2 block opacity-50"></i>
                                <span>No companies match your search.</span>
                            </div>
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
                    <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700 transition-colors"><i class="ph ph-x text-lg"></i></button>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="add_company" value="1">
                    <input type="hidden" name="parent_id" id="inputIdParent">
                    <input type="hidden" name="parent_level" id="inputIdLevel">
                    <div class="space-y-4">
                        <div id="parentInfoBox" class="hidden p-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-xl flex items-center gap-3">
                            <div class="p-2 bg-white dark:bg-indigo-900/50 rounded-lg text-indigo-600 dark:text-indigo-400"><i class="ph ph-arrow-elbow-down-right text-lg"></i></div>
                            <div>
                                <p class="text-xs text-indigo-600 dark:text-indigo-300 font-bold uppercase">Parent Company</p>
                                <p id="parentNameDisplay" class="text-sm font-bold text-slate-800 dark:text-white">PT Parent</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Company Name</label>
                            <input type="text" name="company_name" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="e.g. PT Maju Jaya">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-500 dark:text-slate-400 mb-1.5">Partner Code</label>
                            <input type="text" name="partner_code" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-sm focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all dark:text-white" placeholder="e.g. IDN001">
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
            <div class="bg-white dark:bg-darkcard w-full max-w-md rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 modal-anim flex flex-col relative z-10" id="detailContent">
                
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Company Details</h3>
                    <button onclick="closeDetail()" class="text-slate-400 hover:text-slate-600"><i class="ph ph-x text-lg"></i></button>
                </div>

                <div class="p-6" id="detailBody">
                    <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                        <i class="ph ph-spinner animate-spin text-3xl mb-2"></i>
                        <span class="text-sm">Loading details...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- 1. MODAL ADD ---
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
                document.getElementById('modalSubtitle').innerText = "Create a child company.";
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
            setTimeout(() => { modal.classList.add('hidden'); }, 300);
        }

        // --- 2. SEARCH & FILTER ---
        function filterTable() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let levelFilter = document.getElementById("levelFilter").value;
            let rows = document.querySelectorAll(".company-row");
            let hasVisible = false;

            rows.forEach(row => {
                let name = row.getAttribute("data-name");
                let code = row.getAttribute("data-code");
                let level = row.getAttribute("data-level");

                let matchSearch = name.includes(input) || code.includes(input);
                let matchLevel = (levelFilter === "all") || (level === levelFilter);

                if (matchSearch && matchLevel) {
                    row.classList.remove("hidden-row");
                    hasVisible = true;
                } else {
                    row.classList.add("hidden-row");
                }
            });

            document.getElementById("noResults").style.display = hasVisible ? "none" : "block";
        }

        // --- 3. DETAIL MODAL (AJAX) ---
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

            // Reset Loading
            detailBody.innerHTML = `
                <div class="flex flex-col items-center justify-center py-8 text-slate-400">
                    <i class="ph ph-spinner animate-spin text-3xl mb-2 text-primary"></i>
                    <span class="text-sm">Fetching company data...</span>
                </div>`;

            // Fetch Data
            fetch(`manage-client.php?action=get_detail&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    let hierarchyHTML = '';
                    data.hierarchy.forEach((h, index) => {
                        let isLast = index === data.hierarchy.length - 1;
                        let color = isLast ? 'text-indigo-600 font-bold' : 'text-slate-500';
                        hierarchyHTML += `
                            <div class="flex items-center gap-2 mb-2 last:mb-0">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-[10px] border border-slate-200">Lvl ${h.level}</span>
                                <span class="text-sm ${color}">${h.name}</span>
                            </div>
                            ${!isLast ? '<div class="pl-4 border-l-2 border-slate-100 h-3 ml-3"></div>' : ''}
                        `;
                    });

                    detailBody.innerHTML = `
                        <div class="mb-6">
                            <p class="text-xs font-bold uppercase text-slate-400 mb-2">Company Info</p>
                            <h2 class="text-xl font-bold text-slate-800 dark:text-white">${data.info.company_name}</h2>
                            <p class="text-sm text-slate-500 font-mono mt-1 bg-slate-50 inline-block px-2 py-1 rounded border">${data.info.partner_code}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                                <p class="text-xs text-indigo-600 font-bold uppercase">Total SIMs</p>
                                <p class="text-2xl font-bold text-indigo-700">${data.sims.total}</p>
                            </div>
                            <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                                <p class="text-xs text-emerald-600 font-bold uppercase">Active</p>
                                <p class="text-2xl font-bold text-emerald-700">${data.sims.active}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase text-slate-400 mb-3">Hierarchy Structure</p>
                            <div class="p-4 bg-white border border-slate-100 rounded-xl shadow-sm">
                                ${hierarchyHTML}
                            </div>
                        </div>
                    `;
                })
                .catch(err => {
                    detailBody.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load details.</p>';
                });
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