<?php
/**
 * Get OTPs for Test User's Allocated Numbers
 */
session_start();
if (!isset($_SESSION['test_user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$pdo = getDB();
$testUser = $_SESSION['test_username'];

// Get user's allocated numbers
$stmt = $pdo->prepare("SELECT number FROM test_user_numbers WHERE test_username = ?");
$stmt->execute([$testUser]);
$numbers = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($numbers)) {
    echo json_encode(['status' => 'success', 'otps' => []]);
    exit;
}

// Get OTPs for these numbers (last 1 hour)
$placeholders = implode(',', array_fill(0, count($numbers), '?'));
$stmt = $pdo->prepare("
    SELECT 
        number,
        service,
        country,
        otp,
        message,
        received_at
    FROM sms_received
    WHERE number IN ($placeholders)
    AND received_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY received_at DESC
    LIMIT 50
");
$stmt->execute($numbers);
$otps = $stmt->fetchAll();

// Format with privacy masking
$result = [];
foreach ($otps as $otp) {
    // Mask service name
    $service = $otp['service'] ?: 'Unknown';
    $serviceMasked = substr($service, 0, 3) . str_repeat('*', max(0, strlen($service) - 3));
    
    // Mask message
    $message = $otp['message'] ?: '';
    $messagePreview = str_repeat('*', min(6, strlen($message)));
    
    // Time ago
    $time = strtotime($otp['received_at']);
    $diff = time() - $time;
    if ($diff < 60) {
        $timeAgo = $diff . 's ago';
    } elseif ($diff < 3600) {
        $timeAgo = floor($diff / 60) . 'm ago';
    } else {
        $timeAgo = date('H:i', $time);
    }
    
    $result[] = [
        'number' => $otp['number'],
        'service' => $service,
        'service_masked' => $serviceMasked,
        'country' => $otp['country'],
        'otp' => $otp['otp'],
        'message' => $message,
        'message_preview' => $messagePreview,
        'received_at' => $otp['received_at'],
        'time_ago' => $timeAgo
    ];
}

echo json_encode([
    'status' => 'success',
    'otps' => $result
]);
