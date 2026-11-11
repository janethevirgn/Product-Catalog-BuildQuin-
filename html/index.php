<?php
define('APP_ROUTER', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../db/auth.php';

$page = get_string('page') ?: 'home';

/* ------------------------------------------------------------ form token for login pages */
if ($page === 'csrf') {
    header('Content-Type: application/json');
    echo json_encode([
        'token' => csrf_token(),
        'loggedIn' => isset($_SESSION['user_id']),
        'role' => $_SESSION['role'] ?? null,
    ]);
    exit();
}

/* ------------------------------------------------------------- register / login / logout */
if ($page === 'register' || $page === 'login' || $page === 'logout') {
    $isStaffRegistration = $page === 'register' && post_string('account_type') === 'staff';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
        $back = $page === 'register' ? ($isStaffRegistration ? 'register_staff.php' : 'register_user.php') : '../index.html';
        header('Location: ' . $back . '?notice=invalid_request');
        exit();
    }

    if ($isStaffRegistration) {
        // One simple form: Full name, Gmail, Role and the Password of that role.
        $name = trim(post_string('full_name'));
        $email = strtolower(trim(post_string('email')));
        $roleKey = post_string('role');
        $rolePassword = post_string('password');
        $securityPassword = post_string('security_password');
        $confirmSecurity = post_string('confirm_security_password');

        if ($roleKey !== 'admin' && !staff_role_is_valid($roleKey)) {
            header('Location: register_staff.php?notice=choose_role');
            exit();
        }
        if ($name === '' || mb_strlen($name) > 100 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !preg_match('/@gmail\.com$/', $email)) {
            header('Location: register_staff.php?notice=invalid_registration');
            exit();
        }
        if (!role_password_is_valid($roleKey, $rolePassword)) {
            header('Location: register_staff.php?notice=bad_code');
            exit();
        }

        if (strlen($securityPassword) < 8 || strlen($securityPassword) > 72) {
            header('Location: register_staff.php?notice=security_short');
            exit();
        }
        if (!hash_equals($securityPassword, $confirmSecurity)) {
            header('Location: register_staff.php?notice=security_mismatch');
            exit();
        }
        if (hash_equals($rolePassword, $securityPassword)) {
            header('Location: register_staff.php?notice=security_same');
            exit();
        }

        $existing = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $existing->bind_param('s', $email);
        $existing->execute();
        if ($existing->get_result()->num_rows > 0) {
            header('Location: register_staff.php?notice=email_exists');
            exit();
        }

        // Admins and staff sign in with their role's password, so no personal password is stored.
        // The column still needs a value, so it holds a random hash nobody can type.
        $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $securityHash = password_hash($securityPassword, PASSWORD_DEFAULT);
        $created = false;
        $errorCode = 0;
        try {
            if ($roleKey === 'admin') {
                // Only one Admin may exist. The check and the insert happen in one statement.
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, staff_role, security_password_hash) SELECT ?, ?, ?, 'admin', NULL, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin')");
                $stmt->bind_param('ssss', $name, $email, $passwordHash, $securityHash);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    header('Location: register_staff.php?notice=admin_taken');
                    exit();
                }
                $created = true;
            } else {
                // If the Admin already reserved a seat for this role ("Add a staff member"), take that seat.
                $seat = $conn->prepare("SELECT id FROM users WHERE role = 'staff' AND staff_role = ? AND email LIKE 'seat-%@pending.local' ORDER BY id ASC LIMIT 1");
                $seat->bind_param('s', $roleKey);
                $seat->execute();
                $seatRow = $seat->get_result()->fetch_assoc();
                if ($seatRow) {
                    $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, password_hash = ?, security_password_hash = ?, created_at = NOW() WHERE id = ?');
                    $seatId = (int) $seatRow['id'];
                    $stmt->bind_param('ssssi', $name, $email, $passwordHash, $securityHash, $seatId);
                    $created = $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, staff_role, security_password_hash) VALUES (?, ?, ?, 'staff', ?, ?)");
                    $stmt->bind_param('sssss', $name, $email, $passwordHash, $roleKey, $securityHash);
                    $created = $stmt->execute();
                }
            }
        } catch (mysqli_sql_exception $exception) {
            $errorCode = (int) $exception->getCode();
        }
        if (!$created) {
            header('Location: register_staff.php?notice=' . ($errorCode === 1062 ? 'email_exists' : 'registration_error'));
            exit();
        }

        header('Location: ../index.html?mode=admin&notice=staff_registered');
        exit();
    }

    if ($page === 'register') {
        $name = trim(post_string('full_name'));
        $email = strtolower(trim(post_string('email')));
        $password = post_string('password');
        $confirmPassword = post_string('confirm_password');

        if ($name === '' || mb_strlen($name) > 100 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 8 || strlen($password) > 72 || $password !== $confirmPassword) {
            header('Location: register_user.php?notice=invalid_registration');
            exit();
        }

        $existing = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $existing->bind_param('s', $email);
        $existing->execute();
        if ($existing->get_result()->num_rows > 0) {
            header('Location: register_user.php?notice=email_exists');
            exit();
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param('sss', $name, $email, $passwordHash);
        $created = false;
        $errorCode = 0;
        try {
            $created = $stmt->execute();
        } catch (mysqli_sql_exception $exception) {
            $errorCode = (int) $exception->getCode();
        }
        if (!$created) {
            header('Location: register_user.php?notice=' . ($errorCode === 1062 ? 'email_exists' : 'registration_error'));
            exit();
        }

        header('Location: ../index.html?mode=user&notice=registered');
        exit();
    }

    if ($page === 'login') {
        $email = strtolower(trim(post_string('email')));
        $password = post_string('password');
        $accountType = post_string('account_type');
        $securityPassword = post_string('security_password');
        $mode = $accountType === 'admin' ? 'admin' : 'user';
        if (!in_array($accountType, ['user', 'admin'], true) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) > 72 || strlen($securityPassword) > 72) {
            header('Location: ../index.html?mode=' . $mode . '&notice=login_failed');
            exit();
        }

        $ipAddress = substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
        $attemptCheck = $conn->prepare('SELECT attempts, (last_attempt > (NOW() - INTERVAL 15 MINUTE)) AS recent FROM login_attempts WHERE email = ? AND ip_address = ?');
        $attemptCheck->bind_param('ss', $email, $ipAddress);
        $attemptCheck->execute();
        $attemptRecord = $attemptCheck->get_result()->fetch_assoc();
        if ($attemptRecord && (int) $attemptRecord['attempts'] >= 5 && (int) $attemptRecord['recent'] === 1) {
            header('Location: ../index.html?mode=' . $mode . '&notice=too_many_attempts');
            exit();
        }

        $recordFailure = function () use ($conn, $email, $ipAddress) {
            $recordAttempt = $conn->prepare('INSERT INTO login_attempts (email, ip_address, attempts, last_attempt) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = IF(last_attempt < NOW() - INTERVAL 15 MINUTE, 1, attempts + 1), last_attempt = NOW()');
            $recordAttempt->bind_param('ss', $email, $ipAddress);
            $recordAttempt->execute();
        };

        $stmt = $conn->prepare('SELECT id, full_name, email, password_hash, role, staff_role, security_password_hash, account_status, suspended_until, (suspended_until IS NOT NULL AND suspended_until > NOW()) AS still_suspended FROM users WHERE email = ? AND email NOT LIKE \'%@pending.local\'');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $staffRoleToSave = null;
        if ($accountType === 'admin') {
            // Admin / staff tab: Gmail + security password + the password of their role.
            $loginOk = false;
            $securityHashToSave = null;
            if ($user && in_array($user['role'], STAFF_ROLES, true)) {
                if (empty($user['security_password_hash'])) {
                    // Account made before security passwords existed: the one they type now becomes theirs.
                    // It is only kept if the role password below is also correct.
                    $securityOk = strlen($securityPassword) >= 8 && !hash_equals($password, $securityPassword);
                    if ($securityOk) {
                        $securityHashToSave = password_hash($securityPassword, PASSWORD_DEFAULT);
                    }
                } else {
                    $securityOk = $securityPassword !== '' && password_verify($securityPassword, $user['security_password_hash']);
                }
                if (!$securityOk) {
                    // Wrong security password: stop here, and still count it as a failed attempt.
                } elseif ($user['role'] === 'admin') {
                    $loginOk = role_password_is_valid('admin', $password);
                } else {
                    $savedRole = (string) ($user['staff_role'] ?? '');
                    if ($savedRole !== '') {
                        $loginOk = role_password_is_valid($savedRole, $password);
                    } else {
                        // Older staff accounts have no saved role yet: use the role their password belongs to.
                        $detectedRole = staff_role_for_password($password);
                        if ($detectedRole !== '') {
                            $loginOk = true;
                            $staffRoleToSave = $detectedRole;
                        }
                    }
                }
            }
            if (!$loginOk) {
                $recordFailure();
                $customerTriedStaffTab = $user && $user['role'] === 'user' && password_verify($password, $user['password_hash']);
                header('Location: ../index.html?mode=admin&notice=' . ($customerTriedStaffTab ? 'wrong_portal' : 'login_failed'));
                exit();
            }
        } else {
            // Customer tab: Gmail/email + personal password.
            if ($user && in_array($user['role'], STAFF_ROLES, true)) {
                header('Location: ../index.html?mode=admin&notice=wrong_portal');
                exit();
            }
            if (!$user || $password === '' || !password_verify($password, $user['password_hash'])) {
                $recordFailure();
                header('Location: ../index.html?mode=user&notice=login_failed');
                exit();
            }
        }
        $isStaffAccount = in_array($user['role'], STAFF_ROLES, true);

        // Correct credentials, but the owner may have dropped or suspended this account.
        $blocked = account_block_reason($user);
        if ($blocked !== '') {
            $until = $blocked === 'suspended' ? '&until=' . urlencode(date('M j, Y g:i a', strtotime((string) $user['suspended_until']))) : '';
            header('Location: ../index.html?mode=' . $mode . '&notice=account_' . $blocked . $until);
            exit();
        }

        $clearAttempts = $conn->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?');
        $clearAttempts->bind_param('ss', $email, $ipAddress);
        $clearAttempts->execute();
        if ($accountType !== 'admin' && password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateHash = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $updateHash->bind_param('si', $newHash, $user['id']);
            $updateHash->execute();
        }
        if (!empty($securityHashToSave)) {
            $saveSecurity = $conn->prepare('UPDATE users SET security_password_hash = ? WHERE id = ?');
            $saveSecurity->bind_param('si', $securityHashToSave, $user['id']);
            $saveSecurity->execute();
        }
        if ($staffRoleToSave !== null) {
            $saveRole = $conn->prepare('UPDATE users SET staff_role = ? WHERE id = ?');
            $saveRole->bind_param('si', $staffRoleToSave, $user['id']);
            $saveRole->execute();
            $user['staff_role'] = $staffRoleToSave;
        }
        $lastLogin = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $lastLogin->bind_param('i', $user['id']);
        $lastLogin->execute();

        session_regenerate_id(true);
        $_SESSION['login_log_id'] = login_log_open($user);
        $_SESSION['login_log_touch'] = time();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['staff_role'] = $user['staff_role'];
        unset($_SESSION['csrf_token']);
        header('Location: index.php?page=' . ($isStaffAccount ? 'dashboard' : 'products'));
        exit();
    }

    // logout
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.html?mode=login');
        exit();
    }
    login_log_close((int) ($_SESSION['login_log_id'] ?? 0), 'signed_out');
    $_SESSION = [];
    session_destroy();
    header('Location: ../index.html?notice=logged_out');
    exit();
}

