<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';
// require_once 'config/database.php';

class ResetPasswordAPI {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function resetPassword() {
        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (!isset($input['email']) || empty(trim($input['email']))) {
                return $this->sendResponse(400, 0, 'Email is required');
            }
            
            if (!isset($input['otp']) || empty(trim($input['otp']))) {
                return $this->sendResponse(400, 0, 'OTP is required');
            }
            
            if (!isset($input['newPassword']) || empty(trim($input['newPassword']))) {
                return $this->sendResponse(400, 0, 'New password is required');
            }
            
            $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
            $otp = trim($input['otp']);
            $newPassword = trim($input['newPassword']);
            
            if (!$email) {
                return $this->sendResponse(400, 0, 'Invalid email format');
            }
            
            // Validate password strength
            // if (strlen($newPassword) < 6) {
            //     return $this->sendResponse(400, 0, 'Password must be at least 6 characters long');
            // }
            
            // Find user by email
            $stmt = $this->conn->prepare("SELECT userID, fullName FROM user WHERE emailID = ? AND isActive = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return $this->sendResponse(404, 0, 'No account found with this email address');
            }
            
            $user = $result->fetch_assoc();
            
            // Verify OTP
            $stmt = $this->conn->prepare("SELECT id FROM password_reset_tokens WHERE userID = ? AND token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("is", $user['userID'], $otp);
            $stmt->execute();
            $otpResult = $stmt->get_result();
            
            if ($otpResult->num_rows === 0) {
                return $this->sendResponse(400, 0, 'Invalid or expired OTP');
            }
            
            $otpRecord = $otpResult->fetch_assoc();
            
            // Hash the new password
            // $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Start transaction
            $this->conn->begin_transaction();
            
            try {
                // Update user password
                $stmt = $this->conn->prepare("UPDATE user SET password = ? WHERE userID = ?");
                $stmt->bind_param("si", $newPassword, $user['userID']);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update password');
                }
                
                // Delete used OTP token
                $stmt = $this->conn->prepare("DELETE FROM password_reset_tokens WHERE id = ?");
                $stmt->bind_param("i", $otpRecord['id']);
                $stmt->execute();
                
                // Delete all other OTP tokens for this user
                $stmt = $this->conn->prepare("DELETE FROM password_reset_tokens WHERE userID = ?");
                $stmt->bind_param("i", $user['userID']);
                $stmt->execute();
                
                // Commit transaction
                $this->conn->commit();
                
                return $this->sendResponse(200, 1, 'Password reset successfully', [
                    'message' => 'Your password has been updated successfully. You can now login with your new password.'
                ]);
                
            } catch (Exception $e) {
                // Rollback transaction
                $this->conn->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, 0, 'Server error: ' . $e->getMessage());
        }
    }
    
    public function verifyOTP() {
        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['email']) || empty(trim($input['email']))) {
                return $this->sendResponse(400, 0, 'Email is required');
            }
            
            if (!isset($input['otp']) || empty(trim($input['otp']))) {
                return $this->sendResponse(400, 0, 'OTP is required');
            }
            
            $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
            $otp = trim($input['otp']);
            
            if (!$email) {
                return $this->sendResponse(400, 0, 'Invalid email format');
            }
            
            // Find user by email
            $stmt = $this->conn->prepare("SELECT userID FROM user WHERE emailID = ? AND isActive = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return $this->sendResponse(404, 0, 'No account found with this email address');
            }
            
            $user = $result->fetch_assoc();
            
            // Verify OTP
            $stmt = $this->conn->prepare("SELECT expires_at FROM password_reset_tokens WHERE userID = ? AND token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("is", $user['userID'], $otp);
            $stmt->execute();
            $otpResult = $stmt->get_result();
            
            if ($otpResult->num_rows === 0) {
                return $this->sendResponse(400, 0, 'Invalid or expired OTP');
            }
            
            return $this->sendResponse(200, 1, 'OTP verified successfully', [
                'verified' => true
            ]);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, 0, 'Server error: ' . $e->getMessage());
        }
    }
    
    private function sendResponse($statusCode, $outVal, $message, $data = null) {
        http_response_code($statusCode);
        $response = [
            'statusCode' => $statusCode,
            'outVal' => $outVal,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
}

// Database connection
try {
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $resetPassword = new ResetPasswordAPI($conn);
        
        // Check if it's OTP verification or password reset
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action']) && $input['action'] === 'verify_otp') {
            $resetPassword->verifyOTP();
        } else {
            $resetPassword->resetPassword();
        }
    } else {
        http_response_code(405);
        echo json_encode(['statusCode' => 405, 'outVal' => 0, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['statusCode' => 500, 'outVal' => 0, 'message' => 'Database connection failed']);
}
?>