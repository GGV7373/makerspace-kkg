<?php
// API for managing admin accounts (list, create, delete)
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT id, full_name, email, role, is_active, created_at FROM admins ORDER BY id ASC";
    $res = $conn->query($query);
    if (!$res) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error: ' . $conn->error]);
        exit;
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$row['id'],
            'fullName' => $row['full_name'],
            'username' => $row['email'],
            'role' => $row['role'],
            'isActive' => (bool)$row['is_active'],
            'createdAt' => $row['created_at']
        ];
    }
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $email = trim($payload['username'] ?? $payload['email'] ?? '');
    $fullName = trim($payload['fullName'] ?? $payload['full_name'] ?? '');
    $password = $payload['password'] ?? '';
    $role = in_array($payload['role'] ?? 'INVENTORY_ADMIN', ['HEAD_ADMIN','INVENTORY_ADMIN']) ? ($payload['role'] ?? 'INVENTORY_ADMIN') : 'INVENTORY_ADMIN';

    if (!$email || !$fullName || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Check for existing email
    $chk = $conn->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already exists']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admins (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'DB prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('ssss', $fullName, $email, $hash, $role);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'DB insert failed: ' . $stmt->error]);
        exit;
    }

    $newId = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => (int)$newId, 'username' => $email, 'fullName' => $fullName, 'role' => $role]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid id']);
        exit;
    }
    // Prevent deleting first superadmin (id=1)
    if ($id === 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete default admin']);
        exit;
    }

    $stmt = $conn->prepare('DELETE FROM admins WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'DB delete failed: ' . $stmt->error]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

?>
