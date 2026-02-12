<?php
// API for managing printable items inventory (quantities, sizes, colors)

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

// GET - Fetch inventory for an item
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['item_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing item_id']);
        exit;
    }
    
    $itemId = intval($_GET['item_id']);
    
    $query = "SELECT id, size, color, quantity, reorder_level FROM printable_inventory WHERE item_id = ? ORDER BY size, color";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $inventory = [];
    while ($row = $result->fetch_assoc()) {
        $inventory[] = [
            'id' => (int)$row['id'],
            'size' => $row['size'],
            'color' => $row['color'],
            'quantity' => (int)$row['quantity'],
            'reorder_level' => (int)$row['reorder_level']
        ];
    }
    
    http_response_code(200);
    echo json_encode($inventory);
    exit;
}

// POST - Add new inventory variant (size + color combination)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['item_id']) || !isset($input['size']) || !isset($input['color'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: item_id, size, color']);
        exit;
    }
    
    $itemId = intval($input['item_id']);
    $size = $input['size'];
    $color = $input['color'];
    $quantity = $input['quantity'] ?? 0;
    $reorder_level = $input['reorder_level'] ?? 10;
    
    // Check item exists
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
    
    $query = "INSERT INTO printable_inventory (item_id, size, color, quantity, reorder_level) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('issii', $itemId, $size, $color, $quantity, $reorder_level);
    
    if (!$stmt->execute()) {
        http_response_code(409);
        echo json_encode(['error' => 'This size/color combination already exists']);
        exit;
    }
    
    $invId = $conn->insert_id;
    $stmt->close();
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Inventory variant created',
        'id' => $invId
    ]);
    exit;
}

// PUT - Update inventory quantity or details
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_GET['inv_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing inv_id']);
        exit;
    }
    
    $invId = intval($_GET['inv_id']);
    
    // Check if inventory exists
    $checkQuery = "SELECT id FROM printable_inventory WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $invId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Inventory not found']);
        exit;
    }
    $checkStmt->close();
    
    // Update inventory
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['quantity'])) {
        $updates[] = 'quantity = ?';
        $params[] = intval($input['quantity']);
        $types .= 'i';
    }
    if (isset($input['reorder_level'])) {
        $updates[] = 'reorder_level = ?';
        $params[] = intval($input['reorder_level']);
        $types .= 'i';
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $params[] = $invId;
    $types .= 'i';
    
    $query = "UPDATE printable_inventory SET " . implode(', ', $updates) . " WHERE id = ?";
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
        echo json_encode(['error' => 'Failed to update inventory: ' . $stmt->error]);
        exit;
    }
    
    $stmt->close();
    
    // Log transaction if quantity was changed
    if (isset($input['quantity']) && isset($input['reason'])) {
        $query = "INSERT INTO printable_transactions (item_id, size, color, qty_change, reason, notes) 
                  SELECT item_id, size, color, ?, ?, ? FROM printable_inventory WHERE id = ?";
        $logStmt = $conn->prepare($query);
        $qtyChange = intval($input['quantity']);
        $reason = $input['reason'];
        $notes = $input['notes'] ?? null;
        $logStmt->bind_param('issi', $qtyChange, $reason, $notes, $invId);
        $logStmt->execute();
        $logStmt->close();
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Inventory updated successfully']);
    exit;
}

// DELETE - Remove inventory variant
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_GET['inv_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing inv_id']);
        exit;
    }
    
    $invId = intval($_GET['inv_id']);
    
    $query = "DELETE FROM printable_inventory WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $invId);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete inventory: ' . $stmt->error]);
        exit;
    }
    
    $stmt->close();
    
    http_response_code(200);
    echo json_encode(['message' => 'Inventory variant deleted']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
