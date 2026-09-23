<?php

declare(strict_types=1);

namespace Media;

use PDO;
use RuntimeException;

final class Auth
{
    private const SESSION_KEY = 'media_user_id';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function attempt(PDO $db, string $email, string $password): bool
    {
        self::startSession();

        $stmt = $db->prepare(
            'SELECT id, password_hash
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'email' => strtolower(trim($email)),
        ]);

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = (int) $user['id'];

        return true;
    }

    public static function check(PDO $db): bool
    {
        self::startSession();

        $userId = (int) ($_SESSION[self::SESSION_KEY] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        $stmt = $db->prepare(
            'SELECT id
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $userId,
        ]);

        if (!$stmt->fetch()) {
            self::logout();
            return false;
        }

        return true;
    }

    public static function requireLogin(PDO $db): void
    {
        if (self::check($db)) {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/admin/events/';

        self::startSession();

        $_SESSION['auth_redirect'] = $requestUri;

        header('Location: /admin/login.php');
        exit;
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'] ?? '',
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();

        return is_string($token)
            && $token !== ''
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function redirectAfterLogin(): never
    {
        self::startSession();

        $redirect = $_SESSION['auth_redirect'] ?? '/admin/events/';

        unset($_SESSION['auth_redirect']);

        if (
            !is_string($redirect)
            || $redirect === ''
            || !str_starts_with($redirect, '/')
            || str_starts_with($redirect, '//')
        ) {
            $redirect = '/admin/events/';
        }

        header('Location: ' . $redirect);
        exit;
    }
}
