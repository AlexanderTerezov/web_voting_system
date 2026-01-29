<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username']);
$password = $_POST['password'];

require_once __DIR__ . '/db.php';
$pdo = getDb();

$stmt = $pdo->prepare('SELECT username, email, password, role FROM users WHERE username = :username LIMIT 1');
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = date('Y-m-d H:i:s');
    header('Location: dashboard.php');
    exit();
}

header('Location: index.php?error=Невалидни данни за вход');
exit();
?>
