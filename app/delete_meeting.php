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

$meeting_id = $_POST['meeting_id'];

require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare('SELECT * FROM meetings WHERE id = :id');
$meetingStmt->execute([':id' => $meeting_id]);
$meeting = $meetingStmt->fetch();

if ($meeting === null) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

// Check if user is secretary in this meeting's agency
$participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
$participantStmt->execute([
    ':agency_id' => $meeting['agency_id'],
    ':username' => $_SESSION['user']
]);
$participant = $participantStmt->fetch();
$isSecretary = $participant && hasRole($participant['role'], 'secretary');

if (!$isSecretary) {
    header('Location: dashboard.php?error=Само секретари могат да изтриват заседания');
    exit();
}

// Delete the meeting and related data
$pdo->beginTransaction();
$pdo->prepare('DELETE FROM votes WHERE question_id IN (SELECT id FROM questions WHERE meeting_id = :meeting_id)')
    ->execute([':meeting_id' => $meeting_id]);
$pdo->prepare('DELETE FROM attachments WHERE question_id IN (SELECT id FROM questions WHERE meeting_id = :meeting_id)')
    ->execute([':meeting_id' => $meeting_id]);
$pdo->prepare('DELETE FROM questions WHERE meeting_id = :meeting_id')
    ->execute([':meeting_id' => $meeting_id]);
$pdo->prepare('DELETE FROM meetings WHERE id = :meeting_id')
    ->execute([':meeting_id' => $meeting_id]);
$pdo->commit();

header('Location: dashboard.php?success=Заседанието е изтрито успешно');
exit();
?>
