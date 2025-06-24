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
        } elseif ($segments[0] === 'history' && isset($segments[1])) {
            getOrderHistory($segments[1]);
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

function getAllOrders()
{
    global $conn;

    // Only get active orders (latest version of each order)
    $query = "SELECT 
        ROW_NUMBER() OVER (ORDER BY o.orderID) as rowNumber,
        o.orderID,
        o.parentOrderID,
        COALESCE(o.parentOrderID, o.orderID) as originalOrderID,
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
        o.fOperationID,
        ot.operationName,
        o.fAssignUserID as fUserAssignID,
        u.fullName as assignUser,
        1 as isDelete,
        o.productWeightTypeID,
        o.totalWeight,
        o.isActive,
        o.status,
        DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn,
        -- Count how many times this order has been reassigned
        (SELECT COUNT(*) FROM `order` o2 
         WHERE COALESCE(o2.parentOrderID, o2.orderID) = COALESCE(o.parentOrderID, o.orderID)) as assignmentCount
    FROM `order` o
    LEFT JOIN client_master cm ON o.fClientID = cm.id
    LEFT JOIN product p ON o.fProductID = p.id
    LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
    LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
    LEFT JOIN operation_type ot ON o.fOperationID = ot.id
    LEFT JOIN user u ON o.fAssignUserID = u.userID
    WHERE o.isActive = 1
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

function getOrderById($orderId)
{
    global $conn;

    $orderId = mysqli_real_escape_string($conn, $orderId);

    // Get the current active order
    $query = "SELECT 
        ROW_NUMBER() OVER (ORDER BY o.orderID) as rowNumber,
        o.orderID,
        o.parentOrderID,
        COALESCE(o.parentOrderID, o.orderID) as originalOrderID,
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
        o.description,
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
        o.totalWeight,
        o.isActive,
        DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn
    FROM `order` o
    LEFT JOIN client_master cm ON o.fClientID = cm.id
    LEFT JOIN product p ON o.fProductID = p.id
    LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
    LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
    LEFT JOIN operation_type ot ON o.fOperationID = ot.id
    LEFT JOIN user u ON o.fAssignUserID = u.userID
    WHERE o.orderID = '$orderId' AND o.isActive = 1";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        sendResponse('Error fetching order: ' . mysqli_error($conn), 500, 0);
    }

    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        sendResponse('Order not found', 404, 0);
    }

    sendResponse('Record Get Successfully!', 200, 1, $order);
}

function getOrderHistory($orderId)
{
    global $conn;

    $orderId = mysqli_real_escape_string($conn, $orderId);

    // Get all versions of this order (including inactive ones)
    $query = "SELECT 
        o.orderID,
        o.parentOrderID,
        COALESCE(o.parentOrderID, o.orderID) as originalOrderID,
        o.orderNo,
        DATE_FORMAT(o.orderOn, '%d-%b-%Y') as orderOn,
        o.fClientID,
        cm.clientName,
        o.fAssignUserID as fUserAssignID,
        u.fullName as assignUser,
        o.status,
        o.isActive,
        DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn,
        DATE_FORMAT(o.updatedAt, '%d-%b-%Y %H:%i') as updatedOn,
        CASE 
            WHEN o.isActive = 1 THEN 'Current'
            ELSE 'Previous'
        END as assignmentStatus
    FROM `order` o
    LEFT JOIN client_master cm ON o.fClientID = cm.id
    LEFT JOIN user u ON o.fAssignUserID = u.userID
    WHERE (o.orderID = '$orderId' OR COALESCE(o.parentOrderID, o.orderID) = 
           (SELECT COALESCE(parentOrderID, orderID) FROM `order` WHERE orderID = '$orderId' LIMIT 1))
    ORDER BY o.createdAt ASC";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        sendResponse('Error fetching order history: ' . mysqli_error($conn), 500, 0);
    }

    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }

    if (empty($history)) {
        sendResponse('Order history not found', 404, 0);
    }

    sendResponse('Order History Retrieved Successfully!', 200, 1, $history);
}

