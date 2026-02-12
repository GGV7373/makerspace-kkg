<?php
// Error handling for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json');

// GET /api/manual.php?id=1 - Fetch manual for a product
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        exit;
    }

    $query = "SELECT id, name, manual_content FROM products WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
        exit;
    }
    
    $product = $result->fetch_assoc();
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'id' => (int)$product['id'],
        'name' => $product['name'],
        'content' => $product['manual_content'] ?? '<p>Ingen bruksanvisning opprettet enda</p>'
    ]);
    exit;
}

// PUT /api/manual.php?id=1 - Update manual content
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data || !isset($data['manual_content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Manual content required']);
        exit;
    }
    
    $manual_content = $data['manual_content'];
    
    $query = "UPDATE products SET manual_content = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('si', $manual_content, $productId);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update manual: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Manual updated successfully']);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
$conn->close();
?>
