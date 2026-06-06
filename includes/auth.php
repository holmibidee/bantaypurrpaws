<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/sensitive-data.php';

// ── Session ───────────────────────────────────────────────

function sessionCookiePath(): string {
    $host = request_host();
    if (preg_match('/\.(infinityfree\.me|infinityfreeapp\.com|rf\.gd|42web\.io)$/i', $host)) {
        return '/';
    }
    $base = app_base();
    return $base === '' ? '/' : $base;
}

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => sessionCookiePath(),
            'secure'   => request_scheme() === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

/** Administrator or Staff (admin area). */
function requireAdmin() {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
        header('Location: ' . url('dashboard.php'));
        exit;
    }
}

/** Administrator only. */
function requireAdminOnly() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

function currentUser() {
    startSession();
    return [
        'id'       => $_SESSION['user_id']   ?? null,
        'name'     => $_SESSION['full_name'] ?? '',
        'email'    => $_SESSION['email']     ?? '',
        'role'     => $_SESSION['role']      ?? 'user',
        'avatar'   => $_SESSION['avatar']    ?? '',
        'username' => $_SESSION['username']  ?? '',
        'phone'    => $_SESSION['phone']      ?? '',
    ];
}

/** Human-readable role label. */
function roleLabel(?string $role = null): string {
    $role = $role ?? ($_SESSION['role'] ?? 'user');
    return match ($role) {
        'admin' => 'Administrator',
        'staff' => 'Staff',
        default => 'User',
    };
}

/** CSS class suffix for role badges (role-administrator, etc.). */
function roleBadgeClass(?string $role = null): string {
    $role = $role ?? ($_SESSION['role'] ?? 'user');
    return match ($role) {
        'admin' => 'role-administrator',
        'staff' => 'role-staff',
        default => 'role-user',
    };
}

function isAdmin() {
    startSession();
    return in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true);
}

