<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

/**
 * 見込案件ステータスマスタ（m_sales_case_status）
 *
 * 表示名(name) が DB 格納値を兼ねる。設定画面では name・is_completed・
 * is_protected・is_active・display_order を編集する。
 * is_protected=1 のレコードは削除・無効化不可、is_completed=1 のレコードは
 * 完了扱いとしてダッシュボード集計などから除外される。
 */
final class SalesCaseStatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 新規追加。重複する name は DB の UNIQUE 制約で拒否される。
     *
     * @throws \DomainException 同一 name が既に存在する場合
     */
    public function create(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($name)) {
            throw new \DomainException('同じ名前のステータスが既に登録されています。');
        }

        $stmt = $this->pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM m_sales_case_status');
        $maxOrder = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_sales_case_status (name, display_order, is_active, is_completed, is_protected)
             VALUES (:name, :display_order, 1, 0, 0)'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':display_order', $maxOrder + 1, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 指定 name が既に存在するか（自身除外可）。
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM m_sales_case_status WHERE name = :name'
            );
            $stmt->bindValue(':name', $name);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM m_sales_case_status WHERE name = :name AND id <> :id'
            );
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * 保護されていないレコードを物理削除する。
     *
     * @throws \DomainException
     */
    public function delete(int $id): void
    {
        $row = $this->findById($id);
        if ($row === null) {
            throw new \DomainException('対象が見つかりません。');
        }
        if ((int) ($row['is_protected'] ?? 0) === 1) {
            throw new \DomainException('保護されたステータスは削除できません。');
        }
        $stmt = $this->pdo->prepare('DELETE FROM m_sales_case_status WHERE id = :id AND is_protected = 0');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * 全件取得（管理画面・検索フォーム用）
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, display_order, is_active, is_completed, is_protected, created_at, updated_at
             FROM m_sales_case_status
             ORDER BY is_active DESC, display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * 有効なものだけ（編集フォームのプルダウン用）
     *
     * @return array<int, array<string, mixed>>
     */
    public function findActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, display_order, is_completed
             FROM m_sales_case_status
             WHERE is_active = 1
             ORDER BY display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * バリデーション用：有効な name 一覧
     *
     * @return list<string>
     */
    public function findActiveNames(): array
    {
        $rows = $this->findActive();
        return array_values(array_map(
            static fn(array $r) => (string) ($r['name'] ?? ''),
            $rows
        ));
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, display_order, is_active, is_completed, is_protected
             FROM m_sales_case_status WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * name を変更する。重複は UNIQUE 制約で拒否される。
     *
     * @throws \DomainException
     */
    public function updateName(int $id, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('名前を入力してください。');
        }
        if ($this->existsByName($name, $id)) {
            throw new \DomainException('同じ名前のステータスが既に登録されています。');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE m_sales_case_status SET name = :name WHERE id = :id'
        );
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_sales_case_status SET is_active = :active WHERE id = :id AND is_protected = 0'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function setCompleted(int $id, int $completed): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_sales_case_status SET is_completed = :completed WHERE id = :id'
        );
        $stmt->bindValue(':completed', $completed, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * 表示順を隣接行と入れ替える
     * @return bool 交換が実行された場合 true
     */
    public function swapDisplayOrder(int $id, string $direction): bool
    {
        $self = $this->findById($id);
        if ($self === null) {
            return false;
        }
        $selfOrder = (int) ($self['display_order'] ?? 0);

        if ($direction === 'up') {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_sales_case_status
                 WHERE display_order < :order ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_sales_case_status
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
            'UPDATE m_sales_case_status SET display_order = :order WHERE id = :id'
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
