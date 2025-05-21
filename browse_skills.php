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
    die("Connection failed: " . $conn->connect_error);
}

$skillToLearn = $_GET['skill'] ?? '';
$language = $_GET['language'] ?? '';
$location = $_GET['location'] ?? ''; // from JS GPS
$currentTime = date("H:i"); // Current server time

$sql = "SELECT id, name, profile_photo, headline, skills_to_teach, languages_known 
        FROM users 
        WHERE skills_to_teach LIKE ? 
        AND languages_known LIKE ?";

$stmt = $conn->prepare($sql);
$likeSkill = "%$skillToLearn%";
$likeLang = "%$language%";
$stmt->bind_param("ss", $likeSkill, $likeLang);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Browse Skills - Skill Swap</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background: linear-gradient(to right, #43cea2, #185a9d);
      color: white;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 20px;
    }
    h1 {
      text-align: center;
      margin-bottom: 30px;
    }
    form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
      background: rgba(255, 255, 255, 0.1);
      padding: 20px;
      border-radius: 12px;
      backdrop-filter: blur(8px);
    }
    input, select, button {
      padding: 10px;
      border-radius: 8px;
      border: none;
      font-size: 1rem;
    }
    button {
      background: #00c6ff;
      color: white;
      cursor: pointer;
      transition: 0.3s;
    }
    button:hover {
      background: #007acc;
    }
    .user-card {
      background: rgba(255,255,255,0.1);
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 20px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .view-profile-btn {
      background-color: #00ffcc;
      border: none;
      padding: 10px 16px;
      border-radius: 8px;
      color: #000;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s;
    }
    .view-profile-btn:hover {
      background-color: #00ccaa;
    }
    .user-card img {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #00c6ff;
    }
    .user-info {
      flex: 1;
    }
    .user-info h3 {
      margin: 0 0 8px;
    }
    .user-info p {
      margin: 4px 0;
    }
    .back-btn {
      display: inline-block;
      margin-top: 20px;
      color: #fff;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 20px;
      background: #00c6ff;
    }
    .back-btn:hover {
      background: #007acc;
    }
  </style>
</head>
<body>
<div class="container">
  <h1>🌍 Explore People Who Can Teach You</h1>
  <form method="GET" action="">
    <input type="text" name="skill" placeholder="Skill to learn (e.g., Video Editing)" value="<?= htmlspecialchars($skillToLearn) ?>" required>
    <select name="language" required>
      <option value="">Select Language</option>
      <option value="English" <?= $language == "English" ? 'selected' : '' ?>>English</option>
      <option value="Tamil" <?= $language == "Tamil" ? 'selected' : '' ?>>Tamil</option>
      <option value="Hindi" <?= $language == "Hindi" ? 'selected' : '' ?>>Hindi</option>
      <option value="Telugu" <?= $language == "Telugu" ? 'selected' : '' ?>>Telugu</option>
    </select>
    <input type="text" id="location" name="location" placeholder="Auto-detected location" value="<?= htmlspecialchars($location) ?>" readonly>
    <input type="text" value="Current Time: <?= $currentTime ?>" readonly>
    <button type="submit">Search</button>
  </form>

  <?php if (empty($users)): ?>
    <p>No matches found. Try adjusting your skill or language.</p>
  <?php else: ?>
    <?php foreach ($users as $user): ?>
      <div class="user-card">
        <a href="view_profile.php?id=<?= $user['id'] ?>">
          <button class="view-profile-btn">👤 View Profile</button>
        </a>
        <img src="<?= htmlspecialchars($user['profile_photo'] ?: "https://i.pravatar.cc/100?u=" . $user['id']) ?>" alt="Profile">
        <div class="user-info">
          <h3><?= htmlspecialchars($user['name']) ?></h3>
          <p><strong>Headline:</strong> <?= htmlspecialchars($user['headline']) ?></p>
          <p><strong>Skills to Teach:</strong> <?= htmlspecialchars($user['skills_to_teach']) ?></p>
          <p><strong>Languages Known:</strong> <?= htmlspecialchars($user['languages_known']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>

<script>
// GPS location auto-detect
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(function(position) {
    const lat = position.coords.latitude;
    const lon = position.coords.longitude;
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`)
      .then(response => response.json())
      .then(data => {
        if (data.address && data.address.city) {
          document.getElementById('location').value = data.address.city;
        } else {
          document.getElementById('location').value = "Location not found";
        }
      });
  });
}
</script>
</body>
</html>
