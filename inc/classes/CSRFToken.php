<?php
namespace inc\classes;

require_once __DIR__ . '/../../config.php';

/**
 * Class CSRFToken
 *
 * Handles CSRF (Cross-Site Request Forgery) token generation, validation, and rendering for forms.
 * Implements the singleton pattern to ensure a single instance per request/session.
 *
 * Usage:
 *   $csrf = CSRFToken::getInstance();
 *   $token = $csrf->getToken();
 *   echo $csrf->renderToken(); // For use in forms
 *   $csrf->validateToken($_POST['csrf_token']); // To validate on form submission
 *
 * @package inc\classes
 * @version 1.0
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */
final class CSRFToken
{
    /**
     * The singleton instance of the CSRFToken class.
     * @var CSRFToken|null
     */
    private static $instance = null;

    /**
     * The CSRF token string.
     * @var string
     */
    private $token;

    /**
     * CSRFToken constructor.
     * Private to enforce singleton usage.
     * Initializes the token from session or generates a new one if not present.
     */
    private function __construct()
    {
        if (isset($_SESSION['csrf_token'])) {
            $this->token = $_SESSION['csrf_token'];
        } else {
            $this->generateToken();
        }
    }

    /**
     * Returns the singleton instance of the CSRFToken class.
     *
     * @return CSRFToken The singleton instance.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generates a new CSRF token and stores it in the session.
     *
     * @return void
     */
    public function generateToken()
    {
        $this->token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $this->token;
    }

    /**
     * Returns the current CSRF token.
     *
     * @return string The CSRF token.
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Renders the CSRF token as a HTML tag
     * @param bool $meta when true it render meta tag else render the hidden input field for use in HTML forms.
     * @return string HTML input field containing the CSRF token.
     */
    public function renderToken($meta=false)
    {
        return $meta ? 
        '<meta name="csrf-token" content="' . htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8') . '">'
        : 
        '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8') . '">' ;
    }

    /**
     * Validates a given CSRF token against the token stored in the session.
     *
     * @param string $token The token to validate.
     * @return bool True if the token is valid, false otherwise.
     */
    public function validateToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}