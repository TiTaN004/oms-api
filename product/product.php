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

switch($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getProduct($conn, $_GET['id']);
        } else {
            getAllProducts($conn);
        }
        break;
    
    case 'POST':
        createProduct($conn);
        break;
    
    case 'PUT':
        if (isset($_GET['id'])) {
            updateProduct($conn, $_GET['id']);
        } else {
            echo json_encode(['error' => 'Product ID is required for update']);
        }
        break;
    
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteProduct($conn, $_GET['id']);
        } else {
            echo json_encode(['error' => 'Product ID is required for delete']);
        }
        break;
    
    default:
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getAllProducts($conn) {
    $sql = "SELECT * FROM product ORDER BY id DESC";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = [
                'srno' => $row['id'],
                'productName' => $row['product_name'],
                'image' => $row['product_image'] ? 'http://localhost/freelancing/oms-api/uploads/products/' . $row['product_image'] : null,
                'isActive' => (bool)$row['is_active']
            ];
        }
        echo json_encode(['success' => true, 'data' => $products]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
}

function getProduct($conn, $id) {
    $sql = "SELECT * FROM product WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $product = [
            'srno' => $row['id'],
            'productName' => $row['product_name'],
            'image' => $row['product_image'] ? 'http://localhost/freelancing/oms-api/uploads/products/' . $row['product_image'] : null,
            'isActive' => (bool)$row['is_active']
        ];
        echo json_encode(['success' => true, 'data' => $product]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }
    
    mysqli_stmt_close($stmt);
}

function createProduct($conn) {
    $product_name = $_POST['product_name'] ?? '';
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;
    
    // Validate required fields
    if (empty($product_name)) {
        echo json_encode(['success' => false, 'error' => 'Product name is required']);
        return;
    }
    
    $image_filename = null;
    
    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $image_filename = handleImageUpload($_FILES['product_image']);
        if (!$image_filename) {
            echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
            return;
        }
    } else if (isset($_POST['image_base64']) && !empty($_POST['image_base64'])) {
        // Handle base64 image
        $image_filename = handleBase64Image($_POST['image_base64']);
        if (!$image_filename) {
            echo json_encode(['success' => false, 'error' => 'Failed to process base64 image']);
            return;
        }
    }
    
    $sql = "INSERT INTO product (product_name, product_image, is_active) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssi", $product_name, $image_filename, $is_active);
    
    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($conn);
        echo json_encode([
            'success' => true, 
            'message' => 'Product created successfully',
            'data' => [
                'id' => $product_id,
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

function updateProduct($conn, $id) {
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
    $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : (bool)$current_product['is_active'];
    $image_filename = $current_product['product_image'];
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Delete old image if exists
        if ($image_filename && file_exists('../uploads/products/' . $image_filename)) {
            unlink('../uploads/products/' . $image_filename);
        }
        
        $image_filename = handleImageUpload($_FILES['image']);
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
    mysqli_stmt_bind_param($stmt, "ssii", $product_name, $image_filename, $is_active, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Product updated successfully',
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

function deleteProduct($conn, $id) {
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

function handleImageUpload($file) {
    $upload_dir = '../uploads/products/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
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
    
    return false;
}

function handleBase64Image($base64_string) {
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
?>