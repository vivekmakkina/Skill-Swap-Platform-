<?php
// DB connection
$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'skillswap';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session and get logged in user
session_start();
$loggedInUserId = $_SESSION['user_id'] ?? 1;

// Handle "Complete Event" attendance and credit earning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_event_id'])) {
    $eventId = intval($_POST['complete_event_id']);

    // Fetch start_time for attendance
    $stmtGet = $conn->prepare("SELECT start_time FROM event_attendance WHERE event_id = ? AND user_id = ? AND end_time IS NULL");
    $stmtGet->bind_param("ii", $eventId, $loggedInUserId);
    $stmtGet->execute();
    $resultGet = $stmtGet->get_result();

    if ($row = $resultGet->fetch_assoc()) {
        $startTime = new DateTime($row['start_time']);
        $endTime = new DateTime(); // now
        $diffMinutes = floor(($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);
        $creditsEarned = floor($diffMinutes / 30); // 1 credit per 30 min

        if ($creditsEarned > 0) {
            // Update event_attendance with end_time and credits_earned
            $stmtUpdate = $conn->prepare("UPDATE event_attendance SET end_time = ?, credits_earned = ? WHERE event_id = ? AND user_id = ?");
            $nowStr = $endTime->format('Y-m-d H:i:s');
            $stmtUpdate->bind_param("siii", $nowStr, $creditsEarned, $eventId, $loggedInUserId);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            // Add credits to user's credits_balance
            $stmtAddCredit = $conn->prepare("UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?");
            $stmtAddCredit->bind_param("ii", $creditsEarned, $loggedInUserId);
            $stmtAddCredit->execute();
            $stmtAddCredit->close();
        }
    }

    $stmtGet->close();

    // Redirect to refresh page and avoid resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get swap friends
function getSwapFriends($conn, $userId) {
    $friends = [];
    $sql = "SELECT 
                CASE 
                    WHEN from_user_id = ? THEN to_user_id 
                    ELSE from_user_id 
                END AS friend_id,
                u.name
            FROM swap_requests sr
            JOIN users u ON u.id = CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END
            WHERE (from_user_id = ? OR to_user_id = ?) AND status='accepted'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $userId, $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $friends[] = $row;
    }
    $stmt->close();
    return $friends;
}

// Event form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $datetime = $_POST['datetime'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $invitees = $_POST['invitees'] ?? [];

    if (!$title) $errors[] = "Event title is required.";
    if (!$type || !in_array($type, ['online', 'offline'])) $errors[] = "Select a valid event type.";
    if (!$datetime) $errors[] = "Event date & time is required.";
    if ($type === 'offline' && !$location) $errors[] = "Location is required for offline events.";

    $swapFriends = array_column(getSwapFriends($conn, $loggedInUserId), 'friend_id');
    foreach ($invitees as $inv) {
        if (!in_array(intval($inv), $swapFriends)) {
            $errors[] = "Invalid invitee selected.";
            break;
        }
    }

    $coverImagePath = null;
    if (!empty($_FILES['cover_image']['name'])) {
        $targetDir = "uploads/event_covers/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $fileName = basename($_FILES["cover_image"]["name"]);
        $targetFile = $targetDir . time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        } else {
            if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $targetFile)) {
                $coverImagePath = $targetFile;
            } else {
                $errors[] = "Failed to upload cover image.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO events (creator_id, title, type, datetime, location, description, cover_image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $loggedInUserId, $title, $type, $datetime, $location, $description, $coverImagePath);
        if ($stmt->execute()) {
            $eventId = $stmt->insert_id;
            $stmt->close();

            if (!empty($invitees)) {
                $stmtInv = $conn->prepare("INSERT INTO event_invites (event_id, user_id) VALUES (?, ?)");
                foreach ($invitees as $invUserId) {
                    $invUserId = intval($invUserId);
                    $stmtInv->bind_param("ii", $eventId, $invUserId);
                    $stmtInv->execute();
                }
                $stmtInv->close();
            }

            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } else {
            $errors[] = "Failed to create event. Try again.";
        }
    }
}

