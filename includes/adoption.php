<?php
/**
 * BantayPurrPaws — Adoption Helper (MySQL)
 */

require_once __DIR__ . '/auth.php';

define('PET_UPLOAD_DIR',       dirname(__DIR__) . '/uploads/pets');
define('PET_UPLOAD_URL',       'uploads/pets');
define('PET_MAX_IMAGE_BYTES',  5 * 1024 * 1024);
define('PET_ALLOWED_MIMES',    ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']);
define('PET_ALLOWED_EXT',      ['jpg', 'jpeg', 'png', 'webp']);

// ── Image upload helpers ──────────────────────────────────
// These are unchanged — they deal with local files, not the DB

function ensurePetUploadDir(): void {
    if (!is_dir(PET_UPLOAD_DIR)) {
        mkdir(PET_UPLOAD_DIR, 0775, true);
    }
    if (!is_writable(PET_UPLOAD_DIR)) {
        @chmod(PET_UPLOAD_DIR, 0775);
    }
}

function petImageUrl(?string $path): string {
    if (!$path) return url('assets/pet-placeholder.svg');
    if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
        return str_starts_with($path, '/') ? url(ltrim($path, '/')) : $path;
    }
    return url($path);
}

function uploadPetImage(array $file): array {
    ensurePetUploadDir();
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        $phpErrMsg = match($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image file is too large. Maximum allowed size is 5MB.',
            UPLOAD_ERR_NO_FILE   => 'No image file was selected.',
            UPLOAD_ERR_PARTIAL   => 'Image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Server configuration error: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE=> 'Server error: could not write file to disk.',
            default              => 'Image upload failed (error ' . $err . '). Please try again.',
        };
        return ['ok' => false, 'error' => $phpErrMsg];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, PET_ALLOWED_EXT, true)) {
        return ['ok' => false, 'error' => 'Only JPG, JPEG, PNG, and WEBP images are allowed.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, PET_ALLOWED_MIMES, true)) {
        return ['ok' => false, 'error' => 'Invalid image file type.'];
    }
    if (($file['size'] ?? 0) > PET_MAX_IMAGE_BYTES) {
        return ['ok' => false, 'error' => 'Image must be under 5MB.'];
    }
    $filename = uniqid('pet_', true) . '.' . $ext;
    $dest     = PET_UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save uploaded image.'];
    }
    return ['ok' => true, 'path' => PET_UPLOAD_URL . '/' . $filename];
}

function deletePetImageFile(?string $path): void {
    if (!$path || str_contains($path, '..')) return;
    $full = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $path), '/');
    if (is_file($full)) @unlink($full);
}

// ── Status formatters ─────────────────────────────────────

function formatPetStatus(string $status): array {
    return match ($status) {
        'available'        => ['label' => 'Available',                'class' => 'pet-status-available'],
        'pending_adoption' => ['label' => 'Pending Adoption Approval','class' => 'pet-status-pending'],
        'adopted'          => ['label' => 'Adopted',                  'class' => 'pet-status-adopted'],
        default            => ['label' => ucfirst($status),           'class' => 'pet-status-available'],
    };
}

function formatApplicationStatus(string $status): array {
    return match ($status) {
        'pending'  => ['label' => 'Pending',  'class' => 'status-pending'],
        'approved' => ['label' => 'Approved', 'class' => 'status-rescued'],
        'rejected' => ['label' => 'Rejected', 'class' => 'status-failed'],
        default    => ['label' => ucfirst($status), 'class' => 'status-pending'],
    };
}

// ── Pet queries ───────────────────────────────────────────

function getPetById(int $id): ?array {
    return db_select('pets', 'id=eq.' . $id . '&limit=1', true);
}

function getPetImages(int $petId): array {
    return db_select('pet_images', 'pet_id=eq.' . $petId . '&order=sort_order.asc,id.asc');
}

function getPetGallery(array $pet): array {
    $images = getPetImages((int) $pet['id']);
    $paths  = [];
    if (!empty($pet['image'])) $paths[] = $pet['image'];
    foreach ($images as $img) {
        if ($img['image_path'] !== $pet['image']) $paths[] = $img['image_path'];
    }
    if (empty($paths)) $paths[] = null;
    return $paths;
}

function petCanReceiveApplications(int $petId): bool {
    $pet = getPetById($petId);
    // Allow approval if the pet is still available OR has a pending_adoption application
    // (status is only changed to 'adopted' when admin explicitly approves, not on submission)
    return $pet && in_array($pet['status'], ['available', 'pending_adoption'], true);
}

function userHasPendingApplication(int $userId, int $petId): bool {
    $row = db_select(
        'adoption_applications',
        'user_id=eq.' . $userId . '&pet_id=eq.' . $petId . '&status=eq.pending&limit=1',
        true
    );
    return $row !== null;
}

// ── Stats ─────────────────────────────────────────────────

/**
 * FIX #1: Use db_count() with filters instead of fetching full tables.
 * This avoids loading thousands of rows just to count them.
 */
function getAdoptionStats(): array {
    return [
        'total_pets'           => db_count('pets'),
        'available_pets'       => db_count('pets', 'status=eq.available'),
        'pending_applications' => db_count('adoption_applications', 'status=eq.pending'),
        'approved_adoptions'   => db_count('adoption_applications', 'status=eq.approved'),
    ];
}

// ── Notifications ─────────────────────────────────────────

require_once __DIR__ . '/notifications.php';

function createAdoptionNotification(int $applicationId, string $applicantName, string $petName): void {
    $message = trim($applicantName) . ' applied to adopt ' . trim($petName);
    createSystemNotification(
        'adoption',
        $message,
        'admin/application.php?id=' . $applicationId,
        $applicationId
    );
}

// ── Extra images ──────────────────────────────────────────

function savePetExtraImages(int $petId, array $files): void {
    if (empty($files['name']) || !is_array($files['name'])) return;

    $existing = db_select('pet_images', 'pet_id=eq.' . $petId . '&order=sort_order.desc&limit=1');
    $order    = !empty($existing) ? (int) $existing[0]['sort_order'] : 0;

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $file = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $result = uploadPetImage($file);
        if ($result['ok']) {
            $order++;
            db_insert('pet_images', [
                'pet_id'     => $petId,
                'image_path' => $result['path'],
                'sort_order' => $order,
            ]);
        }
    }
}

// ── Pet to JSON ───────────────────────────────────────────

function petToJson(array $pet, bool $includeRequirements = true): array {
    $gallery  = getPetGallery($pet);
    $canAdopt = $pet['status'] === 'available';

    if (isLoggedIn()) {
        $user = currentUser();
        if (userHasPendingApplication((int) $user['id'], (int) $pet['id'])) {
            $canAdopt = false;
        }
    }

    return [
        'id'                    => (int) $pet['id'],
        'name'                  => $pet['name'],
        'breed'                 => $pet['breed'],
        'age'                   => $pet['age'],
        'gender'                => $pet['gender'],
        'vaccination_status'    => $pet['vaccination_status'] ?? '',
        'health_condition'      => $pet['health_condition'] ?? '',
        'description'           => $pet['description'] ?? '',
        'adoption_requirements' => $includeRequirements ? ($pet['adoption_requirements'] ?? '') : '',
        'rescue_date'           => $pet['rescue_date'] ? date('M j, Y', strtotime($pet['rescue_date'])) : '—',
        'status'                => $pet['status'],
        'status_label'          => formatPetStatus($pet['status'])['label'],
        'images'                => array_map(fn($p) => petImageUrl($p), $gallery),
        'can_adopt'             => $canAdopt,
    ];
}
