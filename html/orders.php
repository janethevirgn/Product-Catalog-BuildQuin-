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

require_once __DIR__ . '/../db/db.php';

$staffId = (int) $_SESSION['user_id'];
$statusTabs = [
    'pending' => 'Pending',
    'accepted' => 'Preparing',
    'shipping' => 'Shipping',
    'completed' => 'Completed',
    'declined' => 'Declined',
    'cancelled' => 'Cancelled',
    'all' => 'All orders',
];

function orders_redirect(string $notice, array $extra = []): void
{
    $query = array_merge(['page' => 'orders', 'notice' => $notice], $extra);
    header('Location: index.php?' . http_build_query($query));
    exit();
}

/* ----------------------------------------------------------------------- actions --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = filter_var(post_string('order_id'), FILTER_VALIDATE_INT);
    $action = post_string('action');
    $returnStatus = post_string('return_status');
    if (!isset($statusTabs[$returnStatus])) {
        $returnStatus = 'pending';
    }
    $back = ['status' => $returnStatus];
    if (!$orderId || $orderId < 1 || !in_array($action, ['accept', 'decline', 'ship', 'complete'], true)) {
        orders_redirect('order_invalid', $back);
    }

    $lookup = $conn->prepare('SELECT id, order_number, user_id, status FROM orders WHERE id = ?');
    $lookup->bind_param('i', $orderId);
    $lookup->execute();
    $order = $lookup->get_result()->fetch_assoc();
    if (!$order) {
        orders_redirect('order_missing', $back);
    }
    $orderNumber = $order['order_number'];
    $customerId = (int) $order['user_id'];
    $link = 'index.php?page=my_orders';

    if ($action === 'accept') {
        $shortProduct = '';
        try {
            $conn->begin_transaction();
            $lock = $conn->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $lock->bind_param('i', $orderId);
            $lock->execute();
            $locked = $lock->get_result()->fetch_assoc();
            if (!$locked || $locked['status'] !== 'pending') {
                $conn->rollback();
                orders_redirect('order_changed', $back);
            }

            $itemStmt = $conn->prepare('SELECT product_id, product_name, quantity FROM order_items WHERE order_id = ?');
            $itemStmt->bind_param('i', $orderId);
            $itemStmt->execute();
            $itemRows = $itemStmt->get_result();
            $items = [];
            while ($itemRow = $itemRows->fetch_assoc()) {
                $items[] = $itemRow;
            }

            $deduct = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                $deducted = false;
                if ($productId > 0) {
                    $deduct->bind_param('iii', $quantity, $productId, $quantity);
                    $deduct->execute();
                    $deducted = $deduct->affected_rows === 1;
                }
                if (!$deducted) {
                    $shortProduct = (string) $item['product_name'];
                    break;
                }
            }

            if ($shortProduct !== '') {
                $conn->rollback();
                orders_redirect('stock_short', $back + ['item' => $shortProduct]);
            }

            $accept = $conn->prepare("UPDATE orders SET status = 'accepted', handled_by = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $accept->bind_param('ii', $staffId, $orderId);
            $accept->execute();
            $conn->commit();
        } catch (mysqli_sql_exception $exception) {
            try {
                $conn->rollback();
            } catch (mysqli_sql_exception $ignored) {
            }
            error_log('Accept order failed: ' . $exception->getMessage());
            orders_redirect('order_error', $back);
        }
        notify_user($conn, $customerId, 'order_accepted', 'The admin is preparing your order. (Order ' . $orderNumber . ')', $link);
        orders_redirect('order_accepted', ['status' => 'accepted']);
    }

    if ($action === 'decline') {
        $reason = trim(post_string('reason'));
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }
        $reasonValue = $reason !== '' ? $reason : null;
        $decline = $conn->prepare("UPDATE orders SET status = 'declined', decline_reason = ?, handled_by = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $decline->bind_param('sii', $reasonValue, $staffId, $orderId);
        $decline->execute();
        if ($decline->affected_rows !== 1) {
            orders_redirect('order_changed', $back);
        }
        notify_user($conn, $customerId, 'order_declined', 'Your order has been declined due to some reasons. Please try again. (Order ' . $orderNumber . ')', $link);
        orders_redirect('order_declined', ['status' => 'declined']);
    }

    if ($action === 'ship') {
        $ship = $conn->prepare("UPDATE orders SET status = 'shipping', handled_by = ?, updated_at = NOW() WHERE id = ? AND status = 'accepted'");
        $ship->bind_param('ii', $staffId, $orderId);
        $ship->execute();
        if ($ship->affected_rows !== 1) {
            orders_redirect('order_changed', $back);
        }
        notify_user($conn, $customerId, 'order_shipping', 'Your order is on shipping. (Order ' . $orderNumber . ')', $link);
        orders_redirect('order_shipping', ['status' => 'shipping']);
    }

    // complete
    $complete = $conn->prepare("UPDATE orders SET status = 'completed', handled_by = ?, updated_at = NOW() WHERE id = ? AND status = 'shipping'");
    $complete->bind_param('ii', $staffId, $orderId);
    $complete->execute();
    if ($complete->affected_rows !== 1) {
        orders_redirect('order_changed', $back);
    }
    notify_user($conn, $customerId, 'order_completed', 'Your order has been completed. Thank you for shopping with BuildQuin! (Order ' . $orderNumber . ')', $link);
    orders_redirect('order_completed', ['status' => 'completed']);
}

/* -------------------------------------------------------------------------- list --- */
$activeStatus = get_string('status');
if (!isset($statusTabs[$activeStatus])) {
    $activeStatus = 'pending';
}
$search = trim(get_string('q'));

