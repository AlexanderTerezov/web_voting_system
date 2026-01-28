<?php
session_start();

// Set timezone (adjust to your timezone)
date_default_timezone_set('Europe/Sofia'); // Change this to your timezone if needed

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$username = $_SESSION['user'];
$email = $_SESSION['email'];
$role = $_SESSION['role'];
$loginTime = $_SESSION['login_time'];

// Load agencies
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

// Load meetings
$meetings_file = '../db/meetings.json';
$meetings = [];
if (file_exists($meetings_file)) {
    $meetings = json_decode(file_get_contents($meetings_file), true);
}

// Load users for admin
$users = [];
if ($role === 'Admin') {
    $users_file = '../db/users.json';
    if (file_exists($users_file)) {
        $users = json_decode(file_get_contents($users_file), true);
    }
}

// Filter agencies for normal users and check if they are secretary
$userAgencies = [];
$isSecretaryInAny = false;
if ($role !== 'Admin') {
    foreach ($agencies as $index => $agency) {
        foreach ($agency['participants'] as $participant) {
            if ($participant['username'] === $username) {
                $userAgencies[] = [
                    'agency' => $agency,
                    'role' => $participant['role'],
                    'index' => $index
                ];
                if ($participant['role'] === 'secretary') {
                    $isSecretaryInAny = true;
                }
                break;
            }
        }
    }
}

// Get upcoming and past meetings for user
$upcomingMeetings = [];
$pastMeetings = [];
$now = new DateTime();

foreach ($meetings as $meeting) {
    // Calculate meeting end time based on duration
    $meetingStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
    if (!empty($meeting['started_at'])) {
        $overrideStart = new DateTime($meeting['started_at']);
        if ($overrideStart < $meetingStart) {
            $meetingStart = $overrideStart;
        }
    }
    $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60; // Default 60 minutes
    $meetingEnd = clone $meetingStart;
    $meetingEnd->modify("+{$duration} minutes");
    if (!empty($meeting['ended_at'])) {
        $overrideEnd = new DateTime($meeting['ended_at']);
        if ($overrideEnd < $meetingEnd) {
            $meetingEnd = $overrideEnd;
        }
    }
    
    // Check if user is part of this agency
    $isParticipant = false;
    foreach ($agencies as $agency) {
        if ($agency['name'] === $meeting['agency_name']) {
            foreach ($agency['participants'] as $participant) {
                if ($participant['username'] === $username || $role === 'Admin') {
                    $isParticipant = true;
                    break 2;
                }
            }
        }
    }
    
    if ($isParticipant) {
        if ($meetingEnd >= $now) {
            $upcomingMeetings[] = $meeting;
        } else {
            $pastMeetings[] = $meeting;
        }
    }
}

// Sort upcoming by date/time (ascending)
usort($upcomingMeetings, function($a, $b) {
    $dateA = new DateTime($a['date'] . ' ' . $a['time']);
    $dateB = new DateTime($b['date'] . ' ' . $b['time']);
    return $dateA <=> $dateB;
});

// Sort past by date/time (descending - most recent first)
usort($pastMeetings, function($a, $b) {
    $dateA = new DateTime($a['date'] . ' ' . $a['time']);
    $dateB = new DateTime($b['date'] . ' ' . $b['time']);
    return $dateB <=> $dateA;
});

