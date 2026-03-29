<?php
declare(strict_types=1);

namespace App\Infra;

use App\AppConfig;
use PDO;
use PDOException;
use RuntimeException;

final class TenantConnectionFactory
{
    /**
     * @var array<string, PDO>
     */
    private array $connections = [];

    public function __construct(private AppConfig $config)
    {
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function createForAuthenticatedUser(array $auth): PDO
    {
        $tenantDbName = $auth['tenant_db_name'] ?? null;
        if (!is_string($tenantDbName) || $tenantDbName === '') {
            throw new RuntimeException('テナントDB名がセッションに存在しません。');
        }

        return $this->createByDbName($tenantDbName);
    }

    public function createByDbName(string $dbName): PDO
    {
        if (isset($this->connections[$dbName])) {
            return $this->connections[$dbName];
        }

        $this->config->assertTenantDbConfigured();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->tenantDbHost,
            $this->config->tenantDbPort,
            $dbName
        );

        try {
            $connection = new PDO($dsn, $this->config->tenantDbUser, $this->config->tenantDbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('tenant DBへ接続できません。', 0, $e);
        }

        $this->connections[$dbName] = $connection;
        return $connection;
    }
}
