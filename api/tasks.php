<?php
// Error handling for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json');

// GET /api/tasks.php - Fetch all tasks
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT id, title, description, status, created_at, updated_at FROM admin_tasks ORDER BY created_at DESC";
    $result = $conn->query($query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
        exit;
    }

    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'desc' => $row['description'],
            'status' => strtolower($row['status']),
            'createdAt' => $row['created_at'],
            'from' => 'secondary'
        ];
    }

    http_response_code(200);
    echo json_encode($tasks);
    exit;
}

// POST /api/tasks.php - Create new task
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data || !isset($data['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title required']);
        exit;
    }
    
    $title = $data['title'];
    $description = $data['description'] ?? '';
    $status = 'OPEN';
    
    $query = "INSERT INTO admin_tasks (title, description, status, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('sss', $title, $description, $status);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create task: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(201);
    echo json_encode(['id' => $conn->insert_id, 'message' => 'Task created successfully']);
    exit;
}

// PUT /api/tasks.php?id=1 - Update task status
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Status required']);
        exit;
    }
    
    $status = strtoupper($data['status']);
    
    $query = 'UPDATE admin_tasks SET status = ?, updated_at = NOW() WHERE id = ?';
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('si', $status, $taskId);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update task: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Task updated successfully']);
    exit;
}

// DELETE /api/tasks.php?id=1 - Delete task
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $taskId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        exit;
    }
    
    $query = "DELETE FROM admin_tasks WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $taskId);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete task: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Task deleted successfully']);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
$conn->close();
?>
