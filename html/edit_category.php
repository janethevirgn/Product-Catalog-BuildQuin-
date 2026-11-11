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

$error = '';
$id = filter_var(get_string('id'), FILTER_VALIDATE_INT) ?: 0;

$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php?page=categories");
    exit();
}

$category = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = trim(post_string('category_name'));

    if ($category_name === '' || strlen($category_name) > 255) {
        $error = 'Enter a category name between 1 and 255 characters.';
    } else {
        $check = $conn->prepare('SELECT id FROM categories WHERE LOWER(category_name) = LOWER(?) AND id != ?');
        $check->bind_param('si', $category_name, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = 'That category name is already in use.';
        } else {
            $update = $conn->prepare('UPDATE categories SET category_name = ? WHERE id = ?');
            $update->bind_param('si', $category_name, $id);
            try {
                $saved = $update->execute();
            } catch (mysqli_sql_exception $exception) {
                $saved = false;
            }
            if ($saved) {
                header('Location: index.php?page=categories&notice=category_updated');
                exit();
            }
            $error = isset($exception) && (int) $exception->getCode() === 1062
                ? 'That category name is already in use.'
                : 'There was an error updating the category. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/edit_category.css">
    <link rel="stylesheet" href="../css/app.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body class="form-page">
    <?php include __DIR__ . '/navigation.php'; ?>
    <div class="form-card">
        <div class="form-title">Edit Category</div>
        <div class="form-subtitle">Edit Category Name</div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-group">
                <label class="form-label">Category Name</label>
                <input type="text" name="category_name" class="form-input" maxlength="255" value="<?= htmlspecialchars(post_string('category_name') ?: $category['category_name']) ?>" required>
            </div>
            <button type="submit" class="btn-submit">Update Category</button>
        </form>

        <a href="index.php?page=categories" class="back-link"><span class="mi">arrow_back</span> Back to Categories</a>
    </div>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>