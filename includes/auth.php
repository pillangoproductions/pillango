<?php
/**
 * Admin session handling: login state, idle timeout, CSRF tokens.
 * Include this at the top of every /admin page and every API that
 * mutates data.
 */

require_once __DIR__ . '/functions.php';

/**
 * Start (or resume) the hardened admin session exactly once.
 */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,               // session cookie
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/**
 * Is an admin currently logged in (and not timed out)?
 * A timed-out session is destroyed as a side effect.
 */
function is_logged_in(): bool
{
    session_boot();

    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    $idle = time() - (int) ($_SESSION['last_activity'] ?? 0);
    if ($idle > SESSION_TIMEOUT_MINUTES * 60) {
        logout_user();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Gate an admin page: redirect to the login screen when not logged in.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect(BASE_URL . '/admin/login.php');
    }
}

/**
 * Attempt a login. Returns true on success.
 */
function login_user(string $username, string $password): bool
{
    session_boot();

    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Fresh session id on privilege change.
    session_regenerate_id(true);
    $_SESSION['admin_id']      = (int) $user['id'];
    $_SESSION['admin_name']    = $user['username'];
    $_SESSION['last_activity'] = time();

    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
        ->execute([':id' => $user['id']]);

    return true;
}

/**
 * Destroy the current session.
 */
function logout_user(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Return the session's CSRF token, creating it on first use.
 */
function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden form field carrying the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Abort the request unless the posted CSRF token is valid.
 * Call this at the top of every POST handler.
 */
function csrf_verify(): void
{
    session_boot();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        die('Érvénytelen vagy lejárt űrlap. Frissítsd az oldalt, és próbáld újra.');
    }
}

/**
 * Queue a one-shot status message for the next admin page view.
 */
function flash_set(string $message, string $type = 'ok'): void
{
    session_boot();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/**
 * Read + clear the queued status message. NULL when none.
 *
 * @return array{message:string, type:string}|null
 */
function flash_get(): ?array
{
    session_boot();
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