function updateOrder($orderId)
{
    global $conn;

    $orderId = mysqli_real_escape_string($conn, $orderId);
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse('Invalid JSON data', 400, 0);
    }

    // Check if order exists and is active
    $check_query = "SELECT * FROM `order` WHERE orderID = '$orderId' AND isActive = 1";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) == 0) {
        sendResponse('Order not found or inactive', 404, 0);
    }

    $current_order = mysqli_fetch_assoc($check_result);

    // If user is being reassigned, create new order entry with proper parent tracking
    if (isset($input['fAssignUserID']) && $input['fAssignUserID'] != $current_order['fAssignUserID']) {
        
        // Mark current order as inactive and set updated timestamp
        $deactivate_query = "UPDATE `order` SET 
            isActive = 0, 
            updatedAt = NOW(),
            remark = CONCAT(COALESCE(remark, ''), ' [Reassigned on " . date('Y-m-d H:i:s') . "]')
            WHERE orderID = '$orderId'";
        
        if (!mysqli_query($conn, $deactivate_query)) {
            sendResponse('Error deactivating current order: ' . mysqli_error($conn), 500, 0);
        }

        // Determine the original order ID (parent chain)
        $originalOrderID = $current_order['parentOrderID'] ?? $current_order['orderID'];
        $new_fAssignUserID = mysqli_real_escape_string($conn, $input['fAssignUserID']);
        
        // Create new order with proper parent tracking
        $insert_query = "INSERT INTO `order` (
            parentOrderID, orderNo, fClientID, fProductID, fOperationID, fAssignUserID, 
            orderOn, weight, WeightTypeID, productWeight, productWeightTypeID, 
            productQty, pricePerQty, totalPrice, totalWeight, remark, description, 
            status, isActive, createdAt
        ) VALUES (
            '$originalOrderID', '{$current_order['orderNo']}', '{$current_order['fClientID']}', 
            '{$current_order['fProductID']}', '{$current_order['fOperationID']}', '$new_fAssignUserID',
            '{$current_order['orderOn']}', '{$current_order['weight']}', '{$current_order['WeightTypeID']}', 
            '{$current_order['productWeight']}', '{$current_order['productWeightTypeID']}',
            '{$current_order['productQty']}', '{$current_order['pricePerQty']}', '{$current_order['totalPrice']}', 
            '{$current_order['totalWeight']}', 
            '" . mysqli_real_escape_string($conn, ($input['remark'] ?? '') . ' [Reassigned from previous user]') . "',
            '" . mysqli_real_escape_string($conn, $input['description'] ?? $current_order['description']) . "', 
            'Processing', 1, NOW()
        )";

        if (mysqli_query($conn, $insert_query)) {
            $newOrderId = mysqli_insert_id($conn);
            
            // Get user names for response
            $user_query = "SELECT 
                (SELECT fullName FROM user WHERE userID = '{$current_order['fAssignUserID']}') as previousUser,
                (SELECT fullName FROM user WHERE userID = '$new_fAssignUserID') as newUser";
            $user_result = mysqli_query($conn, $user_query);
            $users = mysqli_fetch_assoc($user_result);
            
            sendResponse('Order reassigned successfully!', 200, 1, [
                'newOrderID' => $newOrderId,
                'originalOrderID' => $originalOrderID,
                'previousUser' => $users['previousUser'],
                'newUser' => $users['newUser'],
                'reassignedAt' => date('d-M-Y H:i')
            ]);
        } else {
            sendResponse('Error reassigning order: ' . mysqli_error($conn), 500, 0);
        }
        return;
    }

    // Regular update for other fields (same as before)
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
        $description = mysqli_real_escape_string($conn, $input['description']);
        $update_fields[] = "description = '$description'";
    }

    if (isset($input['status'])) {
        $status = (int)$input['status'] === 1 ? 'Completed' : 'Processing';
        $update_fields[] = "status = '$status'";
    }

    // Recalculate totals if needed
    if (isset($input['productQty']) || isset($input['pricePerQty'])) {
        $qty = isset($input['productQty']) ? $input['productQty'] : $current_order['productQty'];
        $price = isset($input['pricePerQty']) ? $input['pricePerQty'] : $current_order['pricePerQty'];
        $totalPrice = floatval($qty) * floatval($price);
        $update_fields[] = "totalPrice = '$totalPrice'";
    }

    if (isset($input['weight']) || isset($input['productWeight'])) {
        $weight = isset($input['weight']) ? $input['weight'] : $current_order['weight'];
        $productWeight = isset($input['productWeight']) ? $input['productWeight'] : $current_order['productWeight'];
        $totalWeight = floatval($weight) + floatval($productWeight);
        $update_fields[] = "totalWeight = '$totalWeight'";
    }

    if (empty($update_fields)) {
        sendResponse('No fields to update', 400, 0);
    }

    $update_fields[] = "updatedAt = NOW()";
    $query = "UPDATE `order` SET " . implode(', ', $update_fields) . " WHERE orderID = '$orderId' AND isActive = 1";

    if (mysqli_query($conn, $query)) {
        if (mysqli_affected_rows($conn) > 0) {
            sendResponse('Order updated successfully!', 200, 1);
        } else {
            sendResponse('No changes made to the order', 200, 1);
        }
    } else {
        sendResponse('Error updating order: ' . mysqli_error($conn), 500, 0);
    }
}

