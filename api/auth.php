<?php
// Simple auth API to validate admin credentials against DB
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$username = trim($payload['username'] ?? '');
$password = $payload['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing credentials']);
    exit;
}

$stmt = $conn->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM admins WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

if (!password_verify($password, $row['password_hash'])){
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

if (!(bool)$row['is_active']){
    http_response_code(403);
    echo json_encode(['error' => 'Account disabled']);
    exit;
}

// Return account info (do not include password_hash)
echo json_encode([
    'id' => (int)$row['id'],
    'username' => $row['email'],
    'fullName' => $row['full_name'],
    'role' => $row['role']
]);
exit;
?>
