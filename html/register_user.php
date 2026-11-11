<?php
/* =====================================================================
   GUEST GUARD
   This is the sign-up page, so visitors who are NOT signed in may open it.
   A visitor who is already signed in is sent to their own home page.
   ===================================================================== */
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../db/auth.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: index.php?page=' . (is_staff_side() ? 'dashboard' : 'products'));
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | BuildQuin</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400..700,0..1,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="../css/icons.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-visual" aria-label="BuildQuin building supplies">
            <a class="brand" href="../index.html"><span class="brand-mark">BQ</span><span>BuildQuin</span></a>
            <div class="visual-copy">
                <span class="eyebrow">CATALOG &amp; SUPPLIES</span>
                <h1>Start building<br>with us.</h1>
                <p>Create a free customer account in less than a minute.</p>
                <ul class="visual-points">
                    <li>Save your delivery addresses</li>
                    <li>Order and track every purchase</li>
                    <li>Ask questions about any product</li>
                </ul>
            </div>
            <span class="visual-caption">BUILDQUIN HARDWARE &amp; ARCHITECTURAL SUPPLIES</span>
        </section>

        <section class="auth-panel">
            <div class="auth-content">
                <div class="mobile-brand"><span class="brand-mark">BQ</span><span>BuildQuin</span></div>
                <p class="eyebrow">NEW CUSTOMER</p>
                <h2>Create your account</h2>
                <p class="intro">Fill in your details to start shopping.</p>

                <div class="notice" id="notice" role="status" aria-live="polite" hidden></div>
                <div class="form-error" id="tokenError" role="alert" hidden>Unable to start a secure form. Refresh this page and try again.</div>

                <form id="registerForm" action="index.php?page=register" method="post">
                    <input type="hidden" name="csrf_token" value="">

                    <label for="fullName">Full name</label>
                    <input id="fullName" name="full_name" type="text" autocomplete="name" maxlength="100" placeholder="Juan Dela Cruz" required>

                    <label for="registerEmail">Email address</label>
                    <input id="registerEmail" name="email" type="email" autocomplete="email" maxlength="254" placeholder="you@example.com" required>

                    <label for="registerPassword">Password</label>
                    <div class="field">
                        <input id="registerPassword" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                        <button type="button" class="toggle-pass" data-target="registerPassword" aria-label="Show password">Show</button>
                    </div>

                    <label for="confirmPassword">Confirm password</label>
                    <div class="field">
                        <input id="confirmPassword" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Type it again" required>
                        <button type="button" class="toggle-pass" data-target="confirmPassword" aria-label="Show password">Show</button>
                    </div>
                    <span class="field-hint">Use 8 to 72 characters.</span>

                    <button class="submit-button" id="submitButton" type="submit" disabled><b>Create account</b> <span class="mi" aria-hidden="true">arrow_forward</span></button>
                </form>

                <p class="switch-note">Already have an account? <a href="../index.html">Sign in here</a></p>
                <p class="security-note">Are you part of the team? <a href="register_staff.php">Register as staff</a></p>
            </div>
            <footer>&copy; <span id="year"></span> BuildQuin</footer>
        </section>
    </main>

    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
        const notice = document.getElementById('notice');
        const messages = {
            email_exists: 'An account already uses that email address.',
            invalid_registration: 'Check the form. Use a valid email and matching passwords of at least 8 characters.',
            registration_error: 'The account could not be created. Please try again.',
            invalid_request: 'The form expired. Refresh and try again.'
        };

        const query = new URLSearchParams(window.location.search);
        if (messages[query.get('notice')]) {
            notice.textContent = messages[query.get('notice')];
            notice.classList.add('error');
            notice.hidden = false;
        }

        document.querySelectorAll('.toggle-pass').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.textContent = show ? 'Hide' : 'Show';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });

        fetch('index.php?page=csrf', { credentials: 'same-origin' })
            .then(response => {
                if (!response.ok) throw new Error('Could not load form token.');
                return response.json();
            })
            .then(data => {
                document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                    input.value = data.token;
                });
                document.getElementById('submitButton').disabled = false;
            })
            .catch(() => {
                document.getElementById('tokenError').hidden = false;
            });

        const confirmPassword = document.getElementById('confirmPassword');
        document.getElementById('registerForm').addEventListener('submit', event => {
            if (document.getElementById('registerPassword').value !== confirmPassword.value) {
                event.preventDefault();
                confirmPassword.setCustomValidity('Passwords do not match.');
                confirmPassword.reportValidity();
            }
        });
        confirmPassword.addEventListener('input', () => confirmPassword.setCustomValidity(''));
    </script>
</body>
</html>