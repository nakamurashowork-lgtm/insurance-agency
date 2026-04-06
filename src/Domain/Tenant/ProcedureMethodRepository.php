<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class ProcedureMethodRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, label, display_order, is_active, created_at, updated_at
             FROM m_procedure_method
             ORDER BY is_active DESC, display_order ASC, id ASC'
        )->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, array<string, mixed>>  有効なものだけ（プルダウン用） */
    public function findActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, label, display_order
             FROM m_procedure_method
             WHERE is_active = 1
             ORDER BY display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @return string[]  有効な label 一覧（バリデーション用） */
    public function findActiveLabels(): array
    {
        $rows = $this->findActive();
        return array_map(static fn(array $r) => (string) $r['label'], $rows);
    }

    public function create(string $label, int $displayOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_procedure_method (label, display_order, is_active) VALUES (:label, :display_order, 1)'
        );
        $stmt->bindValue(':label', $label);
        $stmt->bindValue(':display_order', $displayOrder, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $label): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_procedure_method SET label = :label WHERE id = :id'
        );
        $stmt->bindValue(':label', $label);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_procedure_method SET is_active = :active WHERE id = :id'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
