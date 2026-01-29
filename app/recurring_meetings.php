<?php
function ensureRecurringMeetings(PDO $pdo): void
{
    $now = new DateTime();

    $pdo->exec("UPDATE meetings SET series_id = id WHERE series_id IS NULL OR series_id = ''");

    $stmt = $pdo->query(
        'SELECT id, series_id, name, reason, agency_id, date, time, duration, recurring, created_by, created_at, started_at, ended_at
         FROM meetings
         WHERE recurring != "Once"'
    );
    $meetings = $stmt->fetchAll();

    $seriesLatest = [];
    foreach ($meetings as $meeting) {
        $seriesId = $meeting['series_id'] ?? $meeting['id'];
        if ($seriesId === null) {
            continue;
        }
        $start = new DateTime(($meeting['date'] ?? '1970-01-01') . ' ' . ($meeting['time'] ?? '00:00'));
        if (!isset($seriesLatest[$seriesId]) || $start > $seriesLatest[$seriesId]['start']) {
            $seriesLatest[$seriesId] = [
                'meeting' => $meeting,
                'start' => $start
            ];
        }
    }

    $existsStmt = $pdo->prepare('SELECT 1 FROM meetings WHERE series_id = :series_id AND date = :date AND time = :time LIMIT 1');
    $insertStmt = $pdo->prepare(
        'INSERT INTO meetings (id, series_id, name, reason, comments, agency_id, date, time, duration, recurring, created_by, created_at)
         VALUES (:id, :series_id, :name, :reason, :comments, :agency_id, :date, :time, :duration, :recurring, :created_by, :created_at)'
    );

    foreach ($seriesLatest as $seriesId => $info) {
        $latest = $info['meeting'];
        $recurring = $latest['recurring'] ?? 'Once';
        $intervalSpec = $recurring === 'Daily' ? 'P1D' : ($recurring === 'Weekly' ? 'P1W' : ($recurring === 'Monthly' ? 'P1M' : null));
        if ($intervalSpec === null) {
            continue;
        }

        $duration = isset($latest['duration']) ? (int)$latest['duration'] : 60;
        $latestStart = new DateTime(($latest['date'] ?? '1970-01-01') . ' ' . ($latest['time'] ?? '00:00'));
        $latestEnd = clone $latestStart;
        $latestEnd->modify("+{$duration} minutes");
        if (!empty($latest['ended_at'])) {
            $overrideEnd = new DateTime($latest['ended_at']);
            if ($overrideEnd < $latestEnd) {
                $latestEnd = $overrideEnd;
            }
        }

        if ($now <= $latestEnd) {
            continue;
        }

        $nextStart = clone $latestStart;
        $nextStart->add(new DateInterval($intervalSpec));

        $safety = 0;
        while ($nextStart <= $now && $safety < 30) {
            $existsStmt->execute([
                ':series_id' => $seriesId,
                ':date' => $nextStart->format('Y-m-d'),
                ':time' => $nextStart->format('H:i')
            ]);
            $exists = (bool)$existsStmt->fetchColumn();

            if (!$exists) {
                $insertStmt->execute([
                    ':id' => uniqid('meeting_', true),
                    ':series_id' => $seriesId,
                    ':name' => $latest['name'] ?? 'Заседание',
                    ':reason' => $latest['reason'] ?? '',
                    ':comments' => '',
                    ':agency_id' => $latest['agency_id'],
                    ':date' => $nextStart->format('Y-m-d'),
                    ':time' => $nextStart->format('H:i'),
                    ':duration' => $duration,
                    ':recurring' => $recurring,
                    ':created_by' => $latest['created_by'] ?? 'System',
                    ':created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $nextStart->add(new DateInterval($intervalSpec));
            $safety++;
        }

        if ($safety < 30) {
            $existsStmt->execute([
                ':series_id' => $seriesId,
                ':date' => $nextStart->format('Y-m-d'),
                ':time' => $nextStart->format('H:i')
            ]);
            $exists = (bool)$existsStmt->fetchColumn();
            if (!$exists) {
                $insertStmt->execute([
                    ':id' => uniqid('meeting_', true),
                    ':series_id' => $seriesId,
                    ':name' => $latest['name'] ?? 'Заседание',
                    ':reason' => $latest['reason'] ?? '',
                    ':comments' => '',
                    ':agency_id' => $latest['agency_id'],
                    ':date' => $nextStart->format('Y-m-d'),
                    ':time' => $nextStart->format('H:i'),
                    ':duration' => $duration,
                    ':recurring' => $recurring,
                    ':created_by' => $latest['created_by'] ?? 'System',
                    ':created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}
