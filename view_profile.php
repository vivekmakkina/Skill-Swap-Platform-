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

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    die("User ID is required.");
}

// Update the SQL query to remove the 'location' field and keep 'headline'
$sql = "SELECT id, name, profile_photo, headline, skills_to_teach, skills_to_learn, languages_known 
        FROM users 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Profile - Skill Swap</title>
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
    .user-card {
      background: rgba(255,255,255,0.1);
      padding: 20px;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .user-card img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #00c6ff;
    }
    .user-info {
      text-align: center;
    }
    .user-info h3 {
      margin: 0 0 8px;
    }
    .user-info p {
      margin: 4px 0;
    }
    .back-btn, .request-btn {
      display: inline-block;
      margin-top: 20px;
      color: #fff;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 20px;
      background: #00c6ff;
      transition: background 0.3s;
    }
    .back-btn:hover, .request-btn:hover {
      background: #007acc;
    }
  </style>
</head>
<body>
<div class="container">
  <h1>🌍 <?= htmlspecialchars($user['name']) ?>'s Profile</h1>

  <div class="user-card">
    <img src="<?= htmlspecialchars($user['profile_photo'] ?: "https://i.pravatar.cc/150?u=" . $user['id']) ?>" alt="Profile">
    <div class="user-info">
      <h3><?= htmlspecialchars($user['name']) ?></h3>
      <p><strong>Headline:</strong> <?= htmlspecialchars($user['headline']) ?></p>
      <p><strong>Skills to Teach:</strong> <?= htmlspecialchars($user['skills_to_teach']) ?></p>
      <p><strong>Skills to Learn:</strong> <?= htmlspecialchars($user['skills_to_learn']) ?></p>
      <p><strong>Languages Known:</strong> <?= htmlspecialchars($user['languages_known']) ?></p>
      <p><strong>Location:</strong> <span id="user-location">Loading...</span></p>
      <!-- Request Swap Button inside user-card -->
      <a href="request_swap.php?user_id=<?= $user['id'] ?>" class="request-btn">Request Swap</a>
    </div>
  </div>

  <a href="browse_skills.php" class="back-btn">← Back to Search Results</a>
</div>

<script>
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const latitude = position.coords.latitude;
      const longitude = position.coords.longitude;

      const apiKey = 'YOUR_GOOGLE_MAPS_API_KEY'; // Replace with your API key
      const geocodeUrl = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${latitude},${longitude}&key=${apiKey}`;
      
      fetch(geocodeUrl)
        .then(response => response.json())
        .then(data => {
          if (data.results.length > 0) {
            const location = data.results[0].formatted_address;
            document.getElementById('user-location').innerText = location;
          } else {
            document.getElementById('user-location').innerText = 'Location not found';
          }
        })
        .catch(() => {
          document.getElementById('user-location').innerText = 'Unable to retrieve location';
        });
    }, function() {
      document.getElementById('user-location').innerText = 'Location not available';
    });
  } else {
    document.getElementById('user-location').innerText = 'Geolocation is not supported by this browser.';
  }
</script>

</body>
</html>
