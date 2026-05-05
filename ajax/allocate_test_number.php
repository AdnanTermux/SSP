<?php
/**
 * Allocate Number to Test User
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
$country = trim($_POST['country'] ?? '');
$service = trim($_POST['service'] ?? '');

if (empty($number)) {
    echo json_encode(['status' => 'error', 'message' => 'Number required']);
    exit;
}

// Check user's limit
$stmt = $pdo->prepare("SELECT number_limit FROM test_users WHERE username = ?");
$stmt->execute([$testUser]);
$limit = $stmt->fetchColumn() ?: 10;

// Check current allocation count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM test_user_numbers WHERE test_username = ?");
$stmt->execute([$testUser]);
$currentCount = $stmt->fetchColumn();

if ($currentCount >= $limit) {
    echo json_encode(['status' => 'error', 'message' => 'Limit reached. You can only allocate ' . $limit . ' numbers.']);
    exit;
}

// Check if number is already allocated
$stmt = $pdo->prepare("SELECT COUNT(*) FROM test_user_numbers WHERE number = ?");
$stmt->execute([$number]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Number already allocated']);
    exit;
}

// Allocate number
try {
    $stmt = $pdo->prepare("
        INSERT INTO test_user_numbers (test_username, number, country, service, allocated_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$testUser, $number, $country, $service]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Number allocated successfully'
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to allocate number']);
}
