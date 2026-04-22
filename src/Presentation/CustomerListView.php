<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListPageRenderer as LP;
use App\Presentation\View\ListViewHelper;

final class CustomerListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @param array<string, mixed>|null $createDraft
     * @param array<int, array{id: int, staff_name: string}> $staffUsers
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $rows,
        int $totalCount,
        array $criteria,
        array $listState,
        string $searchUrl,
        string $detailBaseUrl,
        ?string $errorMessage,
        bool $forceFilterOpen,
        string $createUrl,
        string $createCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        string $openModal,
        ?array $createDraft,
        array $layoutOptions
    ): string {
        $listErrorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $listErrorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $customerType = (string) ($criteria['customer_type'] ?? '');
        $perPage      = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $sort         = (string) ($listState['sort'] ?? '');
        $direction    = (string) ($listState['direction'] ?? 'asc');
        $filterOpen   = $forceFilterOpen || ListViewHelper::hasActiveFilters($criteria) || $listErrorHtml !== '';
        $pager        = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery    = LP::queryParams($criteria, $listState);

        $activeModal = $openModal === 'create' ? 'create' : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = 'create';
        }

        $rowsHtml = '';
        foreach ($rows as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $detailUrl    = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
            $updatedRaw   = (string) ($row['updated_at'] ?? '');
            $updatedTs    = $updatedRaw !== '' ? strtotime($updatedRaw) : false;
            $updatedDisplay = $updatedTs !== false ? date('Y-m-d', $updatedTs) : '';

            $typeLabel = match ((string) ($row['customer_type'] ?? '')) {
                'individual' => '個人',
                'corporate'  => '法人',
                default      => '',
            };
            $rowsHtml .= '<tr>'
                . '<td class="cell-ellipsis" data-label="顧客名" title="' . Layout::escape((string) ($row['customer_name'] ?? '')) . '"><a class="text-link" href="' . $detailUrl . '">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</a></td>'
                . '<td data-label="顧客種別">' . Layout::escape($typeLabel) . '</td>'
                . '<td data-label="生年月日" style="white-space:nowrap;">' . Layout::escape((string) ($row['birth_date'] ?? '')) . '</td>'
                . '<td class="td-triple" data-label="満期件数" style="text-align:right;">' . (int) ($row['renewal_case_count'] ?? 0) . '</td>'
                . '<td class="td-triple" data-label="事故件数" style="text-align:right;">' . (int) ($row['accident_case_count'] ?? 0) . '</td>'
                . '<td class="td-triple" data-label="活動件数" style="text-align:right;">' . (int) ($row['activity_count'] ?? 0) . '</td>'
                . '<td data-label="最終更新" style="white-space:nowrap;">' . Layout::escape($updatedDisplay) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="7">該当データはありません。</td></tr>';
        }

        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);
        $createForm  = self::renderCreateForm($createDraft, $createUrl, $createCsrf, $searchUrl);

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
            . '<div class="search-field"><span class="search-label">顧客名</span><input type="text" name="customer_name" class="compact-input w-lg" value="' . $customerName . '"></div>'
            . '<div class="search-field"><span class="search-label">顧客種別</span>'
            . '<select name="customer_type" class="compact-input">'
            . '<option value="">すべて</option>'
            . '<option value="individual"' . ($customerType === 'individual' ? ' selected' : '') . '>個人</option>'
            . '<option value="corporate"' . ($customerType === 'corporate' ? ' selected' : '') . '>法人</option>'
            . '</select></div>'
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
            . '<table class="table-fixed table-card list-table">'
            . '<colgroup>'
            . '<col style="width:auto;">'
            . '<col style="width:80px;">'
            . '<col style="width:110px;">'
            . '<col style="width:80px;">'
            . '<col style="width:80px;">'
            . '<col style="width:80px;">'
            . '<col style="width:110px;">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>' . LP::sortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>顧客種別</th>'
            . '<th>生年月日</th>'
            . '<th style="text-align:right;">満期件数</th>'
            . '<th style="text-align:right;">事故件数</th>'
            . '<th style="text-align:right;">活動件数</th>'
            . '<th>' . LP::sortLink('最終更新', 'updated_at', $searchUrl, $criteria, $listState) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('顧客一覧', '<button class="btn" type="button" data-open-dialog="customer-create-dialog">顧客を追加</button>')
            . $noticeHtml
            . $listErrorHtml
            . $filterPanelHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . '<dialog id="customer-create-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . $createForm
            . '</dialog>'
            . '<script>'
            . '(function(){'
            . 'const dlg=document.getElementById("customer-create-dialog");'
            . 'if(!dlg)return;'
            . 'const openBtn=document.querySelector("[data-open-dialog=\"customer-create-dialog\"]");'
            . 'if(openBtn){openBtn.addEventListener("click",function(){if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}});}'
            . 'const initial=' . ($activeModal === 'create' ? '"customer-create-dialog"' : '""') . ';'
            . 'if(initial!==""){if(typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}}'
            . '})();'
            . '</script>';

        return Layout::render('顧客一覧', $content, $layoutOptions);
    }

    private static function renderCreateForm(
        ?array $draft,
        string $createUrl,
        string $createCsrf,
        string $returnTo
    ): string {
        $draftType   = Layout::escape((string) ($draft['customer_type'] ?? ''));
        $draftName   = Layout::escape((string) ($draft['customer_name'] ?? ''));
        $draftPhone  = Layout::escape((string) ($draft['phone'] ?? ''));
        $draftPostal = Layout::escape((string) ($draft['postal_code'] ?? ''));
        $draftAddr1  = Layout::escape((string) ($draft['address1'] ?? ''));
        $draftAddr2  = Layout::escape((string) ($draft['address2'] ?? ''));
        $draftNote   = Layout::escape((string) ($draft['note'] ?? ''));

        $typeOptions = '';
        foreach (['individual' => '個人', 'corporate' => '法人'] as $val => $label) {
            $selected    = $draftType === $val ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($val) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $action     = Layout::escape(LP::formAction($createUrl));
        $routeInput = LP::routeInput($createUrl);

        return '<form method="post" action="' . $action . '" id="customer-create-form" class="customer-create-form">'
            . $routeInput
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($createCsrf) . '">'
            . '<input type="hidden" name="return_to" value="' . Layout::escape($returnTo) . '">'
            . '<h2 class="modal-title">顧客を追加</h2>'
            . '<div class="customer-create-grid">'
            . '<label class="form-field form-field--required"><span class="form-field-label">顧客区分</span>'
            . '<select name="customer_type" required aria-required="true">'
            . '<option value="">選択してください</option>'
            . $typeOptions
            . '</select></label>'
            . '<label class="form-field form-field--required"><span class="form-field-label">顧客名</span>'
            . '<input type="text" name="customer_name" value="' . $draftName . '" required aria-required="true" maxlength="200" placeholder="例：山田太郎"></label>'
            . '<label class="form-field"><span class="form-field-label">電話番号</span>'
            . '<input type="text" name="phone" value="' . $draftPhone . '" maxlength="30" placeholder="例：03-1234-5678"></label>'
            . '<label class="form-field"><span class="form-field-label">郵便番号</span>'
            . '<input type="text" name="postal_code" value="' . $draftPostal . '" maxlength="20" placeholder="例：100-0001"></label>'
            . '<label class="form-field"><span class="form-field-label">住所1</span>'
            . '<input type="text" name="address1" value="' . $draftAddr1 . '" maxlength="255" placeholder="例：東京都千代田区千代田1-1"></label>'
            . '<label class="form-field"><span class="form-field-label">住所2</span>'
            . '<input type="text" name="address2" value="' . $draftAddr2 . '" maxlength="255" placeholder="例：○○ビル3F"></label>'
            . '<label class="form-field form-field--full"><span class="form-field-label">備考</span>'
            . '<textarea name="note" rows="4" maxlength="2000">' . $draftNote . '</textarea></label>'
            . '</div>'
            . '<div class="dialog-actions">'
            . '<button type="button" class="btn btn-secondary" onclick="(function(){var d=document.getElementById(\'customer-create-dialog\');if(d&&d.open)d.close();})()">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">登録する</button>'
            . '</div>'
            . '</form>';
    }

}
