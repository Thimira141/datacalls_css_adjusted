/**
 * toggle on password type between text/password
 * @param {*} inputId 
 * @param {*} iconId 
 */
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

/**
 * takes an error object (like from Axios or Fetch) and formats the message for display, 
 * whether it’s a single string or an array of messages from your PHP backend
 * @param {mixed} error 
 * @returns 
 */
function formatErrorMessage(error) {
    // Try to extract the message from the response
    let rawMessage = error?.response?.data?.message || error.message || 'Unknown error';

    console.log(typeof rawMessage);
    console.log(rawMessage);
    // If it's an array (e.g. from PHP validation), join with <br>
    if (Array.isArray(rawMessage)) {
        return rawMessage.map(msg => escapeHtml(msg)).join('<br>');
    }

    // If it's a string with \n, convert to <br> for HTML display
    return escapeHtml(rawMessage).replace(/\n/g, '<br>');
}

// Optional: Escape HTML to prevent injection
function escapeHtml(str) {
    return str.replace(/[&<>"']/g, tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[tag]));
}
