<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
$name = $_SESSION['user_name'];

$sql = "SELECT sr.*, 
               u_from.name AS from_name, u_to.name AS to_name
        FROM swap_requests sr
        JOIN users u_from ON sr.from_user_id = u_from.id
        JOIN users u_to ON sr.to_user_id = u_to.id
        WHERE sr.from_user_id = ? OR sr.to_user_id = ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Request History - Skill Swap</title>
<style>
  body {
    font-family: 'Montserrat', sans-serif;
    background: #121212;
    color: #eee;
    padding: 40px;
  }
  h1 {
    margin-bottom: 30px;
    color: #00e0ff;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    background: #222;
    border-radius: 10px;
    overflow: hidden;
  }
  th, td {
    padding: 15px 20px;
    text-align: left;
  }
  th {
    background: #333;
    color: #00e0ff;
  }
  tr:nth-child(even) {
    background: #1a1a1a;
  }
  tr:hover {
    background: #333;
  }
  .status {
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 15px;
    display: inline-block;
  }
  .accepted {
    background: #36d1dc;
    color: #000;
  }
  .declined {
    background: #ff6b6b;
    color: #fff;
  }
  .pending {
    background: #f0ad4e;
    color: #000;
  }
  .btn-message {
    background: #5b86e5;
    border: none;
    padding: 8px 14px;
    border-radius: 20px;
    color: white;
    cursor: pointer;
    text-decoration: none;
  }
  .btn-message:hover {
    background: #486fd1;
  }
</style>
</head>
<body>

<h1>Swap Request History for <?= htmlspecialchars($name) ?></h1>

<table>
  <thead>
    <tr>
      <th>From</th>
      <th>To</th>
      <th>Skill to Learn</th>
      <th>Skill to Teach</th>
      <th>Location</th>
      <th>Proposed Date/Time</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($result->num_rows === 0): ?>
      <tr><td colspan="8" style="text-align:center;">No request history found.</td></tr>
    <?php else: ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['from_name']) ?></td>
          <td><?= htmlspecialchars($row['to_name']) ?></td>
          <td><?= htmlspecialchars($row['skill_to_learn']) ?></td>
          <td><?= htmlspecialchars($row['skill_to_teach']) ?></td>
          <td><?= htmlspecialchars($row['location']) ?></td>
          <td><?= date('M d, Y h:i A', strtotime($row['proposed_datetime'])) ?></td>
          <td>
            <?php 
              $status_class = strtolower($row['status']);
              if (!in_array($status_class, ['accepted', 'declined', 'pending'])) {
                  $status_class = 'pending';
              }
            ?>
            <span class="status <?= $status_class ?>"><?= ucfirst($row['status']) ?></span>
          </td>
          <td>
            <?php if (strtolower($row['status']) === 'accepted'): ?>
              <?php
                $other_user_id = ($row['from_user_id'] == $user_id) ? $row['to_user_id'] : $row['from_user_id'];
              ?>
              <a class="btn-message" href="messages.php?user_id=<?= $other_user_id ?>">Message</a>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php endif; ?>
  </tbody>
</table>

</body>
</html>
