<?php
// config.php

// --- LOAD LIBRARY PHPMAILER (VIA COMPOSER) ---
// Pastikan folder 'vendor' ada di directory yang sama
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Jakarta');
session_start();

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
    
    if (empty($iccid)) return 'IDN0000034'; 

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
            if (!empty($row['partner_code'])) {
                return $row['partner_code'];
            }
        }
    }
    return 'IDN0000034'; 
}

// --- HELPER CURL ---
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
// API FUNCTIONS
// ==========================================================

function getSimDetailFromApi($iccid) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/details?partnerCode=" . $pCode . "&assetId=" . $iccid;
    return callCurl($url);
}

function getSimConnectionFromApi($iccid) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/connection?assetId=" . $iccid . "&partnerCode=" . $pCode;
    return callCurl($url);
}

function getSimCdrFromApi($iccid, $page = 1, $size = 20) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/connectionCdr?pageNum=" . $page . "&pageSize=" . $size . "&assetId=" . $iccid . "&partnerCode=" . $pCode;
    return callCurl($url);
}

function getSimMonthUsageFromApi($iccid, $from, $to) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/monthUsage?assetId=" . $iccid . "&partnerCode=" . $pCode . "&from=" . $from . "&to=" . $to;
    return callCurl($url);
}

function getSimDailyUsageFromApi($iccid, $from, $to) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/dailyUsage?assetId=" . $iccid . "&partnerCode=" . $pCode . "&from=" . $from . "&to=" . $to;
    return callCurl($url);
}

function getSimBundlesFromApi($iccid, $status) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/bundle?assetId=" . $iccid . "&status=" . $status . "&partnerCode=" . $pCode;
    return callCurl($url);
}

function getSimEventsFromApi($iccid) {
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/event/" . $iccid;
    return callCurl($url);
}

function getSimStatusRealtime($iccid) {
    $pCode = getPartnerCodeByIccid($iccid);
    $url = "https://www.lfiotsim.net/prod-api/spoon/sim/getSimConStatus?assetId=" . $iccid . "&partnerCode=" . $pCode;
    return callCurl($url);
}

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

function toggleSimStatusFromApi($iccid, $orderId, $operationType) {
    $pCode = getPartnerCodeByIccid($iccid); 
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

// --- HELPER UI ---
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
    return getStatusBadge($code); 
}

function getPackageStatusToggle($iccid, $pkgStatus) {
    $isChecked = ($pkgStatus == '1') ? 'checked' : '';
    $toggleId = 'pkg_toggle_' . $iccid;
    $labelId = 'pkg_label_' . $iccid;
    $statusText = ($pkgStatus == '1') ? 'Active' : 'Suspend';
    $statusColor = ($pkgStatus == '1') ? 'text-emerald-600' : 'text-slate-400';
    
    return '
    <div class="flex items-center justify-center gap-2">
        <span id="'.$labelId.'" class="text-[11px] font-bold '.$statusColor.' min-w-[45px] text-right transition-colors">'.$statusText.'</span>
        <label for="'.$toggleId.'" class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="'.$toggleId.'" class="sr-only peer" onchange="togglePackageStatus(\''.$iccid.'\', this)" '.$isChecked.'>
            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-500"></div>
        </label>
    </div>';
}

function createNotification($userId, $title, $message, $type = 'info') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    return $stmt->execute();
}

// Fungsi Cek Akses Menu Dinamis
function hasAccess($page_key) {
    global $conn;
    if (!isset($_SESSION['role'])) return false;
    $role = $_SESSION['role'];
    if ($role == 'superadmin') return true; 
    
    $stmt = $conn->prepare("SELECT id FROM role_permissions WHERE role = ? AND page_key = ?");
    $stmt->bind_param("ss", $role, $page_key);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows > 0);
}

// --- FUNGSI CEK AKSES COMPANY USER ---
function getClientIdsForUser($user_id) {
    global $conn;
    $u = $conn->query("SELECT access_all_companies, role FROM users WHERE id = '$user_id'")->fetch_assoc();
    if ($u['access_all_companies'] == 1 || $u['role'] == 'superadmin') {
        return 'ALL';
    }

    $assigned_ids = [];
    $q = $conn->query("SELECT company_id FROM user_companies WHERE user_id = '$user_id'");
    while($row = $q->fetch_assoc()) {
        $assigned_ids[] = $row['company_id'];
    }

    if (empty($assigned_ids)) return 'NONE';

    $final_ids = $assigned_ids;
    $parents_to_check = $assigned_ids;

    while (!empty($parents_to_check)) {
        $check_list = implode(',', $parents_to_check);
        $qChild = $conn->query("SELECT id FROM companies WHERE parent_id IN ($check_list)");
        $new_children = [];
        while($row = $qChild->fetch_assoc()) {
            if (!in_array($row['id'], $final_ids)) {
                $final_ids[] = $row['id'];
                $new_children[] = $row['id'];
            }
        }
        $parents_to_check = $new_children;
    }
    return $final_ids;
}

// --- FUNGSI GENERATE PASSWORD ---
function generateStrongPassword($length = 10) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

// --- FUNGSI KIRIM EMAIL (PHPMailer Optimized & Fixed) ---
function sendEmail($to, $subject, $body, $isTest = false) {
    global $conn;
    
    // Ambil setting SMTP dari DB
    $q = $conn->query("SELECT * FROM smtp_settings LIMIT 1");
    if ($q->num_rows == 0) return ['status' => false, 'msg' => 'SMTP Settings not found in DB'];
    $smtp = $q->fetch_assoc();

    $mail = new PHPMailer(true);

    // Variabel untuk menampung debug log
    $debugOutput = '';

    try {
        // 1. Setup Debugging (Hanya jika ini Test Email)
        if ($isTest) {
            $mail->SMTPDebug = SMTP::DEBUG_CONNECTION; // Level 3 memberikan detail koneksi
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                $debugOutput .= "$str\n";
            };
        } else {
            $mail->SMTPDebug = 0; // Matikan debug untuk user biasa
        }
        
        // 2. Konfigurasi Server
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->Port       = (int)$smtp['port'];

        // 3. Timeout Settings (FIXED: Hapus Timelimit yang deprecated)
        $mail->Timeout    = 10; // Timeout koneksi (detik)
        // $mail->Timelimit = 10; // <-- INI YANG BIKIN ERROR DEPRECATED, SUDAH DIHAPUS

        // 4. Pengaturan Enkripsi
        if ($smtp['encryption'] == 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtp['encryption'] == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = false;
        }

        // 5. Bypass SSL Verification (Solusi Jitu untuk "Connection Failed")
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // 6. Penerima & Konten
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return ['status' => true, 'msg' => 'Email sent successfully', 'log' => $debugOutput];

    } catch (Exception $e) {
        // Kembalikan error beserta log debugnya
        return [
            'status' => false, 
            'msg' => "Mailer Error: " . $mail->ErrorInfo,
            'log' => $debugOutput
        ];
    }
}
?>