/* ------------------------------------------------------------------------------ routing */
$routes = [
    'dashboard' => 'home.php',
    'home' => 'home.php',
    'products' => 'products.php',
    'categories' => 'categories.php',
    'add' => 'add.php',
    'update' => 'update.php',
    'delete' => 'delete.php',
    'add_category' => 'add_category.php',
    'edit_category' => 'edit_category.php',
    'delete_category' => 'delete_category.php',
    'users' => 'users.php',
    'inquiries' => 'inquiries.php',
    'orders' => 'orders.php',
    'submit_feedback' => 'submit_feedback.php',
    'my_feedback' => 'my_feedback.php',
    'cart' => 'cart.php',
    'cart_add' => 'cart_actions.php',
    'cart_update' => 'cart_actions.php',
    'cart_remove' => 'cart_actions.php',
    'address_save' => 'cart_actions.php',
    'address_delete' => 'cart_actions.php',
    'place_order' => 'cart_actions.php',
    'my_orders' => 'my_orders.php',
    'notifications' => 'notifications.php',
    'notifications_read' => 'notifications.php',
];

$jsonPages = ['notifications', 'notifications_read', 'cart_add'];
$staffPages = ['dashboard', 'home', 'add', 'update', 'add_category', 'edit_category', 'inquiries', 'orders'];
$adminOnlyPages = ['users', 'delete', 'delete_category'];
$customerPages = ['submit_feedback', 'my_feedback', 'cart', 'cart_add', 'cart_update', 'cart_remove', 'address_save', 'address_delete', 'place_order', 'my_orders'];

if (!isset($routes[$page])) {
    http_response_code(404);
    exit('Page not found.');
}

if (in_array($page, $jsonPages, true) && !isset($_SESSION['user_id'])) {
    json_out(['ok' => false, 'message' => 'Please sign in again.'], 401);
}

require_login();

if (in_array($page, ['dashboard', 'home'], true) && has_role(['user'])) {
    header('Location: index.php?page=products');
    exit();
}
if (in_array($page, $staffPages, true)) {
    require_roles(STAFF_ROLES);
}
if (in_array($page, $adminOnlyPages, true)) {
    require_roles(['admin']);
}
if (in_array($page, $customerPages, true)) {
    require_roles(['user']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_is_valid()) {
    if (is_ajax_request() || in_array($page, $jsonPages, true)) {
        json_out(['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 400);
    }
    http_response_code(400);
    exit('Invalid form token. Refresh the page and try again.');
}

include __DIR__ . '/' . $routes[$page];
