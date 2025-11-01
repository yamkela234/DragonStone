<?php
require_once __DIR__ . '/helpers.php';

// admin/_guard.php
session_start();

// --- Database connection
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "dragonstone.db"; // make sure your DB name matches

$mysqli = @new mysqli($servername, $username, $password, $dbname);

if ($mysqli->connect_errno) {
    error_log("❌ DB Connection failed: " . $mysqli->connect_error);
}

// --- Helper for safe counts
function safeCount(mysqli $db = null, string $sql = ''): int {
    if (!$db || !$sql) return 0;
    try {
        $res = $db->query($sql);
        if ($res) {
            $row = $res->fetch_row();
            return (int)($row[0] ?? 0);
        }
    } catch (Throwable $e) {
        error_log("safeCount error: " . $e->getMessage());
    }
    return 0;
}

// --- Session role check (simplified for dev)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['name'] = "Admin User";
    $_SESSION['role'] = "admin";
    $_SESSION['is_admin'] = 1;
}
