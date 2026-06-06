<?php
/**
 * Shared input validation helpers.
 */

define('PHONE_MIN_DIGITS', 7);
define('PHONE_MAX_DIGITS', 15);
define('PET_MAX_AGE_YEARS', 50);

/**
 * Strip non-digits and validate a phone/contact number.
 *
 * @return array{ok: true, value: string}|array{ok: false, error: string}
 */
function validatePhoneNumber(string $phone, bool $required = true): array {
    $digits = preg_replace('/\D+/', '', trim($phone));

    if ($digits === '') {
        if ($required) {
            return ['ok' => false, 'error' => 'Contact number is required.'];
        }
        return ['ok' => true, 'value' => ''];
    }

    if (!preg_match('/^\d+$/', $digits)) {
        return ['ok' => false, 'error' => 'Phone number must contain digits only.'];
    }

    $len = strlen($digits);
    if ($len < PHONE_MIN_DIGITS || $len > PHONE_MAX_DIGITS) {
        return [
            'ok'    => false,
            'error' => 'Phone number must be between ' . PHONE_MIN_DIGITS . ' and ' . PHONE_MAX_DIGITS . ' digits.',
        ];
    }

    return ['ok' => true, 'value' => $digits];
}

/**
 * Parse free-text pet age into approximate years (for validation).
 */
function parsePetAgeYears(string $age): ?float {
    $age = trim(strtolower($age));
    if ($age === '') {
        return null;
    }

    if (preg_match('/^(\d+(?:\.\d+)?)\s*(year|years|yr|yrs|y)\b/', $age, $m)) {
        return (float) $m[1];
    }

    if (preg_match('/^(\d+(?:\.\d+)?)\s*(month|months|mo|m)\b/', $age, $m)) {
        return (float) $m[1] / 12;
    }

    if (preg_match('/^(\d+(?:\.\d+)?)\s*(week|weeks|wk|w)\b/', $age, $m)) {
        return (float) $m[1] / 52;
    }

    if (preg_match('/^(\d+(?:\.\d+)?)$/', $age, $m)) {
        return (float) $m[1];
    }

    if (preg_match('/(\d+(?:\.\d+)?)\s*(year|years|yr|yrs|y)\b/', $age, $m)) {
        return (float) $m[1];
    }

    if (preg_match('/(\d+(?:\.\d+)?)\s*(month|months|mo|m)\b/', $age, $m)) {
        return (float) $m[1] / 12;
    }

    return null;
}

/**
 * @return array{ok: true, value: string}|array{ok: false, error: string}
 */
function validatePetAge(string $age): array {
    $age = trim($age);
    if ($age === '') {
        return ['ok' => false, 'error' => 'Age is required.'];
    }

    $years = parsePetAgeYears($age);
    if ($years === null) {
        return [
            'ok'    => false,
            'error' => 'Enter a valid age (e.g. "2 years", "18 months", or "3").',
        ];
    }

    if ($years < 0) {
        return ['ok' => false, 'error' => 'Age cannot be negative.'];
    }

    if ($years > PET_MAX_AGE_YEARS) {
        return [
            'ok'    => false,
            'error' => 'Pet age cannot exceed ' . PET_MAX_AGE_YEARS . ' years.',
        ];
    }

    return ['ok' => true, 'value' => $age];
}
