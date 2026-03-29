<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class SalesPerformanceListView
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $criteria
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $renewalCases
     * @param array<string, mixed>|null $editing
     * @param array<int, string> $allowedTypes
    * @param array<string, mixed>|null $importBatch
    * @param array<int, array<string, mixed>> $importRows
     */
    public static function render(
        array $rows,
        array $criteria,
        array $customers,
        array $contracts,
        array $renewalCases,
        ?array $editing,
        string $listUrl,
        string $createUrl,
        string $updateUrl,
        string $deleteUrl,
        string $importUrl,
        string $dashboardUrl,
        string $createCsrf,
        string $updateCsrf,
        string $deleteCsrf,
        string $importCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $fatalError,
        array $allowedTypes,
        ?array $importBatch,
        array $importRows
    ): string {
        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($fatalError) && $fatalError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($fatalError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $searchForm = self::renderSearchForm($criteria, $listUrl);
        $importForm = self::renderImportForm($importUrl, $importCsrf);
        $importResult = self::renderImportResult($importBatch, $importRows);
        $createForm = self::renderEditForm(
            null,
            $customers,
            $contracts,
            $renewalCases,
            $allowedTypes,
            $createUrl,
            $createCsrf,
            false,
            $listUrl
        );

        $editForm = '';
        if ($editing !== null) {
            $editForm = self::renderEditForm(
                $editing,
                $customers,
                $contracts,
                $renewalCases,
                $allowedTypes,
                $updateUrl,
                $updateCsrf,
                true,
                $listUrl
            );
        }

        $rowsHtml = self::renderRows($rows, $listUrl, $deleteUrl, $deleteCsrf);

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">実績管理</h1>'
            . '<p class="muted">実績の検索、登録、編集、削除、CSV取込をこの画面で実施します。</p>'
            . '<p><a class="btn btn-secondary" href="' . Layout::escape($dashboardUrl) . '">ダッシュボードへ戻る</a></p>'
            . $errorHtml
            . $successHtml
            . $searchForm
            . '</div>'
            . '<div class="card">'
            . '<h2>CSV取込</h2>'
            . '<p class="muted">ヘッダ付きCSVを取り込みます。更新キーは `receipt_no`、契約紐付けは `policy_no`、満期案件紐付けは `policy_no + maturity_date` を使用します。</p>'
            . $importForm
            . $importResult
            . '</div>'
            . '<div class="card">'
            . '<h2>実績一覧</h2>'
            . '<p>件数: ' . count($rows) . '</p>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">操作</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">実績計上日</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">契約者名</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">証券番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">種目</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">保険料</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">精算月</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">備考</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>'
            . '<div class="card">'
            . '<h2>実績追加</h2>'
            . $createForm
            . '</div>'
            . ($editForm !== ''
                ? '<div class="card"><h2>実績編集</h2>' . $editForm . '</div>'
                : '');

        return Layout::render('実績管理', $content);
    }

    /**
     * @param array<string, string> $criteria
     */
    private static function renderSearchForm(array $criteria, string $listUrl): string
    {
        $dateFrom = Layout::escape((string) ($criteria['performance_date_from'] ?? ''));
        $dateTo = Layout::escape((string) ($criteria['performance_date_to'] ?? ''));
        $customerName = Layout::escape((string) ($criteria['customer_name'] ?? ''));
        $policyNo = Layout::escape((string) ($criteria['policy_no'] ?? ''));
        $productType = Layout::escape((string) ($criteria['product_type'] ?? ''));
        $settlementMonth = Layout::escape((string) ($criteria['settlement_month'] ?? ''));

        return ''
            . '<form method="get" action="' . Layout::escape($listUrl) . '">'
            . '<div class="grid">'
            . '<label>実績計上日From<input type="date" name="performance_date_from" value="' . $dateFrom . '"></label>'
            . '<label>実績計上日To<input type="date" name="performance_date_to" value="' . $dateTo . '"></label>'
            . '<label>契約者名<input type="text" name="customer_name" value="' . $customerName . '"></label>'
            . '<label>証券番号<input type="text" name="policy_no" value="' . $policyNo . '"></label>'
            . '<label>種目<input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label>精算月<input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
            . '</div>'
            . '<div style="margin-top:12px;">'
            . '<button class="btn" type="submit">検索</button> '
            . '<a class="btn btn-secondary" href="' . Layout::escape($listUrl) . '">条件クリア</a>'
            . '</div>'
            . '</form>';
    }

    private static function renderImportForm(string $importUrl, string $importCsrf): string
    {
        return ''
            . '<form method="post" action="' . Layout::escape($importUrl) . '" enctype="multipart/form-data">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($importCsrf) . '">'
            . '<p class="muted">必須ヘッダ: receipt_no, policy_no, customer_name, maturity_date, performance_date, performance_type, insurance_category, product_type, premium_amount, settlement_month, remark</p>'
            . '<input type="file" name="csv_file" accept=".csv,text/csv" required> '
            . '<button class="btn" type="submit">CSV取込を実行</button>'
            . '</form>';
    }

    /**
     * @param array<string, mixed>|null $importBatch
     * @param array<int, array<string, mixed>> $importRows
     */
    private static function renderImportResult(?array $importBatch, array $importRows): string
    {
        if ($importBatch === null) {
            return '';
        }

        $rowsHtml = '';
        foreach ($importRows as $row) {
            $rowsHtml .= '<tr>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['row_no'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['row_status'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['error_message'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" style="padding:8px;">取込結果はありません。</td></tr>';
        }

        return ''
            . '<div style="margin-top:16px;">'
            . '<h3 style="margin-bottom:8px;">直近取込結果</h3>'
            . '<p>ファイル名: ' . Layout::escape((string) ($importBatch['file_name'] ?? '')) . '</p>'
            . '<p>状態: ' . Layout::escape((string) ($importBatch['import_status'] ?? ''))
            . ' / 総行数: ' . Layout::escape((string) ($importBatch['total_row_count'] ?? '0'))
            . ' / 有効: ' . Layout::escape((string) ($importBatch['valid_row_count'] ?? '0'))
            . ' / 新規: ' . Layout::escape((string) ($importBatch['insert_count'] ?? '0'))
            . ' / 更新: ' . Layout::escape((string) ($importBatch['update_count'] ?? '0'))
            . ' / エラー: ' . Layout::escape((string) ($importBatch['error_count'] ?? '0'))
            . '</p>'
            . '<div style="overflow:auto;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">行</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">判定</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">証券番号</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">契約者名</th>'
            . '<th style="text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;">エラー</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $editing
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $renewalCases
     * @param array<int, string> $allowedTypes
     */
    private static function renderEditForm(
        ?array $editing,
        array $customers,
        array $contracts,
        array $renewalCases,
        array $allowedTypes,
        string $actionUrl,
        string $csrfToken,
        bool $isUpdate,
        string $cancelUrl
    ): string {
        $currentId = (int) ($editing['id'] ?? 0);
        $customerId = (int) ($editing['customer_id'] ?? 0);
        $contractId = (int) ($editing['contract_id'] ?? 0);
        $renewalCaseId = (int) ($editing['renewal_case_id'] ?? 0);
        $performanceDate = Layout::escape((string) ($editing['performance_date'] ?? date('Y-m-d')));
        $performanceType = (string) ($editing['performance_type'] ?? 'new');
        $insuranceCategory = Layout::escape((string) ($editing['insurance_category'] ?? ''));
        $productType = Layout::escape((string) ($editing['product_type'] ?? ''));
        $premiumAmount = Layout::escape((string) ($editing['premium_amount'] ?? '0'));
        $receiptNo = Layout::escape((string) ($editing['receipt_no'] ?? ''));
        $settlementMonth = Layout::escape((string) ($editing['settlement_month'] ?? ''));
        $staffUserId = Layout::escape((string) ($editing['staff_user_id'] ?? ''));
        $remark = Layout::escape((string) ($editing['remark'] ?? ''));

        $customerOptions = '<option value="">選択してください</option>';
        foreach ($customers as $row) {
            $id = (int) ($row['id'] ?? 0);
            $selected = $id === $customerId ? ' selected' : '';
            $label = (string) ($row['customer_name'] ?? '');
            $customerOptions .= '<option value="' . $id . '"' . $selected . '>'
                . Layout::escape($label . ' (#' . $id . ')')
                . '</option>';
        }

        $contractOptions = '<option value="">未設定</option>';
        foreach ($contracts as $row) {
            $id = (int) ($row['id'] ?? 0);
            $selected = $id === $contractId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $customerName = (string) ($row['customer_name'] ?? '');
            $contractOptions .= '<option value="' . $id . '"' . $selected . '>'
                . Layout::escape($policyNoText . ' / ' . $customerName . ' (#' . $id . ')')
                . '</option>';
        }

        $renewalOptions = '<option value="">未設定</option>';
        foreach ($renewalCases as $row) {
            $id = (int) ($row['id'] ?? 0);
            $selected = $id === $renewalCaseId ? ' selected' : '';
            $policyNoText = (string) ($row['policy_no'] ?? '');
            $maturityDate = (string) ($row['maturity_date'] ?? '');
            $renewalOptions .= '<option value="' . $id . '"' . $selected . '>'
                . Layout::escape('案件#' . $id . ' / ' . $policyNoText . ' / ' . $maturityDate)
                . '</option>';
        }

        $typeOptions = '';
        foreach ($allowedTypes as $type) {
            $selected = $type === $performanceType ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($type) . '"' . $selected . '>'
                . Layout::escape($type)
                . '</option>';
        }

        $submitLabel = $isUpdate ? '更新する' : '登録する';
        $cancelButton = $isUpdate
            ? ' <a class="btn btn-secondary" href="' . Layout::escape($cancelUrl) . '">編集をキャンセル</a>'
            : '';
        $idHidden = $isUpdate
            ? '<input type="hidden" name="id" value="' . $currentId . '">'
            : '';

        return ''
            . '<form method="post" action="' . Layout::escape($actionUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . $idHidden
            . '<div class="grid">'
            . '<label>顧客<span style="color:#c92a2a;">*</span><select name="customer_id" required>' . $customerOptions . '</select></label>'
            . '<label>契約<select name="contract_id">' . $contractOptions . '</select></label>'
            . '<label>満期案件<select name="renewal_case_id">' . $renewalOptions . '</select></label>'
            . '<label>実績計上日<span style="color:#c92a2a;">*</span><input type="date" name="performance_date" value="' . $performanceDate . '" required></label>'
            . '<label>実績区分<span style="color:#c92a2a;">*</span><select name="performance_type" required>' . $typeOptions . '</select></label>'
            . '<label>保険種類<input type="text" name="insurance_category" value="' . $insuranceCategory . '"></label>'
            . '<label>種目<input type="text" name="product_type" value="' . $productType . '"></label>'
            . '<label>保険料<span style="color:#c92a2a;">*</span><input type="number" min="0" step="1" name="premium_amount" value="' . $premiumAmount . '" required></label>'
            . '<label>領収証番号<input type="text" name="receipt_no" value="' . $receiptNo . '"></label>'
            . '<label>精算月<input type="month" name="settlement_month" value="' . $settlementMonth . '"></label>'
            . '<label>担当者ID<input type="number" min="1" step="1" name="staff_user_id" value="' . $staffUserId . '"></label>'
            . '<label style="grid-column:1/-1;">備考<textarea name="remark" rows="3" style="width:100%;">' . $remark . '</textarea></label>'
            . '</div>'
            . '<div style="margin-top:12px;">'
            . '<button class="btn" type="submit">' . $submitLabel . '</button>'
            . $cancelButton
            . '</div>'
            . '</form>';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function renderRows(array $rows, string $listUrl, string $deleteUrl, string $deleteCsrf): string
    {
        $rowsHtml = '';

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $editUrl = Layout::escape($listUrl . '&edit_id=' . $id);

            $rowsHtml .= '<tr>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;white-space:nowrap;">'
                . '<a class="btn" href="' . $editUrl . '">編集</a> '
                . '<form method="post" action="' . Layout::escape($deleteUrl) . '" style="display:inline;" onsubmit="return confirm(\'この実績を削除します。よろしいですか？\');">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($deleteCsrf) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<button class="btn btn-secondary" type="submit">削除</button>'
                . '</form>'
                . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['performance_date'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['customer_name'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['policy_no'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['premium_amount'] ?? '0')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['settlement_month'] ?? '')) . '</td>'
                . '<td style="border-bottom:1px solid #eef2f6;padding:8px;">' . Layout::escape((string) ($row['remark'] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="8" style="padding:8px;">該当データはありません。</td></tr>';
        }

        return $rowsHtml;
    }
}