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
     * @param array<int, array<string, mixed>> $salesCases
     * @param array<int, array{id: int, name: string}> $staffUsers
     * @param array<string, mixed>|null $editDraft
     * @param array<int, string> $editErrors
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $detail,
        array $contracts,
        array $activities,
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
        array $staffUsers,
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

        // 活動履歴タイムライン（直近5件）
        $timelineHtml = '';
        foreach ($activities as $row) {
            $subject = trim((string) ($row['subject'] ?? '')) ?: trim((string) ($row['activity_type'] ?? ''));
            $actDate = trim((string) ($row['activity_date'] ?? ''));
            $staffId = (int) ($row['staff_user_id'] ?? 0);
            $staffName = '';
            foreach ($staffUsers as $u) {
                if ((int) ($u['id'] ?? 0) === $staffId) {
                    $staffName = (string) ($u['name'] ?? '');
                    break;
                }
            }
            $timelineHtml .= '<div class="timeline-item">'
                . '<div class="timeline-dot done"></div>'
                . '<div class="timeline-body">'
                . '<div style="font-size:12.5px;font-weight:500;">' . Layout::escape($subject) . '</div>'
                . '<div class="timeline-time">' . Layout::escape($actDate) . ($staffName !== '' ? '&nbsp;&nbsp;' . Layout::escape($staffName) : '') . '</div>'
                . '</div>'
                . '</div>';
        }
        if ($timelineHtml === '') {
            $timelineHtml = '<div style="font-size:12.5px;color:var(--text-secondary);">活動履歴はありません。</div>';
        }
        $activityListUrl = Layout::escape($activityListBaseUrl . '&customer_id=' . $customerId);

        $address = trim((string) (($detail['address1'] ?? '') . ' ' . ($detail['address2'] ?? '')));

        // 編集フォーム初期値（draft があれば draft 優先）
        $d = $editDraft ?? $detail;
        $editErrorsHtml = '';
        if ($editErrors !== []) {
            $editErrorsHtml = '<div class="error" style="margin-bottom:12px;">' . Layout::escape(implode(' ', $editErrors)) . '</div>';
        }

        $draftType   = Layout::escape((string) ($d['customer_type'] ?? ''));
        $draftName   = Layout::escape((string) ($d['customer_name'] ?? ''));
        $draftKana   = Layout::escape((string) ($d['customer_name_kana'] ?? ''));
        $draftPhone  = Layout::escape((string) ($d['phone'] ?? ''));
        $draftEmail  = Layout::escape((string) ($d['email'] ?? ''));
        $draftPostal = Layout::escape((string) ($d['postal_code'] ?? ''));
        $draftAddr1  = Layout::escape((string) ($d['address1'] ?? ''));
        $draftAddr2  = Layout::escape((string) ($d['address2'] ?? ''));
        $draftNote   = Layout::escape((string) ($d['note'] ?? ''));
        $draftUserId = (int) ($d['assigned_user_id'] ?? 0);

        $typeOptions = '';
        foreach (['individual' => '個人', 'corporate' => '法人'] as $val => $label) {
            $selected = $draftType === $val ? ' selected' : '';
            $typeOptions .= '<option value="' . Layout::escape($val) . '"' . $selected . '>' . Layout::escape($label) . '</option>';
        }

        $userOptions = '<option value="">（未設定）</option>';
        foreach ($staffUsers as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $uname = Layout::escape((string) ($u['name'] ?? ''));
            $selected = $draftUserId === $uid ? ' selected' : '';
            $userOptions .= '<option value="' . $uid . '"' . $selected . '>' . $uname . '</option>';
        }

        $openModal = (string) ($_GET['open_modal'] ?? '');
        $openDialog = ($openModal === 'edit' || $editDraft !== null) ? 'true' : 'false';

        $content = $errorHtml
            . $successHtml
            . '<div class="page-header">'
            . '<div><h1 class="title">顧客詳細</h1>' . self::renderCustomerStatus((string) ($detail['status'] ?? '')) . '</div>'
            . '<div class="actions">'
            . '<a class="btn btn-secondary" href="' . Layout::escape($returnTo) . '">' . Layout::escape(self::resolveBackLabel($returnTo)) . '</a>'
            . '<button class="btn" type="button" onclick="document.getElementById(\'customer-edit-dialog\').showModal()">基本情報を編集</button>'
            . '</div>'
            . '</div>'
            . '<div class="two-col">'
            // ── 左カラム ──
            . '<div>'
            . '<div class="card">'
            . '<div class="detail-section-title">基本情報</div>'
            . '<div class="kv"><span class="kv-key">顧客名</span><span class="kv-val">' . Layout::escape((string) ($detail['customer_name'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">よみがな</span><span class="kv-val">' . Layout::escape((string) ($detail['customer_name_kana'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">顧客種別</span><span class="kv-val">' . Layout::escape((string) ($detail['customer_type'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">担当者</span><span class="kv-val">' . (trim((string) ($detail['assigned_user_name'] ?? '')) !== '' ? Layout::escape((string) ($detail['assigned_user_name'] ?? '')) : '<span class="muted">未設定</span>') . '</span></div>'
            . '<div class="kv"><span class="kv-key">電話</span><span class="kv-val">' . Layout::escape((string) ($detail['phone'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">メール</span><span class="kv-val">' . Layout::escape((string) ($detail['email'] ?? '')) . '</span></div>'
            . '<div class="kv"><span class="kv-key">住所</span><span class="kv-val">' . Layout::escape($address) . '</span></div>'
            . '<div class="kv"><span class="kv-key">備考</span><span class="kv-val">' . Layout::escape((string) ($detail['note'] ?? '')) . '</span></div>'
            . '</div>'
            . '<div class="card">'
            . '<div class="detail-section-title">対応履歴（直近）</div>'
            . '<div class="status-timeline">' . $timelineHtml . '</div>'
            . '<a class="btn" href="' . $activityListUrl . '" style="width:100%;display:block;margin-top:8px;text-align:center;">活動履歴を全て見る</a>'
            . '</div>'
            . '</div>'
            // ── 右カラム ──
            . '<div class="card">'
            . '<div class="detail-section-title">保有契約一覧（クリックで満期詳細へ）</div>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed">'
            . '<thead><tr><th>証券番号</th><th>種目</th><th>満期日</th><th>対応状況</th></tr></thead>'
            . '<tbody>' . $contractsHtml . '</tbody>'
            . '</table>'
            . '</div>'
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
            . '<h2 class="modal-title">基本情報を編集</h2>'
            . $editErrorsHtml
            . '<div class="customer-create-grid">'
            . '<label class="form-field form-field--required"><span class="form-field-label">顧客区分</span>'
            . '<select name="customer_type" required>'
            . '<option value="">選択してください</option>'
            . $typeOptions
            . '</select></label>'
            . '<label class="form-field form-field--required"><span class="form-field-label">顧客名</span>'
            . '<input type="text" name="customer_name" value="' . $draftName . '" required maxlength="200"></label>'
            . '<label class="form-field"><span class="form-field-label">顧客名カナ</span>'
            . '<input type="text" name="customer_name_kana" value="' . $draftKana . '" maxlength="200"></label>'
            . '<label class="form-field"><span class="form-field-label">電話番号</span>'
            . '<input type="text" name="phone" value="' . $draftPhone . '" maxlength="30"></label>'
            . '<label class="form-field"><span class="form-field-label">メールアドレス</span>'
            . '<input type="email" name="email" value="' . $draftEmail . '" maxlength="255"></label>'
            . '<label class="form-field"><span class="form-field-label">郵便番号</span>'
            . '<input type="text" name="postal_code" value="' . $draftPostal . '" maxlength="20"></label>'
            . '<label class="form-field"><span class="form-field-label">住所1</span>'
            . '<input type="text" name="address1" value="' . $draftAddr1 . '" maxlength="255"></label>'
            . '<label class="form-field"><span class="form-field-label">住所2</span>'
            . '<input type="text" name="address2" value="' . $draftAddr2 . '" maxlength="255"></label>'
            . '<label class="form-field"><span class="form-field-label">主担当者</span>'
            . '<select name="assigned_user_id">' . $userOptions . '</select></label>'
            . '<div class="form-field form-field--spacer" aria-hidden="true"></div>'
            . '<label class="form-field form-field--full"><span class="form-field-label">備考</span>'
            . '<textarea name="note" rows="4" maxlength="2000">' . $draftNote . '</textarea></label>'
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
