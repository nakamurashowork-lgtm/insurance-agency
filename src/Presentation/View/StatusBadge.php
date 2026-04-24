<?php
declare(strict_types=1);

namespace App\Presentation\View;

/**
 * 一覧画面のステータス・優先度・ランクから
 * urgency（緊急度識別子）/ stripe（左端ストライプ CSS クラス）/ badge（バッジ CSS クラス）/ label（表示テキスト）
 * を統一的に解決する共通ヘルパー。
 *
 * 各 *ListView が独自に持っていた色判定ロジックを集約。
 *
 * 用法:
 * ```php
 * $ctx = StatusBadge::renderByMaturity($row['maturity_date'], $isCompleted, date('Y-m-d'));
 * echo '<tr data-urgency="' . Layout::escape($ctx['urgency']) . '">';
 * echo '<span class="list-card-stripe ' . $ctx['stripe'] . '"></span>';
 * echo '<span class="badge ' . $ctx['badge'] . '">' . Layout::escape($ctx['label']) . '</span>';
 * ```
 *
 * 返り値の CSS クラス名は以下と整合:
 * - stripe: `stripe-danger/warning/info/success/gray`（Layout.php の `.list-card-stripe` 配下）
 * - badge : `badge-danger/warn/info/success/gray`（Layout.php の `.badge` 配下）
 */
final class StatusBadge
{
    /**
     * 満期日と完了フラグから緊急度を算出する。満期一覧・契約一覧用。
     *
     * - 完了          → completed / gray
     * - 満期日未設定  → unknown   / gray
     * - 今日より前    → overdue   / danger
     * - 今日＋7日以内 → urgent    / warning
     * - 今日＋30日以内→ soon      / info
     * - それ以外      → later     / gray
     *
     * @return array{urgency:string, stripe:string, badge:string, label:string}
     */
    public static function renderByMaturity(string $maturityDate, bool $isCompleted, string $today): array
    {
        if ($isCompleted) {
            return ['urgency' => 'completed', 'stripe' => 'stripe-gray', 'badge' => 'badge-gray', 'label' => '完了'];
        }
        if ($maturityDate === '') {
            return ['urgency' => 'unknown', 'stripe' => 'stripe-gray', 'badge' => 'badge-gray', 'label' => '満期日未設定'];
        }
        if ($maturityDate < $today) {
            return ['urgency' => 'overdue', 'stripe' => 'stripe-danger', 'badge' => 'badge-danger', 'label' => '対応遅れ'];
        }
        $within7 = date('Y-m-d', strtotime($today . ' +7 days'));
        if ($maturityDate <= $within7) {
            return ['urgency' => 'urgent', 'stripe' => 'stripe-warning', 'badge' => 'badge-warn', 'label' => '7日以内'];
        }
        $within30 = date('Y-m-d', strtotime($today . ' +30 days'));
        if ($maturityDate <= $within30) {
            return ['urgency' => 'soon', 'stripe' => 'stripe-info', 'badge' => 'badge-info', 'label' => '30日以内'];
        }
        return ['urgency' => 'later', 'stripe' => 'stripe-gray', 'badge' => 'badge-gray', 'label' => '余裕あり'];
    }

    /**
     * 事故案件の優先度から色を解決する。
     *
     * - high   → danger（赤）
     * - medium / normal → warning（黄）
     * - low    → info（青）
     * - その他 → gray
     *
     * @return array{urgency:string, stripe:string, badge:string, label:string}
     */
    public static function renderByPriority(string $priority): array
    {
        $p = strtolower(trim($priority));
        return match ($p) {
            'high', 'h', '高', '高優先'
                => ['urgency' => 'high',   'stripe' => 'stripe-danger',  'badge' => 'badge-pri badge-pri-high', 'label' => '高'],
            'medium', 'mid', 'normal', 'm', '中', '中優先'
                => ['urgency' => 'medium', 'stripe' => 'stripe-warning', 'badge' => 'badge-pri badge-pri-mid',  'label' => '中'],
            'low', 'l', '低', '低優先'
                => ['urgency' => 'low',    'stripe' => 'stripe-gray',    'badge' => 'badge-pri badge-pri-low',  'label' => '低'],
            default
                => ['urgency' => 'unknown','stripe' => 'stripe-gray',    'badge' => 'badge-pri badge-pri-low',  'label' => $priority !== '' ? $priority : '未設定'],
        };
    }

    /**
     * 見込案件のランクから色を解決する。
     *
     * - A → danger（最優先）
     * - B → warning
     * - C → info
     * - その他 → gray
     *
     * @return array{urgency:string, stripe:string, badge:string, label:string}
     */
    public static function renderByRank(string $rank): array
    {
        $r = strtoupper(trim($rank));
        return match ($r) {
            'A'     => ['urgency' => 'rank-a', 'stripe' => 'stripe-danger',  'badge' => 'badge-pri badge-pri-high', 'label' => 'A'],
            'B'     => ['urgency' => 'rank-b', 'stripe' => 'stripe-warning', 'badge' => 'badge-pri badge-pri-mid',  'label' => 'B'],
            'C'     => ['urgency' => 'rank-c', 'stripe' => 'stripe-gray',    'badge' => 'badge-pri badge-pri-low',  'label' => 'C'],
            default => ['urgency' => 'unknown','stripe' => 'stripe-gray',    'badge' => 'badge-pri badge-pri-low',  'label' => $rank !== '' ? $rank : '未設定'],
        };
    }
}
