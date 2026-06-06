<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/sensitive-data.php';
require_once __DIR__ . '/includes/notifications.php';
requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pageTitle = 'Submit Rescue Report';
$user  = currentUser();
$error = '';
$extraJs = ['js/phone-input.js'];

define('REPORT_UPLOAD_DIR', __DIR__ . '/uploads/reports');

function ensureReportUploadDir(): void {
    if (!is_dir(REPORT_UPLOAD_DIR)) {
        mkdir(REPORT_UPLOAD_DIR, 0755, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporterName   = sanitize($_POST['reporter_name'] ?? '');
    $contactRaw     = trim($_POST['contact_number'] ?? '');
    $location       = sanitize($_POST['location'] ?? '');
    $animalType     = sanitize($_POST['animal_type'] ?? '');
    $description    = sanitize($_POST['description'] ?? '');

    $phoneCheck = validatePhoneNumber($contactRaw);

    if (empty($reporterName) || empty($location)) {
        $error = 'Please fill out all required fields.';
    } elseif (!$phoneCheck['ok']) {
        $error = $phoneCheck['error'];
    } elseif (empty($_FILES['photo']['name']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please upload a photo as proof.';
    } else {
        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes, true)) {
            $error = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Photo must be under 5MB.';
        } else {
            ensureReportUploadDir();
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('report_', true) . '.' . $ext;
            $dest     = REPORT_UPLOAD_DIR . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Failed to upload photo. Please try again.';
            } else {
                $photoPath = 'uploads/reports/' . $filename;

                try {
                    $db         = getDB();
                    $reportCode = generateReportCode();
                    $userId     = (int) $user['id'];

                    $stmt = $db->prepare(
                        'INSERT INTO rescue_reports
                        (report_code, reporter_id, reporter_name, contact_number, location, animal_type, description, photo_path, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $reportCode,
                        $userId,
                        $reporterName,
                        protectSubmissionPhone($phoneCheck['value']),
                        $location,
                        $animalType !== '' ? $animalType : null,
                        $description !== '' ? $description : null,
                        $photoPath,
                        'pending',
                    ]);

                    $reportId = (int) $db->lastInsertId();

                    $logStmt = $db->prepare(
                        'INSERT INTO report_logs (report_id, updated_by, old_status, new_status, notes)
                         VALUES (?, ?, NULL, ?, ?)'
                    );
                    $logStmt->execute([$reportId, $userId, 'pending', 'Report submitted by user.']);

                    createReportNotification($reportId, $reportCode, $reporterName);

                    flash('success', "Report {$reportCode} submitted successfully! Our team will respond shortly.");
                    header('Location: ' . url('view-report.php?id=' . $reportId));
                    exit;
                } catch (Throwable $e) {
                    require_once __DIR__ . '/includes/logger.php';
                    bpp_log('reports', 'error', 'Report submit failed.', ['error' => $e->getMessage()]);
                    @unlink($dest);
                    $error = 'Could not save your report. Please try again. (' . htmlspecialchars($e->getMessage()) . ')';
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2>Submit a Rescue Report</h2>
    <p>Fill in the details below to alert a rescue team about a stray animal in need.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">✕ <?= sanitize($error) ?></div>
<?php endif; ?>

<div class="form-narrow">
<div class="card">
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" id="reportForm">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="reporter_name">Full Name</label>
                    <input
                        type="text"
                        id="reporter_name"
                        name="reporter_name"
                        class="form-control"
                        value="<?= sanitize($user['full_name'] ?? $user['name'] ?? '') ?>"
                        readonly
                        style="background:var(--surface-2,#f5f5f5);cursor:default;color:var(--text-secondary);"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_number">Contact Number</label>
                    <input
                        type="tel"
                        id="contact_number"
                        name="contact_number"
                        class="form-control"
                        value="<?= sanitize($user['phone_number'] ?? $user['phone'] ?? '') ?>"
                        readonly
                        style="background:var(--surface-2,#f5f5f5);cursor:default;color:var(--text-secondary);"
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="location">Location <span class="req">*</span></label>
                <input
                    type="text"
                    id="location"
                    name="location"
                    class="form-control"
                    placeholder="Street address, landmark, or description of location"
                    value="<?= sanitize($_POST['location'] ?? '') ?>"
                    required
                >
            </div>


            <div class="form-group">
                <label class="form-label" for="animal_type">Animal Type</label>
                <input
                    type="text"
                    id="animal_type"
                    name="animal_type"
                    class="form-control"
                    placeholder="e.g., Dog, Cat, Bird, Stray"
                    value="<?= sanitize($_POST['animal_type'] ?? '') ?>"
                >
                <p class="form-hint">Optional — specify the type or species of the animal.</p>
            </div>



            <div class="form-group">
                <label class="form-label" for="description">Additional Description</label>
                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    rows="4"
                    placeholder="Describe the animal's condition, behavior, urgency, or any other relevant details..."
                ><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Photo Proof <span class="req">*</span></label>
                <div class="file-upload-area" id="dropZone">
                    <input type="file" name="photo" id="photoInput" accept="image/*" required>
                    <div class="upload-icon">📷</div>
                    <p>Click to upload or drag &amp; drop a photo</p>
                    <span>JPG, PNG, GIF or WEBP — max 5MB</span>
                    <div class="upload-preview" id="uploadPreview">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                </div>
                <p class="form-hint">Please include a clear photo of the animal or its surroundings.</p>
            </div>

            <div class="flex gap-3" style="margin-top:8px;">
                <button type="submit" class="btn btn-accent">🐾 Submit Report</button>
                <a href="<?= url('dashboard.php') ?>" class="btn btn-ghost">Cancel</a>
            </div>

        </form>
    </div>
</div>
</div>

<!-- No Phone Modal -->
<div id="noPhoneModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--surface-1,#fff);border-radius:16px;padding:32px 28px;max-width:380px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:12px;">📱</div>
        <h3 style="margin:0 0 10px;font-size:1.1rem;color:var(--text-primary);">Contact Number Required</h3>
        <p style="margin:0 0 20px;font-size:.875rem;color:var(--text-secondary);line-height:1.6;">
            You haven't added your mobile phone yet. Please add your contact number in your profile before submitting a report.
        </p>
        <a href="<?= url('profile.php') ?>" class="btn btn-accent" style="display:inline-block;margin-bottom:10px;text-decoration:none;">
            Go to My Profile
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Check if phone is empty — show modal before form can be submitted
    const phoneVal = document.getElementById('contact_number').value.trim();
    if (!phoneVal) {
        const modal = document.getElementById('noPhoneModal');
        modal.style.display = 'flex';
        // Disable submit button
        const submitBtn = document.querySelector('#reportForm button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
    }

    const dropZone    = document.getElementById('dropZone');
    const photoInput  = document.getElementById('photoInput');
    const preview     = document.getElementById('uploadPreview');
    const previewImg  = document.getElementById('previewImg');

    dropZone.addEventListener('click', () => photoInput.click());

    photoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    ['dragover', 'dragenter'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, () => dropZone.classList.remove('dragover'));
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file) {
            photoInput.files = e.dataTransfer.files;
            const reader = new FileReader();
            reader.onload = (ev) => {
                previewImg.src = ev.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
