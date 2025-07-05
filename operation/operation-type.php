<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require '../config.php'; 

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        getOperationTypes($conn);
        break;
    case 'POST':
        createOperationType($conn, $input);
        break;
    case 'PUT':
        updateOperationType($conn, $input);
        break;
    case 'DELETE':
        deleteOperationType($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'message' => 'Method not allowed',
            'statusCode' => 405,
            'outVal' => 0,
            'data' => null
        ]);
        break;
}

function getOperationTypes($conn) {
    $sql = "SELECT 
                id,
                operationName,
                isActive
            FROM operation_type
            ORDER BY id DESC";

    $result = $conn->query($sql);

    if ($result) {
        $data = [];
        $rowNumber = 1;
        while ($row = $result->fetch_assoc()) {
            $row['rowNumber'] = $rowNumber++;
            $row['isActive'] = (bool)$row['isActive'];
            $data[] = $row;
        }

        echo json_encode([
            'message' => 'Record Get Successfully!',
            'statusCode' => 200,
            'outVal' => count($data),
            'data' => $data
        ]);
    } else {
        sendError('Error fetching operation types', 500, $conn->error);
    }
}

function createOperationType($conn, $input) {
    if (empty($input['operationName'])) {
        sendError('Operation name is required', 400);
    }

    $operationName = $conn->real_escape_string($input['operationName']);
    $isActive = isset($input['isActive']) && $input['isActive'] ? 1 : 0;

    $sql = "INSERT INTO operation_type (operationName,  isActive)
            VALUES ('$operationName', $isActive)";

    if ($conn->query($sql)) {
        $newId = $conn->insert_id;
        echo json_encode([
            'message' => 'Operation type created successfully!',
            'statusCode' => 200,
            'outVal' => 1,
            'data' => [
                'operationTypeID' => $newId,
                'operationName' => $operationName,
                'isActive' => (bool)$isActive
            ]
        ]);
    } else {
        sendError('Error creating operation type', 500, $conn->error);
    }
}

function updateOperationType($conn, $input) {

    $operationTypeID = $input['operationTypeID'] ?? $input['id'] ?? null;
$operationName = $input['operationName'] ?? null;

    if (empty($operationTypeID) || empty($input['operationName'])) {
        sendError('Operation type ID and name are required', 400);
    };
    // $operationTypeID = intval($input['id']) ?? intval($input['operationTypeID']);
    $operationName = $conn->real_escape_string($input['operationName']);
    $isActive = isset($input['isActive']) && $input['isActive'] ? 1 : 0;

    $sql = "UPDATE operation_type 
            SET operationName = '$operationName', isActive = $isActive
            WHERE id = $operationTypeID";

    if ($conn->query($sql)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'message' => 'Operation type updated successfully!',
                'statusCode' => 200,
                'outVal' => 1,
                'data' => [
                    'id' => $operationTypeID,
                    'operationName' => $operationName,
                    'isActive' => (bool)$isActive
                ]
            ]);
        } else {
            sendError('Operation type not found', 404);
        }
    } else {
        sendError('Error updating operation type', 500, $conn->error);
    }
}

function deleteOperationType($conn) {
    $operationTypeID = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (empty($operationTypeID)) {
        sendError('Operation type ID is required', 400);
    }

    $sql = "delete from operation_type WHERE id = $operationTypeID";

    if ($conn->query($sql)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'message' => 'Operation type deleted successfully!',
                'statusCode' => 200,
                'outVal' => 1,
                'data' => null
            ]);
        } else {
            sendError('Operation type not found', 404);
        }
    } else {
        sendError('Error deleting operation type', 500, $conn->error);
    }
}

function sendError($message, $statusCode, $errorDetail = null) {
    http_response_code($statusCode);
    echo json_encode([
        'message' => $errorDetail ? "$message: $errorDetail" : $message,
        'statusCode' => $statusCode,
        'outVal' => 0,
        'data' => null
    ]);
    exit;
}

?>