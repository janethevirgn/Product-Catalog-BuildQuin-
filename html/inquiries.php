<?php
/* =====================================================================
   SECURITY GUARD (runs first on this page)
   1. This page can only be opened through index.php. Typing its own
      file name in the address bar sends the visitor to the login page.
   2. A visitor who is not signed in is sent back to the login page.
   3. Only Admin / Staff accounts may open this page.
   ===================================================================== */
if (!defined('APP_ROUTER')) {
    header('Location: ../index.html');
    exit();
}
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../index.html?mode=login');
    exit();
}
if (!in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}

// Admin / staff inbox: customers' product feedback and order reviews.
// Staff can reply to or delete each entry; the customer is notified.

require_once __DIR__ . '/../db/db.php';

$notice = get_string('notice');

// Handle the form submit (reply or delete).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_string('action');
    $feedbackId = filter_var(post_string('feedback_id'), FILTER_VALIDATE_INT);

    if (!$feedbackId || $feedbackId < 1) {
        header('Location: index.php?page=inquiries&notice=' . ($action === 'delete' ? 'feedback_delete_error' : 'reply_invalid'));
        exit();
    }

    $kind = post_string('kind') === 'review' ? 'review' : 'product';

    // Look the feedback up first so we know who to notify.
    if ($kind === 'review') {
        $lookup = $conn->prepare('SELECT r.id, r.user_id, r.photos, o.order_number AS product_name FROM order_reviews r JOIN orders o ON o.id = r.order_id WHERE r.id = ?');
    } else {
        $lookup = $conn->prepare('SELECT f.id, f.user_id, p.name AS product_name FROM product_feedback f JOIN products p ON p.id = f.product_id WHERE f.id = ?');
    }
    $lookup->bind_param('i', $feedbackId);
    $lookup->execute();
    $target = $lookup->get_result()->fetch_assoc();
    if (!$target) {
        header('Location: index.php?page=inquiries&notice=feedback_missing');
        exit();
    }
    $subject = $kind === 'review' ? 'your review of order ' . $target['product_name'] : 'your feedback about "' . $target['product_name'] . '"';
    $table = $kind === 'review' ? 'order_reviews' : 'product_feedback';

    // Delete the feedback and notify the customer.
    if ($action === 'delete') {
        $delete = $conn->prepare('DELETE FROM ' . $table . ' WHERE id = ?');
        $delete->bind_param('i', $feedbackId);
        $delete->execute();
        if ($delete->affected_rows > 0) {
            if ($kind === 'review') {
                delete_review_photos($target['photos']);
            }
            notify_user($conn, (int) $target['user_id'], 'feedback_deleted', ucfirst($subject) . ' was deleted by the admin.', 'index.php?page=my_feedback');
            header('Location: index.php?page=inquiries&notice=feedback_deleted');
        } else {
            header('Location: index.php?page=inquiries&notice=feedback_missing');
        }
        exit();
    }

    // Save the reply.
    $response = trim(post_string('response'));
    if ($response === '' || mb_strlen($response) > 2000) {
        header('Location: index.php?page=inquiries&notice=reply_invalid');
        exit();
    }

    $responderId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('UPDATE ' . $table . ' SET response = ?, responded_by = ?, responded_at = NOW() WHERE id = ?');
    $stmt->bind_param('sii', $response, $responderId, $feedbackId);
    $saved = false;
    try {
        $saved = $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        error_log('Reply failed: ' . $exception->getMessage());
    }
    if (!$saved) {
        header('Location: index.php?page=inquiries&notice=reply_error');
        exit();
    }

    notify_user($conn, (int) $target['user_id'], 'feedback_reply', 'The admin replied to ' . $subject . '.', 'index.php?page=my_feedback');
    header('Location: index.php?page=inquiries&notice=reply_saved');
    exit();
}

