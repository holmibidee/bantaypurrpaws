<?php
/**
 * BantayPurrPaws — Application Action
 * Approval runs in a single MySQL transaction via approveAdoption().
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';
require_once __DIR__ . '/../includes/submission-notifications.php';

requireCanReviewAdoptionApplications();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('admin/adoption-requests.php'));
    exit;
}

$applicationId = (int) ($_POST['application_id'] ?? 0);
$action        = $_POST['action'] ?? '';

$app = db_select('adoption_applications', 'id=eq.' . $applicationId . '&limit=1', true);

if (!$app) {
    flash('error', 'Application not found.');
    header('Location: ' . url('admin/adoption-requests.php'));
    exit;
}

if ($app['status'] !== 'pending') {
    flash('error', 'This application has already been processed.');
    header('Location: ' . url('admin/application.php?id=' . $applicationId));
    exit;
}

if ($action === 'approve') {
    if (!petCanReceiveApplications((int) $app['pet_id'])) {
        flash('error', 'This pet is no longer available for adoption.');
        header('Location: ' . url('admin/application.php?id=' . $applicationId));
        exit;
    }

    try {
        approveAdoption($applicationId, (int) $app['pet_id']);
    } catch (Throwable $e) {
        error_log('approve adoption: ' . $e->getMessage());
        flash('error', 'Could not approve application. Please try again.');
        header('Location: ' . url('admin/application.php?id=' . $applicationId));
        exit;
    }

    try {
        markNotificationsReadForApplication($applicationId);
    } catch (Throwable $e) {
        error_log('mark notifications after approve: ' . $e->getMessage());
    }

    $petRow  = getPetById((int) $app['pet_id']);
    $petName = $petRow['name'] ?? 'your pet';

    try {
        $mailed = notifyPetSubmissionApproved($app, $petName);
    } catch (Throwable $e) {
        error_log('approval email: ' . $e->getMessage());
        $mailed = false;
    }

    flash('success', 'Application approved. Pet marked as adopted.'
        . ($mailed ? ' Approval email sent.' : ' (Email could not be sent.)'));

} elseif ($action === 'reject') {
    db_update('adoption_applications', ['status' => 'rejected'], 'id=eq.' . $applicationId);
    markNotificationsReadForApplication($applicationId);

    $petRow  = getPetById((int) $app['pet_id']);
    $petName = $petRow['name'] ?? 'the pet';
    try {
        $mailed = notifyPetSubmissionRejected($app, $petName);
    } catch (Throwable $e) {
        error_log('rejection email: ' . $e->getMessage());
        $mailed = false;
    }
    flash('success', 'Application rejected.'
        . ($mailed ? ' Rejection email sent.' : ' (Email could not be sent.)'));

} else {
    flash('error', 'Invalid action.');
}

header('Location: ' . url('admin/application.php?id=' . $applicationId));
exit;
