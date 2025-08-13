<?php
session_start();
// Always generate a new CSRF token on page load
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DataCaller</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">
</head>

<body class="login">
    <div class="login-container">
        <div class="login-left">
            <h2>Empower Your Data Journey</h2>
            <p>Sign in to DataCaller to access instant insights, lightning-fast performance, and unparalleled precision.
            </p>
        </div>
        <div class="login-right">
            <h3>Welcome Back</h3>
            <p>Sign in to continue exploring DataCaller</p>
            <form id="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" required class="form-control" id="username" name="username"
                        placeholder="Username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input type="password" required class="form-control" id="password" name="password"
                            placeholder="Password">
                        <div class="input-group-append">
                            <span class="input-group-text" tabindex="0"
                                onclick="togglePassword('password', 'toggleIcon')"
                                onkeydown="if(event.key === 'Enter') togglePassword('password', 'toggleIcon')"
                                aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-login btn-block">Sign In</button>
                <div class="login-footer text-center">
                    <p>Don't have an account? <a href="register.php">Sign up</a></p>
                    <p><a href="forgot-password.php">Forgot Password?</a></p>
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
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('message');
        const button = form.querySelector('.btn-login');
        // Log form data for debugging
        console.log('Form data:', new URLSearchParams(formData).toString());
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
                messageDiv.textContent = 'Login successful. Redirecting...';
                setTimeout(() => {
                    window.location.href = './dashboard.php';
                }, 2000);
            }
        } catch (error) {
            console.error('Login error:', error);
            messageDiv.textContent = `Error logging in: ${error.message}`;
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