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
require_once '../notification/notification-service.php';

$serviceAccountPath = '../push-notification-test-fd696-b3ddb2ece7a0.json';
$notificationService = new FirebaseNotificationService($serviceAccountPath, $conn);

date_default_timezone_set('Asia/Kolkata');

$method = $_SERVER['REQUEST_METHOD'];
$path_info = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$segments = explode('/', $path_info);

switch ($method) {
    case 'GET':
        if (empty($path_info)) {
            $userID = $_GET['userID'] ?? null;
            $isAdmin = $_GET['isAdmin'] ?? false;

            getAllOrders($userID, $isAdmin);
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

// function getAllOrders($isAdmin,$userID)
// {
//     global $conn;

//     // $isAdmin = $_GET['isAdmin'];
//     // $userID = $_GET['userID'];

//        $userCondition = "";
//     if (!$isAdmin && !empty($userID)) {
//         $userCondition = "AND o.fAssignUserID = '$userID'";
//     }
//     // Only get active orders (latest version of each order)
//     // $query = "SELECT 
//     //     ROW_NUMBER() OVER (ORDER BY o.orderID) as rowNumber,
//     //     o.orderID,
//     //     o.parentOrderID,
//     //     COALESCE(o.parentOrderID, o.orderID) as originalOrderID,
//     //     o.orderNo,
//     //     DATE_FORMAT(o.orderOn, '%d-%b-%Y') as orderOn,
//     //     o.fClientID,
//     //     cm.clientName,
//     //     o.fProductID,
//     //     p.product_name as productName,
//     //     o.weight,
//     //     o.WeightTypeID as weightTypeID,
//     //     CONCAT(o.weight, ' ', wt.name) as weightTypeText,
//     //     o.productWeight,
//     //     CONCAT(o.productWeight, ' ', pwt.name) as productWeightText,
//     //     o.productQty,
//     //     o.pricePerQty,
//     //     o.totalPrice,
//     //     o.description,
//     //     o.fOperationID,
//     //     ot.operationName,
//     //     o.fAssignUserID as fUserAssignID,
//     //     u.fullName as assignUser,
//     //     1 as isDelete,
//     //     o.productWeightTypeID,
//     //     o.totalWeight,
//     //     o.isActive,
//     //     o.status,
//     //     DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn,
//     //     -- Count how many times this order has been reassigned
//     //     (SELECT COUNT(*) FROM `order` o2 
//     //      WHERE COALESCE(o2.parentOrderID, o2.orderID) = COALESCE(o.parentOrderID, o.orderID)) as assignmentCount
//     // FROM `order` o
//     // LEFT JOIN client_master cm ON o.fClientID = cm.id
//     // LEFT JOIN product p ON o.fProductID = p.id
//     // LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
//     // LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
//     // LEFT JOIN operation_type ot ON o.fOperationID = ot.id
//     // LEFT JOIN user u ON o.fAssignUserID = u.userID
//     // WHERE 
//     // (
//     // o.parentOrderID IS NOT NULL
//     // OR
//     // NOT EXISTS (
//     //     SELECT 1 FROM `order` c WHERE c.parentOrderID = o.orderID
//     // )
//     // )
//     // ORDER BY o.orderID DESC";
//     $query = "SELECT 
//     ROW_NUMBER() OVER (ORDER BY o.orderID DESC) as rowNumber,
//     o.orderID,
//     o.parentOrderID,
//     COALESCE(o.parentOrderID, o.orderID) as originalOrderID,
//     o.orderNo,
//     DATE_FORMAT(o.orderOn, '%d-%b-%Y') as orderOn,
//     o.fClientID,
//     cm.clientName,
//     o.fProductID,
//     p.product_name as productName,
//     o.weight,
//     o.WeightTypeID as weightTypeID,
//     CONCAT(o.weight, ' ', wt.name) as weightTypeText,
//     o.productWeight,
//     CONCAT(o.productWeight, ' ', pwt.name) as productWeightText,
//     o.productQty,
//     o.pricePerQty,
//     o.totalPrice,
//     o.description,
//     o.fOperationID,
//     ot.operationName,
//     o.fAssignUserID as fUserAssignID,
//     u.fullName as assignUser,
//     1 as isDelete,
//     o.productWeightTypeID,
//     o.totalWeight,
//     o.isActive,
//     o.status,
//     DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn,
//     -- Count how many times this order has been reassigned
//     (
//         SELECT COUNT(*) FROM `order` o2 
//         WHERE COALESCE(o2.parentOrderID, o2.orderID) = COALESCE(o.parentOrderID, o.orderID)
//     ) as assignmentCount
// FROM `order` o
// LEFT JOIN client_master cm ON o.fClientID = cm.id
// LEFT JOIN product p ON o.fProductID = p.id
// LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
// LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
// LEFT JOIN operation_type ot ON o.fOperationID = ot.id
// LEFT JOIN user u ON o.fAssignUserID = u.userID
// WHERE o.orderID IN (
//     SELECT MAX(orderID)
//     FROM `order`
//     GROUP BY COALESCE(parentOrderID, orderID)
// )
// $userCondition
// ORDER BY o.orderID DESC;";

//     $result = mysqli_query($conn, $query);

//     if (!$result) {
//         sendResponse('Error fetching orders: ' . mysqli_error($conn), 500, 0);
//     }

//     $orders = [];
//     while ($row = mysqli_fetch_assoc($result)) {
//         $orders[] = $row;
//     }

//     sendResponse('Records Get Successfully!', 200, 1, $orders);
// }

function getAllOrders()
{
    global $conn;

    $userID = $_GET['userID'] ?? null;
    $isAdmin = $_GET['isAdmin'] ?? 'false';
    $isAdmin = $isAdmin === 'true'; // ensure it's boolean

    // Base query
    $query = "SELECT 
    ROW_NUMBER() OVER (ORDER BY o.orderID DESC) as rowNumber,
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
    p.product_image,
    o.description,
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
    (
        SELECT COUNT(*) FROM `order` o2 
        WHERE COALESCE(o2.parentOrderID, o2.orderID) = COALESCE(o.parentOrderID, o.orderID)
    ) as assignmentCount
FROM `order` o
LEFT JOIN client_master cm ON o.fClientID = cm.id
LEFT JOIN product p ON o.fProductID = p.id
LEFT JOIN weight_type wt ON o.WeightTypeID = wt.id
LEFT JOIN weight_type pwt ON o.productWeightTypeID = pwt.id
LEFT JOIN operation_type ot ON o.fOperationID = ot.id
LEFT JOIN user u ON o.fAssignUserID = u.userID
WHERE o.orderID IN (
    SELECT MAX(orderID)
    FROM `order`
    ";

    // 👇 Now inject condition *inside* subquery
    if (!$isAdmin && $userID) {
        $userID = mysqli_real_escape_string($conn, $userID);
        $query .= "WHERE fAssignUserID = '$userID' ";
    }

    $query .= "GROUP BY COALESCE(parentOrderID, orderID)
)
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
        op.operationName as operationType,
        o.remark,
        o.description,
        o.status,
        o.isActive,
        o.totalPrice as totalPrice,
        CONCAT(o.weight, ' ', wt2.name) as totalWeight,
        CONCAT(o.productWeight, ' ', wt1.name) as productWeight,
        o.pricePerQty,
        o.productQty, 
        p.product_name as product,
        DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn,
        DATE_FORMAT(o.updatedAt, '%d-%b-%Y %H:%i') as updatedOn,
        CASE 
            WHEN o.isActive = 1 THEN 'Current'
            ELSE 'Previous'
        END as assignmentStatus
    FROM `order` o
    LEFT JOIN client_master cm ON o.fClientID = cm.id
    LEFT JOIN operation_type op ON o.fOperationID = op.id
    LEFT JOIN user u ON o.fAssignUserID = u.userID
    LEFT JOIN weight_type wt2 ON o.WeightTypeID = wt2.id
    LEFT JOIN weight_type wt1 ON o.productWeightTypeID = wt1.id
    LEFT JOIN product p ON o.fProductID = p.id
    WHERE (o.orderID = '$orderId' OR COALESCE(o.parentOrderID, o.orderID) = 
           (SELECT COALESCE(parentOrderID, orderID) FROM `order` WHERE orderID = '$orderId' LIMIT 1))
    ORDER BY o.createdAt ASC";
    // $query = "SELECT 
    //     o.orderID,
    //     o.parentOrderID,
    //     COALESCE(o.parentOrderID, o.orderID) as originalOrderID,
    //     o.orderNo,
    //     DATE_FORMAT(o.orderOn, '%d-%b-%Y') as orderOn,
    //     o.fClientID,
    //     cm.clientName,
    //     o.fAssignUserID as fUserAssignID,
    //     u.fullName as assignUser,
    //     op.operationName as operationType,
    //     o.remark,
    //     o.description,
    //     o.status,
    //     o.isActive,
    //     DATE_FORMAT(o.createdAt, '%d-%b-%Y %H:%i') as assignedOn,
    //     DATE_FORMAT(o.updatedAt, '%d-%b-%Y %H:%i') as updatedOn,
    //     CASE 
    //         WHEN o.isActive = 1 THEN 'Current'
    //         ELSE 'Previous'
    //     END as assignmentStatus
    // FROM `order` o
    // LEFT JOIN client_master cm ON o.fClientID = cm.id
    // LEFT JOIN operation_type op ON o.fOperationID = op.id
    // LEFT JOIN user u ON o.fAssignUserID = u.userID
    // WHERE (o.orderID = '$orderId' OR COALESCE(o.parentOrderID, o.orderID) = 
    //        (SELECT COALESCE(parentOrderID, orderID) FROM `order` WHERE orderID = '$orderId' LIMIT 1))
    // ORDER BY o.createdAt ASC";

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
    global $conn, $notificationService;

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

    // // If user is being reassigned, create new order entry with proper parent tracking
    // if (isset($input['fAssignUserID']) && $input['fAssignUserID'] != $current_order['fAssignUserID']) {

    //     // Mark current order as inactive and set updated timestamp
    //     $deactivate_query = "UPDATE `order` SET 
    //         updatedAt = NOW(),
    //         remark = CONCAT(COALESCE(remark, ''), ' [Reassigned on " . date('Y-m-d H:i:s') . "]')
    //         WHERE orderID = '$orderId'";

    //     if (!mysqli_query($conn, $deactivate_query)) {
    //         sendResponse('Error deactivating current order: ' . mysqli_error($conn), 500, 0);
    //     }

    //     // Determine the original order ID (parent chain)
    //     $originalOrderID = $current_order['parentOrderID'] ?? $current_order['orderID'];
    //     // $new_fAssignUserID = mysqli_real_escape_string($conn, $input['fAssignUserID']);
    //     $new_fAssignUserID = mysqli_real_escape_string($conn, $input['fAssignUserID']);
    //     $new_fOperationID = isset($input['operationType']) ? mysqli_real_escape_string($conn, $input['operationType']) : $current_order['operationType'];


    //     // Create new order with proper parent tracking
    //     // $insert_query = "INSERT INTO `order` (
    //     //     parentOrderID, orderNo, fClientID, fProductID, fOperationID, fAssignUserID, 
    //     //     orderOn, weight, WeightTypeID, productWeight, productWeightTypeID, 
    //     //     productQty, pricePerQty, totalPrice, totalWeight, remark, description, 
    //     //     status, isActive, createdAt
    //     // ) VALUES (
    //     //     '$originalOrderID', '{$current_order['orderNo']}', '{$current_order['fClientID']}', 
    //     //     '{$current_order['fProductID']}', '$new_fOperationID', '$new_fAssignUserID',
    //     //     '{$current_order['orderOn']}', '{$current_order['weight']}', '{$current_order['WeightTypeID']}', 
    //     //     '{$current_order['productWeight']}', '{$current_order['productWeightTypeID']}',
    //     //     '{$current_order['productQty']}', '{$current_order['pricePerQty']}', '{$current_order['totalPrice']}', 
    //     //     '{$current_order['totalWeight']}', 
    //     //     '" . mysqli_real_escape_string($conn, ($input['remark'] ?? '') . ' [Reassigned from previous by admin]') . "',
    //     //     '" . mysqli_real_escape_string($conn, $input['description'] ?? $current_order['description']) . "', 
    //     //     'Processing', 1, NOW()
    //     // )";
    //     $insert_query = "INSERT INTO `order` (
    // parentOrderID, orderNo, fClientID, fProductID, fOperationID, fAssignUserID, 
    // orderOn, weight, WeightTypeID, productWeight, productWeightTypeID, 
    // productQty, pricePerQty, totalPrice, totalWeight, remark, description, 
    // status, isActive, createdAt
    // ) VALUES (
    //     '$originalOrderID', '{$current_order['orderNo']}', '{$current_order['fClientID']}', 
    //     '{$current_order['fProductID']}', '$new_fOperationID', '$new_fAssignUserID',
    //     '{$current_order['orderOn']}', '{$current_order['weight']}', '{$current_order['WeightTypeID']}', 
    //     '{$current_order['productWeight']}', '{$current_order['productWeightTypeID']}',
    //     '{$current_order['productQty']}', '{$current_order['pricePerQty']}', '{$current_order['totalPrice']}', 
    //     '{$current_order['totalWeight']}', 
    //     '" . mysqli_real_escape_string($conn, ($input['remark'] ?? '') . ' [Reassigned from previous by admin]') . "',
    //     '" . mysqli_real_escape_string($conn, $input['description'] ?? $current_order['description']) . "', 
    //     'Processing', 1, NOW()
    // )";

    //     if (mysqli_query($conn, $insert_query)) {
    //         $newOrderId = mysqli_insert_id($conn);

    //         // Get user names for response
    //         $user_query = "SELECT 
    //             (SELECT fullName FROM user WHERE userID = '{$current_order['fAssignUserID']}') as previousUser,
    //             (SELECT fullName FROM user WHERE userID = '$new_fAssignUserID') as newUser";
    //         $user_result = mysqli_query($conn, $user_query);
    //         $users = mysqli_fetch_assoc($user_result);

    //         // ✅ Send notification
    //         $result = $notificationService->sendNewOrderNotification(
    //             $new_fAssignUserID,
    //             $current_order["orderNo"],
    //             [
    //                 // 'client_name' => $current_order["clientName"],
    //                 // 'product_name' => $current_order["productName"],
    //                 // 'total_amount' => number_format($current_order["totalPrice"], 2),
    //                 'order_id' => $newOrderId
    //             ]
    //         );

    //         sendResponse('Order reassigned successfully!', 200, 1, [
    //             'newOrderID' => $newOrderId,
    //             'originalOrderID' => $originalOrderID,
    //             'previousUser' => $users['previousUser'],
    //             'newUser' => $users['newUser'],
    //             'reassignedAt' => date('d-M-Y H:i'),
    //             'notification' => $result['message']
    //         ]);
    //     } else {
    //         sendResponse('Error reassigning order: ' . mysqli_error($conn), 500, 0);
    //     }
    //     return;
    // }

    // If user is being reassigned, handle both original order update AND create new reassigned order
if (isset($input['fAssignUserID']) && $input['fAssignUserID'] != $current_order['fAssignUserID']) {

    // ✅ STEP 1: UPDATE ORIGINAL ORDER with all requested changes (except user assignment)
    $update_original_fields = [];
    $update_original_fields[] = "updatedAt = NOW()";
    $update_original_fields[] = "remark = CONCAT(COALESCE(remark, ''), ' [Reassigned on " . date('Y-m-d H:i:s') . "]')";
    
    // Apply all field updates to original order
    if (isset($input['fClientID'])) {
        $fClientID = mysqli_real_escape_string($conn, $input['fClientID']);
        $update_original_fields[] = "fClientID = '$fClientID'";
    }
    if (isset($input['fProductID'])) {
        $fProductID = mysqli_real_escape_string($conn, $input['fProductID']);
        $update_original_fields[] = "fProductID = '$fProductID'";
    }
    if (isset($input['weight'])) {
        $weight = mysqli_real_escape_string($conn, $input['weight']);
        $update_original_fields[] = "weight = '$weight'";
    }
    if (isset($input['WeightTypeID'])) {
        $WeightTypeID = mysqli_real_escape_string($conn, $input['WeightTypeID']);
        $update_original_fields[] = "WeightTypeID = '$WeightTypeID'";
    }
    if (isset($input['productWeight'])) {
        $productWeight = mysqli_real_escape_string($conn, $input['productWeight']);
        $update_original_fields[] = "productWeight = '$productWeight'";
    }
    if (isset($input['productWeightTypeID'])) {
        $productWeightTypeID = mysqli_real_escape_string($conn, $input['productWeightTypeID']);
        $update_original_fields[] = "productWeightTypeID = '$productWeightTypeID'";
    }
    if (isset($input['productQty'])) {
        $productQty = mysqli_real_escape_string($conn, $input['productQty']);
        $update_original_fields[] = "productQty = '$productQty'";
    }
    if (isset($input['pricePerQty'])) {
        $pricePerQty = mysqli_real_escape_string($conn, $input['pricePerQty']);
        $update_original_fields[] = "pricePerQty = '$pricePerQty'";
    }
    if (isset($input['description'])) {
        $description = mysqli_real_escape_string($conn, $input['description']);
        $update_original_fields[] = "description = '$description'";
    }
    // ✅ IMPORTANT: Apply status update to ORIGINAL order
    if (isset($input['status'])) {
        $status = (int)$input['status'] === 1 ? 'Completed' : 'Processing';
        $update_original_fields[] = "status = '$status'";
    }
    // ✅ IMPORTANT: Apply operation type update to ORIGINAL order  
    if (isset($input['fOperationID'])) {
        $fOperationID = mysqli_real_escape_string($conn, $input['fOperationID']);
        $update_original_fields[] = "fOperationID = '$fOperationID'";
    }
    
    // Recalculate totals for original order if needed
    if (isset($input['productQty']) || isset($input['pricePerQty'])) {
        $qty = isset($input['productQty']) ? $input['productQty'] : $current_order['productQty'];
        $price = isset($input['pricePerQty']) ? $input['pricePerQty'] : $current_order['pricePerQty'];
        $totalPrice = floatval($qty) * floatval($price);
        $update_original_fields[] = "totalPrice = '$totalPrice'";
    }
    
    if (isset($input['weight']) || isset($input['productWeight'])) {
        $weight = isset($input['weight']) ? $input['weight'] : $current_order['weight'];
        $productWeight = isset($input['productWeight']) ? $input['productWeight'] : $current_order['productWeight'];
        $totalWeight = floatval($weight) + floatval($productWeight);
        $update_original_fields[] = "totalWeight = '$totalWeight'";
    }

    // Execute original order update
    $update_original_query = "UPDATE `order` SET " . implode(', ', $update_original_fields) . " WHERE orderID = '$orderId'";
    
    if (!mysqli_query($conn, $update_original_query)) {
        sendResponse('Error updating original order: ' . mysqli_error($conn), 500, 0);
    }

    // ✅ STEP 2: CREATE NEW REASSIGNED ORDER with NEW operation type and NEW user
    
    // Get UPDATED values from original order (after our update)
    $updated_order_query = "SELECT * FROM `order` WHERE orderID = '$orderId'";
    $updated_order_result = mysqli_query($conn, $updated_order_query);
    $updated_order = mysqli_fetch_assoc($updated_order_result);
    
    // Determine the original order ID (parent chain)
    $originalOrderID = $current_order['parentOrderID'] ?? $current_order['orderID'];
    $new_fAssignUserID = mysqli_real_escape_string($conn, $input['fAssignUserID']);
    
    // ✅ For NEW order: Use the UPDATED operation type from request (for reassignment)
    $new_fOperationID = isset($input['operationType']) ? mysqli_real_escape_string($conn, $input['operationType']) : $updated_order['operationType'];

    // Create new reassigned order with updated values
    $insert_query = "INSERT INTO `order` (
        parentOrderID, orderNo, fClientID, fProductID, fOperationID, fAssignUserID, 
        orderOn, weight, WeightTypeID, productWeight, productWeightTypeID, 
        productQty, pricePerQty, totalPrice, totalWeight, remark, description, 
        status, isActive, createdAt
    ) VALUES (
        '$originalOrderID', 
        '{$updated_order['orderNo']}', 
        '{$updated_order['fClientID']}', 
        '{$updated_order['fProductID']}', 
        '$new_fOperationID', 
        '$new_fAssignUserID',
        '{$updated_order['orderOn']}', 
        '{$updated_order['weight']}', 
        '{$updated_order['WeightTypeID']}', 
        '{$updated_order['productWeight']}', 
        '{$updated_order['productWeightTypeID']}',
        '{$updated_order['productQty']}', 
        '{$updated_order['pricePerQty']}', 
        '{$updated_order['totalPrice']}', 
        '{$updated_order['totalWeight']}', 
        'Reassigned from order {$orderId}',
        '{$updated_order['description']}', 
        'Processing', 
        1, 
        NOW()
    )";

    if (mysqli_query($conn, $insert_query)) {
        $newOrderId = mysqli_insert_id($conn);

        // Get user names and operation names for response
        $info_query = "SELECT 
            (SELECT fullName FROM user WHERE userID = '{$current_order['fAssignUserID']}') as previousUser,
            (SELECT fullName FROM user WHERE userID = '$new_fAssignUserID') as newUser,
            (SELECT operationName FROM operation_type WHERE id = '{$current_order['fOperationID']}') as previousOperation,
            (SELECT operationName FROM operation_type WHERE id = '$new_fOperationID') as newOperation";
        $info_result = mysqli_query($conn, $info_query);
        $info = mysqli_fetch_assoc($info_result);

        // ✅ Send notification to NEW user
        $result = $notificationService->sendNewOrderNotification(
            $new_fAssignUserID,
            $updated_order["orderNo"],
            [
                'order_id' => $newOrderId
            ]
        );

        $updated_order_query = "SELECT 
                o.*,
                c.clientName as clientName,
                p.product_name as productName,
                u.userName as assignUserName,
                op.operationName as operationName,
                wt1.name as weightTypeName,
                wt2.name as productWeightTypeName
                FROM `order` o
                LEFT JOIN client_master c ON o.fClientID = c.id
                LEFT JOIN product p ON o.fProductID = p.id
                LEFT JOIN user u ON o.fAssignUserID = u.userID
                LEFT JOIN operation_type op ON o.fOperationID = op.id
                LEFT JOIN weight_type wt1 ON o.WeightTypeID = wt1.id
                LEFT JOIN weight_type wt2 ON o.productWeightTypeID = wt2.id
                WHERE o.orderID = '$newOrderId' AND o.isActive = 1";
            
             $updated_result = mysqli_query($conn, $updated_order_query);
            
            if (!$updated_result) {
                sendResponse('Error fetching updated order data: ' . mysqli_error($conn), 500, 0);
                return;
            }
            
            $updated_order_raw = mysqli_fetch_assoc($updated_result);
            
            if (!$updated_order_raw) {
                sendResponse('Updated order data not found', 404, 0);
                return;
            }
            // Format the updated order data
            $updated_order_data = [
                'orderID' => $updated_order_raw['orderID'],
                'orderNo' => $updated_order_raw['orderNo'],
                'clientName' => $updated_order_raw['clientName'],
                'productName' => $updated_order_raw['productName'],
                'assignUser' => $updated_order_raw['assignUserName'],
                'operationName' => $updated_order_raw['operationName'],
                'fClientID' => $updated_order_raw['fClientID'],
                'fProductID' => $updated_order_raw['fProductID'],
                'fOperationID' => $updated_order_raw['fOperationID'],
                'fAssignUserID' => $updated_order_raw['fAssignUserID'],
                'orderOn' => $updated_order_raw['orderOn'],
                'weight' => (float)$updated_order_raw['weight'],
                'weightTypeID' => $updated_order_raw['WeightTypeID'],
                'productWeight' => (float)$updated_order_raw['productWeight'],
                'productWeightTypeID' => $updated_order_raw['productWeightTypeID'],
                'productQty' => (int)$updated_order_raw['productQty'],
                'pricePerQty' => (float)$updated_order_raw['pricePerQty'],
                'totalPrice' => (float)$updated_order_raw['totalPrice'],
                'totalWeight' => (float)$updated_order_raw['totalWeight'],
                'remark' => $updated_order_raw['remark'],
                'description' => $updated_order_raw['description'],
                'status' => $updated_order_raw['status'],
                'isActive' => (bool)$updated_order_raw['isActive'],
                'createdAt' => $updated_order_raw['createdAt'],
                'updatedAt' => $updated_order_raw['updatedAt']
            ];


        sendResponse('Order updated and reassigned successfully!', 200, 1, $updated_order_data);
    } else {
        sendResponse('Error creating reassigned order: ' . mysqli_error($conn), 500, 0);
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
             $updated_order_query = "SELECT 
                o.*,
                c.clientName as clientName,
                p.product_name as productName,
                u.userName as assignUserName,
                op.operationName as operationName,
                wt1.name as weightTypeName,
                wt2.name as productWeightTypeName
                FROM `order` o
                LEFT JOIN client_master c ON o.fClientID = c.id
                LEFT JOIN product p ON o.fProductID = p.id
                LEFT JOIN user u ON o.fAssignUserID = u.userID
                LEFT JOIN operation_type op ON o.fOperationID = op.id
                LEFT JOIN weight_type wt1 ON o.WeightTypeID = wt1.id
                LEFT JOIN weight_type wt2 ON o.productWeightTypeID = wt2.id
                WHERE o.orderID = '$orderId' AND o.isActive = 1";
            
             $updated_result = mysqli_query($conn, $updated_order_query);
            
            if (!$updated_result) {
                sendResponse('Error fetching updated order data: ' . mysqli_error($conn), 500, 0);
                return;
            }
            
            $updated_order_raw = mysqli_fetch_assoc($updated_result);
            
            if (!$updated_order_raw) {
                sendResponse('Updated order data not found', 404, 0);
                return;
            }
            // Format the updated order data
            $updated_order_data = [
                'orderID' => $updated_order_raw['orderID'],
                'orderNo' => $updated_order_raw['orderNo'],
                'clientName' => $updated_order_raw['clientName'],
                'productName' => $updated_order_raw['productName'],
                'assignUser' => $updated_order_raw['assignUserName'],
                'operationName' => $updated_order_raw['operationName'],
                'fClientID' => $updated_order_raw['fClientID'],
                'fProductID' => $updated_order_raw['fProductID'],
                'fOperationID' => $updated_order_raw['fOperationID'],
                'fAssignUserID' => $updated_order_raw['fAssignUserID'],
                'orderOn' => $updated_order_raw['orderOn'],
                'weight' => (float)$updated_order_raw['weight'],
                'weightTypeID' => $updated_order_raw['WeightTypeID'],
                'productWeight' => (float)$updated_order_raw['productWeight'],
                'productWeightTypeID' => $updated_order_raw['productWeightTypeID'],
                'productQty' => (int)$updated_order_raw['productQty'],
                'pricePerQty' => (float)$updated_order_raw['pricePerQty'],
                'totalPrice' => (float)$updated_order_raw['totalPrice'],
                'totalWeight' => (float)$updated_order_raw['totalWeight'],
                'remark' => $updated_order_raw['remark'],
                'description' => $updated_order_raw['description'],
                'status' => $updated_order_raw['status'],
                'isActive' => (bool)$updated_order_raw['isActive'],
                'createdAt' => $updated_order_raw['createdAt'],
                'updatedAt' => $updated_order_raw['updatedAt']
            ];

            sendResponse('Order updated successfully!', 200, 1, $updated_order_data);
            // sendResponse('Order updated successfully!', 200, 1,$orderData);
        } else {
            sendResponse('No changes made to the order', 200, 1);
        }
    } else {
        sendResponse('Error updating order: ' . mysqli_error($conn), 500, 0);
    }
}

function createOrder()
{
    global $conn, $notificationService;

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
        $extraQuery = "
            SELECT 
                (SELECT clientName FROM client_master WHERE id = '$fClientID') AS clientName,
                (SELECT product_name FROM product WHERE id = '$fProductID') AS productName,
                (SELECT userName FROM user WHERE userID = '$fAssignUserID') AS assignUserName,
                (SELECT operationName FROM operation_type WHERE id = '$fOperationID') AS operationName
        ";
        $extraResult = mysqli_query($conn, $extraQuery);
        $extraData = mysqli_fetch_assoc($extraResult);


        $clientName = $extraData['clientName'] ?? '';
        $productName = $extraData['productName'] ?? '';
        $assignUserName = $extraData['assignUserName'] ?? '';
        $operationName = $extraData['operationName'] ?? '';

        // ✅ Send notification
        $result = $notificationService->sendNewOrderNotification(
            $fAssignUserID,
            $orderNo,
            [
                'client_name' => $clientName,
                'product_name' => $productName,
                'total_amount' => number_format($totalPrice, 2),
                'order_id' => $orderId
            ]
        );

        // if ($result['success']) {
        //     echo "Notification sent successfully!";
        // } else {
        //     echo "Failed to send notification: " . $result['message'];
        // }

        $orderData = [
            'orderID' => $orderId,
            'orderNo' => $orderNo,
            'clientName' => $clientName,
            'productName' => $productName,
            'assignUser' => $assignUserName,
            'operationName' => $operationName,
            'fClientID' => $fClientID,
            'fProductID' => $fProductID,
            'fOperationID' => $fOperationID,
            'fAssignUserID' => $fAssignUserID,
            'orderOn' => $orderOn,
            'weight' => $weight,
            'weightTypeID' => $WeightTypeID,
            'productWeight' => $productWeight,
            'productWeightTypeID' => $productWeightTypeID,
            'productQty' => (int)$productQty,
            'pricePerQty' => (float)$pricePerQty,
            'totalPrice' => (float)$totalPrice,
            'totalWeight' => (float)$totalWeight,
            'remark' => $remark,
            'description' => $description,
            'status' => 'Processing',
            'isActive' => true,
            'createdAt' => date('Y-m-d H:i:s')
        ];

        sendResponse('Order created successfully!', 200, 1, $orderData);
        // sendResponse('Order created successfully!', 200, 1, ['order' => $orderData, "notification" => $result['message']]);
    } else {
        sendResponse('Error creating order: ' . mysqli_error($conn), 500, 0);
    }
}

