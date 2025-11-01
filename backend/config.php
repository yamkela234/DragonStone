<?php
// config.php
$servername = "localhost";
$username   = "root";       // default XAMPP username
$password   = "";           // default XAMPP password is blank
$dbname     = "dragonstone_db";  // your DB name in phpMyAdmin

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>
