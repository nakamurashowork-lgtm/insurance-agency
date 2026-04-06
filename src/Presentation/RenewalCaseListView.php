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
        string $deleteActionUrl,
        string $deleteCsrfToken,
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
        array $allStatuses = []
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
        $maturityWindow    = (string) ($criteria['maturity_window'] ?? '30');
        $filterUserId      = (string) ($criteria['assigned_staff_id'] ?? '');
        $filterProductType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $perPage           = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort              = (string) ($listState['sort'] ?? '');
        $direction         = (string) ($listState['direction'] ?? 'asc');
        $filterOpen        = $forceFilterOpen || ListViewHelper::hasActiveFilters(array_diff_key($criteria, ['maturity_window' => true])) || $errorHtml !== '';
        $pager             = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery         = LP::queryParams($criteria, $listState);

        // バッジ用ラベルマップ
        $badgeLabelMap = [];
        foreach ($allStatuses as $sRow) {
            $badgeLabelMap[(string) ($sRow['code'] ?? '')] = (string) ($sRow['display_name'] ?? '');
        }

        $rowsHtml = '';
        $today    = date('Y-m-d');
        foreach ($rows as $row) {
            $id               = (int) ($row['renewal_case_id'] ?? 0);
            $status           = (string) ($row['case_status'] ?? '');
            $customerText     = (string) ($row['customer_name'] ?? '');
            $policyText       = (string) ($row['policy_no'] ?? '');
            $productType      = (string) ($row['product_type'] ?? '');
            $maturityDate     = (string) ($row['maturity_date'] ?? '');
            $earlyDeadline    = (string) ($row['early_renewal_deadline'] ?? '');
            $assignedUserName = (string) ($row['assigned_user_name'] ?? '');
            $detailUrl        = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $rowClass         = self::isCompletedStatus($status) ? ' class="is-completed-row"' : '';

            $deleteFormId = 'form-del-renewal-' . $id;
            $deleteLabel  = $policyText !== '' ? $policyText : ('ID: ' . $id);
            $deleteForm = '<form id="' . $deleteFormId . '" method="post" action="' . Layout::escape($deleteActionUrl) . '" style="display:inline;">'
                . LP::routeInput($deleteActionUrl)
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrfToken) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . LP::hiddenInputs(LP::queryParams($criteria, $listState))
                . '<button type="button" class="btn-icon-delete" title="削除"'
                . ' data-delete-form="' . $deleteFormId . '"'
                . ' data-delete-label="' . Layout::escape($deleteLabel) . '">'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'
                . '</button>'
                . '</form>';

            $rowsHtml .= '<tr' . $rowClass . '>'
                . '<td data-label="証券番号"><a class="text-link list-policy-text" href="' . $detailUrl . '" title="' . Layout::escape($policyText) . '">' . Layout::escape($policyText) . '</a></td>'
                . '<td data-label="満期日">' . self::renderMaturityDate($maturityDate, $status, $today) . '</td>'
                . '<td data-label="顧客名"><strong class="truncate list-row-primary" title="' . Layout::escape($customerText) . '">' . Layout::escape($customerText) . '</strong></td>'
                . '<td data-label="種目">' . Layout::escape($productType) . '</td>'
                . '<td data-label="早期更改締切">' . self::renderEarlyDeadline($earlyDeadline, $status, $today) . '</td>'
                . '<td data-label="営業担当">' . ($assignedUserName !== '' ? Layout::escape($assignedUserName) : '<span class="muted">−</span>') . '</td>'
                . '<td data-label="対応状況">' . self::renderStatusBadge($status, $badgeLabelMap) . '</td>'
                . '<td>' . $deleteForm . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="8">該当データはありません。</td></tr>';
        }

        // ステータスフィルター選択肢
        if ($allStatuses !== []) {
            $statuses = ['' => 'すべて'];
            foreach ($allStatuses as $sRow) {
                $statuses[(string) ($sRow['code'] ?? '')] = (string) ($sRow['display_name'] ?? '');
            }
        } else {
            $statuses = ['' => 'すべて', 'not_started' => '未対応', 'sj_requested' => 'SJ依頼中', 'doc_prepared' => '書類作成済', 'waiting_return' => '返送待ち', 'quote_sent' => '見積送付済', 'waiting_payment' => '入金待ち', 'completed' => '完了'];
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

        $sortSummary = self::renderSortSummary($sort, $direction);
        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage, $sortSummary);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);

        $importReturnToUrl = ListViewHelper::buildUrl($searchUrl, array_merge($listQuery, ['import_dialog' => '1']));
        $importErrorHtml   = is_string($importFlashError) && $importFlashError !== ''
            ? '<div class="error">' . Layout::escape($importFlashError) . '</div>'
            : '';
        $importSuccessHtml = is_string($importFlashSuccess) && $importFlashSuccess !== ''
            ? '<div class="notice">' . Layout::escape($importFlashSuccess) . '</div>'
            : '';

        $filterFormHtml =
            '<form method="get" action="' . Layout::escape(LP::formAction($searchUrl)) . '">'
            . LP::routeInput($searchUrl)
            . '<input type="hidden" name="filter_open" value="1">'
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="list-filter-grid">'
            . '<label class="list-filter-field"><span>顧客名</span><input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="assigned_staff_id">' . $userOptions . '</select></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $filterProductType . '"></label>'
            . '<label class="list-filter-field"><span>対応状況</span><select name="case_status">' . $statusOptions . '</select></label>'
            . '<label class="list-filter-field"><span>満期日</span><select name="maturity_window">'
            . self::renderWindowOptions($maturityWindow)
            . '</select></label>'
            . '</div>'
            . '<div class="actions list-filter-actions">'
            . '<button class="btn" type="submit">検索</button>'
            . '<a class="btn btn-secondary" href="' . Layout::escape(ListViewHelper::buildUrl($searchUrl, ['filter_open' => '1'])) . '">条件クリア</a>'
            . '</div>'
            . '</form>';

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
            . '<col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('証券番号', 'policy_no', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('満期日', 'maturity_date', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('種目', 'product_type', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('早期更改締切', 'early_renewal_deadline', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>営業担当</th>'
            . '<th>' . LP::sortLink('対応状況', 'case_status', $searchUrl, $criteria, $listState) . '</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $deleteConfirmDialog =
            '<dialog id="dlg-delete-renewal-confirm" class="modal-dialog">'
            . '<div class="modal-head"><h2>削除の確認</h2>'
            . '<button type="button" class="modal-close" id="dlg-delete-renewal-close">×</button>'
            . '</div>'
            . '<p id="dlg-delete-renewal-msg" style="margin:16px 0;"></p>'
            . '<div class="dialog-actions">'
            . '<button type="button" id="dlg-delete-renewal-ok" class="btn btn-danger">削除する</button>'
            . '<button type="button" id="dlg-delete-renewal-cancel" class="btn btn-ghost">キャンセル</button>'
            . '</div>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-delete-renewal-confirm");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'var msg=document.getElementById("dlg-delete-renewal-msg");'
            . 'var pendingId=null;'
            . 'document.querySelectorAll("[data-delete-form]").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'pendingId=btn.getAttribute("data-delete-form");'
            . 'var label=btn.getAttribute("data-delete-label")||"この件";'
            . 'msg.textContent="「"+label+"」を削除しますか？この操作は取り消せません。";'
            . 'if(!dlg.open){dlg.showModal();}});});'
            . 'function closeDlg(){if(dlg.open){dlg.close();}pendingId=null;}'
            . 'document.getElementById("dlg-delete-renewal-ok").addEventListener("click",function(){'
            . 'if(pendingId){var f=document.getElementById(pendingId);if(f){f.submit();}}'
            . 'closeDlg();});'
            . 'document.getElementById("dlg-delete-renewal-cancel").addEventListener("click",closeDlg);'
            . 'document.getElementById("dlg-delete-renewal-close").addEventListener("click",closeDlg);'
            . '})();</script>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('満期一覧', '<button class="btn btn-primary" type="button" data-open-dialog="renewal-import-dialog">+ CSV取込</button>')
            . $noticeHtml
            . LP::filterCard($filterFormHtml, $filterOpen, $errorHtml)
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . $deleteConfirmDialog
            . '<dialog id="renewal-import-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>SJNET満期データ取込</h2></div>'
            . $importErrorHtml
            . $importSuccessHtml
            . self::renderImportResult($importBatch, $importRows)
            . '<form method="post" action="' . Layout::escape($csvImportActionUrl) . '" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csvImportCsrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($importReturnToUrl) . '">'
            . '<p class="muted" style="margin-bottom:12px;font-size:12.5px;">SJ-NETからダウンロードしたCSVを選択してください。</p>'
            . '<div style="font-size:12.5px;line-height:1.7;margin-bottom:16px;">'
            . '<p style="margin:0 0 4px;font-weight:600;">■ 必須カラム</p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">証券番号 / 満期日（月）/ 満期日（日）/ 顧客名 / 保険会社 / 保険始期 / 保険終期 / 種目種類 / 合計保険料 / 代理店ｺｰﾄﾞ</p>'
            . '<p style="margin:0 0 10px;padding-left:1em;" class="muted">1つでも欠けていると取込できません。</p>'
            . '<p style="margin:0 0 4px;font-weight:600;">■ 任意カラム（あれば取り込みます）</p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">郵便番号 / 住所 / ＴＥＬ　<span class="muted">→ 新規顧客の自動登録時のみ使用。既存顧客は不変。</span></p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">払込方法　<span class="muted">→ 契約情報として登録。</span></p>'
            . '<p style="margin:0 0 10px;padding-left:1em;">担当者　<span class="muted">→ 取込履歴に参考記録。担当者設定は代理店コードを使用。</span></p>'
            . '<p style="margin:0 0 4px;font-weight:600;">■ 満期年の判定</p>'
            . '<p style="margin:0 0 2px;padding-left:1em;">CSVには満期の「月」「日」のみ含まれ、「年」は取込実行日から自動判定します。</p>'
            . '<p style="margin:0 0 2px;padding-left:1em;" class="muted">取込日以降の月日 → 当年　／　取込日より過去の月日 → 翌年</p>'
            . '<p style="margin:0 0 10px;"></p>'
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

    /**
     * @param array<string, string> $labelMap  code => display_name from master (may be empty)
     */
    private static function renderStatusBadge(string $status, array $labelMap = []): string
    {
        $badgeClass = match ($status) {
            'not_started'     => 'badge-gray',
            'sj_requested'    => 'badge-info',
            'doc_prepared'    => 'badge-info',
            'waiting_return'  => 'badge-warn',
            'quote_sent'      => 'badge-info',
            'waiting_payment' => 'badge-warn',
            'completed'       => 'badge-success',
            default           => 'badge-gray',
        };

        if (isset($labelMap[$status])) {
            $label = $labelMap[$status];
        } else {
            $label = match ($status) {
                'not_started'     => '未対応',
                'sj_requested'    => 'SJ依頼中',
                'doc_prepared'    => '書類作成済',
                'waiting_return'  => '返送待ち',
                'quote_sent'      => '見積送付済',
                'waiting_payment' => '入金待ち',
                'completed'       => '完了',
                default           => '未設定',
            };
        }

        return '<span class="badge ' . $badgeClass . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderSortSummary(string $sort, string $direction): string
    {
        if ($sort === '') {
            return '並び順: 業務優先順';
        }

        $label = match ($sort) {
            'customer_name'          => '顧客名',
            'policy_no'              => '証券番号',
            'maturity_date'          => '満期日',
            'case_status'            => '対応状況',
            'next_action_date'       => '次回対応予定日',
            'product_type'           => '種目',
            'early_renewal_deadline' => '早期更改締切',
            default                  => '業務優先順',
        };

        return '並び順: ' . $label . ' ' . ($direction === 'desc' ? '降順' : '昇順');
    }

    private static function isCompletedStatus(string $status): bool
    {
        return $status === 'completed';
    }

    private static function renderMaturityDate(string $maturityDate, string $status, string $today): string
    {
        if ($maturityDate === '') {
            return '<span class="muted">−</span>';
        }
        if ($status !== 'completed' && $maturityDate < $today) {
            return '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($maturityDate) . '</span>';
        }
        return Layout::escape($maturityDate);
    }

    private static function renderEarlyDeadline(string $earlyDeadline, string $status, string $today): string
    {
        if ($earlyDeadline === '') {
            return '<span class="muted">−</span>';
        }
        if ($status !== 'completed' && $earlyDeadline < $today) {
            return '<span style="color:var(--text-danger);font-weight:500;">' . Layout::escape($earlyDeadline) . '</span>';
        }
        if ($status !== 'completed' && $earlyDeadline <= date('Y-m-d', strtotime($today . ' +7 days'))) {
            return '<span style="color:var(--text-warning);">' . Layout::escape($earlyDeadline) . '</span>';
        }
        return Layout::escape($earlyDeadline);
    }

    private static function renderWindowOptions(string $current): string
    {
        $options = ['30' => '満期：今後30日', '60' => '今後60日', '90' => '今後90日', 'all' => '全期間'];
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
            . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:12.5px;">'
            . '<div><span class="muted">処理行数</span><span style="margin-left:8px;font-weight:500;">' . $totalRows . '行</span></div>'
            . '<div><span class="muted">契約 新規登録</span><span style="margin-left:8px;font-weight:500;">' . $insertCount . '件</span></div>'
            . '<div><span class="muted">契約 更新</span><span style="margin-left:8px;font-weight:500;">' . $updateCount . '件</span></div>'
            . '<div><span class="muted">顧客 自動登録</span><span style="margin-left:8px;font-weight:500;">' . $customerInsert . '件</span></div>'
            . '<div><span class="muted">スキップ</span><span style="margin-left:8px;">' . $skipCount . '行</span></div>'
            . '<div><span class="muted">エラー</span><span style="margin-left:8px;' . ($errorCount > 0 ? 'color:var(--text-danger);font-weight:500;' : '') . '">' . $errorCount . '行</span></div>'
            . '</div>'
            . '<hr style="margin:10px 0;border:none;border-top:1px solid var(--border-color);">'
            . '<div style="font-size:12.5px;font-weight:600;margin-bottom:4px;">担当者マッピング</div>'
            . '<div style="display:flex;flex-direction:column;gap:3px;font-size:12.5px;">'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">解決済み</span><span style="font-weight:500;">' . $resolvedCount . '件</span></div>'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">コード未登録</span><span style="' . ($unresolvedCount > 0 ? 'color:var(--text-warning);font-weight:500;' : '') . '">' . $unresolvedCount . '件</span></div>'
            . '<div style="display:flex;justify-content:space-between;"><span class="muted">無効コード</span><span>' . $inactiveCount . '件</span></div>'
            . '</div>'
            . '</div>';

        $warnings = '';
        if ($unresolvedCount > 0) {
            $warnings .= '<div class="error" style="margin-bottom:10px;font-size:12.5px;">'
                . Layout::escape($unresolvedCount . '件の代理店コードがマッピング未登録です。テナント設定 > SJNETコード設定 で登録してください。')
                . '</div>';
        }

        $ambiguousCount = 0;
        $errorRowsHtml  = '';
        foreach ($importRows as $row) {
            if ((string) ($row['row_status'] ?? '') !== 'error') {
                continue;
            }
            $errMsg = (string) ($row['error_message'] ?? '');
            if (str_contains($errMsg, 'ambiguous_customer')) {
                $ambiguousCount++;
            }
            $errorRowsHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['row_no'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['policy_no'] ?? '−')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td><span class="truncate">' . Layout::escape($errMsg) . '</span></td>'
                . '</tr>';
        }

        if ($ambiguousCount > 0) {
            $warnings .= '<div class="error" style="margin-bottom:10px;font-size:12.5px;">'
                . Layout::escape($ambiguousCount . '件の顧客名が複数一致しました。該当行の契約・満期案件は登録されていません。顧客一覧で名寄せを行ってから、手動で登録してください。')
                . '</div>';
        }

        if ($errorRowsHtml === '') {
            return $summary . $warnings;
        }

        return $summary
            . $warnings
            . '<details class="details-panel modal-help" open>'
            . '<summary>エラー行を確認（' . $errorCount . '件）</summary>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card">'
            . '<thead><tr><th>行</th><th>証券番号</th><th>顧客名</th><th>エラー内容</th></tr></thead>'
            . '<tbody>' . $errorRowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</details>';
    }
}