// function deleteOrder($orderId)
// {
//     global $conn;

//     $orderId = mysqli_real_escape_string($conn, $orderId);

//     // Check if order exists
//     $check_query = "SELECT orderID, parentOrderID FROM `order` WHERE orderID = '$orderId'";
//     $check_result = mysqli_query($conn, $check_query);

//     if (mysqli_num_rows($check_result) == 0) {
//         sendResponse('Order not found', 404, 0);
//     }

//     $order = mysqli_fetch_assoc($check_result);
//     $originalOrderID = $order['parentOrderID'] ?? $order['orderID'];

//     // Delete all versions of this order (including history)
//     $query = "DELETE FROM `order` WHERE orderID = '$orderId' OR 
//               COALESCE(parentOrderID, orderID) = '$originalOrderID'";

//     if (mysqli_query($conn, $query)) {
//         $deletedCount = mysqli_affected_rows($conn);
//         sendResponse("Order and its history deleted successfully! ($deletedCount records deleted)", 200, 1);
//     } else {
//         sendResponse('Error deleting order: ' . mysqli_error($conn), 500, 0);
//     }
// }
function deleteOrder($orderId)
{
    global $conn;
    $orderId = mysqli_real_escape_string($conn, $orderId);

    // find the root (original) order
    $rootQ  = "SELECT COALESCE(parentOrderID, orderID) AS rootID
               FROM `order` WHERE orderID = '$orderId' LIMIT 1";
    $rootRes = mysqli_query($conn, $rootQ);
    if (mysqli_num_rows($rootRes) === 0) {
        sendResponse('Order not found', 404, 0);
    }
    $rootID = mysqli_fetch_assoc($rootRes)['rootID'];

    // delete in two steps inside a transaction
    mysqli_begin_transaction($conn);
    try {
        // 1- delete children
        mysqli_query(
            $conn,
            "DELETE FROM `order` WHERE parentOrderID = '$rootID'"
        );

        // 2- delete the parent
        mysqli_query(
            $conn,
            "DELETE FROM `order` WHERE orderID = '$rootID'"
        );

        $deleted = mysqli_affected_rows($conn);
        mysqli_commit($conn);

        sendResponse("Order Deleted successfully", 200, 1);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        sendResponse('Error deleting order: ' . $e->getMessage(), 500, 0);
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