// RSVP Handling with Attendance Timer Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp_event_id']) && isset($_POST['rsvp_status'])) {
    $eventId = intval($_POST['rsvp_event_id']);
    $rsvpStatus = $_POST['rsvp_status'];
    if (in_array($rsvpStatus, ['going', 'interested'])) {
        $stmt = $conn->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status=?");
        $stmt->bind_param("iiss", $eventId, $loggedInUserId, $rsvpStatus, $rsvpStatus);
        $stmt->execute();
        $stmt->close();

        // Attendance timer logic:
        if ($rsvpStatus === 'going') {
            // Check if attendance already exists
            $stmtCheck = $conn->prepare("SELECT id FROM event_attendance WHERE event_id = ? AND user_id = ?");
            $stmtCheck->bind_param("ii", $eventId, $loggedInUserId);
            $stmtCheck->execute();
            $stmtCheck->store_result();

            if ($stmtCheck->num_rows === 0) {
                // Insert start_time = now
                $now = date('Y-m-d H:i:s');
                $stmtInsert = $conn->prepare("INSERT INTO event_attendance (event_id, user_id, start_time) VALUES (?, ?, ?)");
                $stmtInsert->bind_param("iis", $eventId, $loggedInUserId, $now);
                $stmtInsert->execute();
                $stmtInsert->close();
            }
            $stmtCheck->close();
        } else {
            // If user changed from going to interested, remove attendance record if exists
            $stmtDel = $conn->prepare("DELETE FROM event_attendance WHERE event_id = ? AND user_id = ?");
            $stmtDel->bind_param("ii", $eventId, $loggedInUserId);
            $stmtDel->execute();
            $stmtDel->close();
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch upcoming events for this user (invited or created)
$sqlEvents = "
    SELECT e.*, u.name AS creator_name,
    (SELECT status FROM event_rsvps WHERE event_id = e.id AND user_id = ?) AS user_rsvp_status,
    (SELECT end_time IS NOT NULL FROM event_attendance WHERE event_id = e.id AND user_id = ?) AS event_completed
    FROM events e
    LEFT JOIN users u ON e.creator_id = u.id
    WHERE e.creator_id = ? OR e.id IN (SELECT event_id FROM event_invites WHERE user_id = ?)
    ORDER BY e.datetime ASC
";
$stmtEvents = $conn->prepare($sqlEvents);
$stmtEvents->bind_param("iiii", $loggedInUserId, $loggedInUserId, $loggedInUserId, $loggedInUserId);
$stmtEvents->execute();
$resultEvents = $stmtEvents->get_result();

// Fetch swap friends for invite dropdown
$swapFriends = getSwapFriends($conn, $loggedInUserId);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Events Page</title>
<style>
  :root {
    --primary: #4a90e2;
    --secondary: #7b61ff;
    --background: #f2f6ff;
    --card-bg: #ffffff;
    --accent: #e1ecff;
    --success: #28a745;
    --error: #dc3545;
    --text-dark: #2d2d2d;
    --text-light: #666;
  }

  body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--background);
    color: var(--text-dark);
  }

  .container {
    max-width: 1000px;
    margin: 40px auto;
    background: var(--card-bg);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
  }

  h1, h2 {
    color: var(--primary);
    margin-bottom: 16px;
  }

  .error {
    color: var(--error);
    font-weight: 600;
  }

  .success {
    color: var(--success);
    font-weight: 600;
  }

  .event-card {
    border: 1px solid var(--accent);
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 14px;
    background: #fff;
    transition: box-shadow 0.3s ease;
  }

  .event-card:hover {
    box-shadow: 0 4px 15px rgba(74, 144, 226, 0.15);
  }

  .event-card img {
    max-width: 100%;
    height: auto;
    margin-top: 12px;
    border-radius: 12px;
  }

  label {
    display: block;
    margin: 12px 0 6px;
    font-weight: bold;
  }

  input[type="text"],
  input[type="datetime-local"],
  select,
  textarea {
    width: 100%;
    padding: 10px 12px;
    margin-bottom: 12px;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 15px;
    transition: border 0.3s ease;
  }

  input:focus,
  textarea:focus,
  select:focus {
    border-color: var(--primary);
    outline: none;
  }

  button {
    background: var(--primary);
    color: #fff;
    padding: 10px 20px;
    border-radius: 12px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s ease;
    margin-top: 10px;
  }

  button:hover {
    background: var(--secondary);
  }

  .invitee-checkbox {
    margin-right: 8px;
  }

  .section {
    margin-top: 30px;
  }

  .rsvp-buttons button {
    margin-right: 10px;
    background-color: var(--accent);
    color: var(--primary);
  }

  .rsvp-buttons button.selected {
    background-color: var(--primary);
    color: white;
  }

  .complete-btn {
    background-color: var(--success);
  }

  .rsvp-buttons,
  .event-controls {
    margin-top: 12px;
  }

  .form-section {
    margin-bottom: 40px;
  }

</style>

<body>
<div class="container">
<h1>Create New Event</h1>

