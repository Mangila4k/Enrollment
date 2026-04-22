<?php
/* ========================================
   SMS CONFIGURATION - WEBHOOK.SITE (TESTING)
   ========================================
   Use this for testing SMS functionality
   Webhook URL: https://webhook.site/9ff4a1a9-e548-437a-bc9a-4961a2939bc3
   ======================================== */

// ========== WEBHOOK CONFIGURATION ==========
define('WEBHOOK_URL', 'https://webhook.site/9ff4a1a9-e548-437a-bc9a-4961a2939bc3');

// ========== DATABASE OTP CONFIGURATION ==========
define('OTP_EXPIRY_MINUTES', 5); // OTP expires in 5 minutes

// ========== LOGGING ==========
define('SMS_LOG_FILE', '../logs/sms_log.txt');

/**
 * Send OTP via webhook
 * 
 * @param string $phone_number The recipient's phone number
 * @param string $otp_code The OTP code to send
 * @return bool True if successful, false otherwise
 */
function sendOTP($phone_number, $otp_code) {
    $message = "PLSNHS: Your OTP code is: " . $otp_code . ". Valid for 5 minutes. Do not share this code with anyone.";
    return sendWebhook($phone_number, $message, $otp_code);
}

/**
 * Send data to webhook.site
 * 
 * @param string $phone_number The recipient's phone number
 * @param string $message The message to send
 * @param string $otp_code The OTP code
 * @return bool True if successful, false otherwise
 */
