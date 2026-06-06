<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';
require_once __DIR__ . '/../includes/sensitive-data.php';

requireCanViewAdoptionApplications();

$id = (int) ($_GET['id'] ?? 0);

$appRows = db_select('adoption_applications', 'id=eq.' . $id . '&limit=1');
$app = $appRows[0] ?? null;

if ($app) {
    $app = hydrateAdoptionApplication($app);
    $petRows = db_select('pets', 'id=eq.' . (int) $app['pet_id'] . '&select=name,breed,image,status&limit=1');
    $pet = $petRows[0] ?? [];
    $app['pet_name']   = $pet['name']   ?? 'Unknown Pet';
    $app['breed']      = $pet['breed']  ?? '';
    $app['pet_image']  = $pet['image']  ?? '';
    $app['pet_status'] = $pet['status'] ?? 'unknown';
}

if (!$app) {
    flash('error', 'Application not found.');
    header('Location: ' . url('admin/adoption-requests.php'));
    exit;
}

markNotificationsReadForApplication($id);

$pageTitle = 'Adoption Application';
$st        = formatApplicationStatus($app['status']);
$useSweetAlert = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <a href="<?= url('admin/adoption-requests.php') ?>" class="btn btn-ghost btn-sm">← Back to requests</a>
    <h2 class="mt-2">Application #<?= (int) $app['id'] ?></h2>
    <p>Submitted <?= timeAgo($app['created_at']) ?> · Pet: <strong><?= sanitize($app['pet_name']) ?></strong></p>
</div>

<div class="adoption-application-layout">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Applicant Information</span>
            <span class="status-badge <?= $st['class'] ?>"><span class="status-dot"></span><?= $st['label'] ?></span>
        </div>
        <dl class="detail-grid">
            <div><dt>Full Name</dt><dd><?= sanitize($app['full_name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= sanitize($app['email']) ?></dd></div>
            <div><dt>Contact</dt><dd><?= sanitize($app['contact_number']) ?></dd></div>
            <div><dt>Occupation</dt><dd><?= sanitize($app['occupation']) ?></dd></div>
            <div class="detail-full"><dt>Address</dt><dd><?= sanitize($app['address']) ?></dd></div>
            <div><dt>Home Type</dt><dd><?= sanitize($app['home_type']) ?></dd></div>
            <div><dt>Existing Pets</dt><dd><?= ucfirst(sanitize($app['existing_pets'])) ?></dd></div>
            <div class="detail-full"><dt>Reason for Adoption</dt><dd><?= nl2br(sanitize($app['reason_for_adoption'])) ?></dd></div>
        </dl>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Pet Applied For</span></div>
        <div class="card-body text-center">
            <img src="<?= petImageUrl($app['pet_image']) ?>" alt="" class="application-pet-photo">
            <h3 class="application-pet-name"><?= sanitize($app['pet_name']) ?></h3>
            <p class="text-secondary text-sm"><?= sanitize($app['breed']) ?></p>
            <p class="mt-3">
                <span class="pet-status-badge <?= formatPetStatus($app['pet_status'])['class'] ?>">
                    <?= formatPetStatus($app['pet_status'])['label'] ?>
                </span>
            </p>
        </div>

        <?php if ($app['status'] === 'pending' && !canReviewAdoptionApplications()): ?>
        <div class="permission-notice">
            Pending applications can only be approved or rejected by an administrator.
        </div>
        <?php endif; ?>

        <?php if ($app['status'] === 'pending' && canReviewAdoptionApplications()): ?>
        <div class="card-body pt-0 flex flex-col gap-2">
            <form method="POST" action="<?= url('admin/application-action.php') ?>" class="app-action-form">
                <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-accent" style="width:100%">Approve Application</button>
            </form>
            <form method="POST" action="<?= url('admin/application-action.php') ?>" class="app-action-form">
                <input type="hidden" name="application_id" value="<?= (int) $app['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-ghost" style="width:100%;color:var(--status-failed)">Reject Application</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.app-action-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const action = form.querySelector('[name="action"]').value;
        const title = action === 'approve' ? 'Approve application?' : 'Reject application?';
        const text = action === 'approve'
            ? 'The pet will be marked as adopted and no new applications will be accepted.'
            : 'The applicant will be notified of the rejection.';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title, text, icon: 'question',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#8B3A3A' : '#ef4444',
                confirmButtonText: action === 'approve' ? 'Approve' : 'Reject'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        } else if (confirm(title)) {
            form.submit();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>