<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$agency_index = intval($_POST['agency_index']);
$agency_name = $_POST['agency_name'];
$meeting_name = trim($_POST['meeting_name']);
$meeting_date = $_POST['meeting_date'];
$meeting_time = $_POST['meeting_time'];
$duration = intval($_POST['duration']);
$recurring = $_POST['recurring'];

// Validate inputs
if (empty($meeting_name) || empty($meeting_date) || empty($meeting_time) || empty($recurring) || $duration < 1) {
    header('Location: create_meeting.php?agency_index=' . $agency_index . '&error=All fields are required');
    exit();
}

// Load existing meetings
$meetings_file = '../db/meetings.json';
$meetings = [];
if (file_exists($meetings_file)) {
    $meetings = json_decode(file_get_contents($meetings_file), true);
}

// Generate unique ID
$meeting_id = uniqid('meeting_', true);

// Create new meeting
$meetings[] = [
    'id' => $meeting_id,
    'name' => $meeting_name,
    'agency_name' => $agency_name,
    'agency_index' => $agency_index,
    'date' => $meeting_date,
    'time' => $meeting_time,
    'duration' => $duration,
    'recurring' => $recurring,
    'created_by' => $_SESSION['user'],
    'created_at' => date('Y-m-d H:i:s')
];

// Save to file
file_put_contents($meetings_file, json_encode($meetings, JSON_PRETTY_PRINT));

header('Location: dashboard.php?success=Meeting created successfully');
exit();
?>