<?php
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

$loggedIn = isLoggedIn();
$isAdmin  = $loggedIn && isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms &amp; Conditions — BantayPurrPaws</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <style>
        .terms-page {
            min-height: 100vh;
            background: var(--bg);
        }
        .terms-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px clamp(20px, 6vw, 64px);
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .terms-topbar a.brand {
            display: flex; align-items: center; gap: 10px; text-decoration: none;
            color: var(--text-primary); font-weight: 600;
        }
        .terms-topbar img { height: 36px; }
        .terms-topbar .back-link { font-size: .875rem; color: var(--text-secondary); text-decoration: none; }
        .terms-topbar .back-link:hover { color: var(--accent); }

        .terms-hero {
            background: var(--stone-900);
            color: #fff;
            padding: 56px clamp(20px, 6vw, 64px);
            text-align: center;
        }
        .terms-hero h1 {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 5vw, 3rem);
            letter-spacing: -0.02em;
            margin-bottom: 10px;
        }
        .terms-hero p {
            font-size: .9375rem;
            color: rgba(255,255,255,.65);
            font-weight: 300;
        }
        .terms-effective {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .06em;
            color: var(--accent-muted);
            background: rgba(139,58,58,.2);
            border: 1px solid rgba(232,212,212,.2);
            padding: 5px 14px;
            border-radius: 999px;
            margin-top: 16px;
        }

        .terms-body {
            max-width: 820px;
            margin: 0 auto;
            padding: 56px clamp(20px, 6vw, 64px);
        }

        .terms-section {
            margin-bottom: 44px;
        }
        .terms-section h2 {
            font-family: var(--font-display);
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1.5px solid var(--border);
        }
        .terms-section p {
            font-size: .9375rem;
            line-height: 1.75;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        .terms-section ul {
            padding-left: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .terms-section li {
            font-size: .9375rem;
            line-height: 1.65;
            color: var(--text-secondary);
        }
        .terms-section li strong {
            color: var(--text-primary);
        }

        .terms-highlight {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-left: 3px solid var(--accent);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            font-size: .875rem;
            line-height: 1.65;
            color: var(--text-secondary);
            margin: 16px 0;
        }

        .terms-footer-bar {
            background: var(--surface);
            border-top: 1px solid var(--border);
            text-align: center;
            padding: 24px;
            font-size: .8125rem;
            color: var(--text-muted);
        }
        .terms-footer-bar a { color: var(--accent); text-decoration: none; }
        .terms-footer-bar a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="terms-page">

    <!-- Top bar -->
    <div class="terms-topbar">
        <a class="brand" href="<?= url('index.php') ?>">
            <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws">
            BantayPurrPaws
        </a>
        <?php if ($loggedIn): ?>
            <a href="<?= url($isAdmin ? 'admin/dashboard.php' : 'dashboard.php') ?>" class="back-link">← Back to Dashboard</a>
        <?php else: ?>
            <a href="<?= url('index.php') ?>" class="back-link">← Back to Home</a>
        <?php endif; ?>
    </div>

    <!-- Hero -->
    <div class="terms-hero">
        <h1>Terms &amp; Conditions</h1>
        <p>Please read these terms carefully before using BantayPurrPaws.</p>
        <div class="terms-effective">Effective June 1, 2025 — Last updated June 6, 2025</div>
    </div>

    <!-- Content -->
    <div class="terms-body">

        <div class="terms-section">
            <h2>1. Acceptance of Terms</h2>
            <p>
                By accessing or using the BantayPurrPaws platform ("the Platform"), you agree to be bound by these
                Terms and Conditions. If you do not agree with any part of these terms, you may not use the Platform.
                These terms apply to all users, including reporters, adopters, staff, and administrators.
            </p>
        </div>

        <div class="terms-section">
            <h2>2. Platform Purpose</h2>
            <p>
                BantayPurrPaws is a community rescue and adoption platform designed to:
            </p>
            <ul>
                <li>Allow community members to report stray animals in need of rescue.</li>
                <li>Enable rescue staff to manage and track rescue operations.</li>
                <li>Facilitate the adoption of rescued animals to qualified applicants.</li>
                <li>Maintain transparent records of animal welfare activities.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>3. User Accounts</h2>
            <p>To access most features, you must create an account. You agree to:</p>
            <ul>
                <li><strong>Provide accurate information</strong> when registering, including your real name and a valid email address.</li>
                <li><strong>Keep your credentials secure.</strong> You are responsible for all activity that occurs under your account.</li>
                <li><strong>Not share your account</strong> with any other person or entity.</li>
                <li><strong>Notify us immediately</strong> of any unauthorized access to your account.</li>
            </ul>
            <div class="terms-highlight">
                Account registration requires email verification via a one-time password (OTP). Accounts created with
                false information may be suspended without notice.
            </div>
        </div>

        <div class="terms-section">
            <h2>4. Rescue Reports</h2>
            <p>When submitting a rescue report, you agree to:</p>
            <ul>
                <li>Provide truthful, accurate information about the animal's location and condition.</li>
                <li>Not submit false, duplicate, or malicious reports.</li>
                <li>Understand that rescue staff will make operational decisions based on your report — timely response is not guaranteed.</li>
                <li>Grant the Platform permission to use the submitted information and photos for operational purposes.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>5. Adoption Applications</h2>
            <p>Submitting an adoption application does not guarantee approval. You agree to:</p>
            <ul>
                <li>Provide honest and complete information in your application.</li>
                <li>Comply with the Platform's adoption requirements and any applicable local regulations.</li>
                <li>Understand that approval or rejection is at the sole discretion of the Platform's staff.</li>
                <li>Not transfer, sell, or otherwise rehome an adopted animal without prior written consent.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>6. Prohibited Conduct</h2>
            <p>You must not:</p>
            <ul>
                <li>Use the Platform for any unlawful purpose or in violation of any applicable regulations.</li>
                <li>Harass, abuse, or threaten other users or staff members.</li>
                <li>Submit false or misleading information about animals, locations, or your identity.</li>
                <li>Attempt to gain unauthorized access to other user accounts or system data.</li>
                <li>Use automated scripts or bots to interact with the Platform.</li>
                <li>Interfere with or disrupt the integrity or performance of the Platform.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>7. Privacy &amp; Data Collection</h2>
            <p>
                By using the Platform, you consent to the collection and processing of the following data:
            </p>
            <ul>
                <li>Name, email address, and phone number (for account management and communication).</li>
                <li>Location data included in rescue reports (used only for rescue coordination).</li>
                <li>Usage logs and activity data (used for security and system improvements).</li>
                <li>Profile photographs uploaded by users.</li>
            </ul>
            <div class="terms-highlight">
                Your data is never sold to third parties. We may use your contact information to send
                service-related notifications. You may request account deletion at any time via the Platform settings.
            </div>
        </div>

        <div class="terms-section">
            <h2>8. Intellectual Property</h2>
            <p>
                All content on the Platform — including the logo, design, and written materials — is owned by
                BantayPurrPaws or its content suppliers and is protected under applicable intellectual property laws.
                You may not reproduce, distribute, or create derivative works without prior written permission.
            </p>
        </div>

        <div class="terms-section">
            <h2>9. Disclaimer of Warranties</h2>
            <p>
                The Platform is provided on an "as is" and "as available" basis. We make no warranties, express or
                implied, regarding the accuracy, reliability, or availability of the Platform. We are not liable for
                any damages arising from the use of or inability to use the Platform.
            </p>
        </div>

        <div class="terms-section">
            <h2>10. Changes to Terms</h2>
            <p>
                We reserve the right to update these Terms and Conditions at any time. Changes will be posted on this
                page with an updated effective date. Continued use of the Platform after changes constitutes acceptance
                of the revised terms.
            </p>
        </div>

        <div class="terms-section">
            <h2>11. Contact</h2>
            <p>
                If you have questions about these Terms and Conditions, please contact us through the Platform's
                support channel or by emailing <strong>admin@bantaypurrpaws.com</strong>.
            </p>
        </div>

    </div>

    <div class="terms-footer-bar">
        &copy; <?= date('Y') ?> BantayPurrPaws &mdash; Stray Animal Rescue &amp; Adoption System &nbsp;|&nbsp;
        <a href="<?= url('index.php') ?>">Home</a>
    </div>
</div>
</body>
</html>
