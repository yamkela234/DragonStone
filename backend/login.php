<?php
session_start();

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "dragonstone.db"; // your schema name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // sanitize
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $passIn   = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($email === '' || $passIn === '') {
        echo "❌ Please enter both email and password.";
        exit;
    }

    // case-insensitive email match
    $sql = "SELECT id, name, surname, email, password, is_admin, role
            FROM users
            WHERE LOWER(email) = LOWER(?)
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();

        $stored = (string)$user['password'];

        // allow both hashed and plaintext
        $ok = false;
        if (strlen($stored) > 0) {
            if (password_verify($passIn, $stored)) {
                $ok = true;
            } elseif ($passIn === $stored) {
                $ok = true;
            }
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['is_admin'] = (int)$user['is_admin'];
            $_SESSION['role']     = $user['role'] ?: 'customer';
            $_SESSION['name']     = $user['name'] ?? '';

            // route by role
            $role = strtolower($_SESSION['role']);
            if ($role === 'admin' || $_SESSION['is_admin'] === 1) {
                header("Location: ../admin-dashboard.php");
            } elseif ($role === 'author') {
                // author: send to community admin area (change if your path differs)
                header("Location: ../admin/community.php");
            } else {
                header("Location: ../index.php");
            }
            exit;
        } else {
            echo "❌ Incorrect password.";
        }
    } else {
        echo "❌ No account found with that email.";
    }

    $stmt->close();
}

$conn->close();
