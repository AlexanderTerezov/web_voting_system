<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// Validate inputs
if (strlen($username) < 3) {
    header('Location: register.php?error=Потребителското име трябва да е поне 3 символа');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=Невалиден имейл адрес');
    exit();
}

if (strlen($password) < 6) {
    header('Location: register.php?error=Паролата трябва да е поне 6 символа');
    exit();
}

if ($password !== $confirm_password) {
    header('Location: register.php?error=Паролите не съвпадат');
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

// Check if username or email already exists
$stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
$stmt->execute([':username' => $username, ':email' => $email]);
$existing = $stmt->fetch();
if ($existing) {
    if ($existing['username'] === $username) {
        header('Location: register.php?error=Потребителското име вече съществува');
        exit();
    }
    if ($existing['email'] === $email) {
        header('Location: register.php?error=Имейлът вече е регистриран');
        exit();
    }
}

// Add new user (always as regular user, not admin)
$insert = $pdo->prepare('INSERT INTO users (username, email, password, role, created_at) VALUES (:username, :email, :password, :role, :created_at)');
$insert->execute([
    ':username' => $username,
    ':email' => $email,
    ':password' => password_hash($password, PASSWORD_DEFAULT),
    ':role' => 'User',
    ':created_at' => date('Y-m-d H:i:s')
]);

// Redirect to login
header('Location: index.php?registered=1');
exit();
?>
