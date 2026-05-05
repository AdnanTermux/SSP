<?php
/**
 * Refresh Math CAPTCHA
 */
session_start();
header('Content-Type: application/json');

$num1 = rand(1, 10);
$num2 = rand(1, 10);

$_SESSION['math_captcha'] = [
    'num1' => $num1,
    'num2' => $num2,
    'answer' => $num1 + $num2
];

echo json_encode([
    'status' => 'success',
    'num1' => $num1,
    'num2' => $num2
]);
