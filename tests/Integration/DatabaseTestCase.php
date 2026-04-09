<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * 統合テスト基底クラス
 *
 * テスト用 MySQL DB (xs000001_test) に接続し、
 * 各テスト実行前に対象テーブルを TRUNCATE する。
 *
 * 前提: tests/db/setup_test_db.sh を事前に実行してスキーマが存在すること。
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    /** TRUNCATE する順序（外部キー的な参照順を考慮して子 → 親の順） */
    private const TRUNCATE_ORDER = [
        't_sjnet_import_row',
        't_sjnet_import_batch',
        't_audit_event_detail',
        't_audit_event',
        't_notification_delivery',
        't_notification_run',
        't_accident_reminder_rule_weekday',
        't_accident_reminder_rule',
        't_case_comment',
        't_activity',
        't_renewal_case',
        't_accident_case',
        't_sales_case',
        't_sales_performance',
        't_contract',
        'm_staff',
        'm_customer',
    ];

    protected function setUp(): void
    {
        $this->pdo = $this->createPdo();
        $this->truncateAllTables();
    }

    private function createPdo(): PDO
    {
        $host   = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $port   = getenv('TEST_DB_PORT') ?: '3306';
        $dbName = getenv('TEST_DB_NAME') ?: 'xs000001_test';
        $user   = getenv('TEST_DB_USER') ?: 'root';
        $pass   = getenv('TEST_DB_PASS') ?: '';

        // 安全装置: テスト DB 名以外への接続を拒否
        if (!str_ends_with($dbName, '_test')) {
            throw new \RuntimeException(
                "TEST_DB_NAME '{$dbName}' は '_test' で終わっていません。" .
                "本番 DB への接続を防ぐためテストを中止します。"
            );
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function truncateAllTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TRUNCATE_ORDER as $table) {
            $this->pdo->exec("TRUNCATE TABLE `{$table}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    // =========================================================
    // マスタデータ投入ヘルパ
    // =========================================================

    /**
     * m_staff に1行挿入し、挿入した id を返す。
     *
     * @param array<string, mixed> $overrides
     */
    protected function createStaff(array $overrides = []): int
    {
        $defaults = [
            'staff_name' => 'テスト担当者',
            'is_sales'   => 1,
            'is_office'  => 0,
            'user_id'    => null,
            'sjnet_code' => null,
            'is_active'  => 1,
            'sort_order' => 0,
            'created_by' => 1,
            'updated_by' => 1,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_staff
               (staff_name, is_sales, is_office, user_id, sjnet_code,
                is_active, sort_order, created_by, updated_by)
             VALUES
               (:staff_name, :is_sales, :is_office, :user_id, :sjnet_code,
                :is_active, :sort_order, :created_by, :updated_by)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * m_customer に1行挿入し、挿入した id を返す。
     *
     * @param array<string, mixed> $overrides
     */
    protected function createCustomer(array $overrides = []): int
    {
        $defaults = [
            'customer_type'      => 'individual',
            'customer_name'      => 'テスト顧客',
            'customer_name_kana' => null,
            'phone'              => null,
            'email'              => null,
            'postal_code'        => null,
            'address1'           => null,
            'address2'           => null,
            'status'             => 'active',
            'note'               => null,
            'is_deleted'         => 0,
            'created_by'         => 1,
            'updated_by'         => 1,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_customer
               (customer_type, customer_name, customer_name_kana, phone, email,
                postal_code, address1, address2, status, note,
                is_deleted, created_by, updated_by)
             VALUES
               (:customer_type, :customer_name, :customer_name_kana, :phone, :email,
                :postal_code, :address1, :address2, :status, :note,
                :is_deleted, :created_by, :updated_by)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * t_contract に1行挿入し、挿入した id を返す。
     *
     * @param array<string, mixed> $overrides
     */
    protected function createContract(array $overrides = []): int
    {
        $defaults = [
            'customer_id'           => 0,
            'policy_no'             => 'TEST-' . uniqid(),
            'product_type'          => null,
            'policy_start_date'     => null,
            'policy_end_date'       => null,
            'premium_amount'        => 0,
            'payment_cycle'         => null,
            'status'                => 'active',
            'sales_staff_id'        => null,
            'office_staff_id'       => null,
            'last_sjnet_imported_at' => null,
            'is_deleted'            => 0,
            'created_by'            => 1,
            'updated_by'            => 1,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO t_contract
               (customer_id, policy_no, product_type,
                policy_start_date, policy_end_date, premium_amount, payment_cycle,
                status, sales_staff_id, office_staff_id, last_sjnet_imported_at,
                is_deleted, created_by, updated_by)
             VALUES
               (:customer_id, :policy_no, :product_type,
                :policy_start_date, :policy_end_date, :premium_amount, :payment_cycle,
                :status, :sales_staff_id, :office_staff_id, :last_sjnet_imported_at,
                :is_deleted, :created_by, :updated_by)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * t_renewal_case に1行挿入し、挿入した id を返す。
     *
     * @param array<string, mixed> $overrides
     */
    protected function createRenewalCase(array $overrides = []): int
    {
        $defaults = [
            'contract_id'            => 0,
            'maturity_date'          => date('Y-m-d'),
            'case_status'            => 'not_started',
            'assigned_staff_id'      => null,
            'office_staff_id'        => null,
            'is_deleted'             => 0,
            'created_by'             => 1,
            'updated_by'             => 1,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = $this->pdo->prepare(
            'INSERT INTO t_renewal_case
               (contract_id, maturity_date, case_status,
                assigned_staff_id, office_staff_id,
                is_deleted, created_by, updated_by)
             VALUES
               (:contract_id, :maturity_date, :case_status,
                :assigned_staff_id, :office_staff_id,
                :is_deleted, :created_by, :updated_by)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }
}
