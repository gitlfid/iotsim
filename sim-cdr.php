<?php 
include 'config.php';
checkLogin();

if (!isset($_GET['iccid']) || empty($_GET['iccid'])) {
    echo "<script>alert('Invalid ICCID'); window.location='sim-list.php';</script>";
    exit();
}

$iccid = $_GET['iccid'];
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if($page < 1) $page = 1;
$pageSize = 20;

// API CALL untuk CDR dengan halaman
$apiCdr = getSimCdrFromApi($iccid, $page, $pageSize);
$cdrList = $apiCdr['rows'] ?? [];
$totalRecords = $apiCdr['total'] ?? 0;
$totalPages = ceil($totalRecords / $pageSize);

// Helper function
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CDR History: <?= $iccid ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
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
                            <a href="sim-detail.php?iccid=<?= $iccid ?>" class="p-2 bg-white dark:bg-darkcard border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                <i class="ph ph-arrow-left text-xl"></i>
                            </a>
                            <div>
                                <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Connection History</h2>
                                <p class="text-sm text-slate-500 font-mono">Asset ID: <?= $iccid ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <span class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 text-sm font-bold border border-indigo-100">
                                Total Records: <?= number_format($totalRecords) ?>
                            </span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-xl shadow-soft border border-slate-100 dark:border-slate-800">
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
                                        <td colspan="5" class="p-12 text-center text-slate-500">No records found on this page.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if($totalPages > 1): ?>
                        <div class="flex justify-between items-center mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                Page <span class="font-semibold text-slate-800 dark:text-white"><?= $page ?></span> of <span class="font-semibold text-slate-800 dark:text-white"><?= $totalPages ?></span>
                            </span>
                            <div class="flex gap-2">
                                <?php if($page > 1): ?>
                                    <a href="?iccid=<?= $iccid ?>&page=<?= $page - 1 ?>" class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php if($page < $totalPages): ?>
                                    <a href="?iccid=<?= $iccid ?>&page=<?= $page + 1 ?>" class="px-4 py-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>