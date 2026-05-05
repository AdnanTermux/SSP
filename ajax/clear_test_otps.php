<?php
/**
 * Clear Test OTPs
 * Clears test OTPs for current user
 */
require_once __DIR__ . '/../functions.php';
requireLogin();
requirePostWithCsrf();
header('Content-Type: application/json');

$user = getCurrentUser();
$userId = (int)$user['id'];
$pdo = getDB();

// Get user's numbers
if (in_array($user['role'], ['admin', 'manager'])) {
    // Admin/Manager: Clear all test OTPs
    $stmt = $pdo->query("
        DELETE FROM sms_received 
        WHERE received_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $deleted = $stmt->rowCount();
} else {
    // Reseller/Sub-reseller: Only their numbers
    $numberIds = getAssignedNumberIds($userId, true);
    
    if (empty($numberIds)) {
        jsonResponse(['status' => 'success', 'deleted' => 0]);
    }
    
    $placeholders = implode(',', array_fill(0, count($numberIds), '?'));
    $stmt = $pdo->prepare("
        DELETE sr FROM sms_received sr
        JOIN numbers n ON sr.number = n.number
        WHERE n.id IN ($placeholders)
        AND sr.received_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute($numberIds);
    $deleted = $stmt->rowCount();
}

jsonResponse([
    'status' => 'success',
    'message' => "Cleared $deleted test OTPs",
    'deleted' => $deleted
]);
