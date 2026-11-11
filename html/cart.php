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
$lines = cart_lines($conn, $userId);

// A line can only be ticked for checkout when enough stock is available.
$subtotal = 0.0;
$itemCount = 0;
$hasProblem = false;
$selectableCount = 0;
foreach ($lines as $index => $line) {
    $lines[$index]['problem'] = $line['stock'] < 1 || $line['quantity'] > $line['stock'];
    if ($lines[$index]['problem']) {
        $hasProblem = true;
        continue;
    }
    $selectableCount++;
    $subtotal += $line['line_total'];
    $itemCount += $line['quantity'];
}
$totals = order_totals($subtotal);
$paymentMethods = payment_methods();

$addressStmt = $conn->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC');
$addressStmt->bind_param('i', $userId);
$addressStmt->execute();
$addressResult = $addressStmt->get_result();
$addresses = [];
while ($row = $addressResult->fetch_assoc()) {
    $addresses[] = $row;
}

$selectedAddressId = filter_var(get_string('address'), FILTER_VALIDATE_INT) ?: 0;
$selectedFound = false;
foreach ($addresses as $address) {
    if ((int) $address['id'] === $selectedAddressId) {
        $selectedFound = true;
    }
}
if (!$selectedFound && $addresses) {
    $selectedAddressId = (int) $addresses[0]['id'];
}

