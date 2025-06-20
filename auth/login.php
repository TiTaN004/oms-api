<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config.php';
require_once './jwt_helper.php';
require '../vendor/autoload.php'; 

class LoginAPI {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function login() {
        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['userName']) || !isset($input['password'])) {
                return $this->sendResponse("Email ID and password are required", 400, 0, []);
            }
            
            $userName = $input['userName'];
            $password = $input['password'];
            
            // Query with JOIN to get operation type info
            $sql = "SELECT u.userID, u.fullName, u.userName, u.emailID, u.mobileNo, 
                           u.password, u.operationTypeID, u.isAdmin, u.isActive,
                           ot.operation_type, ot.is_active as operation_active
                    FROM user u 
                    LEFT JOIN operation_type ot ON u.operationTypeID = ot.id 
                    WHERE u.userName = ? AND u.isActive = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $userName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return $this->sendResponse("Invalid credentials", 401, 0, []);
            }
            
            $user = $result->fetch_assoc();

            $hashedPassword = trim($user['password']);

            // Verify password (assuming you're using password_hash())
            if (!password_verify($password, $hashedPassword)) {
                return $this->sendResponse("Invalid credentials wrong password", 401, 0, []);
            }
            
            // Generate JWT token
            $payload = [
                'sub' => $user['userName'],
                'jti' => $user['userID'],
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => $user['userName'],
                'UserId' => (string)$user['userID'],
                'exp' => time() + (24 * 60 * 60), // 24 hours
                'iss' => 'otpsystem',
                'aud' => 'otpsystem'
            ];
            
            $token = JWTHelper::encode($payload);
            
            // Update token in database
            $updateSql = "UPDATE user SET token = ? WHERE userID = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bind_param("si", $token, $user['userID']);
            $updateStmt->execute();
            
            // Prepare response data
            $responseData = [
                'userID' => (int)$user['userID'],
                'fullName' => $user['fullName'],
                'userName' => $user['userName'],
                'operationTypeID' => (int)$user['operationTypeID'],
                'mobileNo' => $user['mobileNo'],
                'emailID' => $user['emailID'],
                'token' => $token,
                'isActive' => (bool)$user['isActive'],
                'isAdmin' => (bool)$user['isAdmin']
            ];
            
            return $this->sendResponse("Login successful!", 200, 1, [$responseData]);
            
        } catch (Exception $e) {
            return $this->sendResponse("Login failed: " . $e->getMessage(), 500, 0, []);
        }
    }
    
    // private function generateUUID() {
    //     return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    //         mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    //         mt_rand(0, 0xffff),
    //         mt_rand(0, 0x0fff) | 0x4000,
    //         mt_rand(0, 0x3fff) | 0x8000,
    //         mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    //     );
    // }
    
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

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginAPI = new LoginAPI($conn);
    echo $loginAPI->login();
} else {
    http_response_code(405);
    echo json_encode([
        'message' => 'Method not allowed',
        'statusCode' => 405,
        'outVal' => 0,
        'data' => []
    ]);
}
?>