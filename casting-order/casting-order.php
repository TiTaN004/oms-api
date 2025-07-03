<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config.php'; // Use the mysqli connection
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../notification/notification-service.php';

$serviceAccountPath = '../push-notification-test-fd696-b3ddb2ece7a0.json';
$notificationService = new FirebaseNotificationService($serviceAccountPath, $conn);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

$id = null;
if (isset($path_parts[count($path_parts) - 1]) && is_numeric($path_parts[count($path_parts) - 1])) {
    $id = intval($path_parts[count($path_parts) - 1]);
}

switch ($method) {
    case 'GET':
        $id ? getCastingOrder($conn, $id) : getAllCastingOrders($conn);
        break;

    case 'POST':
        createCastingOrder($conn);
        break;

    case 'PUT':
        $id ? updateCastingOrder($conn, $id) : http_response_code(400) && print(json_encode(['error' => 'ID is required for update']));
        break;

    case 'DELETE':
        $id ? deleteCastingOrder($conn, $id) : http_response_code(400) && print(json_encode(['error' => 'ID is required for delete']));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

// function getAllCastingOrders($conn) {
//     $sql = "SELECT 
//                 co.CastingOrderId as id,
//                 cm.client_name as client,
//                 u.fullName as user,
//                 p.product_name as product,
//                 co.quantity as qty,
//                 co.size,
//                 co.status,
//                 DATE(co.order_date) as orderDate,
//                 co.fClientID,
//                 co.fAssignUserID,
//                 co.fProductID,
//                 co.fOperationID
//             FROM casting_order co
//             LEFT JOIN client_master cm ON co.fClientID = cm.id
//             LEFT JOIN user u ON co.fAssignUserID = u.userID
//             LEFT JOIN product p ON co.fProductID = p.id
//             WHERE cm.is_active = 1 AND u.isActive = 1 AND p.is_active = 1
//             ORDER BY co.CastingOrderId DESC";

//     $result = $conn->query($sql);
//     $orders = [];

//     while ($row = $result->fetch_assoc()) {
//         $row['status'] = strtolower($row['status']) === 'processing' ? 'pending' : strtolower($row['status']);
//         $orders[] = $row;
//     }

//     echo json_encode(['success' => true, 'data' => $orders]);
// }

function getAllCastingOrders($conn) {
    $sql = "SELECT 
                co.CastingOrderId as id,
                cm.clientName as client,
                u.fullName as user,
                p.product_name as product,
                co.quantity as qty,
                co.size,
                co.status,
                DATE(co.order_date) as orderDate,
                co.fClientID,
                co.fAssignUserID,
                co.fProductID,
                co.fOperationID
            FROM casting_order co
            LEFT JOIN client_master cm ON co.fClientID = cm.id
            LEFT JOIN user u ON co.fAssignUserID = u.userID
            LEFT JOIN product p ON co.fProductID = p.id
            WHERE u.isActive = 1 AND p.is_active = 1
            ORDER BY co.CastingOrderId DESC";

    $result = $conn->query($sql);

    // Check for query error
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Query failed: ' . $conn->error
        ]);
        return;
    }

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $row['status'] = strtolower($row['status']) === 'processing' ? 'pending' : strtolower($row['status']);
        $orders[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $orders]);
}

