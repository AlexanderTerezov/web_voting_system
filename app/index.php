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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Вход</title>
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
      display: grid;
      place-items: center;
      padding: 24px;
    }

    .card{
      width: 100%;
      max-width: 420px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 26px;
    }

    .header{
      margin-bottom: 18px;
    }

    h1{
      font-size: 20px;
      line-height: 1.2;
      margin: 0 0 6px 0;
      letter-spacing: -0.01em;
    }

    .sub{
      margin: 0;
      font-size: 14px;
      color: var(--muted);
    }

    .message{
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 12px;
      margin: 14px 0;
      font-size: 14px;
    }
    .message.error{
      background: var(--danger-bg);
      color: var(--danger-text);
      border-color: rgba(153,27,27,0.25);
    }
    .message.success{
      background: var(--success-bg);
      color: var(--success-text);
      border-color: rgba(6,95,70,0.25);
    }

    form{ margin-top: 10px; }

    .field{
      margin-bottom: 14px;
    }

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

    .btn{
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
    .btn:hover{ opacity: 0.95; }
    .btn:active{ transform: translateY(1px); }

    .footer{
      margin-top: 14px;
      text-align: center;
      font-size: 14px;
      color: var(--muted);
    }
    .footer a{
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
    }
    .footer a:hover{ text-decoration: underline; }
  </style>
</head>
<body>
  <main class="card" role="main" aria-label="Вход">
    <div class="header">
      <h1>Вход</h1>
      <p class="sub">Използвайте своите данни, за да влезете в таблото.</p>
    </div>

    <?php if (isset($_GET['error'])): ?>
      <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['logout'])): ?>
      <div class="message success">Излязохте успешно.</div>
    <?php endif; ?>

    <?php if (isset($_GET['registered'])): ?>
      <div class="message success">Акаунтът е създаден. Моля, влезте.</div>
    <?php endif; ?>

    <form action="login.php" method="POST" autocomplete="on">
      <div class="field">
        <label for="username">Потребителско име</label>
        <input
          type="text"
          id="username"
          name="username"
          autocomplete="username"
          placeholder="Въведете потребителско име"
          required
        />
      </div>

      <div class="field">
        <label for="password">Парола</label>
        <input
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          placeholder="Въведете парола"
          required
        />
      </div>

      <button class="btn" type="submit">Вход</button>
    </form>

    <div class="footer">
      Нов потребител? <a href="register.php">Създай акаунт</a>
    </div>
  </main>
</body>
</html>
