<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = '127.0.0.1';
$dbname = 'oms_site';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Route handling
switch($method) {
    case 'GET':
        handleGetRequest($pathParts, $pdo);
        break;
    case 'POST':
        handlePostRequest($pathParts, $pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGetRequest($pathParts, $pdo) {
    $endpoint = end($pathParts);
    
    switch($endpoint) {
        case 'reports':
            getReports($pdo);
            break;
        case 'filter-options':
            getFilterOptions($pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

function handlePostRequest($pathParts, $pdo) {
    $endpoint = end($pathParts);
    
    switch($endpoint) {
        case 'reports':
            getFilteredReports($pdo);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

function getFilterOptions($pdo) {
    try {
        $result = [];
        
        // Get clients
        $stmt = $pdo->query("SELECT id, clientName FROM client_master WHERE isActive = 1 ORDER BY clientName");
        $result['clients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get products
        $stmt = $pdo->query("SELECT id, product_name FROM product WHERE is_active = 1 ORDER BY product_name");
        $result['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get operation types
        $stmt = $pdo->query("SELECT id, operationName FROM operation_type WHERE isActive = 1 ORDER BY operationName");
        $result['operationTypes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get users
        $stmt = $pdo->query("SELECT userID, fullName FROM user WHERE isActive = 1 ORDER BY fullName");
        $result['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get status options
        $result['statusOptions'] = [
            ['value' => 'Processing', 'label' => 'Processing'],
            ['value' => 'Completed', 'label' => 'Completed'],
            ['value' => 'Cancelled', 'label' => 'Cancelled']
        ];
        
        echo json_encode($result);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch filter options: ' . $e->getMessage()]);
    }
}

function getReports($pdo) {
    try {
        $sql = "SELECT 
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
                WHERE o.isActive = 1
                ORDER BY o.orderOn DESC
                LIMIT 100";
        
        $stmt = $pdo->query($sql);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $reports,
            'total' => count($reports)
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch reports: ' . $e->getMessage()]);
    }
}

function getFilteredReports($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Base query
        $sql = "SELECT 
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
                WHERE o.isActive = 1";
        
        $params = [];
        $conditions = [];
        
        // Apply filters
        if (!empty($input['fromDate'])) {
            $conditions[] = "DATE(o.orderOn) >= :fromDate";
            $params['fromDate'] = $input['fromDate'];
        }
        
        if (!empty($input['toDate'])) {
            $conditions[] = "DATE(o.orderOn) <= :toDate";
            $params['toDate'] = $input['toDate'];
        }
        
        if (!empty($input['clientId'])) {
            $conditions[] = "o.fClientID = :clientId";
            $params['clientId'] = $input['clientId'];
        }
        
        if (!empty($input['productId'])) {
            $conditions[] = "o.fProductID = :productId";
            $params['productId'] = $input['productId'];
        }
        
        if (!empty($input['operationTypeId'])) {
            $conditions[] = "o.fOperationID = :operationTypeId";
            $params['operationTypeId'] = $input['operationTypeId'];
        }
        
        if (!empty($input['assignedUserId'])) {
            $conditions[] = "o.fAssignUserID = :assignedUserId";
            $params['assignedUserId'] = $input['assignedUserId'];
        }
        
        if (!empty($input['status'])) {
            $conditions[] = "o.status = :status";
            $params['status'] = $input['status'];
        }
        
        if (!empty($input['search'])) {
            $conditions[] = "(cm.clientName LIKE :search OR p.product_name LIKE :search OR o.orderNo LIKE :search OR u.fullName LIKE :search)";
            $params['search'] = '%' . $input['search'] . '%';
        }
        
        // Add conditions to SQL
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY o.orderOn DESC";
        
        // Add limit if specified
        if (isset($input['limit']) && $input['limit'] > 0) {
            $sql .= " LIMIT " . intval($input['limit']);
            if (isset($input['offset']) && $input['offset'] > 0) {
                $sql .= " OFFSET " . intval($input['offset']);
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM `order` o
                     LEFT JOIN client_master cm ON o.fClientID = cm.id
                     LEFT JOIN user u ON o.fAssignUserID = u.userID
                     LEFT JOIN product p ON o.fProductID = p.id
                     LEFT JOIN operation_type ot ON o.fOperationID = ot.id
                     WHERE o.isActive = 1";
        
        if (!empty($conditions)) {
            $countSql .= " AND " . implode(" AND ", $conditions);
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Calculate summary statistics
        $summaryStats = calculateSummaryStats($reports);
        
        echo json_encode([
            'success' => true,
            'data' => $reports,
            'total' => $totalCount,
            'filteredCount' => count($reports),
            'summary' => $summaryStats
        ]);
        
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch filtered reports: ' . $e->getMessage()]);
    }
}

function calculateSummaryStats($reports) {
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

// Additional utility functions
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>