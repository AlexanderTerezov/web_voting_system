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

require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare('SELECT * FROM meetings WHERE id = :id');
$meetingStmt->execute([':id' => $meeting_id]);
$meeting = $meetingStmt->fetch();

if ($meeting === null) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

$questionStmt = $pdo->prepare('SELECT id FROM questions WHERE id = :question_id AND meeting_id = :meeting_id');
$questionStmt->execute([
    ':question_id' => $question_id,
    ':meeting_id' => $meeting_id
]);
if (!$questionStmt->fetchColumn()) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Точката не е намерена');
    exit();
}

// Access check (admin or participant)
$hasAccess = false;
if ($_SESSION['role'] === 'Admin') {
    $hasAccess = true;
} else {
    $participantStmt = $pdo->prepare('SELECT 1 FROM agency_participants WHERE agency_id = :agency_id AND username = :username');
    $participantStmt->execute([
        ':agency_id' => $meeting['agency_id'],
        ':username' => $_SESSION['user']
    ]);
    $hasAccess = (bool)$participantStmt->fetchColumn();
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

$insertVote = $pdo->prepare(
    'INSERT INTO votes (question_id, username, vote, created_at)
     VALUES (:question_id, :username, :vote, :created_at)
     ON DUPLICATE KEY UPDATE vote = VALUES(vote), created_at = VALUES(created_at)'
);
$insertVote->execute([
    ':question_id' => $question_id,
    ':username' => $_SESSION['user'],
    ':vote' => $vote,
    ':created_at' => date('Y-m-d H:i:s')
]);

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Гласът е записан');
exit();
?>
