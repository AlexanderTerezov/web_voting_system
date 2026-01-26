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
    header('Location: register.php?error=Username must be at least 3 characters');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=Invalid email address');
    exit();
}

if (strlen($password) < 6) {
    header('Location: register.php?error=Password must be at least 6 characters');
    exit();
}

if ($password !== $confirm_password) {
    header('Location: register.php?error=Passwords do not match');
    exit();
}

// Load existing users
$users_file = '../db/users.json';
$users = [];
if (file_exists($users_file)) {
    $users = json_decode(file_get_contents($users_file), true);
}

// Check if username already exists
foreach ($users as $user) {
    if ($user['username'] === $username) {
        header('Location: register.php?error=Username already exists');
        exit();
    }
    if ($user['email'] === $email) {
        header('Location: register.php?error=Email already registered');
        exit();
    }
}

// Add new user (always as regular user, not admin)
$users[] = [
    'username' => $username,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'User',
    'created_at' => date('Y-m-d H:i:s')
];

// Save to file
file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));

// Redirect to login
header('Location: index.php?registered=1');
exit();
?>
