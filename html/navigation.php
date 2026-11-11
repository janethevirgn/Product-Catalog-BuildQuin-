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

// Navigation bar shown at the top of every page: brand, menu links,
// cart icon (customers only), notifications and the sign-out button.

// Work out which menu link should be highlighted and what the user may see.
$currentPage = get_string('page');
if ($currentPage === 'home') $currentPage = 'dashboard';

$navUserId = (int) $_SESSION['user_id'];
$navIsStaff = is_staff_side();
$navUnread = notification_unread_count($conn, $navUserId);
$navCartCount = $navIsStaff ? 0 : cart_count($conn, $navUserId);
$navPendingOrders = $navIsStaff ? pending_order_count($conn) : 0;
$navOpenFeedback = $navIsStaff ? open_feedback_count($conn) : 0;
$homePage = $navIsStaff ? 'dashboard' : 'products';
?>
<header class="navbar">
    <a href="index.php?page=<?= $homePage ?>" class="brand-wrapper">
        <span class="brand-icon">BQ</span>
        <span class="brand-text"><span class="brand-title">BuildQuin</span><span class="brand-subtitle">Catalog &amp; Supplies</span></span>
    </a>

    <nav class="nav-links" aria-label="Main navigation">
        <?php if ($navIsStaff): ?>
            <a class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">Dashboard</a>
            <a class="<?= $currentPage === 'products' ? 'active' : '' ?>" href="index.php?page=products">Products</a>
            <a class="<?= $currentPage === 'categories' ? 'active' : '' ?>" href="index.php?page=categories">Categories</a>
            <a class="<?= $currentPage === 'orders' ? 'active' : '' ?>" href="index.php?page=orders">Orders <span
                class="nav-badge"
                id="navOrdersBadge"
                <?= $navPendingOrders > 0 ? '' : 'hidden' ?>
            ><?= $navPendingOrders ?></span></a>
            <a class="<?= $currentPage === 'inquiries' ? 'active' : '' ?>" href="index.php?page=inquiries">Feedback <span
                class="nav-badge"
                id="navFeedbackBadge"
                <?= $navOpenFeedback > 0 ? '' : 'hidden' ?>
            ><?= $navOpenFeedback ?></span></a>
            <?php if (is_admin()): ?>
                <a class="<?= $currentPage === 'users' ? 'active' : '' ?>" href="index.php?page=users">Accounts</a>
            <?php endif; ?>
        <?php else: ?>
            <a class="<?= $currentPage === 'products' ? 'active' : '' ?>" href="index.php?page=products">Products</a>
            <a class="<?= $currentPage === 'categories' ? 'active' : '' ?>" href="index.php?page=categories">Categories</a>
            <a class="<?= in_array($currentPage, ['my_orders', 'cart'], true) ? 'active' : '' ?>" href="index.php?page=my_orders">My Orders</a>
            <a class="<?= $currentPage === 'my_feedback' ? 'active' : '' ?>" href="index.php?page=my_feedback">My Feedback</a>
        <?php endif; ?>
    </nav>

    <div class="nav-right">
        <?php if (!$navIsStaff): ?>
            <a class="nav-icon-btn cart-btn" id="cartButton" href="index.php?page=cart" aria-label="Open my cart" title="My cart">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="21"
                    height="21"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                <span
                    class="icon-badge"
                    id="cartBadge"
                    <?= $navCartCount > 0 ? '' : 'hidden' ?>
                ><?= $navCartCount > 99 ? '99+' : $navCartCount ?></span>
            </a>
        <?php endif; ?>

        <div
            class="notif-wrap"
            id="notifWrap"
            data-endpoint="index.php?page=notifications"
            data-read-endpoint="index.php?page=notifications_read"
            data-token="<?= e(csrf_token()) ?>">
            <button
                type="button"
                class="nav-icon-btn"
                id="notificationBtn"
                aria-label="Notifications"
                aria-haspopup="true"
                aria-expanded="false"
                title="Notifications">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="21"
                    height="21"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <span class="notif-dot" id="notifDot" <?= $navUnread > 0 ? '' : 'hidden' ?>></span>
            </button>
            <div class="notif-dropdown" id="notificationDropdown" hidden>
                <div class="notif-header">
                    <strong>Notifications</strong>
                    <span class="notif-count" id="notifCount"><?= $navUnread > 0 ? $navUnread . ' new' : '' ?></span>
                    <button type="button" class="notif-markall" id="markAllRead">Mark all as read</button>
                </div>
                <div class="notif-list" id="notificationContent">
                    <p class="notif-empty">Loading...</p>
                </div>
            </div>
        </div>

        <div class="account-nav">
            <span class="account-name"><?= e($_SESSION['full_name']) ?> <em
                class="role-chip role-<?= e($_SESSION['role']) ?>"
            ><?= e(role_display($_SESSION['role'], $_SESSION['staff_role'] ?? null)) ?></em></span>
            <form
                action="index.php?page=logout"
                method="post"
                data-confirm
                data-confirm-title="Sign out"
                data-confirm-message="Are you sure you want to sign out?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit">Sign out</button>
            </form>
        </div>
    </div>
</header>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script src="../js/notifications.js?v=bq6" defer></script>
