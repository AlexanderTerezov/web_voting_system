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

$meeting_id = $_POST['meeting_id'];

// Load meetings
$meetings_file = '../db/meetings.json';
if (!file_exists($meetings_file)) {
    header('Location: dashboard.php?error=Няма намерени заседания');
    exit();
}

$meetings = json_decode(file_get_contents($meetings_file), true);

// Find and verify the meeting
$meetingIndex = null;
$meeting = null;
foreach ($meetings as $index => $m) {
    if ($m['id'] === $meeting_id) {
        $meetingIndex = $index;
        $meeting = $m;
        break;
    }
}

if ($meeting === null) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

// Load agencies to verify secretary rights
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

// Check if user is secretary in this meeting's agency
$isSecretary = false;
foreach ($agencies as $agency) {
    if ($agency['name'] === $meeting['agency_name']) {
        foreach ($agency['participants'] as $participant) {
            if ($participant['username'] === $_SESSION['user'] && $participant['role'] === 'secretary') {
                $isSecretary = true;
                break 2;
            }
        }
    }
}

if (!$isSecretary) {
    header('Location: dashboard.php?error=Само секретари могат да изтриват заседания');
    exit();
}

// Delete the meeting
array_splice($meetings, $meetingIndex, 1);
file_put_contents($meetings_file, json_encode($meetings, JSON_PRETTY_PRINT));

header('Location: dashboard.php?success=Заседанието е изтрито успешно');
exit();
?>
