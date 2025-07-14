<?php
// local
// // config.php
// $servername = "localhost";
// $username = "root";
// $password = "";
// $dbname = "oms_site";

// // Create connection
// $conn = new mysqli($servername, $username, $password, $dbname);

// // Check connection
// if ($conn->connect_error) {
//     die(json_encode([
//         'message' => 'Database connection failed: ' . $conn->connect_error,
//         'statusCode' => 500,
//         'outVal' => 0,
//         'data' => []
//     ]));
// }

// // Set charset
// $conn->set_charset("utf8");

//hosting
// config.php
$servername = "localhost";
$username = "u131187086_otp";
$password = "otp14_07_2025&03.27";
$dbname = "u131187086_otp_site";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'statusCode' => 500,
        'outVal' => 0,
        'data' => []
    ]));
}

// Set charset
$conn->set_charset("utf8");
?>