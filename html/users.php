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

// Admin-only page: add staff roles, and suspend, drop, restore or remove accounts.
// It also lists every account with its latest sign-in time.

require_once __DIR__ . '/../db/db.php';

// Handle the form submit.
$message = '';
$messageType = 'success';
$accessDescriptions = [
    'admin' => 'Owner access: products, categories, orders, feedback, accounts and sales',
    'staff' => 'Clerk access: add/edit products, process orders, reply to feedback',
    'user' => 'Customer: browse, add to cart, order and send feedback',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_string('action') === 'create_account') {
    // Add a role: its name and the password staff must type for it. It then shows up in the
    // Admin / Staff registration list, and anyone who registers or signs in under it uses this password.
    $roleName = trim(preg_replace('/\s+/', ' ', post_string('role_name')));
    $rolePassword = post_string('role_password');
    $roleKey = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($roleName)), '_');
    $reserved = ['admin', 'administrator', 'super_admin', 'owner', 'staff', 'user', 'customer'];

    $nameTaken = false;
    foreach (staff_role_labels() as $existingKey => $existingLabel) {
        if ($existingKey === $roleKey || strcasecmp($existingLabel, $roleName) === 0) {
            $nameTaken = true;
        }
    }

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 &\-]{1,29}$/', $roleName) || $roleKey === '') {
        $message = 'Role name: 2 to 30 characters, using letters, numbers, spaces, & or -.';
        $messageType = 'error';
    } elseif (in_array($roleKey, $reserved, true)) {
        $message = '"' . $roleName . '" is reserved. Choose another role name.';
        $messageType = 'error';
    } elseif ($nameTaken) {
        $message = 'The role "' . $roleName . '" already exists.';
        $messageType = 'error';
    } elseif (strlen($rolePassword) < 8 || strlen($rolePassword) > 72) {
        $message = 'The password of the role must be 8 to 72 characters.';
        $messageType = 'error';
    } elseif (role_password_is_valid('admin', $rolePassword) || staff_role_for_password($rolePassword) !== '') {
        $message = 'That password is already used by another role. Choose a different one.';
        $messageType = 'error';
    } else {
        $hash = password_hash($rolePassword, PASSWORD_DEFAULT);
        $create = $conn->prepare('INSERT INTO custom_roles (role_key, label, password_hash) VALUES (?, ?, ?)');
        $create->bind_param('sss', $roleKey, $roleName, $hash);
        try {
            $create->execute();
            custom_staff_roles(true);
            $message = '"' . $roleName . '" was added. It is now in the Admin / Staff registration list. Give the staff member this role\'s password so they can register and sign in.';
        } catch (mysqli_sql_exception $exception) {
            $message = 'The role could not be added.';
            $messageType = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Account actions: suspend, drop, restore or remove an empty seat.
    $userId = filter_var(post_string('user_id'), FILTER_VALIDATE_INT);
    $action = post_string('action');

    if (!$userId || !in_array($action, ['remove_seat', 'suspend', 'drop', 'restore'], true)) {
        $message = 'Choose a valid account action.';
        $messageType = 'error';
    } elseif ($userId === (int) $_SESSION['user_id']) {
        $message = 'You cannot change or delete your own account here.';
        $messageType = 'error';
    } else {
        $current = $conn->prepare('SELECT role, full_name, email FROM users WHERE id = ?');
        $current->bind_param('i', $userId);
        $current->execute();
        $target = $current->get_result()->fetch_assoc();

        if (!$target) {
            $message = 'That account could not be found.';
            $messageType = 'error';
        } elseif ($target['role'] === 'admin' && in_array($action, ['suspend', 'drop', 'remove_seat'], true)) {
            $message = 'The Admin account cannot be suspended or dropped.';
            $messageType = 'error';
        } elseif ($action === 'suspend') {
            $amount = filter_var(post_string('amount'), FILTER_VALIDATE_INT);
            $unit = post_string('unit');
            $limits = ['days' => ['DAY', 3650], 'months' => ['MONTH', 120], 'years' => ['YEAR', 10]];
            if (!$amount || $amount < 1 || !isset($limits[$unit]) || $amount > $limits[$unit][1]) {
                $message = 'Enter how long the suspension lasts (days up to 3650, months up to 120 or years up to 10).';
                $messageType = 'error';
            } else {
                $sql = "UPDATE users SET account_status = 'suspended', suspended_until = DATE_ADD(NOW(), INTERVAL ? " . $limits[$unit][0] . ') WHERE id = ?';
                $suspend = $conn->prepare($sql);
                $suspend->bind_param('ii', $amount, $userId);
                try {
                    $suspend->execute();
                    $message = $target['full_name'] . ' is suspended for ' . $amount . ' ' . ($amount === 1 ? rtrim($unit, 's') : $unit) . '. They can sign in again when it ends.';
                } catch (mysqli_sql_exception $exception) {
                    $message = 'The account could not be suspended.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'drop') {
            $drop = $conn->prepare("UPDATE users SET account_status = 'dropped', suspended_until = NULL WHERE id = ?");
            $drop->bind_param('i', $userId);
            try {
                $drop->execute();
                $message = $target['full_name'] . ' was dropped. They can no longer access the website.';
            } catch (mysqli_sql_exception $exception) {
                $message = 'The account could not be dropped.';
                $messageType = 'error';
            }
        } elseif ($action === 'restore') {
            $restore = $conn->prepare("UPDATE users SET account_status = 'active', suspended_until = NULL WHERE id = ?");
            $restore->bind_param('i', $userId);
            try {
                $restore->execute();
                $message = $target['full_name'] . ' has access again.';
            } catch (mysqli_sql_exception $exception) {
                $message = 'The account could not be restored.';
                $messageType = 'error';
            }
        } elseif ($action === 'remove_seat' && substr($target['email'], -14) !== '@pending.local') {
            $message = 'Only empty seats can be removed. Use Drop or Suspend for real accounts.';
            $messageType = 'error';
        } elseif ($action === 'remove_seat') {
            $delete = $conn->prepare('DELETE FROM users WHERE id = ?');
            $delete->bind_param('i', $userId);
            try {
                $delete->execute();
                $message = 'Empty seat removed.';
            } catch (mysqli_sql_exception $exception) {
                $message = 'The seat could not be removed.';
                $messageType = 'error';
            }
        }
    }
}

// Load data: the three account lists shown below.
/** One row per account, with the details of its latest sign-in. */
function load_accounts(mysqli $conn, string $roleFilter): array
{
    $sql = "SELECT u.id, u.full_name, u.email, u.role, u.staff_role, u.created_at, u.account_status, u.suspended_until,
                   (u.suspended_until IS NOT NULL AND u.suspended_until > NOW()) AS still_suspended,
                   l.login_at, l.last_seen, l.logout_at, l.end_reason,
                   TIMESTAMPDIFF(MINUTE, l.login_at, COALESCE(l.logout_at, l.last_seen)) AS minutes,
                   (l.logout_at IS NULL AND l.last_seen > (NOW() - INTERVAL 15 MINUTE)) AS online
            FROM users u
            LEFT JOIN login_logs l ON l.id = (SELECT MAX(x.id) FROM login_logs x WHERE x.user_id = u.id)
            WHERE u.role = ?
            ORDER BY u.created_at DESC, u.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $roleFilter);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['account_status'] === 'suspended' && !(int) $row['still_suspended']) {
            $row['account_status'] = 'active';   // the suspension is over; it is lifted at their next sign in
        }
        $rows[] = $row;
    }
    return $rows;
}
$adminRows = load_accounts($conn, 'admin');
$staffRows = load_accounts($conn, 'staff');
$customerRows = load_accounts($conn, 'user');

