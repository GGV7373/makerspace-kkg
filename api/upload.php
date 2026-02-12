<?php
// Start output buffering
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Increase max input size for large image data URLs
ini_set('post_max_size', '50M');
ini_set('upload_max_filesize', '50M');

// Include config
require_once __DIR__ . '/../config.php';

// Set JSON header IMMEDIATELY
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit;
}

// Get input data from POST - read raw POST data
$rawInput = file_get_contents('php://input');

if (empty($rawInput)) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => 'Empty request body']);
    ob_end_flush();
    exit;
}

// Decode JSON
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    ob_end_flush();
    exit;
}

$productId = isset($input['product_id']) ? intval($input['product_id']) : 0;
$imageData = isset($input['image_data']) ? $input['image_data'] : null;

if (!$productId) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => 'Missing product_id']);
    ob_end_flush();
    exit;
}

if (!$imageData) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => 'Missing image_data', 'received_keys' => array_keys($input)]);
    ob_end_flush();
    exit;
}

// Validate that it's a valid data URL
if (strpos($imageData, 'data:image/') !== 0) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => 'Invalid image data format. Must start with "data:image/"']);
    ob_end_flush();
    exit;
}

// Validate product exists
$checkQuery = "SELECT id FROM products WHERE id = ?";
$checkStmt = $conn->prepare($checkQuery);
if (!$checkStmt) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    ob_end_flush();
    exit;
}

$checkStmt->bind_param('i', $productId);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows === 0) {
    http_response_code(404);
    ob_end_clean();
    echo json_encode(['error' => 'Product not found']);
    ob_end_flush();
    exit;
}
$checkStmt->close();

// Update database with the image data URL
$query = "UPDATE products SET image_url = ? WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    ob_end_flush();
    exit;
}

// Bind with correct types: s for string (image_url), i for integer (id)
$stmt->bind_param('si', $imageData, $productId);

if (!$stmt->execute()) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => 'Failed to update product: ' . $stmt->error]);
    ob_end_flush();
    exit;
}

$stmt->close();
$conn->close();

// Success
http_response_code(200);
ob_end_clean();
echo json_encode([
    'message' => 'Image uploaded successfully',
    'product_id' => $productId,
    'image_data_length' => strlen($imageData)
]);
ob_end_flush();
exit;
?>
