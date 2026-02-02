<?php
// config.php
date_default_timezone_set('Asia/Jakarta');
session_start();

require 'vendor/autoload.php'; // Ini kunci agar PHPMailer jalan

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- DATABASE CONFIGURATION ---
$host = 'localhost';
$user = 'lfid_iotsim';
$pass = 'Kumisan5'; 
$db   = 'lfid_iotsim'; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- SECURITY ---
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// --- FUNGSI AMBIL TOKEN DINAMIS ---
function getDynamicToken() {
    global $conn;
    $sql = "SELECT access_token FROM api_tokens WHERE service_name = 'lfiot' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['access_token'];
    }
    return '';
}

// --- FUNGSI AMBIL PARTNER CODE BERDASARKAN ICCID ---
function getPartnerCodeByIccid($iccid) {
    global $conn;
    
    // Cek jika ICCID kosong
    if (empty($iccid)) return 'IDN0000034'; // Default fallback

    // Query join ke companies untuk ambil partner_code
    $stmt = $conn->prepare("
        SELECT c.partner_code 
        FROM sims s
        JOIN companies c ON s.company_id = c.id
        WHERE s.iccid = ?
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("s", $iccid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            // Jika ada partner code di DB, pakai itu
            if (!empty($row['partner_code'])) {
                return $row['partner_code'];
            }
        }
    }
    
    return 'IDN0000034'; // Default jika tidak ketemu
}

// --- HELPER CURL (Agar tidak nulis ulang terus) ---
function callCurl($url) {
    $token = getDynamicToken(); 
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json, text/plain, */*',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'accept-language: en-US',
            'Authorization: ' . $token,
            'content-type: application/json'
        ),
        CURLOPT_SSL_VERIFYPEER => false 
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode == 200) ? json_decode($response, true) : null;
}

// ==========================================================
// API FUNCTIONS (UPDATED DYNAMIC PARTNER CODE)
// ==========================================================

// 1. GET SIM DETAIL
function getSimDetailFromApi($iccid) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/details?partnerCode=" . $pCode . "&assetId=" . $iccid;
    return callCurl($url);
}

// 2. GET SIM CONNECTION
function getSimConnectionFromApi($iccid) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/connection?assetId=" . $iccid . "&partnerCode=" . $pCode;
    return callCurl($url);
}

// 3. GET SIM CDR
function getSimCdrFromApi($iccid, $page = 1, $size = 20) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/connectionCdr?pageNum=" . $page . "&pageSize=" . $size . "&assetId=" . $iccid . "&partnerCode=" . $pCode;
    return callCurl($url);
}

// 4. GET MONTHLY USAGE
function getSimMonthUsageFromApi($iccid, $from, $to) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/monthUsage?assetId=" . $iccid . "&partnerCode=" . $pCode . "&from=" . $from . "&to=" . $to;
    return callCurl($url);
}

// 5. GET DAILY USAGE
function getSimDailyUsageFromApi($iccid, $from, $to) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/dailyUsage?assetId=" . $iccid . "&partnerCode=" . $pCode . "&from=" . $from . "&to=" . $to;
    return callCurl($url);
}

// 6. GET SIM BUNDLES
function getSimBundlesFromApi($iccid, $status) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/bundle?assetId=" . $iccid . "&status=" . $status . "&partnerCode=" . $pCode;
    return callCurl($url);
}

// 7. GET SIM EVENTS
function getSimEventsFromApi($iccid) {
    // Event API biasanya tidak butuh partnerCode di URL path-nya, tapi jika butuh bisa ditambahkan
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/event/" . $iccid;
    return callCurl($url);
}

// 8. GET SIM STATUS REALTIME
function getSimStatusRealtime($iccid) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/getSimConStatus?assetId=" . $iccid . "&partnerCode=" . $pCode;
    return callCurl($url);
}

// 9. GET SIM LIST (POST Request) - Untuk Sync Data
// Fungsi ini menerima partnerCode langsung dari parameter, jadi tidak perlu lookup ICCID
function getSimListFromApi($partnerCode, $page = 1, $size = 5) {
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/list";
    
    $postData = [
        "source" => null,
        "assetId" => "",
        "partnerCodeList" => [$partnerCode],
        "pageNum" => intval($page),
        "pageSize" => intval($size),
        "loginPartnerCode" => $partnerCode
    ];

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json, text/plain, */*',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'accept-language: en-US',
            'Authorization: ' . getDynamicToken(),
            'content-type: application/json'
        ),
        CURLOPT_SSL_VERIFYPEER => false 
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode == 200) ? json_decode($response, true) : null;
}

