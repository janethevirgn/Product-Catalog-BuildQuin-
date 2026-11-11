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
$selectedCategory = trim(get_string('category')) ?: 'All';
$pageNumber = max(1, filter_var(get_string('page_number'), FILTER_VALIDATE_INT) ?: 1);
$perPage = 12;
$offset = ($pageNumber - 1) * $perPage;
$like = '%' . $search . '%';

if ($search !== '' && $selectedCategory !== 'All') {
    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM products p JOIN categories c ON c.id = p.category_id WHERE c.category_name = ? AND (p.name LIKE ? OR p.product_code LIKE ?)');
    $countStmt->bind_param('sss', $selectedCategory, $like, $like);
    $countStmt->execute();
    $totalProducts = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $stmt = $conn->prepare('SELECT p.*, c.category_name AS category FROM products p JOIN categories c ON c.id = p.category_id WHERE c.category_name = ? AND (p.name LIKE ? OR p.product_code LIKE ?) ORDER BY p.id DESC LIMIT ? OFFSET ?');
    $stmt->bind_param('sssii', $selectedCategory, $like, $like, $perPage, $offset);
} elseif ($search !== '') {
    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM products p JOIN categories c ON c.id = p.category_id WHERE p.name LIKE ? OR p.product_code LIKE ?');
    $countStmt->bind_param('ss', $like, $like);
    $countStmt->execute();
    $totalProducts = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $stmt = $conn->prepare('SELECT p.*, c.category_name AS category FROM products p JOIN categories c ON c.id = p.category_id WHERE p.name LIKE ? OR p.product_code LIKE ? ORDER BY p.id DESC LIMIT ? OFFSET ?');
    $stmt->bind_param('ssii', $like, $like, $perPage, $offset);
} elseif ($selectedCategory !== 'All') {
    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM products p JOIN categories c ON c.id = p.category_id WHERE c.category_name = ?');
    $countStmt->bind_param('s', $selectedCategory);
    $countStmt->execute();
    $totalProducts = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $stmt = $conn->prepare('SELECT p.*, c.category_name AS category FROM products p JOIN categories c ON c.id = p.category_id WHERE c.category_name = ? ORDER BY p.id DESC LIMIT ? OFFSET ?');
    $stmt->bind_param('sii', $selectedCategory, $perPage, $offset);
} else {
    $countResult = $conn->query('SELECT COUNT(*) AS total FROM products');
    $totalProducts = (int) $countResult->fetch_assoc()['total'];
    $stmt = $conn->prepare('SELECT p.*, c.category_name AS category FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC LIMIT ? OFFSET ?');
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));

