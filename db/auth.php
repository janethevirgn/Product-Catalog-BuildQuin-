<?php
// Session setup and login helpers used by every page: secure session cookie, CSRF token,
// role checks (admin / staff / user), the login log, and small output helpers.

// ---- Session ----
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isSecure,
    'samesite' => 'Strict',
]);
session_start();

// ---- Roles ----
/*
 * Roles
 *   admin - the owner / boss. Full control, including accounts and deleting products.
 *   staff - clerk. Manages products, processes orders and answers feedback.
 *   user  - customer. Browses, adds to cart, orders and sends feedback.
 */
const STAFF_ROLES = ['admin', 'staff'];

// ---- Reading input and escaping output ----

/** Reads a text value from $_POST ('' if missing). */
function post_string(string $name): string
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
}

/** Reads a text value from $_GET ('' if missing). */
function get_string(string $name): string
{
    return isset($_GET[$name]) && is_string($_GET[$name]) ? $_GET[$name] : '';
}

/** Escape any value (including null) for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ---- CSRF protection ----

/** Returns the session's CSRF token, creating one if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** True when the submitted form token matches the session token. */
function csrf_is_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ---- Login check ----

/** Sends the visitor to the login page unless they are signed in and allowed. */
function require_login(): void
{
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.html?mode=login');
        exit();
    }

    $stmt = $conn->prepare('SELECT full_name, email, role, staff_role, account_status, suspended_until, (suspended_until IS NOT NULL AND suspended_until > NOW()) AS still_suspended FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../index.html?mode=login');
        exit();
    }

    // Dropped or temporarily suspended accounts are signed out right away.
    $blocked = account_block_reason($user);
    if ($blocked !== '') {
        login_log_close((int) ($_SESSION['login_log_id'] ?? 0), $blocked);
        $_SESSION = [];
        session_destroy();
        header('Location: ../index.html?notice=account_' . $blocked);
        exit();
    }
    login_log_touch();

    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['staff_role'] = $user['staff_role'];
}

/**
 * '' when the account may sign in, otherwise 'dropped' or 'suspended'.
 * A suspension that has run out is lifted automatically.
 */
function account_block_reason(array $user): string
{
    global $conn;

    $status = (string) ($user['account_status'] ?? 'active');
    if ($status === 'dropped') {
        return 'dropped';
    }
    if ($status === 'suspended') {
        if (!empty($user['still_suspended'])) {
            return 'suspended';
        }
        if (!empty($user['id'])) {
            $lift = $conn->prepare("UPDATE users SET account_status = 'active', suspended_until = NULL WHERE id = ? AND account_status = 'suspended'");
            $lift->bind_param('i', $user['id']);
            $lift->execute();
        } elseif (!empty($_SESSION['user_id'])) {
            $lift = $conn->prepare("UPDATE users SET account_status = 'active', suspended_until = NULL WHERE id = ? AND account_status = 'suspended'");
            $lift->bind_param('i', $_SESSION['user_id']);
            $lift->execute();
        }
    }
    return '';
}

// ---- Login log ----

/** Starts a row in the login log and returns its id. */
function login_log_open(array $user): int
{
    global $conn;

    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
    $userId = (int) $user['id'];
    $stmt = $conn->prepare('INSERT INTO login_logs (user_id, full_name, email, role, staff_role, ip_address, login_at, last_seen) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('isssss', $userId, $user['full_name'], $user['email'], $user['role'], $user['staff_role'], $ip);
    try {
        $stmt->execute();
        return (int) $conn->insert_id;
    } catch (mysqli_sql_exception $exception) {
        error_log('Login log failed: ' . $exception->getMessage());
        return 0;
    }
}

/** Marks the sign-out time. $reason: signed_out, suspended or dropped. */
function login_log_close(int $logId, string $reason = 'signed_out'): void
{
    global $conn;

    if ($logId < 1) {
        return;
    }
    $stmt = $conn->prepare('UPDATE login_logs SET logout_at = NOW(), last_seen = NOW(), end_reason = ? WHERE id = ? AND logout_at IS NULL');
    $stmt->bind_param('si', $reason, $logId);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        error_log('Login log close failed: ' . $exception->getMessage());
    }
}

/** Remembers the last time this signed-in session was used (at most once a minute). */
function login_log_touch(): void
{
    global $conn;

    $logId = (int) ($_SESSION['login_log_id'] ?? 0);
    if ($logId < 1 || time() - (int) ($_SESSION['login_log_touch'] ?? 0) < 60) {
        return;
    }
    $_SESSION['login_log_touch'] = time();
    $stmt = $conn->prepare('UPDATE login_logs SET last_seen = NOW() WHERE id = ? AND logout_at IS NULL');
    $stmt->bind_param('i', $logId);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        error_log('Login log touch failed: ' . $exception->getMessage());
    }
}

// ---- Role checks ----

/** True when the signed-in user has one of the given roles. */
function has_role(array $roles): bool
{
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles, true);
}

/** True for admin or staff accounts. */
function is_staff_side(): bool
{
    return has_role(STAFF_ROLES);
}

/** True only for the admin (owner) account. */
function is_admin(): bool
{
    return has_role(['admin']);
}

/** Turns a role key into text for display (admin -> Admin, user -> Customer). */
function role_label(?string $role): string
{
    $labels = ['admin' => 'Admin', 'staff' => 'Staff', 'user' => 'Customer'];
    return $labels[$role ?? ''] ?? ucfirst((string) $role);
}

/** Stops the page with a 403 error unless the user has one of the given roles. */
function require_roles(array $roles): void
{
    if (!has_role($roles)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

// ---- Misc helpers ----

/** Sends an array as JSON with an HTTP status code, then stops. */
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

/** True when the request was made by our JavaScript fetch() calls. */
function is_ajax_request(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/** Fixes the path of images stored in the photos folder so pages in subfolders can load them. */
function catalog_image_url(?string $url): string
{
    $url = (string) $url;
    return preg_match('#^photos/[A-Za-z0-9_.-]+$#', $url) ? '../' . $url : $url;
}

// ---- Load the other helper files ----
require_once __DIR__ . '/staff_roles.php';
require_once __DIR__ . '/shop.php';
?>
