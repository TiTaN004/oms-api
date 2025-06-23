<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config.php';
date_default_timezone_set('Asia/Kolkata');

$method = $_SERVER['REQUEST_METHOD'];
$path_info = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$segments = explode('/', $path_info);

switch ($method) {
    case 'GET':
        if (empty($path_info)) {
            getAllOrders();
        } else {
            getOrderById($segments[0]);
        }
        break;
    
    case 'POST':
        createOrder();
        break;
    
    case 'PUT':
        if (!empty($path_info)) {
            updateOrder($segments[0]);
        } else {
            sendResponse('Order ID is required for update', 400, 0);
        }
        break;
    
    case 'DELETE':
        if (!empty($path_info)) {
            deleteOrder($segments[0]);
        } else {
            sendResponse('Order ID is required for delete', 400, 0);
        }
        break;
    
    default:
        sendResponse('Method not allowed', 405, 0);
        break;
}

function getAllOrders() {
    global $conn;
    
    $query = "SELECT 
        ROW_NUMBER() OVER (ORDER BY o.orderID) as rowNumber,
        o.orderID,
        o.orderNo,
        DATE_FORMAT(o.orderOn, '%d-%b-%Y') as orderOn,
        o.fClientID,
        cm.clientName,
        o.fProductID,
        p.product_name as productName,
        o.weight,
        o.WeightTypeID as weightTypeID,
        CONCAT(o.weight, ' ', wt.name) as weightTypeText,
        o.productWeight,
        CONCAT(o.productWeight, ' ', pwt.name) as productWeightText,
        o.productQty,
        o.pricePerQty,
        o.totalPrice,
        o.remark as orderDetails,
        CASE 
            WHEN o.status = 'Processing' THEN 0
            WHEN o.status = 'Completed' THEN 1
            ELSE 0
        END as status,
        o.fOperationID,
        ot.operationName,
        o.fAssignUserID as fUserAssignID,
        u.fullName as assignUser,
        1 as isDelete,
        o.productWeightTypeID,
        o.totalWeight
    FROM `order` o
    LEFT JOIN client_master cm ON o.fClientID = cm.id
    LEFT JOIN product p ON o.fProductID = p.id
    LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
    LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
    LEFT JOIN operation_type ot ON o.fOperationID = ot.id
    LEFT JOIN user u ON o.fAssignUserID = u.userID
    ORDER BY o.orderID DESC";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        sendResponse('Error fetching orders: ' . mysqli_error($conn), 500, 0);
    }
    
    $orders = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
    
    sendResponse('Records Get Successfully!', 200, 1, $orders);
}

function getOrderById($orderId) {
    global $conn;
    
    $orderId = mysqli_real_escape_string($conn, $orderId);
    
    $query = "SELECT 
        ROW_NUMBER() OVER (ORDER BY o.orderID) as rowNumber,
        o.orderID,
        o.orderNo,
        DATE_FORMAT(o.orderOn, '%d-%b-%Y') as orderOn,
        o.fClientID,
        cm.clientName,
        o.fProductID,
        p.product_name as productName,
        o.weight,
        o.WeightTypeID as weightTypeID,
        CONCAT(o.weight, ' ', wt.name) as weightTypeText,
        o.productWeight,
        CONCAT(o.productWeight, ' ', pwt.name) as productWeightText,
        o.productQty,
        o.pricePerQty,
        o.totalPrice,
        o.remark as orderDetails,
        CASE 
            WHEN o.status = 'Processing' THEN 0
            WHEN o.status = 'Completed' THEN 1
            ELSE 0
        END as status,
        o.fOperationID,
        ot.operationName,
        o.fAssignUserID as fUserAssignID,
        u.fullName as assignUser,
        1 as isDelete,
        o.productWeightTypeID,
        o.totalWeight
    FROM `order` o
    LEFT JOIN client_master cm ON o.fClientID = cm.id
    LEFT JOIN product p ON o.fProductID = p.id
    LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
    LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
    LEFT JOIN operation_type ot ON o.fOperationID = ot.id
    LEFT JOIN user u ON o.fAssignUserID = u.userID
    WHERE o.orderID = '$orderId'";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        sendResponse('Error fetching order: ' . mysqli_error($conn), 500, 0);
    }
    
    $order = mysqli_fetch_assoc($result);
    
    if (!$order) {
        sendResponse('Order not found', 404, 0);
    }
    
    sendResponse('Record Get Successfully!', 200, 1, [$order]);
}

