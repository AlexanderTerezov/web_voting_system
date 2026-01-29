<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$agency_id = intval($_POST['agency_id']);

require_once __DIR__ . '/db.php';
$pdo = getDb();

$agencyStmt = $pdo->prepare('SELECT id FROM agencies WHERE id = :id');
$agencyStmt->execute([':id' => $agency_id]);
if (!$agencyStmt->fetchColumn()) {
    header('Location: dashboard.php?error=Органът не е намерен');
    exit();
}

$pdo->beginTransaction();
$pdo->prepare('DELETE FROM votes WHERE question_id IN (SELECT id FROM questions WHERE meeting_id IN (SELECT id FROM meetings WHERE agency_id = :id))')
    ->execute([':id' => $agency_id]);
$pdo->prepare('DELETE FROM attachments WHERE question_id IN (SELECT id FROM questions WHERE meeting_id IN (SELECT id FROM meetings WHERE agency_id = :id))')
    ->execute([':id' => $agency_id]);
$pdo->prepare('DELETE FROM questions WHERE meeting_id IN (SELECT id FROM meetings WHERE agency_id = :id)')
    ->execute([':id' => $agency_id]);
$pdo->prepare('DELETE FROM meetings WHERE agency_id = :id')
    ->execute([':id' => $agency_id]);
$pdo->prepare('DELETE FROM agency_participants WHERE agency_id = :id')
    ->execute([':id' => $agency_id]);
$pdo->prepare('DELETE FROM agencies WHERE id = :id')
    ->execute([':id' => $agency_id]);
$pdo->commit();

header('Location: dashboard.php?success=Органът е изтрит успешно');
exit();
?>
