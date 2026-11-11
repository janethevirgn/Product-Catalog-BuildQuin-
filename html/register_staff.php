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
    <title>Admin &amp; Staff registration | BuildQuin</title>
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
                <h1>Join the<br>BuildQuin team.</h1>
                <p>Register as Admin or staff to manage products, orders and customer feedback.</p>
                <ul class="visual-points">
                    <li>Enter your name and Gmail</li>
                    <li>Choose your role</li>
                    <li>Type the password of your role</li>
                    <li>Set your own security password</li>
                </ul>
            </div>
            <span class="visual-caption">BUILDQUIN HARDWARE &amp; ARCHITECTURAL SUPPLIES</span>
        </section>

        <section class="auth-panel">
            <div class="auth-content">
                <div class="mobile-brand"><span class="brand-mark">BQ</span><span>BuildQuin</span></div>
                <p class="eyebrow">ADMIN &amp; STAFF</p>
                <h2>Create your account</h2>
                <p class="intro">Fill in the form, then sign in with your Gmail, security password and role password.</p>

                <div class="notice" id="notice" role="status" aria-live="polite" hidden></div>
                <div class="form-error" id="tokenError" role="alert" hidden>Unable to start a secure form. Refresh this page and try again.</div>

                <form id="registerForm" action="index.php?page=register" method="post">
                    <input type="hidden" name="csrf_token" value="">
                    <input type="hidden" name="account_type" value="staff">

                    <label for="fullName">Full name</label>
                    <input id="fullName" name="full_name" type="text" autocomplete="name" maxlength="100" placeholder="Juan Dela Cruz" required>

                    <label for="registerEmail">Gmail address</label>
                    <input id="registerEmail" name="email" type="email" autocomplete="email" maxlength="254" placeholder="yourname@gmail.com" pattern="[A-Za-z0-9._%+\-]+@[Gg][Mm][Aa][Ii][Ll]\.[Cc][Oo][Mm]" title="Use your Gmail address (ending in @gmail.com)" required>

                    <label for="staffRole">Role</label>
                    <select id="staffRole" name="role" required>
                        <option value="" disabled selected>Select your role</option>
                        <option value="admin">Admin</option>
                        <?php foreach (staff_role_labels() as $roleKey => $roleName): ?>
                            <option value="<?= e($roleKey) ?>"><?= e($roleName) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="rolePassword">Password</label>
                    <div class="field">
                        <input id="rolePassword" name="password" type="password" autocomplete="off" maxlength="60" placeholder="Password for your role" required>
                        <button type="button" class="toggle-pass" data-target="rolePassword" aria-label="Show password">Show</button>
                    </div>
                    <span class="field-hint">Use the password that belongs to your role given by the admin.</span>

                    <label for="securityPassword">Security password</label>
                    <div class="field">
                        <input id="securityPassword" name="security_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Your own spare password (min. 8 characters)" required>
                        <button type="button" class="toggle-pass" data-target="securityPassword" aria-label="Show password">Show</button>
                    </div>
                    <span class="field-hint">A backup password only you know. Do not  share it with others.</span>

                    <label for="confirmSecurityPassword">Confirm security password</label>
                    <div class="field">
                        <input id="confirmSecurityPassword" name="confirm_security_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Type your security password again" required>
                        <button type="button" class="toggle-pass" data-target="confirmSecurityPassword" aria-label="Show password">Show</button>
                    </div>

                    <button class="submit-button" id="submitButton" type="submit" disabled><b>Register</b> <span class="mi" aria-hidden="true">arrow_forward</span></button>
                </form>

                <p class="switch-note">Already registered? <a href="../index.html?mode=admin">Sign in here</a></p>
                <p class="security-note">Customer instead? <a href="register_user.php">Create a customer account</a></p>
            </div>
            <footer>&copy; <span id="year"></span> BuildQuin</footer>
        </section>
    </main>

    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
        const notice = document.getElementById('notice');
        const messages = {
            email_exists: 'An account already uses that email address.',
            invalid_registration: 'Check the form. Enter your full name and a Gmail address (ending in @gmail.com).',
            registration_error: 'The account could not be created. Please try again.',
            choose_role: 'Please choose your role.',
            bad_code: 'That password is not correct for the role you picked.',
            admin_taken: 'The Admin account already exists. Only one Admin is allowed. Choose a staff role instead.',
            security_short: 'Your security password must be at least 8 characters.',
            security_mismatch: 'The security password and its confirmation do not match.',
            security_same: 'Your security password must be different from the password of your role.',
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

        const registerForm = document.getElementById('registerForm');
        registerForm.addEventListener('submit', event => {
            const security = registerForm.elements.security_password;
            const confirm = registerForm.elements.confirm_security_password;
            confirm.setCustomValidity(security.value !== confirm.value ? 'The security passwords do not match.' : '');
            if (!registerForm.checkValidity()) { event.preventDefault(); registerForm.reportValidity(); }
        });
        registerForm.elements.confirm_security_password.addEventListener('input', event => event.target.setCustomValidity(''));

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
    </script>
</body>
</html>