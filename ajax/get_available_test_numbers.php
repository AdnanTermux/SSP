<?php
/**
 * Get Available Numbers for Test Allocation
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

// Get already allocated numbers by this user
$stmt = $pdo->prepare("SELECT number FROM test_user_numbers WHERE test_username = ?");
$stmt->execute([$testUser]);
$allocatedNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get available numbers (not allocated by anyone, active status)
$sql = "
    SELECT n.number, n.country, n.service 
    FROM numbers n
    WHERE n.status = 'active'
    AND n.number NOT IN (
        SELECT number FROM test_user_numbers
    )
    ORDER BY RAND()
    LIMIT 50
";

$stmt = $pdo->query($sql);
$numbers = $stmt->fetchAll();

$result = [];
foreach ($numbers as $num) {
    $result[] = [
        'number' => $num['number'],
        'country' => $num['country'],
        'service' => $num['service'] ?: 'Any'
    ];
}

echo json_encode([
    'status' => 'success',
    'numbers' => $result
]);
