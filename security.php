<?php
/**
 * Sigma SMS A2P — Security Functions
 * Rate limiting, input validation, and security utilities
 */

/**
 * Rate Limiter
 * Prevents brute force and abuse
 */
class RateLimiter {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if action is rate limited
     * @param string $identifier User ID, IP, or unique identifier
     * @param string $action Action type (login, api_call, etc.)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if rate limit exceeded
     */
    public function isRateLimited(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM rate_limits 
            WHERE identifier = ? AND action = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $action, $windowSeconds]);
        $count = $stmt->fetchColumn();
        
        return $count >= $maxAttempts;
    }
    
    /**
     * Record an attempt
     */
    public function recordAttempt(string $identifier, string $action): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO rate_limits (identifier, action, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$identifier, $action]);
        
        // Clean old records (older than 1 hour)
        $this->pdo->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 300): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM rate_limits 
            WHERE identifier = ? AND action = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $action, $windowSeconds]);
        $count = $stmt->fetchColumn();
        
        return max(0, $maxAttempts - $count);
    }
    
    /**
     * Clear rate limit for identifier
     */
    public function clearLimit(string $identifier, string $action): void {
        $stmt = $this->pdo->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?");
        $stmt->execute([$identifier, $action]);
    }
}

/**
 * Input Sanitizer
 */
class InputSanitizer {
    /**
     * Sanitize string input
     */
    public static function sanitizeString(string $input, int $maxLength = 255): string {
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return substr($input, 0, $maxLength);
    }
    
    /**
     * Sanitize phone number
     */
    public static function sanitizePhone(string $phone): string {
        // Remove all non-digit and non-plus characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        // Ensure + is only at the start
        $phone = '+' . str_replace('+', '', $phone);
        return substr($phone, 0, 20);
    }
    
    /**
     * Sanitize email
     */
    public static function sanitizeEmail(string $email): string {
        $email = trim(strtolower($email));
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Validate email
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number
     */
    public static function isValidPhone(string $phone): bool {
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }
    
    /**
     * Sanitize URL
     */
    public static function sanitizeUrl(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    /**
     * Validate URL
     */
    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Sanitize integer
     */
    public static function sanitizeInt($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int {
        $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        $value = (int)$value;
        return max($min, min($max, $value));
    }
    
    /**
     * Sanitize float
     */
    public static function sanitizeFloat($value, float $min = PHP_FLOAT_MIN, float $max = PHP_FLOAT_MAX): float {
        $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $value = (float)$value;
        return max($min, min($max, $value));
    }
}

/**
 * SQL Injection Prevention
 * Always use prepared statements, but this adds extra layer
 */
class SQLSanitizer {
    /**
     * Escape identifier (table/column name)
     */
    public static function escapeIdentifier(string $identifier): string {
        // Remove any backticks and escape
        $identifier = str_replace('`', '', $identifier);
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid identifier');
        }
        return '`' . $identifier . '`';
    }
    
    /**
     * Validate ORDER BY direction
     */
    public static function sanitizeOrderDirection(string $direction): string {
        $direction = strtoupper(trim($direction));
        return in_array($direction, ['ASC', 'DESC']) ? $direction : 'ASC';
    }
}

/**
 * XSS Prevention
 */
class XSSProtection {
    /**
     * Clean HTML output
     */
    public static function clean(string $input): string {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Clean for JavaScript context
     */
    public static function cleanJS(string $input): string {
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * Clean for URL context
     */
    public static function cleanURL(string $input): string {
        return urlencode($input);
    }
}

/**
 * Password Security
 */
class PasswordSecurity {
    /**
     * Validate password strength
     */
    public static function isStrongPassword(string $password): array {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Hash password
     */
    public static function hash(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password
     */
    public static function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

/**
 * Session Security
 */
class SessionSecurity {
    /**
     * Regenerate session ID
     */
    public static function regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    /**
     * Validate session
     */
    public static function validate(): bool {
        // Check if session has user agent
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        // Verify user agent hasn't changed
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            return false;
        }
        
        // Check session timeout (24 hours)
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > 86400) {
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Destroy session securely
     */
    public static function destroy(): void {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
}

/**
 * IP Security
 */
class IPSecurity {
    /**
     * Get real client IP
     */
    public static function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Check if IP is in whitelist
     */
    public static function isWhitelisted(string $ip, array $whitelist): bool {
        return in_array($ip, $whitelist);
    }
    
    /**
     * Check if IP is in blacklist
     */
    public static function isBlacklisted(string $ip, array $blacklist): bool {
        return in_array($ip, $blacklist);
    }
}

/**
 * File Upload Security
 */
class FileUploadSecurity {
    private static $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private static $maxFileSize = 5242880; // 5MB
    
    /**
     * Validate uploaded file
     */
    public static function validateUpload(array $file): array {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
            return ['valid' => false, 'errors' => $errors];
        }
        
        if ($file['size'] > self::$maxFileSize) {
            $errors[] = 'File size exceeds maximum allowed (5MB)';
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$allowedExtensions)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Invalid file MIME type';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'extension' => $ext,
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Generate safe filename
     */
    public static function generateSafeFilename(string $originalName): string {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50);
        return $basename . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    }
}
