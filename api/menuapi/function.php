<?php
/**
 * Common Utility Functions
 *
 * This file contains reusable helper functions used across the Asterisk call control API.
 * It centralizes logic for input sanitization, error handling, response formatting, and
 * other shared tasks to keep endpoint scripts clean and modular.
 *
 * @Author thimira dilshan <thimirad865@gmail.com>
 * @LastUpdated 2025-10-07
 */
/**
 * echo an json string as json response
 * @param string $message 
 * @param mixed $code
 * @return never
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    error_log("[ERROR] [$code] [" . date("Y-m-d H-i-s") . "]" . $message);
    exit;
}

/**
 * Summary of sanitizeText
 * @param mixed $text
 * @param int $length
 * @return string
 * @author Thimira Dilshan <thimirad865@gmail.com>
 */
function sanitizeText($text, $length = 50)
{
    // Trim whitespace
    $text = trim($text);

    // Remove HTML tags
    $text = strip_tags($text);

    // Convert special characters to safe entities
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Remove non-printable characters
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);

    // Limit length to 50 characters (MagnusBilling CDR constraint)
    $text = substr($text, 0, $length);

    return $text;
}
