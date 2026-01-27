<?php 
include 'config.php';
checkLogin();

// Validasi ICCID
if (!isset($_GET['iccid']) || empty($_GET['iccid'])) {
    echo "<script>alert('Invalid ICCID'); window.location='sim-list.php';</script>";
    exit();
}

$iccid = $_GET['iccid'];

// --- 1. SETUP FILTER GRAFIK USAGE ---
$viewType = isset($_GET['view_type']) ? $_GET['view_type'] : 'month'; 
if ($viewType == 'day') {
    $defaultTo = date('Y-m-d');
    $defaultFrom = date('Y-m-d', strtotime('-30 days'));
    $inputType = 'date';
} else {
    $defaultTo = date('Y-m');
    $defaultFrom = date('Y-m', strtotime('-5 months'));
    $inputType = 'month';
}
$filterFrom = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : $defaultFrom;
$filterTo = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : $defaultTo;

// --- 2. API CALLS ---

// A. Detail Utama SIM
$apiResponse = getSimDetailFromApi($iccid);
$data = $apiResponse['data'] ?? null;

// B. Connection Info (Detail Lokasi, APN, Operator)
$apiConnection = getSimConnectionFromApi($iccid);
$connData = $apiConnection['data'] ?? null;

// B2. Realtime Status Check (Khusus untuk Badge Online/Offline)
$apiStatus = getSimStatusRealtime($iccid);
$statusData = $apiStatus['data'] ?? null;

// C. CDR (Call Detail Record) - Preview 5 data
$apiCdr = getSimCdrFromApi($iccid, 1, 5); 
$cdrList = $apiCdr['rows'] ?? [];

// D. Bundles
$apiBundleValid = getSimBundlesFromApi($iccid, 1); 
$validBundles = $apiBundleValid['data'] ?? [];

$apiBundleExpired = getSimBundlesFromApi($iccid, 2); 
$expiredBundles = $apiBundleExpired['data'] ?? [];

// E. Events Log
$apiEvents = getSimEventsFromApi($iccid);
$eventList = $apiEvents['data'] ?? [];

// F. Usage History Chart
$chartLabels = [];
$chartValues = [];
if ($viewType == 'day') {
    $apiUsage = getSimDailyUsageFromApi($iccid, $filterFrom, $filterTo);
} else {
    $apiUsage = getSimMonthUsageFromApi($iccid, $filterFrom, $filterTo);
}
$usageList = $apiUsage['data']['usageDetailList'] ?? [];

if (!empty($usageList)) {
    foreach ($usageList as $u) {
        if ($viewType == 'day') {
            $dateObj = DateTime::createFromFormat('Y-m-d', $u['time']);
            $chartLabels[] = $dateObj ? $dateObj->format('d M') : $u['time'];
        } else {
            $dateObj = DateTime::createFromFormat('Y-m', $u['time']);
            $chartLabels[] = $dateObj ? $dateObj->format('M Y') : $u['time'];
        }
        $mbValue = floatval($u['amount']) / (1024 * 1024); 
        $chartValues[] = round($mbValue, 2);
    }
}

if (!$data) {
    echo "<script>alert('Failed to fetch data'); window.location='sim-list.php';</script>";
    exit();
}

// --- 3. LOGIC FORMATTING DATA ---

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

