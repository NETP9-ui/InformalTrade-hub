<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// Generate CSRF if missing
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_user = $_SESSION['user_id'];
$chat_with    = filter_var($_GET['receiver_id'] ?? ($_GET['user'] ?? ''), FILTER_VALIDATE_INT);
$prod_id      = filter_var($_GET['prod_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

if (!$chat_with) { 
    $stmt = $pdo->prepare("SELECT DISTINCT
            CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS user_id,
            u.username,
            MAX(m.created_at) AS last_message_at
        FROM messages m
        JOIN users u ON u.user_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY user_id, u.username
        ORDER BY last_message_at DESC");
    $stmt->execute([$current_user, $current_user, $current_user, $current_user]);
    $conversations = $stmt->fetchAll();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Messages - InformalTrade Hub</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-chat-dots me-2"></i>Messages</h3>
            <a href="product.php" class="btn btn-outline-success btn-sm"><i class="bi bi-grid me-1"></i>Browse Products</a>
        </div>
        <?php if ($conversations): ?>
            <div class="list-group">
                <?php foreach ($conversations as $conversation): ?>
                    <a href="chat.php?receiver_id=<?= $conversation['user_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span><?= htmlspecialchars($conversation['username']) ?></span>
                        <small class="text-muted"><?= htmlspecialchars($conversation['last_message_at']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No messages yet. Open a product and choose Message Seller to start a conversation.</div>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    exit; 
}

if ($chat_with === $current_user) {
    header("Location: chat.php");
    exit;
}

// Fetch other user's name
$stmtUser = $pdo->prepare("SELECT username FROM users WHERE user_id=?");
$stmtUser->execute([$chat_with]);
$otherUser = $stmtUser->fetch();

$stmt = $pdo->prepare("SELECT m.*, u.username AS sender_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.user_id
    WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
    ORDER BY m.created_at ASC");
$stmt->execute([$current_user, $chat_with, $chat_with, $current_user]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat with <?= htmlspecialchars($otherUser['username'] ?? "User #$chat_with") ?> - InformalTrade Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
	 <link rel="stylesheet" href="responsive.css">
    <script>
        function sendMessage(event) {
            const btn = event.submitter;
            btn.disabled = true;
            btn.innerHTML = 'Sending...';
        }
        function refreshChat() {
            location.reload();
        }
    </script>
</head>
<body class="bg-light">
<div class="container-fluid messages-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="bi bi-chat-dots me-2"></i>Chat with <strong><?= htmlspecialchars($otherUser['username'] ?? "User #$chat_with") ?></strong></h5>
        <div>
            <?php if ($prod_id): ?>
                <a href="buy-product.php?id=<?= htmlspecialchars($prod_id) ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-box-seam me-1"></i>View Product</a>
            <?php endif; ?>
            <button onclick="refreshChat()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>
    <div id="messages" class="border p-3 mb-3 bg-white rounded" style="height:400px; overflow-y:auto;">
        <?php if ($messages): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="mb-2 <?= $msg['sender_id'] == $current_user ? 'text-end' : '' ?>">
                    <div class="d-inline-block p-2 rounded <?= $msg['sender_id'] == $current_user ? 'bg-success text-white' : 'bg-light' ?>" style="max-width:70%;">
                        <small class="fw-bold"><?= htmlspecialchars($msg['sender_name']) ?></small><br>
                        <?= htmlspecialchars($msg['message']) ?>
                        <br><small class="opacity-75"><?= date('M j, H:i', strtotime($msg['created_at'])) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted text-center py-4">No messages yet. Start the conversation!</p>
        <?php endif; ?>
    </div>
    <form id="chatForm" method="POST" action="send-message.php" onsubmit="sendMessage(event)">
        <input type="hidden" name="receiver_id" value="<?= $chat_with ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <?php if ($prod_id): ?>
            <input type="hidden" name="prod_id" value="<?= htmlspecialchars($prod_id) ?>">
        <?php endif; ?>
        <div class="input-group">
            <textarea name="message" class="form-control" rows="2" placeholder="Type your message... (Enter to send)" required></textarea>
            <button type="submit" class="btn btn-success"><i class="bi bi-send me-1"></i>Send</button>
        </div>
    </form>
</div>
</body>
</html>
