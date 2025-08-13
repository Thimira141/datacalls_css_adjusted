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
    <title>Register - DataCaller</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">
</head>

<body class="register">
    <div class="register-container">
        <div class="register-left">
            <h2>Join DataCaller Today</h2>
            <p>Create an account to unlock powerful data insights and personalized analytics with DataCaller.</p>
        </div>
        <div class="register-right">
            <h3>Create Account</h3>
            <p>Sign up to start your DataCaller journey</p>
            <form id="register-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" required class="form-control" id="username" name="username"
                        placeholder="e.g. Username" pattern="[A-Za-z0-9]{3,20}"
                        title="Username must be 3-20 characters, letters and numbers only">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" required class="form-control" id="email" name="email"
                        placeholder="e.g. email@mail.com">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input type="password" required class="form-control" id="password" name="password"
                            placeholder="Password" aria-describedby="passwordHelp">
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
                            name="confirm-password" placeholder="Confirm Password">
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
                <button type="submit" class="btn btn-register btn-block">Sign Up</button>
                <div class="register-footer text-center">
                    <p>Already have an account? <a href="./login.php">Sign in</a></p>
                </div>
                <div id="message" aria-live="polite"></div>
            </form>
        </div>
    </div>
    <script>
    function togglePassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('message');
        const button = form.querySelector('.btn-register');
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        // Client-side validation
        if (username === '' || email === '' || password === '' || confirmPassword === '') {
            messageDiv.textContent = 'All fields are required';
            messageDiv.className = 'error';
            messageDiv.style.display = 'block';
            return;
        }
        if (!/^[A-Za-z0-9]{3,20}$/.test(username)) {
            messageDiv.textContent = 'Username must be 3-20 characters, letters and numbers only';
            messageDiv.className = 'error';
            messageDiv.style.display = 'block';
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            messageDiv.textContent = 'Invalid email format';
            messageDiv.className = 'error';
            messageDiv.style.display = 'block';
            return;
        }
        if (!/^.{8,}$/.test(password)) {
            messageDiv.textContent = 'Password must be at least 8 characters';
            messageDiv.className = 'error';
            messageDiv.style.display = 'block';
            return;
        }
        if (password !== confirmPassword) {
            messageDiv.textContent = 'Passwords do not match';
            messageDiv.className = 'error';
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
                body: new URLSearchParams(formData).toString()
            });
            const data = await response.json();

            messageDiv.textContent = data.message;
            messageDiv.className = data.status === 'success' ? 'success' : 'error';
            messageDiv.style.display = 'block';

            if (data.status === 'success') {
                setTimeout(() => {
                    window.location.href = './login.php';
                }, 2000);
            }
        } catch (error) {
            console.error('Registration error:', error);
            messageDiv.textContent = 'Error registering. Please try again.';
            messageDiv.className = 'error';
            messageDiv.style.display = 'block';
        } finally {
            form.classList.remove('loading');
            button.disabled = false;
        }
    });
    </script>
</body>

</html>