// Helper: turns a number of minutes into text such as "2 h 5 min".
function format_minutes(int $minutes): string
{
    if ($minutes < 1) {
        return 'Under 1 min';
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    return $hours > 0 ? $hours . ' h ' . $rest . ' min' : $rest . ' min';
}

// Helper: prints one table of accounts (used for admin, staff and customers).
function render_log_table(array $rows, string $emptyText): void
{
    $endLabels = [
        'signed_out' => 'Signed out',
        'suspended' => 'Suspended',
        'dropped' => 'Dropped'
    ];
    ?>
    <div class="table-wrap scroll-box<?= count($rows) > 5 ? ' is-scrolling' : '' ?>">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Time of Signed In</th>
                    <th>Time of Signed Out</th>
                    <th>Time Online</th>
                    <th>Status</th>
                    <th>Manage</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="empty-row"><?= e($emptyText) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $user):
                $isSelf = (int) $user['id'] === (int) $_SESSION['user_id'];
                $isSeat = substr($user['email'], -14) === '@pending.local';
                $hasLog = $user['login_at'] !== null;
            ?>
                <tr class="data-row">
                    <td><?= e($user['full_name']) ?><?= $isSelf ? ' (you)' : '' ?></td>
                    <td><span class="role <?= e($user['role']) ?>"><?= e(role_display($user['role'], $user['staff_role'])) ?></span></td>
                    <td><?= $isSeat ? '<span class="muted">Not registered yet</span>' : e($user['email']) ?></td>
                    <td><?= $isSeat ? '&mdash;' : e(date('M j, Y', strtotime($user['created_at']))) ?></td>
                    <td><?= $hasLog ? e(date('M j, Y g:i a', strtotime($user['login_at']))) : '&mdash;' ?></td>
                    <td>
                        <?= $hasLog && $user['logout_at'] !== null ? e(date('M j, Y g:i a', strtotime($user['logout_at']))) : '&mdash;' ?>
                    </td>
                    <td><?= $hasLog ? e(format_minutes((int) $user['minutes'])) : '&mdash;' ?></td>
                    <td>
                        <?php if ($user['account_status'] === 'dropped'): ?>
                            <span class="state dropped"><span class="mi">block</span> Dropped</span>
                        <?php elseif ($user['account_status'] === 'suspended'): ?>
                            <span
                                class="state suspended"
                                title="Suspended until <?= e(date('M j, Y g:i a', strtotime($user['suspended_until']))) ?>"
                            ><span class="mi">schedule</span> Until <?= e(date('M j, Y', strtotime($user['suspended_until']))) ?></span>
                        <?php elseif ($isSeat): ?>
                            <span class="state ended"><span class="mi">hourglass_top</span> Waiting to register</span>
                        <?php elseif (!$hasLog): ?>
                            <span class="state ended"><span class="mi">remove_circle_outline</span> Never signed in</span>
                        <?php elseif ($user['logout_at'] !== null): ?>
                            <span class="state ended"><span class="mi">logout</span> <?= e($endLabels[$user['end_reason']] ?? 'Signed out') ?></span>
                        <?php elseif ((int) $user['online'] === 1): ?>
                            <span class="state active"><span class="mi">radio_button_checked</span> Online</span>
                        <?php else: ?>
                            <span
                                class="state ended"
                                title="Last seen <?= e(date('M j, Y g:i a', strtotime($user['last_seen']))) ?>"
                            ><span class="mi">history</span> Session ended</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$isSelf && $user['role'] !== 'admin'): ?>
                            <div class="manage-line">
                                <?php if ($user['account_status'] === 'active'): ?>
                                    <?php if ($isSeat): ?>
                                        <form
                                            class="inline-form"
                                            method="post"
                                            action="index.php?page=users"
                                            data-confirm
                                            data-confirm-title="Remove empty seat"
                                            data-confirm-message="Remove this empty seat?"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                            <input type="hidden" name="action" value="remove_seat">
                                            <button class="m-btn drop" type="submit"><span class="mi">close</span> Remove seat</button>
                                        </form>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="m-btn suspend"
                                            data-suspend="<?= (int) $user['id'] ?>"
                                            data-name="<?= e($user['full_name']) ?>"
                                        ><span class="mi">schedule</span> Suspend</button>
                                        <form
                                            class="inline-form"
                                            method="post"
                                            action="index.php?page=users"
                                            data-confirm
                                            data-confirm-title="Drop account"
                                            data-confirm-message="Drop <?= e($user['full_name']) ?>? They will no longer be able to access the website. You can restore them later."
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                            <input type="hidden" name="action" value="drop">
                                            <button class="m-btn drop" type="submit"><span class="mi">block</span> Drop</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form class="inline-form" method="post" action="index.php?page=users">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button class="m-btn restore" type="submit"><span class="mi">lock_open</span> Restore</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="muted"><?= $isSelf ? 'This is you' : 'Owner account' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts | BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/users.css?v=bq6">
    <link rel="stylesheet" href="../css/confirmations.css">
    <link rel="stylesheet" href="../css/navigation.css?v=bq6">
