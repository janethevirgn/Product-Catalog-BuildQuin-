<?php
/* =====================================================================
   SECURITY GUARD (runs first on this page)
   1. This page can only be opened through index.php. Typing its own
      file name in the address bar sends the visitor to the login page.
   2. A visitor who is not signed in is sent back to the login page.
   ===================================================================== */
if (!defined('APP_ROUTER')) {
    header('Location: ../index.html');
    exit();
}
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../index.html?mode=login');
    exit();
}

require_once __DIR__ . '/../db/db.php';

$search = trim(get_string('q'));
$categories = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $categoryQuery = $conn->prepare('SELECT * FROM categories WHERE category_name LIKE ? ORDER BY id DESC');
    $categoryQuery->bind_param('s', $like);
    $categoryQuery->execute();
    $result = $categoryQuery->get_result();
} else {
    $result = $conn->query('SELECT * FROM categories ORDER BY id DESC');
}
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuildQuin Categories</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/categories.css">
    <link rel="stylesheet" href="../css/app.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body>

    <?php include __DIR__ . '/navigation.php'; ?>

    <div class="container">
        <?php
        $categoryNotices = [
            'category_added' => 'Category added.',
            'category_updated' => 'Category updated.',
            'category_deleted' => 'Category deleted.',
            'category_in_use' => 'Move or delete this category\'s products before deleting the category.',
            'category_missing' => 'That category was not found.',
        ];
        $noticeKey = get_string('notice');
        if (isset($categoryNotices[$noticeKey])): ?>
            <div class="status-message"><?= e($categoryNotices[$noticeKey]) ?></div>
        <?php endif; ?>
        <form class="search-bar-container" action="index.php" method="get" data-auto-search="categories">
            <input type="hidden" name="page" value="categories">
            <input type="search" name="q" class="search-input" value="<?= e($search) ?>" placeholder="Search category name...">
            <a href="index.php?page=categories" class="btn btn-clear">Clear</a>
            <?php if (is_staff_side()): ?><a href="index.php?page=add_category" class="btn btn-add">+ Add New Category</a><?php endif; ?>
        </form>

        <div id="categoriesContainer">
            <?php if (count($categories) > 0): ?>
                <?php foreach ($categories as $catItem): 
                    $catName = $catItem['category_name'];
                    $catId = $catItem['id'];

                    $stmt = $conn->prepare('SELECT * FROM products WHERE category_id = ? ORDER BY id DESC LIMIT 4');
                    $stmt->bind_param('i', $catId);
                    $stmt->execute();
                    $catResult = $stmt->get_result();
                ?>
                    <div class="category-row-section" data-category-name="<?= e(strtolower($catName)) ?>">
                        <div class="category-row-header">
                            <div class="category-row-title">
                                <?= e($catName) ?>
                            </div>
                            <div class="category-actions">
                                <?php if (is_staff_side()): ?><a href="index.php?page=edit_category&amp;id=<?= (int) $catId ?>" class="btn-sm-edit">Edit Category</a><?php endif; ?>
                                <?php if (is_admin()): ?><form action="index.php?page=delete_category" method="post" data-confirm data-confirm-title="Delete category" data-confirm-message="Delete this category? It can only be removed when no products use it."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $catId ?>"><button type="submit" class="btn-sm-delete">Delete</button></form><?php endif; ?>
                            </div>
                        </div>

                        <?php if ($catResult && $catResult->num_rows > 0): ?>
                            <div class="row-products-grid">
                                <?php while($prod = $catResult->fetch_assoc()): ?>
                                    <div class="mini-card">
                                        <img src="<?= e(catalog_image_url($prod['image_url'])) ?>" alt="Product" class="mini-img">
                                        <div class="mini-content">
                                            <div class="mini-title"><?= e($prod['name']) ?></div>
                                            <div class="mini-price">₱<?= number_format($prod['price'], 2) ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-row">No products listed under this category yet.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: #706357; padding: 40px;">No categories found. Click "+ Add New Category" to create.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../js/confirmations.js" defer></script>
    <script src="../js/auto-search.js" defer></script>
</body>
</html>