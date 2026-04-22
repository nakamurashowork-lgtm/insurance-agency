<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use App\AppConfig;
use App\Security\AuthGuard;
use App\SessionManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * AuthGuard::rememberReturnTo() の判定ロジックを検証する。
 *
 * requireAuthenticated() 内部のリダイレクト (Responses::redirect = exit) は
 * ユニットテスト対象外。return_to 保存部分のみリフレクションで直接叩く。
 */
final class AuthGuardReturnToTest extends TestCase
{
    private SessionManager $session;
    private ReflectionMethod $remember;
    private AuthGuard $guard;

    protected function setUp(): void
    {
        $config = new AppConfig();
        $config->appUrl = '';
        $config->sessionCookieName = 'INS_AGENCY_SESSID_TEST';
        $config->sessionCookieSecure = false;

        $this->session = new SessionManager($config);
        $guard = new AuthGuard($this->session, $config);

        $ref = new \ReflectionClass($guard);
        $this->remember = $ref->getMethod('rememberReturnTo');
        $this->remember->setAccessible(true);

        $_SESSION = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->guard = $guard;
    }

    public function test_saves_route_for_protected_get_request(): void
    {
        $_GET['route'] = 'renewal/list';
        $this->remember->invoke($this->guard);

        self::assertSame('renewal/list', $this->session->takeReturnTo());
    }

    public function test_saves_accident_list_route(): void
    {
        $_GET['route'] = 'accident/list';
        $this->remember->invoke($this->guard);

        self::assertSame('accident/list', $this->session->takeReturnTo());
    }

    public function test_ignores_login_route(): void
    {
        $_GET['route'] = 'login';
        $this->remember->invoke($this->guard);

        self::assertNull($this->session->takeReturnTo());
    }

    public function test_ignores_auth_prefixed_routes(): void
    {
        $_GET['route'] = 'auth/totp';
        $this->remember->invoke($this->guard);

        self::assertNull($this->session->takeReturnTo());
    }

    public function test_ignores_api_prefixed_routes(): void
    {
        $_GET['route'] = 'api/dashboard/renewal-summary';
        $this->remember->invoke($this->guard);

        self::assertNull($this->session->takeReturnTo());
    }

    public function test_ignores_post_requests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['route'] = 'renewal/update';
        $this->remember->invoke($this->guard);

        self::assertNull($this->session->takeReturnTo());
    }

    public function test_ignores_empty_route(): void
    {
        $_GET['route'] = '';
        $this->remember->invoke($this->guard);

        self::assertNull($this->session->takeReturnTo());
    }

    public function test_trims_leading_and_trailing_slashes(): void
    {
        $_GET['route'] = '/renewal/list/';
        $this->remember->invoke($this->guard);

        self::assertSame('renewal/list', $this->session->takeReturnTo());
    }
}
