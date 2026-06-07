<?php
/**
 * BantayPurrPaws — Admin Security Dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
requireAdmin();

$tab = $_GET['tab'] ?? 'attempts';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'block_ip' && !empty($_POST['ip'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO blocked_ips (ip_address, reason, blocked_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), blocked_by=VALUES(blocked_by), created_at=NOW()");
        $stmt->execute([sanitize($_POST['ip']), sanitize($_POST['reason'] ?? 'Manual block'), currentUser()['id']]);
        sec_log_event('ip_blocked', 'IP manually blocked: ' . $_POST['ip'], 'warning', (int)currentUser()['id']);
        header('Location: ' . url('admin/security.php?tab=blocked&msg=blocked')); exit;
    }
    if ($act === 'unblock_ip' && !empty($_POST['ip'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
        $stmt->execute([sanitize($_POST['ip'])]);
        header('Location: ' . url('admin/security.php?tab=blocked&msg=unblocked')); exit;
    }
    if ($act === 'force_logout' && !empty($_POST['user_id'])) {
        sec_invalidate_user_sessions((int)$_POST['user_id']);
        sec_log_event('force_logout', 'Admin forced logout for user ' . $_POST['user_id'], 'warning', (int)currentUser()['id']);
        header('Location: ' . url('admin/security.php?tab=sessions&msg=logged_out')); exit;
    }
    if ($act === 'unlock_account' && !empty($_POST['user_id'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET failed_login_count=0, locked_until=NULL WHERE id=?");
        $stmt->execute([(int)$_POST['user_id']]);
        sec_log_event('account_unlocked', 'Admin unlocked account for user ' . $_POST['user_id'], 'info', (int)currentUser()['id']);
        header('Location: ' . url('admin/security.php?tab=attempts&msg=unlocked')); exit;
    }
}

// Data
$pdo = getDB();

// Stats
$totalAttempts  = $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
$suspicious     = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE is_suspicious = 1")->fetchColumn();
$blockedIps     = $pdo->query("SELECT COUNT(*) FROM blocked_ips")->fetchColumn();
$activeSessions = $pdo->query("SELECT COUNT(*) FROM user_sessions WHERE is_active = 1 AND expires_at > NOW()")->fetchColumn();
$lockedAccounts = $pdo->query("SELECT COUNT(*) FROM users WHERE locked_until > NOW()")->fetchColumn();
$todayEvents    = $pdo->query("SELECT COUNT(*) FROM security_events WHERE DATE(created_at) = CURDATE()")->fetchColumn();

$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.sec-tabs{display:flex;gap:4px;margin-bottom:24px;background:var(--surface-2);padding:4px;border-radius:10px;border:1px solid var(--border);}
.sec-tab{flex:1;padding:8px 4px;text-align:center;font-size:0.8rem;font-weight:600;color:var(--text-muted);border-radius:7px;cursor:pointer;text-decoration:none;transition:all .2s;}
.sec-tab.active{background:var(--surface-1);color:var(--accent);box-shadow:0 1px 4px rgba(0,0,0,0.1);}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px;}
.stat-card{background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;}
.stat-num{font-size:1.8rem;font-weight:900;color:var(--text-primary);line-height:1;}
.stat-label{font-size:0.75rem;color:var(--text-muted);margin-top:4px;}
.stat-card.danger{border-color:rgba(239,68,68,0.4);background:rgba(239,68,68,0.05);}
.stat-card.danger .stat-num{color:#ef4444;}
.stat-card.success .stat-num{color:#10b981;}
.risk-badge{padding:2px 8px;border-radius:999px;font-size:0.72rem;font-weight:700;}
.risk-low{background:rgba(16,185,129,0.12);color:#10b981;}
.risk-medium{background:rgba(245,158,11,0.12);color:#d97706;}
.risk-high{background:rgba(239,68,68,0.12);color:#ef4444;}
.risk-critical{background:rgba(139,0,0,0.18);color:#dc2626;}
.status-badge-sm{padding:2px 8px;border-radius:999px;font-size:0.72rem;font-weight:600;}
.sb-verified{background:rgba(16,185,129,0.12);color:#10b981;}
.sb-failed{background:rgba(239,68,68,0.12);color:#ef4444;}
.sb-pending{background:rgba(245,158,11,0.12);color:#d97706;}
.sb-denied{background:rgba(139,0,0,0.12);color:#dc2626;}
.sb-suspicious{background:rgba(168,85,247,0.12);color:#7c3aed;}
.sb-blocked{background:rgba(239,68,68,0.18);color:#b91c1c;}
.data-table{width:100%;border-collapse:collapse;font-size:0.82rem;}
.data-table th{padding:10px 12px;text-align:left;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--border);font-size:0.75rem;text-transform:uppercase;letter-spacing:.03em;}
.data-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-primary);}
.data-table tr:hover td{background:var(--surface-2);}
.btn-sm{padding:5px 12px;font-size:0.78rem;border-radius:6px;cursor:pointer;border:1px solid var(--border);background:var(--surface-2);color:var(--text-primary);}
.btn-sm.danger{background:rgba(239,68,68,0.1);color:#ef4444;border-color:rgba(239,68,68,0.3);}
.btn-sm.success{background:rgba(16,185,129,0.1);color:#10b981;border-color:rgba(16,185,129,0.3);}
.scroll-table{overflow-x:auto;}
</style>

<div class="container" style="max-width:1200px;padding:24px 16px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <span style="font-size:28px;">🛡️</span>
        <div>
            <h1 style="margin:0;font-size:1.4rem;">Security Dashboard</h1>
            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">Monitor login attempts, threats & active sessions</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= in_array($msg, ['blocked','logged_out']) ? 'error' : 'success' ?>" style="margin-bottom:16px;">
        <?= match($msg) { 'blocked'=>'IP blocked successfully.', 'unblocked'=>'IP unblocked.', 'logged_out'=>'User sessions terminated.', 'unlocked'=>'Account unlocked.', default=>'Done.' } ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-num"><?= $totalAttempts ?></div><div class="stat-label">Total Attempts</div></div>
        <div class="stat-card danger"><div class="stat-num"><?= $suspicious ?></div><div class="stat-label">Suspicious</div></div>
        <div class="stat-card danger"><div class="stat-num"><?= $blockedIps ?></div><div class="stat-label">Blocked IPs</div></div>
        <div class="stat-card success"><div class="stat-num"><?= $activeSessions ?></div><div class="stat-label">Active Sessions</div></div>
        <div class="stat-card <?= $lockedAccounts > 0 ? 'danger' : '' ?>"><div class="stat-num"><?= $lockedAccounts ?></div><div class="stat-label">Locked Accounts</div></div>
        <div class="stat-card"><div class="stat-num"><?= $todayEvents ?></div><div class="stat-label">Events Today</div></div>
    </div>

    <!-- Tabs -->
    <div class="sec-tabs">
        <?php foreach (['attempts'=>'Login Attempts','suspicious'=>'Suspicious','blocked'=>'Blocked IPs','sessions'=>'Sessions','events'=>'Audit Log'] as $t=>$label): ?>
        <a href="?tab=<?= $t ?>" class="sec-tab <?= $tab===$t?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'attempts'): ?>
    <div class="scroll-table">
    <?php
    $rows = $pdo->query("SELECT la.*, u.full_name FROM login_attempts la LEFT JOIN users u ON u.id = la.user_id ORDER BY la.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="data-table">
        <thead><tr><th>Time</th><th>Email</th><th>User</th><th>IP</th><th>Browser / OS</th><th>Risk</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= date('M j H:i', strtotime($r['created_at'])) ?></td>
            <td><?= sanitize($r['email']) ?></td>
            <td><?= sanitize($r['full_name'] ?? '—') ?></td>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']) ?></td>
            <td><?= sanitize($r['browser']??'') ?> / <?= sanitize($r['os']??'') ?></td>
            <td><span class="risk-badge risk-<?= $r['risk_level'] ?>"><?= strtoupper($r['risk_level']) ?></span></td>
            <td><span class="status-badge-sm sb-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span><?php if($r['is_suspicious']): ?> <span style="color:#f59e0b;" title="Suspicious">⚠</span><?php endif; ?></td>
            <td>
                <?php if ($r['user_id']): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="unlock_account">
                    <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                    <button class="btn-sm success" onclick="return confirm('Unlock this account?')">Unlock</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="block_ip">
                    <input type="hidden" name="ip" value="<?= sanitize($r['ip_address']) ?>">
                    <input type="hidden" name="reason" value="Manual block from dashboard">
                    <button class="btn-sm danger" onclick="return confirm('Block IP <?= sanitize($r['ip_address']) ?>?')">Block IP</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($tab === 'suspicious'): ?>
    <div class="scroll-table">
    <?php $rows = $pdo->query("SELECT la.*, u.full_name FROM login_attempts la LEFT JOIN users u ON u.id=la.user_id WHERE la.is_suspicious=1 ORDER BY la.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); ?>
    <table class="data-table">
        <thead><tr><th>Time</th><th>Email</th><th>IP</th><th>Browser</th><th>Risk</th><th>Status</th><th>Block IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= date('M j H:i', strtotime($r['created_at'])) ?></td>
            <td><?= sanitize($r['email']) ?></td>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']) ?></td>
            <td><?= sanitize($r['browser']??'') ?></td>
            <td><span class="risk-badge risk-<?= $r['risk_level'] ?>"><?= strtoupper($r['risk_level']) ?></span></td>
            <td><span class="status-badge-sm sb-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="block_ip">
                    <input type="hidden" name="ip" value="<?= sanitize($r['ip_address']) ?>">
                    <input type="hidden" name="reason" value="Suspicious login attempt">
                    <button class="btn-sm danger" onclick="return confirm('Block this IP?')">Block IP</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($tab === 'blocked'): ?>
    <div style="margin-bottom:16px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
        <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="action" value="block_ip">
            <input type="text" name="ip" class="form-control" placeholder="IP Address" style="width:200px;" required>
            <input type="text" name="reason" class="form-control" placeholder="Reason" style="width:200px;">
            <button type="submit" class="btn btn-accent">Block IP</button>
        </form>
    </div>
    <div class="scroll-table">
    <?php $rows = $pdo->query("SELECT b.*, u.full_name as blocked_by_name FROM blocked_ips b LEFT JOIN users u ON u.id=b.blocked_by ORDER BY b.created_at DESC")->fetchAll(PDO::FETCH_ASSOC); ?>
    <table class="data-table">
        <thead><tr><th>IP Address</th><th>Reason</th><th>Risk Score</th><th>Blocked By</th><th>Blocked At</th><th>Expires</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']) ?></td>
            <td><?= sanitize($r['reason']??'—') ?></td>
            <td><?= (int)$r['risk_score'] ?></td>
            <td><?= sanitize($r['blocked_by_name']??'System') ?></td>
            <td><?= date('M j, Y H:i', strtotime($r['created_at'])) ?></td>
            <td><?= $r['expires_at'] ? date('M j, Y', strtotime($r['expires_at'])) : 'Never' ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="unblock_ip">
                    <input type="hidden" name="ip" value="<?= sanitize($r['ip_address']) ?>">
                    <button class="btn-sm success" onclick="return confirm('Unblock this IP?')">Unblock</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($tab === 'sessions'): ?>
    <div class="scroll-table">
    <?php $rows = $pdo->query("SELECT us.*, u.full_name, u.email FROM user_sessions us JOIN users u ON u.id=us.user_id WHERE us.is_active=1 AND us.expires_at > NOW() ORDER BY us.last_activity DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); ?>
    <table class="data-table">
        <thead><tr><th>User</th><th>Email</th><th>IP</th><th>Browser / OS</th><th>Last Active</th><th>Expires</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= sanitize($r['full_name']) ?></td>
            <td><?= sanitize($r['email']) ?></td>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']??'') ?></td>
            <td><?= sanitize($r['browser']??'') ?> / <?= sanitize($r['os']??'') ?></td>
            <td><?= date('M j H:i', strtotime($r['last_activity'])) ?></td>
            <td><?= date('M j H:i', strtotime($r['expires_at'])) ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="force_logout">
                    <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                    <button class="btn-sm danger" onclick="return confirm('Force logout all sessions for this user?')">Force Logout</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($tab === 'events'): ?>
    <div class="scroll-table">
    <?php $rows = $pdo->query("SELECT se.*, u.full_name FROM security_events se LEFT JOIN users u ON u.id=se.user_id ORDER BY se.created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); ?>
    <table class="data-table">
        <thead><tr><th>Time</th><th>Event</th><th>Severity</th><th>User</th><th>IP</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= date('M j H:i', strtotime($r['created_at'])) ?></td>
            <td style="font-family:monospace;font-size:0.75rem;"><?= sanitize($r['event_type']) ?></td>
            <td><span class="risk-badge risk-<?= $r['severity']==='critical'?'critical':($r['severity']==='warning'?'high':'low') ?>"><?= strtoupper($r['severity']) ?></span></td>
            <td><?= sanitize($r['full_name']??'—') ?></td>
            <td style="font-family:monospace;font-size:0.75rem;"><?= sanitize($r['ip_address']??'') ?></td>
            <td><?= sanitize($r['description']??'') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
