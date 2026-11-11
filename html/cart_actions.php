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
    // cart_add is called by JavaScript, so it gets a JSON answer instead of a redirect.
    if ($page === 'cart_add') {
        json_out(['ok' => false, 'message' => 'Please sign in again.'], 401);
    }
    header('Location: ../index.html?mode=login');
    exit();
}
if ($_SESSION['role'] !== 'user') {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}

require_once __DIR__ . '/../db/db.php';

/*
 * Customer actions: cart_add, cart_update, cart_remove, address_save, address_delete, place_order.
 * $page is set by the router.
 */
$userId = (int) $_SESSION['user_id'];

function cart_redirect(string $notice, array $extra = []): void
{
    $query = array_merge(['page' => 'cart', 'notice' => $notice], $extra);
    header('Location: index.php?' . http_build_query($query));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=cart');
    exit();
}

/* ------------------------------------------------------------------------- cart_add --- */
if ($page === 'cart_add') {
    $productId = filter_var(post_string('product_id'), FILTER_VALIDATE_INT);
    $qtyRaw = post_string('qty');
    $qty = filter_var($qtyRaw === '' ? '1' : $qtyRaw, FILTER_VALIDATE_INT);

    $fail = function (string $message) use ($conn, $userId) {
        if (is_ajax_request()) {
            json_out(['ok' => false, 'message' => $message, 'count' => cart_count($conn, $userId)]);
        }
        cart_redirect('cart_error');
    };

    if (!$productId || $productId < 1 || !$qty || $qty < 1 || $qty > 999) {
        $fail('Choose a quantity between 1 and 999.');
    }

    $stmt = $conn->prepare('SELECT id, name, stock FROM products WHERE id = ?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) {
        $fail('That product is no longer available.');
    }

    $stock = (int) ($product['stock'] ?? 0);
    if ($stock < 1) {
        $fail($product['name'] . ' is out of stock.');
    }

    $existingStmt = $conn->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
    $existingStmt->bind_param('ii', $userId, $productId);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $currentQty = $existing ? (int) $existing['quantity'] : 0;

    if ($currentQty >= $stock) {
        $fail('Your cart already has all ' . $stock . ' available piece' . ($stock === 1 ? '' : 's') . ' of ' . $product['name'] . '.');
    }

    $newQty = min($stock, $currentQty + $qty);
    $message = 'Added to cart: ' . $product['name'];
    if ($currentQty + $qty > $stock) {
        $message = 'Only ' . $stock . ' in stock. Your cart now has ' . $newQty . ' of ' . $product['name'] . '.';
    }

    if ($existing) {
        $update = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
        $update->bind_param('iii', $newQty, $userId, $productId);
        $update->execute();
    } else {
        $insert = $conn->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)');
        $insert->bind_param('iii', $userId, $productId, $newQty);
        $insert->execute();
    }

    if (is_ajax_request()) {
        json_out(['ok' => true, 'message' => $message, 'count' => cart_count($conn, $userId)]);
    }
    cart_redirect('cart_added');
}

/* ---------------------------------------------------------------------- cart_update --- */
if ($page === 'cart_update') {
    $productId = filter_var(post_string('product_id'), FILTER_VALIDATE_INT);
    $qty = filter_var(post_string('qty'), FILTER_VALIDATE_INT);
    if (!$productId || $productId < 1 || $qty === false || $qty === null) {
        cart_redirect('cart_error');
    }

    if ($qty < 1) {
        $delete = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $delete->bind_param('ii', $userId, $productId);
        $delete->execute();
        cart_redirect('cart_removed');
    }

    $stmt = $conn->prepare('SELECT stock FROM products WHERE id = ?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) {
        cart_redirect('cart_error');
    }
    $stock = (int) ($product['stock'] ?? 0);
    $notice = 'cart_updated';
    if ($qty > $stock) {
        $qty = max(1, $stock);
        $notice = 'cart_limited';
    }

    $update = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
    $update->bind_param('iii', $qty, $userId, $productId);
    $update->execute();
    cart_redirect($notice);
}

