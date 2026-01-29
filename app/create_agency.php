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

$agency_name = trim($_POST['agency_name']);
$quorum = intval($_POST['quorum']);
$participants = $_POST['participants'];
$roles = $_POST['roles'];

if (empty($agency_name) || $quorum < 1 || empty($participants)) {
    header('Location: dashboard.php?error=Всички полета са задължителни');
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
    header('Location: dashboard.php?error=Нужен е поне един участник');
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

$pdo->beginTransaction();
$insertAgency = $pdo->prepare('INSERT INTO agencies (name, quorum, created_at, created_by) VALUES (:name, :quorum, :created_at, :created_by)');
$insertAgency->execute([
    ':name' => $agency_name,
    ':quorum' => $quorum,
    ':created_at' => date('Y-m-d H:i:s'),
    ':created_by' => $_SESSION['user']
]);
$agencyId = (int)$pdo->lastInsertId();

$insertParticipant = $pdo->prepare('INSERT INTO agency_participants (agency_id, username, role) VALUES (:agency_id, :username, :role)');
foreach ($participantsList as $participant) {
    $insertParticipant->execute([
        ':agency_id' => $agencyId,
        ':username' => $participant['username'],
        ':role' => $participant['role']
    ]);
}
$pdo->commit();

header('Location: dashboard.php?success=Органът е създаден успешно');
exit();
?>
