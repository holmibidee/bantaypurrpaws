<?php
/**
 * Email notifications for adoption (pet submission) and rescue report decisions.
 */
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sensitive-data.php';
require_once __DIR__ . '/logger.php';

function submissionNotifySafe(callable $sender, string $context): bool {
    try {
        if (!is_callable($sender)) {
            bpp_log('submission-notify', 'error', 'Missing handler.', ['context' => $context]);
            return false;
        }
        return (bool) $sender();
    } catch (Throwable $e) {
        bpp_log('submission-notify', 'error', 'Notification failed.', ['context' => $context, 'error' => $e->getMessage()]);
        return false;
    }
}

function notifyPetSubmissionApproved(array $application, string $petName): bool {
    $application = hydrateAdoptionApplication($application);
    $email = trim($application['email'] ?? '');
    $name  = $application['full_name'] ?? 'Applicant';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        bpp_log('submission-notify', 'error', 'Missing applicant email.', ['application_id' => $application['id'] ?? null]);
        return false;
    }
    return submissionNotifySafe(
        static fn () => sendPetSubmissionApprovedEmail($email, $name, $petName),
        'pet submission approved'
    );
}

function notifyPetSubmissionRejected(array $application, string $petName): bool {
    $application = hydrateAdoptionApplication($application);
    $email = trim($application['email'] ?? '');
    $name  = $application['full_name'] ?? 'Applicant';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        bpp_log('submission-notify', 'error', 'Missing applicant email.', ['application_id' => $application['id'] ?? null]);
        return false;
    }
    return submissionNotifySafe(
        static fn () => sendPetSubmissionRejectedEmail($email, $name, $petName),
        'pet submission rejected'
    );
}

function reportSubmitterEmail(array $report): ?string {
    $reporterId = (int) ($report['reporter_id'] ?? 0);
    if ($reporterId > 0) {
        $user = db_select('users', 'id=eq.' . $reporterId . '&limit=1', true);
        if ($user) {
            $user = hydrateUserSensitiveFields($user);
            if (!empty($user['email'])) {
                return $user['email'];
            }
        }
    }
    return null;
}

function reportSubmitterName(array $report): string {
    if (!empty($report['reporter_name'])) {
        return $report['reporter_name'];
    }
    $reporterId = (int) ($report['reporter_id'] ?? 0);
    if ($reporterId > 0) {
        $user = db_select('users', 'id=eq.' . $reporterId . '&select=full_name&limit=1', true);
        if (!empty($user['full_name'])) {
            return $user['full_name'];
        }
    }
    return 'Reporter';
}

function notifyReportApproved(array $report): bool {
    $email = reportSubmitterEmail($report);
    if (!$email) {
        error_log('[SubmissionNotify] No email for report ' . ($report['id'] ?? '?'));
        return false;
    }
    if (!function_exists('sendReportApprovedEmail')) {
        error_log('[SubmissionNotify] sendReportApprovedEmail() is not defined.');
        return false;
    }
    return submissionNotifySafe(
        static fn () => sendReportApprovedEmail($email, reportSubmitterName($report), $report['report_code'] ?? 'Report'),
        'report approved'
    );
}

function notifyReportRejected(array $report): bool {
    $email = reportSubmitterEmail($report);
    if (!$email) {
        error_log('[SubmissionNotify] No email for report ' . ($report['id'] ?? '?'));
        return false;
    }
    if (!function_exists('sendReportRejectedEmail')) {
        error_log('[SubmissionNotify] sendReportRejectedEmail() is not defined.');
        return false;
    }
    return submissionNotifySafe(
        static fn () => sendReportRejectedEmail($email, reportSubmitterName($report), $report['report_code'] ?? 'Report'),
        'report rejected'
    );
}
