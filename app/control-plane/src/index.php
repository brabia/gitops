<?php $now = new DateTime('now', new DateTimeZone('UTC')); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Linexa</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0;
           min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 2.5rem; max-width: 480px; width: 100%; text-align: center; }
    h1 { font-size: 2rem; font-weight: 700; margin-bottom: .5rem; }
    .date { font-family: monospace; font-size: .85rem; color: #64748b;
            margin-bottom: 1.5rem; }
    .links { display: flex; flex-direction: column; gap: .5rem; }
    a  { color: #60a5fa; text-decoration: none; font-size: .9rem; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Linexa</h1>
    <p class="date"><?= $now->format('Y-m-d H:i:s') ?> UTC</p>
    <div class="links">
      <a href="http://dev.tenant-cfd4486.linexa.eu">&rarr; tenant cfd4486</a>
      <a href="http://dev.tenant-3622fab.linexa.eu">&rarr; tenant 3622fab</a>
    </div>
  </div>
</body>
</html>
