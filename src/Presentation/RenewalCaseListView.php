<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;
use App\Presentation\View\StatusBadge;

final class RenewalCaseListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array<string, mixed>> $allUsers
     * @param array<string, mixed> $layoutOptions
     * @param array<int, array<string, mixed>> $allStatuses
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        string $searchUrl,
        string $detailBaseUrl,
        string $csvImportActionUrl,
        string $csvImportCsrfToken,
        ?string $importFlashError,
        ?string $importFlashSuccess,
        ?array $importBatch,
        array $importRows,
        bool $openImportDialog,
        ?string $errorMessage,
        bool $forceFilterOpen,
        array $allUsers,
        array $layoutOptions,
        ?string $pageSuccessMessage = null,
        array $allStatuses = [],
        string $customerDetailBaseUrl = '',
        array $quickFilterCounts = [],
        array $recentImportBatches = []
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $noticeHtml = '';
        if (is_string($pageSuccessMessage) && $pageSuccessMessage !== '') {
            $noticeHtml = '<div class="notice">' . Layout::escape($pageSuccessMessage) . '</div>';
        }

        $customerName      = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo          = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $caseStatus        = (string) ($criteria['case_status'] ?? '');
        $maturityWindow    = (string) ($criteria['maturity_window'] ?? 'all');
        $filterUserId      = (string) ($criteria['assigned_staff_id'] ?? '');
        $filterProductType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $perPage           = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort              = (string) ($listState['sort'] ?? '');
        $direction         = (string) ($listState['direction'] ?? 'asc');
        $filterOpen        = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $errorHtml !== '';
        $pager             = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery         = LP::queryParams($criteria, $listState);

        // 完了扱いの name セット（is_completed_row のグレーアウト・期限色判定に使用）
        $completedNames = [];
        foreach ($allStatuses as $sRow) {
            if ((int) ($sRow['is_completed'] ?? 0) === 1) {
                $completedNames[(string) ($sRow['name'] ?? '')] = true;
            }
        }

        $rowsHtml = '';
        $today    = date('Y-m-d');
        foreach ($rows as $row) {
            $id               = (int) ($row['renewal_case_id'] ?? 0);
            $status           = (string) ($row['case_status'] ?? '');
            $customerId       = isset($row['customer_id']) && $row['customer_id'] !== null ? (int) $row['customer_id'] : null;
            $rowCustomerName  = (string) ($row['customer_name'] ?? '');
            $sjnetName        = (string) ($row['sjnet_customer_name'] ?? '');
            $policyText       = (string) ($row['policy_no'] ?? '');
            $productType      = (string) ($row['product_type'] ?? '');
            $maturityDate     = (string) ($row['maturity_date'] ?? '');
            $earlyDeadline    = (string) ($row['early_renewal_deadline'] ?? '');
            $assignedUserName = (string) ($row['assigned_user_name'] ?? '');
            $detailUrl        = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $isCompletedRow   = isset($completedNames[$status]);
            $statusCtx        = StatusBadge::renderByMaturity($maturityDate, $isCompletedRow, $today);

            // PC: テーブル行（左端ストライプ + data-urgency 属性）
            $rowAttrs = ' data-urgency="' . Layout::escape($statusCtx['urgency']) . '"'
                . ($isCompletedRow ? ' class="is-completed-row"' : '');

            // 対応状況バッジ: DB 値ラベル + 緊急度 badge クラス
            $statusLabel    = $status !== '' ? $status : '未設定';
            $statusBadgeCls = $isCompletedRow ? 'badge-gray' : $statusCtx['badge'];
            $statusBadge    = '<span class="badge ' . $statusBadgeCls . '">' . Layout::escape($statusLabel) . '</span>';

            $displayCustomer = $rowCustomerName !== ''
                ? $rowCustomerName
                : ($sjnetName !== '' ? $sjnetName : '（顧客未設定）');
            $secondaryHtml = $policyText !== ''
                ? '<div class="list-row-secondary">' . Layout::escape($policyText) . '</div>'
                : '';

            $rowsHtml .= '<tr' . $rowAttrs . '>'
                . '<td class="cell-stripe" aria-hidden="true"></td>'
                . '<td class="cell-date" data-label="満期日">' . self::renderMaturityDate($maturityDate, $isCompletedRow, $today) . '</td>'
                . '<td data-label="顧客名">'
                . '<div class="list-row-stack">'
                . '<a class="list-row-primary text-link" href="' . $detailUrl . '" title="' . Layout::escape($displayCustomer) . '">' . Layout::escape($displayCustomer) . '</a>'
                . $secondaryHtml
                . '</div>'
                . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape($productType) . '">' . Layout::escape($productType) . '</td>'
                . '<td class="cell-ellipsis" data-label="担当" title="' . Layout::escape($assignedUserName) . '">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : '<span class="muted">−</span>') . '</td>'
                . '<td data-label="対応状況">' . $statusBadge . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6">該当データはありません。</td></tr>';
        }

        // モバイル list-card は LP::mobileCardList に委譲（closure 内で再計算）
        $cardsHtml = LP::mobileCardList(
            $rows,
            fn (array $row): string => self::buildMobileCardHtml(
                $row, $today, $completedNames, $detailBaseUrl, $listQuery, $customerDetailBaseUrl
            ),
            '満期一覧（モバイル表示）'
        );

        // ステータスフィルター選択肢
        $statuses = ['' => 'すべて'];
        foreach ($allStatuses as $sRow) {
            $name = (string) ($sRow['name'] ?? '');
            if ($name !== '') {
                $statuses[$name] = $name;
            }
        }
        $statusOptions = '';
        foreach ($statuses as $value => $label) {
            $selected      = $caseStatus === $value ? ' selected' : '';
            $statusOptions .= '<option value="' . Layout::escape($value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $userOptions = '<option value="">全担当者</option>';
        foreach ($allUsers as $staffRow) {
            $uid         = (int) ($staffRow['id'] ?? 0);
            $uname       = (string) ($staffRow['staff_name'] ?? '');
            $selected    = $filterUserId === (string) $uid ? ' selected' : '';
            $userOptions .= '<option value="' . Layout::escape((string) $uid) . '"' . $selected . '>' . Layout::escape($uname) . '</option>';
        }

        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);

        $importReturnToUrl = ListViewHelper::buildUrl($searchUrl, array_merge($listQuery, ['import_dialog' => '1']));
        $importErrorHtml   = is_string($importFlashError) && $importFlashError !== ''
            ? '<div class="error">' . Layout::escape($importFlashError) . '</div>'
            : '';
        $importSuccessHtml = is_string($importFlashSuccess) && $importFlashSuccess !== ''
            ? '<div class="notice">' . Layout::escape($importFlashSuccess) . '</div>'
            : '';

        // クイックフィルタタブ（すべて / 対応遅れ / 満期N日前 / 完了以外）
        $quickFilterTabsHtml = LP::quickFilterTabs([
            'currentKey' => (string) ($criteria['quick_filter'] ?? ''),
            'tabs' => [
                ''           => ['label' => 'すべて',       'countKey' => 'all'],
                'overdue'    => ['label' => '対応遅れ',     'countKey' => 'overdue'],
                'w7'         => ['label' => '満期7日前',    'countKey' => 'w7'],
                'w14'        => ['label' => '満期14日前',   'countKey' => 'w14'],
                'w28'        => ['label' => '満期28日前',   'countKey' => 'w28'],
                'w60'        => ['label' => '満期60日前',   'countKey' => 'w60'],
                'incomplete' => ['label' => '完了以外',     'countKey' => 'incomplete'],
            ],
            'counts'    => $quickFilterCounts,
            'paramName' => 'quick_filter',
            'searchUrl' => $searchUrl,
            'criteria'  => $criteria,
            'listState' => $listState,
        ]);

        // 絞り込みボタンのバッジ件数（顧客名以外で適用中の数）
        $advancedFilterCount = 0;
        foreach (['policy_no', 'case_status', 'maturity_window', 'assigned_staff_id', 'product_type'] as $k) {
            $v = (string) ($criteria[$k] ?? '');
            if ($v !== '' && !($k === 'maturity_window' && $v === 'all')) {
                $advancedFilterCount++;
            }
        }

        // クイック検索バー + 絞込ボタン + CSV取込ボタン (LP::searchToolbar に委譲)
        $toolbarBarHtml = LP::searchToolbar([
            'searchUrl'         => $searchUrl,
            'searchParam'       => 'customer_name',
            'searchValue'       => (string) ($criteria['customer_name'] ?? ''),
            'searchPlaceholder' => '顧客名・証券番号で検索',
            'criteria'          => $criteria,
            'listState'         => $listState,
            'filterDialogId'    => 'renewal-filter-dialog',
            'advancedCount'     => $advancedFilterCount,
            'extraButtons'      => [
                ['label' => 'CSV取込', 'icon' => 'upload', 'dialogId' => 'renewal-import-dialog'],
            ],
        ]);

        // 絞り込み詳細ダイアログ（LP::filterDialog に委譲）
        // クイックフィルタタブの選択を preserveCriteria で hidden 保持
        $filterDialogHtml = LP::filterDialog([
            'id'        => 'renewal-filter-dialog',
            'title'     => '絞り込み条件',
            'searchUrl' => $searchUrl,
            'listState' => $listState,
            'preserveCriteria' => [
                'quick_filter' => (string) ($criteria['quick_filter'] ?? ''),
            ],
            'fields' => [
                ['label' => '顧客名', 'html' => '<input type="text" name="customer_name" value="' . $customerName . '" placeholder="部分一致">'],
                ['label' => '証券番号', 'html' => '<input type="text" name="policy_no" value="' . $policyNo . '" placeholder="部分一致">'],
                ['label' => '担当者', 'html' => '<select name="assigned_staff_id">' . $userOptions . '</select>'],
                ['label' => '種目',   'html' => '<input type="text" name="product_type" value="' . $filterProductType . '" placeholder="部分一致">'],
                ['label' => '対応状況', 'html' => '<select name="case_status">' . $statusOptions . '</select>'],
                ['label' => '満期日', 'html' => '<select name="maturity_window">' . self::renderWindowOptions($maturityWindow) . '</select>'],
            ],
            'clearUrl' => $searchUrl,
        ]);

        $filterPanelHtml = $toolbarBarHtml . $quickFilterTabsHtml;

        $tableHtml =
            '<div class="table-wrap list-pc-only">'
            . '<table class="table-fixed list-table list-table-renewal">'
            . '<colgroup>'
            . '<col class="list-col-stripe" style="width:4px;">'
            . '<col class="list-col-date">'
            . '<col class="list-col-customer">'
            . '<col class="list-col-product">'
            . '<col class="list-col-user">'
            . '<col class="list-col-status">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th aria-hidden="true"></th>'
            . '<th>' . LP::sortLink('満期日', 'maturity_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>担当</th>'
            . '<th>' . LP::sortLink('対応状況', 'case_status', $searchUrl, $criteria, $listState) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . $cardsHtml;

        $content =
            '<div class="list-page-frame">'
            // CSV取込ボタンはツールバーに移したのでヘッダーはタイトルのみ
            . LP::pageHeader('満期一覧', '')
            . $noticeHtml
            . $errorHtml
            . $filterPanelHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . '<dialog id="renewal-import-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>SJNET満期データ取込</h2></div>'
            . $importErrorHtml
            . $importSuccessHtml
            . self::renderImportResult($importBatch, $importRows)
            . '<form method="post" action="' . Layout::escape($csvImportActionUrl) . '" enctype="multipart/form-data" data-csv-form>'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csvImportCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($importReturnToUrl) . '">'
            // ドラッグ＆ドロップ領域（クリックでもファイル選択可能）
            . '<label class="csv-dropzone" data-csv-dropzone>'
            . '<input type="file" name="csv_file" accept=".csv,text/csv" required data-csv-input>'
            . '<div class="csv-dropzone-icon" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
            . '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>'
            . '<polyline points="17 8 12 3 7 8"/>'
            . '<line x1="12" y1="3" x2="12" y2="15"/>'
            . '</svg>'
            . '</div>'
            . '<div class="csv-dropzone-text">'
            . '<strong class="csv-dropzone-title">CSVファイルをドラッグ＆ドロップ</strong>'
            . '<span class="csv-dropzone-sub">またはクリックして選択 (Shift-JIS / UTF-8 対応)</span>'
            . '</div>'
            . '<div class="csv-dropzone-selected" data-csv-selected hidden>'
            . '<span class="csv-dropzone-selected-icon" aria-hidden="true">📄</span>'
            . '<span class="csv-dropzone-selected-name" data-csv-selected-name></span>'
            . '</div>'
            . '</label>'
            // カラム仕様（折りたたみ）
            . '<details class="csv-spec-details">'
            . '<summary>カラム仕様を確認する</summary>'
            . '<div class="csv-spec-body">'
            . '<p class="csv-spec-heading">必須カラム</p>'
            . '<p class="csv-spec-text">証券番号 / 顧客名 / 生年月日 / 保険終期 / 種目種類 / 合計保険料 / 代理店ｺｰﾄﾞ</p>'
            . '<p class="csv-spec-note">1つでも欠けていると取込できません。</p>'
            . '<p class="csv-spec-heading">任意カラム（あれば取り込みます）</p>'
            . '<p class="csv-spec-text">郵便番号 / 住所 / ＴＥＬ <span class="muted">→ 新規顧客の自動登録時のみ使用</span></p>'
            . '<p class="csv-spec-text">保険始期 / 払込方法 <span class="muted">→ 契約情報として登録</span></p>'
            . '<p class="csv-spec-text">担当者 <span class="muted">→ 取込履歴に参考記録。担当者設定は代理店コードを使用</span></p>'
            . '<p class="csv-spec-heading">文字コード</p>'
            . '<p class="csv-spec-text muted">Shift-JIS・UTF-8 どちらも対応</p>'
            . '</div>'
            . '</details>'
            // 直近の取込履歴
            . self::renderRecentImportHistory($recentImportBatches, $importReturnToUrl, $searchUrl, $listState, $criteria)
            // アクション: キャンセル / 取込を開始
            . '<div class="dialog-actions" style="margin-top:16px;">'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="renewal-import-dialog">キャンセル</button>'
            . '<button class="btn btn-primary" type="submit" data-csv-submit disabled>取込を開始</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . $filterDialogHtml
            // 共通ダイアログ JS（renewal-import-dialog と renewal-filter-dialog を bind、
            // CSV 取込フローからの戻りでは import-dialog を自動オープン）
            . LP::dialogScript(
                ['renewal-import-dialog', 'renewal-filter-dialog'],
                $openImportDialog ? 'renewal-import-dialog' : null
            );

        return Layout::render('満期一覧', $content, $layoutOptions);
    }

    /**
     * モバイル用 list-card の 1 件分 HTML（<li>...</li>）を生成する。
     * LP::mobileCardList() から closure 経由で呼ばれる。
     *
     * @param array<string, mixed>  $row
     * @param array<string, bool>   $completedNames
     * @param array<string, string> $listQuery
     */
    private static function buildMobileCardHtml(
        array $row,
        string $today,
        array $completedNames,
        string $detailBaseUrl,
        array $listQuery,
        string $customerDetailBaseUrl
    ): string {
        $id               = (int) ($row['renewal_case_id'] ?? 0);
        $status           = (string) ($row['case_status'] ?? '');
        $rowCustomerName  = (string) ($row['customer_name'] ?? '');
        $sjnetName        = (string) ($row['sjnet_customer_name'] ?? '');
        $policyText       = (string) ($row['policy_no'] ?? '');
        $productType      = (string) ($row['product_type'] ?? '');
        $maturityDate     = (string) ($row['maturity_date'] ?? '');
        $assignedUserName = (string) ($row['assigned_user_name'] ?? '');
        $premiumAmount    = (int) ($row['premium_amount'] ?? 0);
        $isCompletedRow   = isset($completedNames[$status]);
        $detailUrl        = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
        $statusCtx        = StatusBadge::renderByMaturity($maturityDate, $isCompletedRow, $today);

        $statusLabel    = $status !== '' ? $status : '未設定';
        $statusBadgeCls = $isCompletedRow ? 'badge-gray' : $statusCtx['badge'];
        $statusBadge    = '<span class="badge ' . $statusBadgeCls . '">' . Layout::escape($statusLabel) . '</span>';

        $cardCustomerName = $rowCustomerName !== '' ? $rowCustomerName : ($sjnetName !== '' ? $sjnetName : '（顧客未設定）');
        $cardClasses      = 'list-card with-stripe' . ($isCompletedRow ? ' completed' : '');

        $maturityCls = '';
        if (!$isCompletedRow && $maturityDate !== '') {
            if ($maturityDate < $today) {
                $maturityCls = ' is-overdue';
            } elseif ($maturityDate <= date('Y-m-d', strtotime($today . ' +7 days'))) {
                $maturityCls = ' is-urgent';
            }
        }

        $iconCalendar = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        $iconYen      = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l6 9 6-9"/><line x1="6" y1="13" x2="18" y2="13"/><line x1="6" y1="17" x2="18" y2="17"/><line x1="12" y1="12" x2="12" y2="21"/></svg>';
        $iconUser     = '<svg class="list-card-meta-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

        $premiumLabel = $premiumAmount > 0 ? '¥' . number_format($premiumAmount) : '−';

        return '<li class="' . $cardClasses . '" data-urgency="' . Layout::escape($statusCtx['urgency']) . '">'
            . '<span class="list-card-stripe ' . Layout::escape($statusCtx['stripe']) . '" aria-hidden="true"></span>'
            . '<a class="list-card-link" href="' . $detailUrl . '">'
            . '<div class="list-card-top">'
            . '<span class="list-card-product">' . ($productType !== '' ? Layout::escape($productType) : '<span class="muted">種目未設定</span>') . '</span>'
            . $statusBadge
            . '</div>'
            . '<div class="list-card-customer">' . Layout::escape($cardCustomerName) . '</div>'
            . '<div class="list-card-policy">証券番号: ' . ($policyText !== '' ? Layout::escape($policyText) : '−') . '</div>'
            . '<div class="list-card-meta">'
            . '<span class="list-card-meta-item">' . $iconCalendar . '<span class="list-card-meta-value' . $maturityCls . '">' . ($maturityDate !== '' ? Layout::escape($maturityDate) : '−') . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconYen . '<span class="list-card-meta-value">' . $premiumLabel . '</span></span>'
            . '<span class="list-card-meta-item">' . $iconUser . '<span class="list-card-meta-value">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : '−') . '</span></span>'
            . '</div>'
            . '</a>'
            . '</li>';
    }

    /**
     * 顧客名セルの表示: customer_id が設定済みならリンク、未設定ならプレーンテキスト
     */
    private static function renderCustomerCell(?int $customerId, string $customerName, string $sjnetCustomerName, string $customerDetailBaseUrl = ''): string
    {
        if ($customerId !== null && $customerName !== '') {
            $escaped = Layout::escape($customerName);
            $href = $customerDetailBaseUrl !== '' ? $customerDetailBaseUrl . '&id=' . $customerId : '?route=customer/detail&id=' . $customerId;
            return '<a class="text-link" href="' . Layout::escape($href) . '" title="' . $escaped . '">' . $escaped . '</a>';
        }

        $displayName = $sjnetCustomerName !== '' ? $sjnetCustomerName : $customerName;
        if ($displayName === '') {
            return '<span class="muted">（顧客未設定）</span>';
        }
        return '<span class="muted" title="' . Layout::escape($displayName) . '">' . Layout::escape($displayName) . '</span>';
    }

    private static function renderMaturityDate(string $maturityDate, bool $isCompleted, string $today): string
    {
        if ($maturityDate === '') {
            return '<span class="muted">−</span>';
        }
        if (!$isCompleted && $maturityDate < $today) {
            return '<span class="cell-date is-overdue">' . Layout::escape($maturityDate) . '</span>';
        }
        return Layout::escape($maturityDate);
    }

/**
     * 直近の取込履歴リストを描画する。
     * 件数が 0 の時は空文字を返す（セクションごと非表示）。
     *
     * @param array<int, array<string, mixed>> $batches
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderRecentImportHistory(
        array $batches,
        string $importReturnToUrl,
        string $searchUrl,
        array $listState,
        array $criteria
    ): string {
        if (empty($batches)) {
            return '';
        }

        $items = '';
        foreach ($batches as $batch) {
            $batchId    = (int) ($batch['id'] ?? 0);
            $fileName   = (string) ($batch['file_name'] ?? '—');
            $finishedAt = (string) ($batch['finished_at'] ?? ($batch['started_at'] ?? ''));
            $status     = (string) ($batch['import_status'] ?? '');
            $total      = (int) ($batch['total_row_count'] ?? 0);
            $insert     = (int) ($batch['insert_count'] ?? 0);
            $update     = (int) ($batch['update_count'] ?? 0);
            $errorCnt   = (int) ($batch['error_count'] ?? 0);

            // ステータスバッジ
            $statusLabel = match ($status) {
                'success' => '完了',
                'partial' => '一部エラー',
                'failed'  => '失敗',
                default   => $status !== '' ? $status : '—',
            };
            $statusCls = match ($status) {
                'success' => 'badge-success',
                'partial' => 'badge-warn',
                'failed'  => 'badge-danger',
                default   => 'badge-gray',
            };

            // 日時整形（Y-m-d H:i）
            $displayTime = $finishedAt;
            if ($finishedAt !== '') {
                $ts = strtotime($finishedAt);
                if ($ts !== false) {
                    $displayTime = date('Y-m-d H:i', $ts);
                }
            }

            // 詳細表示 URL: 現ページに import_batch_id と import_dialog=1 を付与
            $detailParams = array_merge(['import_batch_id' => (string) $batchId, 'import_dialog' => '1'], LP::queryParams($criteria, $listState));
            $detailUrl = ListViewHelper::buildUrl($searchUrl, $detailParams);

            $items .= '<li class="csv-history-item">'
                . '<a class="csv-history-link" href="' . Layout::escape($detailUrl) . '">'
                . '<div class="csv-history-main">'
                . '<span class="csv-history-time">' . Layout::escape($displayTime) . '</span>'
                . '<span class="csv-history-file" title="' . Layout::escape($fileName) . '">' . Layout::escape($fileName) . '</span>'
                . '</div>'
                . '<div class="csv-history-meta">'
                . '<span class="badge ' . $statusCls . '">' . Layout::escape($statusLabel) . '</span>'
                . '<span class="csv-history-counts">'
                . '処理 ' . $total . '件'
                . ($insert > 0 ? ' / 新規 ' . $insert : '')
                . ($update > 0 ? ' / 更新 ' . $update : '')
                . ($errorCnt > 0 ? ' / <span class="csv-history-error">エラー ' . $errorCnt . '</span>' : '')
                . '</span>'
                . '</div>'
                . '</a>'
                . '</li>';
        }

        return '<div class="csv-history">'
            . '<div class="csv-history-head">直近の取込履歴</div>'
            . '<ol class="csv-history-list">' . $items . '</ol>'
            . '</div>';
    }

    private static function renderWindowOptions(string $current): string
    {
        $options = ['all' => '全期間', '30' => '今後30日', '60' => '今後60日', '90' => '今後90日'];
        $html    = '';
        foreach ($options as $value => $label) {
            $selected = $current === (string) $value ? ' selected' : '';
            $html .= '<option value="' . Layout::escape((string) $value) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }
        return $html;
    }

    /**
     * @param array<string, mixed>|null $importBatch
     * @param array<int, array<string, mixed>> $importRows
     */
    private static function renderImportResult(?array $importBatch, array $importRows): string
    {
        if (!is_array($importBatch)) {
            return '';
        }

        $status         = (string) ($importBatch['import_status'] ?? '-');
        $totalRows      = (int) ($importBatch['total_row_count'] ?? 0);
        $insertCount    = (int) ($importBatch['insert_count'] ?? 0);
        $updateCount    = (int) ($importBatch['update_count'] ?? 0);
        $customerInsert = (int) ($importBatch['customer_insert_count'] ?? 0);
        $unlinkedCount  = (int) ($importBatch['unlinked_count'] ?? 0);
        $skipCount      = (int) ($importBatch['duplicate_skip_count'] ?? 0);
        $errorCount     = (int) ($importBatch['error_count'] ?? 0);

        $statusLabel = match ($status) {
            'success' => '完了',
            'partial' => '一部エラーあり',
            'failed'  => '失敗',
            default   => $status,
        };
        $statusClass = match ($status) {
            'success' => 'badge-success',
            'partial' => 'badge-warn',
            default   => 'badge-danger',
        };

        $resolvedCount   = 0;
        $unresolvedCount = 0;
        $inactiveCount   = 0;
        foreach ($importRows as $row) {
            $ms = (string) ($row['staff_mapping_status'] ?? '');
            if ($ms === 'resolved')       { $resolvedCount++; }
            elseif ($ms === 'unresolved') { $unresolvedCount++; }
            elseif ($ms === 'inactive')   { $inactiveCount++; }
        }

        $summary = '<div class="modal-result" style="margin-bottom:14px;">'
            . '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">'
            . '<span style="font-weight:600;">取込結果</span>'
            . '<span class="badge ' . $statusClass . '">' . Layout::escape($statusLabel) . '</span>'
            . '</div>'
            . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:13px;">'
            . '<div><span class="muted">処理行数</span><span style="margin-left:8px;font-weight:500;">' . $totalRows . '行</span></div>'
            . '<div><span class="muted">契約 新規登録</span><span style="margin-left:8px;font-weight:500;">' . $insertCount . '件</span></div>'
            . '<div><span class="muted">契約 更新</span><span style="margin-left:8px;font-weight:500;">' . $updateCount . '件</span></div>'
            . '<div><span class="muted">顧客 自動登録</span><span style="margin-left:8px;font-weight:500;">' . $customerInsert . '件</span></div>'
            . '<div><span class="muted">未紐づけ契約</span><span style="margin-left:8px;' . ($unlinkedCount > 0 ? 'color:var(--text-warning);font-weight:500;' : '') . '">' . $unlinkedCount . '件</span></div>'
            . '<div><span class="muted">スキップ</span><span style="margin-left:8px;">' . $skipCount . '行</span></div>'
            . '<div><span class="muted">エラー</span><span style="margin-left:8px;' . ($errorCount > 0 ? 'color:var(--text-danger);font-weight:500;' : '') . '">' . $errorCount . '行</span></div>'
            . '</div>'
            . '<hr style="margin:10px 0;border:none;border-top:1px solid var(--border-light);">'
            . '<div style="font-size:13px;font-weight:600;margin-bottom:4px;">担当者マッピング</div>'
            . '<div style="display:flex;flex-direction:column;gap:3px;font-size:13px;">'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">解決済み</span><span style="font-weight:500;">' . $resolvedCount . '件</span></div>'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">コード未登録</span><span style="' . ($unresolvedCount > 0 ? 'color:var(--text-warning);font-weight:500;' : '') . '">' . $unresolvedCount . '件</span></div>'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">無効コード</span><span>' . $inactiveCount . '件</span></div>'
            . '</div>'
            . '</div>';

        $warnings = '';
        if ($unresolvedCount > 0) {
            $warnings .= '<div class="error" style="margin-bottom:10px;font-size:13px;">'
                . Layout::escape($unresolvedCount . '件の代理店コードがマッピング未登録です。テナント設定 > SJNETコード設定 で登録してください。')
                . '</div>';
        }

        // 未リンク行（顧客名複数一致）の一覧
        $unlinkedRowsHtml = '';
        $errorRowsHtml    = '';
        foreach ($importRows as $row) {
            $rowStatus = (string) ($row['row_status'] ?? '');
            $rowNo     = Layout::escape((string) ($row['row_no'] ?? ''));
            $policyNo  = Layout::escape((string) ($row['policy_no'] ?? '−'));
            $custName  = Layout::escape((string) ($row['customer_name'] ?? ''));

            if ($rowStatus === 'unlinked') {
                $unlinkedRowsHtml .= '<tr>'
                    . '<td>' . $rowNo . '</td>'
                    . '<td>' . $policyNo . '</td>'
                    . '<td>' . $custName . '</td>'
                    . '<td><span class="muted">満期詳細画面から顧客を手動で紐づけてください</span></td>'
                    . '</tr>';
            } elseif ($rowStatus === 'error') {
                $errMsg = (string) ($row['error_message'] ?? '');
                $errorRowsHtml .= '<tr>'
                    . '<td>' . $rowNo . '</td>'
                    . '<td>' . $policyNo . '</td>'
                    . '<td>' . $custName . '</td>'
                    . '<td><span class="truncate">' . Layout::escape($errMsg) . '</span></td>'
                    . '</tr>';
            }
        }

        if ($unlinkedCount > 0) {
            $warnings .= '<div class="warning" style="margin-bottom:10px;font-size:13px;">'
                . Layout::escape($unlinkedCount . '件の契約が顧客と未紐づけの状態です。満期詳細画面から顧客を手動で設定してください。')
                . '</div>';
        }

        $detailsHtml = '';

        if ($unlinkedRowsHtml !== '') {
            $detailsHtml .= '<details class="details-panel modal-help" open>'
                . '<summary>未紐づけ行を確認（' . $unlinkedCount . '件）</summary>'
                . '<div class="table-wrap">'
                . '<table class="table-fixed table-card">'
                . '<thead><tr><th>行</th><th>証券番号</th><th>顧客名</th><th>対応方法</th></tr></thead>'
                . '<tbody>' . $unlinkedRowsHtml . '</tbody>'
                . '</table>'
                . '</div>'
                . '</details>';
        }

        if ($errorRowsHtml !== '') {
            $detailsHtml .= '<details class="details-panel modal-help" open>'
                . '<summary>エラー行を確認（' . $errorCount . '件）</summary>'
                . '<div class="table-wrap">'
                . '<table class="table-fixed table-card">'
                . '<thead><tr><th>行</th><th>証券番号</th><th>顧客名</th><th>エラー内容</th></tr></thead>'
                . '<tbody>' . $errorRowsHtml . '</tbody>'
                . '</table>'
                . '</div>'
                . '</details>';
        }

        return $summary . $warnings . $detailsHtml;
    }
}
