<?php
/**
 * BantayPurrPaws — User Security Settings
 * Login history, trusted devices, active sessions
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
requireLogin();

$user = currentUser();
$uid  = (int)$user['id'];
$pdo  = getDB();
$tab  = $_GET['tab'] ?? 'history';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'remove_device' && !empty($_POST['device_id'])) {
        $stmt = $pdo->prepare("DELETE FROM trusted_devices WHERE id=? AND user_id=?");
        $stmt->execute([(int)$_POST['device_id'], $uid]);
        header('Location: ' . url('security-settings.php?tab=devices&msg=removed')); exit;
    }
    if ($act === 'logout_session' && !empty($_POST['session_id'])) {
        $stmt = $pdo->prepare("UPDATE user_sessions SET is_active=0 WHERE id=? AND user_id=?");
        $stmt->execute([(int)$_POST['session_id'], $uid]);
        header('Location: ' . url('security-settings.php?tab=sessions&msg=logged_out')); exit;
    }
    if ($act === 'logout_all') {
        sec_invalidate_user_sessions($uid);
        sec_log_event('logout_all_sessions', 'User logged out all sessions', 'info', $uid);
        header('Location: ' . url('logout.php')); exit;
    }
}

$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>
<style>
.sec-tabs{display:flex;gap:4px;margin-bottom:24px;background:var(--surface-2);padding:4px;border-radius:10px;border:1px solid var(--border);}
.sec-tab{flex:1;padding:8px 4px;text-align:center;font-size:0.82rem;font-weight:600;color:var(--text-muted);border-radius:7px;cursor:pointer;text-decoration:none;transition:all .2s;}
.sec-tab.active{background:var(--surface-1);color:var(--accent);box-shadow:0 1px 4px rgba(0,0,0,0.1);}
.data-table{width:100%;border-collapse:collapse;font-size:0.82rem;}
.data-table th{padding:10px 12px;text-align:left;color:var(--text-muted);font-weight:600;border-bottom:1px solid var(--border);font-size:0.75rem;text-transform:uppercase;}
.data-table td{padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-primary);}
.data-table tr:hover td{background:var(--surface-2);}
.risk-badge{padding:2px 8px;border-radius:999px;font-size:0.72rem;font-weight:700;}
.risk-low{background:rgba(16,185,129,0.12);color:#10b981;}
.risk-medium{background:rgba(245,158,11,0.12);color:#d97706;}
.risk-high{background:rgba(239,68,68,0.12);color:#ef4444;}
.risk-critical{background:rgba(139,0,0,0.18);color:#dc2626;}
.status-badge-sm{padding:2px 8px;border-radius:999px;font-size:0.72rem;font-weight:600;}
.sb-verified,.sb-success{background:rgba(16,185,129,0.12);color:#10b981;}
.sb-failed,.sb-denied{background:rgba(239,68,68,0.12);color:#ef4444;}
.sb-pending{background:rgba(245,158,11,0.12);color:#d97706;}
.btn-sm{padding:5px 12px;font-size:0.78rem;border-radius:6px;cursor:pointer;border:1px solid var(--border);background:var(--surface-2);color:var(--text-primary);}
.btn-sm.danger{background:rgba(239,68,68,0.1);color:#ef4444;border-color:rgba(239,68,68,0.3);}
</style>

<div class="container" style="max-width:960px;padding:24px 16px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <span style="font-size:28px;">🔐</span>
        <div>
            <h1 style="margin:0;font-size:1.4rem;">Security Settings</h1>
            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">Manage your login history, trusted devices & sessions</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:16px;"><?= match($msg) { 'removed'=>'Device removed.', 'logged_out'=>'Session terminated.', default=>'Done.' } ?></div>
    <?php endif; ?>

    <div class="sec-tabs">
        <a href="?tab=history"  class="sec-tab <?= $tab==='history' ?'active':'' ?>">Login History</a>
        <a href="?tab=devices"  class="sec-tab <?= $tab==='devices' ?'active':'' ?>">Trusted Devices</a>
        <a href="?tab=sessions" class="sec-tab <?= $tab==='sessions'?'active':'' ?>">Active Sessions</a>
    </div>

    <?php if ($tab === 'history'): ?>
    <?php $rows = $pdo->prepare("SELECT * FROM login_attempts WHERE user_id=? ORDER BY created_at DESC LIMIT 50"); $rows->execute([$uid]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC); ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Date & Time</th><th>IP Address</th><th>Browser</th><th>OS</th><th>Risk</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></td>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']) ?></td>
            <td><?= sanitize($r['browser']??'Unknown') ?></td>
            <td><?= sanitize($r['os']??'Unknown') ?></td>
            <td><span class="risk-badge risk-<?= $r['risk_level'] ?>"><?= strtoupper($r['risk_level']) ?></span></td>
            <td>
                <span class="status-badge-sm sb-<?= in_array($r['status'],['verified','approved'])?'verified':'failed' ?>">
                    <?= ucfirst($r['status']) ?>
                </span>
                <?php if($r['is_suspicious']): ?> <span style="color:#f59e0b;" title="Flagged suspicious">⚠</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">No login history found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($tab === 'devices'): ?>
    <?php $rows = $pdo->prepare("SELECT * FROM trusted_devices WHERE user_id=? ORDER BY last_used_at DESC"); $rows->execute([$uid]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC); ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Device</th><th>Browser</th><th>OS</th><th>IP</th><th>Last Used</th><th>Expires</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= sanitize($r['device_name']??'Unknown') ?></td>
            <td><?= sanitize($r['browser']??'') ?></td>
            <td><?= sanitize($r['os']??'') ?></td>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']??'') ?></td>
            <td><?= date('M j, Y', strtotime($r['last_used_at'])) ?></td>
            <td><?= $r['expires_at'] ? date('M j, Y', strtotime($r['expires_at'])) : 'Never' ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="remove_device">
                    <input type="hidden" name="device_id" value="<?= $r['id'] ?>">
                    <button class="btn-sm danger" onclick="return confirm('Remove this trusted device?')">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:24px;">No trusted devices found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($tab === 'sessions'): ?>
    <?php $rows = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id=? AND is_active=1 AND expires_at>NOW() ORDER BY last_activity DESC"); $rows->execute([$uid]); $rows=$rows->fetchAll(PDO::FETCH_ASSOC); ?>
    <div style="margin-bottom:12px;text-align:right;">
        <form method="POST">
            <input type="hidden" name="action" value="logout_all">
            <button type="submit" class="btn btn-ghost" style="font-size:0.85rem;" onclick="return confirm('Log out of ALL sessions?')">Log Out All Devices</button>
        </form>
    </div>
    <div style="overflow-x:auto;">
    <table class="data-table">
        <thead><tr><th>Browser</th><th>OS</th><th>IP</th><th>Last Active</th><th>Expires</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= sanitize($r['browser']??'Unknown') ?></td>
            <td><?= sanitize($r['os']??'Unknown') ?></td>
            <td style="font-family:monospace;"><?= sanitize($r['ip_address']??'') ?></td>
            <td><?= date('M j H:i', strtotime($r['last_activity'])) ?></td>
            <td><?= date('M j H:i', strtotime($r['expires_at'])) ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="logout_session">
                    <input type="hidden" name="session_id" value="<?= $r['id'] ?>">
                    <button class="btn-sm danger" onclick="return confirm('End this session?')">End Session</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">No active sessions found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
