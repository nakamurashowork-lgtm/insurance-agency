<?php
declare(strict_types=1);

namespace App;

final class SessionManager
{
    private const AUTH_KEY = 'auth';
    private const FLASH_KEY = '_flash';
    private const OAUTH_STATE_KEY = '_oauth_state';
    private const CSRF_TOKENS_KEY = '_csrf_tokens';

    public function __construct(private AppConfig $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->config->sessionCookieSecure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');

        session_name($this->config->sessionCookieName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->config->sessionCookieSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function setAuth(array $auth): void
    {
        $_SESSION[self::AUTH_KEY] = $auth;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAuth(): ?array
    {
        $value = $_SESSION[self::AUTH_KEY] ?? null;
        return is_array($value) ? $value : null;
    }

    public function isAuthenticated(): bool
    {
        $auth = $this->getAuth();
        return is_array($auth) && isset($auth['user_id']);
    }

    public function clearAuth(): void
    {
        unset($_SESSION[self::AUTH_KEY]);
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public function setFlash(string $key, string $value): void
    {
        if (!isset($_SESSION[self::FLASH_KEY]) || !is_array($_SESSION[self::FLASH_KEY])) {
            $_SESSION[self::FLASH_KEY] = [];
        }

        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    public function consumeFlash(string $key): ?string
    {
        $flash = $_SESSION[self::FLASH_KEY][$key] ?? null;
        if (isset($_SESSION[self::FLASH_KEY][$key])) {
            unset($_SESSION[self::FLASH_KEY][$key]);
        }

        return is_string($flash) ? $flash : null;
    }

    public function issueOauthState(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION[self::OAUTH_STATE_KEY] = $state;
        return $state;
    }

    public function validateAndConsumeOauthState(string $state): bool
    {
        $stored = $_SESSION[self::OAUTH_STATE_KEY] ?? null;
        unset($_SESSION[self::OAUTH_STATE_KEY]);

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $state);
    }

    public function issueCsrfToken(string $purpose): string
    {
        $token = bin2hex(random_bytes(16));
        if (!isset($_SESSION[self::CSRF_TOKENS_KEY]) || !is_array($_SESSION[self::CSRF_TOKENS_KEY])) {
            $_SESSION[self::CSRF_TOKENS_KEY] = [];
        }

        $_SESSION[self::CSRF_TOKENS_KEY][$purpose] = $token;
        return $token;
    }

    public function validateAndConsumeCsrfToken(string $purpose, string $token): bool
    {
        $stored = $_SESSION[self::CSRF_TOKENS_KEY][$purpose] ?? null;
        unset($_SESSION[self::CSRF_TOKENS_KEY][$purpose]);

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }
}
