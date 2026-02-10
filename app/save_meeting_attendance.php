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
$attendeesInput = $_POST['attendees'] ?? [];

if ($meeting_id === '') {
    header('Location: dashboard.php?error=Невалидна заявка');
    exit();
}

if (!is_array($attendeesInput)) {
    $attendeesInput = [];
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare('SELECT * FROM meetings WHERE id = :id');
$meetingStmt->execute([':id' => $meeting_id]);
$meeting = $meetingStmt->fetch();

if (!$meeting) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

if (!empty($meeting['started_at'])) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието вече е започнало');
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

$participantsStmt = $pdo->prepare('SELECT username FROM agency_participants WHERE agency_id = :agency_id');
$participantsStmt->execute([':agency_id' => $meeting['agency_id']]);
$participants = $participantsStmt->fetchAll(PDO::FETCH_COLUMN);
$allowed = [];
foreach ($participants as $username) {
    if ($username !== null && $username !== '') {
        $allowed[$username] = true;
    }
}

$present = [];
foreach ($attendeesInput as $username) {
    $username = trim((string)$username);
    if ($username === '') {
        continue;
    }
    if (!isset($allowed[$username])) {
        continue;
    }
    $present[$username] = true;
}

$pdo->beginTransaction();
$clearStmt = $pdo->prepare('DELETE FROM meeting_attendance WHERE meeting_id = :meeting_id');
$clearStmt->execute([':meeting_id' => $meeting_id]);

if (!empty($present)) {
    $insertStmt = $pdo->prepare(
        'INSERT INTO meeting_attendance (meeting_id, username, present, recorded_by, recorded_at)
         VALUES (:meeting_id, :username, :present, :recorded_by, :recorded_at)'
    );
    $now = date('Y-m-d H:i:s');
    foreach (array_keys($present) as $username) {
        $insertStmt->execute([
            ':meeting_id' => $meeting_id,
            ':username' => $username,
            ':present' => 1,
            ':recorded_by' => $_SESSION['user'],
            ':recorded_at' => $now
        ]);
    }
}
$pdo->commit();

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Проверката за кворум е запазена');
exit();
?>