/** Administrator — full system access. */
function isAdministrator(): bool {
    startSession();
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** @deprecated Use isAdministrator() */
function isSiteAdmin(): bool {
    return isAdministrator();
}

function isStaff(): bool {
    startSession();
    return ($_SESSION['role'] ?? '') === 'staff';
}

function isEndUser(): bool {
    startSession();
    return ($_SESSION['role'] ?? '') === 'user';
}

// ── Permissions ───────────────────────────────────────────

/** Approve or reject pending rescue reports. */
function canApproveOrRejectRescueReports(): bool {
    return isAdministrator();
}

/** Update rescue report status (operational workflow). */
function canUpdateRescueReportStatus(): bool {
    return isAdmin();
}

/** Whether staff may change status on this report (not while pending). */
function canUpdateRescueReport(array $report): bool {
    if (isAdministrator()) {
        return true;
    }
    if (!isStaff()) {
        return false;
    }
    $s = $report['status'] ?? ''; return $s !== 'pending' && $s !== 'submitted';
}

// ── RBAC helpers ──────────────────────────────────────────────────────────────
// Staff accounts may have a JSON `staff_permissions` array in the session.
// Admins always pass every permission check.
// Staff without any stored permissions fall back to the safe-minimal defaults.
//
// Permission keys:
//   manage_reports      – view + update rescue reports
//   manage_pets         – create / edit / delete pet listings
//   review_adoptions    – approve / reject adoption applications
//   view_adoptions      – view adoption queue (read-only)
//   manage_users        – manage regular user accounts
//   manage_staff        – create / delete staff accounts
//   post_announcements  – publish site announcements
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Load staff_permissions from the session.
 * Returns null if the user is not a staff member or no overrides are stored.
 */
function staffPermissions(): ?array {
    startSession();
    if (($_SESSION['role'] ?? '') !== 'staff') {
        return null;
    }
    $raw = $_SESSION['staff_permissions'] ?? null;
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
    return null; // No stored permissions → use role defaults
}

/**
 * Check whether the current user holds a named permission.
 *   - Admins always return true.
 *   - Staff with an explicit permissions list check the list.
 *   - Staff without a permissions list fall back to $defaultForStaff.
 *   - Regular users always return false.
 */
function hasPermission(string $key, bool $defaultForStaff = false): bool {
    if (isAdministrator()) {
        return true;
    }
    if (!isStaff()) {
        return false;
    }
    $perms = staffPermissions();
    if ($perms === null) {
        return $defaultForStaff;
    }
    return in_array($key, $perms, true);
}

/** Manage staff and user accounts (admin-only by default). */
function canManageAccounts(): bool {
    return hasPermission('manage_staff', false);
}

/** Post system-wide announcements. */
function canPostAnnouncements(): bool {
    return hasPermission('post_announcements', false);
}

/** Create, edit, and delete pet listings. */
function canManagePetListings(): bool {
    return hasPermission('manage_pets', true); // staff default: allowed
}

/** Approve or reject adoption applications. */
function canReviewAdoptionApplications(): bool {
    return hasPermission('review_adoptions', false);
}

/** View adoption queue and application details. */
function canViewAdoptionApplications(): bool {
    return hasPermission('view_adoptions', true); // staff default: allowed
}

// ── Route guards ──────────────────────────────────────────
// These enforce RBAC: admins always pass; staff are checked against their
// stored permissions; regular users are always redirected out.

function requireCanViewAdoptionApplications(): void {
    requireAdmin(); // ensures logged-in admin or staff
    if (!canViewAdoptionApplications()) {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

function requireCanReviewAdoptionApplications(): void {
    requireAdmin(); // allows admin OR staff with the permission
    if (!canReviewAdoptionApplications()) {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

function requireCanManagePetListings(): void {
    requireAdmin();
    if (!canManagePetListings()) {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

function requireCanManageAccounts(): void {
    requireAdmin();
    if (!canManageAccounts()) {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

function requireCanPostAnnouncements(): void {
    requireAdmin();
    if (!canPostAnnouncements()) {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

/** Manage rescue reports. Staff with manage_reports permission can access. */
function canManageReports(): bool {
    return hasPermission('manage_reports', true); // staff default: allowed
}

function requireCanManageReports(): void {
    requireAdmin();
    if (!canManageReports()) {
        header('Location: ' . url('admin/dashboard.php'));
        exit;
    }
}

/** @deprecated Use requireCanReviewAdoptionApplications() */
function requireCanReviewApplications(): void {
    requireCanReviewAdoptionApplications();
}

function adminAreaTitle(): string {
    if (isAdministrator()) {
        return 'Administrator Dashboard';
    }
    if (isStaff()) {
        return 'Staff Dashboard';
    }
    return 'Dashboard';
}

function dashboardHomeUrl(): string {
    return isAdmin() ? url('admin/dashboard.php') : url('dashboard.php');
}

function flash($key, $message = null) {
    startSession();
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

// ── Login ─────────────────────────────────────────────────

function loginUser(string $email, string $password): array|string {
    $user = findUserByEmail($email);

    if (!$user) {
        return 'Invalid email or password. Please try again.';
    }

    if (empty($user['password']) && ($user['auth_provider'] ?? '') === 'google') {
        return 'This account uses Google Sign-In. Please click "Sign in with Google".';
    }

    if (!password_verify($password, $user['password'])) {
        return 'Invalid email or password. Please try again.';
    }

    require_once __DIR__ . '/users.php';
    refreshUserSession($user);

    return $user;
}

function emailExists(string $email): bool {
    return findUserByEmail($email) !== null;
}

function createUser(string $fullName, string $email, string $hashedPassword): ?int {
    require_once __DIR__ . '/users.php';
    $user = insertUserRecord([
        'full_name'      => $fullName,
        'email'          => $email,
        'password'       => $hashedPassword,
        'role'           => 'user',
        'email_verified' => true,
        'auth_provider'  => 'local',
    ]);

    return $user ? (int) $user['id'] : null;
}

function formatStatus($status) {
    return match($status) {
        'submitted'   => ['label' => 'Submitted',   'class' => 'status-submitted'],
        'pending'     => ['label' => 'Submitted',   'class' => 'status-submitted'], // legacy alias
        'in_progress' => ['label' => 'In Progress', 'class' => 'status-progress'],
        'rescued'     => ['label' => 'Rescued',     'class' => 'status-rescued'],
        'failed'      => ['label' => 'Failed',      'class' => 'status-failed'],
        default       => ['label' => ucfirst($status), 'class' => 'status-pending'],
    };
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d === 0 && $diff->h === 0) return $diff->i . 'm ago';
    if ($diff->d === 0) return $diff->h . 'h ago';
    if ($diff->d < 7)   return $diff->d . 'd ago';
    return $ago->format('M j, Y');
}
