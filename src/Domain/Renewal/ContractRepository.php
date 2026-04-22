<?php
declare(strict_types=1);

namespace App\Domain\Renewal;

use PDO;

final class ContractRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 顧客紐づけ操作（設定・変更）
     *
     * t_contract.customer_id を更新し、監査ログを記録する。
     * $renewalCaseId が指定されていれば renewal_case エンティティの監査にも同内容を記録する。
     *
     * @param int $contractId     対象契約ID
     * @param int $newCustomerId  紐づける顧客ID（正の整数。解除はサポートしない）
     * @param int $userId         操作者ID (common.users.id)
     * @param int $renewalCaseId  満期案件ID（0 指定で renewal_case 側の audit を省略）
     */
    public function linkCustomer(int $contractId, int $newCustomerId, int $userId, int $renewalCaseId = 0): void
    {
        // 変更前の customer_id を取得
        $stmt = $this->pdo->prepare(
            'SELECT customer_id FROM t_contract WHERE id = :id AND is_deleted = 0 LIMIT 1'
        );
        $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new \RuntimeException('契約が見つかりません: contract_id=' . $contractId);
        }

        $beforeCustomerId = $row['customer_id'] !== null ? (int) $row['customer_id'] : null;

        // 変化がなければ何もしない
        if ($beforeCustomerId === $newCustomerId) {
            return;
        }

        // 顧客名を解決（監査テキストに保存）
        $beforeName = $beforeCustomerId !== null ? $this->lookupCustomerName($beforeCustomerId) : null;
        $afterName  = $this->lookupCustomerName($newCustomerId);

        // t_contract.customer_id を更新
        $stmt = $this->pdo->prepare(
            'UPDATE t_contract
             SET customer_id = :customer_id,
                 updated_by  = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );
        $stmt->bindValue(':customer_id', $newCustomerId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
        $stmt->execute();

        // contract 監査を記録（従来通り）
        $note = '顧客紐づけ変更 (customer_id: ' . ($beforeCustomerId ?? 'NULL') . ' → ' . $newCustomerId . ')';
        $this->insertLinkCustomerAudit('contract', $contractId, $userId, $note, $beforeName, $afterName);

        // renewal_case 監査にも記録（満期詳細の変更履歴に表示されるようにするため）
        if ($renewalCaseId > 0) {
            $this->insertLinkCustomerAudit('renewal_case', $renewalCaseId, $userId, '顧客を変更', $beforeName, $afterName);
        }
    }

    private function lookupCustomerName(int $customerId): ?string
    {
        if ($customerId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT customer_name FROM m_customer WHERE id = :id AND is_deleted = 0 LIMIT 1');
        $stmt->bindValue(':id', $customerId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $name = trim((string) ($row['customer_name'] ?? ''));
        return $name === '' ? null : $name;
    }

    private function insertLinkCustomerAudit(
        string $entityType,
        int $entityId,
        int $userId,
        string $note,
        ?string $beforeName,
        ?string $afterName
    ): void {
        // changed_at は DDL の DEFAULT CURRENT_TIMESTAMP（MySQL サーバ時刻＝JST）に任せる。
        // PHP 側で date() を渡すと PHP の timezone 設定（UTC 等）に依存してズレるため明示しない。
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event
               (entity_type, entity_id, action_type, change_source, changed_by, note)
             VALUES
               (:entity_type, :entity_id, \'UPDATE\', \'SCREEN\', :changed_by, :note)'
        );
        $stmt->bindValue(':entity_type', $entityType);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':changed_by', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note);
        $stmt->execute();

        $auditEventId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event_detail
               (audit_event_id, field_key, field_label, value_type, before_value_text, after_value_text)
             VALUES
               (:event_id, \'customer_id\', \'顧客\', \'STRING\', :before, :after)'
        );
        $stmt->bindValue(':event_id', $auditEventId, PDO::PARAM_INT);
        $stmt->bindValue(':before', $beforeName);
        $stmt->bindValue(':after', $afterName);
        $stmt->execute();
    }

    /**
     * 契約に紐づく contract_id を返す（テナント境界チェック用）
     */
    public function findById(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, policy_no FROM t_contract WHERE id = :id AND is_deleted = 0 LIMIT 1'
        );
        $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
