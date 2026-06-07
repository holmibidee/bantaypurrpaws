<?php
/**
 * BantayPurrPaws — Login Challenge Handler
 *
 * User clicks "Yes, It's Me" or "No, It's Not Me" from their email.
 * GET ?action=approve&token=xxx   → shows number matching UI
 * GET ?action=deny&token=xxx      → blocks attempt + shows warning
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mailer_security.php';
require_once __DIR__ . '/../includes/users.php';
startSession();

$action = $_GET['action'] ?? '';
$token  = $_GET['token']  ?? '';

// Validate token
$attempt = $token ? sec_get_attempt($token) : null;

// Expired or invalid token
if (!$attempt || !in_array($attempt['status'], ['pending', 'approved'], true)) {
    $state = 'expired';
} elseif ($action === 'deny') {
    // Mark attempt as suspicious / blocked
    sec_update_attempt((int)$attempt['id'], ['status' => 'denied', 'is_suspicious' => 1, 'email_action' => 'denied']);
    // Notify account owner
    $user = findUserByEmail($attempt['email']);
    if ($user) {
        sendSuspiciousBlockedEmail($user['email'], $user['full_name'], [
            'ip_address' => $attempt['ip_address'],
            'browser'    => $attempt['browser'],
            'os'         => $attempt['os'],
        ]);
    }
    sec_log_event('login_denied_by_owner', 'Owner denied login attempt via email', 'critical', (int)$attempt['user_id'], ['ip' => $attempt['ip_address']]);
    $state = 'denied';
} elseif ($action === 'approve' && $attempt['status'] === 'pending') {
    // Generate number challenge
    $challenge = sec_generate_number_challenge();
    sec_update_attempt((int)$attempt['id'], [
        'status'         => 'approved',
        'email_action'   => 'approved',
        'number_shown'   => $challenge['shown'],
        'number_options' => $challenge['options'],
    ]);
    // Send number match email
    $user = findUserByEmail($attempt['email']);
    if ($user) {
        $options = array_map('intval', explode(',', $challenge['options']));
        sendNumberMatchEmail($user['email'], $user['full_name'], $challenge['shown'], $options);
    }
    sec_log_event('login_approved_by_owner', 'Owner approved login attempt', 'info', (int)$attempt['user_id']);
    $state = 'approved';
    // Reload attempt to get number data
    $attempt = sec_get_attempt($token);
} elseif ($action === 'approve' && $attempt['status'] === 'approved') {
    $state = 'approved'; // Already approved (page refresh)
} else {
    $state = 'invalid';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $state === 'approved' ? 'Verify Your Sign-In' : 'Sign-In Security' ?> — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <style>
        .challenge-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--surface-1); }
        .challenge-card { width: min(100%, 480px); background: var(--surface-2); border: 1px solid var(--border); border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .challenge-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 20px; }
        .icon-success { background: rgba(16,185,129,0.15); color: #10b981; }
        .icon-danger  { background: rgba(239,68,68,0.15);  color: #ef4444; }
        .icon-warning { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .challenge-title { font-size: 1.4rem; font-weight: 700; text-align: center; margin-bottom: 8px; color: var(--text-primary); }
        .challenge-sub   { font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 28px; }
        .number-grid { display: flex; gap: 12px; justify-content: center; margin: 24px 0; }
        .number-btn { width: 72px; height: 72px; border-radius: 14px; border: 2px solid var(--border); background: var(--surface-1); color: var(--text-primary); font-size: 1.6rem; font-weight: 700; cursor: pointer; transition: all .2s; font-family: 'Courier New', monospace; display: flex; align-items: center; justify-content: center; }
        .number-btn:hover { border-color: var(--accent); background: rgba(var(--accent-rgb), 0.08); transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .number-btn.selected { border-color: var(--accent); background: var(--accent); color: #fff; }
        .shown-number { text-align: center; margin: 0 0 8px; }
        .shown-label { font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px; }
        .shown-value { font-size: 3rem; font-weight: 900; color: var(--accent); font-family: 'Courier New', monospace; }
        .info-box { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 24px; font-size: 0.8rem; }
        .info-row { display: flex; gap: 8px; padding: 3px 0; }
        .info-label { color: var(--text-muted); min-width: 90px; }
        .info-val { color: var(--text-primary); font-weight: 600; }
        .btn-confirm { width: 100%; padding: 14px; background: var(--accent); color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: opacity .2s; }
        .btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }
        .status-denied  { color: #ef4444; }
        .status-success { color: #10b981; }
        .progress-steps { display: flex; align-items: center; gap: 0; margin-bottom: 24px; }
        .ps-step { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
        .ps-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
        .ps-dot-done { background: #10b981; color: #fff; }
        .ps-dot-active { background: var(--accent); color: #fff; }
        .ps-dot-pending { background: var(--surface-1); border: 2px solid var(--border); color: var(--text-muted); }
        .ps-label { font-size: 10px; color: var(--text-muted); }
        .ps-label.active { color: var(--text-primary); font-weight: 600; }
        .ps-line { flex: 1; height: 2px; background: var(--border); margin: 0 4px; }
        .ps-line.done { background: #10b981; }
        #msgBox { display: none; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-top: 12px; }
        #msgBox.error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); display: block; }
        #msgBox.success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); display: block; }
    </style>
</head>
<body>

<script>

</script>


<div class="challenge-page">
<div class="challenge-card">

<?php if ($state === 'denied'): ?>
    <div class="challenge-icon icon-danger">🛡️</div>
    <h1 class="challenge-title status-denied">Login Attempt Blocked</h1>
    <p class="challenge-sub">You denied this sign-in. The attempt has been flagged as suspicious and blocked. Your account remains secure.</p>
    <p style="text-align:center;margin-top:8px;"><a href="<?= url('login.php') ?>" class="btn btn-primary">Go to Login</a></p>

<?php elseif ($state === 'expired' || $state === 'invalid'): ?>
    <div class="challenge-icon icon-warning">⏰</div>
    <h1 class="challenge-title">Link Expired</h1>
    <p class="challenge-sub">This verification link has expired or is invalid. Please start a new sign-in attempt.</p>
    <p style="text-align:center;"><a href="<?= url('login.php') ?>" class="btn btn-primary">Back to Login</a></p>

<?php elseif ($state === 'approved'): ?>
    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="ps-step">
            <div class="ps-dot ps-dot-done">✓</div>
            <span class="ps-label">Email</span>
        </div>
        <div class="ps-line done"></div>
        <div class="ps-step">
            <div class="ps-dot ps-dot-done">✓</div>
            <span class="ps-label">Confirm</span>
        </div>
        <div class="ps-line done"></div>
        <div class="ps-step" id="stepNumber">
            <div class="ps-dot ps-dot-active">3</div>
            <span class="ps-label active">Match</span>
        </div>
        <div class="ps-line" id="line3"></div>
        <div class="ps-step" id="stepOtp">
            <div class="ps-dot ps-dot-pending">4</div>
            <span class="ps-label">OTP</span>
        </div>
    </div>

    <div id="numberSection">
        <div class="challenge-icon icon-success">🔢</div>
        <h1 class="challenge-title">Number Matching</h1>
        <p class="challenge-sub">We sent a number to your email. Select the matching number from below.</p>

        <p style="text-align:center;font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;">Select the matching number:</p>
        <div class="number-grid" id="numberGrid">
            <?php
            $options = array_map('intval', explode(',', $attempt['number_options'] ?? ''));
            foreach ($options as $opt): ?>
				<button class="number-btn" onclick="document.querySelectorAll('.number-btn').forEach(b => b.classList.remove('selected')); this.classList.add('selected'); selectedNum = <?= $opt ?>; document.getElementById('btnConfirmNumber').disabled = false;"><?= $opt ?></button>
            <?php endforeach; ?>
        </div>

        <button class="btn-confirm" id="btnConfirmNumber" disabled onclick="confirmNumber()">Confirm Selection</button>
        <div id="msgBox"></div>
    </div>

    <div id="otpSection" style="display:none;">
        <div class="challenge-icon icon-success" style="font-size:22px;">📧</div>
        <h1 class="challenge-title">Enter OTP Code</h1>
        <p class="challenge-sub">A 6-digit one-time code was sent to your email. It expires in 5 minutes.</p>

        <div style="margin:20px 0;">
            <input type="text" id="otpInput" maxlength="6" placeholder="000000"
                style="width:100%;text-align:center;letter-spacing:.25em;font-size:1.6rem;padding:14px;border:2px solid var(--border);border-radius:10px;background:var(--surface-1);color:var(--text-primary);font-family:'Courier New',monospace;box-sizing:border-box;">
        </div>

        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <button class="btn-confirm" id="btnVerifyOtp" onclick="verifyOtp()" style="flex:1;">Verify & Sign In</button>
        </div>
        <button style="width:100%;padding:10px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--text-muted);cursor:pointer;font-size:0.85rem;margin-top:4px;" onclick="resendOtp()">Resend OTP</button>
        <div id="otpMsg" class="msgBox"></div>
    </div>

    <script>
			const TOKEN = <?= json_encode($token) ?>;
			let selectedNum = null;

			async function confirmNumber() {
					if (selectedNum === null) return;
					const btn = document.getElementById('btnConfirmNumber');
					btn.disabled = true; btn.textContent = 'Verifying…';
					try {
							const res = await fetch(<?= json_encode(url('api/login.php')) ?>, {
									method: 'POST',
									headers: {'Content-Type': 'application/x-www-form-urlencoded'},
									body: new URLSearchParams({action: 'number_match', token: TOKEN, selected: selectedNum})
							});
							const j = await res.json();
							if (j.success) {
									// Transition to OTP
									document.getElementById('numberSection').style.display = 'none';
									document.getElementById('otpSection').style.display = 'block';
									document.getElementById('stepNumber').querySelector('.ps-dot').className = 'ps-dot ps-dot-done';
									document.getElementById('stepNumber').querySelector('.ps-dot').textContent = '✓';
									document.getElementById('line3').classList.add('done');
									document.getElementById('stepOtp').querySelector('.ps-dot').className = 'ps-dot ps-dot-active';
									document.getElementById('otpInput').focus();
							} else {
									showMsg(j.message || 'Incorrect number. Please try again.', 'error');
									btn.disabled = false; btn.textContent = 'Confirm Selection';
									document.querySelectorAll('.number-btn').forEach(b => b.classList.remove('selected'));
									selectedNum = null;
									document.getElementById('btnConfirmNumber').disabled = true;
							}
					} catch(e) { showMsg('Network error. Please try again.', 'error'); btn.disabled = false; btn.textContent = 'Confirm Selection'; }
			}

			async function verifyOtp() {
					const code = document.getElementById('otpInput').value;
					if (code.length < 6) { showOtpMsg('Enter the 6-digit code.', 'error'); return; }
					const btn = document.getElementById('btnVerifyOtp');
					btn.disabled = true; btn.textContent = 'Verifying…';
					try {
							const res = await fetch(<?= json_encode(url('api/login.php')) ?>, {
									method: 'POST',
									headers: {'Content-Type': 'application/x-www-form-urlencoded'},
									body: new URLSearchParams({action: 'verify_otp', token: TOKEN, code})
							});
							const j = await res.json();
							if (j.success && j.redirect) {
									showOtpMsg('✓ Verified! Signing you in…', 'success');
									setTimeout(() => window.location.href = j.redirect, 800);
							} else {
									showOtpMsg(j.message || 'Invalid OTP.', 'error');
									btn.disabled = false; btn.textContent = 'Verify & Sign In';
							}
					} catch(e) { showOtpMsg('Network error.', 'error'); btn.disabled = false; btn.textContent = 'Verify & Sign In';}
			}

			async function resendOtp() {
					const res = await fetch(<?= json_encode(url('api/otp.php')) ?>, {
							method: 'POST',
							headers: {'Content-Type': 'application/x-www-form-urlencoded'},
							body: new URLSearchParams({action: 'issue', email: <?= json_encode($attempt['email'] ?? '') ?>, purpose: 'login'})
					});
					const j = await res.json();
					showOtpMsg(j.success ? 'OTP resent! Check your email.' : (j.message || 'Failed to resend.'), j.success ? 'success' : 'error');
			}

			function showMsg(msg, type) {
					const el = document.getElementById('msgBox');
					el.className = 'msgBox ' + type; el.textContent = msg;
			}
			function showOtpMsg(msg, type) {
					const el = document.getElementById('otpMsg');
					el.className = 'msgBox ' + type; el.style.display = 'block'; el.textContent = msg;
			}

			document.getElementById('otpInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') verifyOtp(); });
    </script>
<?php endif; ?>

    <div style="text-align:center;margin-top:20px;font-size:0.8rem;color:var(--text-muted);">
        <a href="<?= url('index.php') ?>" style="color:var(--text-muted);">← BantayPurrPaws</a>
    </div>
</div>
</div>
</body>
</html>
