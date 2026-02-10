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
$participantsInput = $_POST['participants'] ?? [];

if (empty($agency_name) || $quorum < 1 || empty($participantsInput)) {
    header('Location: dashboard.php?error=Всички полета са задължителни');
    exit();
}

if (!is_array($participantsInput)) {
    header('Location: dashboard.php?error=Невалидни данни за участници');
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
    header('Location: dashboard.php?error=' . urlencode('Невалиден имейл: ' . implode(', ', $invalidEmails)));
    exit();
}

if (empty($participantsList)) {
    header('Location: dashboard.php?error=Нужен е поне един участник');
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
    header('Location: dashboard.php?error=' . urlencode('Няма потребител с имейл: ' . implode(', ', $missingEmails)));
    exit();
}

if (empty($finalParticipants)) {
    header('Location: dashboard.php?error=Нужен е поне един участник');
    exit();
}

$pdo->beginTransaction();
$insertAgency = $pdo->prepare('INSERT INTO agencies (name, quorum, default_questions, created_at, created_by) VALUES (:name, :quorum, :default_questions, :created_at, :created_by)');
$insertAgency->execute([
    ':name' => $agency_name,
    ':quorum' => $quorum,
    ':default_questions' => $default_questions,
    ':created_at' => date('Y-m-d H:i:s'),
    ':created_by' => $_SESSION['user']
]);

$default_questions = trim($_POST['default_questions'] ?? '');
$agencyId = (int)$pdo->lastInsertId();

$insertParticipant = $pdo->prepare('INSERT INTO agency_participants (agency_id, username, role) VALUES (:agency_id, :username, :role)');
foreach ($finalParticipants as $participant) {
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
