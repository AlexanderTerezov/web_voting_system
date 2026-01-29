<?php
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../db/database.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            username TEXT PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS agencies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            quorum INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            created_by TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS agency_participants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agency_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            role TEXT NOT NULL,
            UNIQUE(agency_id, username)
        );
        CREATE TABLE IF NOT EXISTS meetings (
            id TEXT PRIMARY KEY,
            series_id TEXT,
            name TEXT NOT NULL,
            reason TEXT,
            comments TEXT,
            agency_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            time TEXT NOT NULL,
            duration INTEGER NOT NULL,
            recurring TEXT NOT NULL,
            created_by TEXT NOT NULL,
            created_at TEXT NOT NULL,
            started_at TEXT,
            ended_at TEXT
        );
        CREATE TABLE IF NOT EXISTS questions (
            id TEXT PRIMARY KEY,
            meeting_id TEXT NOT NULL,
            text TEXT NOT NULL,
            details TEXT,
            created_by TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_id TEXT NOT NULL,
            original_name TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            path TEXT NOT NULL,
            type TEXT,
            size INTEGER
        );
        CREATE TABLE IF NOT EXISTS votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_id TEXT NOT NULL,
            username TEXT NOT NULL,
            vote TEXT NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(question_id, username)
        );
        CREATE INDEX IF NOT EXISTS idx_agency_participants_agency ON agency_participants(agency_id);
        CREATE INDEX IF NOT EXISTS idx_agency_participants_user ON agency_participants(username);
        CREATE INDEX IF NOT EXISTS idx_meetings_agency ON meetings(agency_id);
        CREATE INDEX IF NOT EXISTS idx_meetings_series ON meetings(series_id);
        CREATE INDEX IF NOT EXISTS idx_questions_meeting ON questions(meeting_id);
        CREATE INDEX IF NOT EXISTS idx_attachments_question ON attachments(question_id);
        CREATE INDEX IF NOT EXISTS idx_votes_question ON votes(question_id);'
    );
}
?>
