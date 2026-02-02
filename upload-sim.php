<?php 
include 'config.php';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_sim'])) {
    $iccid = $_POST['iccid'];
    $company_id = $_POST['company_id'];
    $tags = $_POST['tags']; // Opsional

    // Cek duplikasi
    $check = $conn->query("SELECT id FROM sims WHERE iccid = '$iccid'");
    if($check->num_rows > 0) {
        $error = "ICCID already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO sims (iccid, company_id, tags) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $iccid, $company_id, $tags);
        if($stmt->execute()){
            $success = "SIM Card added successfully!";
        } else {
            $error = "Failed to add SIM.";
        }
    }
}

$companies = $conn->query("SELECT * FROM companies");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload SIM - IoT Platform</title>
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
                <div class="mx-auto max-w-screen-md p-4 md:p-6">
                    
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-6">Upload SIM Card</h2>

                    <?php if(isset($success)): ?>
                        <div class="bg-emerald-100 text-emerald-800 p-4 rounded-lg mb-4"><?= $success ?></div>
                    <?php endif; ?>
                    <?php if(isset($error)): ?>
                        <div class="bg-red-100 text-red-800 p-4 rounded-lg mb-4"><?= $error ?></div>
                    <?php endif; ?>

                    <div class="rounded-xl bg-white dark:bg-darkcard p-8 shadow-soft dark:shadow-none">
                        <form method="POST">
                            <div class="mb-5">
                                <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Select Company / Client</label>
                                <select name="company_id" required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-transparent px-5 py-3 text-slate-700 dark:text-white outline-none focus:border-primary">
                                    <option value="" class="text-black">-- Choose Company --</option>
                                    <?php while($row = $companies->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>" class="text-black"><?= $row['company_name'] ?> (<?= $row['project_name'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-5">
                                <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">ICCID (Device ID)</label>
                                <input type="number" name="iccid" placeholder="89430..." required class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-transparent px-5 py-3 text-slate-700 dark:text-white outline-none focus:border-primary">
                            </div>

                            <div class="mb-5">
                                <label class="block mb-2 text-sm font-medium text-slate-700 dark:text-white">Tags (Optional)</label>
                                <input type="text" name="tags" placeholder="e.g. CCTV Jakarta" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-transparent px-5 py-3 text-slate-700 dark:text-white outline-none focus:border-primary">
                            </div>

                            <button type="submit" name="upload_sim" class="w-full rounded-lg bg-primary px-6 py-3 font-medium text-white hover:bg-opacity-90 transition-all shadow-lg shadow-indigo-500/30">
                                Save SIM Data
                            </button>
                        </form>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>