<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$agency_id = intval($_POST['agency_id']);
$meeting_name = trim($_POST['meeting_name']);
$meeting_reason = trim($_POST['meeting_reason'] ?? '');
$meeting_date = $_POST['meeting_date'];
$meeting_time = $_POST['meeting_time'];
$duration = intval($_POST['duration']);
$recurring = $_POST['recurring'];

// Validate inputs
if (empty($meeting_name) || empty($meeting_reason) || empty($meeting_date) || empty($meeting_time) || empty($recurring) || $duration < 1) {
    header('Location: create_meeting.php?agency_id=' . $agency_id . '&error=Всички полета са задължителни');
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

$agencyStmt = $pdo->prepare('SELECT id FROM agencies WHERE id = :id');
$agencyStmt->execute([':id' => $agency_id]);
if (!$agencyStmt->fetchColumn()) {
    header('Location: dashboard.php?error=Органът не е намерен');
    exit();
}

$participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
$participantStmt->execute([
    ':agency_id' => $agency_id,
    ':username' => $_SESSION['user']
]);
$participant = $participantStmt->fetch();
if (!$participant || $participant['role'] !== 'secretary') {
    header('Location: dashboard.php?error=Само секретари могат да създават заседания');
    exit();
}

// Generate unique ID
$meeting_id = uniqid('meeting_', true);
$series_id = $meeting_id;

$insertMeeting = $pdo->prepare(
    'INSERT INTO meetings (id, series_id, name, reason, comments, agency_id, date, time, duration, recurring, created_by, created_at)
     VALUES (:id, :series_id, :name, :reason, :comments, :agency_id, :date, :time, :duration, :recurring, :created_by, :created_at)'
);
$insertMeeting->execute([
    ':id' => $meeting_id,
    ':series_id' => $series_id,
    ':name' => $meeting_name,
    ':reason' => $meeting_reason,
    ':comments' => '',
    ':agency_id' => $agency_id,
    ':date' => $meeting_date,
    ':time' => $meeting_time,
    ':duration' => $duration,
    ':recurring' => $recurring,
    ':created_by' => $_SESSION['user'],
    ':created_at' => date('Y-m-d H:i:s')
]);

header('Location: dashboard.php?success=Заседанието е създадено успешно');
exit();
?>
