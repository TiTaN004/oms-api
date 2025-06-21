<?php
// verify-token.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config.php';
require_once './jwt_helper.php';

class TokenVerifier {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function verifyToken() {
        try {
            $headers = getallheaders();
            $token = null;
            
            // Get token from Authorization header
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $token = $matches[1];
                }
            }
            
            if (!$token) {
                return $this->sendResponse("Token not provided", 401, 0, ['valid' => false]);
            }
            
            // Verify JWT
            $payload = JWTHelper::decode($token);
            if (!$payload) {
                return $this->sendResponse("Invalid token", 401, 0, ['valid' => false]);
            }
            
            // Check if token exists in database and user is active
            $userId = $payload['UserId'];
            $sql = "SELECT u.userID, u.fullName, u.userName, u.emailID, u.isAdmin, u.isActive, u.operationTypeID,
                           ot.operationName
                    FROM user u 
                    LEFT JOIN operation_type ot ON u.operationTypeID = ot.id 
                    WHERE u.userID = ? AND u.token = ? AND u.isActive = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $userId, $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return $this->sendResponse("Token invalid or user inactive", 401, 0, ['valid' => false]);
            }
            
            $user = $result->fetch_assoc();
            
            $userData = [
                'valid' => true,
                'userID' => (int)$user['userID'],
                'fullName' => $user['fullName'],
                'userName' => $user['userName'],
                'emailID' => $user['emailID'],
                'isAdmin' => (bool)$user['isAdmin'],
                'isActive' => (bool)$user['isActive'],
                'operationTypeID' => (int)$user['operationTypeID'],
                'operationType' => $user['operationName']
            ];
            
            return $this->sendResponse("Token valid", 200, 1, $userData);
            
        } catch (Exception $e) {
            return $this->sendResponse("Token verification failed", 500, 0, ['valid' => false]);
        }
    }
    
    private function sendResponse($message, $statusCode, $outVal, $data) {
        http_response_code($statusCode);
        return json_encode([
            'message' => $message,
            'statusCode' => $statusCode,
            'outVal' => $outVal,
            'data' => $data
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verifier = new TokenVerifier($conn);
    echo $verifier->verifyToken();
} else {
    http_response_code(405);
    echo json_encode([
        'message' => 'Method not allowed',
        'statusCode' => 405,
        'outVal' => 0,
        'data' => ['valid' => false]
    ]);
}
?>