<?php
// Error handling for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json');

// GET /api/products.php - Fetch all products
// GET /api/products.php?id=1 - Fetch single product
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(isset($_GET['id'])) {
        // Single product
        $productId = intval($_GET['id']);
        $query = "SELECT id, sku, name, description, unit, manual_content, image_url, is_active FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if(!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }
        
        $imageUrl = $product['image_url'];
        
        echo json_encode([
            'id' => (int)$product['id'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'description' => $product['description'],
            'unit' => $product['unit'],
            'manual_content' => $product['manual_content'],
            'image_url' => $imageUrl,
            'is_active' => (bool)$product['is_active'],
            'img' => $imageUrl ? $imageUrl : 'https://via.placeholder.com/320x220?text=' . urlencode($product['name']),
            'manual' => strtolower(str_replace(' ', '-', $product['name'])) . '.html'
        ]);
    } else {
        // All products
        $query = "SELECT id, sku, name, description, unit, image_url, is_active FROM products ORDER BY id";
        $result = $conn->query($query);

        if (!$result) {
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
            exit;
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $imageUrl = $row['image_url'];
            
            $products[] = [
                'id' => (int)$row['id'],
                'sku' => $row['sku'],
                'name' => $row['name'],
                'description' => $row['description'],
                'unit' => $row['unit'],
                'image_url' => $imageUrl,
                'is_active' => (bool)$row['is_active'],
                'img' => $imageUrl ? $imageUrl : 'https://via.placeholder.com/320x220?text=' . urlencode($row['name']),
                'manual' => strtolower(str_replace(' ', '-', $row['name'])) . '.html'
            ];
        }

        http_response_code(200);
        echo json_encode($products);
    }
    exit;
}

// POST /api/products.php - Create new product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required', 'data' => $data]);
        exit;
    }
    
    $sku = !empty($data['sku']) ? $data['sku'] : null;
    $name = $data['name'];
    $description = $data['description'] ?? '';
    $unit = $data['unit'] ?? 'unit';
    $manual_content = $data['manual_content'] ?? '<p>Ingen bruksanvisning opprettet enda</p>';
    $is_active = isset($data['is_active']) ? (bool)$data['is_active'] : true;
    $is_active_int = $is_active ? 1 : 0;
    
    $query = "INSERT INTO products (sku, name, description, unit, manual_content, is_active) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('sssssi', $sku, $name, $description, $unit, $manual_content, $is_active_int);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create product: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(201);
    echo json_encode(['id' => $conn->insert_id, 'message' => 'Product created successfully']);
    exit;
}

// PUT /api/products.php?id=1 - Update product
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit;
    }
    
    // Build update query dynamically based on provided fields
    $updates = [];
    $params = [];
    $types = '';
    
    if(isset($data['name'])) { $updates[] = 'name = ?'; $params[] = $data['name']; $types .= 's'; }
    if(isset($data['sku'])) { $updates[] = 'sku = ?'; $params[] = $data['sku']; $types .= 's'; }
    if(isset($data['description'])) { $updates[] = 'description = ?'; $params[] = $data['description']; $types .= 's'; }
    if(isset($data['unit'])) { $updates[] = 'unit = ?'; $params[] = $data['unit']; $types .= 's'; }
    if(isset($data['manual_content'])) { $updates[] = 'manual_content = ?'; $params[] = $data['manual_content']; $types .= 's'; }
    if(isset($data['image_url'])) { $updates[] = 'image_url = ?'; $params[] = $data['image_url']; $types .= 's'; }
    if(isset($data['is_active'])) { $updates[] = 'is_active = ?'; $params[] = ($data['is_active'] ? 1 : 0); $types .= 'i'; }
    
    if(empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $params[] = $productId;
    $types .= 'i';
    
    $query = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$params);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update product: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Product updated successfully']);
    exit;
}

// DELETE /api/products.php?id=1 - Delete product
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$productId) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID required']);
        exit;
    }
    
    $query = "DELETE FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $productId);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete product: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Product deleted successfully']);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
$conn->close();
?>
