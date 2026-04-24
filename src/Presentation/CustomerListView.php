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
        array $layoutOptions,
        array $quickFilterCounts = []
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

        $customerType = (string) ($criteria['customer_type'] ?? '');
        $perPage      = (int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE);
        $pager        = ListViewHelper::buildPager((int) ($listState['page'] ?? '1'), $perPage, $totalCount);
        $listState['page'] = (string) ($pager['currentPage'] ?? 1);
        $listQuery    = LP::queryParams($criteria, $listState);

        $activeModal = $openModal === 'create' ? 'create' : '';
        if ($activeModal === '' && $createDraft !== null) {
            $activeModal = 'create';
        }

        // 絞り込みバッジ件数（customer_name 以外で適用中）
        $advancedFilterCount = $customerType !== '' ? 1 : 0;

        // 顧客種別セレクト（フィルタダイアログ用）
        $typeFilterHtml = '<select name="customer_type">'
            . '<option value="">すべて</option>'
            . '<option value="individual"' . ($customerType === 'individual' ? ' selected' : '') . '>個人</option>'
            . '<option value="corporate"' . ($customerType === 'corporate' ? ' selected' : '') . '>法人</option>'
            . '</select>';

        // PC テーブル行
        $rowsHtml = '';
        foreach ($rows as $row) {
            $rowsHtml .= self::buildTableRowHtml($row, $detailBaseUrl, $listQuery);
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="6">該当データはありません。</td></tr>';
        }

        $tableHtml =
            '<div class="table-wrap list-pc-only">'
            . '<table class="table-fixed list-table">'
            . '<colgroup>'
            . '<col style="width:88px;">'
            . '<col style="width:auto;">'
            . '<col style="width:112px;">'
            . '<col style="width:92px;">'
            . '<col style="width:92px;">'
            . '<col style="width:92px;">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>顧客種別</th>'
            . '<th>' . LP::sortLink('顧客名', 'customer_name', $searchUrl, $criteria, $listState) . '</th>'
            . '<th>生年月日</th>'
            . '<th style="text-align:right;">満期件数</th>'
            . '<th style="text-align:right;">事故件数</th>'
            . '<th style="text-align:right;">活動件数</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . LP::mobileCardList(
                $rows,
                fn (array $row): string => self::buildMobileCardHtml($row, $detailBaseUrl, $listQuery),
                '顧客一覧（モバイル表示）'
            );

        $toolbarHtml = LP::searchToolbar([
            'searchUrl'         => $searchUrl,
            'searchParam'       => 'customer_name',
            'searchValue'       => (string) ($criteria['customer_name'] ?? ''),
            'searchPlaceholder' => '顧客名で検索',
            'criteria'          => $criteria,
            'listState'         => $listState,
            'filterDialogId'    => 'customer-filter-dialog',
            'advancedCount'     => $advancedFilterCount,
            'headerActions'     => '<button class="btn btn-primary" type="button" data-open-dialog="customer-create-dialog">＋ 顧客を追加</button>',
        ]);

        $currentQuickFilter = (string) ($criteria['quick_filter'] ?? '');
        $quickFilterTabsHtml = LP::quickFilterTabs([
            'currentKey' => $currentQuickFilter,
            'tabs' => [
                ''           => ['label' => 'すべて', 'countKey' => 'all'],
                'individual' => ['label' => '個人',   'countKey' => 'individual'],
                'corporate'  => ['label' => '法人',   'countKey' => 'corporate'],
            ],
            'counts'    => $quickFilterCounts,
            'paramName' => 'quick_filter',
            'searchUrl' => $searchUrl,
            'criteria'  => $criteria,
            'listState' => $listState,
        ]);

        $filterDialogHtml = LP::filterDialog([
            'id'        => 'customer-filter-dialog',
            'title'     => '絞り込み条件',
            'searchUrl' => $searchUrl,
            'listState' => $listState,
            'preserveCriteria' => ['quick_filter' => $currentQuickFilter],
            'fields'    => [
                ['label' => '顧客種別', 'html' => $typeFilterHtml],
            ],
            'clearUrl'  => $searchUrl,
        ]);

        $topToolbar  = LP::toolbar($searchUrl, $criteria, $listState, $pager, $totalCount, $perPage);
        $bottomPager = LP::bottomPager($searchUrl, $criteria, $listState, $pager);
        $createForm  = self::renderCreateForm($createDraft, $createUrl, $createCsrf, $searchUrl);

        $autoOpenId = $activeModal === 'create'
            ? 'customer-create-dialog'
            : ($forceFilterOpen ? 'customer-filter-dialog' : null);

        $content =
            '<div class="list-page-frame">'
            . LP::pageHeader('顧客一覧', '')
            . $noticeHtml
            . $listErrorHtml
            . $toolbarHtml
            . $quickFilterTabsHtml
            . LP::tableCard($topToolbar, $tableHtml, $bottomPager)
            . '</div>'
            . '<dialog id="customer-create-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . $createForm
            . '</dialog>'
            . $filterDialogHtml
            . LP::dialogScript(['customer-create-dialog', 'customer-filter-dialog'], $autoOpenId);

        return Layout::render('顧客一覧', $content, $layoutOptions);
    }

    /**
     * PC テーブル 1 行の HTML を生成する。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $listQuery
     */
    private static function buildTableRowHtml(
        array $row,
        string $detailBaseUrl,
        array $listQuery
    ): string {
        $id         = (int) ($row['id'] ?? 0);
        $custName   = (string) ($row['customer_name'] ?? '');
        $detailUrl  = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
        $birthDate  = (string) ($row['birth_date'] ?? '');

        $typeLabel = match ((string) ($row['customer_type'] ?? '')) {
            'individual' => '個人',
            'corporate'  => '法人',
            default      => '−',
        };

        return '<tr>'
            . '<td class="cell-ellipsis" data-label="顧客種別" title="' . Layout::escape($typeLabel) . '">' . Layout::escape($typeLabel) . '</td>'
            . '<td data-label="顧客名">'
            . '<a class="list-row-primary text-link" href="' . $detailUrl . '" title="' . Layout::escape($custName) . '">' . Layout::escape($custName) . '</a>'
            . '</td>'
            . '<td class="cell-date" data-label="生年月日" style="white-space:nowrap;">' . Layout::escape($birthDate !== '' ? $birthDate : '−') . '</td>'
            . '<td class="td-triple" data-label="満期件数" style="text-align:right;">' . (int) ($row['renewal_case_count'] ?? 0) . '</td>'
            . '<td class="td-triple" data-label="事故件数" style="text-align:right;">' . (int) ($row['accident_case_count'] ?? 0) . '</td>'
            . '<td class="td-triple" data-label="活動件数" style="text-align:right;">' . (int) ($row['activity_count'] ?? 0) . '</td>'
            . '</tr>';
    }

    /**
     * モバイル用 list-card の HTML を生成する（LP::mobileCardList から closure で呼ばれる）。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $listQuery
     */
    private static function buildMobileCardHtml(
        array $row,
        string $detailBaseUrl,
        array $listQuery
    ): string {
        $id         = (int) ($row['id'] ?? 0);
        $custName   = (string) ($row['customer_name'] ?? '');
        $detailUrl  = Layout::escape(ListViewHelper::buildUrl($detailBaseUrl, array_merge(['id' => (string) $id], $listQuery)));
        $updatedRaw = (string) ($row['updated_at'] ?? '');
        $updatedTs  = $updatedRaw !== '' ? strtotime($updatedRaw) : false;
        $updatedDisplay = $updatedTs !== false ? date('Y-m-d', $updatedTs) : '';

        $typeLabel = match ((string) ($row['customer_type'] ?? '')) {
            'individual' => '個人',
            'corporate'  => '法人',
            default      => '',
        };

        $renewalCount  = (int) ($row['renewal_case_count'] ?? 0);
        $accidentCount = (int) ($row['accident_case_count'] ?? 0);
        $activityCount = (int) ($row['activity_count'] ?? 0);

        return '<li class="list-card">'
            . '<a class="list-card-link" href="' . $detailUrl . '">'
            . '<div class="list-card-top">'
            . '<span class="list-card-product">' . ($typeLabel !== '' ? Layout::escape($typeLabel) : '—') . '</span>'
            . ($updatedDisplay !== '' ? '<span class="list-card-policy">' . Layout::escape($updatedDisplay) . '</span>' : '')
            . '</div>'
            . '<div class="list-card-customer">' . Layout::escape($custName) . '</div>'
            . '<div class="list-card-meta">'
            . '<span class="list-card-meta-item"><span class="list-card-meta-label">満期</span><span class="list-card-meta-value">' . $renewalCount . '</span></span>'
            . '<span class="list-card-meta-item"><span class="list-card-meta-label">事故</span><span class="list-card-meta-value">' . $accidentCount . '</span></span>'
            . '<span class="list-card-meta-item"><span class="list-card-meta-label">活動</span><span class="list-card-meta-value">' . $activityCount . '</span></span>'
            . '</div>'
            . '</a>'
            . '</li>';
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