// Product feedback (older) and order reviews are shown together, unanswered first, newest first.
$items = [];
$productRows = $conn->query('SELECT f.id, f.message, f.response, f.created_at, f.responded_at, p.name AS product_name, p.product_code, u.full_name AS user_name, u.email, r.full_name AS responder_name, r.role AS responder_role, r.staff_role AS responder_staff_role FROM product_feedback f JOIN products p ON p.id = f.product_id JOIN users u ON u.id = f.user_id LEFT JOIN users r ON r.id = f.responded_by ORDER BY f.created_at DESC LIMIT 100');
while ($productRows && ($row = $productRows->fetch_assoc())) {
    $row['kind'] = 'product';
    $items[] = $row;
}
$reviewRows = $conn->query('SELECT v.id, v.rating, v.message, v.photos, v.response, v.created_at, v.responded_at, o.id AS order_id, o.order_number, u.full_name AS user_name, u.email, r.full_name AS responder_name, r.role AS responder_role, r.staff_role AS responder_staff_role FROM order_reviews v JOIN orders o ON o.id = v.order_id JOIN users u ON u.id = v.user_id LEFT JOIN users r ON r.id = v.responded_by ORDER BY v.created_at DESC LIMIT 100');
while ($reviewRows && ($row = $reviewRows->fetch_assoc())) {
    $row['kind'] = 'review';
    $row['items_text'] = '';
    $names = [];
    foreach (load_order_items($conn, (int) $row['order_id']) as $orderItem) {
        $names[] = $orderItem['product_name'] . ' x' . (int) $orderItem['quantity'];
    }
    $row['items_text'] = implode(', ', $names);
    $items[] = $row;
}
usort($items, function ($x, $y) {
    $ax = $x['response'] === null ? 0 : 1;
    $ay = $y['response'] === null ? 0 : 1;
    return $ax <=> $ay ?: strcmp($y['created_at'], $x['created_at']);
});
// Notice messages shown at the top of the page.
$notices = [
    'reply_saved' => 'Response sent to the user.',
    'reply_invalid' => 'Enter a response of up to 2,000 characters.',
    'reply_error' => 'The response could not be saved.',
    'feedback_deleted' => 'User feedback deleted. The user was notified.',
    'feedback_delete_error' => 'The feedback could not be deleted.',
    'feedback_missing' => 'That feedback no longer exists.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback | BuildQuin</title>
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
                <p class="eyebrow">CUSTOMER FEEDBACK</p>
                <h1>Customer feedback and order reviews</h1>
                <p>Reply to your customers' ratings, pictures and comments.</p>
            </div>
        </div>
        <?php if (isset($notices[$notice])): ?>
            <div
                class="notice <?= in_array($notice, ['reply_error', 'reply_invalid', 'feedback_delete_error', 'feedback_missing'], true) ? 'error' : '' ?>"
                role="status"
            ><?= e($notices[$notice]) ?></div>
        <?php endif; ?>
        <?php if ($items): ?>
            <div class="feedback-list">
                <?php foreach ($items as $item): $isReview = $item['kind'] === 'review'; ?>
                    <article class="feedback-item">
                        <div class="feedback-meta">
                            <div>
                                <?php if ($isReview): ?>
                                    <span class="eyebrow">ORDER REVIEW</span>
                                    <h2><?= e($item['order_number']) ?> <?= review_stars((int) $item['rating']) ?><span class="review-score"><?= (int) $item['rating'] ?>/5</span></h2>
                                    <p><?= e($item['user_name']) ?> · <?= e($item['email']) ?></p>
                                    <?php if ($item['items_text'] !== ''): ?>
                                        <p class="review-items"><?= e($item['items_text']) ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="eyebrow">PRODUCT</span>
                                    <h2><?= e($item['product_name']) ?></h2>
                                    <p><?= e($item['product_code']) ?> · <?= e($item['user_name']) ?> · <?= e($item['email']) ?></p>
                                <?php endif; ?>
                            </div>
                            <time><?= e(date('M j, Y g:i a', strtotime($item['created_at']))) ?></time>
                        </div>
                        <?php if ($item['message'] !== null && $item['message'] !== ''): ?>
                            <p class="feedback-message"><?= nl2br(e($item['message'])) ?></p>
                        <?php endif; ?>
                        <?php if ($isReview): $photos = review_photo_list($item['photos']); if ($photos): ?>
                            <div class="review-photos">
                                <?php foreach ($photos as $photo): ?>
                                    <img
                                        src="../<?= e($photo) ?>"
                                        alt="Review photo"
                                        data-lightbox
                                        data-lightbox-group="rv<?= (int) $item['id'] ?>"
                                        loading="lazy"
                                    >
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endif; ?>
                        <?php if ($item['response'] !== null): ?>
                            <div class="admin-response">
                                <strong><?= e($item['responder_name'] ?? 'BuildQuin team') ?><?php if ($item['responder_name'] !== null): ?> (<?= e(role_display($item['responder_role'], $item['responder_staff_role'])) ?>)<?php endif; ?></strong>
                                <p><?= nl2br(e($item['response'])) ?></p>
                                <?php if ($item['responded_at'] !== null): ?>
                                    <time><?= e(date('M j, Y g:i a', strtotime($item['responded_at']))) ?></time>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="feedback-actions">
                            <form class="reply-form" action="index.php?page=inquiries" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="feedback_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="kind" value="<?= e($item['kind']) ?>">
                                <input type="hidden" name="action" value="reply">
                                <label for="reply-<?= e($item['kind']) ?>-<?= (int) $item['id'] ?>"><?= $item['response'] === null ? 'Your response' : 'Update response' ?></label>
                                <textarea
                                    id="reply-<?= e($item['kind']) ?>-<?= (int) $item['id'] ?>"
                                    name="response"
                                    maxlength="2000"
                                    rows="3"
                                    required
                                ><?= e($item['response']) ?></textarea>
                                <button type="submit"><?= $item['response'] === null ? 'Send response' : 'Update response' ?></button>
                            </form>
                            <form
                                class="feedback-delete-form"
                                action="index.php?page=inquiries"
                                method="post"
                                data-confirm
                                data-confirm-title="Delete feedback"
                                data-confirm-message="Do you want to delete this user's feedback? This also removes its response and cannot be undone."
                            >
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="feedback_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="kind" value="<?= e($item['kind']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit">Delete feedback</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">No feedback or reviews yet.</div>
        <?php endif; ?>
    </main>

    <script src="../js/lightbox.js" defer></script>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>
