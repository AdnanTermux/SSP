<?php
/**
 * Run Test (Legacy - kept for compatibility)
 * The new test panel uses real numbers and real OTPs
 * This endpoint is kept for any legacy integrations
 */
require_once __DIR__ . '/../functions.php';
requireLogin();
header('Content-Type: application/json');

jsonResponse([
    'status' => 'info',
    'message' => 'Test panel now uses real numbers. Please use the new test panel interface.',
    'redirect' => APP_URL . '/test_panel.php'
]);
