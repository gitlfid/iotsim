<?php 
include 'config.php';
checkLogin();

// --- 0. PREPARATION ---
$user_id = $_SESSION['user_id'];
$my_partner_code = ''; 
$my_company_id = 0;

$uComp = $conn->query("SELECT c.level, c.partner_code, c.id as company_id 
                       FROM companies c 
                       JOIN users u ON c.id = u.company_id 
                       WHERE u.id = '$user_id'");

if ($uComp->num_rows > 0) {
    $uData = $uComp->fetch_assoc();
    $my_partner_code = $uData['partner_code']; 
    $my_company_id = $uData['company_id'];
}
if (empty($my_partner_code)) $my_partner_code = 'IDN0000034';

// --- 1. HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update Tags
    if (isset($_POST['action']) && $_POST['action'] == 'update_tags') {
        $target_iccid = $_POST['sim_id']; 
        $raw_tags = $_POST['tags_input'];
        $tags_array = array_filter(array_map('trim', explode(',', $raw_tags)));
        $clean_tags = implode(',', $tags_array);
        $stmt = $conn->prepare("UPDATE sims SET tags = ? WHERE iccid = ?");
        $stmt->bind_param("ss", $clean_tags, $target_iccid);
        $stmt->execute();
        header("Location: sim-list.php?msg=tags_updated");
        exit();
    }
    // Bulk Set Project
    if (isset($_POST['action']) && $_POST['action'] == 'bulk_set_project') {
        $project_name = trim($_POST['bulk_project_name']);
        $sim_ids = explode(',', $_POST['bulk_sim_ids']);
        if(!empty($sim_ids)) {
            $val = !empty($project_name) ? $project_name : NULL;
            $types = str_repeat('s', count($sim_ids));
            $placeholders = implode(',', array_fill(0, count($sim_ids), '?'));
            $sql = "UPDATE sims SET custom_project = ? WHERE iccid IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $params = array_merge([$val], $sim_ids);
            $stmt->bind_param('s' . $types, ...$params);
            $stmt->execute();
        }
        header("Location: sim-list.php?msg=project_updated");
        exit();
    }
    // Toggle Package
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_package') {
        header('Content-Type: application/json');
        $iccid = trim($_POST['iccid']);
        $state = $_POST['state']; 
        $token = getDynamicToken();
        $details = getSimDetailFromApi($iccid);
        if (!$details || !isset($details['data']['orderId'])) { echo json_encode(['success'=>false]); exit; }
        $orderId = $details['data']['orderId'];
        $operationType = ($state === 'true') ? "2" : "1";
        $apiResult = toggleSimStatusFromApi($iccid, $orderId, $operationType);
        if ($apiResult && isset($apiResult['code']) && $apiResult['code'] == 200) {
            $newStatus = ($state === 'true') ? '1' : '2';
            $conn->query("UPDATE sims SET package_status = '$newStatus' WHERE iccid = '$iccid'");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

// --- 2. FILTERS & PAGINATION ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = isset($_GET['size']) ? intval($_GET['size']) : 10;
$offset = ($page - 1) * $pageSize;

$s_keyword = $_GET['search'] ?? '';
$f_company = $_GET['company'] ?? '';
$f_project = $_GET['project'] ?? '';
$f_status  = $_GET['status'] ?? '';
$f_tag     = $_GET['tag'] ?? '';
$f_level   = $_GET['level'] ?? '';

$where = "WHERE 1=1";
if ($s_keyword) $where .= " AND (sims.iccid LIKE '%$s_keyword%' OR sims.imsi LIKE '%$s_keyword%' OR sims.msisdn LIKE '%$s_keyword%')";
if ($f_company) $where .= " AND sims.company_id = '$f_company'";
if ($f_status)  $where .= " AND sims.status = '$f_status'";
if ($f_tag)     $where .= " AND sims.tags LIKE '%$f_tag%'";
if ($f_level)   $where .= " AND companies.level = '$f_level'";
if ($f_project) {
    $safeProj = $conn->real_escape_string($f_project);
    $where .= " AND (sims.custom_project = '$safeProj' OR (sims.custom_project IS NULL AND companies.project_name = '$safeProj'))";
}

$totalRes = $conn->query("SELECT COUNT(*) as total FROM sims LEFT JOIN companies ON sims.company_id = companies.id $where");
$totalRecords = $totalRes->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $pageSize);

$sql = "SELECT sims.*, companies.company_name, companies.project_name as default_project, companies.level 
        FROM sims LEFT JOIN companies ON sims.company_id = companies.id $where 
        ORDER BY sims.id DESC LIMIT $offset, $pageSize";
$result = $conn->query($sql);

// Helper Data
$compArr = []; $cQ = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");
while($r = $cQ->fetch_assoc()) $compArr[] = $r;

$projArr = []; $pQ = $conn->query("SELECT DISTINCT IFNULL(custom_project, project_name) as p_name FROM sims LEFT JOIN companies ON sims.company_id = companies.id HAVING p_name IS NOT NULL AND p_name != '' ORDER BY p_name");
while($r = $pQ->fetch_assoc()) $projArr[] = $r['p_name'];

// FIX: Added '?? ""' to handle null tags from database
$tagArr = []; $tQ = $conn->query("SELECT DISTINCT tags FROM sims");
while($r = $tQ->fetch_assoc()) { 
    foreach(explode(',', $r['tags'] ?? '') as $t) {
        if(trim($t)) $tagArr[] = trim($t); 
    }
}
$tagArr = array_unique($tagArr); sort($tagArr);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIM Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { colors: { primary: '#4F46E5', darkcard: '#24303F' }, fontSize: { 'xxs': '0.65rem' } }
            }
        }
    </script>
    <style>
        input[type="checkbox"]:checked { background-color: #4F46E5; border-color: #4F46E5; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .col-hidden { display: none !important; }
        .table-font { font-size: 0.75rem; } 
        
        /* --- STICKY COLUMN LOGIC (STRICT) --- */
        
        /* Definisi Lebar & Posisi Strict untuk Kolom Fixed */
        
        /* 1. CHECKBOX (Width 50px) */
        .w-col-check { width: 50px; min-width: 50px; max-width: 50px; left: 0; }
        
        /* 2. TAGS (Width 100px | Left 50px) */
        .w-col-tags { width: 100px; min-width: 100px; max-width: 100px; left: 50px; }
        
        /* 3. ICCID (Width 160px | Left 150px) */
        .w-col-iccid { width: 160px; min-width: 160px; max-width: 160px; left: 150px; }
        
        /* 4. CUSTOMER (Width 220px | Left 310px) */
        /* Note: 50 + 100 + 160 = 310px */
        .w-col-cust { width: 230px; min-width: 230px; max-width: 220px; left: 310px; }
        
        /* Base Sticky Style */
        .sticky-col { position: sticky; z-index: 20; }
        th.sticky-col { z-index: 30; } /* Header always top */
        
        /* Border Pemisah untuk Kolom Fixed Terakhir (Customer) */
        .border-r-sticky { border-right: 1px solid #e2e8f0; }
        .dark .border-r-sticky { border-right: 1px solid #374151; }
        
        /* Background untuk mencegah tembus pandang */
        .bg-sticky-light { background-color: #ffffff; }
        thead .bg-sticky-light { background-color: #f8fafc; } 
        tr:hover .bg-sticky-light { background-color: #f8fafc; }

        .dark .bg-sticky-dark { background-color: #1F2937; }
        thead .bg-sticky-dark { background-color: #1F2937; }
        .dark tr:hover .bg-sticky-dark { background-color: #374151; }

        /* Action Column Fixed Right */
        .sticky-right { position: sticky; right: 0; z-index: 20; border-left: 1px solid #e2e8f0; }
        .dark .sticky-right { border-left: 1px solid #374151; }

        /* Pagination Button */
        .btn-page {
            width: 2.25rem; height: 2.25rem; display: flex; align-items: center; justify-content: center;
            border-radius: 0.5rem; border: 1px solid #E2E8F0; font-size: 0.875rem; font-weight: 500; transition: all 0.2s;
        }
        .btn-page:hover { background-color: #F8FAFC; border-color: #CBD5E1; }
        .btn-page.active { background-color: #4F46E5; color: white; border-color: #4F46E5; }
        .dark .btn-page { border-color: #374151; color: #D1D5DB; background-color: #1F2937; }
        .dark .btn-page:hover { background-color: #374151; color: white; }
        .dark .btn-page.active { background-color: #4F46E5; border-color: #4F46E5; color: white; }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-dark text-slate-600 dark:text-darktext antialiased overflow-hidden">
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 py-6 px-4 md:px-6">
                
                <div class="mb-5 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 dark:text-white">SIM Card Monitor</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Total: <b><?= number_format($totalRecords) ?></b> records</p>
                    </div>
                    
                    <div id="bulkActionBar" class="hidden flex items-center gap-3 bg-white dark:bg-slate-800 border border-indigo-100 dark:border-slate-700 px-4 py-2 rounded-lg shadow-lg animate-in fade-in slide-in-from-bottom-2 z-20">
                        <span class="font-bold text-indigo-600 dark:text-indigo-400 text-sm"><span id="selectedCount">0</span> Selected</span>
                        <div class="h-4 w-px bg-slate-300 dark:bg-slate-600"></div>
                        <button onclick="openBulkProjectModal()" class="flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400 font-medium text-sm transition-colors dark:text-white">
                            <i class="ph ph-pencil-simple-line"></i> Set Project
                        </button>
                    </div>
                </div>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'project_updated'): ?>
                    <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-sm rounded-lg border border-green-200 dark:border-green-800 flex items-center gap-2">
                        <i class="ph ph-check-circle text-lg"></i> Project updated successfully.
                    </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-darkcard p-4 rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 mb-5">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <input type="hidden" name="size" value="<?= $pageSize ?>">
                        
                        <div class="md:col-span-3">
                            <label class="block mb-1 font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px]">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($s_keyword) ?>" placeholder="ICCID, IMSI..." class="w-full h-9 rounded border border-slate-300 dark:border-slate-600 bg-transparent dark:bg-slate-700/50 px-3 text-xs focus:border-primary dark:focus:border-primary dark:text-white placeholder-slate-400">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px]">Company</label>
                            <select name="company" class="w-full h-9 rounded border border-slate-300 dark:border-slate-600 bg-transparent dark:bg-slate-700/50 px-2 text-xs focus:border-primary dark:focus:border-primary dark:text-white">
                                <option value="" class="dark:bg-darkcard">All Companies</option>
                                <?php foreach($compArr as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $f_company == $c['id'] ? 'selected' : '' ?> class="dark:bg-darkcard"><?= $c['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px]">Project</label>
                            <select name="project" class="w-full h-9 rounded border border-slate-300 dark:border-slate-600 bg-transparent dark:bg-slate-700/50 px-2 text-xs focus:border-primary dark:focus:border-primary dark:text-white">
                                <option value="" class="dark:bg-darkcard">All Projects</option>
                                <?php foreach($projArr as $p): ?>
                                    <option value="<?= htmlspecialchars($p) ?>" <?= $f_project == $p ? 'selected' : '' ?> class="dark:bg-darkcard"><?= htmlspecialchars($p) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block mb-1 font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px]">Status</label>
                            <select name="status" class="w-full h-9 rounded border border-slate-300 dark:border-slate-600 bg-transparent dark:bg-slate-700/50 px-2 text-xs focus:border-primary dark:focus:border-primary dark:text-white">
                                <option value="" class="dark:bg-darkcard">All Status</option>
                                <option value="2" <?= $f_status == '2' ? 'selected' : '' ?> class="dark:bg-darkcard">Active</option>
                                <option value="6" <?= $f_status == '6' ? 'selected' : '' ?> class="dark:bg-darkcard">Suspend</option>
                            </select>
                        </div>

                        <div class="md:col-span-3">
                            <label class="block mb-1 font-bold text-slate-500 dark:text-slate-400 uppercase text-[10px]">Level</label>
                            <div class="flex bg-slate-100 dark:bg-slate-700/50 rounded p-0.5 h-9 border border-transparent dark:border-slate-700">
                                <?php foreach(['' => 'All', '1'=>'L1', '2'=>'L2', '3'=>'L3', '4'=>'L4'] as $val => $lbl): 
                                    $active = ((string)$f_level === (string)$val) ? 'bg-white text-primary shadow-sm dark:bg-slate-600 dark:text-white' : 'text-slate-500 dark:text-slate-400 hover:text-primary dark:hover:text-white';
                                ?>
                                <label class="flex-1 cursor-pointer text-center relative h-full">
                                    <input type="radio" name="level" value="<?= $val ?>" class="sr-only" <?= ((string)$f_level === (string)$val) ? 'checked' : '' ?> onclick="this.form.submit()">
                                    <div class="h-full flex items-center justify-center rounded text-[10px] font-bold transition-all <?= $active ?>"><?= $lbl ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="md:col-span-12 flex justify-end pt-2 border-t border-slate-50 dark:border-slate-800 gap-2">
                            <div class="relative">
                                <button type="button" onclick="document.getElementById('colManager').classList.toggle('hidden')" class="flex items-center gap-2 h-9 px-4 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 shadow-sm transition-colors">
                                    <i class="ph ph-columns"></i> Columns
                                </button>
                                <div id="colManager" class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-100 dark:border-slate-700 p-3 z-30 animate-in fade-in zoom-in-95">
                                    <div class="flex flex-col gap-2 max-h-60 overflow-y-auto">
                                        <?php 
                                        $cols = ['tags'=>'Tags','iccid'=>'ICCID','customer'=>'Customer','level'=>'Level','project'=>'Project','msisdn'=>'MSISDN','imsi'=>'IMSI','profile'=>'Profile','status'=>'Status','package'=>'Package','usage'=>'Usage','dates'=>'Dates','action'=>'Action'];
                                        foreach($cols as $k=>$v): ?>
                                        <label class="flex items-center gap-2 text-xs cursor-pointer text-slate-700 dark:text-slate-300 hover:text-primary"><input type="checkbox" class="col-toggle accent-primary" data-target="col-<?= $k ?>" checked onchange="toggleColumn('col-<?= $k ?>')"> <?= $v ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="bg-primary hover:bg-indigo-600 text-white font-bold h-9 px-6 rounded text-xs shadow-sm flex items-center gap-2 transition-colors">
                                <i class="ph ph-funnel"></i> Apply
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-xl bg-white dark:bg-darkcard shadow-sm overflow-hidden border border-slate-100 dark:border-slate-800 relative">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-font min-w-[1300px]">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-4 py-3 text-center align-middle sticky-col w-col-check bg-sticky-light dark:bg-sticky-dark">
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" id="selectAll" class="w-3.5 h-3.5 rounded border-slate-300 dark:border-slate-600 text-primary cursor-pointer focus:ring-0 bg-white dark:bg-slate-700">
                                        </div>
                                    </th>
                                    <th class="col-tags px-4 py-3 font-bold uppercase align-middle sticky-col w-col-tags bg-sticky-light dark:bg-sticky-dark">Tags</th>
                                    <th class="col-iccid px-4 py-3 font-bold uppercase align-middle sticky-col w-col-iccid bg-sticky-light dark:bg-sticky-dark">ICCID</th>
                                    <th class="col-customer px-4 py-3 font-bold uppercase align-middle sticky-col w-col-cust bg-sticky-light dark:bg-sticky-dark border-r-sticky">Customer</th> 
                                    
                                    <th class="col-level px-4 py-3 font-bold uppercase w-[80px] align-middle">Level</th>
                                    <th class="col-project px-4 py-3 font-bold uppercase min-w-[80px] align-middle">Project</th>
                                    <th class="col-msisdn px-4 py-3 font-bold uppercase align-middle">MSISDN</th>
                                    <th class="col-imsi px-4 py-3 font-bold uppercase align-middle">IMSI</th>
                                    <th class="col-profile px-4 py-3 font-bold uppercase align-middle">Profile</th>
                                    <th class="col-status px-4 py-3 font-bold uppercase text-center align-middle">Status</th>
                                    <th class="col-package px-4 py-3 font-bold uppercase text-center align-middle">Package</th>
                                    <th class="col-usage px-4 py-3 font-bold uppercase min-w-[180px] align-middle">Usage</th>
                                    <th class="col-dates px-4 py-3 font-bold uppercase text-right align-middle">Active/Exp</th>
                                    
                                    <th class="col-action px-4 py-3 font-bold uppercase text-right align-middle sticky-right bg-sticky-light dark:bg-sticky-dark">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): 
                                    $iccid = $row['iccid'];
                                    $displayProject = !empty($row['custom_project']) ? $row['custom_project'] : $row['default_project'];
                                    $isCustom = !empty($row['custom_project']);
                                    $usedMB = floatval($row['used_flow']??0) / 1048576;
                                    $totalMB = floatval($row['total_flow']??0) / 1048576;
                                    $pct = ($totalMB > 0) ? ($usedMB / $totalMB) * 100 : 0;
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                    <td class="px-4 py-3 text-center align-middle sticky-col w-col-check bg-sticky-light dark:bg-sticky-dark">
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="sim_ids[]" value="<?= $iccid ?>" class="sim-check w-3.5 h-3.5 rounded border-slate-300 dark:border-slate-600 text-primary cursor-pointer focus:ring-0 bg-white dark:bg-slate-700">
                                        </div>
                                    </td>
                                    
                                    <td class="col-tags px-4 py-3 align-middle sticky-col w-col-tags bg-sticky-light dark:bg-sticky-dark">
                                        <div class="flex flex-col gap-1 justify-center">
                                            <div class="flex flex-wrap gap-1">
                                                <?php 
                                                // FIX: Added null check
                                                foreach(array_filter(explode(',', $row['tags'] ?? '')) as $tag): ?>
                                                    <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-[10px] border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300"><?= trim($tag) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <button onclick="openTagModal('<?= $iccid ?>', '<?= htmlspecialchars($row['tags'] ?? '') ?>')" class="text-[10px] text-primary dark:text-indigo-400 font-bold hover:underline w-fit">Edit</button>
                                        </div>
                                    </td>

                                    <td class="col-iccid px-4 py-3 align-middle sticky-col w-col-iccid bg-sticky-light dark:bg-sticky-dark">
                                        <span class="block font-bold text-primary dark:text-indigo-400 font-mono select-all"><?= $iccid ?></span>
                                    </td>
                                    
                                    <td class="col-customer px-4 py-3 align-middle whitespace-nowrap sticky-col w-col-cust bg-sticky-light dark:bg-sticky-dark border-r-sticky">
                                        <span class="font-bold text-slate-800 dark:text-white block truncate max-w-[200px]" title="<?= $row['company_name'] ?>"><?= $row['company_name'] ?></span>
                                    </td>

                                    <td class="col-level px-4 py-3 align-middle whitespace-nowrap">
                                        <span class="inline-flex w-fit items-center rounded-lg bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">Lvl <?= $row['level'] ?></span>
                                    </td>

                                    <td class="col-project px-4 py-3 align-middle">
                                        <div class="flex items-center gap-1 font-medium truncate max-w-[150px]" title="<?= $displayProject ?>">
                                            <i class="ph ph-briefcase text-slate-400"></i> <span class="<?= $isCustom ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-600 dark:text-slate-300' ?>"><?= $displayProject ?></span>
                                        </div>
                                    </td>
                                    <td class="col-msisdn px-4 py-3 align-middle font-mono text-slate-600 dark:text-slate-300"><?= $row['msisdn'] ?></td>
                                    <td class="col-imsi px-4 py-3 align-middle font-mono text-slate-600 dark:text-slate-300"><?= $row['imsi'] ?></td>
                                    <td class="col-profile px-4 py-3 align-middle font-mono text-slate-500 dark:text-slate-400"><?= $row['profile_iccid'] ?></td>
                                    <td class="col-status px-4 py-3 align-middle text-center"><?= getRealtimeStatusBadge($row['status']) ?></td>
                                    <td class="col-package px-4 py-3 align-middle text-center"><?= getPackageStatusToggle($iccid, $row['package_status']) ?></td>
                
                                    <td class="col-usage px-4 py-3 align-middle">
                                        <div class="flex flex-col w-full gap-1.5">
                                            <div class="flex justify-between items-baseline">
                                                <span class="text-xs font-bold text-slate-800 dark:text-white">
                                                    <?= number_format($usedMB, 2) ?> MB
                                                </span>
                                                <span class="text-xs text-slate-400">
                                                    / <?= number_format($totalMB, 0) ?> MB
                                                </span>
                                            </div>

                                            <?php
                                                $textColor = $pct >= 50
                                                    ? 'text-white'
                                                    : 'text-slate-600 dark:text-slate-300';
                                            ?>

                                            <div class="relative w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3 overflow-hidden">

                                                <div
                                                    class="bg-indigo-600 h-full rounded-full transition-all duration-500"
                                                    style="width: <?= min($pct, 100) ?>%"
                                                ></div>

                                                <span class="absolute inset-0 flex items-center justify-center text-[8px] font-bold <?= $textColor ?>">
                                                    <?= number_format($pct, 1) ?>%
                                                </span>
                                            </div>
                                        </div>
                 
                                    </td>
                                    <td class="col-dates px-4 py-3 align-middle text-right whitespace-nowrap">
                                        <div class="flex flex-col gap-0.5 justify-center h-full">
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400"><?= (!empty($row['active_date'])) ? date("d M Y", $row['active_date']/1000) : '-' ?></span>
                                            <span class="text-[10px] text-slate-400"><?= (!empty($row['expire_date'])) ? date("d M Y", $row['expire_date']/1000) : '-' ?></span>
                                        </div>
                                    </td>
                                    
                                    <td class="col-action px-4 py-3 align-middle text-right sticky-right bg-sticky-light dark:bg-sticky-dark">
                                        <a href="sim-detail.php?iccid=<?= $iccid ?>" class="inline-flex items-center gap-1 px-3 py-1 bg-white dark:bg-slate-800 border dark:border-slate-600 rounded hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 shadow-sm transition-colors">Detail</a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="14" class="p-8 text-center text-slate-500 dark:text-slate-400">No records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-4 py-4 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-center bg-white dark:bg-darkcard gap-4">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">ROWS:</span>
                            <div class="relative">
                                <select onchange="changePageSize(this.value)" class="appearance-none bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-white text-xs font-bold rounded-lg pl-3 pr-8 py-1.5 outline-none focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer">
                                    <option value="10" <?= $pageSize==10?'selected':'' ?>>10</option>
                                    <option value="50" <?= $pageSize==50?'selected':'' ?>>50</option>
                                    <option value="100" <?= $pageSize==100?'selected':'' ?>>100</option>
                                </select>
                                <i class="ph ph-caret-down absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <?php
                            $range = 2; 
                            if ($page > 1) {
                                echo '<a href="?page=1&size='.$pageSize.'&search='.urlencode($s_keyword).'&status='.$f_status.'&tag='.urlencode($f_tag).'&company='.$f_company.'&project='.urlencode($f_project).'" class="btn-page bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-600">1</a>';
                            } else {
                                echo '<span class="btn-page active">1</span>';
                            }
                            if ($page > $range + 2) echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                            for ($i = max(2, $page - $range); $i <= min($totalPages - 1, $page + $range); $i++) {
                                if ($i == $page) echo '<span class="btn-page active">'.$i.'</span>';
                                else echo '<a href="?page='.$i.'&size='.$pageSize.'&search='.urlencode($s_keyword).'&status='.$f_status.'&tag='.urlencode($f_tag).'&company='.$f_company.'&project='.urlencode($f_project).'" class="btn-page bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-600">'.$i.'</a>';
                            }
                            if ($page < $totalPages - $range - 1) echo '<span class="px-1 text-slate-400 text-xs">...</span>';
                            if ($totalPages > 1) {
                                if ($page == $totalPages) echo '<span class="btn-page active">'.$totalPages.'</span>';
                                else echo '<a href="?page='.$totalPages.'&size='.$pageSize.'&search='.urlencode($s_keyword).'&status='.$f_status.'&tag='.urlencode($f_tag).'&company='.$f_company.'&project='.urlencode($f_project).'" class="btn-page bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 border-slate-200 dark:border-slate-600">'.$totalPages.'</a>';
                            }
                            if ($page < $totalPages) {
                                echo '<a href="?page='.($page+1).'&size='.$pageSize.'&search='.urlencode($s_keyword).'&status='.$f_status.'&tag='.urlencode($f_tag).'&company='.$f_company.'&project='.urlencode($f_project).'" class="h-9 px-4 ml-2 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all bg-white dark:bg-slate-800">Next</a>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="tagModal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="flex min-h-screen items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-darkcard rounded-xl shadow-xl w-full max-w-md p-6 border dark:border-slate-700">
                <h3 class="text-lg font-bold mb-4 dark:text-white">Edit Tags</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_tags">
                    <input type="hidden" name="sim_id" id="modal_sim_id">
                    <textarea name="tags_input" id="modal_tags_input" class="w-full border rounded-lg p-3 dark:bg-slate-800 dark:text-white dark:border-slate-600 focus:border-primary outline-none" rows="3"></textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('tagModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="bulkProjectModal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="flex min-h-screen items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-darkcard rounded-xl shadow-xl w-full max-w-md p-6 border dark:border-slate-700">
                <div class="flex items-center gap-3 mb-4">
                    <div class="bg-indigo-100 dark:bg-indigo-900/30 p-2 rounded-full text-primary"><i class="ph ph-briefcase text-xl"></i></div>
                    <h3 class="text-lg font-bold dark:text-white">Set Project Name</h3>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">You are updating Project Name for <span id="bulkCountDisplay" class="font-bold text-primary">0</span> selected SIMs.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_set_project">
                    <input type="hidden" name="bulk_sim_ids" id="bulk_sim_ids">
                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Project Name</label>
                    <input type="text" name="bulk_project_name" placeholder="e.g. CCTV Warehouse A" class="w-full border border-slate-300 dark:border-slate-600 rounded-lg p-2.5 dark:bg-slate-800 dark:text-white focus:border-primary outline-none">
                    <p class="text-[10px] text-slate-400 mt-1">* Leave empty to reset to default.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('bulkProjectModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white font-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loadingOverlay" class="fixed inset-0 z-[60] hidden bg-black/50 flex items-center justify-center">
        <div class="bg-white dark:bg-darkcard p-4 rounded-lg flex items-center gap-3 shadow-xl">
            <i class="ph ph-spinner animate-spin text-2xl text-primary"></i> <span class="dark:text-white font-medium">Processing...</span>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Column Logic
        const defaultColumns = {
            'col-tags': true, 'col-iccid': true, 'col-customer': true, 'col-level': true, 'col-project': true, 'col-msisdn': true,
            'col-imsi': true, 'col-profile': true, 'col-status': true, 'col-package': true, 'col-usage': true, 'col-dates': true, 'col-action': true
        };
        
        function loadColumnState() {
            const saved = localStorage.getItem('simTableCols');
            const state = saved ? JSON.parse(saved) : defaultColumns;
            for(const [k, v] of Object.entries(state)) {
                const cb = document.querySelector(`.col-toggle[data-target="${k}"]`);
                if(cb) {
                    cb.checked = v;
                    toggleColumn(k, v); 
                }
            }
        }

        function toggleColumn(className, forceState = null) {
            const els = document.querySelectorAll(`.${className}`);
            const cb = document.querySelector(`.col-toggle[data-target="${className}"]`);
            const isVisible = forceState !== null ? forceState : cb.checked;
            els.forEach(el => {
                if(isVisible) el.classList.remove('col-hidden');
                else el.classList.add('col-hidden');
            });
            if(forceState === null) {
                const saved = localStorage.getItem('simTableCols');
                const state = saved ? JSON.parse(saved) : defaultColumns;
                state[className] = isVisible;
                localStorage.setItem('simTableCols', JSON.stringify(state));
            }
        }

        // Bulk Logic
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.sim-check');
        const bulkBar = document.getElementById('bulkActionBar');
        
        function updateBulkUI() {
            const checked = document.querySelectorAll('.sim-check:checked');
            document.getElementById('selectedCount').innerText = checked.length;
            if(checked.length > 0) { bulkBar.classList.remove('hidden'); bulkBar.classList.add('flex'); }
            else { bulkBar.classList.add('hidden'); bulkBar.classList.remove('flex'); }
        }

        if(selectAll) selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkUI();
        });
        checkboxes.forEach(cb => cb.addEventListener('change', updateBulkUI));

        function openBulkProjectModal() {
            const checked = document.querySelectorAll('.sim-check:checked');
            if(checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('bulkCountDisplay').innerText = checked.length;
            document.getElementById('bulk_sim_ids').value = ids;
            document.getElementById('bulkProjectModal').classList.remove('hidden');
        }

        // Helpers
        function togglePackageStatus(iccid, checkbox) {
            document.getElementById('loadingOverlay').classList.remove('hidden');
            const fd = new FormData();
            fd.append('action', 'toggle_package'); fd.append('iccid', iccid); fd.append('state', checkbox.checked ? 'true' : 'false');
            fetch('sim-list.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=>{
                document.getElementById('loadingOverlay').classList.add('hidden');
                if(!d.success) { checkbox.checked = !checkbox.checked; alert('Failed'); }
            });
        }
        
        function openTagModal(iccid, tags) {
            document.getElementById('modal_sim_id').value = iccid;
            document.getElementById('modal_tags_input').value = tags;
            document.getElementById('tagModal').classList.remove('hidden');
        }

        function changePageSize(size) {
            const url = new URL(window.location.href);
            url.searchParams.set('size', size);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        document.addEventListener('DOMContentLoaded', loadColumnState);
    </script>
</body>
</html>