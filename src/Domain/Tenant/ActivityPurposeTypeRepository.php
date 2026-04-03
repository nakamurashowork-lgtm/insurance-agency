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
        $sql .= ' ORDER BY display_order ASC, code ASC';

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
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_activity_purpose_type (code, label, display_order, is_active)
             VALUES (:code, :label, 0, 1)'
        );
        $stmt->bindValue(':code', $code);
        $stmt->bindValue(':label', $label);
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

    public function delete(string $code): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM m_activity_purpose_type WHERE code = :code'
        );
        $stmt->bindValue(':code', $code);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
