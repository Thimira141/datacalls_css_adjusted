<?php
session_start();
// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - DataCaller</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/auth.css" rel="stylesheet">
</head>

<body class="reset-password">
    <div class="reset-password-container">
        <div class="reset-password-left">
            <h2>Reset Your Password</h2>
            <p>Enter your new password to regain access to your DataCaller account.</p>
        </div>
        <div class="reset-password-right">
            <h3>Set New Password</h3>
            <p>Choose a strong password for your account</p>
            <form id="resetPasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-group">
                        <input type="password" required class="form-control" id="password" placeholder="New Password"
                            aria-describedby="passwordHelp">
                        <div class="input-group-append">
                            <span class="input-group-text" tabindex="0"
                                onclick="togglePassword('password', 'toggleIcon')"
                                onkeydown="if(event.key === 'Enter') togglePassword('password', 'toggleIcon')"
                                aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                    <small id="passwordHelp" class="form-text text-muted">Password must be at least 8
                        characters.</small>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <div class="input-group">
                        <input type="password" required class="form-control" id="confirm-password"
                            placeholder="Confirm Password">
                        <div class="input-group-append">
                            <span class="input-group-text" tabindex="0"
                                onclick="togglePassword('confirm-password', 'toggleConfirmIcon')"
                                onkeydown="if(event.key === 'Enter') togglePassword('confirm-password', 'toggleConfirmIcon')"
                                aria-label="Toggle confirm password visibility">
                                <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-reset btn-block">Update Password</button>
                <div class="reset-password-footer text-center">
                    <p>Remember your password? <a href="./login.php">Sign in</a></p>
                </div>
                <div id="resetMessage" aria-live="polite"></div>
            </form>
        </div>
    </div>
    <script>
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        const email = urlParams.get('email');
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const csrfToken = form.querySelector('[name="csrf_token"]').value;
        const messageDiv = document.getElementById('resetMessage');
        const button = form.querySelector('.btn-reset');

        // Validate inputs
        if (!token || !email) {
            messageDiv.textContent = 'Invalid or missing reset token';
            messageDiv.style.color = 'red';
            messageDiv.style.display = 'block';
            return;
        }

        if (password !== confirmPassword) {
            messageDiv.textContent = 'Passwords do not match';
            messageDiv.style.color = 'red';
            messageDiv.style.display = 'block';
            return;
        }

        if (password.length < 8) {
            messageDiv.textContent = 'Password must be at least 8 characters';
            messageDiv.style.color = 'red';
            messageDiv.style.display = 'block';
            return;
        }

        // Show loading state
        form.classList.add('loading');
        button.disabled = true;

        try {
            const response = await fetch('./assets/backend/userapi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `email=${encodeURIComponent(email)}&token=${encodeURIComponent(token)}&password=${encodeURIComponent(password)}&csrf_token=${encodeURIComponent(csrfToken)}`
            });

            const data = await response.json();
            messageDiv.textContent = data.message;
            messageDiv.style.display = 'block';
            if (response.ok) {
                messageDiv.style.color = 'green';
                messageDiv.textContent = 'Password updated successfully. Redirecting to login...';
                setTimeout(() => {
                    window.location.href = './login.php';
                }, 2000);
            } else {
                messageDiv.style.color = 'red';
            }
        } catch (error) {
            console.error('Reset password error:', error);
            messageDiv.textContent = 'Error resetting password';
            messageDiv.style.color = 'red';
            messageDiv.style.display = 'block';
        } finally {
            form.classList.remove('loading');
            button.disabled = false;
        }
    });
    </script>
</body>

</html>