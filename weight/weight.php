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

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

$id = null;
if (isset($path_parts[count($path_parts) - 1]) && is_numeric($path_parts[count($path_parts) - 1])) {
    $id = intval($path_parts[count($path_parts) - 1]);
}

switch ($method) {
    case 'GET':
        getAllWeight($conn);
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

function getAllWeight($conn) {
    $sql = "SELECT 
                w.id,
                w.name
            FROM weight_type w
            ORDER BY w.id DESC";

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
        $orders[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $orders]);
}

?>
