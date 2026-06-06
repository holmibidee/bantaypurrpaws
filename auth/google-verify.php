<?php
/**
 * auth/google-verify.php
 * OTP step after Google OAuth (new sign-up or linking an existing email).
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/google-oauth.php';
require_once dirname(__DIR__) . '/includes/otp.php';

googleOAuthDebugErrors();
startSession();

if (isLoggedIn()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$pending = $_SESSION['google_pending'] ?? null;
$purpose = $_SESSION['google_otp_purpose'] ?? 'registration';

if (!$pending || empty($pending['email'])) {
    header('Location: ' . url('login.php'));
    exit;
}

$email = $pending['email'];
$name  = $pending['name'] ?? 'User';
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {
    $code = trim($_POST['otp_code'] ?? '');

    if (strlen($code) !== 6) {
        $error = 'Please enter the full 6-digit code.';
    } else {
        $status = verifyOtp($email, $code, $purpose);

        if ($status === 'valid') {
            $googleUser = [
                'sub'     => $pending['sub'],
                'email'   => $pending['email'],
                'name'    => $pending['name'],
                'picture' => $pending['picture'] ?? null,
            ];

            $result = handleGoogleLogin($googleUser);
            unset($_SESSION['google_pending'], $_SESSION['google_otp_purpose']);

            if (!$result['success']) {
                $error = $result['error'];
            } else {
                $user = $result['user'];
                $msg  = $purpose === 'google_link'
                    ? 'Your Google account was linked successfully.'
                    : 'Welcome! Your Google account is verified.';

                finalizeGoogleSession($user, $msg);
                flash('success', $msg);
                header('Location: ' . googlePostLoginUrl($user));
                exit;
            }
        } elseif ($status === 'expired') {
            $error = 'Your code has expired. Please request a new one.';
        } else {
            $error = 'Invalid code. Please check and try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_otp') {
    $result = issueAndSendOtp($email, $name, $purpose);
    if ($result === true) {
        $info = 'A new verification code was sent to your email.';
    } else {
        $error = is_string($result) ? $result : 'Could not resend code. Please try again.';
    }
}

$purposeLabel = $purpose === 'google_link'
    ? 'link your Google account'
    : 'complete your Google sign-up';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — BantayPurrPaws</title>
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <style>
        .otp-inputs{display:flex;gap:10px;justify-content:center;margin:20px 0;}
        .otp-inputs input{width:48px;height:56px;text-align:center;font-size:22px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);}
        .otp-inputs input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(124,111,91,.15);}
        #otp_code_hidden{display:none;}
        .resend-row{text-align:center;margin-top:12px;font-size:13px;color:var(--text-muted);}
        .resend-row button{background:none;border:none;color:var(--primary);font-size:13px;cursor:pointer;text-decoration:underline;padding:0;}
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-panel fade-in">
        <div class="auth-logo">
            <img src="<?= url('assets/logo.png') ?>" alt="Bantay PurrPaws" class="auth-logo-img">
            <h1>Verify Your Email</h1>
            <p>Enter the code we sent to <?= sanitize($email) ?> to <?= sanitize($purposeLabel) ?>.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">✕ <?= $error ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="alert alert-info">ℹ <?= $info ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="otpForm">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="otp_code" id="otp_code_hidden">
            <div class="otp-inputs" id="otpBoxes">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                           class="otp-digit" autocomplete="one-time-code"
                           <?= $i === 0 ? 'autofocus' : '' ?>>
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:11px;">
                Verify &amp; Continue
            </button>
        </form>

        <div class="resend-row">
            Didn't receive the code?
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="action" value="resend_otp">
                <button type="submit">Resend code</button>
            </form>
        </div>
        <div class="auth-footer" style="margin-top:16px;">
            <a href="<?= url('login.php') ?>">← Back to Login</a>
        </div>

        <script>
        (function () {
            const digits = document.querySelectorAll('.otp-digit');
            const hidden = document.getElementById('otp_code_hidden');

            digits.forEach((el, i) => {
                el.addEventListener('input', () => {
                    el.value = el.value.replace(/\D/, '');
                    if (el.value && i < digits.length - 1) digits[i + 1].focus();
                    syncHidden();
                });
                el.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && !el.value && i > 0) digits[i - 1].focus();
                });
                el.addEventListener('paste', e => {
                    e.preventDefault();
                    const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                    pasted.split('').forEach((ch, j) => { if (digits[j]) digits[j].value = ch; });
                    if (pasted.length < 6 && digits[pasted.length]) digits[pasted.length].focus();
                    syncHidden();
                });
            });

            function syncHidden() {
                hidden.value = Array.from(digits).map(d => d.value).join('');
            }

            document.getElementById('otpForm').addEventListener('submit', e => {
                syncHidden();
                if (hidden.value.length < 6) {
                    e.preventDefault();
                    alert('Please enter all 6 digits.');
                }
            });
        })();
        </script>
    </div>
</div>
</body>
</html>
