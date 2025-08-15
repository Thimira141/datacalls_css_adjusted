<?php
require_once __DIR__ . '/../config.php';

use inc\classes\CSRFToken;
use inc\classes\Auth;

if (Auth::check()) {
    header("Location: {$config->pUrl}/dashboard.php");
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
    <?= CSRFToken::getInstance()->renderToken(true); ?>
    <link href="<?=$config->pUrl;?>/css/auth.css" rel="stylesheet">
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
                <?= CSRFToken::getInstance()->renderToken(); ?>
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
</body>
<script src="<?=$config->pUrl;?>/js/functions.js"></script>
<script src="<?=$config->pUrl;?>/js/axios.js"></script>
<script>
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
            const response = await axios.post('<?=$config->app->url;?>/controller/userapi.php', new URLSearchParams(formData).toString());
            const data = response.data;
            messageDiv.innerHTML = formatErrorMessage(data);
            messageDiv.className = data.status === 'success' ? 'success' : 'error';
            messageDiv.style.display = 'block';

            if (data.status === 'success') {
                messageDiv.textContent = 'Login successful. Redirecting...';
                setTimeout(() => {
                    window.location.href = '<?=$config->pUrl;?>/dashboard.php';
                }, 2000);
            }
        } catch (error) {
            console.error('Login error:', error);
            messageDiv.innerHTML = `Error logging in: ${formatErrorMessage(error)}`;
            messageDiv.className = 'error';
            messageDiv.style.display = 'block';
        } finally {
            form.classList.remove('loading');
            button.disabled = false;
        }
    });
</script>

</html>