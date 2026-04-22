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
        $sql = 'SELECT id, name, display_order, is_active, created_at, updated_at
                FROM m_activity_purpose_type';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, display_order ASC, id ASC';

        $rows = $this->pdo->query($sql)->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<string>
     */
    public function findActiveNames(): array
    {
        $names = [];
        foreach ($this->findAll(true) as $row) {
            $names[] = (string) ($row['name'] ?? '');
        }
        return array_values(array_filter($names, static fn (string $n) => $n !== ''));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, display_order, is_active, created_at, updated_at
             FROM m_activity_purpose_type
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM m_activity_purpose_type WHERE name = :name');
            $stmt->bindValue(':name', $name);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM m_activity_purpose_type WHERE name = :name AND id <> :id');
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /** @throws \DomainException */
    public function create(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($name)) {
            throw new \DomainException('同じ名前の用件区分が既に登録されています。');
        }

        $maxOrder = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_activity_purpose_type'
        )->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_activity_purpose_type (name, display_order, is_active)
             VALUES (:name, :display_order, 1)'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':display_order', $maxOrder + 1, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /** @throws \DomainException */
    public function updateName(int $id, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($name, $id)) {
            throw new \DomainException('同じ名前の用件区分が既に登録されています。');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE m_activity_purpose_type
             SET name = :name
             WHERE id = :id'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_activity_purpose_type SET is_active = :active WHERE id = :id'
        );
        $stmt->bindValue(':active', $active, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_activity_purpose_type WHERE id = :id');
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
                'SELECT id, display_order FROM m_activity_purpose_type
                 WHERE display_order < :order ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_activity_purpose_type
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
            'UPDATE m_activity_purpose_type SET display_order = :order WHERE id = :id'
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
