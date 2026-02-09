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

if ($meeting_id === '') {
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

$currentStmt = $pdo->prepare(
    'SELECT id FROM questions WHERE meeting_id = :meeting_id AND status = :status ORDER BY created_at ASC LIMIT 1'
);
$currentStmt->execute([
    ':meeting_id' => $meeting_id,
    ':status' => 'current'
]);
$currentQuestion = $currentStmt->fetch();

if (!$currentQuestion) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Няма избрана сегашна точка');
    exit();
}

$message = 'Сегашната точка е приключена';

$pdo->beginTransaction();
try {
    $closeStmt = $pdo->prepare('UPDATE questions SET status = :past WHERE meeting_id = :meeting_id AND status = :status');
    $closeStmt->execute([
        ':past' => 'past',
        ':meeting_id' => $meeting_id,
        ':status' => 'current'
    ]);

    $nextStmt = $pdo->prepare(
        'SELECT id FROM questions WHERE meeting_id = :meeting_id AND status = :status ORDER BY sort_order ASC, created_at ASC, id ASC LIMIT 1'
    );
    $nextStmt->execute([
        ':meeting_id' => $meeting_id,
        ':status' => 'future'
    ]);
    $nextQuestion = $nextStmt->fetch();

    if ($nextQuestion) {
        $setStmt = $pdo->prepare('UPDATE questions SET status = :status WHERE id = :question_id');
        $setStmt->execute([
            ':status' => 'current',
            ':question_id' => $nextQuestion['id']
        ]);
        $message = 'Преминахме към следваща точка';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Неуспешно приключване на точката');
    exit();
}

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=' . urlencode($message));
exit();
?>
