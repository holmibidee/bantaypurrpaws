(function () {
    var config      = window.BPP_ADOPTION || {};
    var apiPet      = config.apiPet;
    var apiAdopt    = config.apiAdopt;
    var userHasPhone = config.userHasPhone !== false;

    var detailModalEl      = document.getElementById('petDetailModal');
    var adoptModalEl       = document.getElementById('adoptFormModal');
    var phoneRequiredModal = document.getElementById('phoneRequiredModal');

    var currentPet = null;

    function openModal(overlay) {
        if (!overlay) return;
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(overlay) {
        if (!overlay) return;
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal-overlay.open')) {
            document.body.style.overflow = '';
        }
    }

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.closest('.modal-overlay'));
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal(overlay);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(closeModal);
        }
    });

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str != null ? str : '';
        return d.innerHTML;
    }

    function showAlert(icon, title, text) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: icon, title: title, text: text, confirmButtonColor: '#8B3A3A' });
        } else {
            alert(title + (text ? '\n' + text : ''));
        }
    }

    function checkPhoneThenAdopt(callback) {
        if (!userHasPhone) {
            openModal(phoneRequiredModal);
            return;
        }
        callback();
    }

    function renderDetail(pet) {
        currentPet = pet;
        var mainImg  = document.getElementById('petDetailMainImg');
        var thumbs   = document.getElementById('petDetailThumbs');
        var body     = document.getElementById('petDetailBody');
        var adoptBtn = document.getElementById('petDetailAdoptBtn');

        if (!mainImg || !body) return;

        var images = pet.images && pet.images.length ? pet.images : [config.placeholder];
        mainImg.src = images[0];
        mainImg.alt = pet.name;

        thumbs.innerHTML = images.map(function(src, i) {
            return '<img src="' + esc(src) + '" class="pet-detail-thumb' + (i === 0 ? ' active' : '') + '" data-src="' + esc(src) + '" alt="">';
        }).join('');

        thumbs.querySelectorAll('.pet-detail-thumb').forEach(function(thumb) {
            thumb.addEventListener('click', function () {
                mainImg.src = this.dataset.src;
                thumbs.querySelectorAll('.pet-detail-thumb').forEach(function(t) { t.classList.remove('active'); });
                this.classList.add('active');
            });
        });

        body.innerHTML =
            '<dl class="pet-detail-list">' +
            '<div><dt>Name</dt><dd>' + esc(pet.name) + '</dd></div>' +
            '<div><dt>Breed</dt><dd>' + esc(pet.breed) + '</dd></div>' +
            '<div><dt>Age</dt><dd>' + esc(pet.age) + '</dd></div>' +
            '<div><dt>Gender</dt><dd>' + esc(pet.gender) + '</dd></div>' +
            '<div><dt>Vaccination Type</dt><dd>' + (esc(pet.vaccination_status) || '—') + '</dd></div>' +
            '<div><dt>Rescue Date</dt><dd>' + esc(pet.rescue_date) + '</dd></div>' +
            '<div class="pet-detail-full"><dt>Health Condition</dt><dd>' + (esc(pet.health_condition) || '—') + '</dd></div>' +
            '<div class="pet-detail-full"><dt>Personality / Description</dt><dd>' + (esc(pet.description) || '—') + '</dd></div>' +
            '</dl>';

        if (adoptBtn) {
            adoptBtn.style.display = pet.can_adopt ? '' : 'none';
            adoptBtn.disabled = !pet.can_adopt;
        }

        document.getElementById('petDetailModalLabel').textContent = pet.name;
    }

    document.querySelectorAll('[data-pet-info]').forEach(function(btn) {
        btn.addEventListener('click', async function () {
            var id = this.dataset.petId;
            try {
                var res  = await fetch(apiPet + '?id=' + encodeURIComponent(id));
                var data = await res.json();
                if (!res.ok || data.error) {
                    showAlert('error', 'Error', data.error || 'Could not load pet details.');
                    return;
                }
                renderDetail(data.pet);
                openModal(detailModalEl);
            } catch (e) {
                showAlert('error', 'Error', 'Network error. Please try again.');
            }
        });
    });

    document.getElementById('petDetailAdoptBtn') && document.getElementById('petDetailAdoptBtn').addEventListener('click', function () {
        if (!currentPet || !currentPet.can_adopt) return;
        checkPhoneThenAdopt(function() {
            document.getElementById('adoptPetId').value = currentPet.id;
            document.getElementById('adoptPetNameLabel').textContent = currentPet.name;
            closeModal(detailModalEl);
            openModal(adoptModalEl);
        });
    });

    document.querySelectorAll('[data-pet-adopt]').forEach(function(btn) {
        btn.addEventListener('click', async function () {
            var id = this.dataset.petId;
            if (this.dataset.available !== '1') {
                showAlert('info', 'Unavailable', 'This pet has already been adopted.');
                return;
            }
            checkPhoneThenAdopt(async function() {
                try {
                    var res  = await fetch(apiPet + '?id=' + encodeURIComponent(id));
                    var data = await res.json();
                    if (!res.ok || !data.pet) {
                        showAlert('error', 'Error', data.error || 'Could not load pet.');
                        return;
                    }
                    currentPet = data.pet;
                    if (!data.pet.can_adopt) {
                        showAlert('info', 'Unavailable', 'This pet is not accepting new applications.');
                        return;
                    }
                    document.getElementById('adoptPetId').value = data.pet.id;
                    document.getElementById('adoptPetNameLabel').textContent = data.pet.name;
                    openModal(adoptModalEl);
                } catch (e) {
                    showAlert('error', 'Error', 'Network error.');
                }
            });
        });
    });

    var adoptForm = document.getElementById('adoptionApplicationForm');
    adoptForm && adoptForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        var form = e.target;
        var fd   = new FormData(form);

        if (!fd.get('agreement')) {
            showAlert('warning', 'Agreement required', 'Please accept the adoption terms.');
            return;
        }
        if (!fd.get('existing_pets')) {
            showAlert('warning', 'Missing field', 'Please indicate if you have existing pets.');
            return;
        }

        var submitBtn = form.querySelector('[type="submit"]');
        submitBtn.disabled = true;

        try {
            var res  = await fetch(apiAdopt, { method: 'POST', body: fd });
            var data = await res.json();
            if (data.success) {
                closeModal(adoptModalEl);
                form.reset();
                showAlert('success', 'Application Submitted', data.message);
                setTimeout(function() { location.reload(); }, 1800);
            } else {
                showAlert('error', 'Submission Failed', data.message || 'Please check your entries.');
            }
        } catch (err) {
            showAlert('error', 'Error', 'Could not submit application.');
        } finally {
            submitBtn.disabled = false;
        }
    });
})();
