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
    <title>Forgot Password - DataCaller</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">    
</head>

<body class="forgot-password">
    <div class="forgot-password-container">
        <div class="forgot-password-left">
            <h2>Reset Your Password</h2>
            <p>Enter your email to receive a password reset link and regain access to your DataCaller account.</p>
        </div>
        <div class="forgot-password-right">
            <h3>Forgot Password</h3>
            <p>Enter your email address to reset your password</p>
            <form id="resetForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" required class="form-control" id="email" name="email" placeholder="Email">
                </div>
                <button type="submit" class="btn btn-reset btn-block">Reset</button>
                <div class="forgot-password-footer text-center">
                    <p>Remember your password? <a href="./login.php">Sign in</a></p>
                </div>
                <div id="resetMessage" aria-live="polite"></div>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('resetForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('resetMessage');
        const button = form.querySelector('.btn-reset');
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
            messageDiv.style.display = 'block';
            messageDiv.style.color = data.status === 'success' ? 'green' : 'red';
            if (data.status === 'success') {
                form.reset();
            }
        } catch (error) {
            console.error('Reset request error:', error);
            messageDiv.textContent = `Error sending reset request: ${error.message}`;
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