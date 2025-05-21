<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'skillswap';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$loggedInUserId = $_SESSION['user_id'] ?? 0;
if (!$loggedInUserId) {
    header('Location: login.php');
    exit;
}

// Get total credits balance
$stmtBal = $conn->prepare("SELECT credits_balance FROM users WHERE id = ?");
$stmtBal->bind_param("i", $loggedInUserId);
$stmtBal->execute();
$stmtBal->bind_result($creditsBalance);
$stmtBal->fetch();
$stmtBal->close();

// Get transactions history
$stmtTx = $conn->prepare("SELECT event_id, type, credits, description, created_at FROM credits_transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmtTx->bind_param("i", $loggedInUserId);
$stmtTx->execute();
$resultTx = $stmtTx->get_result();

?>
<!DOCTYPE html>
<html>
<head><title>Your Wallet</title></head>
<body>
<h1>Your Credits Wallet</h1>
<p><strong>Current Balance: <?= $creditsBalance ?></strong></p>
<table border="1" cellpadding="5" cellspacing="0">
<thead>
  <tr>
    <th>Date</th><th>Event ID</th><th>Type</th><th>Credits</th><th>Description</th>
  </tr>
</thead>
<tbody>
<?php while ($row = $resultTx->fetch_assoc()) : ?>
  <tr>
    <td><?= htmlspecialchars($row['created_at']) ?></td>
    <td><?= htmlspecialchars($row['event_id'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['type']) ?></td>
    <td><?= htmlspecialchars($row['credits']) ?></td>
    <td><?= htmlspecialchars($row['description']) ?></td>
  </tr>
<?php endwhile; ?>
</tbody>
</table>
</body>
</html>
<?php
$stmtTx->close();
?>