// Get last 5 past meetings for quick view
$recentPastMeetings = array_slice($pastMeetings, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        :root{
            --bg: #1110;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
            --radius: 14px;
            --accent: #1f4b99;
            --danger-bg: #fef2f2;
            --danger-text: #991b1b;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body{
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .container{
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .header, .admin-panel, .agencies-section, .meetings-section{
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }
        h1{
            font-size: 22px;
            line-height: 1.2;
            margin: 0 0 10px 0;
            letter-spacing: -0.01em;
        }
        h2{
            font-size: 18px;
            margin: 0 0 12px 0;
        }
        h3{
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .user-info{
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 10px;
            margin-top: 12px;
        }
        .user-info p{ margin: 6px 0; color: var(--muted); }
        .user-role{
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
            background: #eef2ff;
            color: #1f2937;
        }
        .admin-role{ background: rgba(31,75,153,0.12); color: var(--accent); }
        .user-role-badge{ background: rgba(31,75,153,0.12); color: var(--accent); }

        .form-group{ margin-bottom: 14px; }
        .form-group label{
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group input, .form-group select{
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }
        .form-group input:focus, .form-group select:focus{
            border-color: rgba(31,75,153,0.55);
            box-shadow: 0 0 0 4px rgba(31,75,153,0.12);
        }
        .participant-item{
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        .participant-item select{ flex: 1; }
        .add-participant-btn, .remove-participant-btn{
            padding: 8px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            background: #111827;
            color: #fff;
        }
        .add-participant-btn{
            margin-bottom: 10px;
        }
        .remove-participant-btn{
            background: #fff;
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .submit-btn{
            width: 100%;
            padding: 11px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            background: #111827;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 80ms ease, opacity 120ms ease;
        }
        .submit-btn:hover{ opacity: 0.95; }
        .submit-btn:active{ transform: translateY(1px); }
        .logout-btn{
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            transition: box-shadow 120ms ease;
        }
        .logout-btn:hover{ box-shadow: 0 4px 12px rgba(17,24,39,0.08); }
        .message{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            margin: 14px 0;
            font-size: 14px;
        }
        .error{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .success{
            background: var(--success-bg);
            color: var(--success-text);
            border-color: rgba(6,95,70,0.25);
        }
        .agency-card, .meeting-card{
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .agency-info, .meeting-info{
            font-size: 14px;
            color: var(--muted);
            margin: 4px 0;
        }
        .participant-list{
            margin-top: 10px;
            font-size: 14px;
        }
        .participant{
            display: inline-block;
            padding: 4px 10px;
            margin: 3px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 13px;
        }
        .participant.secretary{
            background: rgba(31,75,153,0.12);
            color: var(--accent);
            border-color: rgba(31,75,153,0.25);
        }
        .delete-agency-btn, .edit-agency-btn, .create-meeting-btn, .view-meeting-btn, .delete-meeting-btn{
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            margin-right: 8px;
            font-size: 14px;
            transition: box-shadow 120ms ease;
        }
        .delete-agency-btn, .delete-meeting-btn{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .edit-agency-btn{
            background: #fff;
            color: var(--accent);
        }
        .create-meeting-btn{
            background: #111827;
            color: #fff;
        }
        .view-meeting-btn{
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .delete-agency-btn:hover, .edit-agency-btn:hover, .create-meeting-btn:hover, .view-meeting-btn:hover, .delete-meeting-btn:hover{
            box-shadow: 0 4px 12px rgba(17,24,39,0.12);
        }
        .agency-actions{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .inline-form{
            display: inline;
        }
        
        /* Meeting sections styling */
        .meeting-header-wrapper{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .meeting-status{
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-upcoming{
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-past{
            background: #f3f4f6;
            color: #6b7280;
        }
        .status-active{
            background: #dcfce7;
            color: #166534;
        }
        
        .meeting-name{
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 6px;
            color: var(--text);
        }
        
        .empty-state{
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>Dashboard</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="all_meetings.php" class="logout-btn" style="background: var(--accent); color: #fff;">View All Meetings</a>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
            <div class="user-info">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Role:</strong> <span class="user-role <?php echo $role === 'Admin' ? 'admin-role' : 'user-role-badge'; ?>"><?php echo htmlspecialchars($role); ?></span></p>
                <p><strong>Login Time:</strong> <?php echo htmlspecialchars($loginTime); ?></p>
                <p><strong>Current Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?> (<?php echo date_default_timezone_get(); ?>)</p>
            </div>
        </div>

        <!-- Upcoming Meetings -->
        <?php if (!empty($upcomingMeetings)): ?>
        <div class="meetings-section">
            <div class="meeting-header-wrapper">
                <h2>Upcoming Meetings</h2>
            </div>
            <?php foreach ($upcomingMeetings as $meeting): ?>
                <?php
                // Check if user can delete this meeting
                $canDelete = false;
                if ($role === 'Admin') {
                    $canDelete = true;
                } else {
                    foreach ($agencies as $agency) {
                        if ($agency['name'] === $meeting['agency_name']) {
                            foreach ($agency['participants'] as $participant) {
                                if ($participant['username'] === $username && $participant['role'] === 'secretary') {
                                    $canDelete = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Calculate end time
                $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
                $meetingStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
                if (!empty($meeting['started_at'])) {
                    $overrideStart = new DateTime($meeting['started_at']);
                    if ($overrideStart < $meetingStart) {
                        $meetingStart = $overrideStart;
                    }
                }
                $endTime = clone $meetingStart;
                $endTime->modify("+{$duration} minutes");
                if (!empty($meeting['ended_at'])) {
                    $overrideEnd = new DateTime($meeting['ended_at']);
                    if ($overrideEnd < $endTime) {
                        $endTime = $overrideEnd;
                    }
                }
                $isActive = $now >= $meetingStart && $now <= $endTime;
                ?>
                <div class="meeting-card">
                    <div class="meeting-name">
                        <?php echo htmlspecialchars($meeting['name'] ?? 'Unnamed Meeting'); ?>
                        <?php if ($isActive): ?>
                            <span class="meeting-status status-active">Active</span>
                        <?php else: ?>
                            <span class="meeting-status status-upcoming">Upcoming</span>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($meeting['agency_name']); ?></h3>
                    <p class="meeting-info"><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                    <p class="meeting-info"><strong>Time:</strong> <?php echo htmlspecialchars($meeting['time']); ?> - <?php echo $endTime->format('H:i'); ?> (<?php echo $duration; ?> minutes)</p>
                    <p class="meeting-info"><strong>Recurring:</strong> <?php echo htmlspecialchars($meeting['recurring']); ?></p>
                    <div style="margin-top: 10px;">
                        <a href="view_meeting.php?id=<?php echo $meeting['id']; ?>" class="view-meeting-btn">View Meeting</a>
                        <?php if ($canDelete): ?>
                            <form action="delete_meeting.php" method="POST" style="display: inline;">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                <button type="submit" class="delete-meeting-btn" onclick="return confirm('Are you sure you want to delete this meeting?')">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Past Meetings -->
        <?php if (!empty($recentPastMeetings)): ?>
        <div class="meetings-section">
            <div class="meeting-header-wrapper">
                <h2>Recent Past Meetings</h2>
            </div>
            <?php foreach ($recentPastMeetings as $meeting): ?>
                <?php
                // Check if user can delete this meeting
                $canDelete = false;
                if ($role === 'Admin') {
                    $canDelete = true;
                } else {
                    foreach ($agencies as $agency) {
                        if ($agency['name'] === $meeting['agency_name']) {
                            foreach ($agency['participants'] as $participant) {
                                if ($participant['username'] === $username && $participant['role'] === 'secretary') {
                                    $canDelete = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Calculate end time
                $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
                $meetingStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
                if (!empty($meeting['started_at'])) {
                    $overrideStart = new DateTime($meeting['started_at']);
                    if ($overrideStart < $meetingStart) {
                        $meetingStart = $overrideStart;
                    }
                }
                $endTime = clone $meetingStart;
                $endTime->modify("+{$duration} minutes");
                if (!empty($meeting['ended_at'])) {
                    $overrideEnd = new DateTime($meeting['ended_at']);
                    if ($overrideEnd < $endTime) {
                        $endTime = $overrideEnd;
                    }
                }
                ?>
                <div class="meeting-card">
                    <div class="meeting-name">
                        <?php echo htmlspecialchars($meeting['name'] ?? 'Unnamed Meeting'); ?>
                        <span class="meeting-status status-past">Past</span>
                    </div>
                    <h3><?php echo htmlspecialchars($meeting['agency_name']); ?></h3>
                    <p class="meeting-info"><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                    <p class="meeting-info"><strong>Time:</strong> <?php echo htmlspecialchars($meeting['time']); ?> - <?php echo $endTime->format('H:i'); ?> (<?php echo $duration; ?> minutes)</p>
                    <p class="meeting-info"><strong>Recurring:</strong> <?php echo htmlspecialchars($meeting['recurring']); ?></p>
                    <div style="margin-top: 10px;">
                        <a href="view_meeting.php?id=<?php echo $meeting['id']; ?>" class="view-meeting-btn">View Meeting</a>
                        <?php if ($canDelete): ?>
                            <form action="delete_meeting.php" method="POST" style="display: inline;">
                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                <button type="submit" class="delete-meeting-btn" onclick="return confirm('Are you sure you want to delete this meeting?')">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (empty($upcomingMeetings)): ?>
        <div class="meetings-section">
            <h2>Meetings</h2>
            <div class="empty-state">
                <p>No meetings scheduled yet</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'Admin'): ?>
            <!-- Admin Panel: Create Agency -->
            <div class="admin-panel">
                <h2>Create New Agency</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="message success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                
                <form action="create_agency.php" method="POST" id="agencyForm">
                    <div class="form-group">
                        <label for="agency_name">Agency Name</label>
                        <input type="text" id="agency_name" name="agency_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quorum">Quorum</label>
                        <input type="number" id="quorum" name="quorum" min="1" step="1" inputmode="numeric" pattern="[0-9]*" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Participants</label>
                        <div id="participantsContainer">
                            <div class="participant-item">
                                <select name="participants[]" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="roles[]" required>
                                    <option value="member">Member</option>
                                    <option value="secretary">Secretary</option>
                                </select>
                                <button type="button" class="remove-participant-btn" onclick="removeParticipant(this)">Remove</button>
                            </div>
                        </div>
                        <button type="button" class="add-participant-btn" onclick="addParticipant()">+ Add Participant</button>
                    </div>
                    
                    <button type="submit" class="submit-btn">Create Agency</button>
                </form>
            </div>

            <!-- Admin View: All Agencies -->
            <div class="agencies-section">
                <h2>All Agencies</h2>
                <?php if (empty($agencies)): ?>
                    <p style="color: #999; text-align: center;">No agencies created yet.</p>
                <?php else: ?>
                    <?php foreach ($agencies as $index => $agency): ?>
                        <div class="agency-card">
                            <h3><?php echo htmlspecialchars($agency['name']); ?></h3>
                            <p class="agency-info"><strong>Quorum:</strong> <?php echo htmlspecialchars($agency['quorum']); ?></p>
                            <p class="agency-info"><strong>Total Participants:</strong> <?php echo count($agency['participants']); ?></p>
                            <p class="agency-info"><strong>Created:</strong> <?php echo htmlspecialchars($agency['created_at']); ?></p>
                            
                            <div class="participant-list">
                                <strong>Participants:</strong><br>
                                <?php 
                                $adminIsSecretary = false;
                                foreach ($agency['participants'] as $participant): 
                                    if ($participant['username'] === $_SESSION['user'] && $participant['role'] === 'secretary') {
                                        $adminIsSecretary = true;
                                    }
                                ?>
                                    <span class="participant <?php echo $participant['role'] === 'secretary' ? 'secretary' : ''; ?>">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php echo $participant['role'] === 'secretary' ? '(Secretary)' : ''; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <div class="agency-actions">
                                <form action="delete_agency.php" method="POST" class="inline-form">
                                    <input type="hidden" name="agency_index" value="<?php echo $index; ?>">
                                    <button type="submit" class="delete-agency-btn" onclick="return confirm('Are you sure you want to delete this agency?')">Delete Agency</button>
                                </form>

                                <a href="edit_agency.php?index=<?php echo $index; ?>" class="edit-agency-btn">Edit Agency</a>

                                <?php if ($adminIsSecretary): ?>
                                    <a href="create_meeting.php?agency_index=<?php echo $index; ?>" class="create-meeting-btn">Create Meeting</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- User View: Their Agencies Only -->
            <div class="agencies-section">
                <h2>My Agencies</h2>
                <?php if (empty($userAgencies)): ?>
                    <p style="color: #999; text-align: center;">You are not a member of any agency yet.</p>
                <?php else: ?>
                    <?php foreach ($userAgencies as $item): ?>
                        <?php $agency = $item['agency']; $userRole = $item['role']; $agencyIndex = $item['index']; ?>
                        <div class="agency-card">
                            <h3><?php echo htmlspecialchars($agency['name']); ?></h3>
                            <p class="agency-info"><strong>Your Role:</strong> 
                                <span class="participant <?php echo $userRole === 'secretary' ? 'secretary' : ''; ?>">
                                    <?php echo $userRole === 'secretary' ? 'Secretary' : 'Member'; ?>
                                </span>
                            </p>
                            <p class="agency-info"><strong>Quorum:</strong> <?php echo htmlspecialchars($agency['quorum']); ?></p>
                            <p class="agency-info"><strong>Total Participants:</strong> <?php echo count($agency['participants']); ?></p>
                            
                            <div class="participant-list">
                                <strong>All Participants:</strong><br>
                                <?php foreach ($agency['participants'] as $participant): ?>
                                    <span class="participant <?php echo $participant['role'] === 'secretary' ? 'secretary' : ''; ?>">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php echo $participant['role'] === 'secretary' ? '(Secretary)' : ''; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($userRole === 'secretary'): ?>
                                <div class="agency-actions">
                                    <a href="create_meeting.php?agency_index=<?php echo $agencyIndex; ?>" class="create-meeting-btn">Create Meeting</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Agency form functions
        function addParticipant() {
            const container = document.getElementById('participantsContainer');
            const newItem = container.firstElementChild.cloneNode(true);
            newItem.querySelector('select[name="participants[]"]').value = '';
            newItem.querySelector('select[name="roles[]"]').value = 'member';
            container.appendChild(newItem);
        }

        function removeParticipant(btn) {
            const container = document.getElementById('participantsContainer');
            if (container.children.length > 1) {
                btn.parentElement.remove();
            } else {
                alert('At least one participant is required');
            }
        }
    </script>
</body>
</html>