// FIX: Update function time_elapsed_string agar support PHP 8.2+ (Dynamic Property)
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Hitung minggu dan sisa hari secara manual
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    // Mapping nilai ke array lokal, bukan ke object $diff langsung
    $time_values = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s
    ];

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second'
    );

    foreach ($string as $k => &$v) {
        if ($time_values[$k]) {
            $v = $time_values[$k] . ' ' . $v . ($time_values[$k] > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// --- LOGIC STATUS KONEKSI (ONLINE/OFFLINE) ---
// Mengambil status dari API getSimConStatus (prioritas) atau fallback ke API connection
$connectionStatusCode = isset($statusData['status']) ? $statusData['status'] : ($connData['status'] ?? '2');
$connectionBadge = '';

if ($connectionStatusCode == '1') {
    // Online
    $connectionBadge = '
    <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
        </span>
        Online
    </span>';
} else {
    // Offline
    $connectionBadge = '
    <span class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-xs font-bold border border-slate-200">
        <span class="relative flex h-2 w-2">
          <span class="relative inline-flex rounded-full h-2 w-2 bg-slate-400"></span>
        </span>
        Offline
    </span>';
}

$expireDate = isset($data['expireDate']) ? date("d M Y, H:i", $data['expireDate'] / 1000) : '-';
$totalBytes = floatval($data['totalFlow'] ?? 0);
$usedBytes = floatval($data['usedFlow'] ?? 0);
$remainingBytes = $totalBytes - $usedBytes;

$totalDisplay = formatBytes($totalBytes);
$usedDisplay = formatBytes($usedBytes);
$remainingDisplay = formatBytes($remainingBytes);

// Data Koneksi Detail (APN, Lokasi, dll tetap ambil dari $connData)
$connApn = $connData['apn'] ?? '-';
$connLocation = $connData['location'] ?? '-';
$connFlag = $connData['nationalFlag'] ?? '';
$connNetwork = $connData['mnc'] ?? '-'; 
$connLastSessionSize = formatBytes(floatval($connData['lastSession'] ?? 0));
$connLastTime = isset($connData['lastSessionTime']) ? date("Y-m-d H:i:s", $connData['lastSessionTime'] / 1000) : '-';

$pieChartData = json_encode([$usedBytes, $remainingBytes]); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Detail SIM: <?= $iccid ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
                    
                    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <a href="sim-list.php" class="p-2 bg-white dark:bg-darkcard border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                <i class="ph ph-arrow-left text-xl"></i>
                            </a>
                            <div>
                                <h2 class="text-2xl font-bold text-slate-800 dark:text-white">SIM Detail</h2>
                                <p class="text-sm text-slate-500 font-mono">Asset ID: <?= $iccid ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <span class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 text-sm font-bold border border-indigo-100">
                                Order ID: <?= $data['orderId'] ?? '-' ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        
                        <div class="lg:col-span-2 bg-white dark:bg-darkcard rounded-xl shadow-soft border border-slate-100 dark:border-slate-800 p-6 flex flex-col justify-between">
                            
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-bold text-lg text-slate-800 dark:text-white flex items-center gap-2">
                                    <i class="ph ph-cpu text-indigo-600"></i> Device Status
                                </h3>
                                <div class="flex gap-2">
                                    <?= getStatusBadge($data['lifecycle'] ?? '0') ?>
                                    
                                    <?= $connectionBadge ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">IMSI</p>
                                    <p class="font-mono text-sm font-bold text-slate-800 dark:text-white truncate" title="<?= $data['imsi'] ?>"><?= $data['imsi'] ?? '-' ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">IMEI</p>
                                    <p class="font-mono text-sm font-bold text-slate-800 dark:text-white truncate" title="<?= $data['imei'] ?>"><?= $data['imei'] ?? '-' ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Detected IMEI</p>
                                    <p class="font-mono text-sm font-bold text-slate-800 dark:text-white truncate" title="<?= $data['detectedImei'] ?>"><?= $data['detectedImei'] ?? '-' ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Expires</p>
                                    <p class="font-mono text-sm font-bold text-slate-800 dark:text-white truncate"><?= $expireDate ?></p>
                                </div>
                            </div>

                            <div class="border-t border-slate-100 dark:border-slate-700 mb-6 border-dashed"></div>

                            <div class="grid grid-cols-2 md:grid-cols-3 gap-y-6 gap-x-4">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Location</p>
                                    <div class="flex items-center gap-2">
                                        <?php if($connFlag): ?>
                                            <img src="<?= $connFlag ?>" alt="Flag" class="h-3.5 w-auto rounded-sm shadow-sm">
                                        <?php endif; ?>
                                        <span class="text-sm font-bold text-slate-700 dark:text-white truncate"><?= $connLocation ?></span>
                                    </div>
                                </div>
                                <div class="col-span-1 md:col-span-2">
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Network Operator</p>
                                    <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400 truncate block" title="<?= $connNetwork ?>"><?= $connNetwork ?></span>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">APN</p>
                                    <span class="text-sm font-mono text-slate-600 dark:text-slate-300"><?= $connApn ?: '-' ?></span>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Last Session</p>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold">
                                        <?= $connLastSessionSize ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Last Active</p>
                                    <span class="text-xs font-mono text-slate-600 dark:text-slate-300"><?= $connLastTime ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-1 bg-white dark:bg-darkcard rounded-xl shadow-soft border border-slate-100 dark:border-slate-800 p-5 flex flex-col">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white">Current Plan</h3>
                                    <p class="text-xs text-slate-500">Usage Overview</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-slate-400 uppercase font-bold">Total</p>
                                    <p class="text-lg font-bold text-indigo-600 dark:text-indigo-400"><?= $totalDisplay ?></p>
                                </div>
                            </div>
                            
                            <div class="flex-1 flex items-center justify-center relative min-h-[180px]">
                                <div id="usagePieChart" class="w-full flex justify-center"></div>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none pt-4">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Used</span>
                                    <span class="text-xl font-bold text-slate-800 dark:text-white"><?= $usedDisplay ?></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                    <div>
                                        <p class="text-[10px] text-slate-400 uppercase font-bold">Used</p>
                                        <p class="text-xs font-bold text-slate-700 dark:text-white"><?= $usedDisplay ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                    <div>
                                        <p class="text-[10px] text-slate-400 uppercase font-bold">Free</p>
                                        <p class="text-xs font-bold text-slate-700 dark:text-white"><?= $remainingDisplay ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-xl shadow-soft border border-slate-100 dark:border-slate-800 mb-6">
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b border-slate-100 dark:border-slate-700 pb-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Usage History</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Analyze data consumption trend.</p>
                            </div>
                        </div>

                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 items-end mb-6">
                            <input type="hidden" name="iccid" value="<?= $iccid ?>">
                            
                            <div class="w-full md:w-auto">
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5 uppercase">View By</label>
                                <div class="relative">
                                    <select name="view_type" id="viewTypeSelect" onchange="updateInputType()" class="w-full md:w-32 appearance-none rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm py-2.5 px-4 pr-8 focus:border-primary focus:ring-1 focus:ring-primary outline-none dark:text-white transition-all cursor-pointer">
                                        <option value="month" <?= $viewType == 'month' ? 'selected' : '' ?>>Month</option>
                                        <option value="day" <?= $viewType == 'day' ? 'selected' : '' ?>>Day</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                        <i class="ph ph-caret-down"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="w-full md:w-auto flex-1">
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5 uppercase">From</label>
                                <input type="<?= $inputType ?>" name="date_from" value="<?= $filterFrom ?>" class="date-input w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm py-2.5 px-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none dark:text-white transition-all">
                            </div>

                            <div class="hidden md:flex items-center justify-center pb-3 text-slate-400">
                                <i class="ph ph-arrow-right"></i>
                            </div>

                            <div class="w-full md:w-auto flex-1">
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5 uppercase">To</label>
                                <input type="<?= $inputType ?>" name="date_to" value="<?= $filterTo ?>" class="date-input w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm py-2.5 px-4 focus:border-primary focus:ring-1 focus:ring-primary outline-none dark:text-white transition-all">
                            </div>

                            <div class="w-full md:w-auto">
                                <button type="submit" class="w-full md:w-auto flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm transition-all shadow-lg shadow-indigo-500/20 active:scale-95">
                                    <i class="ph ph-funnel"></i> Apply Filter
                                </button>
                            </div>
                        </form>
                        
                        <div id="monthlyUsageChart" class="w-full h-[350px]"></div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-xl shadow-soft border border-slate-100 dark:border-slate-800 mb-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white">Bundles</h3>
                        </div>

                        <div class="flex gap-1 mb-4 border-b border-slate-100 dark:border-slate-700">
                            <button onclick="switchBundleTab('valid')" id="tabBtn-valid" class="px-4 py-2 text-sm font-medium border-b-2 border-red-500 text-red-600 dark:text-red-400 transition-colors bg-red-50 dark:bg-red-900/20 rounded-t-lg">
                                Valid
                            </button>
                            <button onclick="switchBundleTab('expired')" id="tabBtn-expired" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white transition-colors">
                                Expired
                            </button>
                        </div>

                        <div id="tabContent-valid" class="block">
                            <div class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-700">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                                        <tr>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-24">Status</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Bundle Code</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Bundle Name</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Limit</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Period</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Cycles</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <?php if(!empty($validBundles)): foreach($validBundles as $b): ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                            <td class="p-4">
                                                <span class="px-2 py-1 rounded text-xs font-medium border border-green-200 bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800">
                                                    <?= htmlspecialchars($b['status']) ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['bundle'] ?></td>
                                            <td class="p-4 text-sm text-slate-800 dark:text-white font-medium"><?= $b['bundleNameEn'] ?? $b['bundleName'] ?></td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['limit'] ?></td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['period'] ?></td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['cycle'] ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="6" class="p-8 text-center text-slate-500">No valid bundles found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="tabContent-expired" class="hidden">
                            <div class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-700">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                                        <tr>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-24">Status</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Bundle Code</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Bundle Name</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Limit</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Period</th>
                                            <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Cycles</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <?php if(!empty($expiredBundles)): foreach($expiredBundles as $b): ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors opacity-75">
                                            <td class="p-4">
                                                <span class="px-2 py-1 rounded text-xs font-medium border border-slate-200 bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                                    Expired
                                                </span>
                                            </td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['bundle'] ?></td>
                                            <td class="p-4 text-sm text-slate-800 dark:text-white font-medium"><?= $b['bundleNameEn'] ?? $b['bundleName'] ?></td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['limit'] ?></td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['period'] ?></td>
                                            <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $b['cycle'] ?></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="6" class="p-8 text-center text-slate-500">No expired bundles found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-xl shadow-soft border border-slate-100 dark:border-slate-800 mb-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Latest Connection (CDR)</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Recent data sessions log.</p>
                            </div>
                            <span class="px-3 py-1 rounded bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300">
                                Preview
                            </span>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-700">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 dark:bg-slate-700/50">
                                    <tr>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Start Time</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Volume</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Network</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Up/Down</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <?php if(!empty($cdrList)): foreach($cdrList as $cdr): 
                                        $startTime = date("Y-m-d H:i:s", $cdr['cdrBeginTime'] / 1000);
                                        $volume = formatBytes($cdr['flow']);
                                        $network = $cdr['operatorName'];
                                        $logo = $cdr['mnoLogo'];
                                        $location = $cdr['coverMcc'];
                                        $duration = $cdr['sessionDuration'] > 0 ? gmdate("H:i:s", $cdr['sessionDuration']) : '-';
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="p-4 text-sm font-mono text-slate-600 dark:text-slate-300">
                                            <?= $startTime ?>
                                        </td>
                                        <td class="p-4 text-sm font-bold text-slate-800 dark:text-white">
                                            <?= $volume ?>
                                        </td>
                                        <td class="p-4">
                                            <div class="flex items-center gap-2">
                                                <?php if($logo): ?>
                                                    <img src="<?= $logo ?>" alt="Flag" class="h-3 w-auto rounded-sm shadow-sm">
                                                <?php endif; ?>
                                                <div class="flex flex-col">
                                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= $location ?></span>
                                                    <span class="text-[10px] text-slate-400"><?= $network ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <i class="ph ph-arrows-down-up text-indigo-500 text-lg"></i>
                                        </td>
                                        <td class="p-4 text-center text-xs font-mono text-slate-600 dark:text-slate-300">
                                            <?= $duration ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-500">No connection history found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 text-center">
                            <a href="sim-cdr.php?iccid=<?= $iccid ?>" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg shadow-lg shadow-indigo-500/20 transition-all">
                                <i class="ph ph-list-dashes"></i> View Full Connection History
                            </a>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-xl shadow-soft border border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white">Events Log</h3>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-700">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 dark:bg-slate-700/50">
                                    <tr>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Type</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">SubType</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Result</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Source</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">User</th>
                                        <th class="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <?php if(!empty($eventList)): foreach($eventList as $ev): 
                                        $reqTime = date("Y-m-d H:i", $ev['reqAt'] / 1000);
                                        $timeAgo = time_elapsed_string("@" . ($ev['reqAt'] / 1000));
                                        
                                        $resultBadge = ($ev['respSuccess'] == 1) 
                                            ? '<span class="inline-flex items-center gap-1 rounded border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-400">Succeeded</span>'
                                            : '<span class="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">Failed</span>';
                                        
                                        $sourceIcon = ($ev['source'] == 1) ? '<i class="ph ph-desktop text-slate-400"></i>' : '<i class="ph ph-globe text-slate-400"></i>';
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="p-4 text-sm font-medium text-slate-800 dark:text-white"><?= $ev['eventType'] ?></td>
                                        <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $ev['eventSubtype'] ?></td>
                                        <td class="p-4"><?= $resultBadge ?></td>
                                        <td class="p-4 text-sm text-slate-600 dark:text-slate-300">
                                            <div class="flex flex-col">
                                                <span class="font-mono text-xs text-slate-500"><?= $reqTime ?></span>
                                                <span class="text-[10px] text-slate-400"><?= $timeAgo ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-sm text-slate-600 dark:text-slate-300 flex items-center gap-2">
                                            <?= $sourceIcon ?> 
                                            <?= ($ev['source'] == 1) ? 'web v4' : 'API' ?>
                                        </td>
                                        <td class="p-4 text-sm text-slate-600 dark:text-slate-300"><?= $ev['userEmail'] ?: '-' ?></td>
                                        <td class="p-4 text-sm text-slate-600 dark:text-slate-300 max-w-xs truncate" title="<?= htmlspecialchars($ev['description']) ?>">
                                            <?= htmlspecialchars($ev['description']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="7" class="p-8 text-center text-slate-500">No events found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function updateInputType() {
            var viewType = document.getElementById("viewTypeSelect").value;
            var inputs = document.querySelectorAll(".date-input");
            inputs.forEach(function(input) {
                input.type = (viewType === 'day') ? 'date' : 'month';
            });
        }

        function switchBundleTab(tabName) {
            document.getElementById('tabContent-valid').classList.add('hidden');
            document.getElementById('tabContent-expired').classList.add('hidden');
            document.getElementById('tabContent-' + tabName).classList.remove('hidden');

            const btnValid = document.getElementById('tabBtn-valid');
            const btnExpired = document.getElementById('tabBtn-expired');
            const inactiveClass = "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white bg-transparent";
            const activeClass = "border-red-500 text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-t-lg";

            if(tabName === 'valid') {
                btnValid.className = "px-4 py-2 text-sm font-medium border-b-2 transition-colors " + activeClass;
                btnExpired.className = "px-4 py-2 text-sm font-medium border-b-2 transition-colors " + inactiveClass;
            } else {
                btnValid.className = "px-4 py-2 text-sm font-medium border-b-2 transition-colors " + inactiveClass;
                btnExpired.className = "px-4 py-2 text-sm font-medium border-b-2 transition-colors " + activeClass;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            
            var pieData = <?= $pieChartData ?>;
            var usedMB = (pieData[0] / (1024*1024)).toFixed(2);
            var remainingMB = (pieData[1] / (1024*1024)).toFixed(2);

            var pieOptions = {
                series: [parseFloat(usedMB), parseFloat(remainingMB)],
                labels: ['Used', 'Remaining'],
                chart: { type: 'donut', height: 200, fontFamily: 'Inter, sans-serif' },
                colors: ['#4F46E5', '#E2E8F0'], 
                plotOptions: {
                    pie: {
                        donut: {
                            size: '75%',
                            labels: { show: false }
                        }
                    }
                },
                dataLabels: { enabled: false },
                stroke: { show: false },
                legend: { show: false }, 
                tooltip: { y: { formatter: function(value) { return value + " MB"; } } }
            };
            var pieChart = new ApexCharts(document.querySelector("#usagePieChart"), pieOptions);
            pieChart.render();


            var barLabels = <?= json_encode($chartLabels) ?>;
            var barValues = <?= json_encode($chartValues) ?>;

            var barOptions = {
                series: [{
                    name: 'Data Usage',
                    data: barValues
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    fontFamily: 'Inter, sans-serif',
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: '<?= $viewType == "day" ? "60%" : "40%" ?>', 
                    }
                },
                dataLabels: { enabled: false },
                colors: ['#1e293b'], 
                xaxis: {
                    categories: barLabels,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: '#64748B', fontSize: '12px' },
                        rotate: -45 \n                    }
                },
                yaxis: {
                    title: { text: 'Volume (MB)' },
                    labels: { style: { colors: '#64748B' } }
                },
                grid: {
                    strokeDashArray: 4,
                    yaxis: { lines: { show: true } },
                    padding: { top: 0, right: 0, bottom: 0, left: 10 }
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + " MB";
                        }
                    }
                }
            };

            var barChart = new ApexCharts(document.querySelector("#monthlyUsageChart"), barOptions);
            barChart.render();

        });
    </script>
</body>
</html>