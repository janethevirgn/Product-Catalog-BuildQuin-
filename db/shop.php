<?php
// Small shared helpers for notifications, the cart, orders, payment modes and order reviews.
/*
 * Shared helpers for notifications, cart and orders.
 * Loaded from auth.php, so $conn (db.php) is already available where these are called.
 */

// VAT charged on every new order (percent). Orders already placed keep the rate they were made with.
const VAT_PERCENT = 0.3;

/** Formats a number as pesos, e.g. ₱1,250.00. */
function money($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/* ---------------------------------------------------------------- notifications --- */

/** Saves one notification (the bell) for one user. Failures are only logged. */
function notify_user(mysqli $conn, $userId, string $type, string $message, string $link = ''): void
{
    $userId = (int) $userId;
    if ($userId < 1) {
        return;
    }
    $message = mb_substr($message, 0, 500);
    $linkValue = $link !== '' ? $link : null;
    try {
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isss', $userId, $type, $message, $linkValue);
        $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        error_log('Notification failed: ' . $exception->getMessage());
    }
}

/** Sends the same notification to every admin and staff account. */
function notify_staff(mysqli $conn, string $type, string $message, string $link = '', int $exceptUserId = 0): void
{
    try {
        $result = $conn->query("SELECT id FROM users WHERE role IN ('admin', 'staff')");
        while ($row = $result->fetch_assoc()) {
            if ((int) $row['id'] !== $exceptUserId) {
                notify_user($conn, (int) $row['id'], $type, $message, $link);
            }
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('Staff notification failed: ' . $exception->getMessage());
    }
}

/** How many unread notifications the user has. */
function notification_unread_count(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

/** Turns a date/time into friendly text like "5 mins ago". */
function time_ago(string $datetime): string
{
    $time = strtotime($datetime);
    if (!$time) {
        return '';
    }
    $diff = max(0, time() - $time);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y', $time);
}

/* ------------------------------------------------------------------------- cart --- */

/** Total number of items in the user's cart. */
function cart_count(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM cart_items WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

/** The user's cart rows with product details and each line's total. */
function cart_lines(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare('SELECT c.product_id, c.quantity, p.product_code, p.name, p.price, p.stock, p.image_url, cat.category_name
        FROM cart_items c
        JOIN products p ON p.id = c.product_id
        JOIN categories cat ON cat.id = p.category_id
        WHERE c.user_id = ?
        ORDER BY c.id ASC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $lines = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['stock'] = (int) ($row['stock'] ?? 0);
        $row['quantity'] = (int) $row['quantity'];
        $row['line_total'] = round((float) $row['price'] * $row['quantity'], 2);
        $lines[] = $row;
    }
    return $lines;
}

/** Subtotal, VAT (VAT_PERCENT) and total, always rounded to centavos. */
function order_totals(float $subtotal): array
{
    $subtotal = round($subtotal, 2);
    $vat = round($subtotal * VAT_PERCENT / 100, 2);
    return ['subtotal' => $subtotal, 'vat' => $vat, 'total' => round($subtotal + $vat, 2)];
}

/* ----------------------------------------------------------------------- orders --- */

/** Joins a saved address into one line of text. */
function address_one_line(array $address): string
{
    $parts = [
        trim((string) $address['street_address']),
        'Brgy. ' . trim((string) $address['barangay']),
        trim((string) $address['city']),
        trim((string) $address['province']),
    ];
    $line = implode(', ', array_filter($parts, function ($part) {
        return $part !== '' && $part !== 'Brgy.';
    }));
    $postal = trim((string) ($address['postal_code'] ?? ''));
    return $postal !== '' ? $line . ' ' . $postal : $line;
}

/** Customer-friendly text for an order status. */
function order_status_label(string $status): string
{
    $labels = [
        'pending' => 'Waiting for admin',
        'accepted' => 'Preparing your order',
        'shipping' => 'On shipping',
        'completed' => 'Completed',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status);
}

/** Number of orders still waiting to be accepted. */
function pending_order_count(mysqli $conn): int
{
    $result = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'");
    return $result ? (int) $result->fetch_assoc()['total'] : 0;
}

/** Feedback still waiting for a reply: order reviews plus older product questions. */
function open_feedback_count(mysqli $conn): int
{
    $total = 0;
    try {
        $result = $conn->query('SELECT COUNT(*) AS total FROM product_feedback WHERE response IS NULL');
        $total += $result ? (int) $result->fetch_assoc()['total'] : 0;
        $reviews = $conn->query('SELECT COUNT(*) AS total FROM order_reviews WHERE response IS NULL');
        $total += $reviews ? (int) $reviews->fetch_assoc()['total'] : 0;
    } catch (mysqli_sql_exception $exception) {
        error_log('Feedback count failed: ' . $exception->getMessage());
    }
    return $total;
}

/* ------------------------------------------------------------------ payment modes --- */

/** Modes of payment the customer can choose at checkout. The label is what is saved on the order. */
function payment_methods(): array
{
    return [
        'cod' => ['label' => 'Cash on Delivery', 'icon' => 'payments', 'hint' => 'Pay when it arrives'],
        'gcash' => ['label' => 'GCash', 'icon' => 'smartphone', 'hint' => 'E-wallet'],
        'maya' => ['label' => 'Maya', 'icon' => 'account_balance_wallet', 'hint' => 'E-wallet (PayMaya)'],
        'landbank' => ['label' => 'Landbank', 'icon' => 'account_balance', 'hint' => 'Bank transfer'],
        'bdo' => ['label' => 'BDO', 'icon' => 'account_balance', 'hint' => 'Bank transfer'],
        'bpi' => ['label' => 'BPI', 'icon' => 'account_balance', 'hint' => 'Bank transfer'],
    ];
}

/* ------------------------------------------------------------------------ reviews --- */

/** Five stars, the first $rating of them yellow. */
function review_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '<span class="review-stars" role="img" aria-label="' . $rating . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="mi' . ($i <= $rating ? '' : ' off') . '">star</span>';
    }
    return $html . '</span>';
}

/** Photo paths are stored in one column, separated by "|". */
function review_photo_list(?string $stored): array
{
    $list = [];
    foreach (explode('|', (string) $stored) as $path) {
        $isReviewPath = preg_match('#^photos/review_[a-f0-9]{16}\.(jpg|png|webp|gif)$#', $path);
        // Only list a picture whose file really exists in photos/, so no broken-image icon appears.
        if ($isReviewPath && is_file(__DIR__ . '/../' . $path)) {
            $list[] = $path;
        }
    }
    return $list;
}

/** Deletes the picture files of a review from the photos/ folder. */
function delete_review_photos(?string $stored): void
{
    foreach (review_photo_list($stored) as $path) {
        $file = __DIR__ . '/../' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Saves up to 3 pictures (jpg, png, webp or gif, 3 MB each) from the form field "photos[]" into photos/.
 * Returns the saved paths; on a problem returns [] and sets $error to a notice key.
 */
function save_review_photos(string $field, string &$error): array
{
    $error = '';
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $candidates = [];
    foreach ($_FILES[$field]['name'] as $index => $originalName) {
        $code = (int) $_FILES[$field]['error'][$index];
        if ($code === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $candidates[] = [
            'tmp' => (string) $_FILES[$field]['tmp_name'][$index],
            'size' => (int) $_FILES[$field]['size'][$index],
            'code' => $code
        ];
    }
    if (count($candidates) > 3) {
        $error = 'review_photo_limit';
        return [];
    }
    $checked = [];
    foreach ($candidates as $file) {
        $info = $file['code'] === UPLOAD_ERR_OK
            && $file['size'] > 0
            && $file['size'] <= 3 * 1024 * 1024
            && is_uploaded_file($file['tmp'])
            ? @getimagesize($file['tmp'])
            : false;
        if (!$info || !isset($allowed[$info['mime'] ?? ''])) {
            $error = 'review_photo_invalid';
            return [];
        }
        $checked[] = ['tmp' => $file['tmp'], 'ext' => $allowed[$info['mime']]];
    }
    // Make sure the photos/ folder exists before saving into it.
    if (!is_dir(__DIR__ . '/../photos')) {
        @mkdir(__DIR__ . '/../photos', 0755, true);
    }
    $saved = [];
    foreach ($checked as $file) {
        $relative = 'photos/review_' . bin2hex(random_bytes(8)) . '.' . $file['ext'];
        if (!@move_uploaded_file($file['tmp'], __DIR__ . '/../' . $relative)) {
            foreach ($saved as $done) {
                @unlink(__DIR__ . '/../' . $done);
            }
            $error = 'review_photo_invalid';
            return [];
        }
        $saved[] = $relative;
    }
    return $saved;
}

/* ------------------------------------------------------------------ order items --- */

/** All product lines that belong to one order. */
function load_order_items(mysqli $conn, int $orderId): array
{
    $stmt = $conn->prepare('SELECT product_id, product_code, product_name, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $items = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    return $items;
}
?>
