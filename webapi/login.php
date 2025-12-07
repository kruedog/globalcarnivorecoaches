<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Coach Login • Global Carnivore Coaches</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --red:#C51230;
      --blue:#192168;
      --bg:#f5f7fa;
      --card:#ffffff;
    }
    body {
      margin:0;
      font-family:'Inter',sans-serif;
      background:radial-gradient(circle at 10% 10%, #F8F9FA 0%, #D0D2D5 40%, #B0B3BA 100%);
      color:#111;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:1.5rem;
    }
    .card {
      background:var(--card);
      border-radius:16px;
      padding:2rem 2.2rem;
      max-width:420px;
      width:100%;
      box-shadow:0 14px 40px rgba(0,0,0,.2);
      border:1px solid rgba(25,33,104,.15);
    }
    h1 {
      margin:0 0 .4rem;
      font-family:'Cinzel',serif;
      color:var(--blue);
      font-size:1.9rem;
      text-align:center;
    }
    .sub {
      text-align:center;
      font-size:.9rem;
      color:#555;
      margin-bottom:1.5rem;
    }
    label {
      display:block;
      font-weight:600;
      margin-top:.8rem;
      margin-bottom:.25rem;
      color:var(--blue);
      font-size:.9rem;
    }
    input {
      width:100%;
      padding:.6rem .75rem;
      font-size:.95rem;
      border-radius:10px;
      border:2px solid #d2d4dd;
      font-family:inherit;
      box-sizing:border-box;
    }
    input:focus {
      border-color:var(--red);
      outline:none;
      box-shadow:0 0 0 3px rgba(197,18,48,.2);
    }
    button {
      width:100%;
      margin-top:1.3rem;
      padding:.8rem 1rem;
      border-radius:999px;
      border:none;
      background:linear-gradient(180deg,var(--red),#8C1925);
      color:#fff;
      font-weight:700;
      font-size:.95rem;
      cursor:pointer;
      box-shadow:0 10px 26px rgba(0,0,0,.25);
    }
    button:hover {
      transform:translateY(-1px);
      box-shadow:0 16px 40px rgba(0,0,0,.3);
    }
    .status {
      margin-top:.8rem;
      min-height:1rem;
      font-size:.85rem;
      text-align:center;
    }
    .status.err {
      color:var(--red);
    }
    .status.ok {
      color:green;
    }
    .links {
      margin-top:1rem;
      text-align:center;
      font-size:.85rem;
    }
    .links a {
      color:var(--blue);
      text-decoration:none;
      font-weight:600;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Coach Login</h1>
    <div class="sub">Access your profile & tools.</div>

    <form id="loginForm" autocomplete="off">
      <label for="username">Username</label>
      <input id="username" name="username" required autofocus />

      <label for="password">Password</label>
      <input id="password" name="password" type="password" required />

      <button type="submit">Login</button>
      <div class="status" id="status"></div>
    </form>

    <div class="links">
      <a href="/">← Back to site</a>
    </div>
  </div>

  <script>
    const API_BASE = window.location.origin + '/webapi/';

    const form   = document.getElementById('loginForm');
    const status = document.getElementById('status');

    function setStatus(msg, ok) {
      status.textContent = msg || '';
      status.className = 'status ' + (msg ? (ok ? 'ok' : 'err') : '');
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      setStatus('Logging in…', true);

      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;

      try {
        const res = await fetch(API_BASE + 'login.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          credentials: 'include',
          cache: 'no-store',
          body: JSON.stringify({ username, password })
        });

        const data = await res.json();
        if (!data.success) {
          setStatus(data.message || 'Login failed', false);
          return;
        }

        // Save for convenience (not for security)
        localStorage.setItem('username', data.username || username);
        localStorage.setItem('role', (data.role || 'coach').toLowerCase());

        setStatus('Success. Redirecting…', true);
        window.location.href = '/profile.html';
      } catch (err) {
        console.error(err);
        setStatus('Network error while logging in.', false);
      }
    });
  </script>
</body>
</html>