<?php if (!empty($errors)): ?>
  <div class="error">
    <ul>
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php elseif (isset($_GET['success'])): ?>
  <div class="success">Event created successfully!</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <label for="title">Event Title:</label>
  <input type="text" id="title" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" />

  <label for="type">Event Type:</label>
  <select id="type" name="type" required onchange="toggleLocation()">
    <option value="">Select type</option>
    <option value="online" <?= (($_POST['type'] ?? '') === 'online') ? 'selected' : '' ?>>Online</option>
    <option value="offline" <?= (($_POST['type'] ?? '') === 'offline') ? 'selected' : '' ?>>Offline</option>
  </select>

  <div id="locationDiv" style="display: <?= (($_POST['type'] ?? '') === 'offline') ? 'block' : 'none' ?>;">
    <label for="location">Location (for offline events):</label>
    <input type="text" id="location" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" />
  </div>

  <label for="datetime">Event Date & Time:</label>
  <input type="datetime-local" id="datetime" name="datetime" required value="<?= htmlspecialchars($_POST['datetime'] ?? '') ?>" />

  <label for="description">Description:</label>
  <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

  <label for="cover_image">Cover Image (optional):</label>
  <input type="file" id="cover_image" name="cover_image" accept="image/*" />

  <label for="invitees">Invite Swap Friends:</label>
  <select id="invitees" name="invitees[]" multiple class="invitees">
    <?php foreach ($swapFriends as $friend): ?>
      <option value="<?= $friend['friend_id'] ?>" <?= (isset($_POST['invitees']) && in_array($friend['friend_id'], $_POST['invitees'])) ? 'selected' : '' ?>>
        <?= htmlspecialchars($friend['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <button type="submit" name="create_event">Create Event</button>
</form>

<hr />

<h2>Upcoming Events</h2>

<?php if ($resultEvents->num_rows === 0): ?>
  <p>No upcoming events found.</p>
<?php else: ?>
  <?php while ($event = $resultEvents->fetch_assoc()): ?>
    <div class="event-card">
      <h3><?= htmlspecialchars($event['title']) ?></h3>
      <p><strong>By:</strong> <?= htmlspecialchars($event['creator_name']) ?></p>
      <p><strong>When:</strong> <?= date("F j, Y, g:i A", strtotime($event['datetime'])) ?></p>
      <?php if ($event['type'] === 'offline'): ?>
        <p><strong>Where:</strong> <?= htmlspecialchars($event['location']) ?></p>
      <?php else: ?>
        <p><strong>Type:</strong> Online Event</p>
      <?php endif; ?>
      <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
      <?php if (!empty($event['cover_image_path'])): ?>
        <img src="<?= htmlspecialchars($event['cover_image_path']) ?>" alt="Cover Image" />
      <?php endif; ?>

      <p><strong>Your RSVP status:</strong> <?= htmlspecialchars($event['user_rsvp_status'] ?? 'None') ?></p>

      <!-- RSVP form -->
      <form method="post" style="margin-top: 10px;">
        <input type="hidden" name="rsvp_event_id" value="<?= $event['id'] ?>" />
        <label>
          <input type="radio" name="rsvp_status" value="going" <?= ($event['user_rsvp_status'] === 'going') ? 'checked' : '' ?> /> Going
        </label>
        <label>
          <input type="radio" name="rsvp_status" value="interested" <?= ($event['user_rsvp_status'] === 'interested') ? 'checked' : '' ?> /> Interested
        </label>
        <button type="submit">Set RSVP</button>
      </form>

      <!-- Show Complete Event button only if RSVP=going and event not completed -->
      <?php if ($event['user_rsvp_status'] === 'going' && !$event['event_completed']): ?>
        <form method="post" style="margin-top: 10px;">
          <input type="hidden" name="complete_event_id" value="<?= $event['id'] ?>" />
          <button type="submit">Complete Event (Earn Credits)</button>
        </form>
      <?php elseif ($event['event_completed']): ?>
        <p><em>Event completed. Credits earned!</em></p>
      <?php endif; ?>
    </div>
  <?php endwhile; ?>
<?php endif; ?>

</div>

<script>
function toggleLocation() {
  const typeSelect = document.getElementById('type');
  const locationDiv = document.getElementById('locationDiv');
  if (typeSelect.value === 'offline') {
    locationDiv.style.display = 'block';
  } else {
    locationDiv.style.display = 'none';
  }
}
</script>

</body>
</html>

<?php
// Close DB connection and statements
$stmtEvents->close();
$conn->close();
?>
