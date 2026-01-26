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
$agency_name = trim($_POST['agency_name']);
$quorum = intval($_POST['quorum']);
$participants = $_POST['participants'];
$roles = $_POST['roles'];

if (empty($agency_name) || $quorum < 1 || empty($participants)) {
    header('Location: edit_agency.php?index=' . $agency_index . '&error=All fields are required');
    exit();
}

$participantsList = [];
foreach ($participants as $index => $username) {
    if (!empty($username)) {
        $role = isset($roles[$index]) ? strtolower(trim($roles[$index])) : 'member';
        if (!in_array($role, ['member', 'secretary'])) {
            $role = 'member';
        }
        $participantsList[] = [
            'username' => $username,
            'role' => $role
        ];
    }
}

if (empty($participantsList)) {
    header('Location: edit_agency.php?index=' . $agency_index . '&error=At least one participant is required');
    exit();
}

$agencies_file = '../db/agencies.json';
if (!file_exists($agencies_file)) {
    header('Location: dashboard.php?error=No agencies found');
    exit();
}

$agencies = json_decode(file_get_contents($agencies_file), true);

if (!isset($agencies[$agency_index])) {
    header('Location: dashboard.php?error=Agency not found');
    exit();
}

$agencies[$agency_index]['name'] = $agency_name;
$agencies[$agency_index]['quorum'] = $quorum;
$agencies[$agency_index]['participants'] = $participantsList;

file_put_contents($agencies_file, json_encode($agencies, JSON_PRETTY_PRINT));

header('Location: dashboard.php?success=Agency updated successfully');
exit();
?>