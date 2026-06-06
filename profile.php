<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/otp.php';

requireLogin();

$pageTitle     = 'My Profile';
$useSweetAlert = true;
$extraJs       = ['js/password-strength.js', 'js/phone-input.js'];
$me            = currentUser();
$profileUser   = getUserById((int) $me['id']);

if ($profileUser) {
    $profileUser = normalizeUserRecord($profileUser, $me);
}

if (!$profileUser || $profileUser['id'] <= 0) {
    flash('error', 'Account not found.');
    header('Location: ' . url(isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

// ── AJAX: Send profile-update OTP ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'send_profile_otp') {
    header('Content-Type: application/json');
    $result = issueAndSendOtp($profileUser['email'], $profileUser['full_name'], 'profile_update');
    echo json_encode(['ok' => $result === true, 'message' => is_string($result) ? $result : '']);
    exit;
}

// ── AJAX: Verify profile-update OTP ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'verify_profile_otp') {
    header('Content-Type: application/json');
    $code   = trim($_POST['otp_code'] ?? '');
    $status = verifyOtp($profileUser['email'], $code, 'profile_update');
    echo json_encode(['ok' => $status === 'valid', 'status' => $status]);
    exit;
}

// ── AJAX: Send OTP to current email (for email change) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'send_current_email_otp') {
    header('Content-Type: application/json');
    $result = issueAndSendOtp($profileUser['email'], $profileUser['full_name'], 'email_change_current');
    echo json_encode(['ok' => $result === true, 'message' => is_string($result) ? $result : '']);
    exit;
}

// ── AJAX: Verify current email OTP ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'verify_current_email_otp') {
    header('Content-Type: application/json');
    $code   = trim($_POST['otp_code'] ?? '');
    $status = verifyOtp($profileUser['email'], $code, 'email_change_current');
    echo json_encode(['ok' => $status === 'valid', 'status' => $status]);
    exit;
}

// ── AJAX: Send OTP to new email ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'send_new_email_otp') {
    header('Content-Type: application/json');
    $newEmail = trim($_POST['new_email'] ?? '');
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    if (emailExists($newEmail) && $newEmail !== $profileUser['email']) {
        echo json_encode(['ok' => false, 'message' => 'That email is already in use.']);
        exit;
    }
    $_SESSION['pending_new_email'] = $newEmail;
    $result = issueAndSendOtp($newEmail, $profileUser['full_name'], 'email_change_new');
    echo json_encode(['ok' => $result === true, 'message' => is_string($result) ? $result : '']);
    exit;
}

// ── AJAX: Save new email (verify new OTP + update) ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_new_email') {
    header('Content-Type: application/json');
    $newEmail = $_SESSION['pending_new_email'] ?? '';
    $code     = trim($_POST['otp_code'] ?? '');
    if (!$newEmail) {
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    $status = verifyOtp($newEmail, $code, 'email_change_new');
    if ($status === 'valid') {
        $result = updateOwnProfile((int) $me['id'], [
            'full_name'    => $profileUser['full_name'],
            'email'        => $newEmail,
            'phone_number' => $profileUser['phone_number'] ?? '',
            'username'     => $profileUser['username'] ?? '',
        ]);
        unset($_SESSION['pending_new_email']);
        if ($result['ok']) {
            // refresh session user
            $profileUser = normalizeUserRecord($result['user'], $me);
            refreshUserSession($result['user']);
            echo json_encode(['ok' => true, 'new_email' => $newEmail]);
        } else {
            echo json_encode(['ok' => false, 'message' => $result['error'] ?? 'Could not update email.']);
        }
    } elseif ($status === 'expired') {
        echo json_encode(['ok' => false, 'expired' => true, 'message' => 'OTP expired. Please request a new one.']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Invalid OTP.']);
    }
    exit;
}

// ── AJAX: Update profile (after OTP verified) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save_profile') {
    header('Content-Type: application/json');
    $result = updateOwnProfile((int) $me['id'], [
        'full_name'    => trim($_POST['full_name'] ?? ''),
        'email'        => $profileUser['email'], // email unchanged here
        'phone_number' => trim($_POST['phone_number'] ?? ''),
        'username'     => trim($_POST['username'] ?? ''),
    ]);
    if ($result['ok']) {
        $profileUser = normalizeUserRecord($result['user'], $me);
        refreshUserSession($result['user']);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'message' => $result['error'] ?? 'Update failed.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $result = changeOwnPassword(
            (int) $me['id'],
            $_POST['current_password'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['confirm_new_password'] ?? ''
        );
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Password changed successfully.' : $result['error']);
    }

    if ($action === 'upload_picture' && !empty($_FILES['profile_picture']['name'])) {
        $result = updateOwnProfilePicture((int) $me['id'], $_FILES['profile_picture']);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Profile picture updated.' : $result['error']);
        if ($result['ok']) {
            $profileUser = normalizeUserRecord($result['user'], $me);
        }
    }

    header('Location: ' . url('profile.php'));
    exit;
}

