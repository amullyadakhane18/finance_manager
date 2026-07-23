document.addEventListener('DOMContentLoaded', function () {

    // ---- Show / hide password ----
    document.querySelectorAll('.toggle-visibility').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            btn.classList.toggle('is-visible', isHidden);
        });
    });

    // ---- Password strength meter ----
    var passwordInput = document.getElementById('password');
    var meter = document.querySelector('.strength-meter');
    var strengthLabel = document.getElementById('strength-label');

    function scorePassword(value) {
        var score = 0;
        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
        if (/[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value)) score++;
        return Math.min(score, 4);
    }

    var labels = [
        'Use 8+ characters with a mix of letters and numbers.',
        'Weak — try adding a number or symbol.',
        'Okay — a little longer would help.',
        'Good password.',
        'Strong password.'
    ];

    if (passwordInput && meter) {
        passwordInput.addEventListener('input', function () {
            var score = passwordInput.value.length ? Math.max(scorePassword(passwordInput.value), 1) : 0;
            meter.className = 'strength-meter' + (score ? ' level-' + score : '');
            if (strengthLabel) strengthLabel.textContent = labels[score];
        });
    }

    // ---- Live "passwords match" check ----
    var confirmInput = document.getElementById('confirm_password');
    var matchLabel = document.getElementById('match-label');

    function checkMatch() {
        if (!confirmInput.value) {
            matchLabel.textContent = '';
            return;
        }
        if (passwordInput.value === confirmInput.value) {
            matchLabel.textContent = 'Passwords match.';
            matchLabel.classList.remove('field-hint--error');
        } else {
            matchLabel.textContent = 'Passwords do not match.';
            matchLabel.classList.add('field-hint--error');
        }
    }

    if (passwordInput && confirmInput && matchLabel) {
        confirmInput.addEventListener('input', checkMatch);
        passwordInput.addEventListener('input', checkMatch);
    }

    // ---- Mobile / tablet navbar toggle ----
    var navToggle = document.getElementById('navbarToggle');
    var dashNav = document.getElementById('dashNav');

    if (navToggle && dashNav) {
        navToggle.addEventListener('click', function () {
            var isOpen = dashNav.classList.toggle('is-open');
            navToggle.classList.toggle('is-open', isOpen);
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close the menu once a link inside it is used (mobile/tablet UX)
        dashNav.querySelectorAll('a, button').forEach(function (el) {
            el.addEventListener('click', function () {
                dashNav.classList.remove('is-open');
                navToggle.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });

        // Close the menu if the viewport is resized up to desktop width
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024 && dashNav.classList.contains('is-open')) {
                dashNav.classList.remove('is-open');
                navToggle.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Close the menu when tapping/clicking outside of it
        document.addEventListener('click', function (event) {
            if (!dashNav.classList.contains('is-open')) return;
            if (dashNav.contains(event.target) || navToggle.contains(event.target)) return;
            dashNav.classList.remove('is-open');
            navToggle.classList.remove('is-open');
            navToggle.setAttribute('aria-expanded', 'false');
        });
    }
});