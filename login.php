<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/google-oauth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . dashboardHomeUrl());
    exit;
}

$error = flash('error') ?? '';
if (!$error && !empty($_SESSION['force_relogin_msg'])) {
    $error = $_SESSION['force_relogin_msg'];
    unset($_SESSION['force_relogin_msg']);
}

if (isset($_GET['google'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    header('Location: ' . googleAuthUrl($state));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } else {
        $result = loginUser($email, $password);

        if (is_array($result)) {
            createSystemNotification('system', 'You logged in successfully.', null, null, (int)$result['id']);
            header('Location: ' . dashboardHomeUrl());
            exit;
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <style>
        /* ======================================================
           LOGIN PAGE — Full-screen background with right panel
           ====================================================== */

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: 'DM Sans', system-ui, sans-serif;
            overflow-x: hidden;
        }

        /* Full-screen background */
        .login-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
        }

        .login-bg-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            filter: blur(1px) brightness(0.88);
            transform: scale(1.02); /* prevent blur edge bleed */
        }

        .login-bg-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                135deg,
                rgba(18, 12, 10, 0.55) 0%,
                rgba(28, 18, 14, 0.40) 50%,
                rgba(18, 8, 6, 0.65) 100%
            );
        }

        /* Hero branding on the left side */
        .login-hero {
            position: fixed;
            left: clamp(40px, 6vw, 80px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
            max-width: 440px;
            color: #fff;
            pointer-events: none;
        }

        .login-hero-paws {
            font-size: 2.8rem;
            margin-bottom: 20px;
            display: block;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
            animation: float 4s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .login-hero h1 {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.4);
        }

        .login-hero h1 em {
            font-style: italic;
            color: #f9c8a8;
        }

        .login-hero p {
            font-size: clamp(0.9rem, 1.5vw, 1.05rem);
            line-height: 1.65;
            color: rgba(255,255,255,0.82);
            text-shadow: 0 1px 8px rgba(0,0,0,0.3);
            max-width: 340px;
        }

        .login-hero-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 28px;
        }

        .login-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 999px;
        }

        /* Right-side login card */
        .login-panel {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: clamp(380px, 38vw, 520px);
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(24px, 4vw, 48px);
            overflow-y: auto;
            margin-right: 30px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(24px) saturate(160%);
            -webkit-backdrop-filter: blur(24px) saturate(160%);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.7);
            box-shadow:
                0 32px 80px rgba(0, 0, 0, 0.28),
                0 8px 24px rgba(0, 0, 0, 0.14),
                inset 0 1px 0 rgba(255,255,255,0.9);
            padding: clamp(28px, 5vw, 44px);
            animation: slideIn 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(32px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Brand inside card */
        .card-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            text-decoration: none;
            color: #1c1917;
        }

        .card-brand-img {
            height: 34px;
            width: auto;
        }

        .card-brand-name {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.1rem;
            letter-spacing: -0.01em;
            color: #1c1917;
        }

        .card-brand-mark {
            font-size: 1.6rem;
        }

        /* Welcome heading */
        .card-welcome {
            margin-bottom: 24px;
        }

        .card-welcome h2 {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: clamp(1.45rem, 2.8vw, 1.75rem);
            letter-spacing: -0.02em;
            color: #1c1917;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .card-welcome p {
            font-size: 0.875rem;
            color: #78716c;
            line-height: 1.5;
        }

        /* Error alert */
        .card-alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.845rem;
            font-weight: 500;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Google button */
        .card-btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 11px 16px;
            border: 1.5px solid #e7e5e4;
            background: #fff;
            color: #292524;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            font-family: 'DM Sans', system-ui, sans-serif;
        }

        .card-btn-google:hover {
            border-color: #4285f4;
            background: #f8f9ff;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.12);
            color: #292524;
        }

        /* Divider */
        .card-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: #a8a29e;
            font-size: 0.78rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .card-divider::before,
        .card-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e7e5e4;
        }

        /* MFA Steps — compact style */
        .card-mfa-steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 20px;
        }

        .card-mfa-step {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 500;
            color: #a8a29e;
            transition: color 0.25s;
        }

        .card-mfa-step-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #f5f5f4;
            border: 2px solid #e7e5e4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.62rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.25s ease;
        }

        .card-mfa-step.active { color: #1c1917; }
        .card-mfa-step.active .card-mfa-step-dot {
            background: #8B3A3A;
            border-color: #8B3A3A;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(139, 58, 58, 0.18);
        }

        .card-mfa-step.done .card-mfa-step-dot {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }

        .card-mfa-connector {
            flex: 1;
            height: 2px;
            background: #e7e5e4;
            margin: 0 6px;
            min-width: 12px;
            transition: background 0.3s;
        }

        .card-mfa-connector.done { background: #10b981; }

        /* MFA Panels */
        .card-mfa-panel {
            display: none;
            flex-direction: column;
            gap: 14px;
        }

        .card-mfa-panel.active { display: flex; }

        /* Form elements — override style.css scoped within card */
        .login-card .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .login-card .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #44403c;
            letter-spacing: 0.01em;
        }

        .login-card .req {
            color: #8B3A3A;
        }

        .login-card .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e7e5e4;
            border-radius: 10px;
            font-family: 'DM Sans', system-ui, sans-serif;
            font-size: 0.9rem;
            color: #1c1917;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .login-card .form-control:focus {
            border-color: #8B3A3A;
            box-shadow: 0 0 0 3px rgba(139, 58, 58, 0.14);
        }

        .login-card .form-control::placeholder {
            color: #a8a29e;
        }

        .otp-input {
            letter-spacing: 0.3em;
            font-size: 1.3rem !important;
            text-align: center;
            font-weight: 600;
        }

        /* Buttons */
        .card-btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 20px;
            background: #8B3A3A;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', system-ui, sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.15s;
            letter-spacing: 0.01em;
        }

        .card-btn-primary:hover {
            background: #6B2D2D;
            box-shadow: 0 4px 16px rgba(139, 58, 58, 0.35);
            transform: translateY(-1px);
        }

        .card-btn-primary:active {
            transform: translateY(0);
        }

        .card-btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .card-btn-ghost {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px 20px;
            background: transparent;
            color: #78716c;
            border: 1.5px solid #e7e5e4;
            border-radius: 10px;
            font-family: 'DM Sans', system-ui, sans-serif;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s, background 0.2s;
        }

        .card-btn-ghost:hover {
            border-color: #d6d3d1;
            background: #fafaf9;
            color: #44403c;
        }

        /* OTP hint */
        .card-hint {
            font-size: 0.835rem;
            color: #78716c;
            line-height: 1.5;
        }

        .card-hint strong {
            color: #1c1917;
        }

        /* Footer link */
        .card-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.845rem;
            color: #78716c;
        }

        .card-footer a {
            color: #8B3A3A;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
            transition: color 0.2s;
        }

        .card-footer a:hover {
            color: #6B2D2D;
        }

        /* Password Modal */
        .card-pw-modal {
            display: flex;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(10px);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .card-pw-modal.visible {
            opacity: 1;
            pointer-events: all;
        }

        .card-pw-modal-content {
            width: min(100%, 420px);
            padding: 36px;
            border-radius: 20px;
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 32px 80px rgba(0,0,0,0.3), 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            gap: 16px;
            transform: scale(0.94) translateY(12px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .card-pw-modal.visible .card-pw-modal-content {
            transform: scale(1) translateY(0);
        }

        .card-pw-modal-content h3 {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.4rem;
            color: #1c1917;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .card-mfa-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 4px 12px;
            border-radius: 999px;
            width: fit-content;
        }

        .card-pw-modal-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 4px;
        }

        .card-pw-modal-btns {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .card-pw-modal-content .card-btn-ghost {
            width: auto;
            padding: 9px 18px;
            font-size: 0.845rem;
        }

        .card-pw-modal-content .card-btn-primary {
            width: auto;
            padding: 9px 22px;
            font-size: 0.875rem;
        }

        .auth-modal-link {
            font-size: 0.8rem;
            color: #78716c;
            text-decoration: underline;
            text-underline-offset: 2px;
            transition: color 0.2s;
        }

        .auth-modal-link:hover {
            color: #8B3A3A;
        }

        /* Responsive — tablet */
        @media (max-width: 900px) {
            .login-hero {
                display: none;
            }

            .login-panel {
                width: 100%;
                position: relative;
                min-height: 100vh;
                padding: 24px 20px;
            }

            .login-bg {
                position: fixed;
            }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .login-card {
                padding: 28px 22px;
                border-radius: 20px;
            }

            .card-pw-modal-content {
                padding: 28px 22px;
            }

            .card-mfa-step span {
                display: none;
            }
        }

        /* Fade in animation */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-brand { animation: fadeUp 0.4s 0.1s both; }
        .card-welcome { animation: fadeUp 0.4s 0.18s both; }
        .card-btn-google { animation: fadeUp 0.4s 0.24s both; }
        .card-divider { animation: fadeUp 0.4s 0.28s both; }
        .card-mfa-steps { animation: fadeUp 0.4s 0.3s both; }
        .auth-form { animation: fadeUp 0.4s 0.34s both; }
        .card-footer { animation: fadeUp 0.4s 0.38s both; }
    </style>
</head>
<body>

<!-- Full-screen background -->
<div class="login-bg">
    <img src="<?= url('assets/dog.jpg') ?>" alt="" class="login-bg-img">
    <div class="login-bg-overlay"></div>
</div>

<!-- Left hero text (desktop only) -->
<div class="login-hero" aria-hidden="true">
    <span class="login-hero-paws">🐾</span>
    <h1>Give a pet a<br>loving home.</h1>
    <p>Report strays, connect with rescuers, and find your next furry family member — all in one place.</p

<!-- Right login panel -->
<div class="login-panel">
    <div class="login-card">

        <!-- Brand -->
        <a href="<?= url('login.php') ?>" class="card-brand">
            <?php if (is_file(__DIR__ . '/assets/logo.png')): ?>
                <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws" class="card-brand-img">
            <?php else: ?>
                <span class="card-brand-mark">🐾</span>
            <?php endif; ?>
            <span class="card-brand-name">BantayPurrPaws</span>
        </a>

        <!-- Welcome -->
        <div class="card-welcome">
            <h2>Welcome back</h2>
            <p>Sign in to report strays, adopt pets, and stay updated.</p>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
            <div class="card-alert-error">
                <span>✕</span> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Google Sign In -->
        <a href="?google=1" class="card-btn-google">
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z"/>
                <path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z"/>
                <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.163 6.656 3.58 9 3.58z"/>
            </svg>
            Sign in with Google
        </a>

        <!-- Divider -->
        <div class="card-divider"><span>or with email</span></div>

        <!-- MFA Steps -->
        <div class="card-mfa-steps" id="mfaSteps">
            <div class="card-mfa-step active" id="stepIndicator1">
                <div class="card-mfa-step-dot">1</div>
                <span>Email</span>
            </div>
            <div class="card-mfa-connector" id="connector1"></div>
            <div class="card-mfa-step" id="stepIndicator2">
                <div class="card-mfa-step-dot">2</div>
                <span>Verify OTP</span>
            </div>
            <div class="card-mfa-connector" id="connector2"></div>
            <div class="card-mfa-step" id="stepIndicator3">
                <div class="card-mfa-step-dot">3</div>
                <span>Password</span>
            </div>
        </div>

        <!-- Login Form -->
        <form method="POST" action="" id="loginForm" class="auth-form">
            <!-- Step 1: Email -->
            <div class="card-mfa-panel active" id="panel1">
                <div class="form-group">
                    <label class="form-label" for="email">Email <span class="req">*</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@example.com"
                           value="<?= sanitize($_POST['email'] ?? '') ?>"
                           required autocomplete="email">
                </div>
                <button type="button" id="btnIssueOtp" class="card-btn-primary">Send OTP Code</button>
            </div>

            <!-- Step 2: OTP -->
            <div class="card-mfa-panel" id="panel2">
                <p class="card-hint">
                    A 6-digit code was sent to <strong id="emailDisplay"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label" for="otp">One-Time Password <span class="req">*</span></label>
                    <input type="text" id="otp" name="otp" class="form-control otp-input"
                           placeholder="000000" maxlength="6" autocomplete="one-time-code">
                </div>
                <button type="button" id="btnVerifyOtp" class="card-btn-primary">Verify Code</button>
                <button type="button" id="btnBackToEmail" class="card-btn-ghost">← Back</button>
            </div>

            <input type="password" id="hiddenPassword" name="password" style="display:none;" autocomplete="current-password" data-no-pw-toggle="1">
            <button type="submit" id="finalSubmit" style="display:none;">Sign In</button>
        </form>

        <!-- Footer -->
        <p class="card-footer">
            Don't have an account? <a href="<?= url('register.php') ?>">Sign Up</a>
        </p>
    </div>
</div>

<!-- Password Modal -->
<div id="pwModal" class="card-pw-modal">
    <div class="card-pw-modal-content">
        <div class="card-mfa-badge">✓ Email verified</div>
        <h3>Enter your password</h3>
        <div class="form-group" style="display:flex;flex-direction:column;gap:5px;">
            <label class="form-label" for="modal_password" style="font-size:0.82rem;font-weight:600;color:#44403c;">
                Password <span style="color:#8B3A3A;">*</span>
            </label>
            <input type="password" id="modal_password" class="form-control"
                   style="padding:10px 14px;border:1.5px solid #e7e5e4;border-radius:10px;font-family:'DM Sans',system-ui,sans-serif;font-size:0.9rem;color:#1c1917;background:#fff;transition:border-color 0.2s,box-shadow 0.2s;outline:none;width:100%;"
                   placeholder="Your account password" autocomplete="current-password">
        </div>
        <div class="card-pw-modal-actions">
            <a href="<?= url('forgot-password.php') ?>" class="auth-modal-link">Forgot password?</a>
            <div class="card-pw-modal-btns">
                <button type="button" id="btnCancelModal" class="card-btn-ghost">Cancel</button>
                <button type="button" id="btnModalLogin" class="card-btn-primary">Log In</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const emailInput    = document.getElementById('email');
    const emailDisplay  = document.getElementById('emailDisplay');
    const otpInput      = document.getElementById('otp');
    const hiddenPwd     = document.getElementById('hiddenPassword');
    const loginForm     = document.getElementById('loginForm');

    const btnIssue      = document.getElementById('btnIssueOtp');
    const btnVerify     = document.getElementById('btnVerifyOtp');
    const btnBack       = document.getElementById('btnBackToEmail');
    const btnModalLogin = document.getElementById('btnModalLogin');
    const btnCancel     = document.getElementById('btnCancelModal');
    const modalPwd      = document.getElementById('modal_password');
    const pwModal       = document.getElementById('pwModal');

    const panel1 = document.getElementById('panel1');
    const panel2 = document.getElementById('panel2');

    const si1 = document.getElementById('stepIndicator1');
    const si2 = document.getElementById('stepIndicator2');
    const si3 = document.getElementById('stepIndicator3');
    const c1  = document.getElementById('connector1');
    const c2  = document.getElementById('connector2');

    function setStep(n) {
        [panel1, panel2].forEach(p => p.classList.remove('active'));
        [si1, si2, si3].forEach(s => { s.classList.remove('active','done'); });
        [c1, c2].forEach(c => c.classList.remove('done'));

        if (n === 1) {
            panel1.classList.add('active');
            si1.classList.add('active');
        } else if (n === 2) {
            panel2.classList.add('active');
            si1.classList.add('done'); c1.classList.add('done');
            si2.classList.add('active');
        } else if (n === 3) {
            si1.classList.add('done'); c1.classList.add('done');
            si2.classList.add('done'); c2.classList.add('done');
            si3.classList.add('active');
        }
    }

    btnIssue.addEventListener('click', async function() {
        const e = emailInput.value.trim();
        if (!e) { alert('Enter your email address first.'); return; }
        btnIssue.disabled = true;
        btnIssue.textContent = 'Sending…';
        try {
            const res = await fetch('<?= url('api/otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'issue', email: e, purpose: 'login' })
            });
            const json = await res.json();
            if (json.success) {
                emailDisplay.textContent = e;
                setStep(2);
                otpInput.focus();
            } else {
                alert(json.message || 'Failed to send OTP. Please try again.');
            }
        } catch(err) {
            alert('Network error. Please try again.');
        }
        btnIssue.disabled = false;
        btnIssue.textContent = 'Send OTP Code';
    });

    btnBack.addEventListener('click', function() { setStep(1); });

    btnVerify.addEventListener('click', async function() {
        const e = emailInput.value.trim();
        const c = otpInput.value.trim();
        if (c.length < 6) { alert('Enter the 6-digit OTP code.'); return; }
        btnVerify.disabled = true;
        btnVerify.textContent = 'Verifying…';
        try {
            const res = await fetch('<?= url('api/otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'verify', email: e, code: c, purpose: 'login' })
            });
            const json = await res.json();
            if (json.success) {
                setStep(3);
                pwModal.classList.add('visible');
                if (window.addPwToggle) window.addPwToggle(modalPwd);
                modalPwd.focus();
            } else {
                alert(json.message || 'Invalid or expired OTP. Please try again.');
                btnVerify.disabled = false;
                btnVerify.textContent = 'Verify Code';
            }
        } catch(err) {
            alert('Network error. Please try again.');
            btnVerify.disabled = false;
            btnVerify.textContent = 'Verify Code';
        }
    });

    btnCancel.addEventListener('click', function() {
        pwModal.classList.remove('visible');
        modalPwd.value = '';
        emailInput.value = '';
        otpInput.value = '';
        setStep(1);
        emailInput.focus();
    });

    btnModalLogin.addEventListener('click', function() {
        const pwd = modalPwd.value;
        if (!pwd) { alert('Please enter your password.'); return; }
        hiddenPwd.value = pwd;
        pwModal.classList.remove('visible');
        loginForm.submit();
    });

    otpInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btnVerify.click();
    });

    modalPwd.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') btnModalLogin.click();
    });

    /* Focus style for modal password input */
    modalPwd.addEventListener('focus', function() {
        this.style.borderColor = '#8B3A3A';
        this.style.boxShadow = '0 0 0 3px rgba(139,58,58,0.14)';
    });
    modalPwd.addEventListener('blur', function() {
        this.style.borderColor = '#e7e5e4';
        this.style.boxShadow = 'none';
    });
})();
</script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>