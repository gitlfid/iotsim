<?php 
// Aktifkan Error Reporting sementara untuk debugging jika masih error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

// 1. SECURITY CHECK: Wajib Login
// Pastikan config.php sudah diupdate dengan fungsi checkLogin()
checkLogin();

// 2. DATA FETCHING

// A. Total SIM Cards
$simQuery = $conn->query("SELECT COUNT(*) as total FROM sims");
$totalSims = ($simQuery) ? $simQuery->fetch_assoc()['total'] : 0;

// B. Total Companies
$compQuery = $conn->query("SELECT COUNT(*) as total FROM companies");
$totalComp = ($compQuery) ? $compQuery->fetch_assoc()['total'] : 0;

// C. Data Pie Chart (Level Company)
$chartLabel = [];
$chartData = [];
$levelQuery = $conn->query("SELECT level, COUNT(*) as count FROM companies GROUP BY level ORDER BY level ASC");
if($levelQuery) {
    while($row = $levelQuery->fetch_assoc()) {
        $chartLabel[] = "Level " . $row['level'];
        $chartData[] = (int)$row['count'];
    }
}

// D. Dummy Data untuk Pie Chart Status (Kanan)
// Karena status real ada di API dan berat jika di-load semua di dashboard, kita pakai dummy dulu untuk UI
$activeSims = 75; 
$inactiveSims = 25; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IoT Connectivity</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                fontFamily: { sans: ['Inter', 'sans-serif'] },
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        dark: '#1A222C',
                        darkcard: '#24303F',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-dark text-slate-600 dark:text-slate-300 antialiased overflow-hidden">

    <div class="flex h-screen overflow-hidden">

        <?php include 'includes/sidebar.php'; ?>

        <div id="main-content" class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden transition-all duration-300">
            
            <?php include 'includes/header.php'; ?>

            <main class="flex-1 relative z-10 py-8">
                <div class="mx-auto max-w-screen-2xl p-4 md:p-6 2xl:p-10">
                    
                    <div class="mb-8">
                        <h2 class="text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Overview</h2>
                        <p class="text-sm text-slate-500 mt-1">Welcome back, here's what's happening today.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="bg-white dark:bg-darkcard p-6 rounded-2xl border border-slate-100 dark:border-slate-800 flex items-center gap-6 shadow-sm">
                            <div class="h-16 w-16 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                <i class="ph ph-sim-card text-3xl"></i>
                            </div>
                            <div>
                                <h4 class="text-3xl font-bold text-slate-800 dark:text-white"><?php echo $totalSims; ?></h4>
                                <span class="text-slate-500 dark:text-slate-400 font-medium">Total SIM Cards</span>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkcard p-6 rounded-2xl border border-slate-100 dark:border-slate-800 flex items-center gap-6 shadow-sm">
                            <div class="h-16 w-16 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                <i class="ph ph-buildings text-3xl"></i>
                            </div>
                            <div>
                                <h4 class="text-3xl font-bold text-slate-800 dark:text-white"><?php echo $totalComp; ?></h4>
                                <span class="text-slate-500 dark:text-slate-400 font-medium">Total Companies</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative w-full rounded-3xl bg-[#4F46E5] p-8 md:p-12 mb-8 overflow-hidden shadow-lg shadow-indigo-500/20 text-white">
                        <div class="relative z-10 max-w-xl">
                            <h2 class="text-3xl font-bold mb-4">Manage IoT Connectivity</h2>
                            <p class="text-indigo-100 text-lg mb-8 leading-relaxed">
                                Upload new SIM cards or monitor existing usage in real-time efficiently from one platform.
                            </p>
                            <a href="sim-list.php" class="inline-flex items-center gap-2 bg-white text-[#4F46E5] px-6 py-3 rounded-xl font-bold hover:bg-indigo-50 transition-colors">
                                Go to Monitoring <i class="ph ph-arrow-right font-bold"></i>
                            </a>
                        </div>
                        
                        <div class="absolute -bottom-10 -right-10 opacity-20">
                             <svg width="400" height="400" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="100" cy="100" r="90" stroke="white" stroke-width="20"/>
                                <circle cx="100" cy="100" r="60" stroke="white" stroke-width="20"/>
                                <circle cx="100" cy="100" r="30" stroke="white" stroke-width="20"/>
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div class="bg-white dark:bg-darkcard p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">Company Distribution</h3>
                            <div id="chartCompany" class="flex justify-center min-h-[300px]"></div>
                        </div>

                        <div class="bg-white dark:bg-darkcard p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6">SIM Status Overview</h3>
                            <div id="chartStatus" class="flex justify-center min-h-[300px]"></div>
                        </div>

                    </div>

                </div>
            </main>

            <?php include 'includes/footer.php'; ?>

        </div>
    </div>

    <script src="assets/js/main.js"></script>

    <script>
        // --- 1. Donut Chart (Company) ---
        var optionsComp = {
            series: <?php echo json_encode($chartData); ?>,
            labels: <?php echo json_encode($chartLabel); ?>,
            chart: { type: 'donut', height: 320, fontFamily: 'Inter' },
            colors: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444'],
            plotOptions: {
                pie: { donut: { size: '65%', labels: { show: true, total: { show: true, fontSize: '20px', fontWeight: 600 } } } }
            },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', markers: { radius: 12 } },
            stroke: { show: false }
        };
        var chartComp = new ApexCharts(document.querySelector("#chartCompany"), optionsComp);
        chartComp.render();

        // --- 2. Pie Chart (Status) ---
        var optionsStat = {
            series: [<?php echo $activeSims; ?>, <?php echo $inactiveSims; ?>],
            labels: ['Active', 'Inactive'],
            chart: { type: 'pie', height: 320, fontFamily: 'Inter' },
            colors: ['#3C50E0', '#8FD0EF'], // Warna sesuai gambar referensi (Biru Tua & Muda)
            legend: { position: 'bottom', markers: { radius: 12 } },
            dataLabels: { enabled: true },
            stroke: { show: false }
        };
        var chartStat = new ApexCharts(document.querySelector("#chartStatus"), optionsStat);
        chartStat.render();
    </script>
</body>
</html>