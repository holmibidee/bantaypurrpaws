<?php
/**
 * BantayPurrPaws — Adoption Submit API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/sensitive-data.php';
require_once __DIR__ . '/../includes/users.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user  = currentUser();
$userId = (int) $user['id'];
$petId = (int) ($_POST['pet_id'] ?? 0);

if (!$petId || !petCanReceiveApplications($petId)) {
    echo json_encode(['success' => false, 'message' => 'This pet is not available for adoption.']);
    exit;
}

if (userHasPendingApplication($userId, $petId)) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending application for this pet.']);
    exit;
}

// Get full user for auto-filled fields
$fullUser = getUserById($userId);
$fullName    = $fullUser['full_name'] ?? '';
$contactRaw  = $fullUser['phone_number'] ?? '';

// Validate phone — must be set
if ($contactRaw === '' || $contactRaw === null) {
    echo json_encode(['success' => false, 'message' => 'Please add a phone number to your profile before applying.']);
    exit;
}

$phoneCheck = validatePhoneNumber($contactRaw);
if (!$phoneCheck['ok']) {
    echo json_encode(['success' => false, 'message' => $phoneCheck['error']]);
    exit;
}
$contact = $phoneCheck['value'];

// Collect + validate remaining inputs
$occupation    = sanitize(trim($_POST['occupation']     ?? ''));
$existingPets  = strtolower(sanitize(trim($_POST['existing_pets'] ?? '')));
$scheduleDate  = trim($_POST['schedule_date']           ?? '');
$scheduleTime  = trim($_POST['schedule_time']           ?? '');
$agreement     = isset($_POST['agreement']);

$errors = [];
if ($fullName === '')                                    $errors[] = 'Full name is missing from your profile.';
if ($occupation === '')                                  $errors[] = 'Occupation is required.';
if (!in_array($existingPets, ['yes', 'no'], true))      $errors[] = 'Please specify if you have existing pets.';
if ($scheduleDate === '')                                $errors[] = 'Please select a meeting date.';
if ($scheduleTime === '')                                $errors[] = 'Please select a meeting time.';
if (!$agreement)                                        $errors[] = 'You must agree to the adoption terms.';

// Validate schedule date: today up to 1 month forward
if ($scheduleDate !== '') {
    $ts  = strtotime($scheduleDate);
    $min = strtotime('today');
    $max = strtotime('+1 month');
    if ($ts < $min || $ts > $max) {
        $errors[] = 'Meeting date must be from today and up to 1 month ahead.';
    }
}

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$pet = getPetById($petId);

try {
    $application = db_insert('adoption_applications', [
        'pet_id'         => $petId,
        'user_id'        => $userId,
        'full_name'      => $fullName,
        'contact_number' => protectSubmissionPhone($contact),
        'email'          => protectSubmissionEmail($fullUser['email'] ?? ''),
        'address'        => null,
        'occupation'     => $occupation,
        'reason_for_adoption' => null,
        'home_type'      => null,
        'existing_pets'  => $existingPets,
        'schedule_date'  => $scheduleDate,
        'schedule_time'  => $scheduleTime,
        'agreement'      => true,
        'status'         => 'pending',
    ]);

    if (!$application) {
        throw new RuntimeException('Insert returned null.');
    }

    // NOTE: Pet status remains 'available' until an admin explicitly approves an adoption application.
    // This allows the admin to review and approve from the admin panel without hitting 'not available' errors.

    $applicationId = (int) $application['id'];
    createAdoptionNotification($applicationId, $fullName, $pet['name']);

    echo json_encode([
        'success' => true,
        'message' => 'Your adoption application has been submitted! Our team will review it shortly.',
    ]);

} catch (Throwable $e) {
    require_once __DIR__ . '/../includes/logger.php';
    bpp_log('adoption', 'error', 'Application submit failed.', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not submit application. Please try again.']);
}
