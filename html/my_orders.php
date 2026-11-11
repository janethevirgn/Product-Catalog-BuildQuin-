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

require_once __DIR__ . '/../db/db.php';

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = filter_var(post_string('order_id'), FILTER_VALIDATE_INT);

    if (post_string('action') === 'review' && $orderId && $orderId > 0) {
        $rating = filter_var(post_string('rating'), FILTER_VALIDATE_INT);
        $message = trim(post_string('message'));
        if (!$rating || $rating < 1 || $rating > 5 || mb_strlen($message) > 2000) {
            header('Location: index.php?page=my_orders&notice=review_invalid');
            exit();
        }
        $own = $conn->prepare("SELECT order_number, customer_name FROM orders WHERE id = ? AND user_id = ? AND status = 'completed'");
        $own->bind_param('ii', $orderId, $userId);
        $own->execute();
        $order = $own->get_result()->fetch_assoc();
        if (!$order) {
            header('Location: index.php?page=my_orders&notice=review_unavailable');
            exit();
        }
        $already = $conn->prepare('SELECT id FROM order_reviews WHERE order_id = ?');
        $already->bind_param('i', $orderId);
        $already->execute();
        if ($already->get_result()->fetch_assoc()) {
            header('Location: index.php?page=my_orders&notice=review_exists');
            exit();
        }
        $photoError = '';
        $photos = save_review_photos('photos', $photoError);
        if ($photoError !== '') {
            header('Location: index.php?page=my_orders&notice=' . $photoError);
            exit();
        }
        $photoValue = $photos ? implode('|', $photos) : null;
        $messageValue = $message === '' ? null : $message;
        $insert = $conn->prepare('INSERT INTO order_reviews (order_id, user_id, rating, message, photos) VALUES (?, ?, ?, ?, ?)');
        $insert->bind_param('iiiss', $orderId, $userId, $rating, $messageValue, $photoValue);
        $saved = false;
        try {
            $saved = $insert->execute();
        } catch (mysqli_sql_exception $exception) {
            error_log('Review failed: ' . $exception->getMessage());
        }
        if ($saved) {
            notify_staff($conn, 'feedback_new', $order['customer_name'] . ' rated order ' . $order['order_number'] . ' ' . $rating . '/5.', 'index.php?page=inquiries');
            header('Location: index.php?page=my_orders&notice=review_saved');
            exit();
        }
        delete_review_photos($photoValue);
        header('Location: index.php?page=my_orders&notice=review_error');
        exit();
    }

    if (post_string('action') === 'cancel' && $orderId && $orderId > 0) {
        $cancel = $conn->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'");
        $cancel->bind_param('ii', $orderId, $userId);
        $cancel->execute();
        if ($cancel->affected_rows === 1) {
            $info = $conn->prepare('SELECT order_number, customer_name FROM orders WHERE id = ?');
            $info->bind_param('i', $orderId);
            $info->execute();
            $order = $info->get_result()->fetch_assoc();
            if ($order) {
                notify_staff($conn, 'order_cancelled', 'Order ' . $order['order_number'] . ' was cancelled by ' . $order['customer_name'] . '.', 'index.php?page=orders&status=cancelled');
            }
            header('Location: index.php?page=my_orders&notice=order_cancelled');
            exit();
        }
    }
    header('Location: index.php?page=my_orders&notice=order_error');
    exit();
}

$stmt = $conn->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
$reviewStmt = $conn->prepare('SELECT rating, message, photos, response, created_at FROM order_reviews WHERE order_id = ?');
while ($row = $result->fetch_assoc()) {
    $row['items'] = load_order_items($conn, (int) $row['id']);
    $orderKey = (int) $row['id'];
    $reviewStmt->bind_param('i', $orderKey);
    $reviewStmt->execute();
    $row['review'] = $reviewStmt->get_result()->fetch_assoc() ?: null;
    $orders[] = $row;
}

$noticeKey = get_string('notice');
$justPlaced = $noticeKey === 'order_placed';
$notices = [
    'order_cancelled' => ['Your order was cancelled.', ''],
    'review_saved' => ['Feedback Submitted', 'toast'],
    'review_invalid' => ['Please choose 1 to 5 stars. Your message can be up to 2000 characters.', 'error'],
    'review_unavailable' => ['Only delivered orders can be rated.', 'error'],
    'review_exists' => ['You already rated this order.', 'error'],
    'review_photo_limit' => ['You can upload up to 3 pictures.', 'error'],
    'review_photo_invalid' => ['Pictures must be JPG, PNG, WEBP or GIF and up to 3 MB each.', 'error'],
    'review_error' => ['Your feedback could not be saved. Please try again.', 'error'],
    'order_error' => ['That order could not be changed. It may already have been handled by the admin.', 'error'],
];

