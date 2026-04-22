<?php
declare(strict_types=1);

namespace App\Security;

use App\AppConfig;
use App\Http\Responses;
use App\SessionManager;

final class AuthGuard
{
    public function __construct(private SessionManager $session, private AppConfig $config)
    {
    }

    public function session(): SessionManager
    {
        return $this->session;
    }

    /**
     * @return array<string, mixed>
     */
    public function requireAuthenticated(): array
    {
        $auth = $this->session->getAuth();
        if ($auth === null || !isset($auth['user_id'])) {
            $this->rememberReturnTo();
            $this->session->setFlash('error', 'ログインが必要です。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        return $auth;
    }

    /**
     * 未認証で保護ルートにアクセスされた場合に、元の route をセッションに保存する。
     * GET リクエスト かつ 認証/API 系以外のみ対象とする（ログイン後に戻す意味があるもの）。
     */
    private function rememberReturnTo(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET') {
            return;
        }

        $route = isset($_GET['route']) ? trim((string) $_GET['route'], '/') : '';
        if ($route === '') {
            return;
        }

        if ($route === 'login' || str_starts_with($route, 'auth/') || str_starts_with($route, 'api/')) {
            return;
        }

        $this->session->setReturnTo($route);
    }

    /**
     * Ensure the request is in the TOTP-pending state (Google auth done, TOTP not yet verified).
     * Redirects to login if the session has no pending user.
     */
    public function requireTotpPending(): int
    {
        $pendingUserId = $this->session->getTotpPendingUserId();
        if ($pendingUserId === null) {
            $this->session->setFlash('error', 'セッションが無効です。もう一度ログインしてください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        return $pendingUserId;
    }
}
