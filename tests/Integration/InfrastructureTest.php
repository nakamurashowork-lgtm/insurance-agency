<?php
declare(strict_types=1);

namespace Tests\Integration;

/**
 * Phase 1 動作確認テスト
 *
 * テストインフラ（DB 接続・TRUNCATE・ヘルパ）が正常に動くことだけを確認する。
 * ビジネスロジックは Phase 2 以降でテストする。
 */
final class InfrastructureTest extends DatabaseTestCase
{
    /** DB に接続でき、TRUNCATE 後の m_customer が空であることを確認する */
    public function testInfrastructure_TruncateWorks(): void
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM m_customer');
        $this->assertNotFalse($stmt);
        $count = $stmt->fetchColumn();
        $this->assertSame('0', (string) $count, 'setUp で TRUNCATE されているため 0 件のはず');
    }

    /** createCustomer ヘルパで INSERT → SELECT できることを確認する */
    public function testInfrastructure_CreateCustomerHelper(): void
    {
        $id = $this->createCustomer(['customer_name' => '動作確認太郎']);

        $this->assertGreaterThan(0, $id);

        $stmt = $this->pdo->prepare('SELECT customer_name FROM m_customer WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('動作確認太郎', $row['customer_name']);
    }

    /** createStaff ヘルパで INSERT → sjnet_code で検索できることを確認する */
    public function testInfrastructure_CreateStaffHelper(): void
    {
        $id = $this->createStaff(['staff_name' => 'テスト担当', 'sjnet_code' => 'A999']);

        $this->assertGreaterThan(0, $id);

        $stmt = $this->pdo->prepare('SELECT staff_name FROM m_staff WHERE sjnet_code = :code');
        $stmt->execute(['code' => 'A999']);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('テスト担当', $row['staff_name']);
    }

    /** 2回目の setUp で TRUNCATE が効き、前テストのデータが消えていることを確認する */
    public function testInfrastructure_TruncateClearsPreviousData(): void
    {
        // このテストが実行される時点で setUp が走っており TRUNCATE 済みのはず
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM m_staff');
        $this->assertNotFalse($stmt);
        $this->assertSame('0', (string) $stmt->fetchColumn());
    }

    /** createContract / createRenewalCase ヘルパの動作確認 */
    public function testInfrastructure_CreateContractAndRenewalCaseHelpers(): void
    {
        $customerId  = $this->createCustomer(['customer_name' => '契約テスト顧客']);
        $contractId  = $this->createContract([
            'customer_id'     => $customerId,
            'policy_no'       => 'INFRA-001',
            'policy_end_date' => '2026-04-30',
        ]);
        $renewalId = $this->createRenewalCase([
            'contract_id'  => $contractId,
            'maturity_date' => '2026-04-30',
        ]);

        $this->assertGreaterThan(0, $contractId);
        $this->assertGreaterThan(0, $renewalId);

        $stmt = $this->pdo->prepare(
            'SELECT rc.case_status FROM t_renewal_case rc WHERE rc.id = :id'
        );
        $stmt->execute(['id' => $renewalId]);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('not_started', $row['case_status']);
    }
}
