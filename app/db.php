<?php
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'web_voting_system';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            username VARCHAR(255) PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS agencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            quorum INT NOT NULL,
            quorum_type VARCHAR(10) NOT NULL DEFAULT \'count\',
            quorum_percent INT DEFAULT NULL,
            default_questions TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            created_by VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS agency_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            username VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            UNIQUE KEY uniq_agency_user (agency_id, username),
            KEY idx_agency_participants_agency (agency_id),
            KEY idx_agency_participants_user (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS meetings (
            id VARCHAR(64) PRIMARY KEY,
            series_id VARCHAR(64),
            name VARCHAR(255) NOT NULL,
            reason TEXT,
            comments TEXT,
            agency_id INT NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            duration INT NOT NULL,
            recurring VARCHAR(50) NOT NULL,
            created_by VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            KEY idx_meetings_agency (agency_id),
            KEY idx_meetings_series (series_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS meeting_attendance (
            meeting_id VARCHAR(64) NOT NULL,
            username VARCHAR(255) NOT NULL,
            present TINYINT(1) NOT NULL DEFAULT 1,
            recorded_by VARCHAR(255) NOT NULL,
            recorded_at DATETIME NOT NULL,
            PRIMARY KEY (meeting_id, username),
            KEY idx_meeting_attendance_meeting (meeting_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS questions (
            id VARCHAR(64) PRIMARY KEY,
            meeting_id VARCHAR(64) NOT NULL,
            text TEXT NOT NULL,
            details TEXT,
            status VARCHAR(20) NOT NULL DEFAULT \'future\',
            sort_order INT NOT NULL DEFAULT 0,
            created_by VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_questions_meeting (meeting_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id VARCHAR(64) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            path VARCHAR(255) NOT NULL,
            type VARCHAR(100),
            size INT,
            KEY idx_attachments_question (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question_id VARCHAR(64) NOT NULL,
            username VARCHAR(255) NOT NULL,
            vote VARCHAR(10) NOT NULL,
            statement TEXT,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_votes_question_user (question_id, username),
            KEY idx_votes_question (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
    );

    ensureQuestionStatusColumn($pdo);
    ensureQuestionSortOrderColumn($pdo);
    ensureAgencyQuorumTypeColumn($pdo);
    ensureAgencyQuorumPercentColumn($pdo);
    ensureAgencyDefaultQuestionsColumn($pdo);
    backfillAgencyQuorumType($pdo);
    backfillQuestionSortOrder($pdo);
    ensureVotesStatementColumn($pdo);
    seedAdminUser($pdo);
}
function ensureAgencyQuorumTypeColumn(PDO $pdo): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "agencies"
           AND COLUMN_NAME = "quorum_type"'
    );
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec("ALTER TABLE agencies ADD COLUMN quorum_type VARCHAR(10) NOT NULL DEFAULT 'count' AFTER quorum");
}

function ensureAgencyQuorumPercentColumn(PDO $pdo): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "agencies"
           AND COLUMN_NAME = "quorum_percent"'
    );
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec('ALTER TABLE agencies ADD COLUMN quorum_percent INT DEFAULT NULL AFTER quorum_type');
}

function ensureAgencyDefaultQuestionsColumn(PDO $pdo): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "agencies"
           AND COLUMN_NAME = "default_questions"'
    );
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec('ALTER TABLE agencies ADD COLUMN default_questions TEXT DEFAULT NULL AFTER quorum_percent');
}
function backfillAgencyQuorumType(PDO $pdo): void
{
    $pdo->exec("UPDATE agencies SET quorum_type = 'count' WHERE quorum_type IS NULL OR quorum_type = ''");
}
function seedAdminUser(PDO $pdo): void
{
    $username = getenv('SEED_ADMIN_USERNAME') ?: 'Admin';
    $email = getenv('SEED_ADMIN_EMAIL') ?: 'admin@example.com';
    $password = getenv('SEED_ADMIN_PASSWORD') ?: 'Admin123';
    $role = getenv('SEED_ADMIN_ROLE') ?: 'Admin';

    $check = $pdo->prepare('SELECT 1 FROM users WHERE username = :username');
    $check->execute([':username' => $username]);
    if ($check->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (username, email, password, role, created_at)
         VALUES (:username, :email, :password, :role, :created_at)'
    );
    $insert->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => $role,
        ':created_at' => date('Y-m-d H:i:s')
    ]);
}

function ensureQuestionStatusColumn(PDO $pdo): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "questions"
           AND COLUMN_NAME = "status"'
    );
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec("ALTER TABLE questions ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'future' AFTER details");
}

function ensureVotesStatementColumn(PDO $pdo): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "votes"
           AND COLUMN_NAME = "statement"'
    );
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec('ALTER TABLE votes ADD COLUMN statement TEXT NULL AFTER vote');
}

function ensureQuestionSortOrderColumn(PDO $pdo): void
{
    $check = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "questions"
           AND COLUMN_NAME = "sort_order"'
    );
    $check->execute();
    if ((int)$check->fetchColumn() > 0) {
        return;
    }

    $pdo->exec('ALTER TABLE questions ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER status');
}

function backfillQuestionSortOrder(PDO $pdo): void
{
    $meetingsStmt = $pdo->query('SELECT id FROM meetings');
    $meetingIds = $meetingsStmt ? $meetingsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($meetingIds)) {
        return;
    }

    $selectQuestions = $pdo->prepare(
        'SELECT id, sort_order
         FROM questions
         WHERE meeting_id = :meeting_id
         ORDER BY CASE WHEN sort_order = 0 THEN 1 ELSE 0 END, sort_order ASC, created_at ASC, id ASC'
    );
    $updateOrder = $pdo->prepare('UPDATE questions SET sort_order = :sort_order WHERE id = :id');

    foreach ($meetingIds as $meetingId) {
        $selectQuestions->execute([':meeting_id' => $meetingId]);
        $questions = $selectQuestions->fetchAll();
        if (empty($questions)) {
            continue;
        }

        $needsBackfill = false;
        foreach ($questions as $question) {
            if ((int)$question['sort_order'] === 0) {
                $needsBackfill = true;
                break;
            }
        }
        if (!$needsBackfill) {
            continue;
        }

        $order = 1;
        foreach ($questions as $question) {
            $updateOrder->execute([
                ':sort_order' => $order,
                ':id' => $question['id']
            ]);
            $order++;
        }
    }
}

function normalizeRoles($roles): array
{
    $values = [];
    if (is_string($roles)) {
        $values = explode(',', $roles);
    } elseif (is_array($roles)) {
        $values = $roles;
    }

    $allowed = ['member' => true, 'secretary' => true];
    $normalized = [];
    foreach ($values as $role) {
        $role = strtolower(trim((string)$role));
        if ($role === '') {
            continue;
        }
        if (!isset($allowed[$role])) {
            continue;
        }
        $normalized[$role] = true;
    }

    if (empty($normalized)) {
        $normalized['member'] = true;
    }

    return array_keys($normalized);
}

function serializeRoles($roles): string
{
    $normalized = normalizeRoles($roles);
    sort($normalized);
    return implode(',', $normalized);
}

function hasRole($roles, string $role): bool
{
    $role = strtolower(trim($role));
    if ($role === '') {
        return false;
    }
    return in_array($role, normalizeRoles($roles), true);
}
?>
