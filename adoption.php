<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/adoption.php';

requireLogin();

if (isAdmin()) {
    header('Location: ' . url('admin/pets.php'));
    exit;
}

$pageTitle     = 'Pet Adoption';
$useSweetAlert = true;
$extraCss      = ['css/adoption.css'];

// Only show available pets (and pending-adoption for context)
$pets   = db_select('pets', 'order=created_at.desc') ?: [];
$user   = currentUser();
$userId = (int) $user['id'];

// Full user record for phone check
require_once __DIR__ . '/includes/users.php';
$fullUser = getUserById($userId);
$userPhone   = $fullUser['phone_number'] ?? '';
$userHasPhone = $userPhone !== '' && $userPhone !== null;

require_once __DIR__ . '/includes/header.php';
?>

<div class="adoption-page">
    <div class="page-header">
        <h2>Find Your New Companion</h2>
        <p>Browse pets ready for adoption. View details and submit an application when you're ready.</p>
    </div>

    <?php if (empty($pets)): ?>
        <div class="empty-state">
            <div class="empty-icon">🐱</div>
            <h3>No pets listed yet</h3>
            <p>Check back soon — new rescues will appear here when available.</p>
        </div>
    <?php else: ?>
        <div class="pet-gallery-grid">
            <?php foreach ($pets as $pet):
                $status   = formatPetStatus($pet['status']);
                $isPending = userHasPendingApplication($userId, (int) $pet['id']);
                $canAdopt = $pet['status'] === 'available' && !$isPending;
            ?>
            <article class="pet-card">
                <div class="pet-card-img-wrap">
                    <img src="<?= petImageUrl($pet['image'] ?? null) ?>" alt="<?= sanitize($pet['name']) ?>" loading="lazy">
                </div>
                <div class="pet-card-body">
                    <h3 class="pet-card-title"><?= sanitize($pet['name']) ?></h3>
                    <div class="pet-meta">
                        <span><?= sanitize($pet['age']) ?></span>
                        <span><?= sanitize($pet['breed']) ?></span>
                        <span><?= sanitize($pet['gender']) ?></span>
                    </div>
                    <?php if ($isPending): ?>
                        <span class="pet-status-badge" style="background:#fef3c7;color:#92400e;">Pending Adoption Approval</span>
                    <?php else: ?>
                        <span class="pet-status-badge <?= $status['class'] ?>"><?= $status['label'] ?></span>
                    <?php endif; ?>
                    <div class="pet-card-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-pet-info data-pet-id="<?= (int) $pet['id'] ?>">
                            More Info
                        </button>
                        <?php if ($canAdopt): ?>
                            <button type="button" class="btn btn-accent btn-sm" data-pet-adopt data-pet-id="<?= (int) $pet['id'] ?>" data-available="1">
                                Adopt Now
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Phone number required modal -->
<div class="modal-overlay" id="phoneRequiredModal" aria-hidden="true">
    <div class="modal" role="dialog" style="max-width:420px;">
        <div class="modal-header">
            <h2 class="modal-title">📱 Phone Number Required</h2>
        </div>
        <div style="padding:0 0 20px;">
            <p style="color:var(--text-secondary);margin:0 0 16px;">To submit an adoption application, you need a verified phone number on your profile.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?= url('profile.php') ?>" class="btn btn-accent">Go to Profile</a>
                <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Pet Detail Modal -->
<div class="modal-overlay" id="petDetailModal" aria-hidden="true">
    <div class="modal modal-lg" role="dialog" aria-labelledby="petDetailModalLabel">
        <div class="modal-header">
            <h2 class="modal-title" id="petDetailModalLabel">Pet Details</h2>
            <button type="button" class="modal-close" data-modal-close aria-label="Close">✕</button>
        </div>
        <div class="modal-scroll-body">
            <img id="petDetailMainImg" class="pet-detail-main" src="" alt="">
            <div id="petDetailThumbs" class="pet-detail-gallery"></div>
            <div id="petDetailBody"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
            <button type="button" class="btn btn-accent" id="petDetailAdoptBtn">Adopt Now</button>
        </div>
    </div>
</div>

<!-- Adoption Application Modal -->
<div class="modal-overlay" id="adoptFormModal" aria-hidden="true">
    <div class="modal modal-lg" role="dialog" aria-labelledby="adoptFormModalLabel">
        <form id="adoptionApplicationForm">
            <div class="modal-header">
                <h2 class="modal-title" id="adoptFormModalLabel">Adoption Application — <span id="adoptPetNameLabel"></span></h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Close">✕</button>
            </div>
            <div class="modal-scroll-body">
                <input type="hidden" name="pet_id" id="adoptPetId" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="adopt_full_name">Full Name</label>
                        <input type="text" id="adopt_full_name" name="full_name" class="form-control"
                               readonly value="<?= sanitize($fullUser['full_name'] ?? $user['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="adopt_contact">Contact Number</label>
                        <input type="tel" id="adopt_contact" name="contact_number" class="form-control"
                               readonly value="<?= sanitize($userPhone) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="adopt_occupation">Occupation <span class="req">*</span></label>
                    <input type="text" id="adopt_occupation" name="occupation" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Has Existing Pets? <span class="req">*</span></label>
                    <div style="display:flex;gap:1.5rem;margin-top:6px;">
                        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.9rem;">
                            <input type="radio" name="existing_pets" value="yes" required
                                   style="accent-color:var(--accent);width:16px;height:16px;">
                            Yes
                        </label>
                        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:.9rem;">
                            <input type="radio" name="existing_pets" value="no" required
                                   style="accent-color:var(--accent);width:16px;height:16px;">
                            No
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="adopt_schedule_date">Schedule for Meeting <span class="req">*</span></label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <input type="date" id="adopt_schedule_date" name="schedule_date"
                               class="form-control" required>
                        <input type="time" id="adopt_schedule_time" name="schedule_time"
                               class="form-control" required placeholder="Time of appointment">
                    </div>
                    <p class="text-sm text-secondary" style="margin-top:5px;">Select a date from today up to 1 month ahead.</p>
                </div>
                <div class="form-group form-check-adopt">
                    <label class="form-check-label">
                        <input type="checkbox" name="agreement" id="adoptAgreement" value="1" required>
                        <span>I agree to provide proper care, shelter, and veterinary attention for this pet. <span class="req">*</span></span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-accent">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Solid background for More Info button */
.btn-secondary {
    background: var(--surface-2);
    color: var(--text-primary);
    border: 1px solid var(--border);
}
.btn-secondary:hover {
    background: var(--surface-3, #e8ddd4);
}
</style>

<script>
window.BPP_ADOPTION = {
    apiPet:     <?= json_encode(url('api/pet.php')) ?>,
    apiAdopt:   <?= json_encode(url('api/adopt.php')) ?>,
    placeholder:<?= json_encode(petImageUrl(null)) ?>,
    userHasPhone: <?= $userHasPhone ? 'true' : 'false' ?>
};

// Date limits: ±1 month from today
(function() {
    var now   = new Date();
    var min   = new Date(now); // minimum is today
    var max   = new Date(now); max.setMonth(max.getMonth() + 1);
    function fmt(d) { return d.toISOString().split('T')[0]; }
    var dateInput = document.getElementById('adopt_schedule_date');
    if (dateInput) {
        dateInput.min = fmt(min);
        dateInput.max = fmt(max);
    }
})();
</script>

<?php
$extraJs = ['js/adoption.js'];
require_once __DIR__ . '/includes/footer.php';
?>
