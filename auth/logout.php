<?php
// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: *');
// header('Access-Control-Allow-Headers: Content-Type, Authorization, UserId');

// if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
//     http_response_code(405);
//     echo json_encode(['success' => false, 'message' => 'Method not allowed']);
//     exit;
// }

// // Include your database connection
// include_once '../config.php'; // Make sure this sets up $conn (MySQLi)

// try {
//     // Get headers
//     $headers = getallheaders();
//     $userId = $_GET['userId'] ?? null;
//     $token = $_GET['token'] ?? null;

//     if (!$userId || !$token) {
//         http_response_code(401);
//         echo json_encode([
//             'success' => false,
//             'message' => 'Unauthorized'
//         ]);
//         exit;
//     }

//     // Prepare and execute update query to nullify token
//     $stmt = $conn->prepare("UPDATE user SET token = NULL WHERE userID = ? AND token = ?");
//     $stmt->bind_param("is", $userId, $token);
//     $stmt->execute();

//     if ($stmt->affected_rows > 0) {
//         // Clear FCM token as well
//         $stmt2 = $conn->prepare("UPDATE user SET fcm_token = NULL WHERE userID = ?");
//         $stmt2->bind_param("i", $userId);
//         $stmt2->execute();

//         echo json_encode([
//             'success' => true,
//             'data' => true,
//             'message' => 'Logout successful'
//         ]);
//     } else {
//         http_response_code(401);
//         echo json_encode([
//             'success' => false,
//             'message' => 'Invalid session'
//         ]);
//     }

//     $stmt->close();
//     if (isset($stmt2)) {
//         $stmt2->close();
//     }

// } catch (Exception $e) {
//     http_response_code(500);
//     echo json_encode([
//         'success' => false,
//         'message' => 'Server error'
//     ]);
// }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, UserId');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include your database connection
include_once '../config.php'; // Make sure this sets up $conn (MySQLi)

try {
    // Get headers
// logout.php

$headers = getallheaders();
$userId  = $_GET['userId'] ?? null;
$token = (isset($_GET['token']) && $_GET['token'] === '-') 
    ? ($headers['Authorization'] ?? null) 
    : ($_GET['token'] ?? null);


    if (!$userId || !$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized',
            'token' => $token,
        ]);
        exit;
    }

    // Check prepare for token nullification
    $stmt = $conn->prepare("UPDATE user SET token = NULL WHERE userID = ? AND token = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("is", $userId, $token);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Clear FCM token as well
        $stmt2 = $conn->prepare("UPDATE user_devices SET device_token = NULL WHERE userID = ?");
        if (!$stmt2) {
            throw new Exception("Prepare (FCM clear) failed: " . $conn->error);
        }

        $stmt2->bind_param("i", $userId);
        $stmt2->execute();

        echo json_encode([
            'success' => true,
            'data' => true,
            'message' => 'Logout successful'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid session'
        ]);
    }

    $stmt->close();
    if (isset($stmt2)) {
        $stmt2->close();
    }

    // echo json_encode([
    //     'success' => true,
    //     'data' => true,
    //     'message' => 'Logout successful'
    // ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage() // Show actual DB error
    ]);
}

?>
