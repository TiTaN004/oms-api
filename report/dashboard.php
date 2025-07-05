<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config.php'; 

try {
    $userID = isset($_GET['userID']) ? mysqli_real_escape_string($conn, $_GET['userID']) : null;
    $isAdmin = isset($_GET['isAdmin']) && $_GET['isAdmin'] === 'true';

    if ($isAdmin) {
        $query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN latest_status = 'Processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN latest_status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN latest_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders
        FROM (
            SELECT 
                COALESCE(o.parentOrderID, o.orderID) as parent_order,
                (SELECT status FROM `order` o2 
                 WHERE (o2.parentOrderID = COALESCE(o.parentOrderID, o.orderID) OR 
                        (o.parentOrderID IS NULL AND o2.orderID = o.orderID))
                   AND o2.isActive = 1 
                 ORDER BY o2.orderID DESC LIMIT 1) as latest_status
            FROM `order` o
            WHERE o.isActive = 1 
              AND o.parentOrderID IS NULL
            GROUP BY COALESCE(o.parentOrderID, o.orderID)
        ) as unique_orders";
    } else {
        if (!$userID) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'User ID is required for non-admin requests'
            ]);
            exit;
        }

        $query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN latest_status = 'Processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN latest_status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(CASE WHEN latest_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders
        FROM (
            SELECT 
                COALESCE(o.parentOrderID, o.orderID) as parent_order,
                (SELECT status FROM `order` o2 
                 WHERE (o2.parentOrderID = COALESCE(o.parentOrderID, o.orderID) OR 
                        (o.parentOrderID IS NULL AND o2.orderID = o.orderID))
                   AND o2.isActive = 1 
                 ORDER BY o2.orderID DESC LIMIT 1) as latest_status
            FROM `order` o
            WHERE o.isActive = 1 
              AND EXISTS (
                  SELECT 1 FROM `order` o3 
                  WHERE (o3.parentOrderID = COALESCE(o.parentOrderID, o.orderID) OR 
                         (o.parentOrderID IS NULL AND o3.orderID = o.orderID))
                    AND o3.fAssignUserID = '$userID' 
                    AND o3.isActive = 1
              )
              AND o.parentOrderID IS NULL
            GROUP BY COALESCE(o.parentOrderID, o.orderID)
        ) as unique_orders";
    }

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Query failed',
            'message' => mysqli_error($conn)
        ]);
        exit;
    }

    $data = mysqli_fetch_assoc($result);

    echo json_encode([
        'success' => true,
        'data' => [
            'total_orders' => (int) $data['total_orders'],
            'processing_orders' => (int) $data['processing_orders'],
            'completed_orders' => (int) $data['completed_orders'],
            'cancelled_orders' => (int) $data['cancelled_orders']
        ],
        'message' => 'Dashboard statistics retrieved successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'message' => 'Unable to process request'
    ]);
}
?>