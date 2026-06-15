<?php
$url = 'https://admisioncupbackend-production.up.railway.app/api/materias';
$loginUrl = 'https://admisioncupbackend-production.up.railway.app/api/login';

$ch = curl_init($loginUrl);
$data = json_encode(['email' => 'diogomars2020@gmail.com', 'password' => 'admin123']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
$resObj = json_decode($res);
$token = $resObj->access_token;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token, 'Accept: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
echo $res;
