<?php
/**
 * Staff Account Setup — linked from email invitation.
 * Accessible without login.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/logger.php';

load_env_file(__DIR__ . '/.env');

// ── Validate token ────────────────────────────────────────
$token  = trim($_GET['token'] ?? '');
$invite = null;

if ($token !== '') {
    try {
        $stmt = getDB()->prepare('SELECT * FROM staff_invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('staff-setup token lookup: ' . $e->getMessage());
    }
}

if (!$invite) {
    $pageTitle = 'Invalid Invitation';
    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invalid Invitation — BantayPurrPaws</title>
<style>
  body{font-family:system-ui,sans-serif;background:#faf5f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}
  .box{background:#fff;border-radius:16px;padding:2.5rem;max-width:420px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);}
  h2{color:#8B3A3A;margin-bottom:.5rem;} p{color:#6b5f56;} a{color:#8B3A3A;font-weight:600;}
</style></head>
<body><div class="box">
  <h2>⛔ Invalid or Expired Link</h2>
  <p>This invitation link is invalid or has already been used. Please ask your administrator to send a new invitation.</p>
  <p><a href="<?= url('login.php') ?>">← Back to Login</a></p>
</div></body></html>
<?php
    exit;
}

$inviteEmail = $invite['email'];
$permissions = json_decode($invite['permissions'] ?? '[]', true) ?: [];

// Permission labels for display
$permLabels = [
    'manage_reports'     => 'Manage Rescue Reports',
    'manage_pets'        => 'Manage Pet Listings',
    'review_adoptions'   => 'Review Adoption Applications',
    'view_adoptions'     => 'View Adoption Queue',
    'manage_users'       => 'Manage Regular Users',
    'post_announcements' => 'Post Announcements',
];

// ── Handle POST ───────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName  = trim($_POST['full_name']        ?? '');
    $username  = trim($_POST['username']         ?? '');
    $password  = $_POST['password']              ?? '';
    $confirm   = $_POST['confirm_password']      ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if ($password !== $confirm) {
        // handled client-side; but server-side guard
        $errors[] = 'Passwords do not match.';
    } else {
        $pwErrors = validatePasswordPolicy($password);
        if (!empty($pwErrors)) {
            $errors[] = passwordPolicyMessage($pwErrors);
        }
    }

    if (empty($errors)) {
        // Double-check token still valid
        try {
            $stmt = getDB()->prepare('SELECT id FROM staff_invites WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1');
            $stmt->execute([$token]);
            $stillValid = $stmt->fetchColumn();
        } catch (Throwable $e) {
            $stillValid = false;
        }

        if (!$stillValid) {
            $errors[] = 'This invitation has expired. Please request a new one.';
        } else {
            $data = [
                'full_name'       => $fullName,
                'email'           => $inviteEmail,
                'password'        => password_hash($password, PASSWORD_DEFAULT),
                'role'            => 'staff',
                'email_verified'  => 1,
                'auth_provider'   => 'local',
                'staff_permissions' => !empty($permissions) ? json_encode($permissions) : null,
            ];
            if ($username !== '') {
                if (usernameExists($username)) {
                    $errors[] = 'That username is already taken.';
                } else {
                    $data['username'] = $username;
                }
            }

            if (empty($errors)) {
                $user = insertUserRecord($data);
                if ($user) {
                    // Mark invite as used
                    try {
                        getDB()->prepare('UPDATE staff_invites SET used = 1 WHERE token = ?')->execute([$token]);
                    } catch (Throwable) {}

                    ?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Account Ready — BantayPurrPaws</title>
<style>
  body{font-family:system-ui,sans-serif;background:#faf5f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}
  .box{background:#fff;border-radius:16px;padding:2.5rem;max-width:420px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);}
  h2{color:#2d7a4f;margin-bottom:.5rem;} p{color:#6b5f56;} a.btn{display:inline-block;margin-top:1rem;background:#8B3A3A;color:#fff;font-weight:600;padding:12px 28px;border-radius:8px;text-decoration:none;}
</style></head>
<body><div class="box">
  <div style="font-size:3rem;margin-bottom:.5rem;">🎉</div>
  <h2>Account Ready!</h2>
  <p>Welcome to BantayPurrPaws, <strong><?= htmlspecialchars($fullName) ?></strong>! Your staff account has been set up successfully.</p>
  <a href="<?= url('login.php') ?>" class="btn">Log In Now</a>
</div></body></html>
<?php
                    exit;
                } else {
                    $errors[] = 'Could not create account. The email may already be registered.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Complete Your Staff Account — BantayPurrPaws</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body {
    font-family: system-ui, -apple-system, sans-serif;
    background: #faf5f0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    margin: 0;
    padding: 1.5rem;
  }
  .card {
    background: #fff;
    border-radius: 20px;
    padding: 2.5rem 2rem;
    max-width: 480px;
    width: 100%;
    box-shadow: 0 8px 32px rgba(0,0,0,.10);
  }
  .brand { text-align: center; margin-bottom: 1.75rem; }
  .brand h1 { font-size: 1.5rem; color: #8B3A3A; margin: 0 0 .25rem; }
  .brand p { color: #9c8f84; font-size: .875rem; margin: 0; }
  .perm-list { background: #faf5f0; border-radius: 10px; padding: 12px 16px; margin-bottom: 1.5rem; }
  .perm-list p { font-size: .8rem; color: #6b5f56; margin: 0 0 6px; font-weight: 600; }
  .perm-list ul { margin: 0; padding-left: 18px; }
  .perm-list li { font-size: .8rem; color: #78716c; margin-bottom: 3px; }
  .form-group { margin-bottom: 1rem; }
  label { display: block; font-size: .875rem; font-weight: 600; color: #4a3f38; margin-bottom: 5px; }
  .optional { font-weight: 400; color: #9c8f84; font-size: .8rem; }
  input[type=text], input[type=password] {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e5ddd5;
    border-radius: 9px;
    font-size: .9375rem;
    color: #2d2520;
    outline: none;
    transition: border-color .2s;
  }
  input:focus { border-color: #8B3A3A; }
  .btn-submit {
    width: 100%;
    padding: 13px;
    background: #8B3A3A;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    margin-top: .75rem;
    transition: background .2s;
  }
  .btn-submit:hover { background: #7a3030; }
  .badge-error {
    display: none;
    background: #fee2e2;
    color: #991b1b;
    font-size: .8rem;
    padding: 6px 12px;
    border-radius: 7px;
    margin-top: .5rem;
    text-align: center;
  }
  .server-errors { background:#fee2e2;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:1rem;font-size:.875rem; }
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <h1>🐾 Complete Your Staff Account</h1>
    <p>Setting up account for <strong><?= htmlspecialchars($inviteEmail) ?></strong></p>
  </div>

  <?php if (!empty($permissions)): ?>
  <div class="perm-list">
    <p>Your access includes:</p>
    <ul>
      <?php foreach ($permissions as $key): ?>
        <li><?= htmlspecialchars($permLabels[$key] ?? $key) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
  <div class="server-errors"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" id="setupForm" novalidate>
    <div class="form-group">
      <label for="full_name">Full Name <span style="color:#8B3A3A">*</span></label>
      <input type="text" id="full_name" name="full_name" required
             value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
             placeholder="Juan dela Cruz">
    </div>
    <div class="form-group">
      <label for="username">Username <span class="optional">(optional)</span></label>
      <input type="text" id="username" name="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             placeholder="juandc">
    </div>
    <div class="form-group">
      <label for="password">Password <span style="color:#8B3A3A">*</span></label>
      <input type="password" id="password" name="password" required autocomplete="new-password"
             placeholder="Min. 12 chars, uppercase, number, symbol">
    </div>
    <div class="form-group">
      <label for="confirm_password">Confirm Password <span style="color:#8B3A3A">*</span></label>
      <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn-submit">Finish Setting Up</button>
    <div class="badge-error" id="formError"></div>
  </form>
</div>

<script>
document.getElementById('setupForm').addEventListener('submit', function(e) {
  var name = document.getElementById('full_name').value.trim();
  var pw   = document.getElementById('password').value;
  var cpw  = document.getElementById('confirm_password').value;
  var err  = document.getElementById('formError');
  err.style.display = 'none';
  err.textContent   = '';
  if (!name) {
    e.preventDefault(); err.textContent = 'Full name is required.'; err.style.display = 'block'; return;
  }
  if (pw !== cpw) {
    e.preventDefault(); err.textContent = 'Passwords do not match.'; err.style.display = 'block'; return;
  }
  if (pw.length < 12) {
    e.preventDefault(); err.textContent = 'Password must be at least 12 characters.'; err.style.display = 'block'; return;
  }
});
</script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
</body>
</html>
