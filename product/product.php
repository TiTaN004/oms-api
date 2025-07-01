<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($request_uri, '/'));

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getProduct($conn, $_GET['id']);
        } else {
            getAllProducts($conn);
        }
        break;

    case 'POST':
        if (isset($_GET['id'])) {
            updateProduct($conn, $_GET['id']);
        } else {
            createProduct($conn);
        }
        break;

    // case 'PUT':
    //     if (isset($_GET['id'])) {
    //         updateProduct($conn, $_GET['id']);
    //     } else {
    //         echo json_encode(['success' => false, 'error' => 'Product ID is required for update']);
    //     }
    //     break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteProduct($conn, $_GET['id']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product ID is required for delete']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}

function getAllProducts($conn)
{
    $sql = "SELECT * FROM product ORDER BY id DESC";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = [
                'srno' => $row['id'],
                'productName' => $row['product_name'],
                'product_image' => $row['product_image'] ? 'http://localhost/freelancing/oms-api/uploads/products/' . $row['product_image'] : null,
                'image' => $row['product_image'] ? 'http://localhost/freelancing/oms-api/uploads/products/' . $row['product_image'] : null,
                'isActive' => (bool)$row['is_active']
            ];
        }
        echo json_encode(['success' => true, 'data' => $products]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function getProduct($conn, $id)
{
    $sql = "SELECT * FROM product WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $product = [
            'srno' => $row['id'],
            'productName' => $row['product_name'],
            'product_image' => $row['product_image'] ? 'http://localhost/freelancing/oms-api/uploads/products/' . $row['product_image'] : null,
            'image' => $row['product_image'] ? 'http://localhost/freelancing/oms-api/uploads/products/' . $row['product_image'] : null,
            'isActive' => (bool)$row['is_active']
        ];
        echo json_encode(['success' => true, 'data' => $product]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }

    mysqli_stmt_close($stmt);
}

// function createProduct($conn)
// {
//     // Debug: Log received data
//     error_log("POST data: " . print_r($_POST, true));
//     error_log("FILES data: " . print_r($_FILES, true));

//     $product_name = $_POST['product_name'] ?? '';
//     $is_active = isset($_POST['is_active']) ? ($_POST['is_active'] === '1' || $_POST['is_active'] === 'true' || $_POST['is_active'] === true) : true;

//     // Validate required fields
//     if (empty($product_name)) {
//         echo json_encode(['success' => false, 'error' => 'Product name is required']);
//         return;
//     }

//     $image_filename = null;

//     // Handle image upload - Check for 'product_image' field name from frontend
//     if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
//         $image_filename = handleImageUpload($_FILES['product_image']);
//         if (!$image_filename) {
//             echo json_encode(['success' => false, 'error' => 'Failed to upload image' . ($file['error'] ?? 'unknown')]);
//             return;
//         }
//     } else if (isset($_POST['image_base64']) && !empty($_POST['image_base64'])) {
//         // Handle base64 image
//         $image_filename = handleBase64Image($_POST['image_base64']);
//         if (!$image_filename) {
//             echo json_encode(['success' => false, 'error' => 'Failed to process base64 image']);
//             return;
//         }
//     }

//     $sql = "INSERT INTO product (product_name, product_image, is_active) VALUES (?, ?, ?)";
//     $stmt = mysqli_prepare($conn, $sql);
//     $is_active_int = $is_active ? 1 : 0;
//     mysqli_stmt_bind_param($stmt, "ssi", $product_name, $image_filename, $is_active_int);

//     if (mysqli_stmt_execute($stmt)) {
//         $product_id = mysqli_insert_id($conn);
//         echo json_encode([
//             'success' => true,
//             'message' => 'Product created successfully',
//             'data' => [
//                 'id' => $product_id,
//                 'product_name' => $product_name,
//                 'image' => $image_filename ? 'http://localhost/freelancing/oms-api/uploads/products/' . $image_filename : null,
//                 'is_active' => $is_active
//             ]
//         ]);
//     } else {
//         echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
//     }

//     mysqli_stmt_close($stmt);
// }

