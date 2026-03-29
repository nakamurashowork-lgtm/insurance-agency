<?php
declare(strict_types=1);

namespace App\Domain\Auth;

use App\AppConfig;

final class GoogleOAuthClient
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(private AppConfig $config)
    {
    }

    public function buildAuthorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->config->googleClientId,
            'redirect_uri' => $this->config->googleRedirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return self::AUTH_ENDPOINT . '?' . $query;
    }

    /**
     * @return array{sub: string, email: string, name: string}
     */
    public function fetchIdentityFromAuthorizationCode(string $code): array
    {
        $tokenResponse = $this->requestToken($code);
        $accessToken = $tokenResponse['access_token'] ?? '';
        if (!is_string($accessToken) || $accessToken === '') {
            throw new AuthException('Google認証のトークン取得に失敗しました。');
        }

        $userInfo = $this->requestUserInfo($accessToken);

        $sub = $userInfo['sub'] ?? '';
        $email = $userInfo['email'] ?? '';
        $name = $userInfo['name'] ?? '';

        if (!is_string($sub) || $sub === '') {
            throw new AuthException('Google認証のユーザー情報取得に失敗しました。');
        }

        return [
            'sub' => $sub,
            'email' => is_string($email) ? $email : '',
            'name' => is_string($name) ? $name : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestToken(string $code): array
    {
        $payload = http_build_query([
            'code' => $code,
            'client_id' => $this->config->googleClientId,
            'client_secret' => $this->config->googleClientSecret,
            'redirect_uri' => $this->config->googleRedirectUri,
            'grant_type' => 'authorization_code',
        ]);

        return $this->requestJson(
            self::TOKEN_ENDPOINT,
            [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            $payload
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestUserInfo(string $accessToken): array
    {
        return $this->requestJson(
            self::USERINFO_ENDPOINT,
            [
                'Authorization: Bearer ' . $accessToken,
            ],
            null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $url, array $headers, ?string $postBody): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new AuthException('Google認証通信の初期化に失敗しました。');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        if ($postBody !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        }

        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new AuthException('Google認証通信に失敗しました。');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new AuthException('Google認証結果が不正です。');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new AuthException('Google認証応答の解析に失敗しました。');
        }

        return $data;
    }
}