function createOrder()
{
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
    $remark = mysqli_real_escape_string($conn, $input['remark'] ?? '');
    $description = mysqli_real_escape_string($conn, $input['description'] ?? '');

    $query = "INSERT INTO `order` (
        orderNo, fClientID, fProductID, fOperationID, fAssignUserID, 
        orderOn, weight, WeightTypeID, productWeight, productWeightTypeID, 
        productQty, pricePerQty, totalPrice, totalWeight, remark, description, 
        status, isActive, createdAt
    ) VALUES (
        '$orderNo', '$fClientID', '$fProductID', '$fOperationID', '$fAssignUserID',
        '$orderOn', '$weight', '$WeightTypeID', '$productWeight', '$productWeightTypeID',
        '$productQty', '$pricePerQty', '$totalPrice', '$totalWeight', '$remark', '$description',
        'Processing', 1, NOW()
    )";

    if (mysqli_query($conn, $query)) {
        $orderId = mysqli_insert_id($conn);
        sendResponse('Order created successfully!', 200, 1, ['orderID' => $orderId]);
    } else {
        sendResponse('Error creating order: ' . mysqli_error($conn), 500, 0);
    }
}

function deleteOrder($orderId)
{
    global $conn;

    $orderId = mysqli_real_escape_string($conn, $orderId);

    // Check if order exists
    $check_query = "SELECT orderID, parentOrderID FROM `order` WHERE orderID = '$orderId'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) == 0) {
        sendResponse('Order not found', 404, 0);
    }

    $order = mysqli_fetch_assoc($check_result);
    $originalOrderID = $order['parentOrderID'] ?? $order['orderID'];

    // Delete all versions of this order (including history)
    $query = "DELETE FROM `order` WHERE orderID = '$orderId' OR 
              COALESCE(parentOrderID, orderID) = '$originalOrderID'";

    if (mysqli_query($conn, $query)) {
        $deletedCount = mysqli_affected_rows($conn);
        sendResponse("Order and its history deleted successfully! ($deletedCount records deleted)", 200, 1);
    } else {
        sendResponse('Error deleting order: ' . mysqli_error($conn), 500, 0);
    }
}

function generateOrderNumber()
{
    global $conn;

    $query = "SELECT MAX(CAST(SUBSTRING(orderNo, 4) AS UNSIGNED)) as max_num FROM `order` WHERE orderNo LIKE 'ORD%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);

    $next_num = ($row['max_num'] ?? 0) + 1;
    return 'ORD' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

function sendResponse($message, $statusCode = 200, $outVal = 1, $data = null)
{
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

function validateFields($data, $required_fields)
{
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing_fields[] = $field;
        }
    }
    return $missing_fields;
}