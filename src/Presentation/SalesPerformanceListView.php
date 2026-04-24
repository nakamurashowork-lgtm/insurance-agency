<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

final class SalesPerformanceListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array<string, mixed>> $customers
    * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $renewalCases
     * @param array<int, string> $performanceMonths
     * @param array<string, mixed>|null $createDraft
     * @param array<int, string> $allowedTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        array $customers,
        array $staffUsers,
        array $contracts,
        array $renewalCases,
        array $performanceMonths,
        ?array $createDraft,
        string $openModal,
        string $listUrl,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        string $createUrl,
        string $deleteUrl,
        string $bulkUrl,
        string $createCsrf,
        string $deleteCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $fatalError,
        array $allowedTypes,
        bool $forceFilterOpen,
        array $layoutOptions,
        array $quickFilterCounts = []
    ): string {
        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($fatalError) && $fatalError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($fatalError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $perPage    = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort       = (string) ($listState['sort'] ?? '');
        $direction  = (string) ($listState['direction'] ?? 'asc');
        // performance_fiscal_year / performance_month_num はデフォルト値を持つため、アクティブフィルター判定から除外する
        $filterOpen = $forceFilterOpen || ListViewHelper::hasActiveFilters(array_diff_key($criteria, ['performance_fiscal_year' => true, 'performance_month_num' => true])) || $noticeHtml !== '';
        $pager      = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);

        $activeModal = in_array($openModal, ['create_nonlife', 'create_life'], true) ? $openModal : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = ($createDraft['form_type'] ?? '') === 'life' ? 'create_life' : 'create_nonlife';
        }
        $activeDialogId = match ($activeModal) {
            'create_nonlife' => 'sales-create-nonlife-dialog',
            'create_life'    => 'sales-create-life-dialog',
            default          => '',
        };

        $nonlifeDraft  = ($createDraft !== null && ($createDraft['form_type'] ?? '') !== 'life') ? $createDraft : null;
        $lifeDraft     = ($createDraft !== null && ($createDraft['form_type'] ?? '') === 'life') ? $createDraft : null;
        $nonlifeForm   = self::renderNonlifeCreateForm(
            $nonlifeDraft, $customers, $staffUsers, $renewalCases,
            $createUrl, $createCsrf,
            $listUrl
        );
        $lifeForm      = self::renderLifeCreateForm(
            $lifeDraft, $customers, $staffUsers,
            $createUrl, $createCsrf,
            $listUrl
        );

        $rowsHtml    = self::renderRows($rows, $criteria, $listState, $detailBaseUrl, $customerDetailBaseUrl, $deleteUrl, $deleteCsrf, $listUrl);
        $topToolbar  = LP::toolbar($listUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($listUrl, $criteria, $listState, $pager);

        // ツールバー + クイックフィルタチップ + フィルタダイアログ
        $advancedFilterCount = 0;
        foreach (['performance_fiscal_year', 'performance_month_num', 'source_type', 'performance_type', 'staff_id', 'product_type', 'policy_no', 'settlement_month'] as $k) {
            if ((string) ($criteria[$k] ?? '') !== '') {
                $advancedFilterCount++;
            }
        }

        $headerActionsHtml = '<button type="button" class="btn btn-primary" onclick="location.href=\'' . Layout::escape($bulkUrl) . '\'">＋ 一括入力</button>';

        $toolbarHtml = LP::searchToolbar([
            'searchUrl'         => $listUrl,
            'searchParam'       => 'customer_name',
            'searchValue'       => (string) ($criteria['customer_name'] ?? ''),
            'searchPlaceholder' => '契約者名で検索',
            'criteria'          => $criteria,
            'listState'         => $listState,
            'filterDialogId'    => 'sales-filter-dialog',
            'advancedCount'     => $advancedFilterCount,
            'headerActions'     => $headerActionsHtml,
        ]);

        $currentQuickFilter = (string) ($criteria['quick_filter'] ?? '');
        $quickFilterTabsHtml = LP::quickFilterTabs([
            'currentKey' => $currentQuickFilter,
            'tabs' => [
                ''           => ['label' => 'すべて', 'countKey' => 'all'],
                'this_month' => ['label' => '今月',   'countKey' => 'this_month'],
                'this_fy'    => ['label' => '今年度', 'countKey' => 'this_fy'],
                'mine'       => ['label' => '自分',   'countKey' => 'mine'],
                'non_life'   => ['label' => '損保',   'countKey' => 'non_life'],
                'life'       => ['label' => '生保',   'countKey' => 'life'],
            ],
            'counts'    => $quickFilterCounts,
            'paramName' => 'quick_filter',
            'searchUrl' => $listUrl,
            'criteria'  => $criteria,
            'listState' => $listState,
        ]);

        $filterDialogHtml = self::renderFilterDialog($criteria, $listState, $staffUsers, $performanceMonths, $listUrl, $currentQuickFilter);

        $tableHtml =
            '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table list-table-sales">'
            . '<colgroup>'
            . '<col class="list-col-date">'
            . '<col>'
            . '<col class="list-col-product">'
            . '<col class="list-col-staff">'
            . '<col class="list-col-premium">'
            . '<col class="list-col-action">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('成績計上日', 'performance_date', $listUrl, $criteria, $listState) . '</th>'
            . '<th>' . LP::sortLink('契約者名 / 証券番号', 'customer_name', $listUrl, $criteria, $listState) . '</th>'
            . '<th>種目</th>'
            . '<th>' . LP::sortLink('担当者', 'staff_id', $listUrl, $criteria, $listState) . '</th>'
            . '<th style="text-align:right;">' . LP::sortLink('保険料', 'premium_amount', $listUrl, $criteria, $listState) . '</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('成績管理一覧', '')
            . $noticeHtml
            . $toolbarHtml
            . $quickFilterTabsHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . $filterDialogHtml
            . LP::dialogScript(['sales-filter-dialog'])
            . '<dialog id="sales-create-nonlife-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>成績を登録（損保）</h2></div>'
            . $nonlifeForm
            . '</dialog>'
            . '<dialog id="sales-create-life-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<div class="modal-head"><h2>成績を登録（生保）</h2></div>'
            . $lifeForm
            . '</dialog>'
            . '<script>'
            . '(function(){const dialogs=document.querySelectorAll("dialog[id]");dialogs.forEach((dlg)=>{const id=dlg.id;const openBtns=document.querySelectorAll("[data-open-dialog=\""+id+"\"]");openBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}});});const closeBtns=dlg.querySelectorAll("[data-close-dialog=\""+id+"\"]");closeBtns.forEach((btn)=>{btn.addEventListener("click",()=>{if(dlg.open){dlg.close();}});});});const initial=' . ($activeDialogId === '' ? '""' : '"' . Layout::escape($activeDialogId) . '"') . ';if(initial!==""){const dlg=document.getElementById(initial);if(dlg&&typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}}})();'
            . '(function(){var dlg=document.getElementById("sales-create-nonlife-dialog");if(!dlg){return;}var custText=dlg.querySelector("input[data-role=\"customer-text\"]");var custId=dlg.querySelector("input[name=\"customer_id\"]");var syncCust=function(){if(!custText||!custId){return;}var listId=custText.getAttribute("list");var dl=listId?document.getElementById(listId):null;if(!dl){return;}var val=custText.value;var opts=dl.querySelectorAll("option");var found=false;for(var i=0;i<opts.length;i++){if(opts[i].value===val){custId.value=opts[i].getAttribute("data-id")||"";found=true;break;}}if(!found){custId.value="";}};if(custText){custText.addEventListener("change",syncCust);custText.addEventListener("input",syncCust);}})();'
            . '(function(){var dlg=document.getElementById("sales-create-life-dialog");if(!dlg){return;}var custText=dlg.querySelector("input[data-role=\"customer-text\"]");var custId=dlg.querySelector("input[name=\"customer_id\"]");var syncCust=function(){if(!custText||!custId){return;}var listId=custText.getAttribute("list");var dl=listId?document.getElementById(listId):null;if(!dl){return;}var val=custText.value;var opts=dl.querySelectorAll("option");var found=false;for(var i=0;i<opts.length;i++){if(opts[i].value===val){custId.value=opts[i].getAttribute("data-id")||"";found=true;break;}}if(!found){custId.value="";}};var appDate=dlg.querySelector("input[name=\"application_date\"]");var perfDate=dlg.querySelector("input[data-role=\"perf-date-mirror\"]");var mirror=function(){if(appDate&&perfDate){perfDate.value=appDate.value;}};if(appDate){appDate.addEventListener("change",mirror);appDate.addEventListener("input",mirror);mirror();}if(custText){custText.addEventListener("change",syncCust);custText.addEventListener("input",syncCust);}})();'
            . '</script>'
            . '<dialog id="dlg-delete-sales-confirm" class="modal-dialog">'
            . '<div class="modal-head"><h2>削除の確認</h2>'
            . '<button type="button" class="modal-close" id="dlg-delete-sales-close">×</button>'
            . '</div>'
            . '<p id="dlg-delete-sales-msg" style="margin:16px 0;"></p>'
            . '<div class="dialog-actions">'
            . '<button type="button" id="dlg-delete-sales-ok" class="btn btn-danger">削除する</button>'
            . '<button type="button" id="dlg-delete-sales-cancel" class="btn btn-ghost">キャンセル</button>'
            . '</div>'
            . '</dialog>'
            . '<script>(function(){var dlg=document.getElementById("dlg-delete-sales-confirm");if(!dlg||typeof dlg.showModal!=="function"){return;}var msg=document.getElementById("dlg-delete-sales-msg");var pendingId=null;document.querySelectorAll("[data-delete-form]").forEach(function(btn){btn.addEventListener("click",function(){pendingId=btn.getAttribute("data-delete-form");var label=btn.getAttribute("data-delete-label")||"この件";if(msg){msg.textContent="「"+label+"」を削除しますか？この操作は取り消せません。";}if(!dlg.open){dlg.showModal();}});});function closeDlg(){if(dlg.open){dlg.close();}pendingId=null;}document.getElementById("dlg-delete-sales-ok").addEventListener("click",function(){if(pendingId){var f=document.getElementById(pendingId);if(f){f.submit();}}closeDlg();});document.getElementById("dlg-delete-sales-cancel").addEventListener("click",closeDlg);document.getElementById("dlg-delete-sales-close").addEventListener("click",closeDlg);})();</script>';

        return Layout::render('成績管理一覧', $content, $layoutOptions);
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, string> $performanceMonths
     */
    private static function renderFilterDialog(array $criteria, array $listState, array $staffUsers, array $performanceMonths, string $listUrl, string $currentQuickFilter): string
    {
        $selectedFY       = (string) ($criteria['performance_fiscal_year'] ?? '');
        $selectedMonthNum = (string) ($criteria['performance_month_num'] ?? '');
        $staffUserId      = (string) ($criteria['staff_id'] ?? '');
        $selectedSourceType = (string) ($criteria['source_type'] ?? '');
        $productType      = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $policyNo         = Layout::escape((string) ($criteria['policy_no'] ?? ''));

        // 年度セレクト
        $fiscalYears = [];
        foreach ($performanceMonths as $ym) {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
                continue;
            }
            $fy = (int) $m[2] >= 4 ? (int) $m[1] : (int) $m[1] - 1;
            $fyStr = (string) $fy;
            if (!in_array($fyStr, $fiscalYears, true)) {
                $fiscalYears[] = $fyStr;
            }
        }
        if ($selectedFY !== '' && !in_array($selectedFY, $fiscalYears, true)) {
            $fiscalYears[] = $selectedFY;
        }
        rsort($fiscalYears);
        $fyOptions = '<option value="">すべて</option>';
        foreach ($fiscalYears as $fy) {
            $sel       = $fy === $selectedFY ? ' selected' : '';
            $fyOptions .= '<option value="' . Layout::escape($fy) . '"' . $sel . '>' . Layout::escape($fy . '年度') . '</option>';
        }

        $monthOptions = '<option value="">すべて</option>';
        foreach ([4,5,6,7,8,9,10,11,12,1,2,3] as $mn) {
            $sel           = (string) $mn === $selectedMonthNum ? ' selected' : '';
            $monthOptions .= '<option value="' . $mn . '"' . $sel . '>' . $mn . '月</option>';
        }

        $staffOptions = '<option value="">すべて</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $sel          = $staffUserId !== '' && (int) $staffUserId === $id ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        $sourceSelectHtml = '<select name="source_type">'
            . '<option value="">すべて</option>'
            . '<option value="non_life"' . ($selectedSourceType === 'non_life' ? ' selected' : '') . '>損保</option>'
            . '<option value="life"' . ($selectedSourceType === 'life' ? ' selected' : '') . '>生保</option>'
            . '</select>';

        return LP::filterDialog([
            'id'        => 'sales-filter-dialog',
            'title'     => '絞り込み条件',
            'searchUrl' => $listUrl,
            'listState' => $listState,
            'preserveCriteria' => ['quick_filter' => $currentQuickFilter],
            'fields' => [
                ['label' => '年度',       'html' => '<select name="performance_fiscal_year">' . $fyOptions . '</select>'],
                ['label' => '月',         'html' => '<select name="performance_month_num">' . $monthOptions . '</select>'],
                ['label' => '業務区分',   'html' => $sourceSelectHtml],
                ['label' => '担当者',     'html' => '<select name="staff_id">' . $staffOptions . '</select>'],
                ['label' => '種目',       'html' => '<input type="text" name="product_type" value="' . $productType . '" placeholder="部分一致">'],
                ['label' => '証券番号',   'html' => '<input type="text" name="policy_no" value="' . $policyNo . '" placeholder="部分一致">'],
            ],
            'clearUrl' => $listUrl,
        ]);
    }

    private static function renderSearchForm(array $criteria, array $listState, array $staffUsers, array $performanceMonths, string $listUrl): string
    {
        $selectedFY       = (string) ($criteria['performance_fiscal_year'] ?? '');
        $selectedMonthNum = (string) ($criteria['performance_month_num'] ?? '');
        $customerName     = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $staffUserId      = (string) ($criteria['staff_id'] ?? '');
        $selectedSourceType = (string) ($criteria['source_type'] ?? '');
        $productType      = Layout::escape((string) ($criteria['product_type'] ?? ''));

        // 年度セレクト: DB成績月から年度を逆算（月>=4: その年、月<=3: 前年）
        $fiscalYears = [];
        foreach ($performanceMonths as $ym) {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
                continue;
            }
            $fy = (int) $m[2] >= 4 ? (int) $m[1] : (int) $m[1] - 1;
            $fyStr = (string) $fy;
            if (!in_array($fyStr, $fiscalYears, true)) {
                $fiscalYears[] = $fyStr;
            }
        }
        if ($selectedFY !== '' && !in_array($selectedFY, $fiscalYears, true)) {
            $fiscalYears[] = $selectedFY;
        }
        rsort($fiscalYears);
        $fyOptions = '<option value="">すべて</option>';
        foreach ($fiscalYears as $fy) {
            $sel       = $fy === $selectedFY ? ' selected' : '';
            $fyOptions .= '<option value="' . Layout::escape($fy) . '"' . $sel . '>' . Layout::escape($fy . '年度') . '</option>';
        }

        // 月セレクト: 年度の流れに合わせ 4〜3月 の順で表示
        $monthOptions = '<option value="">すべて</option>';
        foreach ([4,5,6,7,8,9,10,11,12,1,2,3] as $mn) {
            $sel           = (string) $mn === $selectedMonthNum ? ' selected' : '';
            $monthOptions .= '<option value="' . $mn . '"' . $sel . '>' . $mn . '月</option>';
        }

        $staffOptions = '<option value="">すべて</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $sel          = $staffUserId !== '' && (int) $staffUserId === $id ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $sel . '>' . Layout::escape($name) . '</option>';
        }

        return '<div class="search-panel-compact">'
            . '<div class="toggle-header">'
            . '<span class="toggle-header-title">検索条件を閉じる</span>'
            . '<span class="toggle-header-arrow">▲</span>'
            . '</div>'
            . '<div class="search-panel-body">'
            . '<form method="get" action="' . Layout::escape(LP::formAction($listUrl)) . '">'
            . LP::routeInput($listUrl)
            . LP::hiddenInputs(LP::queryParams([], $listState, false, true))
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">年度</span><select name="performance_fiscal_year" class="compact-input w-sm">' . $fyOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">月</span><select name="performance_month_num" class="compact-input w-sm">' . $monthOptions . '</select></div>'
            . '<div class="search-field"><span class="search-label">業務区分</span><select name="source_type" class="compact-input w-sm"><option value="">すべて</option><option value="non_life"' . ($selectedSourceType === 'non_life' ? ' selected' : '') . '>損保</option><option value="life"' . ($selectedSourceType === 'life' ? ' selected' : '') . '>生保</option></select></div>'
            . '<div class="search-field"><span class="search-label">契約者名</span><input type="text" name="customer_name" class="compact-input w-md" value="' . $customerName . '"></div>'
            . '<div class="search-field"><span class="search-label">担当者</span><select name="staff_id" class="compact-input w-md">' . $staffOptions . '</select></div>'
            . '</div>'
            . '<div class="search-row">'
            . '<div class="search-field"><span class="search-label">種目</span><input type="text" name="product_type" class="compact-input w-md" value="' . $productType . '"></div>'
            . '<div class="search-actions">'
            . '<button class="btn btn-small" type="submit">検索</button>'
            . '<a class="btn btn-small btn-secondary" href="' . Layout::escape($listUrl) . '">クリア</a>'
            . '</div>'
            . '</div>'
            . '</form>'
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<int, array<string, mixed>> $renewalCases
     */
    private static function renderNonlifeCreateForm(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $renewalCases,
        string $actionUrl,
        string $csrfToken,
        string $returnTo
    ): string {
        $customerId       = (int) ($record['customer_id'] ?? 0);
        $contractId       = (int) ($record['contract_id'] ?? 0);
        $performanceDate  = Layout::escape((string) ($record['performance_date'] ?? date('Y-m-d')));
        $performanceType  = (string) ($record['performance_type'] ?? '');
        $policyNo         = Layout::escape((string) ($record['policy_no'] ?? ''));
        $policyStartDate  = Layout::escape((string) ($record['policy_start_date'] ?? ''));
        $insuranceCategory = Layout::escape((string) ($record['insurance_category'] ?? ''));
        $productType      = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumAmount    = Layout::escape((string) ($record['premium_amount'] ?? ''));
        $installmentCount = Layout::escape((string) ($record['installment_count'] ?? ''));
        $receiptNo        = Layout::escape((string) ($record['receipt_no'] ?? ''));
        $settlementMonth  = Layout::escape((string) ($record['settlement_month'] ?? ''));
        $staffUserId      = (int) ($record['staff_id'] ?? 0);
        $remark           = Layout::escape((string) ($record['remark'] ?? ''));

        // 成績区分ラジオ
        $ptRadios  = '';
        foreach (['new' => '新規契約', 'addition' => '追加引受', 'change' => '変更', 'cancel_deduction' => '解約・等級訂正'] as $v => $l) {
            $checked   = $v === $performanceType || ($performanceType === '' && $v === 'new') ? ' checked' : '';
            $ptRadios .= '<label class="radio-inline"><input type="radio" name="performance_type_detail" value="' . $v . '"' . $checked . '> ' . $l . '</label>';
        }

        // 顧客 datalist
        $dlId          = 'sales-create-nonlife-customers-list';
        $customerDl    = '<datalist id="' . $dlId . '">';
        $customerNameVal = '';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            if ($cid === $customerId) {
                $customerNameVal = $cname;
            }
            $customerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $customerDl .= '</datalist>';
        $customerNameEsc = Layout::escape($customerNameVal);

        // 担当者選択肢
        $staffOptions = '<option value="">未設定</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $selected     = $id === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        return ''
            . '<form method="post" action="' . Layout::escape($actionUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<input type="hidden" name="form_type" value="non_life">'
            . '<input type="hidden" name="contract_id" value="' . $contractId . '">'
            . '<input type="hidden" name="customer_id" value="' . $customerId . '">'
            . $customerDl
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>成績計上日 <strong class="required-mark">*</strong></span><input type="date" name="performance_date" value="' . $performanceDate . '" required></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><input type="text" list="' . $dlId . '" data-role="customer-text" autocomplete="off" value="' . $customerNameEsc . '" placeholder="顧客名で検索" required></label>'
            . '<label class="list-filter-field"><span>種目</span><input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label class="list-filter-field"><span>保険種類</span><input type="text" name="insurance_category" value="' . $insuranceCategory . '"></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label class="list-filter-field"><span>始期日</span><input type="date" name="policy_start_date" value="' . $policyStartDate . '"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section" data-section="perf-type-section">'
            . '<h3 class="modal-form-title">成績区分 <strong class="required-mark">*</strong></h3>'
            . '<div class="radio-group" style="margin-top:6px;">' . $ptRadios . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">金額・精算情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" min="0" step="1" name="premium_amount" value="' . $premiumAmount . '" required></label>'
            . '<label class="list-filter-field"><span>精算月</span><input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
            . '<label class="list-filter-field"><span>分割回数</span><input type="number" min="1" max="255" step="1" name="installment_count" value="' . $installmentCount . '"></label>'
            . '<label class="list-filter-field"><span>領収証番号</span><input type="text" name="receipt_no" value="' . $receiptNo . '"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="sales-create-nonlife-dialog">キャンセル</button>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     */
    private static function renderLifeCreateForm(
        ?array $record,
        array $customers,
        array $staffUsers,
        string $actionUrl,
        string $csrfToken,
        string $returnTo
    ): string {
        $customerId       = (int) ($record['customer_id'] ?? 0);
        $applicationDate  = Layout::escape((string) ($record['application_date'] ?? date('Y-m-d')));
        $policyNo         = Layout::escape((string) ($record['policy_no'] ?? ''));
        $productType      = Layout::escape((string) ($record['product_type'] ?? ''));
        $premiumAmount    = Layout::escape((string) ($record['premium_amount'] ?? ''));
        $staffUserId      = (int) ($record['staff_id'] ?? 0);
        $remark           = Layout::escape((string) ($record['remark'] ?? ''));

        // 顧客 datalist
        $dlId          = 'sales-create-life-customers-list';
        $customerDl    = '<datalist id="' . $dlId . '">';
        $customerNameVal = '';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            if ($cid === $customerId) {
                $customerNameVal = $cname;
            }
            $customerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $customerDl .= '</datalist>';
        $customerNameEsc = Layout::escape($customerNameVal);

        // 担当者選択肢
        $staffOptions = '<option value="">未設定</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $selected     = $id === $staffUserId ? ' selected' : '';
            $staffOptions .= '<option value="' . $id . '"' . $selected . '>' . Layout::escape($name) . '</option>';
        }

        return ''
            . '<form method="post" action="' . Layout::escape($actionUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<input type="hidden" name="form_type" value="life">'
            . '<input type="hidden" name="customer_id" value="' . $customerId . '">'
            . '<input type="hidden" name="performance_date" value="' . $applicationDate . '" data-role="perf-date-mirror">'
            . $customerDl
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">基本情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>申込日 <strong class="required-mark">*</strong></span><input type="date" name="application_date" value="' . $applicationDate . '" required></label>'
            . '<label class="list-filter-field"><span>担当者</span><select name="staff_id">' . $staffOptions . '</select></label>'
            . '<label class="list-filter-field"><span>契約者名 <strong class="required-mark">*</strong></span><input type="text" list="' . $dlId . '" data-role="customer-text" autocomplete="off" value="' . $customerNameEsc . '" placeholder="顧客名で検索" required></label>'
            . '<label class="list-filter-field"><span>保険商品 <strong class="required-mark">*</strong></span><input type="text" name="product_type" value="' . $productType . '" required></label>'
            . '<label class="list-filter-field"><span>証券番号</span><input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<h3 class="modal-form-title">金額情報</h3>'
            . '<div class="list-filter-grid modal-form-grid">'
            . '<label class="list-filter-field"><span>保険料 <strong class="required-mark">*</strong></span><input type="number" min="0" step="1" name="premium_amount" value="' . $premiumAmount . '" required></label>'
            . '</div>'
            . '</section>'
            . '<section class="modal-form-section">'
            . '<label class="list-filter-field modal-form-wide"><span>備考</span><textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</section>'
            . '<div class="actions modal-form-actions">'
            . '<button class="btn" type="submit">登録する</button>'
            . '<button class="btn btn-secondary" type="button" data-close-dialog="sales-create-life-dialog">キャンセル</button>'
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private static function renderRows(
        array $rows,
        array $criteria,
        array $listState,
        string $detailBaseUrl,
        string $customerDetailBaseUrl,
        string $deleteUrl,
        string $deleteCsrf,
        string $listUrl
    ): string {
        $rowsHtml = '';

        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $params       = LP::queryParams($criteria, $listState);
            $detailUrl    = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $params)));
            $customerName = (string) ($row['display_customer'] ?? $row['customer_name'] ?? '');
            $customerId   = (int) ($row['customer_id'] ?? 0);
            $customerHtml = $customerId > 0
                ? '<a class="text-link" href="' . Layout::escape($customerDetailBaseUrl . '&id=' . $customerId) . '" title="' . Layout::escape($customerName) . '">' . Layout::escape($customerName) . '</a>'
                : Layout::escape($customerName);
            $premium      = (int) ($row['premium_amount'] ?? 0);
            $premiumFormatted = number_format($premium) . '円';
            $premiumHtml  = $premium < 0
                ? '<span style="color:var(--text-danger);">' . Layout::escape($premiumFormatted) . '</span>'
                : Layout::escape($premiumFormatted);

            $deleteFormId = 'form-del-sales-' . $id;
            $deleteLabel  = Layout::escape($customerName !== '' ? $customerName : ('ID: ' . $id));
            $deleteForm   = '<form id="' . $deleteFormId . '" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:inline;">'
                . LP::routeInput($deleteUrl)
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="return_to" value="' . Layout::escape($listUrl) . '">'
                . LP::hiddenInputs($params)
                . '<button type="button" class="btn-icon-delete" title="削除"'
                . ' data-delete-form="' . $deleteFormId . '"'
                . ' data-delete-label="' . $deleteLabel . '">'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'
                . '</button>'
                . '</form>';

            $performanceDate = (string) ($row['performance_date'] ?? '');
            $productType     = (string) ($row['product_type'] ?? '');
            $policyNo        = (string) ($row['policy_no_display'] ?? $row['policy_no'] ?? '');
            $secondaryHtml   = $policyNo !== ''
                ? '<div class="list-row-secondary">' . Layout::escape($policyNo) . '</div>'
                : '';
            $primaryLabel = $customerName !== '' ? $customerName : '（契約者未設定）';

            $rowsHtml .= '<tr>'
                . '<td class="cell-date" data-label="成績計上日" style="white-space:nowrap;">' . Layout::escape($performanceDate) . '</td>'
                . '<td data-label="契約者名 / 証券番号">'
                . '<div class="list-row-stack">'
                . '<a class="list-row-primary text-link" href="' . $detailUrl . '" title="' . Layout::escape($primaryLabel) . '">' . Layout::escape($primaryLabel) . '</a>'
                . $secondaryHtml
                . '</div>'
                . '</td>'
                . '<td class="cell-ellipsis" data-label="種目" title="' . Layout::escape($productType) . '">' . Layout::escape($productType) . '</td>'
                . '<td class="cell-ellipsis" data-label="担当者" title="' . Layout::escape((string) ($row['staff_user_name'] ?? '')) . '">' . Layout::escape((string) ($row['staff_user_name'] ?? '')) . '</td>'
                . '<td data-label="保険料" style="white-space:nowrap;text-align:right;">' . $premiumHtml . '</td>'
                . '<td>' . $deleteForm . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6">該当データはありません。</td></tr>';
        }

        return $rowsHtml;
    }

    private static function performanceTypeLabel(string $type): string
    {
        return match ($type) {
            'new'              => '新規',
            'renewal'          => '更改',
            'addition'         => '追加',
            'change'           => '異動',
            'cancel_deduction' => '解約控除',
            default            => $type,
        };
    }

    private static function sourceTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'non_life' => '損保',
            'life'     => '生保',
            default    => '',
        };
    }

    private static function businessLabel(string $sourceType, string $performanceType): string
    {
        return match ($sourceType) {
            'life'     => '生保',
            'non_life' => '損保',
            default    => '',
        };
    }
}
