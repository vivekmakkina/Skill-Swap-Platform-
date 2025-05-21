<?php
session_start();

$skills = [
    "Tech Skills" => [
        "Web Development", "App Development", "AI / Machine Learning", "Data Science", "Cloud Computing",
        "Cyber Security", "Graphic Design", "UI/UX Design", "Java", "Python", "C", "C++", "MySQL", "R Programming", "Other"
    ],
    "Non-Tech Skills" => [
        "Cooking", "Yoga / Fitness", "Photography", "Music (Singing / Instruments)", "Dance", "Art & Craft",
        "Public Speaking", "Writing / Blogging", "Gym Training", "Video Editing", "Resume Building"
    ]
];

$languages = [
    "Hindi", "English", "Telugu", "Tamil", "Kannada", "Malayalam", "Marathi", "Gujarati", "Punjabi", "Bengali",
    "Odia", "Assamese", "Urdu", "Konkani", "Manipuri", "Dogri", "Sanskrit", "Maithili", "Sindhi"
];

$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'skillswap';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $headline = $_POST['headline'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $raw_password = $_POST['password'];

    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $raw_password)) {
        echo "<script>alert('Password must contain at least 8 characters, including uppercase, lowercase, number, and symbol.');</script>";
        exit;
    }

    $password = password_hash($raw_password, PASSWORD_DEFAULT);

    if (!isset($_POST['skills_to_teach']) || count($_POST['skills_to_teach']) < 1 || count($_POST['skills_to_teach']) > 5) {
        echo "<script>alert('Please select between 1 and 5 skills to teach.');</script>";
        exit;
    }

    $skills_to_teach = implode(",", $_POST['skills_to_teach']);
    $skills_to_learn = implode(",", $_POST['skills_to_learn']);
    $languages_known = implode(",", $_POST['languages_known']);

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Email already registered. Please login or use another email.');</script>";
        exit;
    }
    $check->close();

    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir);
    $profile_photo = $target_dir . basename($_FILES["profile_photo"]["name"]);
    
    if (!move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $profile_photo)) {
        echo "<script>alert('Failed to upload profile photo.');</script>";
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO users (profile_photo, headline, name, email, password, skills_to_teach, skills_to_learn, languages_known)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $profile_photo, $headline, $name, $email, $password, $skills_to_teach, $skills_to_learn, $languages_known);

    if ($stmt->execute()) {
        echo "<script>
            alert('Signup successful!');
            window.location.href = 'login.php';
        </script>";
    } else {
        echo "<script>alert('Signup was unsuccessful. Please try again.');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Signup - Skill Swap Platform</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #667eea, #764ba2);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #fff;
      overflow: hidden;
    }

    .form-container {
      background: rgba(255, 255, 255, 0.1);
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      backdrop-filter: blur(15px);
      width: 90%;
      max-width: 600px;
      animation: fadeIn 1.5s ease-in-out;
      overflow-y: auto;
      max-height: 95vh;
    }

    h1 {
      text-align: center;
      margin-bottom: 20px;
      font-size: 3rem;
      animation: slideDown 1s ease-out;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      font-weight: 600;
      display: block;
      margin-bottom: 5px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: none;
      font-size: 1rem;
    }

    .form-group input[type="file"] {
      padding: 5px;
      background: #fff;
    }

    .form-group button {
      width: 100%;
      padding: 12px;
      background: linear-gradient(45deg, #36d1dc, #5b86e5);
      color: white;
      font-weight: 600;
      border: none;
      border-radius: 30px;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .form-group button:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    .checkbox-group {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .checkbox-group label {
      background-color: rgba(255,255,255,0.2);
      padding: 8px 12px;
      border-radius: 20px;
      cursor: pointer;
      color: #fff;
    }

    .checkbox-group input {
      margin-right: 5px;
    }

    .preview-img {
      display: block;
      margin-top: 10px;
      max-width: 100px;
      max-height: 100px;
      border-radius: 10px;
      object-fit: cover;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.9); }
      to { opacity: 1; transform: scale(1); }
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-50px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 600px) {
      h1 {
        font-size: 2.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h1>Sign Up</h1>
    <form action="signup.php" method="POST" enctype="multipart/form-data">

      <div class="form-group">
        <label for="profile_photo">Profile Photo</label>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required onchange="previewImage(event)" />
        <img id="photo_preview" class="preview-img" src="#" alt="Preview" style="display: none;" />
      </div>

      <div class="form-group">
        <label for="headline">Headline</label>
        <input type="text" name="headline" id="headline" required />
      </div>

      <div class="form-group">
        <label for="name">Name</label>
        <input type="text" name="name" id="name" required />
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required />
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required />
      </div>

      <div class="form-group">
        <label>Skills to Teach (Select 1-5)</label>
        <div class="checkbox-group">
          <?php 
            foreach ($skills as $category => $skillList) {
              echo "<strong style='width:100%'>$category</strong>";
              foreach ($skillList as $skill) {
                echo "<label><input type='checkbox' name='skills_to_teach[]' value='$skill'> $skill</label>";
              }
            }
          ?>
        </div>
      </div>

      <div class="form-group">
        <label>Skills to Learn</label>
        <div class="checkbox-group">
          <?php 
            foreach ($skills as $category => $skillList) {
              echo "<strong style='width:100%'>$category</strong>";
              foreach ($skillList as $skill) {
                echo "<label><input type='checkbox' name='skills_to_learn[]' value='$skill'> $skill</label>";
              }
            }
          ?>
        </div>
      </div>

      <div class="form-group">
        <label>Languages Known</label>
        <div class="checkbox-group">
          <?php foreach ($languages as $lang) {
            echo "<label><input type='checkbox' name='languages_known[]' value='$lang'> $lang</label>";
          } ?>
        </div>
      </div>

      <div class="form-group">
        <button type="submit" name="submit">Sign Up</button>
      </div>
    </form>
  </div>

  <script>
    function previewImage(event) {
      const reader = new FileReader();
      reader.onload = function () {
        const preview = document.getElementById('photo_preview');
        preview.src = reader.result;
        preview.style.display = 'block';
      }
      reader.readAsDataURL(event.target.files[0]);
    }
  </script>
</body>
</html>
