<?php
/**
 * Sigma SMS A2P — SMS Receiving Webhook
 * This endpoint receives SMS from your number provider via HTTP
 * 
 * USAGE:
 * Give this URL to your provider: https://your-domain.com/api/receive_sms.php
 * 
 * SUPPORTED FORMATS:
 * 1. DataTables JSON format (aaData array)
 * 2. Standard JSON POST
 * 3. Form Data POST
 * 4. GET parameters
 * 
 * DATATABLES FORMAT:
 * {
 *   "aaData": [
 *     ["2026-05-05 12:05:25", "Myanmar M 1000K", "959699192862", "TikTok", "User", "Message", null, 0, 0],
 *     ...
 *   ]
 * }
 * Array format: [timestamp, range, number, service, user, message, currency, rate, profit]
 * 
 * STANDARD FORMAT:
 * {
 *   "number": "+1234567890",
 *   "message": "Your code is 123456",
 *   "service": "WhatsApp",
 *   "country": "US"
 * }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Enable error logging
error_log("=== SMS Webhook Called ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Always return JSON
header('Content-Type: application/json');

// Allow CORS (if needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get input data
$data = [];
$rawInput = '';

// Try JSON POST first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // JSON payload
        $rawInput = file_get_contents('php://input');
        error_log("JSON Input: " . $rawInput);
        $data = json_decode($rawInput, true) ?: [];
    } else {
        // Form data
        $data = $_POST;
        error_log("POST Data: " . print_r($_POST, true));
    }
}

// Fallback to GET
if (empty($data)) {
    $data = $_GET;
    error_log("GET Data: " . print_r($_GET, true));
}

// Check if this is DataTables format
if (isset($data['aaData']) && is_array($data['aaData'])) {
    error_log("DataTables format detected with " . count($data['aaData']) . " records");
    
    $pdo = getDB();
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($data['aaData'] as $index => $row) {
        // DataTables format: [timestamp, range, number, service, user, message, currency, rate, profit]
        if (!is_array($row) || count($row) < 6) {
            error_log("Invalid row format at index $index");
            $errorCount++;
            continue;
        }
        
        $timestamp = trim($row[0] ?? '');
        $range = trim($row[1] ?? '');
        $number = trim($row[2] ?? '');
        $service = trim($row[3] ?? '');
        $assignedUser = trim($row[4] ?? '');
        $message = trim($row[5] ?? '');
        
        // Validate required fields
        if (empty($number) || empty($message)) {
            error_log("Missing required fields at index $index: number=$number, message=$message");
            $errorCount++;
            continue;
        }
        
        // Normalize phone number
        $number = preg_replace('/[^0-9+]/', '', $number);
        if (!str_starts_with($number, '+')) {
            $number = '+' . $number;
        }
        
        // Extract OTP from message
        $otp = '';
        if (preg_match('/\b(\d{4,8})\b/', $message, $matches)) {
            $otp = $matches[1];
        }
        
        // Auto-detect country from number
        $country = '';
        if (preg_match('/^\+95/', $number)) {
            $country = 'MM'; // Myanmar
        } elseif (preg_match('/^\+1/', $number)) {
            $country = 'US';
        } elseif (preg_match('/^\+44/', $number)) {
            $country = 'UK';
        } elseif (preg_match('/^\+91/', $number)) {
            $country = 'IN';
        }
        
        // Parse timestamp
        $receivedAt = date('Y-m-d H:i:s');
        if (!empty($timestamp)) {
            $parsedTime = strtotime($timestamp);
            if ($parsedTime !== false) {
                $receivedAt = date('Y-m-d H:i:s', $parsedTime);
            }
        }
        
        // Check if number exists in database
        $stmt = $pdo->prepare("SELECT id, assigned_to, rate FROM numbers WHERE number = ? AND status = 'active'");
        $stmt->execute([$number]);
        $numberRecord = $stmt->fetch();
        
        // Insert SMS into database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sms_received (number, service, country, otp, message, received_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$number, $service, $country, $otp, $message, $receivedAt]);
            $smsId = $pdo->lastInsertId();
            
            error_log("SMS saved with ID: $smsId (number: $number, service: $service)");
            
            // Calculate and log profit if number is assigned
            if ($numberRecord && $numberRecord['assigned_to'] && $numberRecord['rate'] > 0) {
                $userId = $numberRecord['assigned_to'];
                $rate = $numberRecord['rate'];
                
                // Get user's profit percentage
                $userStmt = $pdo->prepare("SELECT profit_percentage FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();
                
                if ($user) {
                    $profitPercentage = $user['profit_percentage'] ?? 0;
                    $profitAmount = $rate * ($profitPercentage / 100);
                    
                    // Log profit
                    $profitStmt = $pdo->prepare("
                        INSERT INTO profit_log (user_id, sms_received_id, profit_amount, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $profitStmt->execute([$userId, $smsId, $profitAmount]);
                    
                    error_log("Profit logged: User $userId earned $profitAmount");
                }
            }
            
            $successCount++;
            
        } catch (Exception $e) {
            error_log("ERROR saving SMS at index $index: " . $e->getMessage());
            $errorCount++;
            $errors[] = "Row $index: " . $e->getMessage();
        }
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Processed " . count($data['aaData']) . " records",
        'success_count' => $successCount,
        'error_count' => $errorCount,
        'errors' => $errors
    ]);
    exit;
}

// Standard format processing
error_log("Standard format detected");

// Extract parameters (support multiple naming conventions)
$number = trim($data['number'] ?? $data['phone'] ?? $data['to'] ?? $data['recipient'] ?? '');
$message = trim($data['message'] ?? $data['text'] ?? $data['sms'] ?? $data['content'] ?? '');
$otp = trim($data['otp'] ?? $data['code'] ?? $data['verification_code'] ?? '');
$service = trim($data['service'] ?? $data['app'] ?? $data['sender'] ?? '');
$country = strtoupper(trim($data['country'] ?? $data['country_code'] ?? ''));
$timestamp = trim($data['timestamp'] ?? $data['received_at'] ?? $data['date'] ?? '');
$apiKey = trim($data['api_key'] ?? $data['key'] ?? $data['token'] ?? '');

// Validate required fields
if (empty($number)) {
    error_log("ERROR: Number is required");
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Number is required',
        'received_data' => $data
    ]);
    exit;
}

if (empty($message)) {
    error_log("ERROR: Message is required");
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Message is required',
        'received_data' => $data
    ]);
    exit;
}

// Optional: Verify API key (uncomment if you want security)
/*
$expectedApiKey = getSetting('sms_webhook_api_key', '');
if (!empty($expectedApiKey) && $apiKey !== $expectedApiKey) {
    error_log("ERROR: Invalid API key");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}
*/

