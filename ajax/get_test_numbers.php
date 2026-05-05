<?php
/**
 * Get Available Test Numbers
 * Returns random numbers from active ranges
 */
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

$user = getCurrentUser();
$userId = (int)$user['id'];
$pdo = getDB();

// Get filters
$country = trim($_GET['country'] ?? '');
$service = trim($_GET['service'] ?? '');
$limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100

// Build query based on user role
if (in_array($user['role'], ['admin', 'manager'])) {
    // Admin/Manager: All active numbers
    $sql = "SELECT id, number, country, service FROM numbers WHERE status = 'active'";
    $params = [];
} else {
    // Reseller/Sub-reseller: Only their assigned numbers
    $userIds = getDescendantUserIds($userId);
    if (empty($userIds)) {
        jsonResponse(['status' => 'success', 'numbers' => []]);
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT id, number, country, service FROM numbers WHERE status = 'active' AND assigned_to IN ($placeholders)";
    $params = $userIds;
}

// Add filters
if (!empty($country)) {
    $sql .= " AND country = ?";
    $params[] = $country;
}

if (!empty($service)) {
    $sql .= " AND service = ?";
    $params[] = $service;
}

// Random order and limit
$sql .= " ORDER BY RAND() LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$numbers = $stmt->fetchAll();

// Format response
$result = [];
foreach ($numbers as $num) {
    $result[] = [
        'id' => (int)$num['id'],
        'number' => $num['number'],
        'country' => $num['country'],
        'country_name' => countryName($num['country']),
        'service' => $num['service']
    ];
}

jsonResponse([
    'status' => 'success',
    'numbers' => $result,
    'count' => count($result)
]);
