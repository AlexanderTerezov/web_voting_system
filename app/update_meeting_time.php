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
    if ($participant && $participant['role'] === 'secretary') {
        $canManage = true;
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

if ($action === 'end_early') {
    if (!($now >= $meetingStart && $now <= $meetingEnd)) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието не е активно');
        exit();
    }
    $updateStmt = $pdo->prepare('UPDATE meetings SET ended_at = :ended_at WHERE id = :id');
    $updateStmt->execute([
        ':ended_at' => $now->format('Y-m-d H:i:s'),
        ':id' => $meeting_id
    ]);
} elseif ($action === 'extend') {
    if ($extendMinutes < 1 || $extendMinutes > 240) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Невалидно удължаване');
        exit();
    }
    if ($now > $meetingEnd) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието вече е приключило');
        exit();
    }
    $updateStmt = $pdo->prepare('UPDATE meetings SET duration = :duration WHERE id = :id');
    $updateStmt->execute([
        ':duration' => $duration + $extendMinutes,
        ':id' => $meeting_id
    ]);
} elseif ($action === 'start_early') {
    $scheduledStart = new DateTime(($meeting['date'] ?? '') . ' ' . ($meeting['time'] ?? '00:00'));
    if ($now >= $scheduledStart) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието вече е започнало');
        exit();
    }
    $updateStmt = $pdo->prepare('UPDATE meetings SET started_at = :started_at WHERE id = :id');
    $updateStmt->execute([
        ':started_at' => $now->format('Y-m-d H:i:s'),
        ':id' => $meeting_id
    ]);
} else {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Невалидно действие');
    exit();
}

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Времето на заседанието е обновено');
exit();
?>
