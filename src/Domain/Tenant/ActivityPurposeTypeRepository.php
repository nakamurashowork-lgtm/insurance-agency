<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class ActivityPurposeTypeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(bool $activeOnly = false): array
    {
        $sql = 'SELECT code, label, display_order, is_active, created_at, updated_at
                FROM m_activity_purpose_type';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, display_order ASC, code ASC';

        $rows = $this->pdo->query($sql)->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, label, display_order, is_active, created_at, updated_at
             FROM m_activity_purpose_type
             WHERE code = :code'
        );
        $stmt->bindValue(':code', $code);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * m_activity_purpose_type には created_by / updated_by 列が存在しない。
     * コードは呼び出し元で自動生成する。
     */
    public function create(string $code, string $label): void
    {
        $maxOrder = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_activity_purpose_type'
        )->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_activity_purpose_type (code, label, display_order, is_active)
             VALUES (:code, :label, :display_order, 1)'
        );
        $stmt->bindValue(':code', $code);
        $stmt->bindValue(':label', $label);
        $stmt->bindValue(':display_order', $maxOrder + 1, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function update(string $code, string $label): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_activity_purpose_type
             SET label = :label
             WHERE code = :code'
        );
        $stmt->bindValue(':label', $label);
        $stmt->bindValue(':code', $code);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function setActive(string $code, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_activity_purpose_type SET is_active = :active WHERE code = :code'
        );
        $stmt->bindValue(':active', $active, \PDO::PARAM_INT);
        $stmt->bindValue(':code', $code);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(string $code): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_activity_purpose_type WHERE code = :code');
        $stmt->bindValue(':code', $code);
        $stmt->execute();
    }

    public function swapDisplayOrder(string $code, string $direction): bool
    {
        $self = $this->findByCode($code);
        if ($self === null) {
            return false;
        }
        $selfOrder = (int) ($self['display_order'] ?? 0);

        if ($direction === 'up') {
            $stmt = $this->pdo->prepare(
                'SELECT code, display_order FROM m_activity_purpose_type
                 WHERE display_order < :order ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT code, display_order FROM m_activity_purpose_type
                 WHERE display_order > :order ORDER BY display_order ASC LIMIT 1'
            );
        }
        $stmt->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $stmt->execute();
        $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($neighbor)) {
            return false;
        }

        $neighborCode  = (string) ($neighbor['code'] ?? '');
        $neighborOrder = (int) ($neighbor['display_order'] ?? 0);

        $upd = $this->pdo->prepare(
            'UPDATE m_activity_purpose_type SET display_order = :order WHERE code = :code'
        );
        $upd->bindValue(':order', $neighborOrder, PDO::PARAM_INT);
        $upd->bindValue(':code', $code);
        $upd->execute();

        $upd->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $upd->bindValue(':code', $neighborCode);
        $upd->execute();

        return true;
    }
}
