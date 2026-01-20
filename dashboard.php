<?php 
include 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// --- 1. DATA ACCESS CONTROL (LOGIC INTI) ---
// Kita gunakan helper yang sudah dibuat di config.php
$allowed_comps = getClientIdsForUser($user_id);

$sim_where_clause = "";
$comp_where_clause = "";

if ($allowed_comps === 'NONE') {
    // User tidak punya akses apapun
    $sim_where_clause = " AND 1=0 ";
    $comp_where_clause = " AND 1=0 ";
} elseif (is_array($allowed_comps)) {
    // User punya akses spesifik
    $ids_str = implode(',', $allowed_comps);
    $sim_where_clause = " AND company_id IN ($ids_str) ";
    $comp_where_clause = " AND id IN ($ids_str) ";
} 
// Jika 'ALL', where clause tetap kosong (ambil semua)

// --- 2. FETCH KEY METRICS ---

// A. Total SIM Cards
$qSim = $conn->query("SELECT COUNT(*) as total FROM sims WHERE 1=1 $sim_where_clause");
$totalSims = $qSim->fetch_assoc()['total'];

// B. Total Active SIMs (Asumsi Status '2' = Active, sesuaikan dengan API Anda)
$qActive = $conn->query("SELECT COUNT(*) as total FROM sims WHERE status = '2' $sim_where_clause");
$activeSims = $qActive->fetch_assoc()['total'];
$inactiveSims = $totalSims - $activeSims;

// C. Total Data Usage (Global)
$qUsage = $conn->query("SELECT SUM(used_flow) as total_usage FROM sims WHERE 1=1 $sim_where_clause");
$rawUsage = $qUsage->fetch_assoc()['total_usage'];

// Function Format Bytes
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}
$displayUsage = formatBytes($rawUsage);

// D. Total Companies Managed
$qComp = $conn->query("SELECT COUNT(*) as total FROM companies WHERE 1=1 $comp_where_clause");
$totalCompanies = $qComp->fetch_assoc()['total'];

// --- 3. FETCH CHART DATA ---

// Chart 1: Top 10 Companies by Data Usage
// Query Join sims & companies, sum usage, group by company
$chartUsageLabels = [];
$chartUsageData = [];

$sqlChart1 = "SELECT c.company_name, SUM(s.used_flow) as usage_sum 
              FROM sims s 
              JOIN companies c ON s.company_id = c.id 
              WHERE 1=1 $sim_where_clause 
              GROUP BY c.company_name 
              ORDER BY usage_sum DESC 
              LIMIT 10";
$resChart1 = $conn->query($sqlChart1);
while($row = $resChart1->fetch_assoc()){
    $chartUsageLabels[] = $row['company_name'];
    // Convert ke MB untuk grafik agar angkanya enak dilihat
    $chartUsageData[] = round($row['usage_sum'] / 1048576, 2); 
}

// Chart 2: Status Distribution (Pie)
// Kita sudah punya $activeSims dan $inactiveSims

// --- 4. TOP 5 SIMs LEADERBOARD ---
$topSims = [];
$sqlTop = "SELECT s.iccid, s.used_flow, s.status, c.company_name 
           FROM sims s 
           LEFT JOIN companies c ON s.company_id = c.id 
           WHERE 1=1 $sim_where_clause 
           ORDER BY s.used_flow DESC 
           LIMIT 5";
