<?php
/**
 * Release Number from Test User
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
$number = trim($_POST['number'] ?? '');

if (empty($number)) {
    echo json_encode(['status' => 'error', 'message' => 'Number required']);
    exit;
}

// Release number
try {
    $stmt = $pdo->prepare("
        DELETE FROM test_user_numbers 
        WHERE test_username = ? AND number = ?
    ");
    $stmt->execute([$testUser, $number]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Number released successfully'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Number not found']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to release number']);
}
