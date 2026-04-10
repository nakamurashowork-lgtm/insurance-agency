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

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, label, display_order, is_active FROM m_procedure_method WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(string $label): int
    {
        $maxOrder = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_procedure_method'
        )->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_procedure_method (label, display_order, is_active) VALUES (:label, :display_order, 1)'
        );
        $stmt->bindValue(':label', $label);
        $stmt->bindValue(':display_order', $maxOrder + 1, PDO::PARAM_INT);
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

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_procedure_method WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function swapDisplayOrder(int $id, string $direction): bool
    {
        $self = $this->findById($id);
        if ($self === null) {
            return false;
        }
        $selfOrder = (int) ($self['display_order'] ?? 0);

        if ($direction === 'up') {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_procedure_method
                 WHERE display_order < :order ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_procedure_method
                 WHERE display_order > :order ORDER BY display_order ASC LIMIT 1'
            );
        }
        $stmt->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $stmt->execute();
        $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($neighbor)) {
            return false;
        }

        $neighborId    = (int) ($neighbor['id'] ?? 0);
        $neighborOrder = (int) ($neighbor['display_order'] ?? 0);

        $upd = $this->pdo->prepare(
            'UPDATE m_procedure_method SET display_order = :order WHERE id = :id'
        );
        $upd->bindValue(':order', $neighborOrder, PDO::PARAM_INT);
        $upd->bindValue(':id', $id, PDO::PARAM_INT);
        $upd->execute();

        $upd->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $upd->bindValue(':id', $neighborId, PDO::PARAM_INT);
        $upd->execute();

        return true;
    }
}
