<?php
session_start();

// === Database connection code included here ===
$host = "localhost";
$username = "root";
$password = "root"; // update if your MAMP password is different
$database = "skillswap";

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// === End DB connection ===

$current_user_id = $_SESSION['user_id'] ?? 0;

if (!$current_user_id) {
    header("Location: login.php");
    exit;
}

// Fetch chat users (who accepted swaps)
$chat_users_sql = "
SELECT u.id, u.name, u.profile_photo 
FROM users u 
JOIN swap_requests sr ON (
    (sr.from_user_id = $current_user_id AND sr.to_user_id = u.id) OR 
    (sr.to_user_id = $current_user_id AND sr.from_user_id = u.id)
)
WHERE sr.status = 'accepted'
GROUP BY u.id
";
$chat_users = mysqli_query($conn, $chat_users_sql);

// Selected chat
$selected_user_id = $_GET['chat_with'] ?? 0;
$messages = [];

if ($selected_user_id) {
    $messages_sql = "
        SELECT * FROM messages 
        WHERE 
            (from_user_id = $current_user_id AND to_user_id = $selected_user_id) OR 
            (from_user_id = $selected_user_id AND to_user_id = $current_user_id)
        ORDER BY timestamp ASC
    ";
    $messages_result = mysqli_query($conn, $messages_sql);
    while ($row = mysqli_fetch_assoc($messages_result)) {
        $messages[] = $row;
    }

    // Mark messages as read
    $mark_read_sql = "UPDATE messages SET is_read = 1 WHERE from_user_id = $selected_user_id AND to_user_id = $current_user_id";
    mysqli_query($conn, $mark_read_sql);
}

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_user_id) {
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $attachment = "";

    if (!empty($_FILES['attachment']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $attachment = $targetDir . basename($_FILES["attachment"]["name"]);
        move_uploaded_file($_FILES["attachment"]["tmp_name"], $attachment);
    }

    $send_sql = "
        INSERT INTO messages (from_user_id, to_user_id, message, attachment_path, timestamp, is_read)
        VALUES ($current_user_id, $selected_user_id, '$message', '$attachment', NOW(), 0)
    ";
    mysqli_query($conn, $send_sql);
    header("Location: messages.php?chat_with=$selected_user_id");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Skill Swap - Messages</title>
   <style>
    body {
        display: flex;
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        height: 100vh;
        background-color: #f0f8ff;
        overflow: hidden;
    }

    .sidebar {
        width: 280px;
        background: #e0f7fa;
        border-right: 1px solid #ccc;
        padding: 20px;
        overflow-y: auto;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
    }

    .chat-window {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #ffffff;
    }

    h3 {
        margin-top: 0;
        color: #00796b;
        font-size: 20px;
    }

    .user-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding: 8px;
        border-radius: 10px;
        transition: background 0.3s;
    }

    .user-item:hover {
        background: #b2ebf2;
    }

    .user-item img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 12px;
        object-fit: cover;
        border: 2px solid #80deea;
    }

    .user-item a {
        display: flex;
        align-items: center;
        color: #004d40;
        font-weight: 500;
        text-decoration: none;
        width: 100%;
    }

    .messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background-color: #f9f9f9;
    }

    .message {
        margin-bottom: 15px;
        max-width: 65%;
        padding: 12px 16px;
        border-radius: 16px;
        line-height: 1.4;
        animation: fadeIn 0.3s ease-in;
    }

    .message.sent {
        background-color: #c8e6c9;
        margin-left: auto;
        text-align: right;
        border-bottom-right-radius: 0;
    }

    .message.received {
        background-color: #e3f2fd;
        border-bottom-left-radius: 0;
    }

    .chat-input {
        padding: 15px;
        background: #fafafa;
        display: flex;
        align-items: center;
        border-top: 1px solid #ccc;
    }

    .chat-input textarea {
        flex: 1;
        padding: 10px;
        resize: none;
        border-radius: 10px;
        border: 1px solid #ccc;
        font-family: inherit;
        font-size: 14px;
    }

    .chat-input input[type="file"] {
        margin-left: 10px;
        font-size: 14px;
    }

    .chat-input button {
        margin-left: 10px;
        padding: 10px 18px;
        background-color: #00796b;
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.3s;
    }

    .chat-input button:hover {
        background-color: #004d40;
    }

    .timestamp {
        font-size: 11px;
        color: gray;
        margin-top: 6px;
    }

    .attachment-link {
        display: block;
        font-size: 12px;
        margin-top: 6px;
        color: #1976d2;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 100px;
            padding: 10px;
        }

        .user-item span {
            display: none;
        }
    }
</style>

</head>
<body>
    <div class="sidebar">
        <h3>💬 Messages</h3>
        <?php if (mysqli_num_rows($chat_users) === 0): ?>
            <p>No chats yet.</p>
        <?php else: ?>
            <?php while ($user = mysqli_fetch_assoc($chat_users)) { ?>
                <div class="user-item">
  <a href="messages.php?chat_with=<?= $user['id'] ?>">
    <img src="<?= htmlspecialchars($user['profile_photo']) ?: 'default-profile.png' ?>" alt="<?= htmlspecialchars($user['name']) ?>">
    <span><?= htmlspecialchars($user['name']) ?></span>
  </a>
</div>

            <?php } ?>
        <?php endif; ?>
    </div>

    <div class="chat-window">
        <div class="messages" id="messages">
            <?php if (empty($messages)): ?>
                <p style="padding: 20px; color: #999;">No messages in this chat yet.</p>
            <?php else: ?>
                <?php foreach ($messages as $msg) { ?>
                    <div class="message <?= $msg['from_user_id'] == $current_user_id ? 'sent' : 'received' ?>">
                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <?php if ($msg['attachment_path']) { ?>
                            <a class="attachment-link" href="<?= htmlspecialchars($msg['attachment_path']) ?>" target="_blank">📎 View Attachment</a>
                        <?php } ?>
                        <div class="timestamp">
                            <?= date('d M Y, h:i A', strtotime($msg['timestamp'])) ?>
                            <?= $msg['from_user_id'] == $current_user_id && $msg['is_read'] ? ' ✅ Read' : '' ?>
                        </div>
                    </div>
                <?php } ?>
            <?php endif; ?>
        </div>

        <?php if ($selected_user_id): ?>
            <form class="chat-input" method="POST" enctype="multipart/form-data">
                <textarea name="message" required placeholder="Type a message..." rows="2"></textarea>
                <input type="file" name="attachment" accept="image/*,application/pdf,.doc,.docx,.txt">
                <button type="submit">Send</button>
            </form>
        <?php else: ?>
            <div style="padding: 20px;">Select a chat to start messaging.</div>
        <?php endif; ?>
    </div>

<script>
    // Auto scroll to bottom of messages on page load
    const messagesDiv = document.getElementById('messages');
    if (messagesDiv) {
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
</script>

</body>
</html>
