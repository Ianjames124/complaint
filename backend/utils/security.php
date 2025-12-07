<?php

/**
 * Security Utilities
 * Input validation, sanitization, and security helpers
 */

/**
 * Sanitize string input to prevent XSS
 * 
 * @param string $input Input string
 * @param bool $allowHtml Whether to allow HTML (default: false)
 * @return string Sanitized string
 */
function sanitizeInput(string $input, bool $allowHtml = false): string {
    if ($allowHtml) {
        // Allow HTML but sanitize dangerous tags
        $input = strip_tags($input, '<p><br><strong><em><ul><ol><li><a>');
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Remove all HTML tags and encode special characters
    $input = strip_tags($input);
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate and sanitize email address
 * 
 * @param string $email Email address
 * @return string|false Sanitized email or false if invalid
 */
function validateEmail(string $email) {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : false;
}

/**
 * Validate and sanitize integer
 * 
 * @param mixed $value Value to validate
 * @param int|null $min Minimum value (optional)
 * @param int|null $max Maximum value (optional)
 * @return int|false Validated integer or false if invalid
 */
function validateInt($value, ?int $min = null, ?int $max = null) {
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false) {
        return false;
    }
    
    if ($min !== null && $int < $min) {
        return false;
    }
    
    if ($max !== null && $int > $max) {
        return false;
    }
    
    return $int;
}

/**
 * Validate and sanitize string with length constraints
 * 
 * @param string $value Value to validate
 * @param int $minLength Minimum length
 * @param int $maxLength Maximum length
 * @param bool $allowHtml Whether to allow HTML
 * @return string|false Sanitized string or false if invalid
 */
function validateString(string $value, int $minLength = 0, int $maxLength = PHP_INT_MAX, bool $allowHtml = false) {
    $sanitized = sanitizeInput($value, $allowHtml);
    $length = mb_strlen($sanitized);
    
    if ($length < $minLength || $length > $maxLength) {
        return false;
    }
    
    return $sanitized;
}

/**
 * Validate enum value
 * 
 * @param mixed $value Value to validate
 * @param array $allowedValues Array of allowed values
 * @return mixed Validated value or false if invalid
 */
function validateEnum($value, array $allowedValues) {
    if (!in_array($value, $allowedValues, true)) {
        return false;
    }
    return $value;
}

/**
 * Sanitize array of strings
 * 
 * @param array $input Array of strings
 * @param bool $allowHtml Whether to allow HTML
 * @return array Sanitized array
 */
function sanitizeArray(array $input, bool $allowHtml = false): array {
    $sanitized = [];
    foreach ($input as $key => $value) {
        $sanitizedKey = sanitizeInput((string)$key, false);
        if (is_string($value)) {
            $sanitized[$sanitizedKey] = sanitizeInput($value, $allowHtml);
        } elseif (is_array($value)) {
            $sanitized[$sanitizedKey] = sanitizeArray($value, $allowHtml);
        } else {
            $sanitized[$sanitizedKey] = $value;
        }
    }
    return $sanitized;
}

/**
 * Get and sanitize POST data
 * 
 * @param string|null $key Specific key to get (optional)
 * @param mixed $default Default value if key not found
 * @return mixed Sanitized data
 */
function getSanitizedPost(?string $key = null, $default = null) {
    if ($key === null) {
        return sanitizeArray($_POST ?? []);
    }
    
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    $value = $_POST[$key];
    if (is_string($value)) {
        return sanitizeInput($value);
    } elseif (is_array($value)) {
        return sanitizeArray($value);
    }
    
    return $value;
}

/**
 * Get and sanitize GET data
 * 
 * @param string|null $key Specific key to get (optional)
 * @param mixed $default Default value if key not found
 * @return mixed Sanitized data
 */
function getSanitizedGet(?string $key = null, $default = null) {
    if ($key === null) {
        return sanitizeArray($_GET ?? []);
    }
    
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    $value = $_GET[$key];
    if (is_string($value)) {
        return sanitizeInput($value);
    } elseif (is_array($value)) {
        return sanitizeArray($value);
    }
    
    return $value;
}

/**
 * Validate JSON input and return sanitized array
 * 
 * @param string $json JSON string
 * @return array|false Decoded and sanitized array or false on error
 */
function validateJsonInput(string $json) {
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    if (!is_array($data)) {
        return false;
    }
    
    return sanitizeArray($data);
}

/**
 * Generate secure random token
 * 
 * @param int $length Token length in bytes
 * @return string Hexadecimal token
 */
function generateSecureToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password using bcrypt
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * 
 * @param string $password Plain text password
 * @param string $hash Password hash
 * @return bool True if password matches
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Check if password needs rehashing (e.g., if cost parameter changed)
 * 
 * @param string $hash Current password hash
 * @return bool True if password needs rehashing
 */
function passwordNeedsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