// 10. TOGGLE SIM SUSPEND/RESTART (POST)
function toggleSimStatusFromApi($iccid, $orderId, $operationType) {
    $pCode = getPartnerCodeByIccid($iccid); // Ambil partner code dinamis
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/suspendOrRestart";
    
    $postData = [
        "partnerCode" => $pCode,
        "assetId"     => $iccid,
        "orderId"     => $orderId,
        "operationType" => $operationType,
        "sysName"     => "SPOON"
    ];

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json, text/plain, */*',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'accept-language: en-US',
            'Authorization: ' . getDynamicToken(),
            'content-type: application/json'
        ),
        CURLOPT_SSL_VERIFYPEER => false 
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode == 200) ? json_decode($response, true) : null;
}

// --- HELPER: STATUS BADGE ---
function getStatusBadge($code) {
    switch ($code) {
        case '1': return '<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Pre-Active</span>';
        case '2': return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Active</span>';
        case '3': return '<span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Expired</span>';
        case '6': return '<span class="inline-flex items-center rounded-md bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700 ring-1 ring-inset ring-orange-600/20">Suspend</span>';
        default: return '<span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Unknown</span>';
    }
}

function getRealtimeStatusBadge($code) {
    return getStatusBadge($code); // Re-use same logic
}

// --- HELPER: PACKAGE STATUS TOGGLE ---
function getPackageStatusToggle($iccid, $pkgStatus) {
    $isChecked = ($pkgStatus == '1') ? 'checked' : '';
    $toggleId = 'pkg_toggle_' . $iccid;
    $labelId = 'pkg_label_' . $iccid;
    
    $statusText = ($pkgStatus == '1') ? 'Active' : 'Suspend';
    $statusColor = ($pkgStatus == '1') ? 'text-emerald-600' : 'text-slate-400';
    
    return '
    <div class="flex items-center justify-center gap-2">
        <span id="'.$labelId.'" class="text-[11px] font-bold '.$statusColor.' min-w-[45px] text-right transition-colors">
            '.$statusText.'
        </span>
        <label for="'.$toggleId.'" class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="'.$toggleId.'" class="sr-only peer" onchange="togglePackageStatus(\''.$iccid.'\', this)" '.$isChecked.'>
            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-500"></div>
        </label>
    </div>';
}

// --- FUNGSI NOTIFIKASI ---
function createNotification($userId, $title, $message, $type = 'info') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    return $stmt->execute();
}

// --- CONFIG.PHP ADDITION ---

// Fungsi Cek Akses Menu Dinamis
function hasAccess($page_key) {
    global $conn;
    
    // Jika user belum login
    if (!isset($_SESSION['role'])) return false;
    $role = $_SESSION['role'];

    // Superadmin selalu true (bypass) - Opsional, tapi lebih aman cek DB
    if ($role == 'superadmin') return true; 

    // Cek Database
    $stmt = $conn->prepare("SELECT id FROM role_permissions WHERE role = ? AND page_key = ?");
    $stmt->bind_param("ss", $role, $page_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return ($result->num_rows > 0);
}

// ... kode config.php sebelumnya ...

// --- FUNGSI CEK AKSES COMPANY USER ---
function getClientIdsForUser($user_id) {
    global $conn;
    
    // 1. Cek apakah user punya akses "ALL" (Superadmin / Global Access)
    $u = $conn->query("SELECT access_all_companies, role FROM users WHERE id = '$user_id'")->fetch_assoc();
    if ($u['access_all_companies'] == 1 || $u['role'] == 'superadmin') {
        return 'ALL';
    }

    // 2. Ambil Company yang di-assign secara EKSPLISIT (Direct Assign)
    $assigned_ids = [];
    $q = $conn->query("SELECT company_id FROM user_companies WHERE user_id = '$user_id'");
    while($row = $q->fetch_assoc()) {
        $assigned_ids[] = $row['company_id'];
    }

    if (empty($assigned_ids)) return 'NONE';

    // 3. LOGIKA HIERARKI: Cari semua Anak, Cucu, Cicit ke bawah
    // Jika di-assign Level 1, otomatis dapat Level 2, 3, dst.
    // Jika di-assign Level 2, otomatis dapat Level 3, 4, dst (TAPI TIDAK Level 1).
    
    $final_ids = $assigned_ids; // Start dengan yang di-assign langsung
    $parents_to_check = $assigned_ids; // Batch untuk dicek anaknya

    // Loop sampai tidak ada lagi anak perusahaan ditemukan
    while (!empty($parents_to_check)) {
        $check_list = implode(',', $parents_to_check);
        
        // Cari perusahaan yang parent_id-nya ada di list batch ini
        $qChild = $conn->query("SELECT id FROM companies WHERE parent_id IN ($check_list)");
        
        $new_children = [];
        while($row = $qChild->fetch_assoc()) {
            // Hindari duplikat (jika struktur data melingkar/salah input)
            if (!in_array($row['id'], $final_ids)) {
                $final_ids[] = $row['id'];
                $new_children[] = $row['id'];
            }
        }
        
        // Set anak-anak yang baru ditemukan sebagai 'Parent' untuk pengecekan level berikutnya (Cucu)
        $parents_to_check = $new_children;
    }

    return $final_ids;
}

// --- FUNGSI GENERATE PASSWORD (ANGKA & HURUF) ---
function generateStrongPassword($length = 10) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

// --- FUNGSI KIRIM EMAIL (NATIVE SMTP) ---
function sendEmail($to, $subject, $body) {
    global $conn;
    
    // Ambil setting dari DB
    $q = $conn->query("SELECT * FROM smtp_settings LIMIT 1");
    if ($q->num_rows == 0) return ['status' => false, 'msg' => 'SMTP Settings not found'];
    $smtp = $q->fetch_assoc();

    // Konfigurasi
    $host = $smtp['host'];
    $port = $smtp['port'];
    $username = $smtp['username'];
    $password = $smtp['password'];
    $from = $smtp['from_email'];
    $fromName = $smtp['from_name'];

    // Ini adalah implementasi socket sederhana untuk SMTP tanpa library PHPMailer
    // Agar script bisa jalan plug-and-play.
    try {
        if(!$socket = fsockopen($host, $port, $errno, $errstr, 30)) {
            return ['status' => false, 'msg' => "Could not connect to SMTP host: $errno $errstr"];
        }

        $response = array();
        $response[] = fgets($socket, 515);

        fputs($socket, "HELO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response[] = fgets($socket, 515);

        if (!empty($smtp['encryption']) && ($smtp['encryption'] == 'tls')) {
            fputs($socket, "STARTTLS\r\n");
            fgets($socket, 515);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "HELO " . $_SERVER['SERVER_NAME'] . "\r\n");
            fgets($socket, 515);
        }

        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket, 515);

        fputs($socket, base64_encode($username) . "\r\n");
        fgets($socket, 515);

        fputs($socket, base64_encode($password) . "\r\n");
        $authResult = fgets($socket, 515);
        
        if (strpos($authResult, '235') === false) {
             return ['status' => false, 'msg' => "Authentication Failed. Check SMTP User/Pass."];
        }

        fputs($socket, "MAIL FROM: <$from>\r\n");
        fgets($socket, 515);

        fputs($socket, "RCPT TO: <$to>\r\n");
        fgets($socket, 515);

        fputs($socket, "DATA\r\n");
        fgets($socket, 515);

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";

        fputs($socket, "$headers\r\n$body\r\n.\r\n");
        $result = fgets($socket, 515);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if (strpos($result, '250') !== false) {
            return ['status' => true, 'msg' => 'Email sent successfully'];
        } else {
            return ['status' => false, 'msg' => "Failed to send: $result"];
        }

    } catch (Exception $e) {
        return ['status' => false, 'msg' => $e->getMessage()];
    }
}

?>