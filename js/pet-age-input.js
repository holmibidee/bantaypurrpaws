(function () {
    'use strict';

    const MAX_YEARS = 50;

    function parseAgeYears(value) {
        const age = String(value || '').trim().toLowerCase();
        if (!age) return null;

        let match = age.match(/^(\d+(?:\.\d+)?)\s*(year|years|yr|yrs|y)\b/);
        if (match) return parseFloat(match[1]);

        match = age.match(/^(\d+(?:\.\d+)?)\s*(month|months|mo|m)\b/);
        if (match) return parseFloat(match[1]) / 12;

        match = age.match(/^(\d+(?:\.\d+)?)$/);
        if (match) return parseFloat(match[1]);

        match = age.match(/(\d+(?:\.\d+)?)\s*(year|years|yr|yrs|y)\b/);
        if (match) return parseFloat(match[1]);

        match = age.match(/(\d+(?:\.\d+)?)\s*(month|months|mo|m)\b/);
        if (match) return parseFloat(match[1]) / 12;

        return null;
    }

    function validatePetAgeInput(input) {
        const maxYears = parseInt(input.getAttribute('data-pet-age-max') || String(MAX_YEARS), 10);
        const years = parseAgeYears(input.value);

        if (years === null) {
            return 'Enter a valid age (e.g. "2 years", "18 months", or "3").';
        }
        if (years < 0) {
            return 'Age cannot be negative.';
        }
        if (years > maxYears) {
            return 'Pet age cannot exceed ' + maxYears + ' years.';
        }
        return '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('pet_age');
        const form = input ? input.closest('form') : null;
        if (!input || !form) return;

        form.addEventListener('submit', function (e) {
            const err = validatePetAgeInput(input);
            if (err) {
                e.preventDefault();
                input.setCustomValidity(err);
                input.reportValidity();
                return;
            }
            input.setCustomValidity('');
        });
    });
})();
