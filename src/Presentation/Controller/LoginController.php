<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\AppConfig;
use App\Http\Responses;
use App\Presentation\View\LoginView;
use App\SessionManager;

final class LoginController
{
    public function __construct(private SessionManager $session, private AppConfig $config)
    {
    }

    public function show(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        $error = $this->session->consumeFlash('error');
        $devLoginBaseUrl = $this->config->routeUrl('dev/login');
        $devLoginButtons = null;
        if ($this->config->appEnv === 'local') {
            $devLoginButtons = [
                'label'   => 'ローカル開発専用 (APP_ENV=local)',
                'buttons' => [
                    ['url' => $devLoginBaseUrl, 'label' => '開発用ログイン (dev@local.test)'],
                ],
            ];
        } elseif ($this->config->appEnv === 'staging') {
            $sep = str_contains($devLoginBaseUrl, '?') ? '&' : '?';
            $devLoginButtons = [
                'label'   => 'ステージング確認用 (APP_ENV=staging)',
                'buttons' => [
                    ['url' => $devLoginBaseUrl . $sep . 'email=' . rawurlencode('staff1@te002.test'), 'label' => 'staff1@te002.test'],
                    ['url' => $devLoginBaseUrl . $sep . 'email=' . rawurlencode('staff2@te002.test'), 'label' => 'staff2@te002.test'],
                ],
            ];
        }
        Responses::html(LoginView::render($error, $this->config->routeUrl('auth/google/start'), $devLoginButtons));
    }
}
