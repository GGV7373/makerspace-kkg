<?php
// Error handling for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config.php';

header('Content-Type: application/json');

// GET /api/reports.php - Fetch all reports
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = "SELECT id, reporter_name, about_text, is_important, status, created_at, updated_at FROM reports ORDER BY created_at DESC";
    $result = $conn->query($query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
        exit;
    }

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'id' => (int)$row['id'],
            'title' => $row['about_text'],
            'desc' => $row['about_text'],
            'reporter_name' => $row['reporter_name'],
            'is_important' => (bool)$row['is_important'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'from' => 'user'
        ];
    }

    http_response_code(200);
    echo json_encode($reports);
    exit;
}

// POST /api/reports.php - Create new report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data || !isset($data['about_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Report text required']);
        exit;
    }
    
    $reporter_name = $data['reporter_name'] ?? 'Anonym';
    $about_text = $data['about_text'];
    $is_important = isset($data['is_important']) ? (bool)$data['is_important'] : false;
    $is_important_int = $is_important ? 1 : 0;
    $status = $data['status'] ?? 'NEW';
    
    $query = "INSERT INTO reports (reporter_name, about_text, is_important, status, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('ssss', $reporter_name, $about_text, $is_important_int, $status);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create report: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(201);
    echo json_encode(['id' => $conn->insert_id, 'message' => 'Report created successfully']);
    exit;
}

// PUT /api/reports.php?id=1 - Update report status
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $reportId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$reportId) {
        http_response_code(400);
        echo json_encode(['error' => 'Report ID required']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Status required']);
        exit;
    }
    
    $status = $data['status'];
    
    $query = 'UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?';
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('si', $status, $reportId);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update report: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Report updated successfully']);
    exit;
}

// DELETE /api/reports.php?id=1 - Delete report
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $reportId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if(!$reportId) {
        http_response_code(400);
        echo json_encode(['error' => 'Report ID required']);
        exit;
    }
    
    $query = "DELETE FROM reports WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if(!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $reportId);
    
    if(!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete report: ' . $stmt->error]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['message' => 'Report deleted successfully']);
    exit;
}

// Unsupported method
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
$conn->close();
?>
