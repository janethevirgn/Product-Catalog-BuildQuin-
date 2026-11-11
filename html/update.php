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

$current_category = post_string('category_origin') !== '' ? post_string('category_origin') : (get_string('category') !== '' ? get_string('category') : 'All');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $code = trim(post_string('product_code'));
    $name = trim(post_string('name'));
    $categoryId = filter_var(post_string('category'), FILTER_VALIDATE_INT);
    $price = $_POST['price'] ?? '';
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
    $description = trim(post_string('description'));
    $image_url = trim(post_string('image_url'));
    $img_interior = trim(post_string('img_interior'));
    $img_exterior = trim(post_string('img_exterior'));
    $img_accent = trim(post_string('img_accent'));

    if (!$id || $id < 1) $errors[] = 'The product ID is invalid.';
    if ($code === '' || strlen($code) > 50) $errors[] = 'Enter a product code up to 50 characters.';
    if ($name === '' || strlen($name) > 150) $errors[] = 'Enter a product name up to 150 characters.';
    if (!is_string($price) || !preg_match('/^\d+(\.\d{1,2})?$/', $price) || (float) $price > 99999999.99) $errors[] = 'Enter a valid price with up to two decimal places.';
    if ($stock === false || $stock < 0 || $stock > 2147483647) $errors[] = 'Stock must be a whole number from 0 to 2,147,483,647.';

    $categoryCheck = $conn->prepare('SELECT category_name FROM categories WHERE id = ?');
    $categoryCheck->bind_param('i', $categoryId);
    $categoryCheck->execute();
    $categoryRow = $categoryCheck->get_result()->fetch_assoc();
    if (!$categoryId || !$categoryRow) {
        $errors[] = 'Choose a category that exists.';
        $category = '';
    } else {
        $category = $categoryRow['category_name'];
    }

    foreach (array_filter([$image_url, $img_interior, $img_exterior, $img_accent]) as $image) {
        $maxLength = $image === $image_url ? 255 : 500;
        $isLocalPhoto = preg_match('#^photos/[A-Za-z0-9_.-]+$#', $image) && is_file(__DIR__ . '/../' . $image);
        $isWebUrl = filter_var($image, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true);
        if (strlen($image) > $maxLength || (!$isLocalPhoto && !$isWebUrl)) {
            $errors[] = 'Use a valid http/https image URL or an existing photos/ file.';
            break;
        }
    }

    if ($id && !$errors) {
        $check = $conn->prepare('SELECT id FROM products WHERE product_code = ? AND id != ?');
        $check->bind_param('si', $code, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errors[] = 'That product code is already in use.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('UPDATE products SET product_code=?, name=?, category_id=?, price=?, stock=?, description=?, image_url=?, img_interior=?, img_exterior=?, img_accent=? WHERE id=?');
        $stmt->bind_param('ssidisssssi', $code, $name, $categoryId, $price, $stock, $description, $image_url, $img_interior, $img_exterior, $img_accent, $id);
        try {
            $saved = $stmt->execute();
        } catch (mysqli_sql_exception $exception) {
            $saved = false;
        }
        if ($saved) {
            header('Location: index.php?page=products&category=' . urlencode($category) . '&notice=product_updated');
            exit();
        }
        $errors[] = isset($exception) && (int) $exception->getCode() === 1062
            ? 'That product code is already in use.'
            : 'The product could not be updated. Check the details and try again.';
    }
}

$requestedId = filter_var(get_string('id'), FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && (!$requestedId || $requestedId < 1)) {
    header("Location: index.php?page=products");
    exit();
}

$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int) ($_POST['id'] ?? 0) : $requestedId;
$stmt = $conn->prepare('SELECT products.* FROM products WHERE id = ?');
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: index.php?page=products");
    exit();
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$field = function (string $name, $fallback) use ($isPost): string {
    return $isPost ? post_string($name) : (string) $fallback;
};

$categories = [];
$categoryResult = $conn->query('SELECT id, category_name FROM categories ORDER BY category_name');
while ($categoryRow = $categoryResult->fetch_assoc()) $categories[] = $categoryRow;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Item - BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/update.css">
    <link rel="stylesheet" href="../css/app.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body class="form-page">
<?php include __DIR__ . '/navigation.php'; ?>

<div class="form-card">
    <h2>Edit Item Details</h2>
    <?php foreach ($errors as $error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
    <form action="index.php?page=update" method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <input type="hidden" name="category_origin" value="<?= e($current_category) ?>">

        <label>Product Code</label>
        <input type="text" name="product_code" maxlength="50" value="<?= e($field('product_code', $item['product_code'])) ?>" required>
        
        <label>Product Name</label>
        <input type="text" name="name" maxlength="150" value="<?= e($field('name', $item['name'])) ?>" required>
        
        <label>Category</label>
        <select name="category" required>
            <?php
            foreach($categories as $c) {
                $selectedCategoryId = post_string('category') !== '' ? (int) post_string('category') : (int) $item['category_id'];
                $selected = ($selectedCategoryId === (int) $c['id']) ? 'selected' : '';
                echo '<option value="' . (int) $c['id'] . '" ' . $selected . '>' . htmlspecialchars($c['category_name']) . '</option>';
            }
            ?>
        </select>
        
        <label>Price (₱)</label>
        <input type="number" min="0" max="99999999.99" step="0.01" name="price" value="<?= e($field('price', $item['price'])) ?>" required>
        
        <label>Stock Quantity</label>
        <input type="number" min="0" max="2147483647" step="1" name="stock" value="<?= e($field('stock', $item['stock'])) ?>" required>
        
        <label>Description</label>
        <textarea name="description" rows="3"><?= e($field('description', $item['description'])) ?></textarea>
        
        <label>Main Image URL</label>
        <input type="text" name="image_url" maxlength="255" value="<?= e($field('image_url', $item['image_url'])) ?>" required>

        <div class="section-divider"><span class="mi">photo_camera</span> Sample Application Photos (Modal Gallery)</div>

        <label>Interior / Wall Sample URL</label>
        <input type="text" name="img_interior" maxlength="500" value="<?= e($field('img_interior', $item['img_interior'])) ?>" placeholder="https://...">

        <label>Exterior / Roof Sample URL</label>
        <input type="text" name="img_exterior" maxlength="500" value="<?= e($field('img_exterior', $item['img_exterior'])) ?>" placeholder="https://...">

        <label>Furniture / Accent Sample URL</label>
        <input type="text" name="img_accent" maxlength="500" value="<?= e($field('img_accent', $item['img_accent'])) ?>" placeholder="https://...">
        
        <button type="submit" class="btn-submit">Update Changes</button>
        <a href="index.php?page=products&category=<?= urlencode($current_category) ?>" class="btn-back">Cancel and Return</a>
    </form>
</div>

<script src="../js/confirmations.js" defer></script>
</body>
</html>