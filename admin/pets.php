<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/adoption.php';
require_once __DIR__ . '/../includes/validation.php';

requireCanManagePetListings();

$pageTitle     = 'Pet Management';
$useSweetAlert = true;
$extraJs       = ['js/pet-age-input.js'];

// ← CHANGED: removed $db argument from getAdoptionStats() and getPetById()/getPetImages()
$stats = getAdoptionStats();

// ← CHANGED: replaced $db->query() with db_select()
$pets = db_select('pets', 'order=created_at.desc');

$editId     = (int) ($_GET['edit'] ?? 0);
$showAdd    = isset($_GET['add']) || $editId;

// ← CHANGED: removed $db arguments
$editPet    = $editId ? getPetById($editId) : null;
$editImages = $editPet ? getPetImages($editId) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header page-header-row">
    <div class="page-header-text">
        <h2>Pet Management</h2>
        <p>Add, edit, and manage pets available for adoption.</p>
    </div>
    <?php if (!$showAdd): ?>
        <a href="<?= url('admin/pets.php?add=1') ?>" class="btn btn-accent">＋ Add New Pet</a>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Pets</div>
        <div class="stat-value"><?= $stats['total_pets'] ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Available</div>
        <div class="stat-value"><?= $stats['available_pets'] ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Pending Applications</div>
        <div class="stat-value"><?= $stats['pending_applications'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved Adoptions</div>
        <div class="stat-value"><?= $stats['approved_adoptions'] ?></div>
    </div>
</div>

<?php if ($showAdd): ?>
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title"><?= $editPet ? 'Edit Pet' : 'Add New Pet' ?></span>
        <a href="<?= url('admin/pets.php') ?>" class="btn btn-ghost btn-sm">← Back to list</a>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= url('admin/pet-save.php') ?>" enctype="multipart/form-data">
            <?php if ($editPet): ?>
                <input type="hidden" name="pet_id" value="<?= (int) $editPet['id'] ?>">
            <?php endif; ?>
            <div class="admin-pet-form-grid">
                <div class="form-group">
                    <label class="form-label">Pet Name <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= sanitize($editPet['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Breed <span class="req">*</span></label>
                    <input type="text" name="breed" class="form-control" required value="<?= sanitize($editPet['breed'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Age <span class="req">*</span></label>
                    <input type="text" name="age" id="pet_age" class="form-control" required
                           data-pet-age-max="<?= PET_MAX_AGE_YEARS ?>"
                           placeholder="e.g. 2 years (max <?= PET_MAX_AGE_YEARS ?> years)"
                           value="<?= sanitize($editPet['age'] ?? '') ?>">
                    <p class="text-sm text-muted mt-2">Enter age in years or months. Maximum <?= PET_MAX_AGE_YEARS ?> years.</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <?php foreach (['Male', 'Female', 'Unknown'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($editPet['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <?php if ($editPet): ?>
                    <select name="status" class="form-control">
                        <option value="available" <?= ($editPet['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="adopted"   <?= ($editPet['status'] ?? '') === 'adopted' ? 'selected' : '' ?>>Adopted</option>
                    </select>
                    <p class="text-sm text-secondary" style="margin-top:4px;">Status changes automatically to Adopted when an application is approved.</p>
                    <?php else: ?>
                    <input type="text" class="form-control" value="Available" readonly disabled>
                    <input type="hidden" name="status" value="available">
                    <p class="text-sm text-secondary" style="margin-top:4px;">New pets default to Available. Status updates automatically upon adoption approval.</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Rescue Date</label>
                    <input type="date" name="rescue_date" class="form-control" value="<?= sanitize($editPet['rescue_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px;">
                        Vaccination Type
                        <span class="vaccine-tip" title="Vaccine type e.g. Anti-Rabies, DA2PP, Leptospirosis" style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:var(--surface-2);border:1px solid var(--border);border-radius:50%;font-size:11px;color:var(--text-secondary);cursor:help;flex-shrink:0;">?</span>
                    </label>
                    <input type="text" name="vaccination_status" class="form-control"
                           placeholder="e.g. Anti-Rabies, DA2PP"
                           value="<?= sanitize($editPet['vaccination_status'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Health Condition</label>
                    <textarea name="health_condition" class="form-control" rows="2"><?= sanitize($editPet['health_condition'] ?? '') ?></textarea>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Description / Personality</label>
                    <textarea name="description" class="form-control" rows="3"><?= sanitize($editPet['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Primary Photo <?= $editPet ? '' : '<span class="req">*</span>' ?></label>
                    <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp" <?= $editPet ? '' : 'required' ?>>
                    <?php if (!empty($editPet['image'])): ?>
                        <img src="<?= petImageUrl($editPet['image']) ?>" class="pet-thumb-preview mt-3" alt="">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Photos</label>
                    <input type="file" name="extra_images[]" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple>
                </div>
            </div>

            <?php if ($editImages): ?>
            <div class="form-group mt-4">
                <label class="form-label">Extra gallery images (check to remove)</label>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ($editImages as $img): ?>
                    <label class="flex items-center gap-2" style="font-size:0.8rem;cursor:pointer">
                        <input type="checkbox" name="delete_image_ids[]" value="<?= (int) $img['id'] ?>">
                        <img src="<?= petImageUrl($img['image_path']) ?>" class="pet-thumb-preview" alt="">
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex gap-2 mt-4">
                <button type="submit" class="btn btn-accent"><?= $editPet ? 'Save Changes' : 'Add Pet' ?></button>
                <a href="<?= url('admin/pets.php') ?>" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Pets</span>
        <a href="<?= url('admin/adoption-requests.php') ?>" class="btn btn-ghost btn-sm">Adoption Requests</a>
    </div>
    <?php if (empty($pets)): ?>
        <div class="empty-state">
            <div class="empty-icon">🐾</div>
            <h3>No pets yet</h3>
            <p>Add your first adoptable pet to get started.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper table-responsive-stack">
            <table>
                <thead>
                    <tr><th>Photo</th><th>Name</th><th>Breed</th><th>Age</th><th>Status</th><th>Added</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pets as $pet):
                        $st = formatPetStatus($pet['status']);
                    ?>
                    <tr>
                        <td><img src="<?= petImageUrl($pet['image']) ?>" class="pet-thumb-preview" alt=""></td>
                        <td><strong><?= sanitize($pet['name']) ?></strong></td>
                        <td><?= sanitize($pet['breed']) ?></td>
                        <td><?= sanitize($pet['age']) ?></td>
                        <td><span class="pet-status-badge <?= $st['class'] ?>"><?= $st['label'] ?></span></td>
                        <td class="text-sm text-secondary"><?= timeAgo($pet['created_at']) ?></td>
                        <td>
                            <div class="flex gap-2">
                                <a href="<?= url('admin/pets.php?edit=' . $pet['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
                                <form method="POST" action="<?= url('admin/pet-save.php') ?>" class="pet-delete-form" style="display:inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="pet_id" value="<?= (int) $pet['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--status-failed)">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.pet-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const name = form.closest('tr')?.querySelector('strong')?.textContent || 'this pet';
        if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'Delete pet?', text: 'Remove ' + name + '? This cannot be undone.',
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444',
                cancelButtonColor: '#78716c', confirmButtonText: 'Delete'
            }).then(function (r) { if (r.isConfirmed) form.submit(); });
        } else if (confirm('Delete this pet?')) { form.submit(); }
    });
});
</script>

<script>
// Vaccine type tooltip
document.querySelectorAll('.vaccine-tip').forEach(function(el) {
    var tip = document.createElement('div');
    tip.style.cssText = 'position:fixed;background:#333;color:#fff;font-size:12px;padding:6px 10px;border-radius:6px;pointer-events:none;z-index:9999;opacity:0;transition:opacity .15s;max-width:220px;line-height:1.4;';
    tip.textContent = el.getAttribute('title');
    el.removeAttribute('title');
    document.body.appendChild(tip);
    el.addEventListener('mouseenter', function(e) {
        var r = el.getBoundingClientRect();
        tip.style.left = (r.right + 8) + 'px';
        tip.style.top  = (r.top - 4) + 'px';
        tip.style.opacity = '1';
    });
    el.addEventListener('mouseleave', function() { tip.style.opacity = '0'; });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
