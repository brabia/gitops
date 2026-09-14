<?php
$namespace = 'linexa-dev';
$now       = new DateTime('now', new DateTimeZone('UTC'));

// ── DB connection ──────────────────────────────────────────────────────────────
$db      = null;
$dbError = null;
try {
    $db = new PDO(
        'mysql:host=mysql.linexa-dev.svc.cluster.local;port=3306;dbname=linexa;connect_timeout=10',
        'linexa', 'linexapass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// ── Schema ────────────────────────────────────────────────────────────────────
if ($db) {
    $db->exec('CREATE TABLE IF NOT EXISTS organizations (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS tenants (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        org_id     INT NOT NULL,
        name       VARCHAR(100) NOT NULL,
        namespace  VARCHAR(100) NOT NULL,
        url        VARCHAR(255) NOT NULL,
        enabled    TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');

    // ── Seed ──────────────────────────────────────────────────────────────────
    if ((int)$db->query('SELECT COUNT(*) FROM organizations')->fetchColumn() === 0) {
        $db->exec("INSERT INTO organizations (name) VALUES
            ('Linexa Demo Org'),
            ('Internal Platform')");
    }
    if ((int)$db->query('SELECT COUNT(*) FROM tenants')->fetchColumn() === 0) {
        $orgA = (int)$db->query('SELECT id FROM organizations ORDER BY id LIMIT 1')->fetchColumn();
        $orgB = (int)$db->query('SELECT id FROM organizations ORDER BY id LIMIT 1 OFFSET 1')->fetchColumn();
        $db->exec("INSERT INTO tenants (org_id, name, namespace, url) VALUES
            ($orgA, 'Tenant cfd4486', 'linexa-tenant-cfd4486', 'http://dev.tenant-cfd4486.linexa.eu'),
            ($orgA, 'Tenant 3622fab', 'linexa-tenant-3622fab', 'http://dev.tenant-3622fab.linexa.eu'),
            ($orgB, 'Tenant 3bf9bd5', 'linexa-tenant-3bf9bd5', 'http://dev.tenant-3bf9bd5.linexa.eu')");
    }
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $db->exec("INSERT INTO users (name, email) VALUES
            ('Admin',   'admin@linexa.eu'),
            ('Manager', 'manager@linexa.eu')");
    }
}

// ── Toggle enable/disable ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id']) && $db) {
    $tid  = (int)$_POST['tenant_id'];
    $stmt = $db->prepare('UPDATE tenants SET enabled = IF(enabled = 1, 0, 1) WHERE id = ?');
    $stmt->execute([$tid]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Generate a unique tenant ID ───────────────────────────────────────────────
function generateTenantId(PDO $db): string {
    do {
        $id = bin2hex(random_bytes(4)); // 7-char hex, e.g. a1b2c3d4 → trimmed to 7
        $id = substr($id, 0, 7);
        $chk = $db->prepare('SELECT COUNT(*) FROM tenants WHERE namespace = ?');
        $chk->execute(['linexa-tenant-' . $id]);
    } while ((int)$chk->fetchColumn() > 0);
    return $id;
}
$generatedId = $db ? generateTenantId($db) : substr(bin2hex(random_bytes(4)), 0, 7);

// ── Create new tenant ─────────────────────────────────────────────────────────
$createSuccess = null;
$createError   = null;
$createdTenant = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_tenant' && $db) {
    $tenantId = $_POST['tenant_slug'] ?? '';
    $orgId    = (int)($_POST['org_id'] ?? 0);
    $name     = trim($_POST['tenant_name'] ?? '') ?: 'Tenant ' . $tenantId;

    $ns  = 'linexa-tenant-' . $tenantId;
    $url = 'http://dev.tenant-' . $tenantId . '.linexa.eu';

    // re-verify uniqueness on submit (race-condition guard)
    $chk = $db->prepare('SELECT COUNT(*) FROM tenants WHERE namespace = ?');
    $chk->execute([$ns]);
    if ((int)$chk->fetchColumn() > 0) {
        // collision — regenerate and show error so user can resubmit
        $createError = 'ID collision on submit — a new ID has been generated. Please submit again.';
        $generatedId = generateTenantId($db);
    } elseif ($orgId <= 0) {
        $createError = 'Please select an organization.';
    } else {
        $ins = $db->prepare('INSERT INTO tenants (org_id, name, namespace, url, enabled) VALUES (?, ?, ?, ?, 1)');
        $ins->execute([$orgId, $name, $ns, $url]);
        $createSuccess = 'Tenant created in database.';
        $createdTenant = ['slug' => $tenantId, 'namespace' => $ns, 'url' => $url, 'name' => $name];
        $generatedId   = $db ? generateTenantId($db) : substr(bin2hex(random_bytes(4)), 0, 7);
    }
}

// ── Fetch orgs → tenants ──────────────────────────────────────────────────────
$orgs    = [];
$orgList = [];   // for select dropdown
if ($db) {
    foreach ($db->query('SELECT * FROM organizations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $org) {
        $orgList[] = $org;
        $stmt = $db->prepare('SELECT * FROM tenants WHERE org_id = ? ORDER BY id');
        $stmt->execute([$org['id']]);
        $org['tenants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orgs[]         = $org;
    }
}

// ── Own users ─────────────────────────────────────────────────────────────────
$ownUsers = [];
$ownError = null;
$ownQuery = 'SELECT id, name, email, created_at FROM users ORDER BY id';
if ($db) {
    try {
        $ownUsers = $db->query($ownQuery)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $ownError = $e->getMessage();
    }
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
    .wrap { max-width: 860px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }

    /* ── Header ── */
    .header { background: linear-gradient(135deg, #1e293b 0%, #0f2038 100%);
              border: 1px solid #334155; border-radius: 12px; padding: 2rem; text-align: center; }
    .badge  { font-family: monospace; font-size: .75rem; color: #38bdf8;
              background: #0c1a2e; border: 1px solid #0369a1; border-radius: 6px;
              padding: .2rem .75rem; display: inline-block; margin-bottom: 1rem; }
    h1   { font-size: 1.75rem; font-weight: 700; margin-bottom: .25rem; }
    .meta { font-family: monospace; font-size: .8rem; color: #64748b; }

    /* ── Cards ── */
    .card       { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 1.25rem; }
    .card-title { font-size: .7rem; font-weight: 700; letter-spacing: .08em;
                  text-transform: uppercase; color: #64748b; margin-bottom: 1rem; }

    /* ── Org section ── */
    .org-block  { display: flex; flex-direction: column; gap: .75rem; }
    .org-header { display: flex; align-items: center; gap: .6rem; margin-bottom: .25rem; }
    .org-name   { font-size: .9rem; font-weight: 600; color: #cbd5e1; }
    .org-badge  { font-family: monospace; font-size: .65rem; color: #94a3b8;
                  background: #0f172a; border: 1px solid #334155; border-radius: 4px;
                  padding: .1rem .4rem; }
    .org-divider { border: none; border-top: 1px solid #1e3050; margin: .5rem 0 1rem; }

    /* ── Tenant table ── */
    table { width: 100%; border-collapse: collapse; }
    th    { text-align: left; padding: .45rem .75rem; color: #64748b;
            font-size: .68rem; text-transform: uppercase; letter-spacing: .07em;
            border-bottom: 1px solid #334155; }
    td    { padding: .55rem .75rem; border-bottom: 1px solid #1a2540;
            font-size: .82rem; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .col-name { font-weight: 500; color: #e2e8f0; }
    .col-ns   { font-family: monospace; font-size: .75rem; color: #7dd3fc; }
    .col-url a { font-family: monospace; font-size: .75rem; color: #60a5fa; text-decoration: none; }
    .col-url a:hover { text-decoration: underline; }

    /* ── Status pill ── */
    .pill         { display: inline-block; font-size: .65rem; font-weight: 700;
                    padding: .15rem .55rem; border-radius: 99px; white-space: nowrap; }
    .pill-enabled  { background: #14532d; color: #4ade80; border: 1px solid #166534; }
    .pill-disabled { background: #1c1917; color: #78716c; border: 1px solid #44403c; }

    /* ── Toggle button ── */
    .btn-enable  { font-size: .7rem; font-weight: 600; padding: .25rem .65rem;
                   border-radius: 6px; border: 1px solid #166534; background: #14532d;
                   color: #4ade80; cursor: pointer; }
    .btn-enable:hover  { background: #166534; }
    .btn-disable { font-size: .7rem; font-weight: 600; padding: .25rem .65rem;
                   border-radius: 6px; border: 1px solid #7f1d1d; background: #451a1a;
                   color: #f87171; cursor: pointer; }
    .btn-disable:hover { background: #7f1d1d; }

    /* ── New tenant form ── */
    .new-tenant-bar { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
    .btn-new { font-size: .75rem; font-weight: 600; padding: .3rem .8rem;
               border-radius: 6px; border: 1px solid #0369a1; background: #0c1a2e;
               color: #38bdf8; cursor: pointer; display: flex; align-items: center; gap: .35rem; }
    .btn-new:hover { background: #0f2644; border-color: #38bdf8; }

    .new-tenant-form { background: #0f172a; border: 1px solid #334155; border-radius: 8px;
                       padding: 1rem 1.25rem; margin-bottom: 1rem; display: none; }
    .new-tenant-form.open { display: block; }
    .form-row  { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .75rem; margin-bottom: .75rem; }
    .form-group label { display: block; font-size: .68rem; font-weight: 700;
                        letter-spacing: .07em; text-transform: uppercase; color: #64748b;
                        margin-bottom: .35rem; }
    .form-group input,
    .form-group select { width: 100%; background: #1e293b; border: 1px solid #334155;
                          border-radius: 6px; padding: .4rem .65rem; font-size: .82rem;
                          color: #e2e8f0; font-family: inherit; }
    .form-group input:focus,
    .form-group select:focus { outline: none; border-color: #38bdf8; }
    .form-group input::placeholder { color: #475569; }
    .form-actions { display: flex; gap: .5rem; justify-content: flex-end; }
    .btn-submit { font-size: .75rem; font-weight: 600; padding: .35rem .9rem;
                  border-radius: 6px; border: 1px solid #0369a1; background: #0c4a6e;
                  color: #38bdf8; cursor: pointer; }
    .btn-submit:hover { background: #075985; }
    .btn-cancel { font-size: .75rem; font-weight: 600; padding: .35rem .9rem;
                  border-radius: 6px; border: 1px solid #334155; background: transparent;
                  color: #64748b; cursor: pointer; }
    .btn-cancel:hover { border-color: #64748b; color: #94a3b8; }

    /* ── Create result ── */
    .create-result { border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
    .create-result.success { background: #022c22; border: 1px solid #065f46; }
    .create-result.error   { background: #1c0a00; border: 1px solid #7f1d1d; }
    .create-result-title { font-size: .8rem; font-weight: 700; margin-bottom: .5rem; }
    .create-result.success .create-result-title { color: #34d399; }
    .create-result.error   .create-result-title { color: #f87171; }
    .deploy-cmds { font-family: monospace; font-size: .75rem; background: #0f172a;
                   border: 1px solid #1e3050; border-radius: 6px; padding: .75rem 1rem;
                   color: #94a3b8; margin-top: .6rem; white-space: pre; overflow-x: auto;
                   line-height: 1.7; }
    .deploy-cmds .cmd { color: #e2e8f0; }
    .deploy-cmds .cmt { color: #475569; }

    /* ── Query block ── */
    .query { font-family: monospace; font-size: .8rem; background: #0f172a;
             border: 1px solid #334155; border-radius: 6px; padding: .6rem .9rem;
             color: #7dd3fc; margin-bottom: .75rem; overflow-x: auto; white-space: nowrap; }

    /* ── Isolation rows ── */
    .iso-row { display: flex; align-items: flex-start; gap: .75rem;
               padding: .6rem 0; border-bottom: 1px solid #1e3050; }
    .iso-row:last-child { border-bottom: none; }
    .pill-blocked { background: #451a1a; color: #f87171; border: 1px solid #7f1d1d; }
    .pill-ok      { background: #14532d; color: #4ade80; border: 1px solid #166534; }
    .iso-ns  { font-family: monospace; font-size: .8rem; color: #cbd5e1; }
    .iso-err { font-family: monospace; font-size: .72rem; color: #94a3b8; margin-top: .25rem; word-break: break-all; }

    /* ── Users table ── */
    .users-td { font-family: monospace; font-size: .8rem; }
    tr.user-row td { padding: .45rem .6rem; border-bottom: 1px solid #1e293b; }
    tr.user-row:last-child td { border-bottom: none; }

    .error { color: #f87171; font-family: monospace; font-size: .8rem; }
  </style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="header">
    <div class="badge">control-plane · <?= $namespace ?></div>
    <h1>Linexa Control Plane</h1>
    <p class="meta"><?= $now->format('Y-m-d H:i:s') ?> UTC &nbsp;·&nbsp; own pod &nbsp;·&nbsp; own namespace &nbsp;·&nbsp; own MySQL &nbsp;·&nbsp; <span style="color:#38bdf8"><?= htmlspecialchars(getenv('IMAGE_TAG') ?: 'dev') ?></span></p>
  </div>

  <?php if ($dbError): ?>
  <div class="card"><p class="error">⚠ Database error: <?= htmlspecialchars($dbError) ?></p></div>
  <?php endif; ?>

  <!-- ── Tenant management ── -->
  <div class="card">
    <div class="card-title">Organizations &amp; Tenants</div>

    <!-- New tenant button -->
    <div class="new-tenant-bar">
      <button class="btn-new" onclick="toggleForm()">
        <span>＋</span> New Tenant
      </button>
    </div>

    <!-- Create error -->
    <?php if ($createError): ?>
    <div class="create-result error">
      <div class="create-result-title">⚠ <?= htmlspecialchars($createError) ?></div>
    </div>
    <?php endif; ?>

    <!-- Create success + deploy commands -->
    <?php if ($createSuccess && $createdTenant): $t = $createdTenant; ?>
    <div class="create-result success">
      <div class="create-result-title">✓ <?= htmlspecialchars($t['name']) ?> added to database</div>
      <div style="font-size:.78rem;color:#6ee7b7;margin-bottom:.5rem">
        Now deploy the namespace, NetworkPolicy, and Helm release on the bastion:
      </div>
      <div class="deploy-cmds"><span class="cmt"># ① namespace + NetworkPolicies</span>
<span class="cmd">cp k8s/namespaces/tenant-cfd4486.yaml k8s/namespaces/tenant-<?= $t['slug'] ?>.yaml</span>
<span class="cmt"># replace cfd4486 → <?= $t['slug'] ?> in that file, then:</span>
<span class="cmd">kubectl apply -f k8s/namespaces/tenant-<?= $t['slug'] ?>.yaml</span>
<span class="cmd">kubectl apply -f k8s/network-policies/tenant-isolation.yaml -n <?= $t['namespace'] ?></span>

<span class="cmt"># ② build + push image</span>
<span class="cmd">TAG=0.0.1
docker build -t europe-west1-docker.pkg.dev/replenit-lab/linexa/tenant-<?= $t['slug'] ?>:$TAG \
  app/tenants/<?= $t['slug'] ?>
docker push europe-west1-docker.pkg.dev/replenit-lab/linexa/tenant-<?= $t['slug'] ?>:$TAG</span>

<span class="cmt"># ③ helm deploy</span>
<span class="cmd">helm upgrade --install tenant-<?= $t['slug'] ?> ./helm/tenant \
  -n <?= $t['namespace'] ?> \
  --set tenantId=<?= $t['slug'] ?> \
  --set image.repository=europe-west1-docker.pkg.dev/replenit-lab/linexa/tenant-<?= $t['slug'] ?> \
  --set image.tag=$TAG</span>

<span class="cmt"># ④ add to hosts file (PowerShell Admin)</span>
<span class="cmd">kubectl get ingress -n <?= $t['namespace'] ?>   # get LB IP
Add-Content "C:\Windows\System32\drivers\etc\hosts" "&lt;LB_IP&gt;  dev.tenant-<?= $t['slug'] ?>.linexa.eu"</span></div>
    </div>
    <?php endif; ?>

    <!-- New tenant form (hidden by default) -->
    <div class="new-tenant-form" id="newTenantForm">
      <form method="POST">
        <input type="hidden" name="action" value="create_tenant">
        <input type="hidden" name="tenant_slug" value="<?= htmlspecialchars($generatedId) ?>">
        <div class="form-row">
          <div class="form-group">
            <label>Tenant ID</label>
            <div style="display:flex;align-items:center;gap:.5rem;height:2rem">
              <span style="font-family:monospace;font-size:.85rem;color:#38bdf8;background:#0c1a2e;
                           border:1px solid #0369a1;border-radius:6px;padding:.3rem .75rem;
                           letter-spacing:.05em"><?= htmlspecialchars($generatedId) ?></span>
              <span style="font-size:.68rem;color:#475569">auto-generated · unique</span>
            </div>
          </div>
          <div class="form-group">
            <label for="org">Organization</label>
            <select id="org" name="org_id" required>
              <option value="">— select —</option>
              <?php foreach ($orgList as $o): ?>
              <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="tname">Display name</label>
            <input type="text" id="tname" name="tenant_name" placeholder="optional, auto-generated">
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="toggleForm()">Cancel</button>
          <button type="submit" class="btn-submit">Create tenant</button>
        </div>
      </form>
    </div>

    <?php if (empty($orgs)): ?>
      <p style="color:#64748b;font-size:.85rem">No organizations yet.</p>
    <?php else: ?>
      <div class="org-block">
        <?php foreach ($orgs as $orgIdx => $org): ?>
          <?php if ($orgIdx > 0): ?><hr class="org-divider"><?php endif; ?>
          <div class="org-header">
            <span class="org-name"><?= htmlspecialchars($org['name']) ?></span>
            <span class="org-badge">org #<?= $org['id'] ?></span>
            <span class="org-badge"><?= count($org['tenants']) ?> tenant<?= count($org['tenants']) !== 1 ? 's' : '' ?></span>
          </div>

          <?php if (empty($org['tenants'])): ?>
            <p style="color:#64748b;font-size:.82rem;padding-left:.25rem">No tenants in this organization.</p>
          <?php else: ?>
          <div style="overflow-x:auto">
          <table>
            <thead>
              <tr>
                <th>Tenant</th>
                <th>Namespace</th>
                <th>URL</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($org['tenants'] as $tenant): ?>
              <tr>
                <td class="col-name"><?= htmlspecialchars($tenant['name']) ?></td>
                <td class="col-ns"><?= htmlspecialchars($tenant['namespace']) ?></td>
                <td class="col-url">
                  <?php if ($tenant['enabled']): ?>
                    <a href="<?= htmlspecialchars($tenant['url']) ?>" target="_blank"><?= htmlspecialchars($tenant['url']) ?></a>
                  <?php else: ?>
                    <span style="color:#4b5563"><?= htmlspecialchars($tenant['url']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="pill <?= $tenant['enabled'] ? 'pill-enabled' : 'pill-disabled' ?>">
                    <?= $tenant['enabled'] ? 'Enabled' : 'Disabled' ?>
                  </span>
                </td>
                <td>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="tenant_id" value="<?= (int)$tenant['id'] ?>">
                    <?php if ($tenant['enabled']): ?>
                      <button type="submit" class="btn-disable">Disable</button>
                    <?php else: ?>
                      <button type="submit" class="btn-enable">Enable</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Own users ── -->
  <div class="card">
    <div class="card-title">Control plane database — mysql.<?= $namespace ?>.svc.cluster.local</div>
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
          <tr class="user-row">
            <td class="users-td"><?= $row['id'] ?></td>
            <td class="users-td"><?= htmlspecialchars($row['name']) ?></td>
            <td class="users-td"><?= htmlspecialchars($row['email']) ?></td>
            <td class="users-td"><?= $row['created_at'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ── Isolation test ── -->
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
<script>
function toggleForm() {
  const f = document.getElementById('newTenantForm');
  f.classList.toggle('open');
  if (f.classList.contains('open')) {
    document.getElementById('org').focus();
  }
}
// auto-open form if there was a validation error
<?php if ($createError): ?>
document.getElementById('newTenantForm').classList.add('open');
<?php endif; ?>
</script>
</body>
</html>
