<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username']);
$password = $_POST['password'];

$users_file = '../db/users.json';
if (!file_exists($users_file)) {
    header('Location: index.php?error=Invalid credentials');
    exit();
}

$users = json_decode(file_get_contents($users_file), true);

foreach ($users as $user) {
    if ($user['username'] === $username) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            
            header('Location: dashboard.php');
            exit();
        } else {
            header('Location: index.php?error=Invalid credentials');
            exit();
        }
    }
}

header('Location: index.php?error=Invalid credentials');
exit();
?>