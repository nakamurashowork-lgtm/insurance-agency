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
        $devLoginUrl = $this->config->appEnv === 'local'
            ? $this->config->routeUrl('dev/login')
            : null;
        Responses::html(LoginView::render($error, $this->config->routeUrl('auth/google/start'), $devLoginUrl));
    }
}