function createOrder() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse('Invalid JSON data', 400, 0);
    }
    
    $required_fields = ['fClientID', 'fProductID', 'fOperationID', 'fAssignUserID', 'productQty', 'weight', 'WeightTypeID', 'productWeight', 'productWeightTypeID'];
    $missing_fields = validateFields($input, $required_fields);
    
    if (!empty($missing_fields)) {
        sendResponse('Missing required fields: ' . implode(', ', $missing_fields), 400, 0);
    }
    
    // Generate order number
    $orderNo = generateOrderNumber();
    
    // Calculate total price and weight
    $totalPrice = floatval($input['productQty']) * floatval($input['pricePerQty'] ?? 0);
    $totalWeight = floatval($input['weight']) + floatval($input['productWeight']);
    
    $fClientID = mysqli_real_escape_string($conn, $input['fClientID']);
    $fProductID = mysqli_real_escape_string($conn, $input['fProductID']);
    $fOperationID = mysqli_real_escape_string($conn, $input['fOperationID']);
    $fAssignUserID = mysqli_real_escape_string($conn, $input['fAssignUserID']);
    $orderOn = isset($input['orderOn']) ? mysqli_real_escape_string($conn, $input['orderOn']) : date('Y-m-d H:i:s');
    $weight = mysqli_real_escape_string($conn, $input['weight']);
    $WeightTypeID = mysqli_real_escape_string($conn, $input['WeightTypeID']);
    $productWeight = mysqli_real_escape_string($conn, $input['productWeight']);
    $productWeightTypeID = mysqli_real_escape_string($conn, $input['productWeightTypeID']);
    $productQty = mysqli_real_escape_string($conn, $input['productQty']);
    $pricePerQty = mysqli_real_escape_string($conn, $input['pricePerQty'] ?? 0);
    $remark = mysqli_real_escape_string($conn, $input['description'] ?? '');
    
    $query = "INSERT INTO `order` (
        orderNo, fClientID, fProductID, fOperationID, fAssignUserID, 
        orderOn, weight, WeightTypeID, productWeight, productWeightTypeID, 
        productQty, pricePerQty, totalPrice, totalWeight, remark, status
    ) VALUES (
        '$orderNo', '$fClientID', '$fProductID', '$fOperationID', '$fAssignUserID',
        '$orderOn', '$weight', '$WeightTypeID', '$productWeight', '$productWeightTypeID',
        '$productQty', '$pricePerQty', '$totalPrice', '$totalWeight', '$remark', 'Processing'
    )";
    
    if (mysqli_query($conn, $query)) {
        $orderId = mysqli_insert_id($conn);
        sendResponse('Order created successfully!', 200, 1, ['orderID' => $orderId]);
    } else {
        sendResponse('Error creating order: ' . mysqli_error($conn), 500, 0);
    }
}

