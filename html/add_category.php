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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = trim(post_string('category_name'));

    if ($category_name === '' || strlen($category_name) > 255) {
        $error = 'Enter a category name between 1 and 255 characters.';
    } else {
        $check = $conn->prepare('SELECT id FROM categories WHERE LOWER(category_name) = LOWER(?)');
        $check->bind_param('s', $category_name);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Category already exists.";
        } else {
            $stmt = $conn->prepare('INSERT INTO categories (category_name) VALUES (?)');
            $stmt->bind_param('s', $category_name);
            try {
                $saved = $stmt->execute();
            } catch (mysqli_sql_exception $exception) {
                $saved = false;
            }
            if ($saved) {
                header('Location: index.php?page=categories&notice=category_added');
                exit();
            } else {
                $error = isset($exception) && (int) $exception->getCode() === 1062
                    ? 'That category name already exists.'
                    : 'There was an error saving the category. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Category - BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/add_category.css">
    <link rel="stylesheet" href="../css/app.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body class="form-page">
    <?php include __DIR__ . '/navigation.php'; ?>
    <div class="form-card">
        <div class="form-title">Add New Category</div>
        <div class="form-subtitle">Add a new category to your catalog.</div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-group">
                <label class="form-label">Category Name</label>
                <input type="text" name="category_name" class="form-input" maxlength="255" value="<?= htmlspecialchars(post_string('category_name')) ?>" placeholder="Ex. Hardware, Flooring..." required>
            </div>
            <button type="submit" class="btn-submit">Save Category</button>
        </form>

        <a href="index.php?page=categories" class="back-link"><span class="mi">arrow_back</span> Back</a>
    </div>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>