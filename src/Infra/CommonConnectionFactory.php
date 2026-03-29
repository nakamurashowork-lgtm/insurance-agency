<?php
declare(strict_types=1);

namespace App\Infra;

use App\AppConfig;
use PDO;
use PDOException;
use RuntimeException;

final class CommonConnectionFactory
{
    private ?PDO $connection = null;

    public function __construct(private AppConfig $config)
    {
    }

    public function create(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->config->assertCommonDbConfigured();

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->commonDbHost,
            $this->config->commonDbPort,
            $this->config->commonDbName
        );

        try {
            $this->connection = new PDO($dsn, $this->config->commonDbUser, $this->config->commonDbPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('common DBへ接続できません。', 0, $e);
        }

        return $this->connection;
    }
}
