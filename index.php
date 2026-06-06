<?php
require_once __DIR__ . '/includes/paths.php';

// If Google Console redirect URI points at the site root, forward to the real callback.
if (!empty($_GET['code']) && isset($_GET['state'])) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . absolute_url('auth/google-callback.php') . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . (isAdmin() ? url('admin/dashboard.php') : url('dashboard.php')));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantayPurrPaws — Stray Animal Rescue &amp; Adoption</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --clay:    #C26A52;
            --clay-dk: #9B4E39;
            --clay-lt: #F2D5CC;
            --ink:     #1A1209;
            --warm:    #F7F0E8;
            --mist:    #EDE4D9;
            --stone:   #7A6A5C;
            --radius:  14px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--warm);
            color: var(--ink);
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* ── Noise texture overlay ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
        }

        /* ── NAV ── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px clamp(24px, 6vw, 72px);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: rgba(247, 240, 232, 0.8);
            border-bottom: 1px solid rgba(194, 106, 82, 0.12);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-brand img {
            height: 38px;
            width: auto;
        }

        .nav-brand-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: var(--ink);
            letter-spacing: -0.01em;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-ghost {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--stone);
            padding: 8px 18px;
            border-radius: 999px;
            border: 1.5px solid transparent;
            background: transparent;
            text-decoration: none;
            transition: color .2s, border-color .2s;
            cursor: pointer;
        }
        .btn-ghost:hover { color: var(--ink); border-color: rgba(26,18,9,.15); }

        .btn-primary {
            font-size: 0.875rem;
            font-weight: 600;
            color: #fff;
            padding: 9px 22px;
            border-radius: 999px;
            background: var(--clay);
            border: none;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, transform .15s;
            box-shadow: 0 4px 16px rgba(194, 106, 82, 0.3);
        }
        .btn-primary:hover { background: var(--clay-dk); transform: translateY(-1px); color: #fff; }

        /* ── HERO ── */
        .hero {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px clamp(24px, 8vw, 120px) 80px;
            overflow: hidden;
        }

        /* decorative blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.55;
        }
        .blob-1 {
            width: 560px; height: 560px;
            background: radial-gradient(circle, #F2C5A0 0%, transparent 70%);
            top: -140px; right: -120px;
        }
        .blob-2 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #C26A52 0%, transparent 70%);
            bottom: 40px; left: -100px;
            opacity: 0.18;
        }
        .blob-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, #EFD4A0 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.3;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--clay);
            background: rgba(194, 106, 82, 0.1);
            border: 1px solid rgba(194, 106, 82, 0.25);
            padding: 6px 16px;
            border-radius: 999px;
            margin-bottom: 28px;
            position: relative;
            z-index: 2;
            animation: fadeUp 0.6s ease both;
        }

        .hero-tag::before {
            content: '';
            width: 6px; height: 6px;
            background: var(--clay);
            border-radius: 50%;
        }

        h1.hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.8rem, 7vw, 5.2rem);
            line-height: 1.08;
            letter-spacing: -0.035em;
            color: var(--ink);
            margin-bottom: 24px;
            position: relative;
            z-index: 2;
            max-width: 820px;
            animation: fadeUp 0.6s 0.1s ease both;
        }

        h1.hero-title em {
            font-style: italic;
            color: var(--clay);
        }

        .hero-lead {
            font-size: clamp(1rem, 2.2vw, 1.15rem);
            line-height: 1.75;
            color: var(--stone);
            max-width: 560px;
            margin: 0 auto 44px;
            font-weight: 300;
            position: relative;
            z-index: 2;
            animation: fadeUp 0.6s 0.2s ease both;
        }

        .hero-ctas {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
            position: relative;
            z-index: 2;
            animation: fadeUp 0.6s 0.3s ease both;
        }

        .btn-hero-primary {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            padding: 14px 36px;
            border-radius: 999px;
            background: var(--clay);
            border: none;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 6px 24px rgba(194, 106, 82, 0.38);
        }
        .btn-hero-primary:hover {
            background: var(--clay-dk);
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(194, 106, 82, 0.45);
            color: #fff;
        }

        .btn-hero-ghost {
            font-size: 1rem;
            font-weight: 500;
            color: var(--stone);
            padding: 14px 36px;
            border-radius: 999px;
            border: 1.5px solid rgba(122, 106, 92, 0.3);
            background: transparent;
            text-decoration: none;
            transition: color .2s, border-color .2s;
        }
        .btn-hero-ghost:hover { color: var(--ink); border-color: var(--stone); }

        /* ── TRUST STRIP ── */
        .trust-strip {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(24px, 5vw, 64px);
            padding: 28px clamp(24px, 6vw, 72px);
            background: var(--mist);
            border-top: 1px solid rgba(194, 106, 82, 0.1);
            border-bottom: 1px solid rgba(194, 106, 82, 0.1);
            flex-wrap: wrap;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.875rem;
            color: var(--stone);
        }

        .trust-icon {
            font-size: 1.25rem;
        }

        /* ── HOW IT WORKS ── */
        .section {
            position: relative;
            z-index: 1;
            padding: 96px clamp(24px, 8vw, 120px);
        }

        .section-label {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--clay);
            margin-bottom: 14px;
            display: block;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: -0.025em;
            color: var(--ink);
            line-height: 1.15;
            margin-bottom: 56px;
        }

        /* Steps */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .step-card {
            background: #fff;
            border: 1px solid rgba(194, 106, 82, 0.12);
            border-radius: 20px;
            padding: 36px 28px;
            position: relative;
            transition: transform .25s, box-shadow .25s;
        }

        .step-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(26, 18, 9, 0.08);
        }

        .step-num {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            line-height: 1;
            color: var(--clay-lt);
            font-weight: 900;
            margin-bottom: 20px;
        }

        .step-icon {
            font-size: 2rem;
            margin-bottom: 16px;
            display: block;
        }

        .step-card h3 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 10px;
        }

        .step-card p {
            font-size: 0.875rem;
            line-height: 1.65;
            color: var(--stone);
            font-weight: 300;
        }

        /* ── FEATURES ── */
        .features-section {
            background: var(--ink);
            color: var(--warm);
        }

        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        .features-text .section-title {
            color: var(--warm);
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .feature-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .feature-dot {
            width: 32px; height: 32px;
            background: rgba(194, 106, 82, 0.2);
            border: 1px solid rgba(194, 106, 82, 0.4);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .feature-item h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--warm);
            margin-bottom: 4px;
        }

        .feature-item p {
            font-size: 0.8125rem;
            color: rgba(247, 240, 232, 0.55);
            line-height: 1.6;
            font-weight: 300;
        }

        .features-visual {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .mock-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .mock-avatar {
            width: 40px; height: 40px;
            border-radius: 10px;
            flex-shrink: 0;
        }
        .mock-avatar.clay { background: rgba(194,106,82,0.5); }
        .mock-avatar.stone { background: rgba(122,106,92,0.4); }
        .mock-avatar.gold { background: rgba(212, 170, 80, 0.5); }

        .mock-lines { flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .mock-line { height: 8px; border-radius: 4px; background: rgba(255,255,255,0.12); }
        .mock-line.short { width: 55%; }

        .mock-badge {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 999px;
            letter-spacing: 0.04em;
        }
        .mock-badge.pending { background: rgba(245,158,11,0.2); color: #F59E0B; }
        .mock-badge.rescued { background: rgba(16,185,129,0.2); color: #10B981; }
        .mock-badge.available { background: rgba(194,106,82,0.2); color: #E8A890; }

        /* ── CTA BANNER ── */
        .cta-section {
            text-align: center;
            padding: 96px clamp(24px, 8vw, 120px);
        }

        .cta-inner {
            background: var(--clay);
            border-radius: 28px;
            padding: 72px clamp(24px, 6vw, 96px);
            position: relative;
            overflow: hidden;
        }

        .cta-inner::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .cta-inner::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -40px;
            width: 240px; height: 240px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .cta-inner h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            color: #fff;
            letter-spacing: -0.02em;
            margin-bottom: 14px;
            position: relative;
            z-index: 1;
        }

        .cta-inner p {
            font-size: 1rem;
            color: rgba(255,255,255,0.75);
            margin-bottom: 36px;
            font-weight: 300;
            position: relative;
            z-index: 1;
        }

        .cta-inner .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--clay-dk);
            padding: 14px 36px;
            border-radius: 999px;
            background: #fff;
            text-decoration: none;
            transition: transform .15s, box-shadow .2s;
            box-shadow: 0 6px 24px rgba(0,0,0,0.15);
            position: relative;
            z-index: 1;
        }
        .cta-inner .btn-white:hover { transform: translateY(-2px); box-shadow: 0 10px 32px rgba(0,0,0,0.2); }

        /* ── FOOTER ── */
        footer {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 28px;
            font-size: 0.78rem;
            color: var(--stone);
            border-top: 1px solid rgba(194,106,82,0.1);
        }

        footer a { color: var(--clay); text-decoration: none; }
        footer a:hover { text-decoration: underline; }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .steps-grid { grid-template-columns: 1fr; max-width: 480px; margin: 0 auto; }
            .features-grid { grid-template-columns: 1fr; }
            .features-visual { display: none; }
        }

        @media (max-width: 600px) {
            nav { padding: 14px 20px; }
            .nav-brand-text { display: none; }
            .trust-strip { gap: 18px; }
            .trust-item { font-size: 0.8rem; }
            .hero-ctas { flex-direction: column; width: 100%; max-width: 320px; margin: 0 auto; }
            .btn-hero-primary, .btn-hero-ghost { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <a class="nav-brand" href="<?= url('index.php') ?>">
        <img src="<?= url('assets/logo.png') ?>" alt="BantayPurrPaws">
        <span class="nav-brand-text">BantayPurrPaws</span>
    </a>
    <div class="nav-actions">
        <a href="<?= url('register.php') ?>" class="btn-ghost">Create account</a>
        <a href="<?= url('login.php') ?>" class="btn-primary">Sign in</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <span class="hero-tag">🐾 Stray Animal Rescue &amp; Adoption System</span>

    <h1 class="hero-title">
        Report. Rescue. <em>Adopt.</em>
    </h1>

    <p class="hero-lead">
        BantayPurrPaws is a community platform for stray animal welfare in the Philippines.
        Spot a stray? Report it in seconds. Our rescue teams are notified instantly,
        and rescued animals become available for adoption — all in one place.
    </p>

    <div class="hero-ctas">
        <a href="<?= url('register.php') ?>" class="btn-hero-primary">Get started free</a>
        <a href="<?= url('login.php') ?>" class="btn-hero-ghost">Sign in</a>
    </div>
</section>

<!-- MISSION BANNER -->
<div style="position:relative;z-index:1;background:var(--clay);color:#fff;padding:16px clamp(24px,6vw,72px);text-align:center;">
    <p style="margin:0;font-size:.95rem;font-weight:500;letter-spacing:.01em;">
        🐾 &nbsp;A free platform to <strong>report strays</strong>, <strong>track rescues</strong>, and <strong>find pets for adoption</strong> — join the community making a difference.
    </p>
</div>

<!-- TRUST STRIP -->
<div class="trust-strip">
    <div class="trust-item">
        <span class="trust-icon">🔒</span>
        <span>Secure &amp; verified accounts</span>
    </div>
    <div class="trust-item">
        <span class="trust-icon">📍</span>
        <span>Location-based reporting</span>
    </div>
    <div class="trust-item">
        <span class="trust-icon">🐾</span>
        <span>Real-time rescue tracking</span>
    </div>
    <div class="trust-item">
        <span class="trust-icon">🏡</span>
        <span>Verified adoption process</span>
    </div>
</div>

<!-- HOW IT WORKS -->
<section class="section">
    <span class="section-label">How it works</span>
    <h2 class="section-title">Three steps to save a life</h2>

    <div class="steps-grid">
        <div class="step-card">
            <div class="step-num">01</div>
            <span class="step-icon">📸</span>
            <h3>Report a rescue</h3>
            <p>Spot a stray in need? Submit their location, a photo, and details. Our team gets notified instantly.</p>
        </div>
        <div class="step-card">
            <div class="step-num">02</div>
            <span class="step-icon">📡</span>
            <h3>Track progress</h3>
            <p>Follow your report from pending through rescue — with status updates every step of the way.</p>
        </div>
        <div class="step-card">
            <div class="step-num">03</div>
            <span class="step-icon">🏠</span>
            <h3>Adopt a pet</h3>
            <p>Browse rescued animals available for adoption and apply to give one a permanent, loving home.</p>
        </div>
    </div>
</section>

<!-- FEATURES (dark) -->
<section class="section features-section">
    <div class="features-grid">
        <div class="features-text">
            <span class="section-label" style="color: var(--clay-lt);">Platform features</span>
            <h2 class="section-title">Everything you need,<br>nothing you don't.</h2>
            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-dot">✉️</div>
                    <div>
                        <h4>OTP-verified login</h4>
                        <p>Multi-factor authentication keeps accounts protected with one-time email codes.</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-dot">🛡️</div>
                    <div>
                        <h4>Role-based access</h4>
                        <p>Granular staff permissions — admins, rescue staff, and users each see only what they need.</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-dot">🔔</div>
                    <div>
                        <h4>Real-time notifications</h4>
                        <p>Instant alerts when reports update, applications change status, or rescues complete.</p>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-dot">🌐</div>
                    <div>
                        <h4>Google Sign-In</h4>
                        <p>One-click login via your Google account — no passwords to remember.</p>
                    </div>
                </li>
            </ul>
        </div>
        <div class="features-visual">
            <div class="mock-card">
                <div class="mock-avatar clay"></div>
                <div class="mock-lines">
                    <div class="mock-line"></div>
                    <div class="mock-line short"></div>
                </div>
                <span class="mock-badge pending">Pending</span>
            </div>
            <div class="mock-card">
                <div class="mock-avatar gold"></div>
                <div class="mock-lines">
                    <div class="mock-line"></div>
                    <div class="mock-line short"></div>
                </div>
                <span class="mock-badge rescued">Rescued</span>
            </div>
            <div class="mock-card">
                <div class="mock-avatar stone"></div>
                <div class="mock-lines">
                    <div class="mock-line"></div>
                    <div class="mock-line short"></div>
                </div>
                <span class="mock-badge available">Available</span>
            </div>
        </div>
    </div>
</section>

<!-- CTA BANNER -->
<section class="cta-section">
    <div class="cta-inner">
        <h2>Ready to make a difference?</h2>
        <p>Join the BantayPurrPaws community and help strays find safety and loving homes today.</p>
        <a href="<?= url('register.php') ?>" class="btn-white">
            Create your free account →
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer>
    &copy; <?= date('Y') ?> BantayPurrPaws &mdash; Stray Animal Rescue &amp; Adoption System &nbsp;|&nbsp;
    <a href="<?= url('terms.php') ?>">Terms &amp; Conditions</a>
</footer>

</body>
</html>
