<?php
/**
 * Get Received Test OTPs
 * Returns OTPs with privacy masking
 */
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

$user = getCurrentUser();
$userId = (int)$user['id'];
$pdo = getDB();

// Get user's numbers
if (in_array($user['role'], ['admin', 'manager'])) {
    // Admin/Manager: All numbers
    $stmt = $pdo->query("SELECT id FROM numbers WHERE status = 'active'");
    $numberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Reseller/Sub-reseller: Only their numbers
    $numberIds = getAssignedNumberIds($userId, true);
}

if (empty($numberIds)) {
    jsonResponse(['status' => 'success', 'otps' => [], 'stats' => ['active' => 0, 'success_rate' => 0]]);
}

// Get recent OTPs (last 1 hour)
$placeholders = implode(',', array_fill(0, count($numberIds), '?'));
$stmt = $pdo->prepare("
    SELECT 
        sr.id,
        sr.number,
        sr.service,
        sr.country,
        sr.otp,
        sr.message,
        sr.received_at,
        n.id as number_id
    FROM sms_received sr
    JOIN numbers n ON sr.number = n.number
    WHERE n.id IN ($placeholders)
    AND sr.received_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY sr.received_at DESC
    LIMIT 50
");
$stmt->execute($numberIds);
$otps = $stmt->fetchAll();

// Format with privacy masking
$result = [];
foreach ($otps as $otp) {
    // Mask service name (e.g., WhatsApp -> WHA****)
    $service = $otp['service'] ?: 'Unknown';
    $serviceMasked = substr($service, 0, 3) . str_repeat('*', max(0, strlen($service) - 3));
    
    // Mask message (show only first 20 chars + ...)
    $message = $otp['message'] ?: '';
    $messagePreview = strlen($message) > 20 ? substr($message, 0, 20) . '...' : $message;
    $messagePreview = str_repeat('*', min(6, strlen($messagePreview)));
    
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
        'id' => (int)$otp['id'],
        'number' => $otp['number'],
        'service' => $service,
        'service_masked' => $serviceMasked,
        'country' => $otp['country'],
        'otp' => $otp['otp'],
        'message' => $message, // Full message for modal
        'message_preview' => $messagePreview, // Masked for table
        'received_at' => $otp['received_at'],
        'time_ago' => $timeAgo
    ];
}

// Calculate stats
$totalTests = count($result);
$activeTests = $pdo->query("
    SELECT COUNT(DISTINCT number) 
    FROM sms_received 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
")->fetchColumn();

$successRate = $totalTests > 0 ? round(($totalTests / count($numberIds)) * 100, 1) : 0;

jsonResponse([
    'status' => 'success',
    'otps' => $result,
    'stats' => [
        'active' => (int)$activeTests,
        'success_rate' => $successRate
    ]
]);
