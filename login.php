<?php
session_start();
$host = 'localhost';
$username = 'root';
$password = 'root';
$dbname = 'skillswap';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $name, $userEmail, $hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $userEmail;
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Email not found.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login - Skill Swap Platform</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #667eea, #764ba2);
      height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      overflow: hidden;
    }

    .login-container {
      background: rgba(255, 255, 255, 0.1);
      padding: 50px 40px;
      border-radius: 20px;
      backdrop-filter: blur(20px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 420px;
      animation: fadeSlideIn 1.2s ease-out forwards;
      opacity: 0;
      transform: translateY(50px);
    }

    @keyframes fadeSlideIn {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    h2 {
      text-align: center;
      margin-bottom: 25px;
      font-size: 2rem;
      background: linear-gradient(45deg, #ffffff, #00e0ff);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: glow 2s infinite alternate;
    }

    @keyframes glow {
      0% {
        text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
      }
      100% {
        text-shadow: 0 0 20px rgba(0, 224, 255, 0.8);
      }
    }

    .form-group {
      margin-bottom: 20px;
      position: relative;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .form-group input {
      width: 100%;
      padding: 12px;
      border-radius: 12px;
      border: none;
      background: rgba(255, 255, 255, 0.2);
      color: #fff;
      font-size: 1rem;
      transition: 0.3s ease;
      outline: none;
    }

    .form-group input::placeholder {
      color: #eee;
    }

    .form-group input:focus {
      background: rgba(255, 255, 255, 0.3);
      box-shadow: 0 0 10px rgba(0, 224, 255, 0.6);
    }

    .forgot-password-inline {
      margin-top: 6px;
      font-size: 0.8rem;
      text-align: left;
    }

    .forgot-password-inline a {
      color: #00e0ff;
      text-decoration: underline;
    }

    button {
      width: 100%;
      padding: 14px;
      background: linear-gradient(45deg, #36d1dc, #5b86e5);
      border: none;
      color: white;
      font-weight: 600;
      border-radius: 30px;
      font-size: 1.1rem;
      cursor: pointer;
      transition: 0.3s ease;
      animation: pulseBtn 2s infinite ease-in-out;
    }

    @keyframes pulseBtn {
      0% { transform: scale(1); box-shadow: 0 0 0 rgba(91, 134, 229, 0.7); }
      70% { transform: scale(1.05); box-shadow: 0 0 20px rgba(91, 134, 229, 0.6); }
      100% { transform: scale(1); box-shadow: 0 0 0 rgba(91, 134, 229, 0.7); }
    }

    button:hover {
      transform: scale(1.03);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    }

    .error {
      color: #ffb3b3;
      font-size: 0.9rem;
      text-align: center;
      margin-bottom: 20px;
    }

    @media (max-width: 480px) {
      .login-container {
        padding: 30px 25px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Login to Skill Swap</h2>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="Enter your email" required />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required />
        <div class="forgot-password-inline">
          <a href="forgot_password.php">Forgot Password?</a>
        </div>
      </div>
      <button type="submit" name="login">Login</button>
    </form>
  </div>
</body>
</html>
