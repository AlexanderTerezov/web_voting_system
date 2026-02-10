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

$agency_name = trim($_POST['agency_name'] ?? '');
$quorumType = $_POST['quorum_type'] ?? 'count';
$quorumCount = isset($_POST['quorum_count']) ? intval($_POST['quorum_count']) : 0;
$quorumPercent = isset($_POST['quorum_percent']) ? intval($_POST['quorum_percent']) : 0;
$participantsBulk = trim($_POST['participants_bulk'] ?? '');

if (empty($agency_name) || $participantsBulk === '') {
    header('Location: dashboard.php?error=Всички полета са задължителни');
    exit();
}

$quorumType = $quorumType === 'percent' ? 'percent' : 'count';
if ($quorumType === 'percent') {
    if ($quorumPercent < 1 || $quorumPercent > 100) {
        header('Location: dashboard.php?error=' . urlencode('Невалиден кворум'));
        exit();
    }
    $quorum = 0;
} else {
    if ($quorumCount < 1) {
        header('Location: dashboard.php?error=' . urlencode('Невалиден кворум'));
        exit();
    }
    $quorum = $quorumCount;
    $quorumPercent = null;
}

require_once __DIR__ . '/db.php';

$participantsList = [];
$invalidEmails = [];
$lines = preg_split('/\R/', $participantsBulk);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $parts = array_map('trim', explode(',', $line));
    $email = $parts[0] ?? '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $invalidEmails[] = $email !== '' ? $email : $line;
        continue;
    }
    $roles = [];
    for ($i = 1; $i < count($parts); $i++) {
        $token = strtolower(trim($parts[$i]));
        if ($token === 'u') {
            $roles['member'] = true;
        } elseif ($token === 's') {
            $roles['secretary'] = true;
        }
    }
    if (empty($roles)) {
        $roles['member'] = true;
    }
    $emailKey = strtolower($email);
    if (!isset($participantsList[$emailKey])) {
        $participantsList[$emailKey] = [
            'email' => $email,
            'roles' => []
        ];
    }
    foreach (array_keys($roles) as $role) {
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


$default_questions = trim($_POST['default_questions'] ?? '');

$pdo->beginTransaction();
$insertAgency = $pdo->prepare('INSERT INTO agencies (name, quorum, quorum_type, quorum_percent, default_questions, created_at, created_by) VALUES (:name, :quorum, :quorum_type, :quorum_percent, :default_questions, :created_at, :created_by)');
$insertAgency->execute([
    ':name' => $agency_name,
    ':quorum' => $quorum,
    ':quorum_type' => $quorumType,
    ':quorum_percent' => $quorumPercent,
    ':default_questions' => $default_questions,
    ':created_at' => date('Y-m-d H:i:s'),
    ':created_by' => $_SESSION['user']
]);

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
