<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$agency_index = intval($_POST['agency_index']);

$agencies_file = '../db/agencies.json';
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
    
    if (isset($agencies[$agency_index])) {
        array_splice($agencies, $agency_index, 1);
        file_put_contents($agencies_file, json_encode($agencies, JSON_PRETTY_PRINT));
        header('Location: dashboard.php?success=Agency deleted successfully');
    } else {
        header('Location: dashboard.php?error=Agency not found');
    }
} else {
    header('Location: dashboard.php?error=No agencies found');
}
exit();
?>