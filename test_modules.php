<?php

$ch = curl_init('http://localhost:8000/api/v1/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'superadmin@erp.test', 'password' => 'Password@123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$resp = curl_exec($ch);
$data = json_decode($resp, true);

$token = $data['data']['access_token'];
$orgId = $data['data']['user']['organizations'][0]['uuid'];

echo "Token: $token\nOrg UUID: $orgId\n";

$ch2 = curl_init('http://localhost:8000/api/v1/modules');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json', 
    'Accept: application/json', 
    "Authorization: Bearer $token", 
    "X-ORG-ID: $orgId"
]);
$resp2 = curl_exec($ch2);
echo "Output: $resp2\n";

