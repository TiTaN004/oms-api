<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
// require_once __DIR__ .'/../config.php';
// require_once __DIR__ .'/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;

class FirebaseNotificationService
{
    private $messaging;
    private $conn;

    public function __construct($serviceAccountPath, $conn)
    {
        $this->conn = $conn;
        // Initialize Firebase
        $factory = (new Factory)->withServiceAccount($serviceAccountPath);
        $this->messaging = $factory->createMessaging();
    }

    // public function sendNewOrderNotification($userId, $orderNo, $orderDetails = []) {
    //     $tokens = $this->getUserTokens($userId);

    //     if (empty($tokens)) {
    //         return [
    //             'success' => false,
    //             'message' => 'No active devices found for user'
    //         ];
    //     }

    //     $notification = Notification::create()
    //         ->withTitle('New Order Assigned')
    //         ->withBody("You have been assigned order: {$orderNo}");

    //     $data = array_merge([
    //         'type' => 'new_order',
    //         'order_no' => $orderNo,
    //         'timestamp' => date('Y-m-d H:i:s')
    //     ], $orderDetails);

    //     return $this->sendToTokens($tokens, $notification, $data);
    // }

    public function sendNewOrderNotification($userId, $orderNo, $orderDetails = [])
    {
        $tokens = $this->getUserTokens($userId);

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No active devices found for user'
            ];
        }

        $notification = Notification::create(
            'New Order Assigned',
            "You have been assigned to order: {$orderNo}"
        );

        $data = array_merge([
            'type' => 'new_order',
            'order_no' => $orderNo,
            'timestamp' => date('Y-m-d H:i:s')
        ], $orderDetails);

        return $this->sendToTokens($tokens, $notification, $data);
    }

    public function sendCustomNotification($userId, $title, $body, $data = [])
    {
        $tokens = $this->getUserTokens($userId);

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No active devices found for user'
            ];
        }

        $notification = Notification::create()
            ->withTitle($title)
            ->withBody($body);

        return $this->sendToTokens($tokens, $notification, $data);
    }

    private function sendToTokens($tokens, $notification, $data = [])
    {
        $successCount = 0;
        $failureCount = 0;
        $errors = [];
        $invalidTokens = [];

        // var_dump($tokens);

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($notification)
                    ->withData($data);

                $this->messaging->send($message);
                $successCount++;
            } catch (MessagingException $e) {
                $failureCount++;
                $errorCode = $e->getCode();

                // Handle invalid tokens
                if (in_array($errorCode, ['invalid-registration-token', 'registration-token-not-registered'])) {
                    $invalidTokens[] = $token;
                }

                $errors[] = [
                    'token' => $token,
                    'error' => $e->getMessage(),
                    'code' => $errorCode
                ];
            } catch (Exception $e) {
                $failureCount++;
                $errors[] = [
                    'token' => $token,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Clean up invalid tokens
        if (!empty($invalidTokens)) {
            $this->removeInvalidTokens($invalidTokens);
        }

        return [
            'success' => $successCount > 0,
            'message' => $successCount > 0 ? 'Notification sent successfully' : 'Failed to send notifications',
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total_tokens' => count($tokens),
            'errors' => $errors,
            'invalid_tokens_removed' => count($invalidTokens)
        ];
    }

    public function sendToMultipleTokens($tokens, $notification, $data = [])
    {
        try {
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            $invalidTokens = [];
            if ($report->hasFailures()) {
                foreach ($report->failures()->getItems() as $failure) {
                    $error = $failure->error();
                    if (in_array($error->getCode(), ['invalid-registration-token', 'registration-token-not-registered'])) {
                        $invalidTokens[] = $failure->target()->value();
                    }
                }

                // Clean up invalid tokens
                if (!empty($invalidTokens)) {
                    $this->removeInvalidTokens($invalidTokens);
                }
            }

            return [
                'success' => $report->successes()->count() > 0,
                'message' => $report->successes()->count() > 0 ? 'Notifications sent successfully' : 'Failed to send notifications',
                'success_count' => $report->successes()->count(),
                'failure_count' => $report->failures()->count(),
                'total_tokens' => count($tokens),
                'invalid_tokens_removed' => count($invalidTokens)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error sending notifications: ' . $e->getMessage(),
                'success_count' => 0,
                'failure_count' => count($tokens),
                'total_tokens' => count($tokens)
            ];
        }
    }

    private function getUserTokens($userId)
    {
        $userId = mysqli_real_escape_string($this->conn, $userId);
        $tokens = [];

        $query = "SELECT device_token FROM user_devices WHERE userID = '$userId' AND is_active = 1";
        $result = mysqli_query($this->conn, $query);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['device_token'])) {
                    $tokens[] = $row['device_token'];
                }
            }
        } else {
            error_log("Error fetching user tokens: " . mysqli_error($this->conn));
        }

        return $tokens;
    }

    private function removeInvalidTokens($invalidTokens)
    {
        if (empty($invalidTokens)) {
            return;
        }

        $tokenList = "'" . implode("','", array_map(function ($token) {
            return mysqli_real_escape_string($this->conn, $token);
        }, $invalidTokens)) . "'";

        $query = "UPDATE user_devices SET is_active = 0 WHERE device_token IN ($tokenList)";

        if (!mysqli_query($this->conn, $query)) {
            error_log("Error removing invalid tokens: " . mysqli_error($this->conn));
        } else {
            error_log("Removed " . count($invalidTokens) . " invalid tokens from database");
        }
    }
}

// Usage example:
/*
try {
    // Path to your Firebase service account JSON file
    $serviceAccountPath = '/path/to/your/firebase-service-account.json';
    
    $notificationService = new FirebaseNotificationService($serviceAccountPath, $conn);
    
    // Send new order notification
    $result = $notificationService->sendNewOrderNotification(
        $userId = 123,
        $orderNo = 'ORD-2024-001',
        $orderDetails = [
            'customer_name' => 'John Doe',
            'delivery_address' => '123 Main St',
            'total_amount' => '50.00'
        ]
    );
    
    if ($result['success']) {
        echo "Notification sent successfully!";
    } else {
        echo "Failed to send notification: " . $result['message'];
    }
    
} catch (Exception $e) {
    echo "Error initializing notification service: " . $e->getMessage();
}
*/