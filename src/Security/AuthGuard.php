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
            $this->session->setFlash('error', 'ログインが必要です。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        return $auth;
    }
}
