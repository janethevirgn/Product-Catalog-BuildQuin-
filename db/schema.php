<?php
// Database setup helpers: creates any missing tables and columns when a page loads,
// so a fresh or older database works without running extra scripts.
/*
 * Creates anything that is missing, without touching existing table names, column names
 * or records. Safe to run on every request (every statement is IF NOT EXISTS / guarded).
 */

// ---- Schema check ----

/** Runs the schema setup once per request, and only when something is missing. */
function bq_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (!bq_schema_is_current($conn)) {
            bq_run_schema($conn);
        }
    } catch (Throwable $exception) {
        error_log('BuildQuin schema check failed: ' . $exception->getMessage());
    }
}

/** Quick check so normal page loads skip the setup work once everything already exists. */
function bq_schema_is_current(mysqli $conn): bool
{
    $tables = "'categories','users','login_attempts','products','product_feedback','notifications','user_addresses','cart_items','orders','order_items','order_reviews','login_logs','custom_roles'";
    $result = $conn->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ($tables)");
    if (!$result || (int) $result->fetch_assoc()['total'] !== 13) {
        return false;
    }
    $role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleRow = $role ? $role->fetch_assoc() : null;
    if (strtolower((string) ($roleRow['Type'] ?? '')) !== "enum('admin','staff','user')") {
        return false;
    }
    $lastLogin = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    $staffRole = $conn->query("SHOW COLUMNS FROM users LIKE 'staff_role'");
    $security = $conn->query("SHOW COLUMNS FROM users LIKE 'security_password_hash'");
    $accountStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'account_status'");
    if (!$accountStatus || $accountStatus->num_rows !== 1) {
        return false;
    }
    return $lastLogin && $lastLogin->num_rows === 1
        && $staffRole && $staffRole->num_rows === 1
        && $security && $security->num_rows === 1;
}

// ---- Table helpers ----

/** Reads a column's type (e.g. INT UNSIGNED) so foreign keys match it; uses $fallback if unknown. */
function bq_column_type(mysqli $conn, string $table, string $column, string $fallback): string
{
    try {
        $result = $conn->query('SHOW COLUMNS FROM `' . $table . '` LIKE \'' . $conn->real_escape_string($column) . '\'');
        $row = $result ? $result->fetch_assoc() : null;
        $type = strtolower(trim((string) ($row['Type'] ?? '')));
        if ($type !== '' && preg_match('/^[a-z]+(\(\d+\))?( unsigned)?$/', $type)) {
            return strtoupper($type);
        }
    } catch (Throwable $exception) {
        // Fall back to the default type below.
    }
    return $fallback;
}

/** Create a table with foreign keys; if the foreign keys are rejected, create it without them. */
function bq_create_table(mysqli $conn, string $name, array $columns, array $constraints = []): void
{
    $suffix = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $withConstraints = 'CREATE TABLE IF NOT EXISTS `' . $name . '` ('
        . implode(', ', array_merge($columns, $constraints))
        . ')' . $suffix;
    try {
        $conn->query($withConstraints);
    } catch (mysqli_sql_exception $exception) {
        $plain = [];
        foreach ($columns as $definition) {
            $plain[] = $definition;
        }
        $conn->query('CREATE TABLE IF NOT EXISTS `' . $name . '` (' . implode(', ', $plain) . ')' . $suffix);
    }
}

// ---- Full setup ----

