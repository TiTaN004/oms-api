<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch($method) {
    case 'GET':
        getUsers($conn);
        break;
    case 'POST':
        createUser($conn, $input);
        break;
    case 'PUT':
        updateUser($conn, $input);
        break;
    case 'DELETE':
        deleteUser($conn);
        break;
    default:
        http_response_code(405);
        sendResponse('Method not allowed', 405, 0, null);
        break;
}

function getUsers($conn) {
    $sql = "SELECT 
                userID,
                fullName,
                userName,
                password,
                operationTypeID,
                mobileNo,
                emailID,
                isActive,
                isAdmin
            FROM user
            ORDER BY userID DESC";

    $result = $conn->query($sql);
    $users = [];


    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['isActive'] = (bool)$row['isActive'];
            $users[] = $row;
        }
    }

    sendResponse('Record Get Successfully!', 200, count($users), $users);
}

function createUser($conn, $input) {
    $required = ['fullName', 'userName', 'password', 'operationTypeID', 'mobileNo', 'emailID'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sendResponse(ucfirst($field) . ' is required', 400, 0, null);
            return;
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user WHERE (userName = ? OR emailID = ?)");
    if (!$stmt) {
        sendResponse('Prepare failed: ' . $conn->error, 500, 0, null);
        return;
    }
    $stmt->bind_param("ss", $input['userName'], $input['emailID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['total'];
    $stmt->close();

    if ($count > 0) {
        sendResponse('Username or email already exists', 400, 0, null);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO user (fullName, userName, password, operationTypeID, mobileNo, emailID, isActive) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $isActive = $input['isActive'] ?? 1;
    $stmt->bind_param("sssissi",
        $input['fullName'],
        $input['userName'],
        $input['password'], 
        $input['operationTypeID'],
        $input['mobileNo'],
        $input['emailID'],
        $isActive
    );

    if ($stmt->execute()) {
        $userID = $conn->insert_id;
        sendResponse('User created successfully!', 200, 1, [
            'userID' => $userID,
            'fullName' => $input['fullName'],
            'userName' => $input['userName'],
            'operationTypeID' => $input['operationTypeID'],
            'password' => $input['password'],
            'mobileNo' => $input['mobileNo'],
            'emailID' => $input['emailID'],
            'isActive' => (bool)$isActive
        ]);
    } else {
        sendResponse('Failed to create user' . $stmt->error, 500, 0, null);
    }
}

function updateUser($conn, $input) {
    if (empty($input['userID'])) {
        sendResponse('User ID is required', 400, 0, null);
        return;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user WHERE (userName = ? OR emailID = ?) AND userID != ? ");
    $stmt->bind_param("ssi", $input['userName'], $input['emailID'], $input['userID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['total'];
    $stmt->close();

    if ($count > 0) {
        sendResponse('Username or email already exists', 400, 0, null);
        return;
    }

    $stmt = $conn->prepare("UPDATE user SET fullName = ?, userName = ?, password = ?, operationTypeID = ?, mobileNo = ?, emailID = ?, isActive = ? WHERE userID = ?");
    $isActive = $input['isActive'] ?? 1;
    $stmt->bind_param("sssissii",
        $input['fullName'],
        $input['userName'],
        $input['password'],
        $input['operationTypeID'],
        $input['mobileNo'],
        $input['emailID'],
        $isActive,
        $input['userID']
    );

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        sendResponse('User updated successfully!', 200, 1, $input);
    } else {
        sendResponse('User not found or no changes made', 404, 0, null);
    }
}

// function deleteUser($conn) {
//     $userID = $_GET['id'] ?? null;

//     if (empty($userID)) {
//         sendResponse('User ID is required', 400, 0, null);
//         return;
//     }

//     $stmt = $conn->prepare("DELETE from user WHERE userID = ?");
//     $stmt->bind_param("i", $userID);

//     if ($stmt->execute() && $stmt->affected_rows > 0) {
//         sendResponse('User deleted successfully!', 200, 1, null);
//     } else {
//         sendResponse('User not found', 404, 0, null);
//     }
// }

function deleteUser($conn) {
    $userID = $_GET['id'] ?? null;

    if (empty($userID)) {
        sendResponse('User ID is required', 400, 0, null);
        return;
    }

    // Delete from password_reset_tokens
    $stmt1 = $conn->prepare("DELETE FROM password_reset_tokens WHERE userID = ?");
    if (!$stmt1) {
        error_log("Prepare failed (password_reset_tokens): " . $conn->error);
    } else {
        $stmt1->bind_param("i", $userID);
        $stmt1->execute();
    }

    // Delete from user_devices
    $stmt2 = $conn->prepare("DELETE FROM user_devices WHERE userID = ?");
    if (!$stmt2) {
        error_log("Prepare failed (user_devices): " . $conn->error);
    } else {
        $stmt2->bind_param("i", $userID);
        $stmt2->execute();
    }

    // Delete from user
    $stmt = $conn->prepare("DELETE FROM user WHERE userID = ?");
    if (!$stmt) {
        error_log("Prepare failed (user): " . $conn->error);
        sendResponse('Internal server error', 500, 0, null);
        return;
    }

    $stmt->bind_param("i", $userID);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        sendResponse('User deleted successfully!', 200, 1, null);
    } else {
        error_log("Delete failed or user not found. ID: $userID, Affected rows: " . $stmt->affected_rows);
        sendResponse('User not found or deletion failed', 404, 0, null);
    }
}




function sendResponse($message, $statusCode, $outVal, $data) {
    http_response_code($statusCode);
    echo json_encode([
        'message' => $message,
        'statusCode' => $statusCode,
        'outVal' => $outVal,
        'data' => $data
    ]);
}