$counts = array_fill_keys(array_keys($statusTabs), 0);
$countResult = $conn->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
while ($countRow = $countResult->fetch_assoc()) {
    if (isset($counts[$countRow['status']])) {
        $counts[$countRow['status']] = (int) $countRow['total'];
    }
    $counts['all'] += (int) $countRow['total'];
}

$conditions = [];
$types = '';
$params = [];
if ($activeStatus !== 'all') {
    $conditions[] = 'o.status = ?';
    $types .= 's';
    $params[] = $activeStatus;
}
if ($search !== '') {
    $conditions[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.contact_number LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$sql = 'SELECT o.*, u.email AS account_email, h.full_name AS handler_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN users h ON h.id = o.handled_by'
    . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
    . ' ORDER BY (o.status = \'pending\') DESC, o.id DESC LIMIT 100';
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $row['items'] = load_order_items($conn, (int) $row['id']);
    $orders[] = $row;
}

$noticeKey = get_string('notice');
$noticeItem = get_string('item');
$notices = [
    'order_accepted' => ['Order accepted. The customer was told you are preparing it, and stock was updated.', ''],
    'order_declined' => ['Order declined. The customer was notified.', ''],
    'order_shipping' => ['Order marked as shipping. The customer was notified.', ''],
    'order_completed' => ['Order marked as completed. The customer was notified.', ''],
    'order_changed' => ['That order was already updated by someone else. The list has been refreshed.', 'error'],
    'order_missing' => ['That order no longer exists.', 'error'],
    'order_invalid' => ['That order action is not valid.', 'error'],
    'order_error' => ['The order could not be updated. Please try again.', 'error'],
    'stock_short' => ['Not enough stock' . ($noticeItem !== '' ? ' for "' . $noticeItem . '"' : '') . ' to accept this order. Update the product stock first, or decline the order.', 'error'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders | BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/orders.css?v=bq6">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body class="shop-page">
    <?php include __DIR__ . '/navigation.php'; ?>

    <main class="shop-wrap">
        <div class="shop-heading">
            <div>
                <p class="eyebrow">ORDER MANAGEMENT</p>
                <h1>Customer orders</h1>
                <p>Accept or decline new orders, then move them to shipping and completed.</p>
            </div>
            <form class="order-search" action="index.php" method="get">
                <input type="hidden" name="page" value="orders">
                <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search order no., name or contact...">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if (isset($notices[$noticeKey])): ?>
            <div class="notice <?= e($notices[$noticeKey][1]) ?>" role="status"><?= e($notices[$noticeKey][0]) ?></div>
        <?php endif; ?>

        <nav class="status-tabs" aria-label="Order status">
            <?php foreach ($statusTabs as $key => $label): ?>
                <a class="<?= $activeStatus === $key ? 'active' : '' ?>" href="index.php?<?= e(http_build_query(['page' => 'orders', 'status' => $key, 'q' => $search])) ?>"><?= e($label) ?> <span><?= (int) $counts[$key] ?></span></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$orders): ?>
            <div class="empty-card">
                <div class="empty-icon"><span class="mi">inventory_2</span></div>
                <h2>No orders here</h2>
                <p><?= $search !== '' ? 'No orders matched your search.' : 'Nothing in this list right now. New customer orders will appear here and in the notification bell.' ?></p>
            </div>
        <?php else: ?>
            <div class="order-list">
                <?php foreach ($orders as $order): $status = $order['status']; ?>
                    <article class="order-card <?= $status === 'pending' ? 'needs-action' : '' ?>">
                        <header class="order-head">
                            <div>
                                <h2><?= e($order['order_number']) ?></h2>
                                <p class="muted-small">Placed <?= e(date('M j, Y g:i a', strtotime($order['created_at']))) ?><?= $order['handler_name'] ? ' &middot; handled by ' . e($order['handler_name']) : '' ?></p>
                            </div>
                            <span class="status-pill status-<?= e($status) ?>"><?= e($statusTabs[$status] ?? ucfirst($status)) ?></span>
                        </header>

                        <div class="customer-box">
                            <div>
                                <div class="receipt-label">Customer</div>
                                <strong><?= e($order['customer_name']) ?></strong>
                                <span class="muted-small"><?= e($order['account_email'] ?? 'Account removed') ?></span>
                            </div>
                            <div>
                                <div class="receipt-label">Contact</div>
                                <strong><?= e($order['contact_number']) ?></strong>
                                <span class="muted-small"><?= e($order['payment_method']) ?></span>
                            </div>
                            <div class="wide-cell">
                                <div class="receipt-label">Deliver to</div>
                                <span><?= e($order['shipping_address']) ?></span>
                            </div>
                        </div>
                        <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?><p class="order-note"><strong>Customer note:</strong> <?= e($order['notes']) ?></p><?php endif; ?>
                        <?php if ($status === 'declined' && $order['decline_reason']): ?><p class="decline-reason"><strong>Decline reason:</strong> <?= e($order['decline_reason']) ?></p><?php endif; ?>

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
                            <div class="order-actions-wrap">
                                <?php if ($status === 'pending'): ?>
                                    <form class="inline-form" action="index.php?page=orders" method="post" data-confirm data-confirm-title="Accept order" data-confirm-message="Accept this order? Stock will be deducted and the customer will be told you are preparing it.">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="return_status" value="<?= e($activeStatus) ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button class="btn-primary" type="submit">Accept order</button>
                                    </form>
                                    <form class="inline-form decline-form" action="index.php?page=orders" method="post" data-confirm data-confirm-title="Decline order" data-confirm-message="Decline this order? The customer will be notified to try again.">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="return_status" value="<?= e($activeStatus) ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <input type="text" name="reason" maxlength="500" placeholder="Reason (optional, shown to customer)">
                                        <button class="btn-danger-outline" type="submit">Decline</button>
                                    </form>
                                <?php elseif ($status === 'accepted'): ?>
                                    <form class="inline-form" action="index.php?page=orders" method="post" data-confirm data-confirm-title="Mark as shipping" data-confirm-message="Mark this order as on shipping? The customer will be notified.">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="return_status" value="<?= e($activeStatus) ?>">
                                        <input type="hidden" name="action" value="ship">
                                        <button class="btn-primary" type="submit">Mark as shipping</button>
                                    </form>
                                <?php elseif ($status === 'shipping'): ?>
                                    <form class="inline-form" action="index.php?page=orders" method="post" data-confirm data-confirm-title="Complete order" data-confirm-message="Mark this order as completed (delivered and paid)?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                        <input type="hidden" name="return_status" value="<?= e($activeStatus) ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button class="btn-primary" type="submit">Mark as completed</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted-small">No further action needed.</span>
                                <?php endif; ?>
                            </div>
                            <div class="totals compact">
                                <div><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                                <div><span>VAT (<?= rtrim(rtrim(number_format((float) $order['vat_rate'], 2), '0'), '.') ?>%)</span><span><?= money($order['vat_amount']) ?></span></div>
                                <div class="grand"><span>Total</span><span><?= money($order['total']) ?></span></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>