function sendWebhook($phone_number, $message, $otp_code = null) {
    // Prepare data payload
    $data = [
        'type' => 'otp_verification',
        'phone' => $phone_number,
        'message' => $message,
        'otp_code' => $otp_code ?: extractOTPFromMessage($message),
        'timestamp' => date('Y-m-d H:i:s'),
        'date' => date('F d, Y'),
        'time' => date('h:i:s A'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Send request to webhook
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Webhook-Source: PLSNHS-System'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log the attempt
    $success = false;
    
    if ($curl_error) {
        logToFile($message, false, "cURL Error: " . $curl_error);
    } elseif ($http_code == 200) {
        $success = true;
        logToFile($message, true, "Webhook sent successfully");
    } else {
        logToFile($message, false, "HTTP Error: " . $http_code);
    }
    
    return $success;
}

/**
 * Extract OTP code from message
 * 
 * @param string $message The message containing OTP
 * @return string The extracted OTP code or empty string
 */
function extractOTPFromMessage($message) {
    preg_match('/\b\d{6}\b/', $message, $matches);
    return isset($matches[0]) ? $matches[0] : '';
}

/**
 * Log to file
 * 
 * @param string $message The message content
 * @param bool $success Whether the operation was successful
 * @param string|null $error Error message if any
 */
function logToFile($message, $success, $error = null) {
    $log_dir = dirname(SMS_LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $status = $success ? "SUCCESS" : "FAILED";
    $log_entry = "[" . date('Y-m-d H:i:s') . "] $status | MSG: " . substr($message, 0, 100);
    
    if ($error) {
        $log_entry .= " | DETAILS: $error";
    }
    $log_entry .= "\n";
    
    file_put_contents(SMS_LOG_FILE, $log_entry, FILE_APPEND);
}

/**
 * Generate random OTP code
 * 
 * @param int $length Length of OTP (default: 6)
 * @return string Generated OTP code
 */
function generateOTP($length = 6) {
    return sprintf("%0" . $length . "d", mt_rand(0, pow(10, $length) - 1));
}

/**
 * Store OTP in database
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $otp_code OTP code
 * @param string $type OTP type (password_reset, verification, etc.)
 * @return bool True if successful, false otherwise
 */
function storeOTP($conn, $user_id, $otp_code, $type = 'password_reset') {
    $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    // Delete old OTPs for this user and type
    $delete = $conn->prepare("DELETE FROM otp_codes WHERE user_id = ? AND type = ?");
    $delete->execute([$user_id, $type]);
    
    // Insert new OTP
    $insert = $conn->prepare("INSERT INTO otp_codes (user_id, otp_code, type, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())");
    return $insert->execute([$user_id, $otp_code, $type, $expires_at]);
}

/**
 * Verify OTP code
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $otp_code OTP code to verify
 * @param string $type OTP type
 * @return array Result with success flag and message
 */
function verifyOTP($conn, $user_id, $otp_code, $type = 'password_reset') {
    $stmt = $conn->prepare("SELECT id, expires_at FROM otp_codes WHERE user_id = ? AND otp_code = ? AND type = ? AND is_used = 0 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $otp_code, $type]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$otp) {
        return ['success' => false, 'message' => 'Invalid OTP code'];
    }
    
    $expires_at = new DateTime($otp['expires_at']);
    $now = new DateTime();
    
    if ($now > $expires_at) {
        return ['success' => false, 'message' => 'OTP code has expired'];
    }
    
    // Mark OTP as used
    $update = $conn->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?");
    $update->execute([$otp['id']]);
    
    return ['success' => true, 'message' => 'OTP verified successfully'];
}

/**
 * Test webhook connection
 * 
 * @return bool True if connection successful, false otherwise
 */
function testWebhook() {
    $test_data = [
        'type' => 'test_connection',
        'test' => true,
        'message' => 'Test connection from PLSNHS System',
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'testing'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Webhook-Test: true'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200;
}

/**
 * Get webhook status
 * 
 * @return array Status information
 */
function getWebhookStatus() {
    $is_connected = testWebhook();
    
    return [
        'connected' => $is_connected,
        'webhook_url' => WEBHOOK_URL,
        'last_check' => date('Y-m-d H:i:s'),
        'status' => $is_connected ? 'active' : 'inactive'
    ];
}

/**
 * Send bulk OTP (for multiple users)
 * 
 * @param PDO $conn Database connection
 * @param array $users Array of user data with id and phone
 * @param string $type OTP type
 * @return array Results for each user
 */
function sendBulkOTP($conn, $users, $type = 'password_reset') {
    $results = [];
    
    foreach ($users as $user) {
        $otp_code = generateOTP();
        $phone = $user['phone'] ?? '';
        
        if (empty($phone)) {
            $results[$user['id']] = [
                'success' => false,
                'message' => 'No phone number found'
            ];
            continue;
        }
        
        // Store OTP in database
        if (storeOTP($conn, $user['id'], $otp_code, $type)) {
            // Send OTP via webhook
            $sent = sendOTP($phone, $otp_code);
            $results[$user['id']] = [
                'success' => $sent,
                'message' => $sent ? 'OTP sent successfully' : 'Failed to send OTP',
                'otp_code' => $otp_code
            ];
        } else {
            $results[$user['id']] = [
                'success' => false,
                'message' => 'Failed to store OTP in database'
            ];
        }
    }
    
    return $results;
}

/**
 * Clean expired OTPs from database
 * 
 * @param PDO $conn Database connection
 * @return int Number of deleted records
 */
function cleanExpiredOTPs($conn) {
    $stmt = $conn->prepare("DELETE FROM otp_codes WHERE expires_at < NOW()");
    $stmt->execute();
    return $stmt->rowCount();
}

/**
 * Get recent OTP logs
 * 
 * @param int $limit Number of logs to retrieve
 * @return array Array of log entries
 */
function getRecentLogs($limit = 50) {
    if (!file_exists(SMS_LOG_FILE)) {
        return [];
    }
    
    $logs = file(SMS_LOG_FILE, FILE_IGNORE_NEW_LINES);
    $recent_logs = array_slice(array_reverse($logs), 0, $limit);
    
    $parsed_logs = [];
    foreach ($recent_logs as $log) {
        if (preg_match('/\[(.*?)\] (SUCCESS|FAILED) \| MSG: (.*?)(?:\| DETAILS: (.*))?$/', $log, $matches)) {
            $parsed_logs[] = [
                'timestamp' => $matches[1],
                'status' => $matches[2],
                'message' => $matches[3],
                'details' => $matches[4] ?? null
            ];
        }
    }
    
    return $parsed_logs;
}
?>