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

if ($meeting_id === '' || $question_id === '') {
    header('Location: dashboard.php?error=Невалидна заявка');
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

// Access check (admin or secretary)
$canManage = false;
if ($_SESSION['role'] === 'Admin') {
    $canManage = true;
} else {
    $participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
    $participantStmt->execute([
        ':agency_id' => $meeting['agency_id'],
        ':username' => $_SESSION['user']
    ]);
    $participant = $participantStmt->fetch();
    if ($participant && hasRole($participant['role'], 'secretary')) {
        $canManage = true;
    }
}

if (!$canManage) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Нямате достъп');
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

$pdo->beginTransaction();
try {
    $clearStmt = $pdo->prepare('UPDATE questions SET status = :past WHERE meeting_id = :meeting_id AND status = :current');
    $clearStmt->execute([
        ':past' => 'past',
        ':meeting_id' => $meeting_id,
        ':current' => 'current'
    ]);

    $setStmt = $pdo->prepare('UPDATE questions SET status = :current WHERE id = :question_id');
    $setStmt->execute([
        ':current' => 'current',
        ':question_id' => $question_id
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Неуспешно задаване на сегашна точка');
    exit();
}

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Сегашната точка е обновена');
exit();
?>
