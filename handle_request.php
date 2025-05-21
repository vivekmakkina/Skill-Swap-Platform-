<?php
session_start();
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Database connection
$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'skillswap';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit;
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate parameters
if (!$request_id || !in_array($action, ['accept', 'decline'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check if the request exists, is for the current user, and is still pending
$stmt = $conn->prepare("SELECT status FROM swap_requests WHERE id = ? AND to_user_id = ?");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Request not found or not authorized']);
    $stmt->close();
    exit;
}

$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

if ($status !== 'Pending') {
    echo json_encode(['success' => false, 'message' => 'Request already processed']);
    exit;
}

// Determine new status
$new_status = $action === 'accept' ? 'Accepted' : 'Declined';

// Update the request status in DB
$update_stmt = $conn->prepare("UPDATE swap_requests SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_status, $request_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request ' . strtolower($new_status)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}

$update_stmt->close();
$conn->close();