$resTop = $conn->query($sqlTop);
while($row = $resTop->fetch_assoc()) {
    $topSims[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - IoT Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { colors: { primary: '#4F46E5', darkcard: '#24303F', darkbg: '#1A222C' } }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 font-sans antialiased overflow-hidden">
    
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 lg:p-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Dashboard Overview</h1>
                        <p class="text-sm text-slate-500 mt-1">Welcome back, <b><?= $_SESSION['username'] ?></b>! Here is your IoT connectivity summary.</p>
                    </div>
                    <div class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-darkcard rounded-lg border border-slate-100 dark:border-slate-800 shadow-sm text-xs font-medium">
                        <i class="ph ph-calendar-blank text-lg text-primary"></i>
                        <span><?= date('d M Y, H:i') ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                    
                    <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 relative overflow-hidden group">
                        <div class="flex justify-between items-start z-10 relative">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total SIMs</p>
                                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= number_format($totalSims) ?></h3>
                            </div>
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400">
                                <i class="ph ph-sim-card text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-xs font-medium text-emerald-600">
                            <i class="ph ph-trend-up mr-1"></i> <span>Live Data</span>
                        </div>
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-indigo-50 dark:bg-indigo-900/10 rounded-full blur-2xl group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/20 transition-colors"></div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 relative overflow-hidden group">
                        <div class="flex justify-between items-start z-10 relative">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Data Usage</p>
                                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= $displayUsage ?></h3>
                            </div>
                            <div class="p-3 bg-cyan-50 dark:bg-cyan-900/30 rounded-xl text-cyan-600 dark:text-cyan-400">
                                <i class="ph ph-chart-bar text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-xs font-medium text-slate-500">
                            <span>Accumulated Volume</span>
                        </div>
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-cyan-50 dark:bg-cyan-900/10 rounded-full blur-2xl group-hover:bg-cyan-100 transition-colors"></div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 relative overflow-hidden group">
                        <div class="flex justify-between items-start z-10 relative">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Active Connections</p>
                                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= number_format($activeSims) ?></h3>
                            </div>
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400">
                                <i class="ph ph-broadcast text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5">
                            <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= ($totalSims > 0) ? ($activeSims/$totalSims)*100 : 0 ?>%"></div>
                        </div>
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-50 dark:bg-emerald-900/10 rounded-full blur-2xl group-hover:bg-emerald-100 transition-colors"></div>
                    </div>

                    <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 relative overflow-hidden group">
                        <div class="flex justify-between items-start z-10 relative">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Companies</p>
                                <h3 class="text-2xl font-bold text-slate-800 dark:text-white"><?= number_format($totalCompanies) ?></h3>
                            </div>
                            <div class="p-3 bg-orange-50 dark:bg-orange-900/30 rounded-xl text-orange-600 dark:text-orange-400">
                                <i class="ph ph-buildings text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-xs font-medium text-slate-500">
                            <span>Managed Clients</span>
                        </div>
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-orange-50 dark:bg-orange-900/10 rounded-full blur-2xl group-hover:bg-orange-100 transition-colors"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    
                    <div class="lg:col-span-2 bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <h3 class="font-bold text-slate-800 dark:text-white mb-4">Top Data Usage by Company (MB)</h3>
                        <div id="chartUsage" class="w-full h-[300px]"></div>
                    </div>

                    <div class="lg:col-span-1 bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col">
                        <h3 class="font-bold text-slate-800 dark:text-white mb-4">Connection Status</h3>
                        <div id="chartStatus" class="w-full flex-1 flex items-center justify-center min-h-[300px]"></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 dark:text-white">Top 5 Highest Usage SIMs</h3>
                        <a href="sim-list.php" class="text-sm font-medium text-primary hover:underline">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800 text-xs uppercase text-slate-500 font-bold">
                                <tr>
                                    <th class="px-6 py-4">ICCID</th>
                                    <th class="px-6 py-4">Company</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Data Used</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php if(!empty($topSims)): foreach($topSims as $row): 
                                    $statusBadge = ($row['status'] == '2') 
                                        ? '<span class="px-2 py-1 rounded bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400 text-xs font-bold">Active</span>'
                                        : '<span class="px-2 py-1 rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400 text-xs font-bold">Inactive</span>';
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4 font-mono font-bold text-primary"><?= $row['iccid'] ?></td>
                                    <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300"><?= $row['company_name'] ?></td>
                                    <td class="px-6 py-4"><?= $statusBadge ?></td>
                                    <td class="px-6 py-4 text-right font-bold text-slate-800 dark:text-white"><?= formatBytes($row['used_flow']) ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-slate-500">No data available.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // --- 1. Bar Chart (Top Companies Usage) ---
        var usageLabels = <?php echo json_encode($chartUsageLabels); ?>;
        var usageData = <?php echo json_encode($chartUsageData); ?>;

        var optionsBar = {
            series: [{ name: 'Usage (MB)', data: usageData }],
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Inter' },
            colors: ['#4F46E5'],
            plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '40%' } },
            dataLabels: { enabled: false },
            xaxis: { 
                categories: usageLabels, 
                labels: { style: { colors: '#64748B', fontSize: '11px' } }
            },
            yaxis: { labels: { style: { colors: '#64748B' } } },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            tooltip: { y: { formatter: function (val) { return val + " MB" } } }
        };
        var chartBar = new ApexCharts(document.querySelector("#chartUsage"), optionsBar);
        chartBar.render();

        // --- 2. Donut Chart (Status) ---
        var optionsDonut = {
            series: [<?php echo $activeSims; ?>, <?php echo $inactiveSims; ?>],
            labels: ['Active', 'Inactive/Suspend'],
            chart: { type: 'donut', height: 320, fontFamily: 'Inter' },
            colors: ['#10B981', '#E2E8F0'], // Emerald & Slate
            plotOptions: {
                pie: { 
                    donut: { 
                        size: '70%', 
                        labels: { 
                            show: true, 
                            total: { 
                                show: true, 
                                label: 'Total', 
                                fontSize: '14px', 
                                fontWeight: 600, 
                                color: '#64748B' 
                            },
                            value: {
                                fontSize: '24px',
                                fontWeight: 700,
                                color: '#1E293B',
                                offsetY: 2
                            }
                        } 
                    } 
                }
            },
            dataLabels: { enabled: false },
            stroke: { show: false },
            legend: { position: 'bottom' }
        };
        var chartDonut = new ApexCharts(document.querySelector("#chartStatus"), optionsDonut);
        chartDonut.render();
    </script>
</body>
</html>