function createProduct($conn) {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    error_log("=== CREATE PRODUCT DEBUG START ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    $product_name = $_POST['product_name'] ?? '';
    $is_active = isset($_POST['is_active']) ? ($_POST['is_active'] === '1' || $_POST['is_active'] === 'true') : true;
    
    error_log("Product name: " . $product_name);
    error_log("Is active: " . ($is_active ? 'true' : 'false'));
    
    // Validate required fields
    if (empty($product_name)) {
        error_log("Product name is empty");
        echo json_encode(['success' => false, 'error' => 'Product name is required']);
        return;
    }
    
    $image_filename = null;
    
    // Handle image upload
    if (isset($_FILES['product_image'])) {
        error_log("Processing image upload...");
        $image_filename = handleImageUploadDebug($_FILES['product_image']);
        if (!$image_filename) {
            error_log("Image upload failed");
            echo json_encode(['success' => false, 'error' => 'Failed to upload image. Check server logs for details.']);
            return;
        }
        error_log("Image uploaded successfully: " . $image_filename);
    } else {
        error_log("No image file found in $_FILES");
    }
    
    $sql = "INSERT INTO product (product_name, product_image, is_active) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    $is_active_int = $is_active ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "ssi", $product_name, $image_filename, $is_active_int);
    
    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($conn);
        error_log("Product created successfully with ID: " . $product_id);
        echo json_encode([
            'success' => true, 
            'statusCode' => 200,
            'outVal' => 1,
            'message' => 'Product created successfully',
            'data' => [
                'id' => $product_id,
                'product_name' => $product_name,
                'image' => $image_filename ? 'http://localhost/freelancing/oms-api/uploads/products/' . $image_filename : null,
                'is_active' => $is_active
            ]
        ]);
    } else {
        error_log("Database error: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
    error_log("=== CREATE PRODUCT DEBUG END ===");
}

function handleImageUploadDebug($file) {
    error_log("=== IMAGE UPLOAD DEBUG START ===");
    
    $upload_dir = '../uploads/products/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Step 1: Check if file was uploaded
    if (!isset($file) || !is_array($file)) {
        error_log("STEP 1 FAILED: No file uploaded or invalid file array");
        return false;
    }
    error_log("STEP 1 PASSED: File array exists");
    error_log("File details: " . print_r($file, true));
    
    // Step 2: Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("STEP 2 FAILED: Upload error code: " . $file['error']);
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                error_log("Error: File exceeds upload_max_filesize directive");
                break;
            case UPLOAD_ERR_FORM_SIZE:
                error_log("Error: File exceeds MAX_FILE_SIZE directive");
                break;
            case UPLOAD_ERR_PARTIAL:
                error_log("Error: File was only partially uploaded");
                break;
            case UPLOAD_ERR_NO_FILE:
                error_log("Error: No file was uploaded");
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                error_log("Error: Missing temporary folder");
                break;
            case UPLOAD_ERR_CANT_WRITE:
                error_log("Error: Failed to write file to disk");
                break;
            case UPLOAD_ERR_EXTENSION:
                error_log("Error: Upload stopped by extension");
                break;
            default:
                error_log("Error: Unknown upload error");
        }
        return false;
    }
    error_log("STEP 2 PASSED: No upload errors");
    
    // Step 3: Check if uploaded file exists and is readable
    if (!file_exists($file['tmp_name'])) {
        error_log("STEP 3 FAILED: Temporary file does not exist: " . $file['tmp_name']);
        return false;
    }
    if (!is_readable($file['tmp_name'])) {
        error_log("STEP 3 FAILED: Temporary file is not readable: " . $file['tmp_name']);
        return false;
    }
    error_log("STEP 3 PASSED: Temporary file exists and is readable");
    
    // Step 4: Validate file type using actual file content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    error_log("Detected MIME type: " . $mime_type);
    error_log("Reported MIME type: " . $file['type']);
    
    if (!in_array($mime_type, $allowed_types)) {
        error_log("STEP 4 FAILED: Invalid file type detected: " . $mime_type);
        return false;
    }
    error_log("STEP 4 PASSED: Valid file type");
    
    // Step 5: Validate file size
    if ($file['size'] > $max_size) {
        error_log("STEP 5 FAILED: File too large: " . $file['size'] . " bytes (max: " . $max_size . ")");
        return false;
    }
    error_log("STEP 5 PASSED: File size OK: " . $file['size'] . " bytes");
    
    // Step 6: Check upload directory
    $real_upload_dir = realpath($upload_dir);
    error_log("Upload directory: " . $upload_dir);
    error_log("Real upload directory: " . ($real_upload_dir ? $real_upload_dir : 'NOT FOUND'));
    
    if (!is_dir($upload_dir)) {
        error_log("STEP 6: Upload directory does not exist, attempting to create: " . $upload_dir);
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("STEP 6 FAILED: Could not create upload directory");
            return false;
        }
        error_log("STEP 6: Upload directory created successfully");
    }
    
    if (!is_writable($upload_dir)) {
        error_log("STEP 6 FAILED: Upload directory is not writable");
        error_log("Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
        return false;
    }
    error_log("STEP 6 PASSED: Upload directory exists and is writable");
    
    // Step 7: Generate filename and check for conflicts
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('product_') . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    error_log("Generated filename: " . $filename);
    error_log("Full filepath: " . $filepath);
    
    // Check if file already exists (shouldn't happen with uniqid, but just in case)
    if (file_exists($filepath)) {
        error_log("STEP 7 WARNING: File already exists, generating new name");
        $filename = uniqid('product_' . time() . '_') . '.' . $extension;
        $filepath = $upload_dir . $filename;
    }
    
    // Step 8: Move the file
    error_log("STEP 8: Attempting to move file from " . $file['tmp_name'] . " to " . $filepath);
    
    // Check if the source file is a valid uploaded file
    if (!is_uploaded_file($file['tmp_name'])) {
        error_log("STEP 8 FAILED: Source file is not a valid uploaded file");
        return false;
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log("STEP 8 PASSED: File moved successfully");
        
        // Verify the file was actually moved
        if (file_exists($filepath)) {
            $file_size = filesize($filepath);
            error_log("File verification: exists, size: " . $file_size . " bytes");
            error_log("=== IMAGE UPLOAD DEBUG END - SUCCESS ===");
            return $filename;
        } else {
            error_log("STEP 8 FAILED: File move reported success but file doesn't exist at destination");
            return false;
        }
    } else {
        error_log("STEP 8 FAILED: move_uploaded_file() returned false");
        error_log("Last PHP error: " . (error_get_last()['message'] ?? 'No error message'));
        error_log("=== IMAGE UPLOAD DEBUG END - FAILED ===");
        return false;
    }
}

