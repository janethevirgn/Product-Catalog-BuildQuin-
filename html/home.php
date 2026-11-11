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

// Staff dashboard: shows totals (products, categories, orders, feedback, stock),
// the 5 most recent orders and the 5 most recently added products.

require_once __DIR__ . '/../db/db.php';

// ---- Load data ----
// Product and category totals
$count_result = $conn->query('SELECT COUNT(*) AS total FROM products');
$total_products = $count_result ? (int) $count_result->fetch_assoc()['total'] : 0;
$category_result = $conn->query('SELECT COUNT(*) AS total FROM categories');
$total_categories = $category_result ? (int) $category_result->fetch_assoc()['total'] : 0;
// Latest 5 products (shown in the "Latest products" table)
$recentProducts = $conn->query('SELECT products.id, products.product_code, products.name, categories.category_name AS category, products.price, products.stock FROM products JOIN categories ON categories.id = products.category_id ORDER BY products.id DESC LIMIT 5');

// Account and sales totals (admin only)
$total_users = 0;
$total_sales = 0.0;
if (is_admin()) {
    $userResult = $conn->query('SELECT COUNT(*) AS total FROM users');
    $total_users = $userResult ? (int) $userResult->fetch_assoc()['total'] : 0;
    $salesResult = $conn->query("SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE status = 'completed'");
    $total_sales = $salesResult ? (float) $salesResult->fetch_assoc()['total'] : 0.0;
}

// Pending work and low-stock counts
$pending_feedback = open_feedback_count($conn);
$pending_orders = pending_order_count($conn);
$lowStockResult = $conn->query('SELECT COUNT(*) AS total FROM products WHERE COALESCE(stock, 0) <= 5');
$low_stock = $lowStockResult ? (int) $lowStockResult->fetch_assoc()['total'] : 0;

// Latest 5 orders (shown in the "Recent orders" table)
$recentOrders = $conn->query('SELECT id, order_number, customer_name, total, status, created_at FROM orders ORDER BY id DESC LIMIT 5');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/app.css">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/orders.css?v=bq6">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body class="dashboard-page">
    <?php
    // Top navigation bar
    include __DIR__ . '/navigation.php';
    ?>

    <main class="dashboard-main">
        <div class="dashboard-heading">
            <div>
                <p class="eyebrow">CATALOG OVERVIEW</p>
                <h1>Welcome, <?= e($_SESSION['full_name']) ?></h1>
                <p>Here is the current snapshot of your BuildQuin catalog.</p>
            </div>
            <span class="role-label"><?= e(role_display($_SESSION['role'], $_SESSION['staff_role'] ?? null)) ?> access</span>
        </div>

        <section class="dashboard-catalog-intro" aria-label="BuildQuin catalog highlights">
            <span class="hero-badge">High Quality Construction Materials</span>
            <h2>Build Better with BuildQuin</h2>
            <div class="dashboard-categories">
                <span>Paints</span><span>Lighting</span><span>Tiles</span><span>Furniture</span>
                <span>Windows</span><span>Glass</span><span>Bedding</span><span>Roof</span>
            </div>
            <p>Explore our architectural catalog featuring top-tier roofing, premium paints, modern lighting fixtures, and interior architectural supplies.</p>
        </section>

        <section class="dashboard-stats" aria-label="Catalog totals">
            <div>
                <span>Pending orders</span><strong><?= $pending_orders ?></strong><a href="index.php?page=orders&amp;status=pending">Review orders</a>
            </div>
            <div>
                <span>Open feedback</span><strong><?= $pending_feedback ?></strong><a href="index.php?page=inquiries">Respond to users</a>
            </div>
            <div>
                <span>Products</span><strong><?= $total_products ?></strong><a href="index.php?page=products">View products</a>
            </div>
            <div>
                <span>Low stock (5 or less)</span><strong><?= $low_stock ?></strong><a href="index.php?page=products">Check stock</a>
            </div>
            <div>
                <span>Categories</span><strong><?= $total_categories ?></strong><a href="index.php?page=categories">View categories</a>
            </div>
            <?php if (is_admin()): ?>
                <div>
                    <span>Accounts</span><strong><?= $total_users ?></strong><a href="index.php?page=users">Manage access</a>
                </div>
                <div>
                    <span>Completed sales</span><strong class="money-figure"><?= e(money($total_sales)) ?></strong><a href="index.php?page=orders&amp;status=completed">View sales</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="dashboard-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">LATEST ACTIVITY</p>
                    <h2>Recent orders</h2>
                </div>
                <a href="index.php?page=orders&amp;status=all">All orders <span class="mi" aria-hidden="true">arrow_forward</span></a>
            </div>
            <div class="recent-table-wrap">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentOrders && $recentOrders->num_rows > 0): while ($order = $recentOrders->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><a class="order-link" href="index.php?page=orders&amp;status=<?= e($order['status']) ?>"><?= e($order['order_number']) ?></a></strong></td>
                                    <td><?= e($order['customer_name']) ?></td>
                                    <td><?= e(money($order['total'])) ?></td>
                                    <td><span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td>
                                    <td><?= e(date('M j, g:i a', strtotime($order['created_at']))) ?></td>
                                </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5">No orders yet. Customer orders will show up here.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">RECENTLY ADDED</p>
                    <h2>Latest products</h2>
                </div>
                <a href="index.php?page=products">All products <span class="mi" aria-hidden="true">arrow_forward</span></a>
            </div>
            <div class="recent-table-wrap">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentProducts && $recentProducts->num_rows > 0): while ($product = $recentProducts->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= e($product['name']) ?></strong><small><?= e($product['product_code']) ?></small></td>
                                    <td><?= e($product['category']) ?></td>
                                    <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                                    <td><?= (int) $product['stock'] ?></td>
                                </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4">No products have been added yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer class="dashboard-footer">&copy; <?= date('Y') ?> BuildQuin Hardware &amp; Architectural Supplies</footer>
    <script src="../js/confirmations.js" defer></script>
</body>
</html>
