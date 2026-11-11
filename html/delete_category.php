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


// Deletes one category (admin only). A category that still has products cannot be deleted.

require_once __DIR__ . '/../db/db.php';

// Only accept a form submit (POST).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=categories');
    exit();
}

// Check the category ID sent by the form.
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid category ID.');
}

// Make sure the category exists.
$stmt = $conn->prepare('SELECT category_name FROM categories WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    header('Location: index.php?page=categories&notice=category_missing');
    exit();
}

// Refuse to delete a category that still has products.
$productCount = $conn->prepare('SELECT COUNT(*) AS total FROM products WHERE category_id = ?');
$productCount->bind_param('i', $id);
$productCount->execute();
if ((int) $productCount->get_result()->fetch_assoc()['total'] > 0) {
    header('Location: index.php?page=categories&notice=category_in_use');
    exit();
}

// Delete the category.
$deleteCategory = $conn->prepare('DELETE FROM categories WHERE id = ?');
$deleteCategory->bind_param('i', $id);
try {
    $deleteCategory->execute();
} catch (mysqli_sql_exception $exception) {
    header('Location: index.php?page=categories&notice=category_in_use');
    exit();
}
header('Location: index.php?page=categories&notice=category_deleted');
exit();
?>