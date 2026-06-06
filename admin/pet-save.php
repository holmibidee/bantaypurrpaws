<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';
require_once __DIR__ . '/../includes/validation.php';

requireCanManagePetListings();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('admin/pets.php'));
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $id = (int) ($_POST['pet_id'] ?? 0);

    // ← CHANGED: removed $db arguments
    $pet = getPetById($id);
    if ($pet) {
        deletePetImageFile($pet['image']);
        foreach (getPetImages($id) as $img) {
            deletePetImageFile($img['image_path']);
        }
        // ← CHANGED: replaced PDO DELETE with db_delete()
        db_delete('pets', 'id=eq.' . $id);
        flash('success', 'Pet removed successfully.');
    } else {
        flash('error', 'Pet not found.');
    }
    header('Location: ' . url('admin/pets.php'));
    exit;
}

// Collect + sanitize inputs
$name         = sanitize(trim($_POST['name']                  ?? ''));
$breed        = sanitize(trim($_POST['breed']                 ?? ''));
$age          = sanitize(trim($_POST['age']                   ?? ''));
$gender       = $_POST['gender']                              ?? 'Unknown';
$vaccination  = sanitize(trim($_POST['vaccination_status']    ?? ''));
$health       = sanitize(trim($_POST['health_condition']      ?? ''));
$description  = sanitize(trim($_POST['description']           ?? ''));
$requirements = sanitize(trim($_POST['adoption_requirements'] ?? ''));
$rescueDate   = trim($_POST['rescue_date'] ?? '') ?: null;
$status       = $_POST['status']           ?? 'available';
$petId        = (int) ($_POST['pet_id']    ?? 0);

$validGender = ['Male', 'Female', 'Unknown'];
$validStatus = ['available', 'adopted'];

if ($name === '' || $breed === '' || $age === '') {
    flash('error', 'Name, breed, and age are required.');
    header('Location: ' . url('admin/pets.php' . ($petId ? '?edit=' . $petId : '')));
    exit;
}

$ageCheck = validatePetAge($age);
if (!$ageCheck['ok']) {
    flash('error', $ageCheck['error']);
    header('Location: ' . url('admin/pets.php' . ($petId ? '?edit=' . $petId : '?add=1')));
    exit;
}
$age = $ageCheck['value'];

if (!in_array($gender, $validGender, true)) $gender = 'Unknown';
if (!in_array($status, $validStatus, true)) $status = 'available';

// Handle primary image upload
$primaryPath = null;
if (!empty($_FILES['image']['name'])) {
    $upload = uploadPetImage($_FILES['image']);
    if (!$upload['ok']) {
        flash('error', $upload['error']);
        header('Location: ' . url('admin/pets.php' . ($petId ? '?edit=' . $petId : '?add=1')));
        exit;
    }
    $primaryPath = $upload['path'];
}

$data = [
    'name'                  => $name,
    'breed'                 => $breed,
    'age'                   => $age,
    'gender'                => $gender,
    'vaccination_status'    => $vaccination,
    'health_condition'      => $health,
    'description'           => $description,
    'adoption_requirements' => $requirements,
    'rescue_date'           => $rescueDate,
    'status'                => $status,
];

if ($petId) {
    // ── UPDATE existing pet ───────────────────────────────
    // ← CHANGED: removed $db argument
    $existing = getPetById($petId);
    if (!$existing) {
        flash('error', 'Pet not found.');
        header('Location: ' . url('admin/pets.php'));
        exit;
    }

    if ($primaryPath) {
        deletePetImageFile($existing['image']);
        $data['image'] = $primaryPath;
    } else {
        $data['image'] = $existing['image'];
    }

    // ← CHANGED: replaced PDO UPDATE with db_update()
    db_update('pets', $data, 'id=eq.' . $petId);

    // Save extra images
    if (!empty($_FILES['extra_images']['name'][0])) {
        // ← CHANGED: removed $db argument
        savePetExtraImages($petId, $_FILES['extra_images']);
    }

    // Delete checked extra images
    if (!empty($_POST['delete_image_ids']) && is_array($_POST['delete_image_ids'])) {
        foreach ($_POST['delete_image_ids'] as $imgId) {
            $imgId = (int) $imgId;
            // ← CHANGED: replaced PDO SELECT + DELETE with db_select() + db_delete()
            $img = db_select('pet_images', 'id=eq.' . $imgId . '&pet_id=eq.' . $petId . '&limit=1', true);
            if ($img) {
                deletePetImageFile($img['image_path']);
                db_delete('pet_images', 'id=eq.' . $imgId);
            }
        }
    }

    flash('success', 'Pet updated successfully.');

} else {
    // ── INSERT new pet ────────────────────────────────────
    if (!$primaryPath) {
        flash('error', 'Please upload a primary pet photo.');
        header('Location: ' . url('admin/pets.php?add=1'));
        exit;
    }

    $data['image'] = $primaryPath;

    // ← CHANGED: replaced PDO INSERT + lastInsertId() with db_insert()
    $newPet = db_insert('pets', $data);
    $petId  = $newPet ? (int) $newPet['id'] : 0;

    if ($petId && !empty($_FILES['extra_images']['name'][0])) {
        // ← CHANGED: removed $db argument
        savePetExtraImages($petId, $_FILES['extra_images']);
    }

    flash('success', 'Pet added successfully.');
}

header('Location: ' . url('admin/pets.php'));
exit;
