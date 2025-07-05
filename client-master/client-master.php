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
        getClient($conn);
        break;
    case 'POST':
        createClient($conn, $input);
        break;
    case 'PUT':
        updateClient($conn, $input);
        break;
    case 'DELETE':
        deleteClient($conn);
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

function getClient($conn) {
    $sql = "SELECT 
                id,
                clientName,
                isActive
            FROM client_master
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
        sendError('Error fetching client types', 500, $conn->error);
    }
}

function createClient($conn, $input) {
    if (empty($input['clientName'])) {
        sendError('clientName name is required', 400);
    }

    $clientName = $conn->real_escape_string($input['clientName']);
    $isActive = isset($input['isActive']) && $input['isActive'] ? 1 : 0;

    $sql = "INSERT INTO client_master (clientName,  isActive)
            VALUES ('$clientName', $isActive)";

    if ($conn->query($sql)) {
        $newId = $conn->insert_id;
        echo json_encode([
            'message' => 'client created successfully!',
            'statusCode' => 200,
            'outVal' => 1,
            'data' => [
                'clientID' => $newId,
                'id' => $newId,
                'clientName' => $clientName,
                'isActive' => (bool)$isActive
            ]
        ]);
    } else {
        sendError('Error creating operation type', 500, $conn->error);
    }
}

function updateClient($conn, $input) {
    if (empty($input['id']) || empty($input['clientName'])) {
        sendError('Operation type ID and name are required', 400);
    }

    $id = intval($input['id']);
    $clientName = $conn->real_escape_string($input['clientName']);
    $isActive = isset($input['isActive']) && $input['isActive'] ? 1 : 0;

    $sql = "UPDATE client_master 
            SET clientName = '$clientName', isActive = $isActive
            WHERE id = $id";

    if ($conn->query($sql)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'message' => 'client updated successfully!',
                'statusCode' => 200,
                'outVal' => 1,
                'data' => [
                    'id' => $id,
                    'clientName' => $clientName,
                    'isActive' => (bool)$isActive
                ]
            ]);
        } else {
            sendError('client not found', 404);
        }
    } else {
        sendError('Error updating client', 500, $conn->error);
    }
}

function deleteClient($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (empty($id)) {
        sendError('client ID is required', 400);
    }

    $sql = "delete from client_master WHERE id = $id";

    if ($conn->query($sql)) {
        if ($conn->affected_rows > 0) {
            echo json_encode([
                'message' => 'client deleted successfully!',
                'statusCode' => 200,
                'outVal' => 1,
                'data' => null
            ]);
        } else {
            sendError('client not found', 404);
        }
    } else {
        sendError('Error deleting client', 500, $conn->error);
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