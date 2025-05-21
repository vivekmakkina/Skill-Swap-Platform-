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

$user_id = $_SESSION['user_id'];

$profile_photo = trim($_POST['profile_photo']);
$headline = trim($_POST['headline']);
$skills_to_teach = trim($_POST['skills_to_teach']);
$skills_to_learn = trim($_POST['skills_to_learn']);

$stmt = $conn->prepare("UPDATE users SET profile_photo=?, headline=?, skills_to_teach=?, skills_to_learn=? WHERE id=?");
$stmt->bind_param("ssssi", $profile_photo, $headline, $skills_to_teach, $skills_to_learn, $user_id);
$stmt->execute();
$stmt->close();

$conn->close();
header("Location: dashboard.php");
exit;
?>
