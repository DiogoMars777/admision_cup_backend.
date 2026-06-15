<?php
$loginUrl = 'https://admisioncupbackend-production.up.railway.app/api/login';
$postulantesUrl = 'https://admisioncupbackend-production.up.railway.app/api/postulantes';

// Login
$ch = curl_init($loginUrl);
$data = json_encode(['email' => 'diogomars2020@gmail.com', 'password' => 'admin123']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

$resObj = json_decode($res);
if (!isset($resObj->access_token)) {
    echo "Login failed: " . $res . "\n";
    exit;
}

$token = $resObj->access_token;

// Fetch
$start = microtime(true);
$ch = curl_init($postulantesUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$end = microtime(true);

echo "Status: $httpCode\n";
echo "Time: " . round($end - $start, 2) . "s\n";
echo "Length: " . strlen($res) . "\n";
