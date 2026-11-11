<?php
// BuildQuin database connection.
// Every page includes this file to get the $conn object. It also makes sure the tables exist.
// The database name stays "product_catalog" (same as your XAMPP database).

// ---- Settings ----
date_default_timezone_set('Asia/Manila');

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "product_catalog";

// ---- Connect ----
// Make MySQL errors throw exceptions so we can catch them below.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    exit('Cannot connect to the "product_catalog" database. Start MySQL in XAMPP and import db/setup.sql in phpMyAdmin.');
}

// ---- Connection settings ----
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");

// ---- Create missing tables/columns ----
require_once __DIR__ . '/schema.php';
bq_ensure_schema($conn);
?>