</head>
<body>
    <?php include __DIR__ . '/navigation.php'; ?>

    <main class="page-wrap">
        <div class="page-heading">
            <div>
                <p class="eyebrow">OWNER CONTROLS</p>
                <h1>Accounts and roles</h1>
                <p>You are the Admin. Manage accounts and watch every sign-in.</p>
            </div>
        </div>
        <?php if ($message !== ''): ?>
            <div class="notice <?= e($messageType) ?>" role="status"><?= e($message) ?></div>
        <?php endif; ?>
        <section class="create-admin">
            <div>
                <p class="eyebrow">NEW TEAM MEMBER</p>
                <h2>Add a staff member</h2>
                <p>Type the <strong>name of the role</strong> and the <strong>password of that role</strong>. The role then appears in the Admin / Staff registration list, and staff register and sign in with that password. Current roles: <strong><?= e(implode(', ', staff_role_labels())) ?></strong>.</p>
            </div>
            <form method="post" action="index.php?page=users">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_account">
                <label for="accRoleName">Role name</label><label for="accRolePassword">Password of the role</label>
                <input id="accRoleName" name="role_name" maxlength="30" autocomplete="off" placeholder="e.g. Cashier" required>
                <input
                    id="accRolePassword"
                    name="role_password"
                    type="password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Password for this role (min. 8)"
                    required
                >
                <button type="submit">Add role</button>
            </form>
        </section>
        <section class="list-section">
            <div class="list-head">
                <h2><span class="mi">admin_panel_settings</span> ADMIN's Log</h2>
                <span class="count"><?= count($adminRows) ?></span>
            </div>
            <?php render_log_table($adminRows, 'No admin account yet.'); ?>
        </section>
        <section class="list-section">
            <div class="list-head">
                <h2><span class="mi">badge</span> STAFF's Log</h2>
                <span class="count"><?= count($staffRows) ?></span>
            </div>
            <?php render_log_table($staffRows, 'No staff yet. Use Add a staff member above.'); ?>
        </section>
        <section class="list-section">
            <div class="list-head">
                <h2><span class="mi">group</span> CUSTOMER's Log</h2>
                <span class="count"><?= count($customerRows) ?></span>
            </div>
            <p class="list-note">&ldquo;Session ended&rdquo; means the person closed the browser without signing out. The table shows each account's latest sign-in.</p>
            <?php render_log_table($customerRows, 'No customers yet.'); ?>
        </section>

        <div class="suspend-backdrop" id="suspendModal" hidden>
            <form class="suspend-dialog" method="post" action="index.php?page=users">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" id="suspendUserId" value="">
                <input type="hidden" name="action" value="suspend">
                <h2>Temporarily suspend</h2>
                <p id="suspendName" class="muted"></p>
                <p class="muted">They cannot sign in until the time is over. After that they can use the website again.</p>
                <div class="suspend-fields">
                    <input
                        type="number"
                        name="amount"
                        id="suspendAmount"
                        min="1"
                        max="3650"
                        value="7"
                        required
                        aria-label="Length"
                    >
                    <select name="unit" aria-label="Unit">
                        <option value="days">Days</option>
                        <option value="months">Months</option>
                        <option value="years">Years</option>
                    </select>
                </div>
                <div class="suspend-actions">
                    <button type="button" class="m-btn" id="suspendCancel">Cancel</button><button type="submit" class="m-btn suspend solid">Suspend</button>
                </div>
            </form>
        </div>
        <p class="role-help"><strong>BuildQuin</strong> is the owner: full control, including accounts, deleting products and categories, and sales. <strong>Staff</strong> are clerks: they add and edit products, accept or decline orders, and reply to feedback. <strong>Customer</strong> accounts browse, order and send feedback. <strong>Suspend</strong> blocks an account for a set time; <strong>Drop</strong> blocks it until you restore it. Password hashes are never shown here.</p>
    </main>

    <script src="../js/confirmations.js" defer></script>
    <script src="../js/accounts.js?v=bq7" defer></script>
</body>
</html>
