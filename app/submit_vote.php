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
$question_id = trim($_POST['question_id'] ?? '');
$vote = trim($_POST['vote'] ?? '');

$allowedVotes = ['yes', 'no', 'abstain'];
if ($meeting_id === '' || $question_id === '' || !in_array($vote, $allowedVotes, true)) {
    header('Location: dashboard.php?error=Невалиден вот');
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

// Access check (admin or participant)
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

$hasAccess = false;
if ($_SESSION['role'] === 'Admin') {
    $hasAccess = true;
} else {
    foreach ($agencies as $agency) {
        if (($agency['name'] ?? '') === ($meeting['agency_name'] ?? '')) {
            foreach ($agency['participants'] as $participant) {
                if (($participant['username'] ?? '') === $_SESSION['user']) {
                    $hasAccess = true;
                    break 2;
                }
            }
        }
    }
}

if (!$hasAccess) {
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

if ($now < $meetingStart || $now > $meetingEnd) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Гласуването е позволено само по време на заседанието');
    exit();
}

if (!isset($meetings[$meetingIndex]['questions']) || !is_array($meetings[$meetingIndex]['questions'])) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Няма намерени точки');
    exit();
}

$questionFound = false;
foreach ($meetings[$meetingIndex]['questions'] as $qIndex => $question) {
    if (($question['id'] ?? '') === $question_id) {
        $questionFound = true;
        if (!isset($meetings[$meetingIndex]['questions'][$qIndex]['votes']) || !is_array($meetings[$meetingIndex]['questions'][$qIndex]['votes'])) {
            $meetings[$meetingIndex]['questions'][$qIndex]['votes'] = [];
        }
        $meetings[$meetingIndex]['questions'][$qIndex]['votes'][$_SESSION['user']] = $vote;
        break;
    }
}

if (!$questionFound) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Точката не е намерена');
    exit();
}

file_put_contents($meetings_file, json_encode($meetings, JSON_PRETTY_PRINT), LOCK_EX);

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Гласът е записан');
exit();
?>
