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
        CREATE TABLE IF NOT EXISTS questions (
            id VARCHAR(64) PRIMARY KEY,
            meeting_id VARCHAR(64) NOT NULL,
            text TEXT NOT NULL,
            details TEXT,
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
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_votes_question_user (question_id, username),
            KEY idx_votes_question (question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
    );

    seedAdminUser($pdo);
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
?>