$picUrl = '';
if (userHasProfilePicture($profileUser)) {
    $picUrl = profilePictureUrl($profileUser);
    $relative = ltrim(str_replace('\\', '/', $profileUser['profile_picture'] ?? ''), '/');
    $fullPath = __DIR__ . '/' . $relative;
    if (is_file($fullPath)) {
        $picUrl .= '?v=' . filemtime($fullPath);
    }
}
$hasPassword = !empty($profileUser['password']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>My Profile</h2>
    <p>View and manage your account information.</p>
</div>

<div class="profile-layout">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Profile Overview</span>
        </div>
        <div class="card-body" style="text-align:center;">
            <div style="margin-bottom:1rem;">
                <?php if ($picUrl): ?>
                    <img src="<?= $picUrl ?>" alt="Profile picture"
                         style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--border);">
                <?php else: ?>
                    <div class="user-avatar" style="width:120px;height:120px;font-size:2rem;margin:0 auto;">
                        <?= strtoupper(substr($profileUser['full_name'] !== '' ? $profileUser['full_name'] : ($profileUser['email'] ?: 'U'), 0, 2)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 style="margin:0 0 0.25rem;"><?= sanitize($profileUser['full_name']) ?></h3>
            <p class="text-secondary" style="margin:0 0 0.75rem;" id="overviewEmail"><?= sanitize($profileUser['email']) ?></p>
            <span class="role-badge <?= roleBadgeClass($profileUser['role']) ?>"><?= roleLabel($profileUser['role']) ?></span>

            <dl class="profile-details-list">
                <div class="profile-detail-row">
                    <dt class="text-secondary">Username</dt>
                    <dd><?= sanitize($profileUser['username'] ?? '') !== '' ? sanitize($profileUser['username']) : 'Not set' ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt class="text-secondary">Phone</dt>
                    <dd><?= sanitize($profileUser['phone_number'] ?? '') !== '' ? sanitize($profileUser['phone_number']) : 'Not set' ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt class="text-secondary">Account type</dt>
                    <dd><?= roleLabel($profileUser['role'] ?? 'user') ?></dd>
                </div>
                <div class="profile-detail-row">
                    <dt class="text-secondary">Member since</dt>
                    <dd><?= !empty($profileUser['created_at']) ? date('F j, Y', strtotime($profileUser['created_at'])) : '—' ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Edit Profile</span>
            </div>
            <div class="card-body">
                <div id="profileFormWrap">
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
                        <input type="text" id="full_name" class="form-control" required
                               value="<?= sanitize($profileUser['full_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email_display">Email Address</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="email" id="email_display" class="form-control" readonly
                                   value="<?= sanitize($profileUser['email']) ?>"
                                   style="background:var(--surface-2,#f5f5f5);cursor:default;flex:1;">
                            <button type="button" class="btn btn-ghost" id="btnChangeEmail"
                                    style="white-space:nowrap;flex-shrink:0;font-size:.8rem;padding:8px 14px;">
                                Change Email
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" id="username" class="form-control"
                               placeholder="Optional"
                               value="<?= sanitize($profileUser['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" class="form-control"
                               placeholder="09XXXXXXXXX (11 digits)"
                               data-phone-numeric data-ph-phone
                               maxlength="11"
                               value="<?= sanitize($profileUser['phone_number'] ?? '') ?>">
                        <div id="phoneErr" style="font-size:.76rem;color:#ef4444;margin-top:4px;display:none;"></div>
                        <p class="text-sm text-secondary" style="margin-top:4px;">
                            Must start with <code>09</code>, be exactly 11 digits, and not use repetitive patterns (e.g. 09999999999).
                        </p>
                    </div>

                    <button type="button" class="btn btn-accent" id="btnSaveProfile">Save Changes</button>
                    <div id="profileSaveMsg" style="margin-top:8px;font-size:.83rem;display:none;"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">Profile Picture</span>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_picture">

                    <div class="form-group">
                        <label class="form-label" for="profile_picture">Upload Image</label>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control"
                               accept="image/jpeg,image/png,image/webp" required>
                        <p class="text-sm text-secondary" style="margin-top:6px;">JPG, PNG, or WEBP. Max 5MB.</p>
                    </div>

                    <button type="submit" class="btn btn-accent">Upload Picture</button>
                </form>
            </div>
        </div>

        <?php if ($hasPassword): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Change Password</span>
            </div>
            <div class="card-body">
                <form method="POST" action="" autocomplete="off">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password <span class="req">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required
                               autocomplete="current-password">
                    </div>

                    <div class="form-group" id="pw-field-group">
                        <label class="form-label" for="new_password">New Password <span class="req">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required
                               autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_new_password">Confirm New Password <span class="req">*</span></label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required
                               autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-accent">Update Password</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <p class="text-secondary text-sm" style="margin:0;">This account uses Google Sign-In. Password changes are managed through your Google account.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Profile Update OTP Modal ══ -->
<div id="profileOtpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:var(--surface-1,#fff);border-radius:16px;padding:32px 28px;max-width:400px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 6px;font-size:1.05rem;">Confirm Your Identity</h3>
        <p style="margin:0 0 18px;font-size:.85rem;color:var(--text-secondary);">
            Enter the 6-digit code sent to your email to save your profile changes.
        </p>
        <div style="display:flex;gap:8px;justify-content:center;margin-bottom:12px;" id="profileOtpBoxes">
            <input type="text" maxlength="1" inputmode="numeric" class="profile-otp-digit"
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            <input type="text" maxlength="1" inputmode="numeric" class="profile-otp-digit"
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            <input type="text" maxlength="1" inputmode="numeric" class="profile-otp-digit"
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            <input type="text" maxlength="1" inputmode="numeric" class="profile-otp-digit"
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            <input type="text" maxlength="1" inputmode="numeric" class="profile-otp-digit"
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            <input type="text" maxlength="1" inputmode="numeric" class="profile-otp-digit"
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
        </div>
        <div id="profileOtpMsg" style="font-size:.8rem;text-align:center;min-height:18px;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn btn-ghost" id="btnCancelProfileOtp">Cancel</button>
            <button type="button" class="btn btn-accent" id="btnVerifyProfileOtp">Verify</button>
        </div>
    </div>
</div>

<!-- ══ Change Email Modal ══ -->
<div id="changeEmailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:var(--surface-1,#fff);border-radius:16px;padding:32px 28px;max-width:420px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 6px;font-size:1.05rem;">Change Email Address</h3>
        <p style="margin:0 0 18px;font-size:.82rem;color:var(--text-secondary);">
            We'll first verify your current email, then your new one.
        </p>

        <!-- Step A: Verify current email -->
        <div id="ceStep1">
            <p style="font-size:.82rem;margin-bottom:8px;color:var(--text-secondary);">Step 1 — Verify your current email</p>
            <button type="button" class="btn btn-accent" id="btnSendCurrentOtp" style="width:100%;justify-content:center;margin-bottom:10px;">
                Send OTP to Current Email
            </button>
            <div style="display:flex;gap:6px;justify-content:center;margin-bottom:6px;" id="ceOtpBoxes1">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-1"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-1"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-1"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-1"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-1"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-1"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            </div>
            <button type="button" class="btn btn-primary" id="btnVerifyCurrentOtp" style="width:100%;justify-content:center;">
                Verify OTP
            </button>
            <div id="ceMsg1" style="font-size:.78rem;text-align:center;margin-top:8px;min-height:16px;"></div>
        </div>

        <!-- Step B: Enter new email + verify -->
        <div id="ceStep2" style="display:none;">
            <p style="font-size:.82rem;margin-bottom:8px;color:var(--text-secondary);">Step 2 — Enter and verify your new email</p>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                <input type="email" id="newEmailInput" class="form-control"
                       placeholder="new@example.com" style="flex:1;"
                       value="">
                <button type="button" class="btn btn-accent" id="btnSendNewEmailOtp"
                        style="white-space:nowrap;flex-shrink:0;font-size:.78rem;padding:9px 12px;">
                    Verify New Email
                </button>
            </div>
            <div id="ceMsg2" style="font-size:.78rem;margin-bottom:10px;min-height:16px;"></div>

            <div style="display:flex;gap:6px;justify-content:center;margin-bottom:8px;" id="ceOtpBoxes2">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-2"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-2"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-2"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-2"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-2"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
                <input type="text" maxlength="1" inputmode="numeric" class="ce-otp-digit-2"
                       style="width:40px;height:48px;text-align:center;font-size:18px;font-weight:700;border:2px solid var(--border);border-radius:8px;background:var(--surface-1);color:var(--text-primary);">
            </div>

            <button type="button" class="btn btn-accent" id="btnSaveEmailChange" style="width:100%;justify-content:center;">
                Save Changes
            </button>
            <div id="ceMsg3" style="font-size:.78rem;text-align:center;margin-top:6px;min-height:16px;"></div>
            <!-- Invalid OTP badge under Save Changes -->
            <div id="ceInvalidBadge" style="display:none;background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.25);border-radius:6px;padding:5px 12px;font-size:.78rem;font-weight:600;text-align:center;margin-top:6px;">
                Invalid OTP!
            </div>
        </div>

        <div style="margin-top:16px;display:flex;justify-content:flex-end;">
            <button type="button" class="btn btn-ghost" id="btnCloseChangeEmail">Close</button>
        </div>
    </div>
</div>

<?php if ($hasPassword): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initPasswordStrength === 'function') {
        initPasswordStrength('new_password', 'confirm_new_password', 'pw-field-group');
    }
});
</script>
<?php endif; ?>

