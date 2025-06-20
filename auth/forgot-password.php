<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

date_default_timezone_set('Asia/Kolkata');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';
require_once '../vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class ForgotPasswordAPI {
 private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function sendOTP() {
        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['email']) || empty(trim($input['email']))) {
                return $this->sendResponse(400, 0, 'Email is required');
            }
            
            $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
            if (!$email) {
                return $this->sendResponse(400, 0, 'Invalid email format');
            }
            
            // Check if user exists
            $stmt = $this->conn->prepare("SELECT userID, fullName FROM user WHERE emailID = ? AND isActive = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return $this->sendResponse(404, 0, 'No account found with this email address');
            }
            
            $user = $result->fetch_assoc();
            
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes')); // OTP expires in 15 minutes
            
            // Store OTP in password_reset_tokens table
            $stmt = $this->conn->prepare("INSERT INTO password_reset_tokens (userID, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP");
            
            $stmt->bind_param("iss", $user['userID'], $otp, $expires_at);
            
            if (!$stmt->execute()) {
                return $this->sendResponse(500, 0, 'Failed to generate OTP');
            }
            
            // Send OTP via email
            if ($this->sendOTPEmail($email, $user['fullName'], $otp)) {
                return $this->sendResponse(200, 1, 'OTP sent successfully to your email', [
                    'email' => $email,
                    'expires_in' => 15 // minutes
                ]);
            } else {
                return $this->sendResponse(500, 0, 'Failed to send OTP email');
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, 0, 'Server error: ' . $e->getMessage());
        }
    }
    
    private function sendOTPEmail($email, $fullName, $otp) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com'; // Change to your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'hello@buzzimgenerator.com'; // Your email
            $mail->Password   = 'Mnr217004nsr*'; // Your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom('hello@buzzimgenerator.com', 'OMS System');
            $mail->addAddress($email, $fullName);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - OMS System';
            $mail->Body = $this->getEmailTemplate($fullName, $otp);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    private function getEmailTemplate($fullName, $otp) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset OTP</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                    <p>Order Management System</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$fullName}!</h2>
                    <p>We received a request to reset your password for your OMS account. Use the OTP below to complete the password reset process:</p>
                    
                    <div class='otp-box'>
                        <p style='margin: 0; color: #666;'>Your OTP Code:</p>
                        <div class='otp-code'>{$otp}</div>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong>
                        <ul style='margin: 10px 0;'>
                            <li>This OTP is valid for <strong>15 minutes</strong> only</li>
                            <li>Do not share this code with anyone</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                        </ul>
                    </div>
                    
                    <p>If you're having trouble with the password reset process, please contact our support team.</p>
                    
                    <p>Best regards,<br>
                    <strong>OMS System Team</strong></p>
                </div>
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " Order Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
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
    // $database = new Database();
    // $db = $database->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $forgotPassword = new ForgotPasswordAPI($conn);
        $forgotPassword->sendOTP();
    } else {
        http_response_code(405);
        echo json_encode(['statusCode' => 405, 'outVal' => 0, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['statusCode' => 500, 'outVal' => 0, 'message' => 'Database connection failed']);
}
?>