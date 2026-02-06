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
$participantsInput = $_POST['participants'] ?? [];


if (empty($agency_name) || $quorum < 1 || empty($participantsInput)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=Всички полета са задължителни');
    exit();
}

if (!is_array($participantsInput)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=Невалидни данни за участници');
    exit();
}

require_once __DIR__ . '/db.php';

$participantsList = [];
$invalidEmails = [];
foreach ($participantsInput as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $email = trim($entry['email'] ?? '');
    if ($email === '') {
        continue;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $invalidEmails[] = $email;
        continue;
    }
    $roles = normalizeRoles($entry['roles'] ?? []);
    $emailKey = strtolower($email);
    if (!isset($participantsList[$emailKey])) {
        $participantsList[$emailKey] = [
            'email' => $email,
            'roles' => []
        ];
    }
    foreach ($roles as $role) {
        $participantsList[$emailKey]['roles'][$role] = true;
    }
}

if (!empty($invalidEmails)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=' . urlencode('Невалиден имейл: ' . implode(', ', $invalidEmails)));
    exit();
}

if (empty($participantsList)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=Нужен е поне един участник');
    exit();
}

$pdo = getDb();

$emailValues = array_values(array_column($participantsList, 'email'));
$placeholders = implode(',', array_fill(0, count($emailValues), '?'));
$usersStmt = $pdo->prepare('SELECT username, email FROM users WHERE email IN (' . $placeholders . ')');
$usersStmt->execute($emailValues);
$usersRows = $usersStmt->fetchAll();
$usersByEmail = [];
foreach ($usersRows as $row) {
    $usersByEmail[strtolower($row['email'])] = $row['username'];
}

$missingEmails = [];
$finalParticipants = [];
foreach ($participantsList as $emailKey => $participant) {
    if (!isset($usersByEmail[$emailKey])) {
        $missingEmails[] = $participant['email'];
        continue;
    }
    $finalParticipants[] = [
        'username' => $usersByEmail[$emailKey],
        'role' => serializeRoles(array_keys($participant['roles']))
    ];
}

if (!empty($missingEmails)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=' . urlencode('Няма потребител с имейл: ' . implode(', ', $missingEmails)));
    exit();
}

if (empty($finalParticipants)) {
    header('Location: edit_agency.php?id=' . $agency_id . '&error=Нужен е поне един участник');
    exit();
}

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
foreach ($finalParticipants as $participant) {
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
