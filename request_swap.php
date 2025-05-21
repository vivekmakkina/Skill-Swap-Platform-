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

$to_user_id = $_GET['user_id'] ?? null;
if (!$to_user_id) {
    die("User ID is required.");
}

// Fetch recipient user info
$sql = "SELECT id, name, profile_photo, skills_to_teach, skills_to_learn FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $to_user_id);
$stmt->execute();
$result = $stmt->get_result();
$to_user = $result->fetch_assoc();
$stmt->close();

if (!$to_user) {
    die("User not found.");
}

$from_user_id = $_SESSION['user_id'];
$sql = "SELECT id, name, profile_photo, skills_to_teach FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $from_user_id);
$stmt->execute();
$result = $stmt->get_result();
$from_user = $result->fetch_assoc();
$stmt->close();

if (!$from_user) {
    die("Your user profile was not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $skill_to_learn = $_POST['skill_to_learn'] ?? '';
    $skill_to_teach = $_POST['skill_to_teach'] ?? '';
    $proposed_datetime = $_POST['proposed_datetime'] ?? '';
    $location = $_POST['location'] ?? '';
    $message = $_POST['message'] ?? '';
    $file_path = null;

    // Basic validation
    if (empty($skill_to_learn) || empty($skill_to_teach) || empty($proposed_datetime) || empty($location)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check for duplicate request
        $check_sql = "SELECT * FROM swap_requests WHERE from_user_id = ? AND to_user_id = ? AND skill_to_learn = ? AND skill_to_teach = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("iiss", $from_user_id, $to_user_id, $skill_to_learn, $skill_to_teach);
        $stmt->execute();
        $existing = $stmt->get_result();
        $stmt->close();

        if ($existing->num_rows > 0) {
            $error = "You've already sent a similar request.";
        } else {
            // Handle file upload
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = "uploads/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $filename = basename($_FILES["attachment"]["name"]);
                $target_file = $upload_dir . time() . "_" . $filename;

                if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                    $file_path = $target_file;
                } else {
                    $error = "File upload failed.";
                }
            }

            if (!isset($error)) {
                $sql = "INSERT INTO swap_requests (from_user_id, to_user_id, skill_to_learn, skill_to_teach, proposed_datetime, location, message, attachment_path)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iissssss", $from_user_id, $to_user_id, $skill_to_learn, $skill_to_teach, $proposed_datetime, $location, $message, $file_path);
                if ($stmt->execute()) {
                    $success = "Swap request sent successfully!";
                } else {
                    $error = "Error sending request: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$conn->close();

function createSkillOptions($skills_csv) {
    $skills = array_map('trim', explode(',', $skills_csv));
    $options = '';
    foreach ($skills as $skill) {
        if (!empty($skill)) {
            $options .= "<option value=\"" . htmlspecialchars($skill) . "\">" . htmlspecialchars($skill) . "</option>";
        }
    }
    return $options;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Skill Swap</title>
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
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        h1 {
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        label {
            font-weight: bold;
        }
        select, input[type="datetime-local"], input[type="text"], textarea, input[type="file"] {
            padding: 10px;
            border-radius: 8px;
            border: none;
            width: 100%;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        .submit-btn {
            background: #00c6ff;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-btn:hover {
            background: #007acc;
        }
        .message {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .error {
            color: #ff4d4d;
        }
        .success {
            color: #00ff99;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🤝 Request Skill Swap with <?= htmlspecialchars($to_user['name']) ?></h1>
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php elseif (isset($success)): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label for="skill_to_learn">🎯 Skill You Want to Learn</label>
        <select name="skill_to_learn" id="skill_to_learn" required>
            <option value="">-- Select Skill --</option>
            <?= createSkillOptions($to_user['skills_to_teach']) ?>
        </select>

        <label for="skill_to_teach">🧠 Skill You'll Teach Them</label>
        <select name="skill_to_teach" id="skill_to_teach" required>
            <option value="">-- Select Skill --</option>
            <?= createSkillOptions($to_user['skills_to_learn']) ?>
        </select>

        <label for="proposed_datetime">🗓️ Propose Date & Time</label>
        <input type="datetime-local" name="proposed_datetime" id="proposed_datetime" required>

        <label for="location">📍 Choose Location</label>
        <input type="text" name="location" id="location" placeholder="e.g., Zoom / Chennai Cafe" required>

        <label for="message">📩 Message (Optional)</label>
        <textarea name="message" id="message" placeholder="I'm available Sundays only..."></textarea>

        <label for="attachment">📎 Upload Resume/Portfolio (Optional)</label>
        <input type="file" name="attachment" id="attachment" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg">

        <button type="submit" class="submit-btn">✅ Submit Request</button>
    </form>
</div>
</body>
</html>
