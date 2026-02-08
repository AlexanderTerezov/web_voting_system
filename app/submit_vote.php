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
$vote_for = trim($_POST['vote_for'] ?? '');
$statement = trim($_POST['statement'] ?? '');

$allowedVotes = ['yes', 'no', 'abstain'];
if ($meeting_id === '' || $question_id === '' || $vote_for === '' || !in_array($vote, $allowedVotes, true)) {
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

$questionStmt = $pdo->prepare('SELECT id, status FROM questions WHERE id = :question_id AND meeting_id = :meeting_id');
$questionStmt->execute([
    ':question_id' => $question_id,
    ':meeting_id' => $meeting_id
]);
$question = $questionStmt->fetch();
if (!$question) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Точката не е намерена');
    exit();
}

if (($question['status'] ?? 'future') !== 'current') {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=?????????????????????? ?? ?????????????????? ???????? ???? ?????????????????? ??????????');
    exit();
}

// Access check (admin or secretary)
$canRecordVotes = false;
if ($_SESSION['role'] === 'Admin') {
    $canRecordVotes = true;
} else {
    $participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
    $participantStmt->execute([
        ':agency_id' => $meeting['agency_id'],
        ':username' => $_SESSION['user']
    ]);
    $participant = $participantStmt->fetch();
    if ($participant && hasRole($participant['role'], 'secretary')) {
        $canRecordVotes = true;
    }
}

if (!$canRecordVotes) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=???? ???????? ???? ?? ??????? ???????');
    exit();
}

$targetStmt = $pdo->prepare('SELECT 1 FROM agency_participants WHERE agency_id = :agency_id AND username = :username');
$targetStmt->execute([
    ':agency_id' => $meeting['agency_id'],
    ':username' => $vote_for
]);
if (!$targetStmt->fetchColumn()) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=????????? ???????? ?? ?????????');
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

$statementValue = $statement !== '' ? $statement : null;

$insertVote = $pdo->prepare(
    'INSERT INTO votes (question_id, username, vote, statement, created_at)
     VALUES (:question_id, :username, :vote, :statement, :created_at)
     ON DUPLICATE KEY UPDATE vote = VALUES(vote), statement = VALUES(statement), created_at = VALUES(created_at)'
);
$insertVote->execute([
    ':question_id' => $question_id,
    ':username' => $vote_for,
    ':vote' => $vote,
    ':statement' => $statementValue,
    ':created_at' => date('Y-m-d H:i:s')
]);

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Гласът е записан');
exit();
?>
