<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $cellphone = $_POST['cellphone'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("INSERT INTO users (name, surname, email, password, cellphone, address) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $name, $surname, $email, $password, $cellphone, $address);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: ../login.html?signup=success");
    exit();
}
?>
