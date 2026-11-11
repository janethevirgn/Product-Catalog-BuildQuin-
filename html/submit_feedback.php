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


// Saves product feedback sent by a customer and notifies the staff. Runs only on a POST request.

require_once __DIR__ . '/../db/db.php';

// Only accept a form submit (POST).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=products');
    exit();
}

// Read and validate the form values.
$productId = filter_var(post_string('product_id'), FILTER_VALIDATE_INT);
$message = trim(post_string('message'));
$messageLength = mb_strlen($message);

if (!$productId || $productId < 1 || $messageLength < 3 || $messageLength > 2000) {
    header('Location: index.php?page=my_feedback&notice=feedback_invalid');
    exit();
}

// Make sure the product exists.
$productCheck = $conn->prepare('SELECT id, name FROM products WHERE id = ?');
$productCheck->bind_param('i', $productId);
$productCheck->execute();
$product = $productCheck->get_result()->fetch_assoc();
if (!$product) {
    header('Location: index.php?page=my_feedback&notice=product_missing');
    exit();
}

// Save the feedback.
$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('INSERT INTO product_feedback (product_id, user_id, message) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $productId, $userId, $message);
$saved = false;
try {
    $saved = $stmt->execute();
} catch (mysqli_sql_exception $exception) {
    error_log('Feedback failed: ' . $exception->getMessage());
}

// Tell the customer what happened.
if ($saved) {
    notify_staff(
        $conn,
        'feedback_new',
        $_SESSION['full_name'] . ' sent feedback about "' . $product['name'] . '".',
        'index.php?page=inquiries'
    );
    header('Location: index.php?page=my_feedback&notice=feedback_sent');
    exit();
}

header('Location: index.php?page=my_feedback&notice=feedback_error');
exit();
?>
