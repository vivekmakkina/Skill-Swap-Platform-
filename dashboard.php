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
$email = $_SESSION['user_email'] ?? 'Not Available';

// Fetch user profile info
$stmt = $conn->prepare("SELECT profile_photo, headline, skills_to_teach, skills_to_learn FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($profile_photo, $headline, $skills_to_teach, $skills_to_learn);
$stmt->fetch();
$stmt->close();

$offeredSkills = array_filter(array_map('trim', explode(',', $skills_to_teach)));
$requestedSkills = array_filter(array_map('trim', explode(',', $skills_to_learn)));

$image_src = !empty($profile_photo) ? $profile_photo : "https://i.pravatar.cc/100?u=$user_id";
$headline = $headline ?: "No headline provided";

// Fetch credits balance
$creditsBalance = 0;
$credits_stmt = $conn->prepare("SELECT credits_balance FROM users WHERE id = ?");
$credits_stmt->bind_param("i", $user_id);
$credits_stmt->execute();
$credits_stmt->bind_result($creditsBalance);
$credits_stmt->fetch();
$credits_stmt->close();


// Fetch incoming swap requests
$notif_sql = "SELECT sr.*, u.name FROM swap_requests sr JOIN users u ON sr.from_user_id = u.id WHERE sr.to_user_id = ? AND sr.status = 'Pending' ORDER BY sr.created_at DESC";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
$notif_count = $notifications->num_rows;
$msg_sql = "SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0";
$msg_stmt = $conn->prepare($msg_sql);
$msg_stmt->bind_param("i", $user_id);
$msg_stmt->execute();
$msg_stmt->bind_result($unread_msg_count);
$msg_stmt->fetch();
$msg_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>User Dashboard - Skill Swap</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Montserrat', sans-serif;
      background: linear-gradient(to right, #667eea, #764ba2);
      min-height: 100vh;
      color: #fff;
      overflow-x: hidden;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
      animation: fadeDown 1s ease-out;
      position: relative;
      z-index: 10;
    }

    .welcome {
      font-size: 1.2rem;
      font-weight: 500;
    }

    .logo {
      font-size: 1.5rem;
      font-weight: 700;
      background: linear-gradient(45deg, #00e0ff, #ffffff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .logout-btn {
      padding: 8px 18px;
      background: rgba(255,255,255,0.1);
      color: #fff;
      border: none;
      border-radius: 20px;
      cursor: pointer;
      transition: 0.3s ease;
    }

    .logout-btn:hover {
      background: rgba(255,255,255,0.2);
    }

    .notification-bell {
      position: relative;
      margin-right: 20px;
      cursor: pointer;
      font-size: 22px;
    }

    .notification-bell span {
      position: absolute;
      top: -8px;
      right: -8px;
      background: red;
      color: white;
      font-size: 12px;
      padding: 3px 6px;
      border-radius: 50%;
    }

    .notification-dropdown {
      position: absolute;
      top: 70px;
      right: 120px;
      background: white;
      color: black;
      width: 300px;
      max-height: 300px;
      overflow-y: auto;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
      display: none;
      z-index: 9999;
    }

    .notification-dropdown.active {
      display: block;
    }

    .notification-dropdown h4 {
      background: #764ba2;
      color: white;
      padding: 12px;
      margin: 0;
      border-radius: 12px 12px 0 0;
    }

    .notification-item {
      padding: 10px 15px;
      border-bottom: 1px solid #ddd;
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    .notification-item strong {
      color: #333;
    }

    @keyframes fadeDown {
      from { opacity: 0; transform: translateY(-30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 20px;
      position: relative;
      z-index: 1;
    }

    .section {
      background: rgba(255, 255, 255, 0.08);
      margin-bottom: 30px;
      border-radius: 20px;
      padding: 30px;
      backdrop-filter: blur(12px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.25);
      animation: slideUp 0.8s ease-in-out;
    }

    @keyframes slideUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .section h2 {
      font-size: 1.6rem;
      margin-bottom: 15px;
      background: linear-gradient(to right, #00e0ff, #ffffff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: glow 2s infinite alternate;
    }

    @keyframes glow {
      0% { text-shadow: 0 0 10px #00e0ff; }
      100% { text-shadow: 0 0 20px #ffffff; }
    }

    .profile-card {
      display: flex;
      align-items: center;
      gap: 20px;
      position: relative;
      z-index: 1;
    }

    .profile-card img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #00e0ff;
      box-shadow: 0 0 15px rgba(0, 224, 255, 0.7);
    }

    .profile-card .info {
      font-size: 1.1rem;
    }

    .headline {
      margin-top: 10px;
      font-style: italic;
      font-size: 1rem;
      color: #e0e0e0;
    }

    .skill-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 15px;
    }

    .skill-card {
      background: rgba(255,255,255,0.12);
      padding: 12px;
      border-radius: 12px;
      text-align: center;
      transition: transform 0.3s;
      box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    }

    .skill-card:hover {
      transform: scale(1.05);
    }

    .browse-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 24px;
      background: linear-gradient(45deg, #36d1dc, #5b86e5);
      color: white;
      text-decoration: none;
      font-weight: 600;
      border-radius: 30px;
      transition: 0.3s ease;
    }

    .browse-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 0 20px rgba(91, 134, 229, 0.8);
    }

    .edit-btn {
      margin-top: 10px;
      padding: 8px 16px;
      border: none;
      background: #ffffff10;
      border-radius: 20px;
      cursor: pointer;
      color: white;
    }

    .edit-form {
      margin-top: 20px;
      background: rgba(255,255,255,0.1);
      padding: 20px;
      border-radius: 12px;
    }

    .edit-form label {
      display: block;
      margin-top: 10px;
      font-weight: 600;
    }

    .edit-form input,
    .edit-form textarea {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: none;
      margin-top: 5px;
    }

    .save-btn {
      margin-top: 15px;
      background: linear-gradient(45deg, #36d1dc, #5b86e5);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 25px;
      cursor: pointer;
    }
    .notification-item {
      padding: 10px 15px;
      border-bottom: 1px solid #ddd;
      position: relative;
    }
    .notification-item:last-child {
      border-bottom: none;
    }
    .notification-item strong {
      color: #333;
      cursor: pointer;
      text-decoration: underline;
    }
    .notif-buttons {
      margin-top: 8px;
      display: flex;
      gap: 10px;
    }
    .notif-btn {
      padding: 6px 12px;
      font-size: 0.9rem;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    .accept-btn {
      background-color: #36d1dc;
      color: white;
    }
    .accept-btn:hover {
      background-color: #2bb0be;
    }
    .decline-btn {
      background-color: #ff6b6b;
      color: white;
    }
    .decline-btn:hover {
      background-color: #e54e4e;
    }
    .message-btn {
      background-color: #5b86e5;
      color: white;
    }
    .message-btn:hover {
      background-color: #486fd1;
    }
    .event-container {
  background: rgba(0, 0, 0, 0.1);
  padding: 30px;
  border-radius: 18px;
  text-align: center;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  margin-top: 20px;
  animation: fadeIn 0.7s ease;
}

.events-btn {
  display: inline-block;
  margin-top: 20px;
  padding: 12px 24px;
  font-size: 1rem;
  font-weight: 600;
  background: linear-gradient(to right, #00c6ff, #0072ff);
  color: white;
  border-radius: 30px;
  text-decoration: none;
  transition: 0.3s ease;
}

.events-btn:hover {
  transform: scale(1.05);
  box-shadow: 0 0 20px rgba(0, 114, 255, 0.7);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.wallet-summary p {
  font-size: 1.2rem;
  margin-top: 10px;
}


  </style>
</head>
<body>

<header>
  <div class="welcome">Hi, <?= htmlspecialchars($name) ?> 👋</div>
  <div class="logo">SkillSwap</div>

  <div style="display: flex; align-items: center;">
    <!-- Notification Bell -->
    <div class="notification-bell" title="Swap Notifications" onclick="toggleDropdown()">
      🔔
      <?php if ($notif_count > 0): ?>
        <span id="notifCount"><?= $notif_count ?></span>
      <?php endif; ?>
    </div>


    <!-- Message Icon -->
    <!-- Message Icon with Notification -->
<div class="notification-bell" title="Messages" onclick="window.location.href='messages.php'" style="cursor:pointer; font-size:22px; margin-right: 20px; position: relative;">
  💬
  <?php if ($unread_msg_count > 0): ?>
    <span style="position: absolute; top: -8px; right: -8px; background: red; color: white; font-size: 12px; padding: 3px 6px; border-radius: 50%;">
      <?= $unread_msg_count ?>
    </span>
  <?php endif; ?>
</div>


    <!-- Request History Icon -->
    <div class="history-icon" title="Request History" onclick="window.location.href='historys.php'" style="cursor:pointer; font-size:22px; margin-right: 20px;">
      🕒
    </div>

    <!-- Logout Button -->
    <form method="POST" action="logout.php">
      <button class="logout-btn" type="submit">Logout</button>
    </form>
  </div>

  <!-- Notification Dropdown -->
  <div id="notifDropdown" class="notification-dropdown">
    <h4>Swap Requests</h4>
    <div id="notifList">
      <?php if ($notif_count == 0): ?>
        <div class="notification-item">No new requests.</div>
      <?php else: ?>
        <?php while ($row = $notifications->fetch_assoc()): ?>
          <div class="notification-item" data-id="<?= (int)$row['id'] ?>">
            <strong class="notif-name" onclick="openNotificationPage(<?= (int)$row['id'] ?>)">
              <?= htmlspecialchars($row['name']) ?>
            </strong> wants to <br>
            <em>Learn: <?= htmlspecialchars($row['skill_to_learn']) ?></em><br>
            <em>Teach: <?= htmlspecialchars($row['skill_to_teach']) ?></em><br>
            📍 <?= htmlspecialchars($row['location']) ?><br>
            📅 <?= date('M d, Y h:i A', strtotime($row['proposed_datetime'])) ?>

            <div class="notif-buttons">
              <button class="notif-btn accept-btn" onclick="respondRequest(<?= (int)$row['id'] ?>, 'accept', this)">Accept</button>
              <button class="notif-btn decline-btn" onclick="respondRequest(<?= (int)$row['id'] ?>, 'decline', this)">Decline</button>
            </div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </div>
</header>


<script>
  function toggleDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('active');
  }

  window.onclick = function(e) {
    if (!e.target.closest('.notification-bell') && !e.target.closest('#notifDropdown')) {
      document.getElementById('notifDropdown')?.classList.remove('active');
    }
  };

  function openNotificationPage(requestId) {
    window.open(`Notifications_page.php?request_id=${requestId}`, '_blank');
  }

  function respondRequest(requestId, action, btn) {
    if (!confirm(`Are you sure you want to ${action} this swap request?`)) return;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'handle_request.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      if (xhr.status === 200) {
        const resp = JSON.parse(xhr.responseText);
        if (resp.success) {
          const notifItem = btn.closest('.notification-item');
          if (action === 'accept') {
            // Replace buttons with message button
            const buttonsDiv = notifItem.querySelector('.notif-buttons');
            buttonsDiv.innerHTML = `<button class="notif-btn message-btn" onclick="openNotificationPage(${requestId})">Message</button>`;
          } else if (action === 'decline') {
            // Remove the notification item
            notifItem.remove();
          }
          // Update notification count
          updateNotifCount();
        } else {
          alert(resp.message || 'Something went wrong.');
        }
      } else {
        alert('Server error. Please try again later.');
      }
    };
    xhr.send(`request_id=${requestId}&action=${action}`);
  }

  function updateNotifCount() {
    const notifList = document.getElementById('notifList');
    const notifCountSpan = document.getElementById('notifCount');
    const remaining = notifList.querySelectorAll('.notification-item').length;
    if (remaining === 0) {
      notifList.innerHTML = '<div class="notification-item">No new requests.</div>';
      notifCountSpan?.remove();
    } else {
      notifCountSpan.textContent = remaining;
    }
  }
</script>

<div class="container">
  <!-- Your existing profile, skills, browse sections as before -->
  <div class="section">
    <h2>Your Profile</h2>
    <div class="profile-card">
      <img src="<?= htmlspecialchars($image_src) ?>" alt="User Profile Image" />
      <div class="info">
        <p><strong>Name:</strong> <?= htmlspecialchars($name) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
        <p class="headline"><?= htmlspecialchars($headline) ?></p>
        <button class="edit-btn" onclick="document.getElementById('editForm').style.display='block'">Edit Profile</button>
      </div>
    </div>

    <form id="editForm" class="edit-form" action="profile_update.php" method="POST" style="display:none;">
      <label for="profile_photo">Profile Photo URL:</label>
      <input type="text" name="profile_photo" value="<?= htmlspecialchars($profile_photo) ?>" />

      <label for="headline">Headline:</label>
      <input type="text" name="headline" value="<?= htmlspecialchars($headline) ?>" />

      <label for="skills_to_teach">Skills to Teach (comma separated):</label>
      <textarea name="skills_to_teach"><?= htmlspecialchars($skills_to_teach) ?></textarea>

      <label for="skills_to_learn">Skills to Learn (comma separated):</label>
      <textarea name="skills_to_learn"><?= htmlspecialchars($skills_to_learn) ?></textarea>

      <button class="save-btn" type="submit">Save Changes</button>
    </form>
  </div>

  <div class="section">
    <h2>Your Offered Skills</h2>
    <div class="skill-list">
      <?php foreach ($offeredSkills as $skill): ?>
        <div class="skill-card"><?= htmlspecialchars($skill) ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <h2>Your Requested Skills</h2>
    <div class="skill-list">
      <?php foreach ($requestedSkills as $skill): ?>
        <div class="skill-card"><?= htmlspecialchars($skill) ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section">
    <h2>Browse Skill Swaps</h2>
    <a href="browse_skills.php" class="browse-btn">Explore Swaps →</a>
  </div>

  <div class="section event-container">
  <h2>📅 Explore Events & Meetups</h2>
  <p>Join learning communities, attend skill-based meetups, or host your own event!</p>
  <a href="eventspagescreen.php" class="events-btn">Browse Events & Meetups</a>
</div>

<div class="section wallet-summary">
  <h2>Your Credits Wallet</h2>
  <p>Total Credits: <strong><?= $creditsBalance ?></strong></p>
  <a href="wallet.php" class="browse-btn">View Wallet Details</a>
</div>


</div>

</body>
</html>
