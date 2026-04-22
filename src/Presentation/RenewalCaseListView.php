<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

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
        string $customerDetailBaseUrl = ''
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

        $rowsHtml     = '';
        $today        = date('Y-m-d');
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
            $rowClass         = $isCompletedRow ? ' class="is-completed-row"' : '';

            $rowsHtml .= '<tr' . $rowClass . '>'
                . '<td data-label="証券番号"><a class="text-link list-policy-text" href="' . $detailUrl . '" title="' . Layout::escape($policyText) . '">' . Layout::escape($policyText) . '</a></td>'
                . '<td data-label="満期日" style="white-space:nowrap;">' . self::renderMaturityDate($maturityDate, $isCompletedRow, $today) . '</td>'
                . '<td class="cell-ellipsis" data-label="顧客名">' . self::renderCustomerCell($customerId, $rowCustomerName, $sjnetName, $customerDetailBaseUrl) . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape($productType) . '">' . Layout::escape($productType) . '</td>'
                . '<td data-label="早期更改締切" style="white-space:nowrap;">' . self::renderEarlyDeadline($earlyDeadline, $isCompletedRow, $today) . '</td>'
                . '<td class="cell-ellipsis" data-label="営業担当" title="' . Layout::escape($assignedUserName) . '">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : '<span class="muted">−</span>') . '</td>'
                . '<td data-label="対応状況">' . self::renderStatusBadge($status) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当データはありません。</td></tr>';
        }

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

        $filterPanelHtml =
            '<div class="search-panel-compact">'
            . '<div class="toggle-header">'
            . '<span class="toggle-header-title">検索条件を閉じる</span>'
            . '<span class="toggle-header-arrow">▲</span>'
            . '</div>'
            . '<div class="search-panel-body">'
            . '<form method="get" action="' . Layout::escape(LP::formAction($searchUrl)) . '">'
            . LP::routeInput($searchUrl)
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">顧客名</span><input type="text" name="customer_name" class="compact-input w-md" value="' . $customerName . '"></div>'
            . '<div class="search-field"><span class="search-label">証券番号</span><input type="text" name="policy_no" class="compact-input w-md" value="' . $policyNo . '"></div>'
            . '<div class="search-field"><span class="search-label">担当者</span><select name="assigned_staff_id" class="compact-input w-md">' . $userOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">種目</span><input type="text" name="product_type" class="compact-input w-md" value="' . $filterProductType . '"></div>'
            . '</div>'
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">対応状況</span><select name="case_status" class="compact-input w-sm">' . $statusOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">満期日</span><select name="maturity_window" class="compact-input w-md">' . self::renderWindowOptions($maturityWindow) . '</select></div>'
            . '<div class="search-actions">'
            . '<button class="btn btn-small" type="submit">検索</button>'
            . '<a class="btn btn-small btn-secondary" href="' . Layout::escape($searchUrl) . '">クリア</a>'
            . '</div>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-renewal">'
            . '<colgroup>'
            . '<col class="list-col-policy">'
            . '<col class="list-col-date">'
            . '<col class="list-col-customer">'
            . '<col class="list-col-product">'
            . '<col class="list-col-early">'
            . '<col class="list-col-user">'
            . '<col class="list-col-status">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('証券番号', 'policy_no', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('満期日', 'maturity_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('早期更改締切', 'early_renewal_deadline', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>営業担当</th>'
            . '<th>' . LP::sortLink('対応状況', 'case_status', $searchUrl, $criteria, $listState) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('満期一覧', '<button class="btn btn-primary" type="button" data-open-dialog="renewal-import-dialog">+ CSV取込</button>')
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
            . '<form method="post" action="' . Layout::escape($csvImportActionUrl) . '" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csvImportCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($importReturnToUrl) . '">'
            . '<p class="muted" style="margin-bottom:12px;font-size:13px;">SJ-NETからダウンロードしたCSVを選択してください。</p>'
            . '<div style="font-size:13px;line-height:1.7;margin-bottom:16px;">'
            . '<p style="margin:0 0 4px;font-weight:600;">■ 必須カラム</p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">証券番号 / 顧客名 / 生年月日 / 保険終期 / 種目種類 / 合計保険料 / 代理店ｺｰﾄﾞ</p>'
            . '<p style="margin:0 0 10px;padding-left:1em;" class="muted">1つでも欠けていると取込できません。</p>'
            . '<p style="margin:0 0 4px;font-weight:600;">■ 任意カラム（あれば取り込みます）</p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">郵便番号 / 住所 / ＴＥＬ　<span class="muted">→ 新規顧客の自動登録時のみ使用。既存顧客は不変。</span></p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">保険始期 / 払込方法　<span class="muted">→ 契約情報として登録。</span></p>'
            . '<p style="margin:0 0 10px;padding-left:1em;">担当者　<span class="muted">→ 取込履歴に参考記録。担当者設定は代理店コードを使用。</span></p>'
            . '<p style="margin:0 0 4px;font-weight:600;">■ 文字コード</p>'
            . '<p style="margin:0;padding-left:1em;" class="muted">Shift-JIS・UTF-8 どちらも対応。</p>'
            . '</div>'
            . '<label class="list-filter-field"><span>CSVファイル</span><input type="file" name="csv_file" accept=".csv,text/csv" required></label>'
            . '<div class="actions" style="margin-top:12px;">'
            . '<button class="btn btn-primary" type="submit">取込を実行する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="renewal-import-dialog">閉じる</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>'
            . '(function(){const id="renewal-import-dialog";const dlg=document.getElementById(id);if(!dlg||typeof dlg.showModal!=="function"){return;}const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});dlg.addEventListener("click",(e)=>{const rect=dlg.getBoundingClientRect();const inside=rect.left<=e.clientX&&e.clientX<=rect.right&&rect.top<=e.clientY&&e.clientY<=rect.bottom;if(!inside&&dlg.open){dlg.close();}});if(' . ($openImportDialog ? 'true' : 'false') . '){dlg.showModal();}})();'
            . '</script>';

        return Layout::render('満期一覧', $content, $layoutOptions);
    }

    private static function renderStatusBadge(string $status): string
    {
        // 表示名がそのままDB格納値。設定画面で自由に変更可能なので個別色はつけない。
        $label = $status !== '' ? $status : '未設定';
        return '<span class="badge badge-gray">' . Layout::escape($label) . '</span>';
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
            return '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($maturityDate) . '</span>';
        }
        return Layout::escape($maturityDate);
    }

    private static function renderEarlyDeadline(string $earlyDeadline, bool $isCompleted, string $today): string
    {
        if ($earlyDeadline === '') {
            return '<span class="muted">−</span>';
        }
        if (!$isCompleted && $earlyDeadline < $today) {
            return '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($earlyDeadline) . '</span>';
        }
        if (!$isCompleted && $earlyDeadline <= date('Y-m-d', strtotime($today . ' +7 days'))) {
            return '<span style="color:var(--text-warning);">' . Layout::escape($earlyDeadline) . '</span>';
        }
        return Layout::escape($earlyDeadline);
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
