<?php
/* =====================================================================
   SECURITY GUARD (runs first on this page)
   1. This page can only be opened through index.php. Typing its own
      file name in the address bar sends the visitor to the login page.
   2. A visitor who is not signed in is sent back to the login page.
   3. Only the Admin account may open this page.
   ===================================================================== */
if (!defined('APP_ROUTER')) {
    header('Location: ../index.html');
    exit();
}
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../index.html?mode=login');
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}


// Deletes one product (admin only). Runs only on a POST request, then goes back to the product list.

require_once __DIR__ . '/../db/db.php';

// Remember which category filter the admin was viewing.
$category = trim(post_string('category')) ?: 'All';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=products');
    exit();
}

// Check the product ID sent by the form.
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid product ID.');
}

// Delete the product, then return to the list with a notice.
$stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$notice = $stmt->affected_rows > 0 ? 'product_deleted' : 'product_missing';
header('Location: index.php?page=products&category=' . urlencode($category) . '&notice=' . $notice);
exit();
?>