/** Creates every table and adds any missing columns to older databases. */
function bq_run_schema(mysqli $conn): void
{
    // ---- Core tables (same structure as setup.sql) -------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS categories (
        id INT NOT NULL AUTO_INCREMENT,
        category_name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_categories_category_name (category_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(254) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL DEFAULT NULL,
        staff_role VARCHAR(30) NULL DEFAULT NULL,
        security_password_hash VARCHAR(255) NULL DEFAULT NULL,
        account_status VARCHAR(12) NOT NULL DEFAULT 'active',
        suspended_until DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        email VARCHAR(254) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (email, ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT NOT NULL AUTO_INCREMENT,
        product_code VARCHAR(50) NOT NULL,
        name VARCHAR(150) NOT NULL,
        category_id INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        stock INT DEFAULT 10,
        description TEXT DEFAULT NULL,
        image_url VARCHAR(255) DEFAULT 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?q=80&w=600',
        img_interior VARCHAR(500) DEFAULT NULL,
        img_exterior VARCHAR(500) DEFAULT NULL,
        img_accent VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_products_product_code (product_code),
        KEY idx_products_category_id (category_id),
        CONSTRAINT fk_products_category FOREIGN KEY (category_id)
            REFERENCES categories (id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // ---- Roles: admin (owner/boss), staff (clerk), user (customer) ---------------------
    // Older copies used super_admin / admin / user. Old "admin" (clerk) becomes "staff",
    // old "super_admin" (boss) becomes "admin". This only runs while the old values exist.
    $roleResult = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleRow = $roleResult ? $roleResult->fetch_assoc() : null;
    $roleType = strtolower((string) ($roleRow['Type'] ?? ''));
    if ($roleType !== '' && $roleType !== "enum('admin','staff','user')") {
        $hasSuper = strpos($roleType, 'super_admin') !== false;
        $hasStaff = strpos($roleType, "'staff'") !== false;
        if ($hasSuper && !$hasStaff) {
            $conn->query("ALTER TABLE users MODIFY role ENUM('super_admin','admin','staff','user') NOT NULL DEFAULT 'user'");
            $conn->query("UPDATE users SET role = CASE role WHEN 'admin' THEN 'staff' WHEN 'super_admin' THEN 'admin' ELSE role END WHERE role IN ('admin','super_admin')");
        } elseif ($hasSuper) {
            $conn->query("UPDATE users SET role = 'admin' WHERE role = 'super_admin'");
        }
        if (strpos($roleType, 'administrator') !== false) {
            $conn->query("ALTER TABLE users MODIFY role ENUM('administrator','super_admin','admin','staff','user') NOT NULL DEFAULT 'user'");
            $conn->query("UPDATE users SET role = 'admin' WHERE role = 'administrator'");
        }
        $conn->query("ALTER TABLE users MODIFY role ENUM('admin','staff','user') NOT NULL DEFAULT 'user'");
    }

    // last_login column for databases that predate login tracking.
    $lastLogin = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($lastLogin && $lastLogin->num_rows === 0) {
        $conn->query('ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL');
    }

    // staff_role: which job the staff member has (clerk, monitor, administrative_assistant, manager).
    $staffRoleColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'staff_role'");
    if ($staffRoleColumn && $staffRoleColumn->num_rows === 0) {
        $conn->query('ALTER TABLE users ADD COLUMN staff_role VARCHAR(30) NULL DEFAULT NULL');
    }

    // security_password_hash: the spare "security password" every admin/staff account sets at registration.
    $securityColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'security_password_hash'");
    if ($securityColumn && $securityColumn->num_rows === 0) {
        $conn->query('ALTER TABLE users ADD COLUMN security_password_hash VARCHAR(255) NULL DEFAULT NULL');
    }

    // account_status + suspended_until: 'active', 'suspended' (until a date) or 'dropped' (no more access).
    $statusColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'account_status'");
    if ($statusColumn && $statusColumn->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN account_status VARCHAR(12) NOT NULL DEFAULT 'active'");
        $conn->query('ALTER TABLE users ADD COLUMN suspended_until DATETIME NULL DEFAULT NULL');
    }

    $uid = bq_column_type($conn, 'users', 'id', 'INT UNSIGNED');
    $pid = bq_column_type($conn, 'products', 'id', 'INT');

    // ---- Feedback ----------------------------------------------------------------------
    bq_create_table($conn, 'product_feedback', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        "product_id $pid NOT NULL",
        "user_id $uid NOT NULL",
        'message TEXT NOT NULL',
        'response TEXT NULL',
        "responded_by $uid NULL",
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'responded_at TIMESTAMP NULL DEFAULT NULL',
        'KEY idx_feedback_product (product_id)',
        'KEY idx_feedback_user (user_id)',
    ], [
        'CONSTRAINT fk_feedback_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE',
        'CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE',
        'CONSTRAINT fk_feedback_responder FOREIGN KEY (responded_by) REFERENCES users (id) ON DELETE SET NULL',
    ]);

    // ---- Notifications (bell) ----------------------------------------------------------
    bq_create_table($conn, 'notifications', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        "user_id $uid NOT NULL",
        'type VARCHAR(40) NOT NULL',
        'message VARCHAR(500) NOT NULL',
        'link VARCHAR(255) NULL',
        'is_read TINYINT(1) NOT NULL DEFAULT 0',
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'KEY idx_notifications_user_read (user_id, is_read)',
    ], [
        'CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE',
    ]);

    // ---- Saved delivery addresses ------------------------------------------------------
    bq_create_table($conn, 'user_addresses', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        "user_id $uid NOT NULL",
        "label VARCHAR(50) NOT NULL DEFAULT 'Home'",
        'recipient_name VARCHAR(100) NOT NULL',
        'contact_number VARCHAR(30) NOT NULL',
        'street_address VARCHAR(255) NOT NULL',
        'barangay VARCHAR(100) NOT NULL',
        'city VARCHAR(100) NOT NULL',
        'province VARCHAR(100) NOT NULL',
        'postal_code VARCHAR(12) NULL',
        'is_default TINYINT(1) NOT NULL DEFAULT 0',
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'KEY idx_addresses_user (user_id)',
    ], [
        'CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE',
    ]);

    // ---- Cart --------------------------------------------------------------------------
    bq_create_table($conn, 'cart_items', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        "user_id $uid NOT NULL",
        "product_id $pid NOT NULL",
        'quantity INT UNSIGNED NOT NULL DEFAULT 1',
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'UNIQUE KEY uq_cart_user_product (user_id, product_id)',
    ], [
        'CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE',
        'CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE',
    ]);

    // ---- Orders ------------------------------------------------------------------------
    bq_create_table($conn, 'orders', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'order_number VARCHAR(30) NOT NULL',
        "user_id $uid NULL",
        'customer_name VARCHAR(100) NOT NULL',
        'contact_number VARCHAR(30) NOT NULL',
        'shipping_address VARCHAR(600) NOT NULL',
        "payment_method VARCHAR(40) NOT NULL DEFAULT 'Cash on Delivery'",
        'notes VARCHAR(500) NULL',
        'subtotal DECIMAL(12,2) NOT NULL',
        'vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.30',
        'vat_amount DECIMAL(12,2) NOT NULL',
        'total DECIMAL(12,2) NOT NULL',
        "status ENUM('pending','accepted','shipping','completed','declined','cancelled') NOT NULL DEFAULT 'pending'",
        'decline_reason VARCHAR(500) NULL',
        "handled_by $uid NULL",
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at TIMESTAMP NULL DEFAULT NULL',
        'UNIQUE KEY uq_orders_number (order_number)',
        'KEY idx_orders_user (user_id)',
        'KEY idx_orders_status (status)',
    ], [
        'CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL',
        'CONSTRAINT fk_orders_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL',
    ]);

    bq_create_table($conn, 'order_items', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'order_id INT UNSIGNED NOT NULL',
        "product_id $pid NULL",
        'product_code VARCHAR(50) NOT NULL',
        'product_name VARCHAR(150) NOT NULL',
        'unit_price DECIMAL(10,2) NOT NULL',
        'quantity INT UNSIGNED NOT NULL',
        'line_total DECIMAL(12,2) NOT NULL',
        'KEY idx_order_items_order (order_id)',
    ], [
        'CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE',
        'CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL',
    ]);

    // ---- Order reviews: star rating + feedback + pictures, one per completed order ----------
    bq_create_table($conn, 'order_reviews', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        'order_id INT UNSIGNED NOT NULL',
        "user_id $uid NOT NULL",
        'rating TINYINT UNSIGNED NOT NULL',
        'message TEXT NULL',
        'photos VARCHAR(1000) NULL',
        'response TEXT NULL',
        "responded_by $uid NULL",
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'responded_at TIMESTAMP NULL DEFAULT NULL',
        'UNIQUE KEY uq_review_order (order_id)',
        'KEY idx_review_user (user_id)',
    ], [
        'CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE',
        'CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE',
        'CONSTRAINT fk_review_responder FOREIGN KEY (responded_by) REFERENCES users (id) ON DELETE SET NULL',
    ]);

    // ---- Roles added by the Admin (Add a staff member): name + its own password ---------------
    bq_create_table($conn, 'custom_roles', [
        'role_key VARCHAR(30) NOT NULL PRIMARY KEY',
        'label VARCHAR(40) NOT NULL',
        'password_hash VARCHAR(255) NOT NULL',
        'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'UNIQUE KEY uq_custom_roles_label (label)',
    ]);

    // ---- Login log: when every account signed in and out ----------------------------------
    bq_create_table($conn, 'login_logs', [
        'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        "user_id $uid NULL",
        'full_name VARCHAR(100) NOT NULL',
        'email VARCHAR(254) NOT NULL',
        "role VARCHAR(10) NOT NULL DEFAULT 'user'",
        'staff_role VARCHAR(30) NULL',
        'ip_address VARCHAR(45) NULL',
        'login_at DATETIME NOT NULL',
        'last_seen DATETIME NOT NULL',
        'logout_at DATETIME NULL DEFAULT NULL',
        'end_reason VARCHAR(20) NULL',
        'KEY idx_login_logs_user (user_id)',
        'KEY idx_login_logs_time (login_at)',
    ], [
        'CONSTRAINT fk_login_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL',
    ]);
}
?>
