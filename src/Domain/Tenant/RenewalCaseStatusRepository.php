<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class RenewalCaseStatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 全件取得（display_order ASC）
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, code, display_name, display_order, is_active, is_fixed, created_at, updated_at
             FROM m_renewal_case_status
             ORDER BY display_order ASC, id ASC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function create(string $displayName, int $actorUserId): int
    {
        // display_order は現在の最大値+1
        $maxOrder = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_renewal_case_status'
        )->fetchColumn();

        // code は display_name から自動生成せず、一意な値として id ベースで後付け更新するため仮値を使う
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_renewal_case_status
                (code, display_name, display_order, is_active, is_fixed, created_by, updated_by)
             VALUES (:code, :display_name, :display_order, 1, 0, :created_by, :updated_by)'
        );
        // code は挿入後に id を使って確定する
        $tmpCode = 'tmp_' . uniqid('', true);
        $stmt->bindValue(':code', $tmpCode);
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':display_order', $maxOrder + 1, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();

        $id = (int) $this->pdo->lastInsertId();

        // code を id ベースの値で確定
        $this->pdo->prepare('UPDATE m_renewal_case_status SET code = :code WHERE id = :id')
            ->execute([':code' => 'status_' . $id, ':id' => $id]);

        return $id;
    }

    public function updateDisplayName(int $id, string $displayName, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_renewal_case_status
             SET display_name = :display_name,
                 updated_by   = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function updateDisplayOrder(int $id, int $displayOrder, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_renewal_case_status
             SET display_order = :display_order,
                 updated_by    = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':display_order', $displayOrder, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM m_renewal_case_status WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