/* ---------------------------------------------------------------------- cart_remove --- */
if ($page === 'cart_remove') {
    $productId = filter_var(post_string('product_id'), FILTER_VALIDATE_INT);
    if (!$productId || $productId < 1) {
        cart_redirect('cart_error');
    }
    $delete = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
    $delete->bind_param('ii', $userId, $productId);
    $delete->execute();
    cart_redirect('cart_removed');
}

/* --------------------------------------------------------------------- address_save --- */
if ($page === 'address_save') {
    $fields = [
        'label' => 50,
        'recipient_name' => 100,
        'contact_number' => 30,
        'street_address' => 255,
        'barangay' => 100,
        'city' => 100,
        'province' => 100,
        'postal_code' => 12,
    ];
    $values = [];
    foreach ($fields as $field => $max) {
        $values[$field] = trim(post_string($field));
        if (mb_strlen($values[$field]) > $max) {
            cart_redirect('address_invalid');
        }
    }
    if ($values['label'] === '') {
        $values['label'] = 'Home';
    }
    $digits = preg_replace('/\D+/', '', $values['contact_number']);
    if ($values['recipient_name'] === '' || $values['street_address'] === '' || $values['barangay'] === ''
        || $values['city'] === '' || $values['province'] === ''
        || !preg_match('/^[0-9+()\- ]+$/', $values['contact_number']) || strlen($digits) < 7 || strlen($digits) > 15
        || ($values['postal_code'] !== '' && !preg_match('/^[0-9A-Za-z -]{3,12}$/', $values['postal_code']))) {
        cart_redirect('address_invalid');
    }

    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM user_addresses WHERE user_id = ?');
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $addressCount = (int) $countStmt->get_result()->fetch_assoc()['total'];
    if ($addressCount >= 10) {
        cart_redirect('address_limit');
    }

    $makeDefault = $addressCount === 0 || post_string('is_default') === '1' ? 1 : 0;
    if ($makeDefault) {
        $clear = $conn->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?');
        $clear->bind_param('i', $userId);
        $clear->execute();
    }

    $postal = $values['postal_code'] !== '' ? $values['postal_code'] : null;
    $insert = $conn->prepare('INSERT INTO user_addresses (user_id, label, recipient_name, contact_number, street_address, barangay, city, province, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->bind_param('issssssssi', $userId, $values['label'], $values['recipient_name'], $values['contact_number'], $values['street_address'], $values['barangay'], $values['city'], $values['province'], $postal, $makeDefault);
    $insert->execute();
    cart_redirect('address_saved', ['address' => $conn->insert_id]);
}

/* ------------------------------------------------------------------- address_delete --- */
if ($page === 'address_delete') {
    $addressId = filter_var(post_string('address_id'), FILTER_VALIDATE_INT);
    if (!$addressId || $addressId < 1) {
        cart_redirect('cart_error');
    }
    $delete = $conn->prepare('DELETE FROM user_addresses WHERE id = ? AND user_id = ?');
    $delete->bind_param('ii', $addressId, $userId);
    $delete->execute();

    // Keep one default address if any remain.
    $remaining = $conn->prepare('SELECT id, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC LIMIT 1');
    $remaining->bind_param('i', $userId);
    $remaining->execute();
    $first = $remaining->get_result()->fetch_assoc();
    if ($first && (int) $first['is_default'] === 0) {
        $promote = $conn->prepare('UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
        $promote->bind_param('ii', $first['id'], $userId);
        $promote->execute();
    }
    cart_redirect('address_deleted');
}

/* -------------------------------------------------------------------- place_order ---- */
if ($page === 'place_order') {
    $lines = cart_lines($conn, $userId);
    if (!$lines) {
        cart_redirect('cart_empty');
    }

    // Only the items the customer ticked are ordered; the rest stay in the cart.
    $selectedIds = [];
    if (isset($_POST['selected']) && is_array($_POST['selected'])) {
        foreach ($_POST['selected'] as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id && $id > 0) {
                $selectedIds[$id] = true;
            }
        }
    }
    $lines = array_values(array_filter($lines, function ($line) use ($selectedIds) {
        return isset($selectedIds[(int) $line['product_id']]);
    }));
    if (!$lines) {
        cart_redirect('cart_none_selected');
    }

    $paymentMethods = payment_methods();
    $paymentKey = post_string('payment_method') === '' ? 'cod' : post_string('payment_method');
    if (!isset($paymentMethods[$paymentKey])) {
        cart_redirect('payment_invalid');
    }

    $addressId = filter_var(post_string('address_id'), FILTER_VALIDATE_INT);
    $address = null;
    if ($addressId && $addressId > 0) {
        $addressStmt = $conn->prepare('SELECT * FROM user_addresses WHERE id = ? AND user_id = ?');
        $addressStmt->bind_param('ii', $addressId, $userId);
        $addressStmt->execute();
        $address = $addressStmt->get_result()->fetch_assoc();
    }
    if (!$address) {
        cart_redirect('address_required');
    }

    $subtotal = 0.0;
    foreach ($lines as $line) {
        if ($line['stock'] < 1 || $line['quantity'] > $line['stock']) {
            cart_redirect('stock_changed');
        }
        $subtotal += $line['line_total'];
    }
    $totals = order_totals($subtotal);

    $notes = trim(post_string('notes'));
    if (mb_strlen($notes) > 500) {
        $notes = mb_substr($notes, 0, 500);
    }
    $notesValue = $notes !== '' ? $notes : null;
    $customerName = (string) $address['recipient_name'];
    $contactNumber = (string) $address['contact_number'];
    $shippingAddress = address_one_line($address);
    $payment = $paymentMethods[$paymentKey]['label'];
    $vatRate = (float) VAT_PERCENT;
    $tempNumber = 'TMP' . bin2hex(random_bytes(8));

    try {
        $conn->begin_transaction();

        $orderStmt = $conn->prepare("INSERT INTO orders (order_number, user_id, customer_name, contact_number, shipping_address, payment_method, notes, subtotal, vat_rate, vat_amount, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $orderStmt->bind_param('sisssssdddd', $tempNumber, $userId, $customerName, $contactNumber, $shippingAddress, $payment, $notesValue, $totals['subtotal'], $vatRate, $totals['vat'], $totals['total']);
        $orderStmt->execute();
        $orderId = (int) $conn->insert_id;

        $orderNumber = 'BQ-' . date('ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
        $numberStmt = $conn->prepare('UPDATE orders SET order_number = ? WHERE id = ?');
        $numberStmt->bind_param('si', $orderNumber, $orderId);
        $numberStmt->execute();

        $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_code, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $unitPrice = (float) $line['price'];
            $quantity = (int) $line['quantity'];
            $lineTotal = (float) $line['line_total'];
            $itemStmt->bind_param('iissdid', $orderId, $productId, $line['product_code'], $line['name'], $unitPrice, $quantity, $lineTotal);
            $itemStmt->execute();
        }

        $clearCart = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        foreach ($lines as $line) {
            $removeId = (int) $line['product_id'];
            $clearCart->bind_param('ii', $userId, $removeId);
            $clearCart->execute();
        }

        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        try {
            $conn->rollback();
        } catch (mysqli_sql_exception $ignored) {
        }
        error_log('Order failed: ' . $exception->getMessage());
        cart_redirect('order_error');
    }

    notify_staff($conn, 'order_new', 'New order ' . $orderNumber . ' from ' . $customerName . ' (' . money($totals['total']) . ', ' . $payment . '). Please accept or decline it.', 'index.php?page=orders&status=pending');
    notify_user($conn, $userId, 'order_placed', 'Your order ' . $orderNumber . ' has been placed. Wait for the admin\'s response.', 'index.php?page=my_orders');

    header('Location: index.php?' . http_build_query(['page' => 'my_orders', 'notice' => 'order_placed', 'order' => $orderId]));
    exit();
}

http_response_code(404);
echo 'Page not found.';
