(function () {
    'use strict';

    const PHONE_MIN = 7;
    const PHONE_MAX = 15;

    function digitsOnly(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function bindPhoneInput(input) {
        if (!input || input.dataset.phoneBound === '1') {
            return;
        }
        input.dataset.phoneBound = '1';
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '[0-9]{' + PHONE_MIN + ',' + PHONE_MAX + '}');
        input.setAttribute('maxlength', String(PHONE_MAX));
        input.setAttribute('autocomplete', 'tel');

        input.addEventListener('input', function () {
            const cleaned = digitsOnly(input.value);
            if (input.value !== cleaned) {
                input.value = cleaned;
            }
        });

        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            input.value = digitsOnly(text).slice(0, PHONE_MAX);
        });
    }

    function validatePhoneInput(input) {
        const value = digitsOnly(input.value);
        if (!value) {
            return input.required ? 'Contact number is required.' : '';
        }
        if (value.length < PHONE_MIN || value.length > PHONE_MAX) {
            return 'Phone number must be ' + PHONE_MIN + '–' + PHONE_MAX + ' digits.';
        }
        return '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-phone-numeric]').forEach(bindPhoneInput);

        document.querySelectorAll('form').forEach(function (form) {
            const phoneFields = form.querySelectorAll('[data-phone-numeric]');
            if (!phoneFields.length) {
                return;
            }

            form.addEventListener('submit', function (e) {
                for (const field of phoneFields) {
                    const err = validatePhoneInput(field);
                    if (err) {
                        e.preventDefault();
                        field.setCustomValidity(err);
                        field.reportValidity();
                        return;
                    }
                    field.setCustomValidity('');
                    field.value = digitsOnly(field.value);
                }
            });
        });
    });

    window.BPPPhone = { digitsOnly, validatePhoneInput, bindPhoneInput };
})();