$notices = [
    'cart_added' => ['Item added to your cart.', ''],
    'cart_updated' => ['Cart updated.', ''],
    'cart_limited' => ['Quantity was limited to the available stock.', 'error'],
    'cart_removed' => ['Item removed from your cart.', ''],
    'cart_empty' => ['Your cart is empty. Add a product first.', 'error'],
    'cart_error' => ['That cart action could not be completed.', 'error'],
    'address_saved' => ['Address saved.', ''],
    'address_deleted' => ['Address removed.', ''],
    'address_invalid' => ['Please complete every required address field and use a valid contact number.', 'error'],
    'address_limit' => ['You can save up to 10 addresses. Remove one to add another.', 'error'],
    'address_required' => ['Choose or add a delivery address before placing your order.', 'error'],
    'stock_changed' => ['Some items are no longer available in the quantity you chose. Please adjust your cart.', 'error'],
    'order_error' => ['Your order could not be placed. Please try again.', 'error'],
    'cart_none_selected' => ['Tick at least one item in your cart to place an order.', 'error'],
    'payment_invalid' => ['Please choose one of the listed modes of payment.', 'error'],
];
$noticeKey = get_string('notice');
$userEmail = (string) $_SESSION['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart | BuildQuin</title>
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
                <p class="eyebrow">CHECKOUT</p>
                <h1>My Cart</h1>
                <p><?= $lines ? 'Tick the items you want to order. The others stay in your cart.' : 'Your cart is waiting for something great.' ?></p>
            </div>
            <a class="ghost-link" href="index.php?page=products"><span class="mi">arrow_back</span> Continue shopping</a>
        </div>

        <?php if (isset($notices[$noticeKey])): ?>
            <div class="notice <?= e($notices[$noticeKey][1]) ?>" role="status"><?= e($notices[$noticeKey][0]) ?></div>
        <?php endif; ?>

        <?php if (!$lines): ?>
            <div class="empty-card">
                <div class="empty-icon"><span class="mi">shopping_cart</span></div>
                <h2>Your cart is empty</h2>
                <p>Browse the catalog and press <strong>Add to Cart</strong> on anything you want to purchase.</p>
                <a class="btn-primary" href="index.php?page=products">Browse products</a>
            </div>
        <?php else: ?>
        <div class="cart-layout">
            <section class="panel cart-items" aria-label="Items in your cart">
                <div class="panel-title">
                    <label class="select-all"><input type="checkbox" id="selectAll" <?= $selectableCount > 0 ? 'checked' : 'disabled' ?>><span class="check-box"><span class="mi">check</span></span> Select all</label>
                    <span class="selected-count" id="selectedCount"></span>
                </div>
                <?php foreach ($lines as $line):
                    $outOfStock = $line['stock'] < 1;
                    $overStock = !$outOfStock && $line['quantity'] > $line['stock'];
                ?>
                    <article class="cart-line <?= $line['problem'] ? 'has-problem' : '' ?>">
                        <label class="line-check" title="<?= $line['problem'] ? 'Fix this item to select it' : 'Select this item' ?>">
                            <input type="checkbox" class="line-pick" name="selected[]" value="<?= (int) $line['product_id'] ?>" form="orderForm" data-price="<?= e((string) $line['price']) ?>" data-qty="<?= (int) $line['quantity'] ?>" aria-label="Select <?= e($line['name']) ?>" <?= $line['problem'] ? 'disabled' : 'checked' ?>>
                            <span class="check-box"><span class="mi">check</span></span>
                        </label>
                        <img src="<?= e(catalog_image_url($line['image_url'])) ?>" alt="" class="cart-thumb">
                        <div class="cart-info">
                            <h3><?= e($line['name']) ?></h3>
                            <p class="muted-small"><?= e($line['product_code']) ?> &middot; <?= e($line['category_name']) ?></p>
                            <p class="unit-price"><?= money($line['price']) ?> each</p>
                            <?php if ($outOfStock): ?>
                                <p class="stock-warning">Out of stock. Please remove this item.</p>
                            <?php elseif ($overStock): ?>
                                <p class="stock-warning">Only <?= (int) $line['stock'] ?> available. Reduce the quantity.</p>
                            <?php elseif ($line['stock'] <= 5): ?>
                                <p class="stock-low">Only <?= (int) $line['stock'] ?> left</p>
                            <?php endif; ?>
                        </div>
                        <div class="cart-controls">
                            <form class="qty-form" action="index.php?page=cart_update" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="product_id" value="<?= (int) $line['product_id'] ?>">
                                <button type="submit" name="qty" value="<?= max(1, $line['quantity'] - 1) ?>" <?= $line['quantity'] <= 1 ? 'disabled' : '' ?> aria-label="Decrease quantity">&minus;</button>
                                <span class="qty-value"><?= (int) $line['quantity'] ?></span>
                                <button type="submit" name="qty" value="<?= $line['quantity'] + 1 ?>" <?= $line['quantity'] >= $line['stock'] ? 'disabled' : '' ?> aria-label="Increase quantity">+</button>
                            </form>
                            <div class="line-amount"><?= money($line['line_total']) ?></div>
                            <form action="index.php?page=cart_remove" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="product_id" value="<?= (int) $line['product_id'] ?>">
                                <button class="link-danger" type="submit">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <aside class="panel receipt" aria-label="Checkout">
                <div class="receipt-head">
                    <span class="brand-mark-sm">BQ</span>
                    <div>
                        <strong>BuildQuin</strong>
                        <small>Hardware &amp; Architectural Supplies</small>
                    </div>
                </div>
                <p class="receipt-date"><?= e(date('M j, Y \a\t g:i A')) ?></p>

                <div class="receipt-section">
                    <div class="receipt-label">Customer</div>
                    <div class="customer-name"><?= e($_SESSION['full_name']) ?></div>
                    <div class="muted-small"><?= e($userEmail) ?></div>
                </div>

                <div class="receipt-section">
                    <div class="receipt-label">Deliver to</div>
                    <?php if ($addresses): ?>
                        <div class="address-list">
                            <?php foreach ($addresses as $address): ?>
                                <div class="address-option">
                                    <label>
                                        <input type="radio" name="address_id" form="orderForm" value="<?= (int) $address['id'] ?>" <?= (int) $address['id'] === $selectedAddressId ? 'checked' : '' ?> required>
                                        <span class="address-card">
                                            <strong><?= e($address['recipient_name']) ?> <em><?= e($address['label']) ?></em><?= (int) $address['is_default'] === 1 ? '<span class="default-tag">Default</span>' : '' ?></strong>
                                            <span><?= e(address_one_line($address)) ?></span>
                                            <span class="muted-small">Contact: <?= e($address['contact_number']) ?></span>
                                        </span>
                                    </label>
                                    <button class="address-remove" type="submit" form="addressDelete<?= (int) $address['id'] ?>" title="Remove this address" aria-label="Remove this address"><span class="mi">close</span></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted-small">No saved address yet. Add one below to continue.</p>
                    <?php endif; ?>

                    <details class="add-address" <?= $addresses ? '' : 'open' ?>>
                        <summary>+ Add a new address</summary>
                        <form class="address-form" action="index.php?page=address_save" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="form-grid">
                                <label>Label
                                    <input type="text" name="label" maxlength="50" placeholder="Home, Office..." value="<?= $addresses ? '' : 'Home' ?>">
                                </label>
                                <label>Receiver's full name *
                                    <input type="text" name="recipient_name" maxlength="100" required value="<?= e($_SESSION['full_name']) ?>">
                                </label>
                                <label>Contact number *
                                    <input type="tel" name="contact_number" maxlength="30" required placeholder="09XX XXX XXXX" pattern="[0-9+()\- ]{7,30}">
                                </label>
                                <label class="span-2">House no., street, building *
                                    <input type="text" name="street_address" maxlength="255" required placeholder="Blk 1 Lot 2, Sample St.">
                                </label>
                                <label>Barangay *
                                    <input type="text" name="barangay" maxlength="100" required>
                                </label>
                                <label>City / Municipality *
                                    <input type="text" name="city" maxlength="100" required>
                                </label>
                                <label>Province *
                                    <input type="text" name="province" maxlength="100" required>
                                </label>
                                <label>Postal code
                                    <input type="text" name="postal_code" maxlength="12" inputmode="numeric">
                                </label>
                            </div>
                            <label class="check-row"><input type="checkbox" name="is_default" value="1"> Set as my default address</label>
                            <button class="btn-secondary" type="submit">Save address</button>
                        </form>
                    </details>
                </div>

                <div class="receipt-section">
                    <div class="receipt-label">Order summary</div>
                    <table class="receipt-table">
                        <tbody>
                            <?php foreach ($lines as $line): ?>
                                <tr data-row="<?= (int) $line['product_id'] ?>" <?= $line['problem'] ? 'hidden' : '' ?>>
                                    <td class="r-name"><?= e($line['name']) ?><small><?= (int) $line['quantity'] ?> &times; <?= money($line['price']) ?></small></td>
                                    <td class="r-amount"><?= money($line['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noneRow" hidden><td colspan="2" class="muted-small">No items selected.</td></tr>
                        </tbody>
                    </table>
                    <div class="totals">
                        <div><span id="subtotalLabel">Subtotal (<?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?>)</span><span id="subtotalAmount"><?= money($totals['subtotal']) ?></span></div>
                        <div><span>VAT (<?= e((string) VAT_PERCENT) ?>%)</span><span id="vatAmount"><?= money($totals['vat']) ?></span></div>
                        <div class="grand"><span>Total</span><span id="totalAmount"><?= money($totals['total']) ?></span></div>
                    </div>
                </div>

                <div class="receipt-section">
                    <div class="receipt-label">Mode of payment</div>
                    <div class="pay-grid">
                        <?php foreach ($paymentMethods as $key => $method): ?>
                            <label class="pay-option">
                                <input type="radio" name="payment_method" value="<?= e($key) ?>" form="orderForm" <?= $key === 'cod' ? 'checked' : '' ?>>
                                <span class="pay-card"><span class="mi"><?= e($method['icon']) ?></span><strong><?= e($method['label']) ?></strong><small><?= e($method['hint']) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="muted-small payment-note" id="payNote" data-cod="Pay in cash when your order arrives. The admin will review your order first." data-online="Your chosen payment is saved with the order. Online payment is not processed in the app yet; the admin will confirm it with you after reviewing your order.">Pay in cash when your order arrives. The admin will review your order first.</p>
                </div>

                <div class="receipt-section">
                    <label class="receipt-label" for="orderNotes">Note to the seller (optional)</label>
                    <textarea id="orderNotes" name="notes" form="orderForm" rows="2" maxlength="500" placeholder="Landmark, preferred delivery time..."></textarea>
                </div>

                <form id="orderForm" action="index.php?page=place_order" method="post" data-confirm data-confirm-title="Place order" data-confirm-message="Place this order? The admin will review it and respond to you.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                </form>
                <?php if ($hasProblem): ?>
                    <p class="stock-warning center">Items that are out of stock or over the available stock cannot be selected.</p>
                <?php endif; ?>
                <button class="btn-primary wide" id="placeOrderBtn" type="submit" form="orderForm" data-has-address="<?= $addresses ? '1' : '0' ?>" <?= (!$selectableCount || !$addresses) ? 'disabled' : '' ?>>Place order &middot; <span id="placeTotal"><?= money($totals['total']) ?></span></button>
                <?php if (!$addresses): ?><p class="muted-small center">Add a delivery address to enable checkout.</p><?php endif; ?>

                <?php foreach ($addresses as $address): ?>
                    <form id="addressDelete<?= (int) $address['id'] ?>" action="index.php?page=address_delete" method="post" data-confirm data-confirm-title="Remove address" data-confirm-message="Remove this saved address?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
                    </form>
                <?php endforeach; ?>
            </aside>
        </div>
        <?php endif; ?>
    </main>
    <script>
    (function () {
        var rate = <?= json_encode((float) VAT_PERCENT) ?>;
        var picks = Array.prototype.slice.call(document.querySelectorAll('input.line-pick'));
        var selectAll = document.getElementById('selectAll');
        if (!picks.length || !selectAll) return;
        var button = document.getElementById('placeOrderBtn');
        function peso(n) { return '\u20B1' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
        function update() {
            var subtotal = 0, items = 0, picked = 0, selectable = 0;
            picks.forEach(function (box) {
                var row = document.querySelector('[data-row="' + box.value + '"]');
                var on = box.checked && !box.disabled;
                if (!box.disabled) selectable++;
                if (row) row.hidden = !on;
                if (on) {
                    picked++;
                    items += Number(box.dataset.qty);
                    subtotal += Number(box.dataset.price) * Number(box.dataset.qty);
                }
            });
            subtotal = round2(subtotal);
            var vat = round2(subtotal * rate / 100);
            document.getElementById('subtotalLabel').textContent = 'Subtotal (' + items + ' item' + (items === 1 ? '' : 's') + ')';
            document.getElementById('subtotalAmount').textContent = peso(subtotal);
            document.getElementById('vatAmount').textContent = peso(vat);
            document.getElementById('totalAmount').textContent = peso(round2(subtotal + vat));
            document.getElementById('placeTotal').textContent = peso(round2(subtotal + vat));
            document.getElementById('noneRow').hidden = picked > 0;
            document.getElementById('selectedCount').textContent = picked + ' of ' + picks.length + ' selected';
            selectAll.checked = selectable > 0 && picked === selectable;
            selectAll.indeterminate = picked > 0 && picked < selectable;
            if (button) button.disabled = picked === 0 || button.dataset.hasAddress !== '1';
        }
        picks.forEach(function (box) { box.addEventListener('change', update); });
        selectAll.addEventListener('change', function () {
            picks.forEach(function (box) { if (!box.disabled) box.checked = selectAll.checked; });
            update();
        });
        var note = document.getElementById('payNote');
        document.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                note.textContent = radio.value === 'cod' ? note.dataset.cod : note.dataset.online;
            });
        });
        update();
    }());
    </script>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>