$statusMessages = [
    'pending' => "Waiting for the admin's response.",
    'accepted' => 'The admin is preparing your order.',
    'shipping' => 'Your order is on shipping.',
    'completed' => 'Order completed. Thank you for shopping with BuildQuin!',
    'declined' => 'Your order has been declined due to some reasons. Please try again.',
    'cancelled' => 'You cancelled this order.',
];
$steps = ['pending' => 'Placed', 'accepted' => 'Preparing', 'shipping' => 'Shipping', 'completed' => 'Completed'];
$stepOrder = array_keys($steps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/orders.css?v=bq6">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/lightbox.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body class="shop-page">
    <?php include __DIR__ . '/navigation.php'; ?>

    <main class="shop-wrap narrow">
        <div class="shop-heading">
            <div>
                <p class="eyebrow">YOUR PURCHASES</p>
                <h1>My Orders</h1>
                <p>Track every order you placed with BuildQuin.</p>
            </div>
            <a class="ghost-link" href="index.php?page=products">Shop more <span class="mi">arrow_forward</span></a>
        </div>

        <?php if ($justPlaced): ?>
            <div class="success-card" role="status">
                <span class="success-check"><span class="mi">check</span></span>
                <div>
                    <strong>Your order has been placed.</strong>
                    <p>Wait for the admin's response. We will notify you here and on the bell once it is accepted or declined.</p>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($notices[$noticeKey]) && $notices[$noticeKey][1] === 'toast'): ?>
            <div class="mini-toast" id="miniToast" role="status"><span class="mi">check_circle</span><?= e($notices[$noticeKey][0]) ?></div>
        <?php elseif (isset($notices[$noticeKey])): ?>
            <div class="notice <?= e($notices[$noticeKey][1]) ?>" role="status"><?= e($notices[$noticeKey][0]) ?></div>
        <?php endif; ?>

        <?php if (!$orders): ?>
            <div class="empty-card">
                <div class="empty-icon"><span class="mi">inventory_2</span></div>
                <h2>No orders yet</h2>
                <p>When you place an order it will show up here with live status updates.</p>
                <a class="btn-primary" href="index.php?page=products">Browse products</a>
            </div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($orders as $order):
                    $status = $order['status'];
                    $reached = array_search($status, $stepOrder, true);
                    $isHighlighted = $justPlaced && (int) $order['id'] === (int) get_string('order');
                ?>
                    <article class="order-card <?= $isHighlighted ? 'highlight' : '' ?>">
                        <header class="order-head">
                            <div>
                                <h2><?= e($order['order_number']) ?></h2>
                                <p class="muted-small">Placed <?= e(date('M j, Y g:i a', strtotime($order['created_at']))) ?></p>
                            </div>
                            <span class="status-pill status-<?= e($status) ?>"><?= e(order_status_label($status)) ?></span>
                        </header>

                        <?php if ($reached !== false): ?>
                            <ol class="tracker" aria-label="Order progress">
                                <?php foreach ($stepOrder as $index => $stepKey): ?>
                                    <li class="<?= $index < $reached ? 'done' : ($index === $reached ? 'current' : '') ?>"><span><?= $index < $reached ? '<span class="mi">check</span>' : $index + 1 ?></span><?= e($steps[$stepKey]) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <p class="status-message-line status-line-<?= e($status) ?>"><?= e($statusMessages[$status] ?? '') ?></p>
                        <?php if ($status === 'declined' && $order['decline_reason'] !== null && $order['decline_reason'] !== ''): ?>
                            <p class="decline-reason"><strong>Admin's note:</strong> <?= e($order['decline_reason']) ?></p>
                        <?php endif; ?>

                        <table class="items-table">
                            <thead><tr><th>Item</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td><?= e($item['product_name']) ?><small><?= e($item['product_code']) ?></small></td>
                                        <td class="num"><?= money($item['unit_price']) ?></td>
                                        <td class="num"><?= (int) $item['quantity'] ?></td>
                                        <td class="num"><?= money($item['line_total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="order-foot">
                            <div class="ship-info">
                                <div class="receipt-label">Deliver to</div>
                                <strong><?= e($order['customer_name']) ?></strong>
                                <span><?= e($order['shipping_address']) ?></span>
                                <span class="muted-small">Contact: <?= e($order['contact_number']) ?> &middot; <?= e($order['payment_method']) ?></span>
                                <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?><span class="muted-small">Note: <?= e($order['notes']) ?></span><?php endif; ?>
                            </div>
                            <div class="totals compact">
                                <div><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                                <div><span>VAT (<?= rtrim(rtrim(number_format((float) $order['vat_rate'], 2), '0'), '.') ?>%)</span><span><?= money($order['vat_amount']) ?></span></div>
                                <div class="grand"><span>Total</span><span><?= money($order['total']) ?></span></div>
                            </div>
                        </div>

                        <?php if ($status === 'pending'): ?>
                            <form class="order-actions" action="index.php?page=my_orders" method="post" data-confirm data-confirm-title="Cancel order" data-confirm-message="Cancel this order? You can place a new one anytime.">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button class="btn-danger-outline" type="submit">Cancel order</button>
                            </form>
                        <?php elseif ($status === 'completed'): ?>
                            <?php $review = $order['review']; ?>
                            <?php if ($review): ?>
                                <div class="my-review">
                                    <div class="receipt-label">Your review</div>
                                    <?= review_stars((int) $review['rating']) ?>
                                    <?php if ($review['message'] !== null && $review['message'] !== ''): ?><p><?= nl2br(e($review['message'])) ?></p><?php endif; ?>
                                    <?php $reviewPhotos = review_photo_list($review['photos']); if ($reviewPhotos): ?>
                                        <div class="review-photos">
                                            <?php foreach ($reviewPhotos as $photo): ?>
                                                <img src="../<?= e($photo) ?>" alt="Review photo" data-lightbox data-lightbox-group="rev<?= (int) $order['id'] ?>" loading="lazy">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($review['response'] !== null && $review['response'] !== ''): ?>
                                        <p class="review-reply"><strong>BuildQuin replied:</strong> <?= e($review['response']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="order-actions">
                                    <button class="rate-btn" type="button" data-rate-order="<?= (int) $order['id'] ?>" data-rate-number="<?= e($order['order_number']) ?>"><span class="rate-circle"><span class="mi">star</span></span> Rate order</button>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($status === 'declined'): ?>
                            <div class="order-actions"><a class="btn-primary" href="index.php?page=products">Try again</a></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <div class="rate-backdrop" id="rateModal" hidden>
        <form class="rate-dialog" action="index.php?page=my_orders" method="post" enctype="multipart/form-data" id="rateForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="order_id" id="rateOrderId" value="">
            <input type="hidden" name="rating" id="rateValue" value="">
            <button class="rate-close" type="button" id="rateClose" aria-label="Close"><span class="mi">close</span></button>
            <h2>Do you like your order?</h2>
            <p class="muted-small" id="rateOrderNo"></p>
            <div class="star-pick" id="starPick" role="radiogroup" aria-label="Rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-btn" data-star="<?= $i ?>" role="radio" aria-checked="false" aria-label="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>"><span class="mi">star</span></button>
                <?php endfor; ?>
            </div>
            <p class="star-word" id="starWord">Tap a star to rate</p>
            <div class="rate-lower" id="rateLower" hidden>
                <label for="rateMessage">Tell us more (optional)</label>
                <textarea name="message" id="rateMessage" rows="4" maxlength="2000" placeholder="How was the product and the delivery?"></textarea>
                <label class="upload-box" for="ratePhotos"><span class="mi">add_photo_alternate</span> Add pictures (up to 3, 3 MB each)</label>
                <input type="file" name="photos[]" id="ratePhotos" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
                <div class="upload-preview" id="uploadPreview"></div>
                <button class="btn-primary wide" type="submit">Submit feedback</button>
            </div>
        </form>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('rateModal');
        if (!modal) return;
        var words = ['Poor', 'Fair', 'Good', 'Very good', 'Excellent'];
        var stars = Array.prototype.slice.call(modal.querySelectorAll('.star-btn'));
        var lower = document.getElementById('rateLower');
        var input = document.getElementById('rateValue');
        var word = document.getElementById('starWord');
        var files = document.getElementById('ratePhotos');
        var preview = document.getElementById('uploadPreview');
        function paint(n) { stars.forEach(function (b, i) { b.classList.toggle('on', i < n); b.setAttribute('aria-checked', i === n - 1 ? 'true' : 'false'); }); }
        function reset() { input.value = ''; paint(0); word.textContent = 'Tap a star to rate'; lower.hidden = true; files.value = ''; preview.innerHTML = ''; document.getElementById('rateMessage').value = ''; }
        function close() { modal.hidden = true; document.body.classList.remove('modal-open'); }
        document.querySelectorAll('[data-rate-order]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                reset();
                document.getElementById('rateOrderId').value = btn.dataset.rateOrder;
                document.getElementById('rateOrderNo').textContent = 'Order ' + btn.dataset.rateNumber;
                modal.hidden = false; document.body.classList.add('modal-open');
            });
        });
        stars.forEach(function (b) {
            b.addEventListener('click', function () {
                var n = Number(b.dataset.star);
                input.value = n; paint(n); word.textContent = n + '/5 \u2013 ' + words[n - 1]; lower.hidden = false;
            });
            b.addEventListener('mouseenter', function () { paint(Number(b.dataset.star)); });
            b.addEventListener('mouseleave', function () { paint(Number(input.value) || 0); });
        });
        files.addEventListener('change', function () {
            preview.innerHTML = '';
            var list = Array.prototype.slice.call(files.files);
            if (list.length > 3) { alert('You can upload up to 3 pictures.'); files.value = ''; return; }
            for (var i = 0; i < list.length; i++) {
                if (list[i].size > 3 * 1024 * 1024) { alert(list[i].name + ' is larger than 3 MB.'); files.value = ''; preview.innerHTML = ''; return; }
                var img = document.createElement('img'); img.src = URL.createObjectURL(list[i]); img.alt = ''; preview.appendChild(img);
            }
        });
        document.getElementById('rateForm').addEventListener('submit', function (e) { if (!input.value) { e.preventDefault(); } });
        document.getElementById('rateClose').addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
        var toast = document.getElementById('miniToast');
        if (toast) setTimeout(function () { toast.classList.add('hide'); }, 3200);
    }());
    </script>
    <script src="../js/lightbox.js" defer></script>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>
