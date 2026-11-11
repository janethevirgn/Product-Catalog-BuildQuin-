<?php
/* =====================================================================
   SECURITY GUARD (runs first on this page)
   1. This page can only be opened through index.php. Typing its own
      file name in the address bar sends the visitor to the login page.
   2. A visitor who is not signed in is sent back to the login page.
   3. Only Customer accounts may open this page.
   ===================================================================== */
if (!defined('APP_ROUTER')) {
    header('Location: ../index.html');
    exit();
}
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../index.html?mode=login');
    exit();
}
if ($_SESSION['role'] !== 'user') {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}

// Shows the customer's product feedback and order reviews, together with replies from the BuildQuin team.

require_once __DIR__ . '/../db/db.php';

// Load data: this customer's product feedback.
$userId = (int) $_SESSION['user_id'];
$items = [];
$stmt = $conn->prepare('SELECT f.message, f.response, f.created_at, f.responded_at, p.name AS product_name, p.product_code FROM product_feedback f JOIN products p ON p.id = f.product_id WHERE f.user_id = ? ORDER BY f.created_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$rows = $stmt->get_result();
while ($row = $rows->fetch_assoc()) {
    $row['kind'] = 'product';
    $items[] = $row;
}

// Load data: order reviews, with a text list of the items in each order.
$reviewStmt = $conn->prepare('SELECT v.rating, v.message, v.photos, v.response, v.created_at, v.responded_at, o.id AS order_id, o.order_number FROM order_reviews v JOIN orders o ON o.id = v.order_id WHERE v.user_id = ? ORDER BY v.created_at DESC');
$reviewStmt->bind_param('i', $userId);
$reviewStmt->execute();
$rows = $reviewStmt->get_result();
while ($row = $rows->fetch_assoc()) {
    $row['kind'] = 'review';
    $names = [];
    foreach (load_order_items($conn, (int) $row['order_id']) as $orderItem) {
        $names[] = $orderItem['product_name'] . ' x' . (int) $orderItem['quantity'];
    }
    $row['items_text'] = implode(', ', $names);
    $items[] = $row;
}

// Newest first, then prepare the notice messages.
usort($items, function ($x, $y) {
    return strcmp($y['created_at'], $x['created_at']);
});
$notice = get_string('notice');
$notices = [
    'feedback_sent' => 'Your feedback was sent to the BuildQuin team.',
    'feedback_invalid' => 'Feedback must contain 3 to 2,000 characters.',
    'feedback_error' => 'Your feedback could not be saved. Try again.',
    'product_missing' => 'That product is no longer available.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Feedback | BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/feedback.css">
    <link rel="stylesheet" href="../css/lightbox.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body>
    <?php include __DIR__ . '/navigation.php'; ?>

    <main class="page-wrap">
        <div class="page-heading">
            <div>
                <p class="eyebrow">YOUR ACCOUNT</p>
                <h1>My feedback</h1>
                <p>Your order ratings, comments and the replies you received.</p>
            </div>
        </div>
        <?php if (isset($notices[$notice])): ?>
            <div
                class="notice <?= in_array($notice, ['feedback_invalid', 'feedback_error', 'product_missing'], true) ? 'error' : '' ?>"
                role="status"
            ><?= htmlspecialchars($notices[$notice]) ?></div>
        <?php endif; ?>
        <?php if ($items): ?>
            <div class="feedback-list">
                <?php foreach ($items as $item): $isReview = $item['kind'] === 'review'; ?>
                    <article class="feedback-item">
                        <div class="feedback-meta">
                            <div>
                                <?php if ($isReview): ?>
                                    <span class="eyebrow">ORDER REVIEW</span>
                                    <h2><?= htmlspecialchars($item['order_number']) ?> <?= review_stars((int) $item['rating']) ?><span class="review-score"><?= (int) $item['rating'] ?>/5</span></h2>
                                    <?php if ($item['items_text'] !== ''): ?>
                                        <p class="review-items"><?= htmlspecialchars($item['items_text']) ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="eyebrow">PRODUCT</span>
                                    <h2><?= htmlspecialchars($item['product_name']) ?></h2>
                                    <p><?= htmlspecialchars($item['product_code']) ?></p>
                                <?php endif; ?>
                            </div>
                            <time><?= htmlspecialchars(date('M j, Y g:i a', strtotime($item['created_at']))) ?></time>
                        </div>
                        <?php if ($item['message'] !== null && $item['message'] !== ''): ?>
                            <p class="feedback-message"><?= nl2br(htmlspecialchars($item['message'])) ?></p>
                        <?php endif; ?>
                        <?php if ($isReview): $photos = review_photo_list($item['photos']); if ($photos): ?>
                            <div class="review-photos">
                                <?php foreach ($photos as $photo): ?>
                                    <img
                                        src="../<?= htmlspecialchars($photo) ?>"
                                        alt="Review photo"
                                        data-lightbox
                                        data-lightbox-group="mf<?= (int) $item['order_id'] ?>"
                                        loading="lazy"
                                    >
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endif; ?>
                        <?php if ($item['response'] !== null): ?>
                            <div class="admin-response">
                                <strong>BuildQuin response</strong>
                                <p><?= nl2br(htmlspecialchars($item['response'])) ?></p>
                                <?php if ($item['responded_at'] !== null): ?>
                                    <time><?= htmlspecialchars(date('M j, Y g:i a', strtotime($item['responded_at']))) ?></time>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="awaiting-response">Awaiting a response</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">You have not rated any order yet. Once an order is delivered, tap Rate order in My Orders.</div>
            <a class="browse-link" href="index.php?page=my_orders">Go to my orders</a>
        <?php endif; ?>
    </main>

    <script src="../js/lightbox.js" defer></script>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>