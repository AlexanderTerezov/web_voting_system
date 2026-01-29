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
$agency_name = trim($_POST['agency_name']);
$quorum = intval($_POST['quorum']);
$participants = $_POST['participants'];
$roles = $_POST['roles'];

if (empty($agency_name) || $quorum < 1 || empty($participants)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=Всички полета са задължителни');
    exit();
}

$participantsList = [];
foreach ($participants as $index => $username) {
    if (!empty($username)) {
        $role = isset($roles[$index]) ? strtolower(trim($roles[$index])) : 'member';
        if (!in_array($role, ['member', 'secretary'])) {
            $role = 'member';
        }
        $participantsList[] = [
            'username' => $username,
            'role' => $role
        ];
    }
}

if (empty($participantsList)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=Нужен е поне един участник');
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

$pdo->beginTransaction();
$updateAgency = $pdo->prepare('UPDATE agencies SET name = :name, quorum = :quorum WHERE id = :id');
$updateAgency->execute([
    ':name' => $agency_name,
    ':quorum' => $quorum,
    ':id' => $agency_id
]);

$pdo->prepare('DELETE FROM agency_participants WHERE agency_id = :id')->execute([':id' => $agency_id]);
$insertParticipant = $pdo->prepare('INSERT INTO agency_participants (agency_id, username, role) VALUES (:agency_id, :username, :role)');
foreach ($participantsList as $participant) {
    $insertParticipant->execute([
        ':agency_id' => $agency_id,
        ':username' => $participant['username'],
        ':role' => $participant['role']
    ]);
}
$pdo->commit();

header('Location: dashboard.php?success=Органът е обновен успешно');
exit();
?>
