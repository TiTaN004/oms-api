<?php
require_once '../config.php';
require_once './notification-service.php';

$serviceAccountPath ='../push-notification-test-fd696-b3ddb2ece7a0.json';
$notificationService = new FirebaseNotificationService($serviceAccountPath, $conn);

$result = $notificationService->sendNewOrderNotification(
            3,
            111,
            [
                'client_name' => "test",
                'product_name' => "test product",
                'order_id' => 111
            ]
        );

        if ($result['success']) {
            echo "Notification sent successfully!";
        } else {
            var_dump($result);
            // echo "Failed to send notification: " . $result['message'];
        }

?>