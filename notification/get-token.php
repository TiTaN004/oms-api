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
require_once '../config.php'; // Make sure this defines $conn

// Get headers
$headers = getallheaders();
$userId = $headers['UserId'] ?? null;
$authorization = $headers['Authorization'] ?? null;

// Validate required headers
if (!$userId || !$authorization) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Missing required headers']);
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
    echo json_encode(['success' => false, 'message' => 'Invalid authorization']);
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

// $allowedPlatforms = ['android', 'ios'];
// if (!in_array($platform, $allowedPlatforms)) {
//     http_response_code(400);
//     echo json_encode(['success' => false, 'message' => 'Invalid platform. Must be android or ios']);
//     exit();
// }

// Start logic
mysqli_begin_transaction($conn);

try {
    // Check if token exists
    $checkQuery = "SELECT id FROM user_devices WHERE userID = '$userId' AND device_token = '$fcmToken'";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        $row = mysqli_fetch_assoc($checkResult);
        $deviceID = $row['id'];
        $updateQuery = "UPDATE user_devices SET platform = '$platform', is_active = 1, updated_at = NOW() WHERE id = '$deviceID'";
        mysqli_query($conn, $updateQuery);
        $message = 'FCM token updated successfully';
    } else {
        $insertQuery = "INSERT INTO user_devices (userID, device_token, platform, is_active, updated_at) 
                        VALUES ('$userId', '$fcmToken', '$platform', 1, NOW())";
        mysqli_query($conn, $insertQuery);
        $message = 'FCM token registered successfully';
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
        'error' => $e->getMessage() // remove in production
    ]);
}
?>
