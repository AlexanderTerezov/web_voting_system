<?php
session_start();
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

// Get upcoming meetings for user
$upcomingMeetings = [];
$now = new DateTime();
foreach ($meetings as $meeting) {
    $meetingDate = new DateTime($meeting['date'] . ' ' . $meeting['time']);
    
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
    
    if ($isParticipant && $meetingDate >= $now) {
        $upcomingMeetings[] = $meeting;
    }
}

// Sort by date/time
usort($upcomingMeetings, function($a, $b) {
    $dateA = new DateTime($a['date'] . ' ' . $a['time']);
    $dateB = new DateTime($b['date'] . ' ' . $b['time']);
    return $dateA <=> $dateB;
});
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

        .logout-btn,
        .add-participant-btn,
        .remove-participant-btn,
        .submit-btn,
        .delete-agency-btn,
        .edit-agency-btn,
        .create-meeting-btn,
        .view-meeting-btn,
        .delete-meeting-btn{
            padding: 10px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            background: #111827;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 80ms ease, opacity 120ms ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .logout-btn:hover,
        .add-participant-btn:hover,
        .remove-participant-btn:hover,
        .submit-btn:hover,
        .delete-agency-btn:hover,
        .edit-agency-btn:hover,
        .create-meeting-btn:hover,
        .view-meeting-btn:hover,
        .delete-meeting-btn:hover{ opacity: 0.95; }
        .logout-btn:active,
        .add-participant-btn:active,
        .remove-participant-btn:active,
        .submit-btn:active,
        .delete-agency-btn:active,
        .edit-agency-btn:active,
        .create-meeting-btn:active,
        .view-meeting-btn:active,
        .delete-meeting-btn:active{ transform: translateY(1px); }

        .remove-participant-btn,
        .delete-agency-btn,
        .delete-meeting-btn{
            background: #fff;
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .edit-agency-btn,
        .view-meeting-btn{
            background: #fff;
            color: var(--accent);
            border-color: rgba(31,75,153,0.25);
        }
        .create-meeting-btn{
            background: #fff;
            color: var(--success-text);
            border-color: rgba(6,95,70,0.25);
        }
        .logout-btn{ margin-top: 12px; }
        .add-participant-btn{ margin-bottom: 10px; }

        .message{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 14px;
            font-size: 14px;
        }
        .success{
            background: var(--success-bg);
            color: var(--success-text);
            border-color: rgba(6,95,70,0.25);
        }
        .error{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }

        .agency-card, .meeting-card{
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .agency-info, .meeting-info{ margin-bottom: 6px; color: var(--muted); }
        .participant-list{
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
        .participant{
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            background: #eef2ff;
            border-radius: 999px;
            margin: 4px 6px 4px 0;
            font-size: 12px;
            color: #1f2937;
        }
        .participant.secretary{
            background: rgba(31,75,153,0.12);
            color: var(--accent);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <div class="user-info">
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Role:</strong> <span class="user-role <?php echo $role === 'Admin' ? 'admin-role' : 'user-role-badge'; ?>"><?php echo htmlspecialchars($role); ?></span></p>
                <p><strong>Login Time:</strong> <?php echo htmlspecialchars($loginTime); ?></p>
            </div>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <!-- Upcoming Meetings -->
        <?php if (!empty($upcomingMeetings)): ?>
        <div class="meetings-section">
            <h2>Upcoming Meetings</h2>
            <?php foreach ($upcomingMeetings as $meeting): ?>
                <?php
                // Check if user is secretary in this meeting's agency
                $canDelete = false;
                foreach ($agencies as $agency) {
                    if ($agency['name'] === $meeting['agency_name']) {
                        foreach ($agency['participants'] as $participant) {
                            if ($participant['username'] === $_SESSION['user'] && $participant['role'] === 'secretary') {
                                $canDelete = true;
                                break 2;
                            }
                        }
                    }
                }
                ?>
                <div class="meeting-card">
                    <h3><?php echo htmlspecialchars($meeting['agency_name']); ?> - Meeting</h3>
                    <p class="meeting-info"><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                    <p class="meeting-info"><strong>Time:</strong> <?php echo htmlspecialchars($meeting['time']); ?></p>
                    <p class="meeting-info"><strong>Recurring:</strong> <?php echo htmlspecialchars($meeting['recurring']); ?></p>
                    <a href="view_meeting.php?id=<?php echo $meeting['id']; ?>" class="view-meeting-btn">View Meeting</a>
                    <?php if ($canDelete): ?>
                        <form action="delete_meeting.php" method="POST" style="display: inline;">
                            <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                            <button type="submit" class="delete-meeting-btn" onclick="return confirm('Are you sure you want to delete this meeting?')">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
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
                        <input type="number" id="quorum" name="quorum" min="1" required>
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
                            
                            <form action="delete_agency.php" method="POST" style="display: inline;">
                                <input type="hidden" name="agency_index" value="<?php echo $index; ?>">
                                <button type="submit" class="delete-agency-btn" onclick="return confirm('Are you sure you want to delete this agency?')">Delete Agency</button>
                            </form>
                            <a href="edit_agency.php?index=<?php echo $index; ?>">
                                <button type="button" class="edit-agency-btn">Edit Agency</button>
                            </a>
                            <?php if ($adminIsSecretary): ?>
                                <a href="create_meeting.php?agency_index=<?php echo $index; ?>" class="create-meeting-btn">Create Meeting</a>
                            <?php endif; ?>
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
                                <a href="create_meeting.php?agency_index=<?php echo $agencyIndex; ?>" class="create-meeting-btn">Create Meeting</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
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
