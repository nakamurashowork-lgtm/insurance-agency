<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Auth\AuthService;
use App\ConfigurationException;
use App\Domain\Auth\AuthException;
use App\Domain\Auth\GoogleOAuthClient;
use App\Http\Responses;
use App\SessionManager;
use Throwable;

final class AuthController
{
    public function __construct(
        private AppConfig $config,
        private SessionManager $session,
        private GoogleOAuthClient $googleOAuthClient,
        private AuthService $authService
    ) {
    }

    public function startGoogle(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        if (!$this->config->hasGoogleOAuthConfig()) {
            $this->session->setFlash('error', '設定不足のためログインを開始できません。管理者へ連絡してください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        $state = $this->session->issueOauthState();
        $authUrl = $this->googleOAuthClient->buildAuthorizationUrl($state);
        Responses::redirect($authUrl);
    }

    public function handleGoogleCallback(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        $state = isset($_GET['state']) ? (string) $_GET['state'] : '';
        if (!$this->session->validateAndConsumeOauthState($state)) {
            $this->session->setFlash('error', '認証失敗のためログインできません。もう一度お試しください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        if (isset($_GET['error'])) {
            $this->session->setFlash('error', '認証失敗のためログインできません。もう一度お試しください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        $code = isset($_GET['code']) ? (string) $_GET['code'] : '';
        if ($code === '') {
            $this->session->setFlash('error', '認証失敗のためログインできません。もう一度お試しください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        try {
            $identity = $this->googleOAuthClient->fetchIdentityFromAuthorizationCode($code);
            $this->authService->loginWithGoogleIdentity($identity['sub'], $identity['email'] ?? '');
            Responses::redirect($this->config->routeUrl('dashboard'));
        } catch (AuthException $e) {
            $this->session->setFlash('error', $e->getMessage());
            Responses::redirect($this->config->routeUrl('login'));
        } catch (ConfigurationException) {
            $this->session->setFlash('error', '設定不足のためログインできません。管理者へ連絡してください。');
            Responses::redirect($this->config->routeUrl('login'));
        } catch (Throwable) {
            $this->session->setFlash('error', 'システムエラーが発生しました。時間をおいて再度お試しください。');
            Responses::redirect($this->config->routeUrl('login'));
        }
    }

    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            Responses::html('<h1>405 Method Not Allowed</h1>');
            return;
        }

        $token = isset($_POST['_csrf_token']) ? (string) $_POST['_csrf_token'] : '';
        if (!$this->session->validateAndConsumeCsrfToken('logout', $token)) {
            $this->session->setFlash('error', '不正なログアウト要求を検出しました。');
            if ($this->session->isAuthenticated()) {
                Responses::redirect($this->config->routeUrl('dashboard'));
            }

            Responses::redirect($this->config->routeUrl('login'));
        }

        $this->session->clearAuth();
        $this->session->destroy();
        Responses::redirect($this->config->routeUrl('login'));
    }
}
