<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if (!$request_id) {
    echo "Invalid request ID.";
    exit;
}

$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'skillswap';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Handle Accept/Decline form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['Accepted', 'Declined'])) {
    $action = $_POST['action'];
    $stmt = $conn->prepare("UPDATE swap_requests SET status = ? WHERE id = ? AND to_user_id = ?");
    $stmt->bind_param("sii", $action, $request_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $status_message = "Request has been $action.";
}

// Fetch the swap request details
$stmt = $conn->prepare("SELECT sr.*, u_from.name AS from_name, u_to.name AS to_name, u_from.email AS from_email, u_to.email AS to_email FROM swap_requests sr JOIN users u_from ON sr.from_user_id = u_from.id JOIN users u_to ON sr.to_user_id = u_to.id WHERE sr.id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Request not found.";
    exit;
}
$request = $result->fetch_assoc();
$stmt->close();

if ($request['to_user_id'] !== $user_id && $request['from_user_id'] !== $user_id) {
    echo "You are not authorized to view this request.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Swap Request Details</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; background: #f0f2f5; }
    .container { max-width: 650px; margin: auto; background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
    h2 { margin-bottom: 1rem; color: #2d2d2d; }
    p { margin: 0.5rem 0; }
    strong { color: #333; }
    .status {
      margin-top: 1rem;
      font-weight: bold;
      color: <?= $request['status'] === 'Accepted' ? '#36d1dc' : ($request['status'] === 'Declined' ? '#ff6b6b' : '#666') ?>;
    }
    .actions {
      margin-top: 1.5rem;
    }
    .actions form {
      display: inline-block;
      margin-right: 10px;
    }
    .actions button {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      color: #fff;
    }
    .accept-btn { background: #28a745; }
    .decline-btn { background: #dc3545; }
    .message-box {
      margin-top: 15px;
      padding: 10px;
      background: #e0ffe0;
      border: 1px solid #70db70;
      border-radius: 5px;
      color: #2d662d;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Skill Swap Request Details</h2>
    <?php if (isset($status_message)): ?>
      <div class="message-box"><?= htmlspecialchars($status_message) ?></div>
    <?php endif; ?>
    <p><strong>From:</strong> <?= htmlspecialchars($request['from_name']) ?> (<?= htmlspecialchars($request['from_email']) ?>)</p>
    <p><strong>To:</strong> <?= htmlspecialchars($request['to_name']) ?> (<?= htmlspecialchars($request['to_email']) ?>)</p>
    <p><strong>Skill to Learn:</strong> <?= htmlspecialchars($request['skill_to_learn']) ?></p>
    <p><strong>Skill to Teach:</strong> <?= htmlspecialchars($request['skill_to_teach']) ?></p>
    <p><strong>Proposed Date & Time:</strong> <?= date('M d, Y h:i A', strtotime($request['proposed_datetime'])) ?></p>
    <p><strong>Location:</strong> <?= htmlspecialchars($request['location']) ?></p>
    <p><strong>Message:</strong></p>
    <p style="margin-left: 1rem; font-style: italic;"><?= nl2br(htmlspecialchars($request['message'] ?: 'No message provided')) ?></p>
    <p class="status">Status: <?= htmlspecialchars($request['status']) ?></p>

    <?php if ($user_id === intval($request['to_user_id']) && $request['status'] === 'Pending'): ?>
    <div class="actions">
      <form method="post">
        <input type="hidden" name="action" value="Accepted" />
        <button type="submit" class="accept-btn">Accept</button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="Declined" />
        <button type="submit" class="decline-btn">Decline</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
