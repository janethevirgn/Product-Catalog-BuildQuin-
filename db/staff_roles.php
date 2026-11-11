<?php
/*
 * Admin / staff roles and their passwords.
 *
 *   Admin (owner)  - full access. Only ONE Admin account can exist.
 *   Staff roles    - Clerk, Monitor, Administrative Assistant, Manager, plus any role the Admin
 *                    adds on the Accounts page (stored in the custom_roles table). Any number of people
 *                    can register under each one, and all of them get the same staff pages.
 *
 * Admins and staff do not have personal passwords. Each ROLE has one password, which is what
 * they type when they register and when they sign in (together with their Gmail address).
 * On top of that, every admin/staff account has its own SECURITY PASSWORD (spare password), chosen at
 * registration and stored hashed in users.security_password_hash. Login needs Gmail + security password
 * + the password of the role.
 * To change a role password, edit the value on the right and save this file. Keep this file private.
 */
// ---- Role passwords and labels ----
const ADMIN_ROLE_PASSWORD = 'admin@123A';

const STAFF_ROLE_LABELS = [
    'clerk' => 'Clerk',
    'monitor' => 'Monitor',
    'administrative_assistant' => 'Administrative Assistant',
    'manager' => 'Manager',
];

const STAFF_ROLE_PASSWORDS = [
    'clerk' => 'clerk@123C',
    'monitor' => 'monitor@123M',
    'administrative_assistant' => 'admin@123AA',
    'manager' => 'manager@123M',
];

/** Roles the Admin added from the Accounts page: role_key => ['label' => ..., 'hash' => ...]. */
function custom_staff_roles(bool $refresh = false): array
{
    static $cache = null;
    global $conn;

    if ($cache === null || $refresh) {
        $cache = [];
        try {
            $result = $conn->query('SELECT role_key, label, password_hash FROM custom_roles ORDER BY label ASC');
            while ($result && ($row = $result->fetch_assoc())) {
                $cache[$row['role_key']] = ['label' => $row['label'], 'hash' => $row['password_hash']];
            }
        } catch (Throwable $exception) {
            // The table is created by db/schema.php; until then only the built-in roles exist.
        }
    }
    return $cache;
}

/** Every staff role (built-in + added by the Admin): role_key => label. */
function staff_role_labels(): array
{
    $labels = STAFF_ROLE_LABELS;
    foreach (custom_staff_roles() as $key => $role) {
        $labels[$key] = $role['label'];
    }
    return $labels;
}

/** True when the given key is a known staff role. */
function staff_role_is_valid(string $staffRole): bool
{
    return isset(staff_role_labels()[$staffRole]);
}

// ---- Password checks ----

/** $roleKey is "admin" or the key of any staff role. */
function role_password_is_valid(string $roleKey, string $password): bool
{
    if ($roleKey === 'admin') {
        return hash_equals(ADMIN_ROLE_PASSWORD, $password);
    }
    if (isset(STAFF_ROLE_PASSWORDS[$roleKey])) {
        return hash_equals(STAFF_ROLE_PASSWORDS[$roleKey], $password);
    }
    $custom = custom_staff_roles();
    return isset($custom[$roleKey]) && password_verify($password, $custom[$roleKey]['hash']);
}

/** Finds which staff role a password belongs to, or '' when it matches none. */
function staff_role_for_password(string $password): string
{
    foreach (STAFF_ROLE_PASSWORDS as $roleKey => $rolePassword) {
        if (hash_equals($rolePassword, $password)) {
            return $roleKey;
        }
    }
    foreach (custom_staff_roles() as $roleKey => $role) {
        if (password_verify($password, $role['hash'])) {
            return $roleKey;
        }
    }
    return '';
}

// ---- Display ----

/** Text shown next to the user's name: "Admin", "Manager", "Clerk", "Customer"... */
function role_display(?string $role, ?string $staffRole): string
{
    if ($role === 'staff') {
        return staff_role_labels()[$staffRole ?? ''] ?? 'Staff';
    }
    return role_label($role);
}
?>
