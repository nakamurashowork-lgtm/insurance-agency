<?php
declare(strict_types=1);

namespace App;

final class AppConfig
{
    public string $appEnv;
    public string $appUrl;
    public string $appPublicUrl;
    public string $commonDbHost;
    public int $commonDbPort;
    public string $commonDbName;
    public string $commonDbUser;
    public string $commonDbPassword;
    public string $tenantDbHost;
    public int $tenantDbPort;
    public string $tenantDbUser;
    public string $tenantDbPassword;
    public string $googleClientId;
    public string $googleClientSecret;
    public string $googleRedirectUri;
    public string $sessionCookieName;
    public bool $sessionCookieSecure;

    public static function fromEnv(): self
    {
        $config = new self();

        $config->appEnv = self::env('APP_ENV', 'production');
        $config->appUrl = rtrim(self::envWithFallback(['APP_URL', 'APP_BASE_URL'], ''), '/');
        $config->appPublicUrl = rtrim(self::envWithFallback(['APP_PUBLIC_URL'], $config->appUrl), '/');

        $config->commonDbHost = self::env('COMMON_DB_HOST', '127.0.0.1');
        $config->commonDbPort = (int) self::env('COMMON_DB_PORT', '3306');
        $config->commonDbName = self::env('COMMON_DB_NAME', '');
        $config->commonDbUser = self::env('COMMON_DB_USER', '');
        $config->commonDbPassword = self::env('COMMON_DB_PASSWORD', '');

        $config->tenantDbHost = self::env('TENANT_DB_HOST', $config->commonDbHost);
        $config->tenantDbPort = (int) self::env('TENANT_DB_PORT', (string) $config->commonDbPort);
        $config->tenantDbUser = self::env('TENANT_DB_USER', $config->commonDbUser);
        $config->tenantDbPassword = self::env('TENANT_DB_PASSWORD', $config->commonDbPassword);

        $config->googleClientId = self::envWithFallback(['GOOGLE_CLIENT_ID', 'GOOGLE_OAUTH_CLIENT_ID'], '');
        $config->googleClientSecret = self::envWithFallback(['GOOGLE_CLIENT_SECRET', 'GOOGLE_OAUTH_CLIENT_SECRET'], '');

        $defaultRedirect = $config->appUrl !== ''
            ? $config->appUrl . '/?route=auth/google/callback'
            : '';
        $config->googleRedirectUri = self::envWithFallback(['GOOGLE_REDIRECT_URI', 'GOOGLE_OAUTH_REDIRECT_URI'], $defaultRedirect);

        $config->sessionCookieName = self::env('SESSION_COOKIE_NAME', 'INS_AGENCY_SESSID');
        $config->sessionCookieSecure = self::envBool('SESSION_COOKIE_SECURE', true);

        return $config;
    }

    public function hasGoogleOAuthConfig(): bool
    {
        return $this->googleClientId !== ''
            && $this->googleClientSecret !== ''
            && $this->googleRedirectUri !== '';
    }

    public function routeUrl(string $route = ''): string
    {
        $normalizedRoute = trim($route, '/');
        $url = '?route=' . $normalizedRoute;

        if ($this->appUrl !== '') {
            return $this->appUrl . '/' . $url;
        }

        return '/?' . ltrim($url, '?');
    }

    public function assertCommonDbConfigured(): void
    {
        if ($this->commonDbName === '' || $this->commonDbUser === '') {
            throw new ConfigurationException('common DB接続情報が不足しています。');
        }
    }

    public function assertTenantDbConfigured(): void
    {
        if ($this->tenantDbUser === '') {
            throw new ConfigurationException('tenant DB接続情報が不足しています。');
        }
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }

        $trimmed = trim((string) $value);
        return $trimmed === '' ? $default : $trimmed;
    }

    /**
     * @param array<int, string> $names
     */
    private static function envWithFallback(array $names, string $default): string
    {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value === false) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return $default;
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
}
