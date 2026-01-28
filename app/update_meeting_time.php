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
$action = trim($_POST['action_type'] ?? '');
$extendMinutes = intval($_POST['extend_minutes'] ?? 0);

if ($meeting_id === '' || $action === '') {
    header('Location: dashboard.php?error=Invalid request');
    exit();
}

$meetings_file = '../db/meetings.json';
if (!file_exists($meetings_file)) {
    header('Location: dashboard.php?error=Meetings file not found');
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
    header('Location: dashboard.php?error=Meeting not found');
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
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Access denied');
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

if ($action === 'end_early') {
    if (!($now >= $meetingStart && $now <= $meetingEnd)) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Meeting is not active');
        exit();
    }
    $meetings[$meetingIndex]['ended_at'] = $now->format('Y-m-d H:i:s');
} elseif ($action === 'extend') {
    if ($extendMinutes < 1 || $extendMinutes > 240) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Invalid extension');
        exit();
    }
    if ($now > $meetingEnd) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Meeting has already ended');
        exit();
    }
    $meetings[$meetingIndex]['duration'] = $duration + $extendMinutes;
} elseif ($action === 'start_early') {
    $scheduledStart = new DateTime(($meeting['date'] ?? '') . ' ' . ($meeting['time'] ?? '00:00'));
    if ($now >= $scheduledStart) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Meeting already started');
        exit();
    }
    $meetings[$meetingIndex]['started_at'] = $now->format('Y-m-d H:i:s');
} else {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Invalid action');
    exit();
}

file_put_contents($meetings_file, json_encode($meetings, JSON_PRETTY_PRINT), LOCK_EX);

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Meeting time updated');
exit();
?>