function updateProduct($conn, $id)
{
    // Debug: Log received data
    error_log("PUT - POST data: " . print_r($_POST, true));
    error_log("PUT - FILES data: " . print_r($_FILES, true));

    // Get current product data
    $sql = "SELECT * FROM product WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$current_product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        return;
    }

    $product_name = $_POST['product_name'] ?? $current_product['product_name'];
    $is_active = isset($_POST['is_active']) ? ($_POST['is_active'] === '1' || $_POST['is_active'] === 'true' || $_POST['is_active'] === true) : (bool)$current_product['is_active'];
    $image_filename = $current_product['product_image'];

    // Handle new image upload - Check for 'product_image' field name from frontend
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        // Delete old image if exists
        if ($image_filename && file_exists('../uploads/products/' . $image_filename)) {
            unlink('../uploads/products/' . $image_filename);
        }

        $image_filename = handleImageUpload($_FILES['product_image']);
        if (!$image_filename) {
            echo json_encode(['success' => false, 'error' => 'Failed to upload new image']);
            return;
        }
    } else if (isset($_POST['image_base64']) && !empty($_POST['image_base64'])) {
        // Handle base64 image
        if ($image_filename && file_exists('../uploads/products/' . $image_filename)) {
            unlink('../uploads/products/' . $image_filename);
        }

        $image_filename = handleBase64Image($_POST['image_base64']);
        if (!$image_filename) {
            echo json_encode(['success' => false, 'error' => 'Failed to process base64 image']);
            return;
        }
    }

    $sql = "UPDATE product SET product_name = ?, product_image = ?, is_active = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $is_active_int = $is_active ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "ssii", $product_name, $image_filename, $is_active_int, $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully',
            'statusCode' => 200,
            'outVal' => 1,
            'data' => [
                'id' => $id,
                'product_name' => $product_name,
                'image' => $image_filename ? 'http://localhost/freelancing/oms-api/uploads/products/' . $image_filename : null,
                'is_active' => $is_active
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }

    mysqli_stmt_close($stmt);
}

function deleteProduct($conn, $id)
{
    // Get product data to delete associated image
    $sql = "SELECT product_image FROM product WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        return;
    }

    // Delete the product from database
    $sql = "DELETE FROM product WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        // Delete associated image file
        if ($product['product_image'] && file_exists('../uploads/products/' . $product['product_image'])) {
            unlink('../uploads/products/' . $product['product_image']);
        }

        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }

    mysqli_stmt_close($stmt);
}

function handleImageUpload($file)
{
    $upload_dir = '../uploads/products/';
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/heic'];
    $max_size = 5 * 1024 * 1024; // 5MB
    error_log("FILES: " . print_r($_FILES, true));
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file['type']);
        return false;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        error_log("File too large: " . $file['size']);
        return false;
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error code: " . $file['error']);
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                error_log("File too large");
                break;
            case UPLOAD_ERR_PARTIAL:
                error_log("File partially uploaded");
                break;
            case UPLOAD_ERR_NO_FILE:
                error_log("No file uploaded");
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                error_log("No temporary directory");
                break;
            case UPLOAD_ERR_CANT_WRITE:
                error_log("Cannot write to disk");
                break;
            default:
                error_log("Unknown upload error");
        }
    }

    // Validate file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime_type, $allowed_types)) {
        error_log("Invalid file type: " . $mime_type . " (detected), " . $file['type'] . " (reported)");
        return false;
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        error_log("File too large: " . $file['size'] . " bytes");
        return false;
    }
    
    // Check if upload directory exists and is writable
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            return false;
        }
    }
    
    if (!is_writable($upload_dir)) {
        error_log("Upload directory is not writable: " . $upload_dir);
        return false;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }

    error_log("Failed to move uploaded file");
    return false;
}

function handleBase64Image($base64_string)
{
    $upload_dir = '../uploads/products/';

    // Remove data:image/xxx;base64, part
    if (strpos($base64_string, 'data:image') === 0) {
        $base64_string = explode(',', $base64_string)[1];
    }

    $image_data = base64_decode($base64_string);
    if ($image_data === false) {
        return false;
    }

    // Get image info
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        return false;
    }

    // Generate filename based on mime type
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    $extension = $extensions[$mime_type];
    $filename = uniqid('product_') . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // Save image
    if (file_put_contents($filepath, $image_data)) {
        return $filename;
    }

    return false;
}

mysqli_close($conn);