// Normalize phone number
$number = preg_replace('/[^0-9+]/', '', $number);
if (!str_starts_with($number, '+')) {
    $number = '+' . $number;
}

// Extract OTP if not provided
if (empty($otp)) {
    // Common OTP patterns
    if (preg_match('/\b(\d{4,8})\b/', $message, $matches)) {
        $otp = $matches[1];
    }
}

// Auto-detect service from message if not provided
if (empty($service)) {
    $messageLower = strtolower($message);
    if (strpos($messageLower, 'whatsapp') !== false) {
        $service = 'WhatsApp';
    } elseif (strpos($messageLower, 'telegram') !== false) {
        $service = 'Telegram';
    } elseif (strpos($messageLower, 'facebook') !== false) {
        $service = 'Facebook';
    } elseif (strpos($messageLower, 'google') !== false) {
        $service = 'Google';
    } elseif (strpos($messageLower, 'instagram') !== false) {
        $service = 'Instagram';
    } elseif (strpos($messageLower, 'twitter') !== false || strpos($messageLower, 'x.com') !== false) {
        $service = 'Twitter';
    } elseif (strpos($messageLower, 'uber') !== false) {
        $service = 'Uber';
    } elseif (strpos($messageLower, 'amazon') !== false) {
        $service = 'Amazon';
    } else {
        $service = 'Unknown';
    }
}

