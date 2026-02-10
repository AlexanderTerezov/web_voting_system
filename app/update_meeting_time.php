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

$action = $action === 'start_early' ? 'start_now' : $action;


require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare('SELECT m.*, a.quorum, a.quorum_type, a.quorum_percent FROM meetings m JOIN agencies a ON a.id = m.agency_id WHERE m.id = :id');
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

$quorum = isset($meeting['quorum']) ? (int)$meeting['quorum'] : 0;
$quorumType = $meeting['quorum_type'] ?? 'count';
$quorumType = $quorumType === 'percent' ? 'percent' : 'count';
$quorumPercent = isset($meeting['quorum_percent']) ? (int)$meeting['quorum_percent'] : 0;
$participantsCountStmt = $pdo->prepare('SELECT COUNT(*) FROM agency_participants WHERE agency_id = :agency_id');
$participantsCountStmt->execute([':agency_id' => $meeting['agency_id']]);
$totalParticipants = (int)$participantsCountStmt->fetchColumn();
$requiredQuorum = $quorum;
if ($quorumType === 'percent' && $quorumPercent > 0) {
    $requiredQuorum = (int)ceil(($totalParticipants * $quorumPercent) / 100);
}

$duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
$scheduledStart = new DateTime(($meeting['date'] ?? '') . ' ' . ($meeting['time'] ?? '00:00'));
$meetingStart = $scheduledStart;
if (!empty($meeting['started_at'])) {
    $meetingStart = new DateTime($meeting['started_at']);
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
$meetingStarted = !empty($meeting['started_at']);

$attendanceCount = 0;
if ($action === 'start_now') {
    $attendanceStmt = $pdo->prepare('SELECT COUNT(*) FROM meeting_attendance WHERE meeting_id = :meeting_id AND present = 1');
    $attendanceStmt->execute([':meeting_id' => $meeting_id]);
    $attendanceCount = (int)$attendanceStmt->fetchColumn();
}


if ($action === 'end_early') {
    if (!$meetingStarted || !($now >= $meetingStart && $now <= $meetingEnd)) {
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
    if (!$meetingStarted || $now > $meetingEnd) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието вече е приключило');
        exit();
    }
    $updateStmt = $pdo->prepare('UPDATE meetings SET duration = :duration WHERE id = :id');
    $updateStmt->execute([
        ':duration' => $duration + $extendMinutes,
        ':id' => $meeting_id
    ]);
} elseif ($action === 'start_now') {
    if ($meetingStarted) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието вече е започнало');
        exit();
    }
    if ($requiredQuorum > 0 && $attendanceCount < $requiredQuorum) {
        header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Няма кворум');
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