function updateOrder($orderId) {
    global $conn;
    
    $orderId = mysqli_real_escape_string($conn, $orderId);
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse('Invalid JSON data', 400, 0);
    }
    
    // Check if order exists
    $check_query = "SELECT orderID FROM `order` WHERE orderID = '$orderId'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 0) {
        sendResponse('Order not found', 404, 0);
    }
    
    $update_fields = [];
    
    if (isset($input['fClientID'])) {
        $fClientID = mysqli_real_escape_string($conn, $input['fClientID']);
        $update_fields[] = "fClientID = '$fClientID'";
    }
    
    if (isset($input['fProductID'])) {
        $fProductID = mysqli_real_escape_string($conn, $input['fProductID']);
        $update_fields[] = "fProductID = '$fProductID'";
    }
    
    if (isset($input['fOperationID'])) {
        $fOperationID = mysqli_real_escape_string($conn, $input['fOperationID']);
        $update_fields[] = "fOperationID = '$fOperationID'";
    }
    
    if (isset($input['fAssignUserID'])) {
        $fAssignUserID = mysqli_real_escape_string($conn, $input['fAssignUserID']);
        $update_fields[] = "fAssignUserID = '$fAssignUserID'";
        // Reset status to Processing when user is reassigned
        $update_fields[] = "status = 'Processing'";
    }
    
    if (isset($input['weight'])) {
        $weight = mysqli_real_escape_string($conn, $input['weight']);
        $update_fields[] = "weight = '$weight'";
    }
    
    if (isset($input['WeightTypeID'])) {
        $WeightTypeID = mysqli_real_escape_string($conn, $input['WeightTypeID']);
        $update_fields[] = "WeightTypeID = '$WeightTypeID'";
    }
    
    if (isset($input['productWeight'])) {
        $productWeight = mysqli_real_escape_string($conn, $input['productWeight']);
        $update_fields[] = "productWeight = '$productWeight'";
    }
    
    if (isset($input['productWeightTypeID'])) {
        $productWeightTypeID = mysqli_real_escape_string($conn, $input['productWeightTypeID']);
        $update_fields[] = "productWeightTypeID = '$productWeightTypeID'";
    }
    
    if (isset($input['productQty'])) {
        $productQty = mysqli_real_escape_string($conn, $input['productQty']);
        $update_fields[] = "productQty = '$productQty'";
    }
    
    if (isset($input['pricePerQty'])) {
        $pricePerQty = mysqli_real_escape_string($conn, $input['pricePerQty']);
        $update_fields[] = "pricePerQty = '$pricePerQty'";
    }
    
    if (isset($input['description'])) {
        $remark = mysqli_real_escape_string($conn, $input['description']);
        $update_fields[] = "remark = '$remark'";
    }
    
    if (isset($input['status'])) {
        $status = $input['status'] == 1 ? 'Completed' : 'Processing';
        $update_fields[] = "status = '$status'";
    }
    
    // Recalculate total price and weight if needed
    if (isset($input['productQty']) || isset($input['pricePerQty'])) {
        $current_query = "SELECT productQty, pricePerQty FROM `order` WHERE orderID = '$orderId'";
        $current_result = mysqli_query($conn, $current_query);
        $current_data = mysqli_fetch_assoc($current_result);
        
        $qty = isset($input['productQty']) ? $input['productQty'] : $current_data['productQty'];
        $price = isset($input['pricePerQty']) ? $input['pricePerQty'] : $current_data['pricePerQty'];
        $totalPrice = floatval($qty) * floatval($price);
        
        $update_fields[] = "totalPrice = '$totalPrice'";
    }
    
    if (isset($input['weight']) || isset($input['productWeight'])) {
        $current_query = "SELECT weight, productWeight FROM `order` WHERE orderID = '$orderId'";
        $current_result = mysqli_query($conn, $current_query);
        $current_data = mysqli_fetch_assoc($current_result);
        
        $weight = isset($input['weight']) ? $input['weight'] : $current_data['weight'];
        $productWeight = isset($input['productWeight']) ? $input['productWeight'] : $current_data['productWeight'];
        $totalWeight = floatval($weight) + floatval($productWeight);
        
        $update_fields[] = "totalWeight = '$totalWeight'";
    }
    
    if (empty($update_fields)) {
        sendResponse('No fields to update', 400, 0);
    }
    
    $query = "UPDATE `order` SET " . implode(', ', $update_fields) . " WHERE orderID = '$orderId'";
    
    if (mysqli_query($conn, $query)) {
        sendResponse('Order updated successfully!', 200, 1);
    } else {
        sendResponse('Error updating order: ' . mysqli_error($conn), 500, 0);
    }
}

function deleteOrder($orderId) {
    global $conn;
    
    $orderId = mysqli_real_escape_string($conn, $orderId);
    
    // Check if order exists
    $check_query = "SELECT orderID FROM `order` WHERE orderID = '$orderId'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 0) {
        sendResponse('Order not found', 404, 0);
    }
    
    $query = "DELETE FROM `order` WHERE orderID = '$orderId'";
    
    if (mysqli_query($conn, $query)) {
        sendResponse('Order deleted successfully!', 200, 1);
    } else {
        sendResponse('Error deleting order: ' . mysqli_error($conn), 500, 0);
    }
}

function generateOrderNumber() {
    global $conn;
    
    $query = "SELECT MAX(CAST(SUBSTRING(orderNo, 4) AS UNSIGNED)) as max_num FROM `order` WHERE orderNo LIKE 'ORD%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    $next_num = ($row['max_num'] ?? 0) + 1;
    return 'ORD' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

function sendResponse($message, $statusCode = 200, $outVal = 1, $data = null) {
    $response = [
        'message' => $message,
        'statusCode' => $statusCode,
        'outVal' => $outVal
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}
// Function to validate required fields
function validateFields($data, $required_fields) {
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing_fields[] = $field;
        }
    }
    return $missing_fields;
}
?>