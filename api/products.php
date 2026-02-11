<?php
require_once 'config.php';

header('Content-Type: application/json');

// GET /api/products.php - Fetch all products
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT id, name, description, unit, sku FROM products WHERE is_active = TRUE ORDER BY id";
    $result = $conn->query($query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
        exit;
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'sku' => $row['sku'],
            'unit' => $row['unit'],
            'img' => 'https://via.placeholder.com/320x220?text=' . urlencode($row['name']),
            'manual' => strtolower(str_replace(' ', '-', $row['name'])) . '.html'
        ];
    }

    http_response_code(200);
    echo json_encode($products);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
$conn->close();
?>
