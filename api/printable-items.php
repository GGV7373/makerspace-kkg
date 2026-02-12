<?php
// API for managing printable items and their inventory (t-shirts, hoodies, etc.)

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// GET - Fetch all printable items with inventory
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        // Single item with inventory details
        $itemId = intval($_GET['id']);
        
        $query = "SELECT id, name, description, category, image_url, price, is_active FROM printable_items WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error (table may not exist): ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            exit;
        }
        
        // Get inventory for this item
        $invQuery = "SELECT id, size, color, quantity, reorder_level FROM printable_inventory WHERE item_id = ?";
        $invStmt = $conn->prepare($invQuery);
        $invStmt->bind_param('i', $itemId);
        $invStmt->execute();
        $invResult = $invStmt->get_result();
        
        $inventory = [];
        while ($inv = $invResult->fetch_assoc()) {
            $inventory[] = $inv;
        }
        
        echo json_encode([
            'id' => (int)$item['id'],
            'name' => $item['name'],
            'description' => $item['description'],
            'category' => $item['category'],
            'image_url' => $item['image_url'],
            'price' => (float)$item['price'],
            'is_active' => (bool)$item['is_active'],
            'inventory' => $inventory
        ]);
    } else {
        // All items with quick inventory summary
        $query = "SELECT id, name, description, category, image_url, price, is_active FROM printable_items ORDER BY category, name";
        $result = $conn->query($query);
        
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Database error: ' . $conn->error,
                'hint' => 'Make sure the printable_items table exists. Run migration 002_add_printable_items_inventory.sql'
            ]);
            exit;
        }
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // Get total inventory for this item
            $invQuery = "SELECT SUM(quantity) as total_qty, COUNT(*) as variants FROM printable_inventory WHERE item_id = ?";
            $invStmt = $conn->prepare($invQuery);
            $invStmt->bind_param('i', $row['id']);
            $invStmt->execute();
            $invData = $invStmt->get_result()->fetch_assoc();
            
            $items[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category'],
                'image_url' => $row['image_url'],
                'price' => (float)$row['price'],
                'is_active' => (bool)$row['is_active'],
                'total_quantity' => (int)($invData['total_qty'] ?? 0),
                'variants' => (int)($invData['variants'] ?? 0)
            ];
        }
        
        http_response_code(200);
        echo json_encode($items);
    }
    exit;
}

// POST - Create new printable item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required field: name']);
        exit;
    }
    
    $name = $input['name'];
    $description = $input['description'] ?? null;
    $category = $input['category'] ?? null;
    $image_url = $input['image_url'] ?? null;
    $price = $input['price'] ?? 0;
    
    $query = "INSERT INTO printable_items (name, description, category, image_url, price) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('ssssd', $name, $description, $category, $image_url, $price);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create item: ' . $stmt->error]);
        exit;
    }
    
    $itemId = $conn->insert_id;
    $stmt->close();
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Item created successfully',
        'id' => $itemId
    ]);
    exit;
}

// PUT - Update printable item
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing item id']);
        exit;
    }
    
    $itemId = intval($_GET['id']);
    
    // Check if item exists
    $checkQuery = "SELECT id FROM printable_items WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $itemId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        exit;
    }
    $checkStmt->close();
    
    // Update item
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['name'])) {
        $updates[] = 'name = ?';
        $params[] = $input['name'];
        $types .= 's';
    }
    if (isset($input['description'])) {
        $updates[] = 'description = ?';
        $params[] = $input['description'];
        $types .= 's';
    }
    if (isset($input['category'])) {
        $updates[] = 'category = ?';
        $params[] = $input['category'];
        $types .= 's';
    }
    if (isset($input['image_url'])) {
        $updates[] = 'image_url = ?';
        $params[] = $input['image_url'];
        $types .= 's';
    }
    if (isset($input['price'])) {
        $updates[] = 'price = ?';
        $params[] = $input['price'];
        $types .= 'd';
    }
    if (isset($input['is_active'])) {
        $updates[] = 'is_active = ?';
        $params[] = $input['is_active'] ? 1 : 0;
        $types .= 'i';
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $params[] = $itemId;
    $types .= 'i';
    
    $query = "UPDATE printable_items SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    // Build parameter array for bind_param
    $bindParams = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update item: ' . $stmt->error]);
        exit;
    }
    
    $stmt->close();
    
    http_response_code(200);
    echo json_encode(['message' => 'Item updated successfully']);
    exit;
}

// DELETE - Delete printable item
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing item id']);
        exit;
    }
    
    $itemId = intval($_GET['id']);
    
    $query = "DELETE FROM printable_items WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $itemId);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete item: ' . $stmt->error]);
        exit;
    }
    
    $stmt->close();
    
    http_response_code(200);
    echo json_encode(['message' => 'Item deleted successfully']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
