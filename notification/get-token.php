<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, UserId');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Include MySQLi connection
require_once '../config.php';

// Enhanced header retrieval function
function getHeaderValue($name) {
    // Try different methods to get headers
    $headers = [];
    
    // Method 1: getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Method 2: $_SERVER array
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('HTTP_', '', $key);
                $header = str_replace('_', '-', $header);
                $header = strtolower($header);
                $headers[$header] = $value;
            }
        }
    }
    
    // Case-insensitive search
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, $name) == 0) {
            return $value;
        }
    }
    
    return null;
}

// Debug: Log all received headers
error_log("=== FCM API Debug Info ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("All Headers: " . json_encode(getallheaders()));
error_log("POST Data: " . file_get_contents('php://input'));

// Get headers using enhanced function
$userId = getHeaderValue('UserId');
$authorization = getHeaderValue('Authorization');

// Debug log
error_log("Extracted UserId: " . ($userId ?? 'NULL'));
error_log("Extracted Authorization: " . ($authorization ?? 'NULL'));

// Validate required headers
if (!$userId || !$authorization) {
    $errorResponse = [
        'success' => false, 
        'message' => 'Missing required headers',
        'debug' => [
            'userId_received' => $userId ? 'yes' : 'no',
            'authorization_received' => $authorization ? 'yes' : 'no',
            'all_headers' => getallheaders(),
            'server_info' => [
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
                'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ]
        ]
    ];
    
    http_response_code(401);
    echo json_encode($errorResponse);
    exit();
}

// Sanitize
$userId = mysqli_real_escape_string($conn, $userId);
$authorization = mysqli_real_escape_string($conn, $authorization);

// Verify user token and active status
$authQuery = "SELECT userID, isActive FROM user WHERE userID = '$userId' AND token = '$authorization' AND isActive = 1";
$authResult = mysqli_query($conn, $authQuery);

if (!$authResult || mysqli_num_rows($authResult) === 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid authorization',
        'debug' => [
            'userId' => $userId,
            'query_executed' => $authQuery,
            'rows_found' => mysqli_num_rows($authResult)
        ]
    ]);
    exit();
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

// Extract and sanitize input
$fcmToken = mysqli_real_escape_string($conn, $input['fcm'] ?? '');
$platform = mysqli_real_escape_string($conn, $input['platform'] ?? 'android');

if (empty($fcmToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'FCM token is required']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Check if user already has a device record (regardless of token)
    $checkQuery = "SELECT id, device_token FROM user_devices WHERE userID = '$userId'";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        // User exists - update their token
        $row = mysqli_fetch_assoc($checkResult);
        $deviceID = $row['id'];
        $oldToken = $row['device_token'];
        
        $updateQuery = "UPDATE user_devices SET 
                        device_token = '$fcmToken', 
                        platform = '$platform', 
                        is_active = 1, 
                        updated_at = NOW() 
                        WHERE userID = '$userId'";
        mysqli_query($conn, $updateQuery);
        
        $message = ($oldToken === $fcmToken) ? 
                   'FCM token refreshed successfully' : 
                   'FCM token updated successfully';
                   
        error_log("Updated FCM token for userID: $userId from '$oldToken' to '$fcmToken'");
    } else {
        // User doesn't exist - insert new record
        $insertQuery = "INSERT INTO user_devices (userID, device_token, platform, is_active, updated_at) 
                        VALUES ('$userId', '$fcmToken', '$platform', 1, NOW())";
        mysqli_query($conn, $insertQuery);
        $message = 'FCM token registered successfully';
        
        error_log("Inserted new FCM token for userID: $userId with token: $fcmToken");
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'userID' => $userId,
            'platform' => $platform,
            'token_registered' => true
        ]
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to manage FCM token',
        'error' => $e->getMessage()
    ]);
}
?>