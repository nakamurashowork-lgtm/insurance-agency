<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class ProductCategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 全件取得（id ASC）
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, csv_value, name, is_active, created_at, updated_at
             FROM m_product_category
             ORDER BY is_active DESC, id ASC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * プルダウン用：有効な name を重複なしで取得（name ASC）
     *
     * @return array<int, array{name: string}>
     */
    public function findActiveNames(): array
    {
        $rows = $this->pdo->query(
            'SELECT DISTINCT name
             FROM m_product_category
             WHERE is_active = 1
             ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function create(string $csvValue, string $name, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_product_category (csv_value, name)
             VALUES (:csv_value, :name)'
        );
        $stmt->bindValue(':csv_value', $csvValue);
        $stmt->bindValue(':name', $name);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $csvValue, string $name): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_product_category
             SET csv_value = :csv_value, name = :name
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':csv_value', $csvValue);
        $stmt->bindValue(':name', $name);
        $stmt->execute();
    }

    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_product_category SET is_active = :active WHERE id = :id'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_product_category WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
