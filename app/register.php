<?php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body{
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .container{
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 26px;
        }
        h2{
            font-size: 20px;
            line-height: 1.2;
            margin: 0 0 6px 0;
            letter-spacing: -0.01em;
            text-align: left;
        }
        .sub{
            margin: 0 0 14px 0;
            font-size: 14px;
            color: var(--muted);
        }
        .form-group{ margin-bottom: 14px; }
        label{
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        input{
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }
        input:focus{
            border-color: rgba(31,75,153,0.55);
            box-shadow: 0 0 0 4px rgba(31,75,153,0.12);
        }
        button{
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
        button:hover{ opacity: 0.95; }
        button:active{ transform: translateY(1px); }
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
        .link-text{
            text-align: center;
            margin-top: 14px;
            font-size: 14px;
            color: var(--muted);
        }
        .link-text a{
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .link-text a:hover{ text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <p class="sub">Create your account to access the dashboard.</p>
        <?php if (isset($_GET['error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required minlength="3">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="link-text">
            Already have an account? <a href="index.php">Login here</a>
        </div>
    </div>
</body>
</html>
