/**
 * BantayPurrPaws — Password show/hide toggle
 * Usage: initPwToggle() — auto-wires all [data-pw-toggle] buttons
 *        or addPwToggle(inputEl) — adds toggle to a specific input
 */
(function () {
    'use strict';

    const SVG_EYE = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
    </svg>`;
    const SVG_EYE_OFF = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
    </svg>`;

    /** Wrap a password input in a relative div and append a toggle button */
    window.addPwToggle = function (input) {
        if (!input || input.dataset.pwToggleInit) return;
        input.dataset.pwToggleInit = '1';

        // Wrap input
        const wrap = document.createElement('div');
        wrap.style.cssText = 'position:relative;display:flex;align-items:center;';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.style.paddingRight = '42px';

        // Button
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Show password');
        btn.innerHTML = SVG_EYE;
        btn.style.cssText = [
            'position:absolute', 'right:10px', 'top:50%', 'transform:translateY(-50%)',
            'background:none', 'border:none', 'cursor:pointer', 'padding:4px',
            'color:var(--text-muted,#78716c)', 'display:flex', 'align-items:center',
            'transition:color .15s', 'border-radius:4px', 'flex-shrink:0'
        ].join(';');
        btn.addEventListener('mouseover',  () => btn.style.color = 'var(--text-primary,#2d2520)');
        btn.addEventListener('mouseout',   () => btn.style.color = 'var(--text-muted,#78716c)');

        let visible = false;
        btn.addEventListener('click', () => {
            visible = !visible;
            input.type = visible ? 'text' : 'password';
            btn.innerHTML = visible ? SVG_EYE_OFF : SVG_EYE;
            btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        });

        wrap.appendChild(btn);
    };

    /** Wire all password inputs that have data-pw-toggle attr, or all if selector given */
    window.initPwToggle = function (selector) {
        const sel = selector || 'input[type="password"]:not([data-no-pw-toggle])';
        document.querySelectorAll(sel).forEach(window.addPwToggle);
    };

    // Auto-init on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        window.initPwToggle();
    });
})();
