<?php
/**
 * Test HTTP Delivery Connection
 */
require_once __DIR__ . '/../functions.php';
requireRole('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
}

$testUrl = trim($_POST['test_url'] ?? '');

if (empty($testUrl) || !filter_var($testUrl, FILTER_VALIDATE_URL)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid URL'], 400);
}

// Create test payload
$testPayload = [
    'number' => '+1234567890',
    'service' => 'TEST',
    'country' => 'US',
    'otp' => '123456',
    'message' => 'This is a test message from Sigma SMS A2P',
    'received_at' => date('Y-m-d H:i:s'),
    'test_mode' => true
];

$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testPayload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: Sigma-SMS-A2P/1.0'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false, // For testing purposes
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    jsonResponse([
        'status' => 'error',
        'message' => 'Connection failed: ' . $error
    ]);
}

if ($httpCode >= 200 && $httpCode < 300) {
    jsonResponse([
        'status' => 'success',
        'message' => 'Connection successful',
        'response_code' => $httpCode,
        'response' => substr($response, 0, 200)
    ]);
} else {
    jsonResponse([
        'status' => 'error',
        'message' => 'Server returned error code: ' . $httpCode,
        'response_code' => $httpCode
    ]);
}
