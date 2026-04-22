<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class ActivityTypeRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, display_order, is_active, created_at, updated_at
             FROM m_activity_type
             ORDER BY is_active DESC, display_order ASC, id ASC'
        )->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, array<string, mixed>>  有効なものだけ（プルダウン用） */
    public function findActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, display_order
             FROM m_activity_type
             WHERE is_active = 1
             ORDER BY display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, string>  name => name マップ（互換性用。表示名 = DB 格納値）
     */
    public function findActiveMap(): array
    {
        $map = [];
        foreach ($this->findActive() as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $map[$name] = $name;
            }
        }
        return $map;
    }

    /** @return list<string>  有効な name 一覧 */
    public function findActiveNames(): array
    {
        $names = [];
        foreach ($this->findActive() as $row) {
            $names[] = (string) ($row['name'] ?? '');
        }
        return array_values(array_filter($names, static fn (string $n) => $n !== ''));
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, display_order, is_active FROM m_activity_type WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM m_activity_type WHERE name = :name');
            $stmt->bindValue(':name', $name);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM m_activity_type WHERE name = :name AND id <> :id');
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @throws \DomainException
     */
    public function create(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($name)) {
            throw new \DomainException('同じ名前の活動種別が既に登録されています。');
        }

        $maxOrder = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_activity_type'
        )->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_activity_type (name, display_order, is_active)
             VALUES (:name, :display_order, 1)'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':display_order', $maxOrder + 1, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @throws \DomainException
     */
    public function updateName(int $id, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($name, $id)) {
            throw new \DomainException('同じ名前の活動種別が既に登録されています。');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE m_activity_type SET name = :name WHERE id = :id'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_activity_type SET is_active = :active WHERE id = :id'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_activity_type WHERE id = :id');
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
                'SELECT id, display_order FROM m_activity_type
                 WHERE display_order < :order ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_activity_type
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
            'UPDATE m_activity_type SET display_order = :order WHERE id = :id'
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
