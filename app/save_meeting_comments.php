<?php
session_start();

date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$meeting_id = trim($_POST['meeting_id'] ?? '');
$meeting_comments = trim($_POST['meeting_comments'] ?? '');

if ($meeting_id === '') {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

$meetings_file = '../db/meetings.json';
if (!file_exists($meetings_file)) {
    header('Location: dashboard.php?error=Файлът със заседания не е намерен');
    exit();
}

$meetings = json_decode(file_get_contents($meetings_file), true);
$meetingIndex = null;
$meeting = null;
foreach ($meetings as $index => $m) {
    if (($m['id'] ?? '') === $meeting_id) {
        $meetingIndex = $index;
        $meeting = $m;
        break;
    }
}

if ($meeting === null) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

// Access check (admin or secretary)
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

$canManage = false;
if ($_SESSION['role'] === 'Admin') {
    $canManage = true;
} else {
    foreach ($agencies as $agency) {
        if (($agency['name'] ?? '') === ($meeting['agency_name'] ?? '')) {
            foreach ($agency['participants'] as $participant) {
                if (($participant['username'] ?? '') === $_SESSION['user'] && ($participant['role'] ?? '') === 'secretary') {
                    $canManage = true;
                    break 2;
                }
            }
        }
    }
}

if (!$canManage) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Нямате достъп');
    exit();
}

$duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
$meetingStart = new DateTime(($meeting['date'] ?? '') . ' ' . ($meeting['time'] ?? '00:00'));
if (!empty($meeting['started_at'])) {
    $overrideStart = new DateTime($meeting['started_at']);
    if ($overrideStart < $meetingStart) {
        $meetingStart = $overrideStart;
    }
}
$meetingEnd = clone $meetingStart;
$meetingEnd->modify("+{$duration} minutes");
if (!empty($meeting['ended_at'])) {
    $overrideEnd = new DateTime($meeting['ended_at']);
    if ($overrideEnd < $meetingEnd) {
        $meetingEnd = $overrideEnd;
    }
}
$now = new DateTime();

if ($now <= $meetingEnd) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието още не е приключило');
    exit();
}

$meetings[$meetingIndex]['comments'] = $meeting_comments;

file_put_contents($meetings_file, json_encode($meetings, JSON_PRETTY_PRINT), LOCK_EX);

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Коментарите са запазени');
exit();
?>
