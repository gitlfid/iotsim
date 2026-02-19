<?php
include 'config.php';
checkLogin();

// --- ACCESS CONTROL ---
if ($_SESSION['role'] !== 'superadmin') {
    echo "<script>alert('Access Denied. IT Team Only.'); window.location='sim-list';</script>";
    exit();
}

// =================================================================================
// BACKEND API HANDLER
// =================================================================================
if (isset($_POST['action'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    // 1. GET COMPANIES HIERARCHY
    if ($_POST['action'] == 'get_companies') {
        $sql = "SELECT id, company_name, partner_code, level, parent_id FROM companies WHERE partner_code IS NOT NULL AND partner_code != '' ORDER BY company_name ASC";
        $res = $conn->query($sql);
        
        $all = [];
        if($res) {
            while($row = $res->fetch_assoc()) $all[] = $row;
        }

        $parents = [];
        $children = [];
        
        foreach ($all as $c) {
            if ($c['level'] == 1) {
                $c['children'] = [];
                $parents[$c['id']] = $c;
            } else {
                $children[] = $c;
            }
        }
        
        foreach ($children as $child) {
            $pid = $child['parent_id'];
            if (isset($parents[$pid])) {
                $parents[$pid]['children'][] = $child;
            } else {
                // Handle orphan L2 if needed
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => array_values($parents)]);
        exit;
    }

    // 2. START SESSION (Buat Log History Baru)
    if ($_POST['action'] == 'start_session') {
        $names = $_POST['names']; // String gabungan nama company
        $syncId = uniqid('sync_');
        
        $stmt = $conn->prepare("INSERT INTO sync_logs (sync_id, company_name, start_time, status, total_records, processed) VALUES (?, ?, NOW(), 'running', 0, 0)");
        $stmt->bind_param("ss", $syncId, $names);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'sync_id' => $syncId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }

    // 3. INIT COMPANY (Cek Total Data)
    if ($_POST['action'] == 'init_company') {
        $partner_code = $_POST['partner_code'];
        $syncId = $_POST['sync_id'];
        
        $apiCheck = getSimListFromApi($partner_code, 1, 1);
        
        if ($apiCheck && isset($apiCheck['total'])) {
            $total = intval($apiCheck['total']);
            // Update total records di log
            $conn->query("UPDATE sync_logs SET total_records = total_records + $total WHERE sync_id = '$syncId'");
            
            echo json_encode([
                'status' => 'success', 
                'total_records' => $total,
                'total_pages' => ceil($total / 100) // Batch size 100 (sesuai script lama)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed connect to API: ' . $partner_code]);
        }
        exit;
    }

    // 4. PROCESS BATCH (Proses Data Per Halaman)
    if ($_POST['action'] == 'process_batch') {
        $partner_code = $_POST['partner_code'];
        $page = intval($_POST['page']);
        $pageSize = 100;
        $syncId = $_POST['sync_id'];
        
        $processedCount = 0;

        // A. Get List
        $apiData = getSimListFromApi($partner_code, $page, $pageSize);
        
        if ($apiData && !empty($apiData['rows'])) {
            
            // Get Company ID Lokal
            $compId = 0;
            $qComp = $conn->query("SELECT id FROM companies WHERE partner_code = '$partner_code' LIMIT 1");
            if($qComp->num_rows > 0) $compId = $qComp->fetch_assoc()['id'];

            foreach ($apiData['rows'] as $row) {
                // Mapping Dasar
                $iccid = $row['assetId'];
                $profileIccid = $row['iccid'];
                $imsi = $row['imsi'];
                $msisdn = $row['msisdn'];
                $statusApi = $row['status'];
                $usedFlow = floatval($row['usedFlow'] ?? 0); 
                $totalFlow = floatval($row['totalFlow'] ?? 0);

                // B. Get Bundle Detail (Deep Sync - Agar Data Lengkap)
                $pkgStatus = '0';
                $valStart = null;
                $valEnd = null;
                
                $bundleRes = getSimBundlesFromApi($iccid, 1); // 1 = Valid
                if ($bundleRes && isset($bundleRes['data'][0]['simServiceVO'])) {
                    $vo = $bundleRes['data'][0]['simServiceVO'];
                    $pkgStatus = $vo['orderStatus'] ?? '0';
                    $valStart = $vo['validityStart'] ?? null;
                    $valEnd = $vo['validityEnd'] ?? null;
                }

                // C. Upsert Database
                $sql = "INSERT INTO sims (iccid, profile_iccid, imsi, msisdn, company_id, status, package_status, active_date, expire_date, used_flow, total_flow, last_sync) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE 
                        profile_iccid = VALUES(profile_iccid),
                        imsi = VALUES(imsi),
                        msisdn = VALUES(msisdn),
                        company_id = VALUES(company_id),
                        status = VALUES(status),
                        package_status = VALUES(package_status),
                        active_date = VALUES(active_date),
                        expire_date = VALUES(expire_date),
                        used_flow = VALUES(used_flow),
                        total_flow = VALUES(total_flow),
                        last_sync = NOW()";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ssssissdddd", $iccid, $profileIccid, $imsi, $msisdn, $compId, $statusApi, $pkgStatus, $valStart, $valEnd, $usedFlow, $totalFlow);
                    $stmt->execute();
                    $processedCount++;
                }
            }

            // Update Log Progress
            $conn->query("UPDATE sync_logs SET processed = processed + $processedCount WHERE sync_id = '$syncId'");

            echo json_encode(['status' => 'success', 'processed' => $processedCount]);
        } else {
            // Bisa jadi halaman kosong atau error
            echo json_encode(['status' => 'success', 'processed' => 0, 'message' => 'Empty batch']);
        }
        exit;
    }

    // 5. FINISH SESSION
    if ($_POST['action'] == 'finish_session') {
        $syncId = $_POST['sync_id'];
        $conn->query("UPDATE sync_logs SET status = 'completed', end_time = NOW() WHERE sync_id = '$syncId'");
        echo json_encode(['status' => 'success']);
        exit;
    }

    // 6. GET HISTORY
    if ($_POST['action'] == 'get_history') {
        $logs = [];
        $q = $conn->query("SELECT * FROM sync_logs ORDER BY id DESC LIMIT 10");
        while($r = $q->fetch_assoc()) $logs[] = $r;
        echo json_encode(['status'=>'success', 'data'=>$logs]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sync Data - IoT Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { colors: { primary: '#4F46E5', darkcard: '#24303F', darkbg: '#1A222C' } }
            }
        }
    </script>
    <style>
        .custom-checkbox:checked { background-color: #4F46E5; border-color: #4F46E5; }
        .scroller::-webkit-scrollbar { width: 6px; }
        .scroller::-webkit-scrollbar-thumb { background-color: #CBD5E1; border-radius: 3px; }
        .dark .scroller::-webkit-scrollbar-thumb { background-color: #475569; }
        .tree-branch { position: relative; }
        .tree-branch::before {
            content: ''; position: absolute; top: -14px; left: -18px; width: 16px; height: 28px;
            border-bottom: 2px solid #E2E8F0; border-left: 2px solid #E2E8F0; border-bottom-left-radius: 8px;
        }
        .dark .tree-branch::before { border-color: #475569; }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 font-sans antialiased overflow-hidden">
    
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 relative">
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Data Synchronization</h1>
                        <p class="text-sm text-slate-500">Robust sync system with full data integrity.</p>
                    </div>
                    
                    <button onclick="startSyncSequence()" id="startBtn" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-lg shadow-lg shadow-indigo-500/30 flex items-center gap-2 transition-all active:scale-95">
                        <i class="ph ph-arrows-clockwise text-lg"></i> Start Sync
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-180px)]">
                    
                    <div class="lg:col-span-1 bg-white dark:bg-darkcard rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col overflow-hidden">
                        <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                            <h3 class="font-bold text-slate-700 dark:text-white">Target Selection</h3>
                            <label class="flex items-center gap-2 text-xs font-bold text-primary cursor-pointer select-none">
                                <input type="checkbox" id="selectAll" class="rounded text-primary focus:ring-0"> Select All
                            </label>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 scroller" id="companyList">
                            <div class="flex flex-col items-center justify-center h-40 text-slate-400 gap-2">
                                <i class="ph ph-spinner animate-spin text-2xl"></i> 
                                <span class="text-xs">Loading Companies...</span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 flex flex-col gap-6 h-full overflow-hidden">
                        
                        <div id="syncMonitor" class="hidden bg-white dark:bg-darkcard rounded-xl p-5 border border-indigo-100 dark:border-indigo-900/30 shadow-md transition-all">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="relative flex h-3 w-3">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                                    </span>
                                    <div>
                                        <h4 class="font-bold text-indigo-600 dark:text-indigo-400 text-sm" id="monitorStatus">Preparing...</h4>
                                        <p class="text-xs text-slate-400" id="monitorSub">Please keep this tab open</p>
                                    </div>
                                </div>
                                <span class="text-lg font-bold font-mono text-slate-700 dark:text-white" id="monitorPct">0%</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-3 overflow-hidden mb-3">
                                <div id="monitorBar" class="bg-indigo-600 h-full rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400 font-mono bg-slate-50 dark:bg-slate-800 p-2 rounded">
                                <span id="monitorStep">Waiting to start...</span>
                                <span id="monitorCount">0 / 0</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkcard rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 flex-1 flex flex-col overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between">
                                <h3 class="font-bold text-slate-700 dark:text-white">Sync History</h3>
                                <button onclick="loadHistory()" class="text-xs text-primary hover:underline"><i class="ph ph-arrow-counter-clockwise"></i> Refresh</button>
                            </div>
                            <div class="flex-1 overflow-y-auto scroller">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs uppercase text-slate-500 sticky top-0 backdrop-blur-sm">
                                        <tr>
                                            <th class="px-4 py-3">Time</th>
                                            <th class="px-4 py-3">Target</th>
                                            <th class="px-4 py-3 text-center">Total</th>
                                            <th class="px-4 py-3 text-center">Processed</th>
                                            <th class="px-4 py-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody" class="divide-y divide-slate-100 dark:divide-slate-700"></tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        let syncQueue = [];
        let currentSyncId = null;
        let totalItemsInBatch = 0;
        let processedInBatch = 0;

        document.addEventListener('DOMContentLoaded', () => {
            loadCompanies();
            loadHistory();
        });

        // FIX: URL diganti dari 'sync-data.php' menjadi 'sync-data' (tanpa .php) agar tidak terkena redirect 301 yang menghilangkan POST data

        // 1. Load Companies
        function loadCompanies() {
            fetch('sync-data', { method: 'POST', body: new URLSearchParams({action: 'get_companies'}) })
            .then(r => r.json())
            .then(res => {
                const list = document.getElementById('companyList');
                list.innerHTML = '';
                if(res.status === 'success' && res.data.length > 0) {
                    res.data.forEach(parent => {
                        let html = buildCompanyItem(parent, false);
                        if(parent.children && parent.children.length > 0) {
                            html += `<div class="ml-8 border-l-2 border-slate-100 dark:border-slate-700 pl-4 space-y-2 mt-2">`;
                            parent.children.forEach(child => {
                                html += buildCompanyItem(child, true);
                            });
                            html += `</div>`;
                        }
                        const wrapper = document.createElement('div');
                        wrapper.className = "mb-3";
                        wrapper.innerHTML = html;
                        list.appendChild(wrapper);
                    });
                } else {
                    list.innerHTML = '<div class="p-4 text-center text-sm text-slate-500">No companies found.</div>';
                }
            });
        }

        function buildCompanyItem(c, isChild) {
            const icon = isChild ? 'ph-arrow-elbow-down-right text-slate-400' : 'ph-buildings text-indigo-500';
            const bgHover = 'hover:bg-slate-50 dark:hover:bg-slate-800/50';
            const extraClass = isChild ? 'tree-branch mt-1' : '';
            return `
            <div class="${extraClass} flex items-center p-2 rounded-lg ${bgHover} transition-colors cursor-pointer" onclick="this.querySelector('input').click()">
                <input type="checkbox" value="${c.partner_code}" data-name="${c.company_name}" class="co-check w-4 h-4 rounded text-primary focus:ring-0 border-slate-300 dark:border-slate-600 dark:bg-slate-800 mr-3" onclick="event.stopPropagation()">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <i class="ph ${icon} text-lg"></i>
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">${c.company_name}</span>
                    </div>
                    ${c.partner_code ? `<p class="text-[10px] text-slate-400 font-mono ml-7">${c.partner_code}</p>` : ''}
                </div>
            </div>`;
        }

        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.co-check').forEach(cb => cb.checked = this.checked);
        });

        // 2. Main Sync Logic (Client Side Loop)
        function startSyncSequence() {
            const checked = Array.from(document.querySelectorAll('.co-check:checked')).map(cb => ({
                code: cb.value,
                name: cb.dataset.name
            }));

            if (checked.length === 0) return alert("Select at least one company.");
            if (!confirm(`Start Deep Sync for ${checked.length} companies? (Please keep tab open)`)) return;

            // UI Init
            document.getElementById('startBtn').disabled = true;
            document.getElementById('startBtn').classList.add('opacity-50');
            document.getElementById('syncMonitor').classList.remove('hidden');
            
            syncQueue = checked;
            
            // Create Session Log
            const names = checked.length > 1 ? checked.length + " Companies" : checked[0].name;
            const fd = new FormData();
            fd.append('action', 'start_session');
            fd.append('names', names);

            fetch('sync-data', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    currentSyncId = res.sync_id;
                    loadHistory(); // Show running row
                    processNextCompany(0);
                } else {
                    alert("Failed to start session.");
                }
            });
        }

        function processNextCompany(index) {
            if (index >= syncQueue.length) {
                finishSync();
                return;
            }

            const target = syncQueue[index];
            updateMonitor(target.name, 0, "Connecting to API...");

            const fd = new FormData();
            fd.append('action', 'init_company');
            fd.append('partner_code', target.code);
            fd.append('sync_id', currentSyncId);

            fetch('sync-data', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    const totalPages = res.total_pages;
                    const totalRecs = res.total_records;
                    
                    if(totalRecs === 0) {
                        processNextCompany(index + 1);
                    } else {
                        updateMonitor(target.name, 0, `Syncing 1 of ${totalPages} batches...`);
                        processBatchLoop(target, 1, totalPages, index);
                    }
                } else {
                    console.error("Init failed:", res.message);
                    processNextCompany(index + 1);
                }
            })
            .catch(err => {
                console.error("Network error:", err);
                processNextCompany(index + 1);
            });
        }

        function processBatchLoop(target, currentPage, totalPages, queueIndex) {
            if (currentPage > totalPages) {
                processNextCompany(queueIndex + 1);
                return;
            }

            const pct = Math.round((currentPage / totalPages) * 100);
            updateMonitor(target.name, pct, `Processing batch ${currentPage}/${totalPages}...`);

            const fd = new FormData();
            fd.append('action', 'process_batch');
            fd.append('partner_code', target.code);
            fd.append('page', currentPage);
            fd.append('sync_id', currentSyncId);

            fetch('sync-data', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                loadHistory(); // Update counter in table
                processBatchLoop(target, currentPage + 1, totalPages, queueIndex);
            })
            .catch(err => {
                // Retry once on error
                console.warn("Retrying batch " + currentPage);
                setTimeout(() => processBatchLoop(target, currentPage, totalPages, queueIndex), 2000);
            });
        }

        function finishSync() {
            const fd = new FormData();
            fd.append('action', 'finish_session');
            fd.append('sync_id', currentSyncId);
            fetch('sync-data', { method: 'POST', body: fd })
            .then(() => {
                updateMonitor("Completed", 100, "All sync tasks finished.");
                document.getElementById('startBtn').disabled = false;
                document.getElementById('startBtn').classList.remove('opacity-50');
                loadHistory();
                setTimeout(() => {
                    document.getElementById('syncMonitor').classList.add('hidden');
                }, 3000);
            });
        }

        function updateMonitor(title, pct, step) {
            document.getElementById('monitorStatus').innerText = title;
            document.getElementById('monitorPct').innerText = pct + '%';
            document.getElementById('monitorBar').style.width = pct + '%';
            document.getElementById('monitorStep').innerText = step;
        }

        function loadHistory() {
            fetch('sync-data', { method: 'POST', body: new URLSearchParams({action: 'get_history'}) })
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('historyTableBody');
                tbody.innerHTML = '';
                if(res.data) {
                    res.data.forEach(h => {
                        let badge = h.status === 'completed' 
                            ? '<span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded">COMPLETED</span>' 
                            : '<span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded animate-pulse">RUNNING</span>';
                        
                        let html = `
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 border-b dark:border-slate-700">
                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-xs">${h.start_time}</td>
                            <td class="px-4 py-3 font-bold text-slate-700 dark:text-white max-w-[200px] truncate" title="${h.company_name}">${h.company_name}</td>
                            <td class="px-4 py-3 text-center font-mono text-xs">${h.total_records}</td>
                            <td class="px-4 py-3 text-center font-mono font-bold text-indigo-600 text-xs">${h.processed}</td>
                            <td class="px-4 py-3 text-center">${badge}</td>
                        </tr>`;
                        tbody.insertAdjacentHTML('beforeend', html);
                    });
                }
            });
        }
    </script>
</body>
</html>