$categories = ['All'];
$catResult = $conn->query("SELECT category_name FROM categories ORDER BY id ASC");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row['category_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuildQuin Product Catalog</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="../css/app.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/lightbox.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body>

    <?php include __DIR__ . '/navigation.php'; ?>

    <div class="container">
        <?php
        $productNotices = ['product_added' => 'Product added.', 'product_updated' => 'Product updated.', 'product_deleted' => 'Product deleted.', 'product_missing' => 'That product was not found.'];
        $noticeKey = get_string('notice');
        if (isset($productNotices[$noticeKey])): ?>
            <div class="status-message"><?= htmlspecialchars($productNotices[$noticeKey]) ?></div>
        <?php endif; ?>
        <form class="search-bar-container" action="index.php" method="get" data-auto-search="products">
            <input type="hidden" name="page" value="products">
            <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
            <input type="search" name="q" class="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Search product name or code...">
            <a href="index.php?page=products" class="btn btn-clear">Clear</a>
            <?php if (is_staff_side()): ?><a href="index.php?page=add" class="btn btn-add">+ Add New Item</a><?php endif; ?>
        </form>

        <!-- Dito na lalabas nang dynamic ang mga buttons galing sa database categories -->
        <div class="categories-bar">
            <?php foreach ($categories as $cat): ?>
                <a class="cat-chip <?= $selectedCategory === $cat ? 'active' : '' ?>" href="index.php?page=products&amp;category=<?= urlencode($cat) ?>&amp;q=<?= urlencode($search) ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="product-grid" id="productGrid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php 
                        $imgInt = catalog_image_url(!empty($row['img_interior']) ? $row['img_interior'] : $row['image_url']);
                        $imgExt = catalog_image_url(!empty($row['img_exterior']) ? $row['img_exterior'] : 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?q=80&w=400');
                        $imgAcc = catalog_image_url(!empty($row['img_accent']) ? $row['img_accent'] : 'https://images.unsplash.com/photo-1513694203232-719a280e022f?q=80&w=400');
                        $stockQty = (int) ($row['stock'] ?? 0);
                    ?>
                    <?php $modalData = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'price' => '₱' . number_format((float) $row['price'], 2), 'category' => (string) $row['category'], 'code' => (string) $row['product_code'], 'stock' => $stockQty, 'description' => (string) $row['description'], 'image' => catalog_image_url($row['image_url']), 'interior' => $imgInt, 'exterior' => $imgExt, 'accent' => $imgAcc]; ?>
                    <div class="product-card" data-product="<?= htmlspecialchars(json_encode($modalData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="card-img-wrapper">
                            <img src="<?= e(catalog_image_url($row['image_url'])) ?>" alt="Product Image">
                            <span class="category-tag"><?= e($row['category']) ?></span>
                            <?php if ($stockQty < 1): ?><span class="stock-tag out">Out of stock</span><?php elseif ($stockQty <= 5): ?><span class="stock-tag low">Only <?= $stockQty ?> left</span><?php endif; ?>
                        </div>
                        <div class="card-content">
                            <div class="card-title"><?= e($row['name']) ?></div>
                            <div class="price-tag">₱<?= number_format((float) $row['price'], 2) ?></div>
                            <div class="product-desc"><?= e($row['description']) ?></div>
                            
                            <div class="meta-info">
                                <div class="meta-item">
                                    <strong>Category:</strong> <span class="category-text-info"><?= e($row['category']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <strong>Product Code:</strong> <?= e($row['product_code']) ?>
                                </div>
                                <div class="meta-item">
                                    <strong>Stock:</strong> <?= $stockQty ?> pcs
                                </div>
                            </div>

                            <?php if (is_staff_side()): ?><div class="card-actions" onclick="event.stopPropagation();">
                                <a href="index.php?page=update&amp;id=<?= (int) $row['id'] ?>&amp;category=<?= urlencode($selectedCategory) ?>" class="btn-edit">Edit Item</a>
                                <?php if (is_admin()): ?><form action="index.php?page=delete" method="post" data-confirm data-confirm-title="Delete product" data-confirm-message="Delete this product? This cannot be undone."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="category" value="<?= e($selectedCategory) ?>"><button class="btn-delete" type="submit">Delete</button></form><?php endif; ?>
                            </div><?php elseif (has_role(['user'])): ?><div class="card-actions" onclick="event.stopPropagation();">
                                <button type="button" class="btn-cart" data-add-to-cart="<?= (int) $row['id'] ?>" <?= $stockQty < 1 ? 'disabled' : '' ?>><?= $stockQty < 1 ? 'Out of stock' : '<span class="mi">shopping_cart</span> Add to Cart' ?></button>
                            </div><?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="empty-state">No catalog items found.</p>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Product pages">
            <?php if ($pageNumber > 1): ?><a href="index.php?<?= htmlspecialchars(http_build_query(['page' => 'products', 'q' => $search, 'category' => $selectedCategory, 'page_number' => $pageNumber - 1])) ?>">Previous</a><?php endif; ?>
            <span>Page <?= $pageNumber ?> of <?= $totalPages ?></span>
            <?php if ($pageNumber < $totalPages): ?><a href="index.php?<?= htmlspecialchars(http_build_query(['page' => 'products', 'q' => $search, 'category' => $selectedCategory, 'page_number' => $pageNumber + 1])) ?>">Next</a><?php endif; ?>
        </nav><?php endif; ?>
    </div>

    <div class="modal-overlay" id="productModal" onclick="closeModal(event)">
        <div class="modal-box" onclick="event.stopPropagation();">
            <span class="close-modal" onclick="closeModal(true)" role="button" aria-label="Close"><span class="mi">close</span></span>
            
            <img id="mImage" src="" class="modal-main-img" alt="Main Preview" data-lightbox data-lightbox-group="product-photos" title="Click to enlarge">
            <div class="zoom-hint"><span class="mi">zoom_in</span> Click a picture to view it larger</div>
            
            <div id="mTitle" class="modal-title">Product Name</div>
            <div id="mPrice" class="modal-price">₱0.00</div>
            <div id="mDesc" class="modal-desc">Product description goes here...</div>

            <div class="modal-details">
                <span><strong>Category:</strong> <span id="mCategory" style="color:#cf7543;">--</span></span>
                <span><strong>Code:</strong> <span id="mCode">--</span></span>
                <span><strong>Stock:</strong> <span id="mStock">--</span> pcs</span>
            </div>

            <div class="samples-header">Sample Application / Design Ideas</div>
            <div class="samples-grid">
                <div class="sample-item">
                    <img id="s1" src="" alt="Interior / Wall" data-lightbox data-lightbox-group="product-photos" title="Click to enlarge">
                    <span class="sample-label">Interior / Wall</span>
                </div>
                <div class="sample-item">
                    <img id="s2" src="" alt="Exterior / Roof" data-lightbox data-lightbox-group="product-photos" title="Click to enlarge">
                    <span class="sample-label">Exterior / Roof</span>
                </div>
                <div class="sample-item">
                    <img id="s3" src="" alt="Furniture / Accent" data-lightbox data-lightbox-group="product-photos" title="Click to enlarge">
                    <span class="sample-label">Furniture / Accent</span>
                </div>
            </div>
            <?php if (has_role(['user'])): ?>
                <section class="modal-buy" id="modalBuy">
                    <div class="qty-picker" role="group" aria-label="Quantity">
                        <button type="button" id="qtyMinus" aria-label="Decrease quantity"><span class="mi">remove</span></button>
                        <input type="number" id="modalQty" value="1" min="1" max="999" inputmode="numeric" aria-label="Quantity">
                        <button type="button" id="qtyPlus" aria-label="Increase quantity"><span class="mi">add</span></button>
                    </div>
                    <button type="button" class="btn-cart wide" id="modalAddCart"><span class="mi">shopping_cart</span> Add to Cart</button>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const CART_ENDPOINT = 'index.php?page=cart_add';
        const CART_TOKEN = <?= json_encode(csrf_token()) ?>;
        let modalProduct = null;

        function openModal(product) {
            const { id, name, price, category, code, stock, description, image, interior, exterior, accent } = product;
            modalProduct = product;
            document.getElementById('mTitle').textContent = name;
            document.getElementById('mPrice').textContent = price;
            document.getElementById('mCategory').textContent = category;
            document.getElementById('mCode').textContent = code;
            document.getElementById('mStock').textContent = stock;
            document.getElementById('mDesc').textContent = description || 'No description yet.';

            document.getElementById('mImage').src = image;

            document.getElementById('s1').src = interior;
            document.getElementById('s2').src = exterior;
            document.getElementById('s3').src = accent;

            const addButton = document.getElementById('modalAddCart');
            const qtyInput = document.getElementById('modalQty');
            if (addButton && qtyInput) {
                const available = Number(stock) || 0;
                qtyInput.value = 1;
                qtyInput.max = Math.max(1, available);
                qtyInput.disabled = available < 1;
                addButton.disabled = available < 1;
                addButton.innerHTML = available < 1 ? 'Out of stock' : '<span class="mi">shopping_cart</span> Add to Cart';
            }

            document.getElementById('productModal').style.display = 'flex';
        }

        function addToCart(productId, quantity, button) {
            const data = new FormData();
            data.append('csrf_token', CART_TOKEN);
            data.append('product_id', productId);
            data.append('qty', quantity);
            const label = button ? button.innerHTML : '';
            if (button) { button.disabled = true; button.textContent = 'Adding...'; }
            return fetch(CART_ENDPOINT, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' }
            })
                .then(response => response.json())
                .then(result => {
                    if (typeof result.count !== 'undefined' && window.BQ) window.BQ.setCartCount(result.count);
                    if (window.BQ) window.BQ.toast(result.message || 'Done.', result.ok ? '' : 'error');
                    return result;
                })
                .catch(() => {
                    if (window.BQ) window.BQ.toast('Could not reach the server. Please try again.', 'error');
                })
                .finally(() => {
                    if (button) { button.disabled = false; button.innerHTML = label; }
                });
        }

        document.querySelectorAll('[data-add-to-cart]').forEach(button => {
            button.addEventListener('click', event => {
                event.stopPropagation();
                addToCart(button.dataset.addToCart, 1, button);
            });
        });

        const modalAddButton = document.getElementById('modalAddCart');
        if (modalAddButton) {
            const qtyInput = document.getElementById('modalQty');
            const clampQty = () => {
                const max = Number(qtyInput.max) || 1;
                let value = parseInt(qtyInput.value, 10);
                if (!value || value < 1) value = 1;
                if (value > max) value = max;
                qtyInput.value = value;
                return value;
            };
            document.getElementById('qtyMinus').addEventListener('click', () => { qtyInput.value = Number(qtyInput.value) - 1; clampQty(); });
            document.getElementById('qtyPlus').addEventListener('click', () => { qtyInput.value = Number(qtyInput.value) + 1; clampQty(); });
            qtyInput.addEventListener('change', clampQty);
            modalAddButton.addEventListener('click', () => {
                if (!modalProduct) return;
                addToCart(modalProduct.id, clampQty(), modalAddButton);
            });
        }

        function closeModal(e) {
            if (e === true || e.target.id === 'productModal') {
                document.getElementById('productModal').style.display = 'none';
            }
        }

        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', event => {
                if (event.target.closest('a, button, form')) return;
                openModal(JSON.parse(card.dataset.product));
            });
        });
    </script>
    <script src="../js/lightbox.js" defer></script>
    <script src="../js/confirmations.js" defer></script>
    <script src="../js/auto-search.js" defer></script>
</body>
</html>