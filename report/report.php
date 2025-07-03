<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config.php'; // assumes $conn is defined in config.php

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

switch ($method) {
    case 'GET':
        handleGetRequest($pathParts, $conn);
        break;
    case 'POST':
        handlePostRequest($pathParts, $conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGetRequest($pathParts, $conn)
{
    $endpoint = end($pathParts);
    switch ($endpoint) {
        case 'reports':
            getReports($conn);
            break;
        case 'filter-options':
            getFilterOptions($conn);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

function handlePostRequest($pathParts, $conn)
{
    $endpoint = end($pathParts);
    switch ($endpoint) {
        case 'reports':
            getFilteredReports($conn);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

function getFilterOptions($conn)
{
    try {
        $result = [];

        $result['clients'] = fetchAllAssoc($conn, "SELECT id, clientName FROM client_master WHERE isActive = 1 ORDER BY clientName");
        $result['products'] = fetchAllAssoc($conn, "SELECT id, product_name FROM product WHERE is_active = 1 ORDER BY product_name");
        $result['operationTypes'] = fetchAllAssoc($conn, "SELECT id, operationName FROM operation_type WHERE isActive = 1 ORDER BY operationName");
        $result['users'] = fetchAllAssoc($conn, "SELECT userID, fullName FROM user WHERE isActive = 1 ORDER BY fullName");

        $result['statusOptions'] = [
            ['value' => 'Processing', 'label' => 'Processing'],
            ['value' => 'Completed', 'label' => 'Completed'],
            ['value' => 'Cancelled', 'label' => 'Cancelled']
        ];

        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch filter options: ' . $e->getMessage()]);
    }
}

function fetchAllAssoc($conn, $sql)
{
    $res = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    return $data;
}

function getReports($conn)
{
    // $sql = "SELECT 
    //             o.orderID, o.orderNo, DATE_FORMAT(o.orderOn, '%d-%M-%Y') as orderDate,
    //             cm.clientName, u.fullName as assignedTo, p.product_name as product,
    //             CONCAT(o.productWeight, ' ', wt1.name) as productWeight,
    //             CONCAT(o.weight, ' ', wt2.name) as totalWeight,
    //             o.productQty, o.pricePerQty, o.totalPrice, o.status,
    //             ot.operationName as operationType
    //         FROM `order` o
    //         LEFT JOIN client_master cm ON o.fClientID = cm.id
    //         LEFT JOIN user u ON o.fAssignUserID = u.userID
    //         LEFT JOIN product p ON o.fProductID = p.id
    //         LEFT JOIN weight_type wt1 ON o.productWeightTypeID = wt1.id
    //         LEFT JOIN weight_type wt2 ON o.WeightTypeID = wt2.id
    //         LEFT JOIN operation_type ot ON o.fOperationID = ot.id
    //         WHERE o.isActive = 1
    //         ORDER BY o.orderOn DESC
    //         LIMIT 100";

    $sql = "
    SELECT 
    o.orderID, 
    o.orderNo, 
    DATE_FORMAT(o.orderOn, '%d-%M-%Y') as orderDate,
    cm.clientName, 
    u.fullName as assignedTo, 
    p.product_name as product,
    CONCAT(o.productWeight, ' ', wt1.name) as productWeight,
    CONCAT(o.weight, ' ', wt2.name) as totalWeight,
    o.productQty, 
    o.pricePerQty, 
    o.totalPrice, 
    o.status,
    ot.operationName as operationType
FROM `order` o
LEFT JOIN client_master cm ON o.fClientID = cm.id
LEFT JOIN user u ON o.fAssignUserID = u.userID
LEFT JOIN product p ON o.fProductID = p.id
LEFT JOIN weight_type wt1 ON o.productWeightTypeID = wt1.id
LEFT JOIN weight_type wt2 ON o.WeightTypeID = wt2.id
LEFT JOIN operation_type ot ON o.fOperationID = ot.id
WHERE o.orderID IN (
    SELECT MAX(orderID)
    FROM `order`
    GROUP BY COALESCE(parentOrderID, orderID)
)
ORDER BY o.orderOn DESC
LIMIT 100";

    $res = mysqli_query($conn, $sql);
    $reports = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $reports[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $reports,
        'total' => count($reports)
    ]);
}

// function getFilteredReports($conn)
// {
//     $input = json_decode(file_get_contents('php://input'), true);

//     $sql = "SELECT 
//                 o.orderID, o.orderNo, DATE_FORMAT(o.orderOn, '%d-%M-%Y') as orderDate,
//                 cm.clientName, u.fullName as assignedTo, p.product_name as product,
//                 CONCAT(o.productWeight, ' ', wt1.name) as productWeight,
//                 CONCAT(o.weight, ' ', wt2.name) as totalWeight,
//                 o.weight,
//                 o.productQty, o.pricePerQty, o.totalPrice, o.status,
//                 ot.operationName as operationType
//             FROM `order` o
//             LEFT JOIN client_master cm ON o.fClientID = cm.id
//             LEFT JOIN user u ON o.fAssignUserID = u.userID
//             LEFT JOIN product p ON o.fProductID = p.id
//             LEFT JOIN weight_type wt1 ON o.productWeightTypeID = wt1.id
//             LEFT JOIN weight_type wt2 ON o.WeightTypeID = wt2.id
//             LEFT JOIN operation_type ot ON o.fOperationID = ot.id
//             WHERE o.isActive = 1";

//     $conditions = [];

//     // Add conditions based on input
//     $clientId        = $input['clientId']        ?? $input['fClientID']        ?? null;
//     $productId       = $input['productId']       ?? $input['fProductID']       ?? null;
//     $operationTypeId = $input['operationTypeId'] ?? $input['fOperationID']     ?? null;
//     $assignedUserId  = $input['assignedUserId']  ?? $input['fUserAssignID']    ?? null;
//     // $status          = $input['status']          ?? null;
//     $statusRaw = $input['status'] ?? null;

// if (is_numeric($statusRaw)) {
//     if ($statusRaw == 2) {
//         $status = 'Completed';
//     } elseif ($statusRaw == 1) {
//         $status = 'Processing';
//     } else {
//         $status = null; // unknown numeric, ignore
//     }
// } elseif (!empty($statusRaw)) {
//     $status = mysqli_real_escape_string($conn, $statusRaw);
// } else {
//     $status = null;
// }
//     $search          = $input['search']          ?? $input['searchText']       ?? null;

//     // Date range
//     if (!empty($input['fromDate'])) {
//         $conditions[] = "DATE(o.orderOn) >= '" . mysqli_real_escape_string($conn, $input['fromDate']) . "'";
//     }
//     if (!empty($input['toDate'])) {
//         $conditions[] = "DATE(o.orderOn) <= '" . mysqli_real_escape_string($conn, $input['toDate']) . "'";
//     }

//     // Filters (ignore 0 if not meaningful in your app)
//     if (!empty($clientId)) {
//         $conditions[] = "o.fClientID = " . intval($clientId);
//     }
//     if (!empty($productId)) {
//         $conditions[] = "o.fProductID = " . intval($productId);
//     }
//     if (!empty($operationTypeId)) {
//         $conditions[] = "o.fOperationID = " . intval($operationTypeId);
//     }
//     if (!empty($assignedUserId)) {
//         $conditions[] = "o.fAssignUserID = " . intval($assignedUserId);
//     }

//     // Special case: if `status` is numeric 0, skip filtering
//     if (!empty($status) || $status === "Pending" || $status === "Completed") {
//         $conditions[] = "o.status = '" . mysqli_real_escape_string($conn, $status) . "'";
//     }

//     // Search text
//     if (!empty($search)) {
//         $searchEscaped = mysqli_real_escape_string($conn, $search);
//         $conditions[] = "(cm.clientName LIKE '%$searchEscaped%' OR p.product_name LIKE '%$searchEscaped%' OR o.orderNo LIKE '%$searchEscaped%' OR u.fullName LIKE '%$searchEscaped%')";
//     }

//     // if (!empty($input['fromDate'])) {
//     //     $conditions[] = "DATE(o.orderOn) >= '" . mysqli_real_escape_string($conn, $input['fromDate']) . "'";
//     // }
//     // if (!empty($input['toDate'])) {
//     //     $conditions[] = "DATE(o.orderOn) <= '" . mysqli_real_escape_string($conn, $input['toDate']) . "'";
//     // }
//     // if (!empty($input['clientId'])) {
//     //     $conditions[] = "o.fClientID = " . intval($input['clientId']);
//     // }
//     // if (!empty($input['productId'])) {
//     //     $conditions[] = "o.fProductID = " . intval($input['productId']);
//     // }
//     // if (!empty($input['operationTypeId'])) {
//     //     $conditions[] = "o.fOperationID = " . intval($input['operationTypeId']);
//     // }
//     // if (!empty($input['assignedUserId'])) {
//     //     $conditions[] = "o.fAssignUserID = " . intval($input['assignedUserId']);
//     // }
//     // if (!empty($input['status'])) {
//     //     $conditions[] = "o.status = '" . mysqli_real_escape_string($conn, $input['status']) . "'";
//     // }
//     // if (!empty($input['search'])) {
//     //     $search = mysqli_real_escape_string($conn, $input['search']);
//     //     $conditions[] = "(cm.clientName LIKE '%$search%' OR p.product_name LIKE '%$search%' OR o.orderNo LIKE '%$search%' OR u.fullName LIKE '%$search%')";
//     // }

//     if (!empty($conditions)) {
//         $sql .= " AND " . implode(" AND ", $conditions);
//     }

//     $sql .= " ORDER BY o.orderOn DESC";

//     if (isset($input['limit']) && intval($input['limit']) > 0) {
//         $sql .= " LIMIT " . intval($input['limit']);
//         if (isset($input['offset']) && intval($input['offset']) > 0) {
//             $sql .= " OFFSET " . intval($input['offset']);
//         }
//     }

//     $res = mysqli_query($conn, $sql);
//     $reports = [];
//     while ($row = mysqli_fetch_assoc($res)) {
//         $reports[] = $row;
//     }

//     $summaryStats = calculateSummaryStats($reports);

//     echo json_encode([
//         'success' => true,
//         'data' => $reports,
//         'total' => count($reports),
//         'filteredCount' => count($reports),
//         'summary' => $summaryStats
//     ]);
// }

function getFilteredReports($conn)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $conditions = [];

    // Add conditions based on input
    $clientId        = $input['clientId']        ?? $input['fClientID']        ?? null;
    $productId       = $input['productId']       ?? $input['fProductID']       ?? null;
    $operationTypeId = $input['operationTypeId'] ?? $input['fOperationID']     ?? null;
    $assignedUserId  = $input['assignedUserId']  ?? $input['fUserAssignID']    ?? null;
    $statusRaw       = $input['status'] ?? null;
    $search          = $input['search'] ?? $input['searchText'] ?? null;

    if (is_numeric($statusRaw)) {
        if ($statusRaw == 2) {
            $status = 'Completed';
        } elseif ($statusRaw == 1) {
            $status = 'Processing';
        } else {
            $status = null; // unknown numeric, ignore
        }
    } elseif (!empty($statusRaw)) {
        $status = mysqli_real_escape_string($conn, $statusRaw);
    } else {
        $status = null;
    }

    // Date range
    if (!empty($input['fromDate'])) {
        $conditions[] = "DATE(o.orderOn) >= '" . mysqli_real_escape_string($conn, $input['fromDate']) . "'";
    }
    if (!empty($input['toDate'])) {
        $conditions[] = "DATE(o.orderOn) <= '" . mysqli_real_escape_string($conn, $input['toDate']) . "'";
    }

    if (!empty($clientId)) {
        $conditions[] = "o.fClientID = " . intval($clientId);
    }
    if (!empty($productId)) {
        $conditions[] = "o.fProductID = " . intval($productId);
    }
    if (!empty($operationTypeId)) {
        $conditions[] = "o.fOperationID = " . intval($operationTypeId);
    }
    if (!empty($assignedUserId)) {
        $conditions[] = "o.fAssignUserID = " . intval($assignedUserId);
    }

    if (!empty($status) || $status === "Pending" || $status === "Completed") {
        $conditions[] = "o.status = '" . mysqli_real_escape_string($conn, $status) . "'";
    }

    if (!empty($search)) {
        $searchEscaped = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(cm.clientName LIKE '%$searchEscaped%' OR p.product_name LIKE '%$searchEscaped%' OR o.orderNo LIKE '%$searchEscaped%' OR u.fullName LIKE '%$searchEscaped%')";
    }

    // ---- Core SQL ----
    $sql = "
        SELECT 
            o.orderID, 
            o.orderNo, 
            DATE_FORMAT(o.orderOn, '%d-%M-%Y') as orderDate,
            cm.clientName, 
            u.fullName as assignedTo, 
            p.product_name as product,
            CONCAT(o.productWeight, ' ', wt1.name) as productWeight,
            CONCAT(o.weight, ' ', wt2.name) as totalWeight,
            o.weight,
            o.productQty, 
            o.pricePerQty, 
            o.totalPrice, 
            o.status,
            ot.operationName as operationType
        FROM `order` o
        LEFT JOIN client_master cm ON o.fClientID = cm.id
        LEFT JOIN user u ON o.fAssignUserID = u.userID
        LEFT JOIN product p ON o.fProductID = p.id
        LEFT JOIN weight_type wt1 ON o.productWeightTypeID = wt1.id
        LEFT JOIN weight_type wt2 ON o.WeightTypeID = wt2.id
        LEFT JOIN operation_type ot ON o.fOperationID = ot.id
        WHERE o.orderID IN (
            SELECT MAX(orderID)
            FROM `order`
            WHERE isActive = 1
            GROUP BY COALESCE(parentOrderID, orderID)
        )
    ";

    // Add dynamic filters
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY o.orderOn DESC";

    // Pagination
    if (isset($input['limit']) && intval($input['limit']) > 0) {
        $sql .= " LIMIT " . intval($input['limit']);
        if (isset($input['offset']) && intval($input['offset']) > 0) {
            $sql .= " OFFSET " . intval($input['offset']);
        }
    }

    $res = mysqli_query($conn, $sql);
    $reports = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $reports[] = $row;
    }

    $summaryStats = calculateSummaryStats($reports);

    echo json_encode([
        'success' => true,
        'data' => $reports,
        'total' => count($reports),
        'filteredCount' => count($reports),
        'summary' => $summaryStats
    ]);
}


function calculateSummaryStats($reports)
{
    $stats = [
        'totalOrders' => count($reports),
        'totalAmount' => 0,
        'totalQuantity' => 0,
        'statusBreakdown' => [
            'Processing' => 0,
            'Completed' => 0,
            'Cancelled' => 0
        ]
    ];

    foreach ($reports as $report) {
        $stats['totalAmount'] += floatval($report['totalPrice']);
        $stats['totalQuantity'] += intval($report['productQty']);

        if (isset($stats['statusBreakdown'][$report['status']])) {
            $stats['statusBreakdown'][$report['status']]++;
        }
    }

    return $stats;
}
