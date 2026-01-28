<?php
function ensureRecurringMeetings(array &$meetings, string $meetingsFile): void
{
    $now = new DateTime();
    $dirty = false;

    foreach ($meetings as $idx => $meeting) {
        if (empty($meeting['series_id'])) {
            $meetings[$idx]['series_id'] = $meeting['id'] ?? uniqid('series_', true);
            $dirty = true;
        }
    }

    $seriesMeetings = [];
    $seriesLatest = [];

    foreach ($meetings as $idx => $meeting) {
        $recurring = $meeting['recurring'] ?? 'Once';
        if ($recurring === 'Once') {
            continue;
        }

        $seriesId = $meeting['series_id'] ?? ($meeting['id'] ?? null);
        if ($seriesId === null) {
            continue;
        }

        $start = new DateTime(($meeting['date'] ?? '1970-01-01') . ' ' . ($meeting['time'] ?? '00:00'));
        $seriesMeetings[$seriesId][] = $idx;

        if (!isset($seriesLatest[$seriesId]) || $start > $seriesLatest[$seriesId]['start']) {
            $seriesLatest[$seriesId] = [
                'index' => $idx,
                'start' => $start
            ];
        }
    }

    foreach ($seriesLatest as $seriesId => $info) {
        $latest = $meetings[$info['index']];
        $recurring = $latest['recurring'] ?? 'Once';
        $intervalSpec = $recurring === 'Daily' ? 'P1D' : ($recurring === 'Weekly' ? 'P1W' : ($recurring === 'Monthly' ? 'P1M' : null));
        if ($intervalSpec === null) {
            continue;
        }

        $duration = isset($latest['duration']) ? intval($latest['duration']) : 60;
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
            $exists = false;
            if (!empty($seriesMeetings[$seriesId])) {
                foreach ($seriesMeetings[$seriesId] as $idx) {
                    $existing = $meetings[$idx];
                    $existingKey = ($existing['date'] ?? '') . ' ' . ($existing['time'] ?? '');
                    if ($existingKey === $nextStart->format('Y-m-d H:i')) {
                        $exists = true;
                        break;
                    }
                }
            }

            if (!$exists) {
                $meetings[] = [
                    'id' => uniqid('meeting_', true),
                    'series_id' => $seriesId,
                    'name' => $latest['name'] ?? 'Заседание',
                    'reason' => $latest['reason'] ?? '',
                    'comments' => '',
                    'agency_name' => $latest['agency_name'] ?? '',
                    'agency_index' => $latest['agency_index'] ?? 0,
                    'date' => $nextStart->format('Y-m-d'),
                    'time' => $nextStart->format('H:i'),
                    'duration' => $duration,
                    'recurring' => $recurring,
                    'created_by' => $latest['created_by'] ?? 'System',
                    'created_at' => date('Y-m-d H:i:s'),
                    'questions' => []
                ];
                $seriesMeetings[$seriesId][] = count($meetings) - 1;
                $dirty = true;
            }

            $nextStart->add(new DateInterval($intervalSpec));
            $safety++;
        }

        if ($safety < 30) {
            $exists = false;
            if (!empty($seriesMeetings[$seriesId])) {
                foreach ($seriesMeetings[$seriesId] as $idx) {
                    $existing = $meetings[$idx];
                    $existingKey = ($existing['date'] ?? '') . ' ' . ($existing['time'] ?? '');
                    if ($existingKey === $nextStart->format('Y-m-d H:i')) {
                        $exists = true;
                        break;
                    }
                }
            }

            if (!$exists) {
                $meetings[] = [
                    'id' => uniqid('meeting_', true),
                    'series_id' => $seriesId,
                    'name' => $latest['name'] ?? 'Заседание',
                    'reason' => $latest['reason'] ?? '',
                    'comments' => '',
                    'agency_name' => $latest['agency_name'] ?? '',
                    'agency_index' => $latest['agency_index'] ?? 0,
                    'date' => $nextStart->format('Y-m-d'),
                    'time' => $nextStart->format('H:i'),
                    'duration' => $duration,
                    'recurring' => $recurring,
                    'created_by' => $latest['created_by'] ?? 'System',
                    'created_at' => date('Y-m-d H:i:s'),
                    'questions' => []
                ];
                $seriesMeetings[$seriesId][] = count($meetings) - 1;
                $dirty = true;
            }
        }
    }

    if ($dirty) {
        file_put_contents($meetingsFile, json_encode($meetings, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