<script>
(function () {
    'use strict';

    // ── Helpers ────────────────────────────────────────
    function getOtpVal(selector) {
        return Array.from(document.querySelectorAll(selector)).map(d => d.value).join('');
    }

    function wireOtpDigits(selector, onComplete) {
        const all = document.querySelectorAll(selector);
        all.forEach((el, i) => {
            el.addEventListener('input', () => {
                el.value = el.value.replace(/\D/, '');
                if (el.value && i < all.length - 1) all[i + 1].focus();
                if (Array.from(all).every(d => d.value) && onComplete) onComplete();
            });
            el.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !el.value && i > 0) all[i - 1].focus();
            });
        });
    }

    async function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        const res  = await fetch('', { method: 'POST', body: fd });
        return res.json();
    }

    function msg(id, text, ok) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = text;
        el.style.color  = ok ? '#16a34a' : '#ef4444';
    }

    // ── ══ Save Profile with OTP Modal ══ ──────────────
    const profileOtpModal = document.getElementById('profileOtpModal');
    let pendingProfileData = null;

    // ── PH phone validation ─────────────────────────────────
    function validatePhPhone(val) {
        const digits = val.replace(/\D/g, '');
        if (digits === '') return ''; // optional field
        if (!/^09\d{9}$/.test(digits)) return 'Phone must start with 09 and be exactly 11 digits.';
        // Check repeated pattern: all same digit, or repeating pairs
        if (/^(\d)\1{10}$/.test(digits)) return 'Phone number cannot be all the same digit.';
        // Check long repeated substring (e.g. 09121212121 — 4+ same consecutive)
        if (/(.{2,})\1{3,}/.test(digits)) return 'Phone number contains too many repeated patterns.';
        // Check 5+ consecutive repeated digit
        if (/(\d)\1{4,}/.test(digits)) return 'Phone number cannot have 5 or more consecutive repeated digits.';
        return '';
    }

    const phoneInput = document.getElementById('phone_number');
    const phoneErr   = document.getElementById('phoneErr');

    phoneInput?.addEventListener('input', function () {
        // Strip non-digits
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
        phoneErr.style.display = 'none';
    });

    document.getElementById('btnSaveProfile').addEventListener('click', async () => {
        const phoneVal = document.getElementById('phone_number').value.trim();
        const phoneErrMsg = validatePhPhone(phoneVal);
        if (phoneErrMsg) {
            phoneErr.textContent = phoneErrMsg;
            phoneErr.style.display = 'block';
            document.getElementById('phone_number').focus();
            return;
        }
        phoneErr.style.display = 'none';

        pendingProfileData = {
            full_name:    document.getElementById('full_name').value.trim(),
            phone_number: phoneVal,
            username:     document.getElementById('username').value.trim(),
        };

        // Send OTP first
        try {
            const data = await post({ ajax_action: 'send_profile_otp' });
            if (!data.ok) {
                document.getElementById('profileSaveMsg').style.display = 'block';
                document.getElementById('profileSaveMsg').style.color   = '#ef4444';
                document.getElementById('profileSaveMsg').textContent   = data.message || 'Could not send OTP.';
                return;
            }
        } catch(e) { return; }

        // Show modal
        document.querySelectorAll('.profile-otp-digit').forEach(d => { d.value = ''; });
        document.getElementById('profileOtpMsg').textContent = '';
        profileOtpModal.style.display = 'flex';
        document.querySelector('.profile-otp-digit').focus();
    });

    document.getElementById('btnCancelProfileOtp').addEventListener('click', () => {
        profileOtpModal.style.display = 'none';
        pendingProfileData = null;
    });

    wireOtpDigits('.profile-otp-digit');

    document.getElementById('btnVerifyProfileOtp').addEventListener('click', async () => {
        const code = getOtpVal('.profile-otp-digit');
        if (code.length < 6) { msg('profileOtpMsg', 'Please enter all 6 digits.', false); return; }

        try {
            const vData = await post({ ajax_action: 'verify_profile_otp', otp_code: code });
            if (!vData.ok) {
                msg('profileOtpMsg', vData.status === 'expired' ? 'OTP expired.' : 'Invalid OTP. Please try again.', false);
                document.querySelectorAll('.profile-otp-digit').forEach(d => d.value = '');
                document.querySelector('.profile-otp-digit').focus();
                return;
            }

            // OTP valid — now save
            const saveData = { ajax_action: 'save_profile', ...pendingProfileData };
            const sData = await post(saveData);
            profileOtpModal.style.display = 'none';

            const msgEl = document.getElementById('profileSaveMsg');
            msgEl.style.display  = 'block';
            msgEl.style.color    = sData.ok ? '#16a34a' : '#ef4444';
            msgEl.textContent    = sData.ok ? '✓ Profile saved successfully.' : (sData.message || 'Save failed.');

            if (sData.ok) {
                // Refresh page to reflect changes
                setTimeout(() => location.reload(), 800);
            }
        } catch(e) {
            msg('profileOtpMsg', 'Network error.', false);
        }
    });

    // ── ══ Change Email Modal ══ ────────────────────────
    const changeEmailModal = document.getElementById('changeEmailModal');
    let currentOtpVerified = false;

    document.getElementById('btnChangeEmail').addEventListener('click', () => {
        currentOtpVerified = false;
        document.getElementById('ceStep1').style.display = 'block';
        document.getElementById('ceStep2').style.display = 'none';
        document.querySelectorAll('.ce-otp-digit-1').forEach(d => d.value = '');
        document.getElementById('ceMsg1').textContent = '';
        changeEmailModal.style.display = 'flex';
    });

    document.getElementById('btnCloseChangeEmail').addEventListener('click', () => {
        changeEmailModal.style.display = 'none';
    });

    // Send OTP to current email
    document.getElementById('btnSendCurrentOtp').addEventListener('click', async () => {
        try {
            const data = await post({ ajax_action: 'send_current_email_otp' });
            msg('ceMsg1', data.ok ? 'OTP sent to your current email.' : (data.message || 'Failed to send.'), data.ok);
        } catch(e) { msg('ceMsg1', 'Network error.', false); }
    });

    wireOtpDigits('.ce-otp-digit-1');

    // Verify current email OTP
    document.getElementById('btnVerifyCurrentOtp').addEventListener('click', async () => {
        const code = getOtpVal('.ce-otp-digit-1');
        if (code.length < 6) { msg('ceMsg1', 'Please enter all 6 digits.', false); return; }

        try {
            const data = await post({ ajax_action: 'verify_current_email_otp', otp_code: code });
            if (data.ok) {
                currentOtpVerified = true;
                document.getElementById('ceStep1').style.display = 'none';
                document.getElementById('ceStep2').style.display = 'block';
                document.getElementById('newEmailInput').focus();
            } else {
                msg('ceMsg1', data.status === 'expired' ? 'OTP expired.' : 'Invalid OTP.', false);
                document.querySelectorAll('.ce-otp-digit-1').forEach(d => d.value = '');
                document.querySelector('.ce-otp-digit-1').focus();
            }
        } catch(e) { msg('ceMsg1', 'Network error.', false); }
    });

    // Send OTP to new email
    document.getElementById('btnSendNewEmailOtp').addEventListener('click', async () => {
        const newEmail = document.getElementById('newEmailInput').value.trim();
        if (!newEmail) { msg('ceMsg2', 'Please enter a new email address.', false); return; }
        document.querySelectorAll('.ce-otp-digit-2').forEach(d => d.value = '');
        document.getElementById('ceInvalidBadge').style.display = 'none';
        try {
            const data = await post({ ajax_action: 'send_new_email_otp', new_email: newEmail });
            msg('ceMsg2', data.ok ? 'OTP sent to ' + newEmail + '.' : (data.message || 'Failed.'), data.ok);
        } catch(e) { msg('ceMsg2', 'Network error.', false); }
    });

    wireOtpDigits('.ce-otp-digit-2');

    // Save new email
    document.getElementById('btnSaveEmailChange').addEventListener('click', async () => {
        const code = getOtpVal('.ce-otp-digit-2');
        if (code.length < 6) { msg('ceMsg3', 'Please enter all 6 digits.', false); return; }

        document.getElementById('ceInvalidBadge').style.display = 'none';
        // Highlight OTP boxes red while checking
        document.querySelectorAll('.ce-otp-digit-2').forEach(d => d.style.borderColor = '');

        try {
            const data = await post({ ajax_action: 'save_new_email', otp_code: code });
            if (data.ok) {
                changeEmailModal.style.display = 'none';
                // Update displayed email
                document.getElementById('email_display').value = data.new_email;
                document.getElementById('overviewEmail').textContent = data.new_email;
                msg('profileSaveMsg', '✓ Email updated successfully.', true);
                document.getElementById('profileSaveMsg').style.display = 'block';
            } else {
                // Invalid OTP: show badge, highlight field
                document.querySelectorAll('.ce-otp-digit-2').forEach(d => {
                    d.style.borderColor = '#ef4444';
                    d.style.borderWidth = '1px';
                });
                document.getElementById('ceInvalidBadge').style.display = 'block';
                document.getElementById('ceMsg3').textContent = '';
                // Clear digits
                document.querySelectorAll('.ce-otp-digit-2').forEach(d => d.value = '');
                document.querySelector('.ce-otp-digit-2').focus();
            }
        } catch(e) {
            msg('ceMsg3', 'Network error.', false);
        }
    });

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
