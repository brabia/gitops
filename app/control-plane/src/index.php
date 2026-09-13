<?php
$namespace = 'linexa-dev';
$now       = new DateTime('now', new DateTimeZone('UTC'));

// ── Own database ──────────────────────────────────────────────────────────────
$ownUsers = [];
$ownError = null;
$ownQuery = 'SELECT id, name, email, created_at FROM users ORDER BY id';

try {
    $db = new PDO(
        'mysql:host=mysql.linexa-dev.svc.cluster.local;port=3306;dbname=linexa;connect_timeout=10',
        'linexa', 'linexapass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $db->exec("INSERT INTO users (name, email) VALUES
            ('Admin',   'admin@linexa.eu'),
            ('Manager', 'manager@linexa.eu')");
    }
    $ownUsers = $db->query($ownQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ownError = $e->getMessage();
}

// ── Cross-tenant isolation test ───────────────────────────────────────────────
$crossTargets = [
    'linexa-tenant-cfd4486' => 'mysql.linexa-tenant-cfd4486.svc.cluster.local',
    'linexa-tenant-3622fab' => 'mysql.linexa-tenant-3622fab.svc.cluster.local',
    'linexa-tenant-3bf9bd5' => 'mysql.linexa-tenant-3bf9bd5.svc.cluster.local',
];
$crossResults = [];
foreach ($crossTargets as $ns => $host) {
    try {
        $x = new PDO(
            "mysql:host=$host;port=3306;dbname=linexa;connect_timeout=2",
            'linexa', 'linexapass',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        $rows = $x->query($ownQuery)->fetchAll(PDO::FETCH_ASSOC);
        $crossResults[$ns] = ['ok' => true, 'rows' => $rows];
    } catch (Exception $e) {
        $crossResults[$ns] = ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Tenant index for nav links
$tenants = [
    'cfd4486' => 'http://dev.tenant-cfd4486.linexa.eu',
    '3622fab' => 'http://dev.tenant-3622fab.linexa.eu',
    '3bf9bd5' => 'http://dev.tenant-3bf9bd5.linexa.eu',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Linexa Control Plane</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0;
           min-height: 100vh; padding: 2rem 1rem; }
    .wrap { max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }

    .header { background: linear-gradient(135deg, #1e293b 0%, #0f2038 100%);
              border: 1px solid #334155; border-radius: 12px; padding: 2rem; text-align: center; }
    .badge { font-family: monospace; font-size: .75rem; color: #38bdf8;
             background: #0c1a2e; border: 1px solid #0369a1; border-radius: 6px;
             padding: .2rem .75rem; display: inline-block; margin-bottom: 1rem; }
    h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: .25rem; }
    .meta { font-family: monospace; font-size: .8rem; color: #64748b; }

    .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1.25rem; }
    .card-title { font-size: .7rem; font-weight: 700; letter-spacing: .08em;
                  text-transform: uppercase; color: #64748b; margin-bottom: .75rem; }

    .query { font-family: monospace; font-size: .8rem; background: #0f172a;
             border: 1px solid #334155; border-radius: 6px; padding: .6rem .9rem;
             color: #7dd3fc; margin-bottom: .75rem; overflow-x: auto; white-space: nowrap; }

    table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    th { text-align: left; padding: .4rem .6rem; color: #94a3b8;
         font-size: .7rem; text-transform: uppercase; letter-spacing: .06em;
         border-bottom: 1px solid #334155; }
    td { padding: .45rem .6rem; border-bottom: 1px solid #1e293b; font-family: monospace; font-size: .8rem; }
    tr:last-child td { border-bottom: none; }

    .iso-row { display: flex; align-items: flex-start; gap: .75rem;
               padding: .6rem 0; border-bottom: 1px solid #1e3050; }
    .iso-row:last-child { border-bottom: none; }
    .pill { font-size: .65rem; font-weight: 700; padding: .15rem .5rem;
            border-radius: 99px; white-space: nowrap; margin-top: .15rem; }
    .pill-blocked { background: #451a1a; color: #f87171; border: 1px solid #7f1d1d; }
    .pill-ok      { background: #14532d; color: #4ade80; border: 1px solid #166534; }
    .iso-ns  { font-family: monospace; font-size: .8rem; color: #cbd5e1; }
    .iso-err { font-family: monospace; font-size: .72rem; color: #94a3b8; margin-top: .25rem; word-break: break-all; }

    .tenant-links { display: flex; gap: .75rem; flex-wrap: wrap; }
    .tenant-link { font-family: monospace; font-size: .8rem; color: #60a5fa;
                   background: #1e3a5f; border: 1px solid #2563eb; border-radius: 6px;
                   padding: .35rem .75rem; text-decoration: none; }
    .tenant-link:hover { background: #1d4ed8; color: #fff; }

    .error { color: #f87171; font-family: monospace; font-size: .8rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <div class="badge">control-plane · <?= $namespace ?></div>
    <h1>Linexa / <?= $now->format('Y-m-d H:i:s') ?> UTC</h1>
    <p class="meta">own pod &nbsp;·&nbsp; own namespace &nbsp;·&nbsp; own MySQL</p>
  </div>

  <!-- tenant nav -->
  <div class="card">
    <div class="card-title">Tenants</div>
    <div class="tenant-links">
      <?php foreach ($tenants as $id => $url): ?>
        <a class="tenant-link" href="<?= $url ?>">→ tenant <?= $id ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- own users -->
  <div class="card">
    <div class="card-title">Own database — mysql.<?= $namespace ?>.svc.cluster.local</div>
    <div class="query"><?= htmlspecialchars($ownQuery) ?></div>
    <?php if ($ownError): ?>
      <p class="error">⚠ <?= htmlspecialchars($ownError) ?></p>
    <?php elseif (empty($ownUsers)): ?>
      <p style="color:#64748b;font-size:.85rem">No rows yet.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>id</th><th>name</th><th>email</th><th>created_at</th></tr></thead>
        <tbody>
          <?php foreach ($ownUsers as $row): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= $row['created_at'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- isolation test -->
  <div class="card">
    <div class="card-title">Isolation test — cross-namespace MySQL queries from control plane</div>
    <?php foreach ($crossResults as $ns => $result): ?>
    <div class="iso-row">
      <span class="pill <?= $result['ok'] ? 'pill-ok' : 'pill-blocked' ?>">
        <?= $result['ok'] ? 'REACHED' : 'BLOCKED' ?>
      </span>
      <div>
        <div class="iso-ns">mysql.<?= $ns ?>.svc.cluster.local</div>
        <?php if ($result['ok']): ?>
          <div class="iso-err" style="color:#4ade80"><?= count($result['rows']) ?> row(s) returned — isolation failure!</div>
        <?php else: ?>
          <div class="iso-err"><?= htmlspecialchars($result['error']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>
</body>
</html>
