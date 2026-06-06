/**
 * password-strength.js — BantayPurrPaws
 * Shared real-time password strength checker.
 *
 * Usage:
 *   initPasswordStrength(passwordInputId, confirmInputId, wrapperInsertAfterId)
 *
 * The widget is injected immediately after the element whose id matches
 * `wrapperInsertAfterId` (typically the password <input> itself or its wrapper).
 */
(function (global) {
  'use strict';

  // ── Policy ────────────────────────────────────────────────────────────────
  const POLICY = {
    minLen:    12,
    uppercase: /[A-Z]/,
    lowercase: /[a-z]/,
    number:    /[0-9]/,
    special:   /[!@#$%^&*()\-_=+\[\]{};:,\.?\/]/,
  };

  // ── Strength levels (requires 0‥5 rules met) ─────────────────────────────
  const LEVELS = [
    { label: '',       color: '#e5e7eb', width:   0 },  // 0 rules
    { label: 'Weak',   color: '#ef4444', width:  20 },  // 1 rule
    { label: 'Weak',   color: '#f97316', width:  40 },  // 2 rules
    { label: 'Fair',   color: '#eab308', width:  55 },  // 3 rules
    { label: 'Good',   color: '#22c55e', width:  75 },  // 4 rules
    { label: 'Strong', color: '#16a34a', width: 100 },  // 5 rules
  ];

  // ── Server-side validation helper (for PHP to call via inline check) ──────
  // Returns an array of error strings; empty array = valid.
  global.validatePasswordPolicy = function (pwd) {
    const errors = [];
    if (pwd.length < POLICY.minLen)        errors.push('Password must be at least 12 characters.');
    if (!POLICY.uppercase.test(pwd))       errors.push('Password must contain at least one uppercase letter.');
    if (!POLICY.lowercase.test(pwd))       errors.push('Password must contain at least one lowercase letter.');
    if (!POLICY.number.test(pwd))          errors.push('Password must contain at least one number.');
    if (!POLICY.special.test(pwd))         errors.push('Password must contain at least one special character.');
    return errors;
  };

  // ── Widget factory ────────────────────────────────────────────────────────
  global.initPasswordStrength = function (pwdId, confirmId, insertAfterId) {
    const pwdInput     = document.getElementById(pwdId);
    const confirmInput = confirmId ? document.getElementById(confirmId) : null;
    const anchor       = document.getElementById(insertAfterId || pwdId);

    if (!pwdInput || !anchor) return;

    // ── Build widget HTML ─────────────────────────────────────────────────
    const wrapper = document.createElement('div');
    wrapper.className = 'pw-strength-widget';
    wrapper.innerHTML = `
      <div class="pw-bar-wrap" aria-label="Password strength">
        <div class="pw-bar-fill" id="pwBarFill_${pwdId}"></div>
      </div>
      <div class="pw-level-label" id="pwLevelLabel_${pwdId}"></div>

      <ul class="pw-reqs" id="pwReqs_${pwdId}">
        <li class="pw-req" data-rule="minLen">
          <span class="pw-req-icon">○</span>
          <span>Minimum 12 characters</span>
        </li>
        <li class="pw-req" data-rule="uppercase">
          <span class="pw-req-icon">○</span>
          <span>Contains uppercase letter (A–Z)</span>
        </li>
        <li class="pw-req" data-rule="lowercase">
          <span class="pw-req-icon">○</span>
          <span>Contains lowercase letter (a–z)</span>
        </li>
        <li class="pw-req" data-rule="number">
          <span class="pw-req-icon">○</span>
          <span>Contains a number (0–9)</span>
        </li>
        <li class="pw-req" data-rule="special">
          <span class="pw-req-icon">○</span>
          <span>Contains a special character (!@#$%^&amp;*…)</span>
        </li>
      </ul>

      <div class="pw-guidance">
        <strong>A strong 12-character password usually includes:</strong>
        <ul>
          <li>Uppercase letters (A–Z)</li>
          <li>Lowercase letters (a–z)</li>
          <li>Numbers (0–9)</li>
          <li>Special characters (! @ # $ % ^ &amp; *)</li>
        </ul>
      </div>
    `;

    // Insert right after the anchor element's parent form-group, or after anchor
    const parent = anchor.closest('.form-group') || anchor.parentNode;
    parent.insertAdjacentElement('afterend', wrapper);

    const barFill   = wrapper.querySelector(`#pwBarFill_${pwdId}`);
    const levelLabel = wrapper.querySelector(`#pwLevelLabel_${pwdId}`);
    const reqItems  = wrapper.querySelectorAll('.pw-req');

    // ── Evaluate on input ─────────────────────────────────────────────────
    function evaluate() {
      const v = pwdInput.value;
      const checks = {
        minLen:    v.length >= POLICY.minLen,
        uppercase: POLICY.uppercase.test(v),
        lowercase: POLICY.lowercase.test(v),
        number:    POLICY.number.test(v),
        special:   POLICY.special.test(v),
      };

      let met = 0;
      reqItems.forEach(li => {
        const rule = li.dataset.rule;
        const icon = li.querySelector('.pw-req-icon');
        if (checks[rule]) {
          li.classList.add('pw-req--ok');
          icon.textContent = '✓';
          met++;
        } else {
          li.classList.remove('pw-req--ok');
          icon.textContent = '○';
        }
      });

      const level = LEVELS[met] || LEVELS[0];
      barFill.style.width            = level.width + '%';
      barFill.style.backgroundColor  = level.color;
      levelLabel.textContent         = level.label;
      levelLabel.style.color         = level.color;

      // Show/hide the guidance tip: visible when field has focus + not all rules met
      const tip = wrapper.querySelector('.pw-guidance');
      if (tip) tip.style.display = (v.length > 0 && met < 5) ? 'block' : 'none';
    }

    pwdInput.addEventListener('input', evaluate);
    evaluate(); // initial state

    // ── Confirm-match indicator ───────────────────────────────────────────
    if (confirmInput) {
      function checkMatch() {
        const p = pwdInput.value;
        const c = confirmInput.value;
        if (!c) {
          confirmInput.style.borderColor = '';
          return;
        }
        confirmInput.style.borderColor = (p === c) ? '#22c55e' : '#ef4444';
      }
      confirmInput.addEventListener('input', checkMatch);
      pwdInput.addEventListener('input', checkMatch);
    }
  };

})(window);
