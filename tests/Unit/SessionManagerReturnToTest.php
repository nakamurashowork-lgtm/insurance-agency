<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\AppConfig;
use App\SessionManager;
use PHPUnit\Framework\TestCase;

final class SessionManagerReturnToTest extends TestCase
{
    private SessionManager $session;

    protected function setUp(): void
    {
        $config = new AppConfig();
        $config->sessionCookieName = 'INS_AGENCY_SESSID_TEST';
        $config->sessionCookieSecure = false;

        $this->session = new SessionManager($config);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function test_takeReturnTo_returns_stored_value_and_clears_it(): void
    {
        $this->session->setReturnTo('renewal/list');

        self::assertSame('renewal/list', $this->session->takeReturnTo());
        self::assertNull($this->session->takeReturnTo(), 'consumed on first take');
    }

    public function test_takeReturnTo_returns_null_when_not_set(): void
    {
        self::assertNull($this->session->takeReturnTo());
    }

    public function test_takeReturnTo_returns_null_for_empty_string(): void
    {
        $this->session->setReturnTo('');
        self::assertNull($this->session->takeReturnTo());
    }
}
