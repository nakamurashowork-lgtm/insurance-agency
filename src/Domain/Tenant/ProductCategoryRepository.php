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
            'SELECT id, csv_value, display_name, created_at, updated_at
             FROM m_product_category
             ORDER BY id ASC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function create(string $csvValue, string $displayName, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_product_category (csv_value, display_name)
             VALUES (:csv_value, :display_name)'
        );
        $stmt->bindValue(':csv_value', $csvValue);
        $stmt->bindValue(':display_name', $displayName);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $csvValue, string $displayName): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_product_category
             SET csv_value = :csv_value, display_name = :display_name
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':csv_value', $csvValue);
        $stmt->bindValue(':display_name', $displayName);
        $stmt->execute();
    }

    public function delete(int $id): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_product_category WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
