<?php $now = new DateTime('now', new DateTimeZone('UTC')); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tenant cfd4486</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0;
           min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px;
            padding: 2.5rem; max-width: 480px; width: 100%; text-align: center; }
    .badge { font-family: monospace; font-size: .75rem; color: #a78bfa;
             background: #1e1b4b; border: 1px solid #6d28d9; border-radius: 6px;
             padding: .2rem .75rem; display: inline-block; margin-bottom: 1.5rem; }
    h1 { font-size: 2rem; font-weight: 700; margin-bottom: .5rem; }
    .date { font-family: monospace; font-size: .85rem; color: #64748b; margin-bottom: 1.5rem; }
    p  { color: #94a3b8; margin-bottom: 1.5rem; }
    a  { color: #60a5fa; text-decoration: none; font-size: .875rem; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <div class="badge">tenant: cfd4486</div>
    <h1>Hello World</h1>
    <p class="date"><?= $now->format('Y-m-d H:i:s') ?> UTC</p>
    <p>Isolated namespace &middot; own pod &middot; php-fpm + nginx</p>
    <a href="http://dev.linexa.eu">&larr; back to control plane</a>
  </div>
</body>
</html>
