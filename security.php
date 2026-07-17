<?php
/**
 * core/security.php
 *
 * Authentication & sanitization helper library.
 *
 * Security principles applied:
 *  - Sessions are started defensively (only if one isn't already active)
 *    so this file can be safely included from multiple entry points.
 *  - CSRF tokens are generated with random_bytes(32), a cryptographically
 *    secure PRNG source, and are compared with hash_equals() to prevent
 *    timing side-channel attacks that could let an attacker guess the
 *    token byte-by-byte.
 *  - escapeHTML() centralizes output escaping so every place that prints
 *    user-controlled data into HTML goes through one audited function,
 *    reducing the chance of a missed encoding call leading to XSS.
 */

declare(strict_types=1);

// Start the session once, defensively. Cookie params are hardened before
// the session starts (must happen before session_start()).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,      // JS cannot read the session cookie.
        'samesite' => 'Lax',     // Mitigates basic CSRF via cross-site nav.
    ]);
    session_start();
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 *
 * random_bytes(32) draws from the OS's cryptographically secure random
 * source. bin2hex() turns the 32 raw bytes into a 64-character
 * hex string that is safe to embed in HTML attributes and POST bodies.
 *
 * The token is only generated once per session and then reused, so that
 * a user with multiple tabs open doesn't invalidate their own token.
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify an inbound CSRF token against the one stored in the session.
 *
 * hash_equals() performs a constant-time string comparison, so the
 * amount of time the check takes does not leak how many leading
 * characters matched. A naive `===` comparison is vulnerable to a
 * timing attack that can recover the token character by character.
 */
function verifyCSRFToken(?string $token): bool
{
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape a string for safe inclusion in HTML output.
 *
 * ENT_QUOTES escapes both single and double quotes, which is required
 * whenever the value might be placed inside an HTML attribute delimited
 * by either quote style. ENT_HTML5 selects HTML5 entity rules. The
 * explicit 'UTF-8' charset argument prevents a legacy PHP default
 * (ISO-8859-1) from mis-encoding multi-byte characters, which in older
 * PHP versions could itself be leveraged to bypass escaping.
 */
function escapeHTML(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Trim and collapse a raw input value into a clean string.
 * This is a light sanitation helper for form fields; it does not replace
 * validation (e.g. FILTER_VALIDATE_EMAIL) but keeps stray whitespace and
 * null bytes out of stored data.
 */
function cleanInput(?string $value): string
{
    $value = (string) $value;
    $value = str_replace("\0", '', $value); // Strip null bytes.
    return trim($value);
}
