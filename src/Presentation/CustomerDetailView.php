<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class CustomerDetailView
{
    /**
     * @param array<string, mixed> $detail
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $activities
     * @param array<int, array<string, mixed>> $accidentCases
     * @param array<int, array<string, mixed>> $salesCases
     * @param array<string, mixed>|null $editDraft
     * @param array<int, string> $editErrors
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $detail,
        array $contracts,
        array $activities,
        array $accidentCases,
        array $salesCases,
        string $listUrl,
        string $detailUrl,
        string $returnTo,
        string $renewalDetailBaseUrl,
        string $activityListBaseUrl,
        string $activityDetailBaseUrl,
        string $salesCaseDetailBaseUrl,
        string $updateUrl,
        string $editCsrf,
        ?array $editDraft,
        array $editErrors,
        ?string $errorMessage,
        ?string $successMessage,
        array $layoutOptions
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $successHtml = '';
        if (is_string($successMessage) && $successMessage !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($successMessage) . '</div>';
        }

        $customerId = (int) ($detail['id'] ?? 0);
        $today = date('Y-m-d');

        // 保有契約テーブル
        $contractsHtml = '';
        foreach ($contracts as $row) {
            $renewalCaseId = (int) ($row['renewal_case_id'] ?? 0);
            $policyNo = Layout::escape((string) ($row['policy_no'] ?? ''));
            $endDate = (string) ($row['policy_end_date'] ?? '');
            $endDateStyle = $endDate !== '' && $endDate < $today ? ' style="color:var(--text-danger);font-weight:500;"' : '';

            if ($renewalCaseId > 0) {
                $renewalUrl = Layout::escape($renewalDetailBaseUrl . '&id=' . $renewalCaseId);
                $policyCell = '<a class="text-link" href="' . $renewalUrl . '">' . $policyNo . '</a>';
            } else {
                $policyCell = $policyNo;
            }

            $contractsHtml .= '<tr>'
                . '<td>' . $policyCell . '</td>'
                . '<td>' . Layout::escape((string) ($row['product_type'] ?? '')) . '</td>'
                . '<td' . $endDateStyle . '>' . Layout::escape($endDate) . '</td>'
                . '<td>' . self::renderContractStatus((string) ($row['case_status'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($contractsHtml === '') {
            $contractsHtml = '<tr><td colspan="4">保有契約はありません。</td></tr>';
        }

        // 事故案件テーブル
        $accidentHtml = '';
        foreach ($accidentCases as $row) {
            $accidentHtml .= '<tr>'
                . '<td>' . Layout::escape((string) ($row['accepted_date'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['accident_no'] ?? '')) . '</td>'
                . '<td>' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '</td>'
                . '<td>' . self::renderAccidentStatus((string) ($row['status'] ?? '')) . '</td>'
                . '</tr>';
        }
        if ($accidentHtml === '') {
            $accidentHtml = '<tr><td colspan="4">事故案件はありません。</td></tr>';
        }

        // 活動履歴タイムライン（直近5件）
        $timelineHtml = '';
        foreach ($activities as $row) {
            $subject = trim((string) ($row['subject'] ?? '')) ?: trim((string) ($row['activity_type'] ?? ''));
            $actDate = trim((string) ($row['activity_date'] ?? ''));
            $timelineHtml .= '<div class="timeline-item">'
                . '<div class="timeline-dot done"></div>'
                . '<div class="timeline-body">'
                . '<div style="font-size:12.5px;font-weight:500;">' . Layout::escape($subject) . '</div>'
                . '<div class="timeline-time">' . Layout::escape($actDate) . '</div>'
                . '</div>'
                . '</div>';
        }
        if ($timelineHtml === '') {
            $timelineHtml = '<div style="font-size:12.5px;color:var(--text-secondary);">活動履歴はありません。</div>';
        }
        $activityListUrl = Layout::escape($activityListBaseUrl . '&customer_id=' . $customerId);

        // 編集フォーム初期値（draft があれば draft 優先）
        $d = $editDraft ?? $detail;
        $editErrorsHtml = '';
        if ($editErrors !== []) {
            $editErrorsHtml = '<div class="error" style="margin-bottom:12px;">' . Layout::escape(implode(' ', $editErrors)) . '</div>';
        }

        $draftNote   = Layout::escape((string) ($d['note'] ?? ''));

        $openModal = (string) ($_GET['open_modal'] ?? '');
        $openDialog = ($openModal === 'edit' || $editDraft !== null) ? 'true' : 'false';

        $content = $errorHtml
            . $successHtml
            . '<div class="page-header">'
            . '<div><h1 class="title">顧客詳細</h1>' . self::renderCustomerStatus((string) ($detail['status'] ?? '')) . '</div>'
            . '<div class="actions">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($returnTo) . '">' . Layout::escape(self::resolveBackLabel($returnTo)) . '</a>'
            . '</div>'
            . '</div>'
            // ── 左カラム ──
            . '<div>'
            . '<div class="card">'
            . '<div class="detail-section-title" style="display:flex;justify-content:space-between;align-items:center;">基本情報<button type="button" class="btn btn-secondary" style="font-size:12px;padding:3px 10px;" onclick="(function(){var d=document.getElementById(\'customer-edit-dialog\');if(d&&typeof d.showModal===\'function\'&&!d.open){d.showModal();}})()">備考を編集</button></div>'
            . '<div class="kv"><span class="kv-key">顧客名</span><span class="kv-val">' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">顧客種別</span><span class="kv-val">' . Layout::escape(self::formatCustomerType((string) ($detail['customer_type'] ?? ''))) . '</span></div>'
            . '<div class="kv"><span class="kv-key">備考</span><span class="kv-val" style="white-space:pre-wrap;">' . Layout::escape((string) ($detail['note'] ?? '')) . '</span></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">保有契約 / 満期</div>'
            . '<div class="table-wrap"><table class="table-fixed table-card list-table" style="margin:0;">'
            . '<colgroup><col style="width:auto;"><col style="width:80px;"><col style="width:90px;"><col style="width:90px;"></colgroup>'
            . '<thead><tr><th>証券番号</th><th>種目</th><th>満期日</th><th>状態</th></tr></thead>'
            . '<tbody>' . $contractsHtml . '</tbody>'
            . '</table></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">事故案件</div>'
            . '<div class="table-wrap"><table class="table-fixed table-card list-table" style="margin:0;">'
            . '<colgroup><col style="width:90px;"><col style="width:auto;"><col style="width:80px;"><col style="width:80px;"></colgroup>'
            . '<thead><tr><th>受付日</th><th>事故番号</th><th>保険種類</th><th>状態</th></tr></thead>'
            . '<tbody>' . $accidentHtml . '</tbody>'
            . '</table></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">対応履歴（直近）</div>'
            . '<div class="status-timeline">' . $timelineHtml . '</div>'
            . '<a class="btn" href="' . $activityListUrl . '" style="width:100%;display:block;margin-top:8px;text-align:center;">活動履歴を全て見る</a>'
            . '</div>'
            . '</div>'
            /* 見込案件セクション: H-5判断#8 により非表示（コードは保持）
            . '<details class="card details-panel details-compact">'
            . '<summary><span>見込案件</span><span class="muted">' . count($salesCases) . '件</span></summary>'
            . '<div class="details-compact-body"><ul class="panel-list" style="list-style:none;padding:0;margin:0;"></ul></div>'
            . '</details>'
            */
            // ── 編集ダイアログ ──
            . '<dialog id="customer-edit-dialog" class="modal-dialog modal-dialog-wide">'
            . '<form method="dialog" class="modal-close-form"><button type="submit" class="modal-close" aria-label="閉じる">×</button></form>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '" id="customer-edit-form" class="customer-create-form">'
            . self::renderRouteInput($updateUrl)
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($editCsrf) . '">'
            . '<input type="hidden" name="id" value="' . $customerId . '">'
            . '<h2 class="modal-title">備考を編集</h2>'
            . $editErrorsHtml
            . '<div class="customer-create-grid">'
            . '<label class="form-field form-field--full"><span class="form-field-label">備考</span>'
            . '<textarea name="note" rows="6" maxlength="2000">' . $draftNote . '</textarea></label>'
            . '</div>'
            . '<div class="dialog-actions">'
            . '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'customer-edit-dialog\').close()">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">保存する</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>'
            . '(function(){'
            . 'const dlg=document.getElementById("customer-edit-dialog");'
            . 'if(!dlg)return;'
            . 'dlg.addEventListener("click",function(e){const r=dlg.getBoundingClientRect();const inside=r.left<=e.clientX&&e.clientX<=r.right&&r.top<=e.clientY&&e.clientY<=r.bottom;if(!inside&&dlg.open){dlg.close();}});'
            . 'if(' . $openDialog . '&&typeof dlg.showModal==="function"&&!dlg.open){dlg.showModal();}'
            . '})();'
            . '</script>';

        return Layout::render('顧客詳細', $content, $layoutOptions);
    }

    private static function formatCustomerType(string $type): string
    {
        return match ($type) {
            'individual' => '個人',
            'corporate'  => '法人',
            default      => $type,
        };
    }

    private static function renderCustomerStatus(string $status): string
    {
        $label = match ($status) {
            'active'   => '有効',
            'prospect' => '見込',
            'inactive' => '休眠',
            'closed'   => '終了',
            default    => '未設定',
        };
        $class = match ($status) {
            'active'            => 'badge-success',
            'prospect'          => 'badge-info',
            'inactive', 'closed' => 'badge-gray',
            default             => 'badge-danger',
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderContractStatus(string $status): string
    {
        if ($status === '' || $status === '-') {
            return '<span class="muted">未作成</span>';
        }

        [$label, $class] = match ($status) {
            'not_started'    => ['未対応',    'badge-danger'],
            'sj_requested'   => ['SJ依頼中',  'badge-info'],
            'doc_prepared'   => ['書類作成済', 'badge-info'],
            'waiting_return' => ['返送待ち',  'badge-info'],
            'quote_sent'     => ['見積送付済', 'badge-info'],
            'waiting_payment' => ['入金待ち', 'badge-info'],
            'completed'      => ['完了',      'badge-success'],
            default          => ['未設定',    'badge-danger'],
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderAccidentStatus(string $status): string
    {
        [$label, $class] = match ($status) {
            'accepted'     => ['受付済',   'badge-info'],
            'linked'       => ['紐付済',   'badge-info'],
            'in_progress'  => ['対応中',   'badge-warn'],
            'waiting_docs' => ['書類待ち', 'badge-warn'],
            'resolved'     => ['解決済',   'badge-success'],
            'closed'       => ['完了',     'badge-gray'],
            default        => [$status,    'badge-danger'],
        };

        return '<span class="badge ' . $class . '">' . Layout::escape($label) . '</span>';
    }

    private static function renderRouteInput(string $url): string
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        $route = trim((string) ($params['route'] ?? ''));
        if ($route === '') {
            return '';
        }

        return '<input type="hidden" name="route" value="' . Layout::escape($route) . '">';
    }

    private static function resolveBackLabel(string $returnToUrl): string
    {
        if (str_contains($returnToUrl, 'route=renewal/detail'))  return '満期詳細へ戻る';
        if (str_contains($returnToUrl, 'route=sales/detail'))    return '実績詳細へ戻る';
        if (str_contains($returnToUrl, 'route=accident/detail')) return '事故案件詳細へ戻る';
        return '顧客一覧へ戻る';
    }
}
