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
        $level = $_POST['level'];
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

// --- 3. FETCH DATA & BUILD HIERARCHY TREE ---
// Kita ambil semua data diurutkan berdasarkan ID terkecil (ASC)
$sql = "SELECT c1.*, c2.company_name as parent_name 
        FROM companies c1 
        LEFT JOIN companies c2 ON c1.parent_company_id = c2.id 
        ORDER BY c1.id ASC"; // ID Kecil ke Besar
$result = $conn->query($sql);

$companyTree = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Kelompokkan berdasarkan Parent ID. 
        // Jika tidak punya parent (Level 1), masukkan ke key 0.
        $pid = $row['parent_company_id'] ? $row['parent_company_id'] : 0;
        $companyTree[$pid][] = $row;
    }
}

// Fungsi Rekursif untuk Menampilkan Baris Tabel Sesuai Hierarki
function renderCompanyRows($parentId, $tree) {
    if (!isset($tree[$parentId])) return;

    foreach ($tree[$parentId] as $row) {
        // --- START RENDER ROW HTML ---
        ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors border-b border-slate-100 dark:border-slate-800">
            <td class="p-4 text-slate-500 dark:text-slate-400 text-sm font-mono">#<?= $row['id'] ?></td>
            
            <td class="p-4 text-slate-700 dark:text-slate-300">
                <div class="flex items-center">
                    <?php 
                    // Visual Indentasi: Level 1=0px, Level 2=30px, dst.
                    $padding = ($row['level'] - 1) * 30; 
                    
                    // Spacer Transparan untuk mendorong ke kanan
                    if($padding > 0) {
                        echo "<div style='width: {$padding}px; height: 1px;' class='shrink-0'></div>";
                    }

                    // Garis Penghubung (L Shape) untuk Sub Company
                    if ($row['level'] > 1) {
                        echo "<div class='text-slate-300 dark:text-slate-600 mr-2'><i class='ph ph-arrow-elbow-down-right'></i></div>";
                    }

                    // Icon Berbeda Tiap Level
                    $iconClass = match($row['level']) {
                        '1' => 'ph-buildings text-indigo-600 text-xl',
                        '2' => 'ph-factory text-emerald-600 text-lg',
                        '3' => 'ph-storefront text-amber-600 text-lg',
                        default => 'ph-building text-slate-400'
                    };
                    ?>
                    
                    <div class="flex items-center gap-2">
                        <i class="ph <?= $iconClass ?>"></i>
                        <span class="font-bold <?= $row['level'] == '1' ? 'text-lg text-slate-800 dark:text-white' : 'text-base' ?>">
                            <?= htmlspecialchars($row['company_name']) ?>
                        </span>
                    </div>
                </div>
            </td>

            <td class="p-4">
                <div class="flex flex-col">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($row['project_name']) ?></span>
                    <span class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($row['partner_code']) ?></span>
                </div>
            </td>
            
            <td class="p-4">
                <div class="flex items-center gap-2">
                    <span class="bg-indigo-50 text-indigo-700 text-xs font-bold px-2.5 py-0.5 rounded border border-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800">
                        Lvl <?= $row['level'] ?>
                    </span>
                    <?php if($row['parent_name']): ?>
                        <span class="text-[10px] text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                            via <?= htmlspecialchars($row['parent_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </td>
            
            <td class="p-4 text-center">
                <div class="flex items-center justify-center gap-2">
                    <?php if($row['level'] < 4): ?>
                    <button onclick="openSubModal('<?= $row['id'] ?>', '<?= htmlspecialchars($row['company_name']) ?>', '<?= $row['level'] ?>')" 
                            class="group relative flex items-center justify-center h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400 transition-all border border-emerald-200 dark:border-emerald-800"
                            title="Add Child Company">
                        <i class="ph ph-plus-circle text-lg"></i>
                    </button>
                    <?php endif; ?>

                    <a href="manage-client.php?delete_id=<?= $row['id'] ?>" 
                       onclick="return confirm('Are you sure? This will delete the company and potentially its children!');" 
                       class="flex items-center justify-center h-8 w-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 dark:bg-red-900/20 dark:text-red-400 transition-all border border-red-200 dark:border-red-800"
                       title="Delete">
                        <i class="ph ph-trash text-lg"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php 
        // --- END RENDER ROW ---

        // PANGGIL REKURSIF: Cari anak dari perusahaan ini (ID ini menjadi Parent ID untuk langkah selanjutnya)
        renderCompanyRows($row['id'], $tree);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Client - IoT Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                fontFamily: { sans: ['Inter', 'sans-serif'] },
                extend: {
                    colors: { primary: '#4F46E5', dark: '#1A222C', darkcard: '#24303F', darktext: '#AEB7C0' }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-dark text-slate-600 dark:text-darktext antialiased overflow-hidden transition-colors duration-300">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        <div id="main-content" class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden transition-all duration-300">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 relative z-10 py-8">
                <div class="mx-auto max-w-screen-2xl p-4 md:p-6 2xl:p-10">
                    
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">Manage Client Structure</h2>

                    <div class="rounded-xl bg-white dark:bg-darkcard p-6 shadow-soft dark:shadow-none mb-8 border border-slate-100 dark:border-slate-800">
                        <div class="mb-4 border-b border-slate-100 dark:border-slate-700 pb-4">
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white">Add Root Company (Level 1)</h3>
                            <p class="text-sm text-slate-500">Create a main company. Use the table actions to add sub-companies.</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="parent_id" value="">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Company Name</label>
                                    <input type="text" name="company_name" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 px-4 py-2.5 text-slate-700 dark:text-white outline-none focus:border-primary">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Project Name</label>
                                    <input type="text" name="project_name" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 px-4 py-2.5 text-slate-700 dark:text-white outline-none focus:border-primary">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Partner Code</label>
                                    <input type="text" name="partner_code" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 px-4 py-2.5 text-slate-700 dark:text-white outline-none focus:border-primary">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Level</label>
                                    <input type="text" name="level" value="1" readonly class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 px-4 py-2.5 text-slate-500 cursor-not-allowed outline-none">
                                </div>
                            </div>
                            <button type="submit" name="add_company" class="mt-6 w-full md:w-auto rounded-lg bg-primary px-6 py-2.5 font-medium text-white hover:bg-indigo-700 shadow-lg shadow-indigo-500/20 transition-all">
                                <i class="ph ph-plus-circle mr-1"></i> Create Root Company
                            </button>
                        </form>
                    </div>

                    <div class="rounded-xl bg-white dark:bg-darkcard shadow-soft dark:shadow-none overflow-hidden border border-slate-100 dark:border-slate-800">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/20">
                            <h3 class="font-bold text-slate-800 dark:text-white">Hierarchy View</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 dark:bg-slate-700/50">
                                    <tr>
                                        <th class="p-4 text-xs font-bold uppercase text-slate-500 dark:text-slate-400 w-16">ID</th>
                                        <th class="p-4 text-xs font-bold uppercase text-slate-500 dark:text-slate-400">Company Hierarchy</th>
                                        <th class="p-4 text-xs font-bold uppercase text-slate-500 dark:text-slate-400">Project / Code</th>
                                        <th class="p-4 text-xs font-bold uppercase text-slate-500 dark:text-slate-400">Level</th>
                                        <th class="p-4 text-xs font-bold uppercase text-slate-500 dark:text-slate-400 text-center w-32">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Panggil Fungsi Render dari Level Root (Parent ID = 0)
                                    renderCompanyRows(0, $companyTree); 
                                    
                                    if(empty($companyTree)) {
                                        echo "<tr><td colspan='5' class='p-8 text-center text-slate-500'>No companies found. Create a root company first.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <div id="subCompanyModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/75 transition-opacity backdrop-blur-sm" onclick="closeSubModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-xl bg-white dark:bg-darkcard text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100 dark:border-slate-700">
                <form method="POST">
                    <input type="hidden" name="add_company" value="1">
                    <input type="hidden" name="parent_id" id="modal_parent_id">
                    <input type="hidden" name="parent_level" id="modal_parent_level">

                    <div class="bg-white dark:bg-darkcard px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="ph ph-tree-structure text-indigo-600 dark:text-indigo-400 text-xl"></i>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                <h3 class="text-base font-bold leading-6 text-slate-900 dark:text-white" id="modal-title">Add Sub-Company</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                                        Adding child company under: <span id="display_parent_name" class="font-bold text-indigo-600 dark:text-indigo-400"></span>
                                        <br>
                                        This will create a <span id="display_new_level" class="font-bold text-slate-800 dark:text-white"></span> company.
                                    </p>
                                    <div class="space-y-3">
                                        <input type="text" name="company_name" placeholder="Sub-Company Name" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 text-slate-700 dark:text-white outline-none focus:border-primary">
                                        <input type="text" name="project_name" placeholder="Project Name" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 text-slate-700 dark:text-white outline-none focus:border-primary">
                                        <input type="text" name="partner_code" placeholder="Partner Code" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 text-slate-700 dark:text-white outline-none focus:border-primary">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100 dark:border-slate-700">
                        <button type="submit" class="inline-flex w-full justify-center rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 sm:ml-3 sm:w-auto">Save</button>
                        <button type="button" onclick="closeSubModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white dark:bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-white shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 sm:mt-0 sm:w-auto">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        const modal = document.getElementById('subCompanyModal');
        const parentIdInput = document.getElementById('modal_parent_id');
        const parentLevelInput = document.getElementById('modal_parent_level');
        const displayParentName = document.getElementById('display_parent_name');
        const displayNewLevel = document.getElementById('display_new_level');

        function openSubModal(id, name, level) {
            parentIdInput.value = id;
            parentLevelInput.value = level;
            displayParentName.innerText = name;
            let newLevel = parseInt(level) + 1;
            displayNewLevel.innerText = "Level " + newLevel;
            modal.classList.remove('hidden');
        }

        function closeSubModal() {
            modal.classList.add('hidden');
        }
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") { closeSubModal(); }
        });
    </script>
</body>
</html>