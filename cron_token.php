<?php
// cron_token.php
// Script ini dijalankan via CLI/Cron Job server
// Contoh Cron: */10 * * * * /usr/bin/php /path/to/cron_token.php

// Konfigurasi Database (Samakan dengan config.php)
$host = 'localhost';
$user = 'lfid_iotsim';
$pass = 'Kumisan5'; 
$db   = 'lfid_iotsim'; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. LOGIN KE API LINKSFIELD
$url = 'https://www.lfiotsim.net/prod-api/auth/login';
$credentials = json_encode([
    "username" => "handy@linksfield.net",
    "password" => "xg379TnQ3J6}"
]);

$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $credentials,
    CURLOPT_HTTPHEADER => array(
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json'
    ),
    CURLOPT_SSL_VERIFYPEER => false 
));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "cURL Error: " . $err;
    exit();
}

$result = json_decode($response, true);

if ($httpCode == 200 && isset($result['data']['access_token'])) {
    $newToken = $result['data']['access_token'];
    
    // 2. SIMPAN TOKEN KE DATABASE
    // Kita gunakan UPDATE agar ID tetap sama (misal ID 1)
    $stmt = $conn->prepare("UPDATE api_tokens SET access_token = ?, updated_at = NOW() WHERE service_name = 'lfiot'");
    $stmt->bind_param("s", $newToken);
    
    if ($stmt->execute()) {
        echo "Token updated successfully at " . date('Y-m-d H:i:s') . "\n";
    } else {
        echo "Failed to update token in DB: " . $conn->error . "\n";
    }
    
    $stmt->close();
} else {
    echo "Failed to login API. Code: $httpCode. Response: " . print_r($result, true) . "\n";
}

$conn->close();
?>