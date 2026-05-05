<?php
/**
 * Get HTTP Delivery Log
 */
require_once __DIR__ . '/../functions.php';
requireRole('admin');
header('Content-Type: application/json');

$pdo = getDB();

// Get recent delivery attempts
$stmt = $pdo->query("
    SELECT * FROM http_delivery_log 
    ORDER BY created_at DESC 
    LIMIT 20
");
$logs = $stmt->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
    FROM http_delivery_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetch();

jsonResponse([
    'status' => 'success',
    'logs' => array_map(function($log) {
        return [
            'timestamp' => $log['created_at'],
            'number' => $log['number'],
            'success' => (bool)$log['success'],
            'response' => $log['response_message']
        ];
    }, $logs),
    'stats' => $stats
]);