// Auto-detect country from number if not provided
if (empty($country)) {
    // Simple country code detection from phone number
    if (preg_match('/^\+1/', $number)) {
        $country = 'US';
    } elseif (preg_match('/^\+44/', $number)) {
        $country = 'UK';
    } elseif (preg_match('/^\+91/', $number)) {
        $country = 'IN';
    } elseif (preg_match('/^\+86/', $number)) {
        $country = 'CN';
    } elseif (preg_match('/^\+81/', $number)) {
        $country = 'JP';
    } elseif (preg_match('/^\+49/', $number)) {
        $country = 'DE';
    } elseif (preg_match('/^\+33/', $number)) {
        $country = 'FR';
    } elseif (preg_match('/^\+39/', $number)) {
        $country = 'IT';
    } elseif (preg_match('/^\+34/', $number)) {
        $country = 'ES';
    } elseif (preg_match('/^\+7/', $number)) {
        $country = 'RU';
    } elseif (preg_match('/^\+55/', $number)) {
        $country = 'BR';
    } elseif (preg_match('/^\+61/', $number)) {
        $country = 'AU';
    } elseif (preg_match('/^\+82/', $number)) {
        $country = 'KR';
    } elseif (preg_match('/^\+62/', $number)) {
        $country = 'ID';
    } elseif (preg_match('/^\+63/', $number)) {
        $country = 'PH';
    } elseif (preg_match('/^\+66/', $number)) {
        $country = 'TH';
    } elseif (preg_match('/^\+84/', $number)) {
        $country = 'VN';
    } elseif (preg_match('/^\+60/', $number)) {
        $country = 'MY';
    } elseif (preg_match('/^\+65/', $number)) {
        $country = 'SG';
    } elseif (preg_match('/^\+971/', $number)) {
        $country = 'AE';
    } elseif (preg_match('/^\+966/', $number)) {
        $country = 'SA';
    } elseif (preg_match('/^\+20/', $number)) {
        $country = 'EG';
    } elseif (preg_match('/^\+234/', $number)) {
        $country = 'NG';
    } elseif (preg_match('/^\+27/', $number)) {
        $country = 'ZA';
    } elseif (preg_match('/^\+52/', $number)) {
        $country = 'MX';
    } elseif (preg_match('/^\+54/', $number)) {
        $country = 'AR';
    } elseif (preg_match('/^\+56/', $number)) {
        $country = 'CL';
    } elseif (preg_match('/^\+57/', $number)) {
        $country = 'CO';
    } elseif (preg_match('/^\+51/', $number)) {
        $country = 'PE';
    }
}

// Parse timestamp
if (!empty($timestamp)) {
    // Try to parse various formats
    $receivedAt = date('Y-m-d H:i:s', strtotime($timestamp));
} else {
    $receivedAt = date('Y-m-d H:i:s');
}

// Check if number exists in database
$pdo = getDB();
$stmt = $pdo->prepare("SELECT id, assigned_to, rate FROM numbers WHERE number = ? AND status = 'active'");
$stmt->execute([$number]);
$numberRecord = $stmt->fetch();

if (!$numberRecord) {
    error_log("WARNING: Number not found in database: $number");
    // Still save the SMS, but without profit calculation
    $numberRecord = ['id' => null, 'assigned_to' => null, 'rate' => 0];
}

// Insert SMS into database
try {
    $stmt = $pdo->prepare("
        INSERT INTO sms_received (number, service, country, otp, message, received_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$number, $service, $country, $otp, $message, $receivedAt]);
    $smsId = $pdo->lastInsertId();
    
    error_log("SUCCESS: SMS saved with ID: $smsId");
    
    // Calculate and log profit if number is assigned
    if ($numberRecord['assigned_to'] && $numberRecord['rate'] > 0) {
        $userId = $numberRecord['assigned_to'];
        $rate = $numberRecord['rate'];
        
        // Get user's profit percentage
        $userStmt = $pdo->prepare("SELECT profit_percentage FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if ($user) {
            $profitPercentage = $user['profit_percentage'] ?? 0;
            $profitAmount = $rate * ($profitPercentage / 100);
            
            // Log profit
            $profitStmt = $pdo->prepare("
                INSERT INTO profit_log (user_id, sms_received_id, profit_amount, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $profitStmt->execute([$userId, $smsId, $profitAmount]);
            
            error_log("Profit logged: User $userId earned $profitAmount");
        }
    }
    
    // Forward to HTTP delivery if configured
    $httpDeliveryEnabled = getSetting('http_delivery_enabled', '0');
    if ($httpDeliveryEnabled === '1') {
        $deliveryUrl = getSetting('http_delivery_url', '');
        if (!empty($deliveryUrl)) {
            try {
                $deliveryData = [
                    'number' => $number,
                    'service' => $service,
                    'country' => $country,
                    'otp' => $otp,
                    'message' => $message,
                    'received_at' => $receivedAt,
                    'sms_id' => $smsId
                ];
                
                $ch = curl_init($deliveryUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($deliveryData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                error_log("HTTP Delivery: Sent to $deliveryUrl, Response: $httpCode");
            } catch (Exception $e) {
                error_log("HTTP Delivery Error: " . $e->getMessage());
            }
        }
    }
    
    // Return success
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'SMS received successfully',
        'sms_id' => $smsId,
        'data' => [
            'number' => $number,
            'service' => $service,
            'country' => $country,
            'otp' => $otp,
            'received_at' => $receivedAt
        ]
    ]);
    
} catch (Exception $e) {
    error_log("ERROR: Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save SMS: ' . $e->getMessage()
    ]);
}