function getCastingOrder($conn, $id) {
    $stmt = $conn->prepare("SELECT 
                co.CastingOrderId as id,
                cm.clientName as client,
                u.fullName as user,
                p.product_name as product,
                co.quantity as qty,
                co.size,
                co.status,
                DATE(co.order_date) as orderDate,
                co.fClientID,
                co.fAssignUserID,
                co.fProductID,
                co.fOperationID
            FROM casting_order co
            LEFT JOIN client_master cm ON co.fClientID = cm.id
            LEFT JOIN user u ON co.fAssignUserID = u.userID
            LEFT JOIN product p ON co.fProductID = p.id
            WHERE co.CastingOrderId = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if ($order) {
        $order['status'] = strtolower($order['status']) === 'processing' ? 'pending' : strtolower($order['status']);
        echo json_encode(['success' => true, 'data' => $order]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
    }
}

// function createCastingOrder($conn) {
//     global $notificationService;

//     $input = json_decode(file_get_contents('php://input'), true);
//     $required_fields = ['client_id', 'user_id', 'product_id', 'qty', 'size'];
//     foreach ($required_fields as $field) {
//         if (empty($input[$field])) {
//             http_response_code(400);
//             echo json_encode(['error' => "Field '$field' is required", 'message' => "Field '$field' is required"]);
//             return;
//         }
//     }

//     $status = $input['status'] ?? 'pending';
//     $status = $status === 'pending' ? 'Processing' : ($status === 'completed' ? 'Completed' : $status);
//     $operation_id = $input['operation_id'] ?? getDefaultOperationType($conn);
//     $orderDate = !empty($input['order_date']) ? $input['order_date'] : date('Y-m-d H:i:s');
//     $stmt = $conn->prepare("INSERT INTO casting_order (fClientID, fProductID, fAssignUserID, fOperationID, quantity, size, order_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
//     $stmt->bind_param(
//         'iiiissss',
//         $input['client_id'],
//         $input['product_id'],
//         $input['user_id'],
//         $operation_id,
//         $input['qty'],
//         $input['size'],
//         $orderDate,
//         $status
//     );
//     $stmt->execute();
    
//     // if($stmt->affected_rows > 1){
//     //     //fetch last inserted user id
//     //     $id = $conn->insert_id;
//     //     $sql = "select fAssignUserID from casting_order where CastingOrderId = $id";
//     //     $result = $conn->query($sql);
//     //     $userID = $result->fetch_assoc()['fAssignUserID'];

//     //      $result = $notificationService->sendNewOrderNotification(
//     //         $userID,
//     //         $id,
//     //         [
//     //             'order_id' => $id
//     //         ]
//     //     );
//     // }

//     echo json_encode([
//         'message' => 'Order created successfully',
//         'success' => true,
//         'statusCode' => 200,
//         'outVal' => 1,
//         'notification' => isset($result) ? $result : null,
//         'data' => [
//             'client_id' => $input['client_id'],
//             'user_id' => $input['user_id'],
//             'product_id' => $input['product_id'],
//             'qty' => $input['qty'],
//             'size' => $input['size'],
//             'status' => $status,
//             'operation_id' => $operation_id
//         ],
//         'id' => $conn->insert_id
//     ]);
// }

function createCastingOrder($conn) {
    global $notificationService;

    $input = json_decode(file_get_contents('php://input'), true);
    $required_fields = ['client_id', 'user_id', 'product_id', 'qty', 'size'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required", 'message' => "Field '$field' is required"]);
            return;
        }
    }

    $status = $input['status'] ?? 'pending';
    $status = $status === 'pending' ? 'Processing' : ($status === 'completed' ? 'Completed' : $status);
    $operation_id = $input['operation_id'] ?? getDefaultOperationType($conn);
    $orderDate = !empty($input['order_date']) ? $input['order_date'] : date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO casting_order (fClientID, fProductID, fAssignUserID, fOperationID, quantity, size, order_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'iiiissss',
        $input['client_id'],
        $input['product_id'],
        $input['user_id'],
        $operation_id,
        $input['qty'],
        $input['size'],
        $orderDate,
        $status
    );
    $stmt->execute();
    
    // Fixed condition: Check if exactly 1 row was affected (successful single insert)
    if($stmt->affected_rows == 1) {
        // Fetch last inserted order id
        $id = $conn->insert_id;
        $sql = "SELECT fAssignUserID FROM casting_order WHERE CastingOrderId = ?";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $result_query = $stmt2->get_result();
        $userID = $result_query->fetch_assoc()['fAssignUserID'];

        $result = $notificationService->sendNewOrderNotification(
            $userID,
            $id,
            [
                'order_id' => $id
            ]
        );
    }

    echo json_encode([
        'message' => 'Order created successfully',
        'success' => true,
        'statusCode' => 200,
        'outVal' => 1,
        'notification' => isset($result) ? $result : null,
        'data' => [
            'client_id' => $input['client_id'],
            'user_id' => $input['user_id'],
            'product_id' => $input['product_id'],
            'qty' => $input['qty'],
            'size' => $input['size'],
            'status' => $status,
            'operation_id' => $operation_id
        ],
        'id' => $conn->insert_id
    ]);
}

function updateCastingOrder($conn, $id) {
    $input = json_decode(file_get_contents('php://input'), true);

    $check = $conn->prepare("SELECT CastingOrderId FROM casting_order WHERE CastingOrderId = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        return;
    }

    $status = $input['status'] ?? 'pending';
    $status = $status === 'pending' ? 'Processing' : ($status === 'completed' ? 'Completed' : $status);

    $stmt = $conn->prepare("UPDATE casting_order SET fClientID=?, fProductID=?, fAssignUserID=?, quantity=?, size=?, status=? WHERE CastingOrderId=?");
    $stmt->bind_param(
        'iiiissi',
        $input['client_id'],
        $input['product_id'],
        $input['user_id'],
        $input['qty'],
        $input['size'],
        $status,
        $id
    );
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        http_response_code(400);
        echo json_encode(['statusCode' => 200,'outVal' => 1 ,'message' => 'No changes made or order not found']);
        return;
    }

    $data = [
        'id' => $id,
        'client_id' => $input['client_id'],
        'user_id' => $input['user_id'],
        'product_id' => $input['product_id'],
        'qty' => $input['qty'],
        'size' => $input['size'],
        'status' => $status
    ];

    echo json_encode(['success' => true, 'message' => 'Order updated successfully', 'statusCode' => 200, 'outVal' => 1,'data' => $data]);
}

function deleteCastingOrder($conn, $id) {
    $check = $conn->prepare("SELECT CastingOrderId FROM casting_order WHERE CastingOrderId = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM casting_order WHERE CastingOrderId = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Order deleted successfully', 'statusCode' => 200, 'outVal' => 1]);
}

function getDefaultOperationType($conn) {
    $result = $conn->query("SELECT id FROM operation_type WHERE isActive = 1 LIMIT 1");
    $row = $result->fetch_assoc();
    return $row ? (int)$row['id'] : 1;
}
?>
