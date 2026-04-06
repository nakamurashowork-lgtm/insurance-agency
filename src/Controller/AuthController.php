<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Auth\AuthService;
use App\Auth\Totp;
use App\ConfigurationException;
use App\Domain\Auth\AuthException;
use App\Domain\Auth\UserRepository;
use App\Domain\Auth\GoogleOAuthClient;
use App\Auth\TenantResolver;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Presentation\View\TotpSetupView;
use App\Presentation\View\TotpView;
use App\Security\AuthGuard;
use App\SessionManager;
use Throwable;

final class AuthController
{
    public function __construct(
        private AppConfig $config,
        private SessionManager $session,
        private GoogleOAuthClient $googleOAuthClient,
        private AuthService $authService,
        private AuthGuard $authGuard,
        private UserRepository $userRepository,
        private TenantResolver $tenantResolver
    ) {
    }

    public function startGoogle(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        $pendingUserId = $this->session->getTotpPendingUserId();
        if ($pendingUserId !== null) {
            $pendingUser = $this->userRepository->findActiveById($pendingUserId);
            if ($pendingUser !== null && ((int) ($pendingUser['totp_enabled'] ?? 0)) === 1) {
                Responses::redirect($this->config->routeUrl('auth/totp'));
            } elseif ($pendingUser !== null) {
                Responses::redirect($this->config->routeUrl('auth/totp-setup'));
            }
            // ユーザーが見つからない場合はペンディング状態をクリアして通常フローへ
            $this->session->clearTotpPending();
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
            $result   = $this->authService->loginWithGoogleIdentity($identity['sub'], $identity['email'] ?? '');

            match ($result['status']) {
                'authenticated'       => Responses::redirect($this->config->routeUrl('dashboard')),
                'totp_verify_required' => Responses::redirect($this->config->routeUrl('auth/totp')),
                'totp_setup_required'  => Responses::redirect($this->config->routeUrl('auth/totp-setup')),
                default               => Responses::redirect($this->config->routeUrl('login')),
            };
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

    // -------------------------------------------------------------------------
    // TOTP: initial setup (first login)
    // -------------------------------------------------------------------------

    public function totpSetupShow(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        $pendingUserId = $this->authGuard->requireTotpPending();
        $user          = $this->userRepository->findActiveById($pendingUserId);
        if ($user === null) {
            $this->session->clearTotpPending();
            $this->session->setFlash('error', 'セッションが無効です。もう一度ログインしてください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        // Generate a new secret if one is not yet stored for this pending session.
        $secret = trim((string) ($user['totp_secret'] ?? ''));
        if ($secret === '' || ((int) ($user['totp_enabled'] ?? 0)) === 1) {
            // Generate fresh secret and persist it (not yet enabled).
            $secret = Totp::generateSecret();
            $this->userRepository->saveTotpSecret($pendingUserId, $secret);
        }

        $issuer      = 'Insurance Agency';
        $accountName = (string) ($user['email'] ?? (string) $pendingUserId);
        $otpAuthUri  = Totp::buildOtpAuthUri($secret, $issuer, $accountName);
        $csrfToken   = $this->session->issueCsrfToken('totp_setup');

        Responses::html(TotpSetupView::render(
            $secret,
            $otpAuthUri,
            $csrfToken,
            $this->config->routeUrl('auth/totp-setup/verify'),
            $this->session->consumeFlash('error')
        ));
    }

    public function totpSetupVerify(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Responses::redirect($this->config->routeUrl('auth/totp-setup'));
        }

        $pendingUserId = $this->authGuard->requireTotpPending();

        $csrfToken = isset($_POST['_csrf_token']) ? (string) $_POST['_csrf_token'] : '';
        if (!$this->session->validateAndConsumeCsrfToken('totp_setup', $csrfToken)) {
            $this->session->setFlash('error', '不正なリクエストです。もう一度お試しください。');
            Responses::redirect($this->config->routeUrl('auth/totp-setup'));
        }

        $code = isset($_POST['totp_code']) ? trim((string) $_POST['totp_code']) : '';
        $user = $this->userRepository->findActiveById($pendingUserId);
        if ($user === null) {
            $this->session->clearTotpPending();
            $this->session->setFlash('error', 'セッションが無効です。もう一度ログインしてください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        $secret = trim((string) ($user['totp_secret'] ?? ''));
        if ($secret === '') {
            $this->session->setFlash('error', 'セットアップ情報が見つかりません。もう一度お試しください。');
            Responses::redirect($this->config->routeUrl('auth/totp-setup'));
        }

        if (!Totp::verify($secret, $code, time())) {
            $this->session->setFlash('error', 'コードが正しくありません。アプリのコードを確認してください。');
            Responses::redirect($this->config->routeUrl('auth/totp-setup'));
        }

        // Verification succeeded — enable TOTP and complete login.
        $this->userRepository->enableTotp($pendingUserId);
        $tenant = $this->tenantResolver->resolvePrimaryTenantForUser($pendingUserId);
        $this->authService->completeLogin($user, $tenant);

        Responses::redirect($this->config->routeUrl('dashboard'));
    }

    // -------------------------------------------------------------------------
    // TOTP: verification (subsequent logins)
    // -------------------------------------------------------------------------

    public function totpShow(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        $this->authGuard->requireTotpPending();

        $csrfToken = $this->session->issueCsrfToken('totp_verify');
        Responses::html(TotpView::render(
            $csrfToken,
            $this->config->routeUrl('auth/totp/verify'),
            $this->session->consumeFlash('error')
        ));
    }

    public function totpVerify(): void
    {
        if ($this->session->isAuthenticated()) {
            Responses::redirect($this->config->routeUrl('dashboard'));
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            Responses::redirect($this->config->routeUrl('auth/totp'));
        }

        $pendingUserId = $this->authGuard->requireTotpPending();

        $csrfToken = isset($_POST['_csrf_token']) ? (string) $_POST['_csrf_token'] : '';
        if (!$this->session->validateAndConsumeCsrfToken('totp_verify', $csrfToken)) {
            $this->session->setFlash('error', '不正なリクエストです。もう一度お試しください。');
            Responses::redirect($this->config->routeUrl('auth/totp'));
        }

        $code = isset($_POST['totp_code']) ? trim((string) $_POST['totp_code']) : '';
        $user = $this->userRepository->findActiveById($pendingUserId);
        if ($user === null) {
            $this->session->clearTotpPending();
            $this->session->setFlash('error', 'セッションが無効です。もう一度ログインしてください。');
            Responses::redirect($this->config->routeUrl('login'));
        }

        $secret = trim((string) ($user['totp_secret'] ?? ''));
        if ($secret === '' || ((int) ($user['totp_enabled'] ?? 0)) !== 1) {
            $this->session->setFlash('error', 'TOTP設定が見つかりません。管理者へ連絡してください。');
            Responses::redirect($this->config->routeUrl('auth/totp'));
        }

        if (!Totp::verify($secret, $code, time())) {
            $this->session->setFlash('error', 'コードが正しくありません。認証アプリのコードを確認してください。');
            Responses::redirect($this->config->routeUrl('auth/totp'));
        }

        $tenant = $this->tenantResolver->resolvePrimaryTenantForUser($pendingUserId);
        $this->authService->completeLogin($user, $tenant);

        Responses::redirect($this->config->routeUrl('dashboard'));
    }

    // -------------------------------------------------------------------------

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
