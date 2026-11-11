<?php
/* =====================================================================
   SECURITY GUARD (runs first on this page)
   1. This page can only be opened through index.php. Typing its own
      file name in the address bar sends the visitor to the login page.
   2. A visitor who is not signed in is sent back to the login page.
   ===================================================================== */
if (!defined('APP_ROUTER')) {
    header('Location: ../index.html');
    exit();
}
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    json_out(['ok' => false, 'message' => 'Please sign in again.'], 401);
}

// Notifications endpoint: marks notifications as read (POST) or returns the latest ones as JSON (GET).

require_once __DIR__ . '/../db/db.php';

$userId = (int) $_SESSION['user_id'];

// Handle the "mark as read" request (one notification or all of them).
if ($page === 'notifications_read') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['ok' => false, 'message' => 'Invalid request.'], 405);
    }
    $notificationId = filter_var(post_string('id'), FILTER_VALIDATE_INT);
    if (post_string('all') === '1') {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    } elseif ($notificationId && $notificationId > 0) {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
    }
    json_out(['ok' => true, 'unread' => notification_unread_count($conn, $userId)]);
}

// GET: latest notifications plus the live counters shown in the navigation bar.
$stmt = $conn->prepare('SELECT id, type, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $link = (string) $row['link'];
    if (strpos($link, 'index.php?') !== 0) {
        $link = '';
    }
    $items[] = [
        'id' => (int) $row['id'],
        'type' => $row['type'],
        'message' => $row['message'],
        'link' => $link,
        'is_read' => (int) $row['is_read'] === 1,
        'time' => time_ago((string) $row['created_at']),
    ];
}

// Build the JSON response, including the navigation bar counters.
$payload = [
    'ok' => true,
    'unread' => notification_unread_count($conn, $userId),
    'items' => $items,
];
if (is_staff_side()) {
    $payload['pending_orders'] = pending_order_count($conn);
    $payload['open_feedback'] = open_feedback_count($conn);
} else {
    $payload['cart'] = cart_count($conn, $userId);
}
json_out($payload);
