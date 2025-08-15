<?php

namespace inc\classes;

require_once __DIR__ . '/../../config.php';

/**
 * Custom authentication class to manage user sessions.
 * @author Thimira Dilshan <thimirad865@gmail.com>
 * @link https://white-moss-03c58b010.2.azurestaticapps.net/
 */

class Auth
{
    protected static ?int $userId = null;

    /**
     * Set the current user ID (e.g. after login)
     */
    public static function setUser(int $id): void
    {
        self::$userId = $id;
        $_SESSION['user_id'] = $id;
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    

    /**
     * Return current user object with ID
     */
    public static function user(): ?object
    {
        if (!self::check()) {
            return null;
        }

        return (object) ['id' => $_SESSION['user_id']];
    }

    /**
     * Clear session and user ID
     */
    public static function logout(): void
    {
        session_unset();
        session_destroy();
        self::$userId = null;
    }
}
