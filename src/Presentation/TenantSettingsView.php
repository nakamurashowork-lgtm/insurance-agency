<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class TenantSettingsView
{
    /**
     * @param array<string, mixed>              $auth
     * @param array<string, array<string, mixed>> $notifySettings
     * @param array<int, array<string, mixed>>  $phases
     * @param array<string, mixed>              $layoutOptions
     * @param array<int, array<string, mixed>>  $purposeTypes
     * @param array<int, array<string, mixed>>  $sjnetMappings
     * @param array<int, array{id:int,name:string}> $staffUsers
     * @param array<int, array<string, mixed>>  $salesTargets
     * @param array<string, string>             $masterCsrfs
     * @param array<string, string>             $masterUrls
     */
    public static function render(
        array $auth,
        array $notifySettings,
        array $phases,
        string $saveUrl,
        string $csrfToken,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess,
        array $layoutOptions,
        array $purposeTypes = [],
        array $sjnetMappings = [],
        array $staffUsers = [],
        array $salesTargets = [],
        int $currentFiscalYear = 0,
        array $masterCsrfs = [],
        array $masterUrls = []
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $renewal = $notifySettings['renewal'] ?? [];
        $accident = $notifySettings['accident'] ?? [];

        $notifyContent = self::renderNotifyForm(
            $renewal,
            $accident,
            $phases,
            $saveUrl,
            $csrfToken
        );

        $tenantName = Layout::escape((string) ($auth['tenant_name'] ?? ''));
        $tenantCode = Layout::escape((string) ($auth['tenant_code'] ?? ''));

        $content = ''
            . '<div style="margin-bottom:16px;">'
            . '<h1 class="title">管理・設定</h1>'
            . '<p class="muted" style="margin:0;">対象代理店: ' . $tenantName . ' (' . $tenantCode . ')</p>'
            . '</div>'
            . $errorHtml
            . $successHtml;

        $content .= $notifyContent;

        $content .= self::renderPurposeTypeSection($purposeTypes, $masterCsrfs, $masterUrls);
        $content .= self::renderSjnetSection($sjnetMappings, $staffUsers, $masterCsrfs, $masterUrls);
        $content .= self::renderSalesTargetSection($salesTargets, $staffUsers, $currentFiscalYear, $masterCsrfs, $masterUrls);

        return Layout::render('管理・設定', $content, $layoutOptions);
    }

    // ---- 用件区分マスタ ----

    /**
     * @param array<int, array<string, mixed>> $purposeTypes
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderPurposeTypeSection(
        array $purposeTypes,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl     = $masterUrls['purpose_type_create'] ?? '';
        $updateUrl     = $masterUrls['purpose_type_update'] ?? '';
        $deactivateUrl = $masterUrls['purpose_type_deactivate'] ?? '';
        $activateUrl   = $masterUrls['purpose_type_activate'] ?? '';

        $csrfCreate     = $masterCsrfs['purpose_type_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['purpose_type_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['purpose_type_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['purpose_type_activate'] ?? '';

        $rows = '';
        foreach ($purposeTypes as $row) {
            $code         = Layout::escape((string) ($row['code'] ?? ''));
            $label        = Layout::escape((string) ($row['label'] ?? ''));
            $displayOrder = (int) ($row['display_order'] ?? 0);
            $isActive     = (int) ($row['is_active'] ?? 1);
            $rowStyle     = $isActive ? '' : ' style="color:#999;"';

            $activeLabel = $isActive ? '<span class="badge badge-success">有効</span>' : '<span class="badge">無効</span>';

            if ($isActive) {
                $toggleForm = ''
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="code" value="' . $code . '">'
                    . '<button type="submit" class="btn btn-sm" onclick="return confirm(\'無効化しますか？\')">無効化</button>'
                    . '</form>';
            } else {
                $toggleForm = ''
                    . '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="code" value="' . $code . '">'
                    . '<button type="submit" class="btn btn-sm">有効化</button>'
                    . '</form>';
            }

            $editForm = ''
                . '<details style="display:inline;">'
                . '<summary class="btn btn-sm" style="display:inline;cursor:pointer;">編集</summary>'
                . '<div style="background:#f9f9f9;padding:12px;margin-top:8px;border:1px solid #ddd;">'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="code" value="' . $code . '">'
                . '<label style="display:block;margin-bottom:6px;">表示名 <input type="text" name="label" value="' . $label . '" required style="width:200px;"></label>'
                . '<label style="display:block;margin-bottom:6px;">表示順 <input type="number" name="display_order" value="' . $displayOrder . '" min="0" style="width:80px;"></label>'
                . '<button type="submit" class="btn btn-primary btn-sm">更新</button>'
                . '</form>'
                . '</div>'
                . '</details>';

            $rows .= '<tr' . $rowStyle . '>'
                . '<td>' . $code . '</td>'
                . '<td>' . $label . '</td>'
                . '<td style="text-align:center;">' . $displayOrder . '</td>'
                . '<td style="text-align:center;">' . $activeLabel . '</td>'
                . '<td>' . $editForm . ' ' . $toggleForm . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="muted" style="text-align:center;">登録なし</td></tr>';
        }

        $table = ''
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">コード</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">表示名</th>'
            . '<th style="text-align:center;padding:6px 8px;border-bottom:1px solid #ddd;">表示順</th>'
            . '<th style="text-align:center;padding:6px 8px;border-bottom:1px solid #ddd;">状態</th>'
            . '<th style="padding:6px 8px;border-bottom:1px solid #ddd;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';

        $addForm = ''
            . '<details style="margin-top:12px;">'
            . '<summary class="btn btn-sm" style="cursor:pointer;">＋ 追加</summary>'
            . '<div style="background:#f9f9f9;padding:12px;margin-top:8px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<label style="display:block;margin-bottom:6px;">コード（半角英数・_・-）<input type="text" name="code" required pattern="[a-zA-Z0-9_\-]+" style="width:200px;margin-left:8px;"></label>'
            . '<label style="display:block;margin-bottom:6px;">表示名 <input type="text" name="label" required style="width:200px;margin-left:8px;"></label>'
            . '<label style="display:block;margin-bottom:8px;">表示順 <input type="number" name="display_order" value="0" min="0" style="width:80px;margin-left:8px;"></label>'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . '</form>'
            . '</div>'
            . '</details>';

        return ''
            . '<div class="card" style="margin-top:24px;">'
            . '<h2>用件区分マスタ</h2>'
            . '<p class="muted" style="margin:0 0 12px;">活動記録の用件区分を管理します。</p>'
            . $table
            . $addForm
            . '</div>';
    }

    // ---- SJNETコード設定 ----

    /**
     * @param array<int, array<string, mixed>>      $sjnetMappings
     * @param array<int, array{id:int,name:string}> $staffUsers
     * @param array<string, string>                 $masterCsrfs
     * @param array<string, string>                 $masterUrls
     */
    private static function renderSjnetSection(
        array $sjnetMappings,
        array $staffUsers,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl     = $masterUrls['sjnet_create'] ?? '';
        $updateUrl     = $masterUrls['sjnet_update'] ?? '';
        $deactivateUrl = $masterUrls['sjnet_deactivate'] ?? '';

        $csrfCreate     = $masterCsrfs['sjnet_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['sjnet_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['sjnet_deactivate'] ?? '';

        // Build user id->name lookup
        $userMap = [];
        foreach ($staffUsers as $u) {
            $userMap[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? '');
        }

        $userSelectOptions = '<option value="">（選択）</option>';
        foreach ($staffUsers as $u) {
            $uid  = (int) ($u['id'] ?? 0);
            $name = Layout::escape((string) ($u['name'] ?? ''));
            $userSelectOptions .= '<option value="' . $uid . '">' . $name . '</option>';
        }

        $rows = '';
        foreach ($sjnetMappings as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $agencyCode   = Layout::escape((string) ($row['sjnet_agency_code'] ?? ''));
            $staffName    = Layout::escape((string) ($row['sjnet_staff_name'] ?? ''));
            $userId       = (int) ($row['user_id'] ?? 0);
            $userName     = Layout::escape($userMap[$userId] ?? '(不明)');
            $note         = Layout::escape((string) ($row['note'] ?? ''));
            $isActive     = (int) ($row['is_active'] ?? 1);
            $rowStyle     = $isActive ? '' : ' style="color:#999;"';

            $activeLabel = $isActive ? '<span class="badge badge-success">有効</span>' : '<span class="badge">無効</span>';

            $deactivateBtn = '';
            if ($isActive) {
                $deactivateBtn = ''
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<button type="submit" class="btn btn-sm" onclick="return confirm(\'無効化しますか？\')">無効化</button>'
                    . '</form>';
            }

            // Build user select options with current selection
            $userSelectEdit = '<select name="user_id" required>';
            foreach ($staffUsers as $u) {
                $uid  = (int) ($u['id'] ?? 0);
                $name = Layout::escape((string) ($u['name'] ?? ''));
                $sel  = $uid === $userId ? ' selected' : '';
                $userSelectEdit .= '<option value="' . $uid . '"' . $sel . '>' . $name . '</option>';
            }
            $userSelectEdit .= '</select>';

            $editForm = ''
                . '<details style="display:inline;">'
                . '<summary class="btn btn-sm" style="display:inline;cursor:pointer;">編集</summary>'
                . '<div style="background:#f9f9f9;padding:12px;margin-top:8px;border:1px solid #ddd;">'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<label style="display:block;margin-bottom:6px;">代理店コード <input type="text" name="sjnet_agency_code" value="' . $agencyCode . '" required style="width:160px;"></label>'
                . '<label style="display:block;margin-bottom:6px;">SJNETスタッフ名 <input type="text" name="sjnet_staff_name" value="' . $staffName . '" style="width:160px;"></label>'
                . '<label style="display:block;margin-bottom:6px;">担当者 ' . $userSelectEdit . '</label>'
                . '<label style="display:block;margin-bottom:8px;">備考 <input type="text" name="note" value="' . $note . '" style="width:200px;"></label>'
                . '<button type="submit" class="btn btn-primary btn-sm">更新</button>'
                . '</form>'
                . '</div>'
                . '</details>';

            $rows .= '<tr' . $rowStyle . '>'
                . '<td>' . $agencyCode . '</td>'
                . '<td>' . $staffName . '</td>'
                . '<td>' . $userName . '</td>'
                . '<td>' . $note . '</td>'
                . '<td style="text-align:center;">' . $activeLabel . '</td>'
                . '<td>' . $editForm . ' ' . $deactivateBtn . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted" style="text-align:center;">登録なし</td></tr>';
        }

        $table = ''
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">代理店コード</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">SJNETスタッフ名</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">担当者</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">備考</th>'
            . '<th style="text-align:center;padding:6px 8px;border-bottom:1px solid #ddd;">状態</th>'
            . '<th style="padding:6px 8px;border-bottom:1px solid #ddd;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';

        $addForm = ''
            . '<details style="margin-top:12px;">'
            . '<summary class="btn btn-sm" style="cursor:pointer;">＋ 追加</summary>'
            . '<div style="background:#f9f9f9;padding:12px;margin-top:8px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<label style="display:block;margin-bottom:6px;">代理店コード <input type="text" name="sjnet_agency_code" required style="width:160px;margin-left:8px;"></label>'
            . '<label style="display:block;margin-bottom:6px;">SJNETスタッフ名 <input type="text" name="sjnet_staff_name" style="width:160px;margin-left:8px;"></label>'
            . '<label style="display:block;margin-bottom:6px;">担当者 <select name="user_id" required style="margin-left:8px;">' . $userSelectOptions . '</select></label>'
            . '<label style="display:block;margin-bottom:8px;">備考 <input type="text" name="note" style="width:200px;margin-left:8px;"></label>'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . '</form>'
            . '</div>'
            . '</details>';

        return ''
            . '<div class="card" style="margin-top:24px;">'
            . '<h2>SJNETコード設定</h2>'
            . '<p class="muted" style="margin:0 0 12px;">SJNETの代理店コードとシステムユーザーの紐付けを管理します。</p>'
            . $table
            . $addForm
            . '</div>';
    }

    // ---- 目標管理 ----

    /**
     * @param array<int, array<string, mixed>>      $salesTargets
     * @param array<int, array{id:int,name:string}> $staffUsers
     * @param array<string, string>                 $masterCsrfs
     * @param array<string, string>                 $masterUrls
     */
    private static function renderSalesTargetSection(
        array $salesTargets,
        array $staffUsers,
        int $currentFiscalYear,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $saveUrl  = $masterUrls['sales_target_save'] ?? '';
        $csrfSave = $masterCsrfs['sales_target_save'] ?? '';

        $targetTypeLabels = [
            'premium_non_life' => '非生保保険料',
            'premium_life'     => '生保保険料',
            'premium_total'    => '合計保険料',
            'case_count'       => '件数',
        ];

        // Build user id->name lookup
        $userMap = [];
        foreach ($staffUsers as $u) {
            $userMap[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? '');
        }

        $rows = '';
        foreach ($salesTargets as $row) {
            $targetMonth  = $row['target_month'] !== null ? (int) $row['target_month'] : null;
            $staffUserId  = $row['staff_user_id'] !== null ? (int) $row['staff_user_id'] : null;
            $targetType   = (string) ($row['target_type'] ?? '');
            $targetAmount = (int) ($row['target_amount'] ?? 0);

            $monthLabel  = $targetMonth !== null ? $targetMonth . '月' : '通年';
            $staffLabel  = $staffUserId !== null ? Layout::escape($userMap[$staffUserId] ?? '(UID:' . $staffUserId . ')') : 'チーム全体';
            $typeLabel   = Layout::escape($targetTypeLabels[$targetType] ?? $targetType);
            $amountLabel = number_format($targetAmount);

            $rows .= '<tr>'
                . '<td style="padding:4px 8px;">' . $monthLabel . '</td>'
                . '<td style="padding:4px 8px;">' . $staffLabel . '</td>'
                . '<td style="padding:4px 8px;">' . $typeLabel . '</td>'
                . '<td style="padding:4px 8px;text-align:right;">' . $amountLabel . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $table = ''
            . '<p style="margin:0 0 8px;">対象年度: <strong>' . $currentFiscalYear . '年度</strong></p>'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">月</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">対象</th>'
            . '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #ddd;">種別</th>'
            . '<th style="text-align:right;padding:6px 8px;border-bottom:1px solid #ddd;">目標値</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>';

        // target_type select options
        $typeOptions = '';
        foreach ($targetTypeLabels as $val => $lbl) {
            $typeOptions .= '<option value="' . Layout::escape($val) . '">' . Layout::escape($lbl) . '</option>';
        }

        // month select options (blank = annual)
        $monthOptions = '<option value="">通年</option>';
        for ($m = 1; $m <= 12; $m++) {
            $monthOptions .= '<option value="' . $m . '">' . $m . '月</option>';
        }

        // staff select options
        $staffOptions = '<option value="">チーム全体</option>';
        foreach ($staffUsers as $u) {
            $uid  = (int) ($u['id'] ?? 0);
            $name = Layout::escape((string) ($u['name'] ?? ''));
            $staffOptions .= '<option value="' . $uid . '">' . $name . '</option>';
        }

        $upsertForm = ''
            . '<details style="margin-top:12px;">'
            . '<summary class="btn btn-sm" style="cursor:pointer;">目標を設定する</summary>'
            . '<div style="background:#f9f9f9;padding:12px;margin-top:8px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($saveUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfSave) . '">'
            . '<input type="hidden" name="fiscal_year" value="' . $currentFiscalYear . '">'
            . '<label style="display:block;margin-bottom:6px;">月 <select name="target_month" style="margin-left:8px;">' . $monthOptions . '</select></label>'
            . '<label style="display:block;margin-bottom:6px;">対象 <select name="staff_user_id" style="margin-left:8px;">' . $staffOptions . '</select></label>'
            . '<label style="display:block;margin-bottom:6px;">種別 <select name="target_type" style="margin-left:8px;">' . $typeOptions . '</select></label>'
            . '<label style="display:block;margin-bottom:8px;">目標値 <input type="number" name="target_amount" value="0" min="0" required style="width:120px;margin-left:8px;"></label>'
            . '<button type="submit" class="btn btn-primary btn-sm">保存（追加/更新）</button>'
            . '</form>'
            . '</div>'
            . '</details>';

        return ''
            . '<div class="card" style="margin-top:24px;">'
            . '<h2>目標管理</h2>'
            . '<p class="muted" style="margin:0 0 12px;">年度・月別・担当者別の目標値を管理します。</p>'
            . $table
            . $upsertForm
            . '</div>';
    }

    // ---- 通知設定 ----

    /**
     * @param array<string, mixed> $renewal
     * @param array<string, mixed> $accident
     */
    private static function renderNotifyForm(
        array $renewal,
        array $accident,
        array $phases,
        string $saveUrl,
        string $csrfToken
    ): string
    {
        $providers = self::availableProviders();

        $renewalEnabled = ((int) ($renewal['is_enabled'] ?? 0) === 1) ? ' checked' : '';
        $accidentEnabled = ((int) ($accident['is_enabled'] ?? 0) === 1) ? ' checked' : '';

        $renewalProviderOptions = self::providerOptions($providers, (string) ($renewal['provider_type'] ?? 'lineworks'));
        $accidentProviderOptions = self::providerOptions($providers, (string) ($accident['provider_type'] ?? 'lineworks'));

        return ''
            . '<form method="post" action="' . Layout::escape($saveUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<div class="card">'
            . '<h2>通知設定</h2>'
            . '<div class="nav-item" style="margin-bottom:12px;">'
            . '<h3>満期通知</h3>'
            . '<p class="muted" style="margin:0 0 10px;">満期案件の通知先と通知タイミングを設定します。</p>'
            . '<label><input type="checkbox" name="renewal_is_enabled" value="1"' . $renewalEnabled . '> 有効</label>'
            . self::renderProviderField('renewal_provider_type', (string) ($renewal['provider_type'] ?? 'lineworks'), $renewalProviderOptions, $providers)
            . self::renderDestinationNameField('renewal_destination_name', (string) ($renewal['destination_name'] ?? 'renewal_default'))
            . '<label style="display:block;margin-top:8px;">Webhook URL<textarea name="renewal_webhook_url" rows="3" style="width:100%;">' . Layout::escape((string) ($renewal['webhook_url'] ?? '')) . '</textarea></label>'
            . self::renderTimingRows($phases)
            . '</div>'
            . '<div class="nav-item">'
            . '<h3>事故通知</h3>'
            . '<p class="muted" style="margin:0 0 10px;">事故通知の通知先を設定します。</p>'
            . '<label><input type="checkbox" name="accident_is_enabled" value="1"' . $accidentEnabled . '> 有効</label>'
            . self::renderProviderField('accident_provider_type', (string) ($accident['provider_type'] ?? 'lineworks'), $accidentProviderOptions, $providers)
            . self::renderDestinationNameField('accident_destination_name', (string) ($accident['destination_name'] ?? 'accident_default'))
            . '<label style="display:block;margin-top:8px;">Webhook URL<textarea name="accident_webhook_url" rows="3" style="width:100%;">' . Layout::escape((string) ($accident['webhook_url'] ?? '')) . '</textarea></label>'
            . '</div>'
            . '</div>'
            . '<div style="margin-top:20px;"><button class="btn btn-primary" type="submit">保存</button></div>'
            . '</form>';
    }

    /**
     * @param array<int, array<string, mixed>> $phases
     */
    private static function renderTimingRows(array $phases): string
    {
        if ($phases === []) {
            return '';
        }

        $orderedPhases = self::orderPhasesForDisplay($phases);
        $rowsHtml = '';
        foreach ($orderedPhases as $phase) {
            $meta = self::phaseUiMeta($phase);
            $id = (int) ($phase['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $from = Layout::escape((string) ($phase['from_days_before'] ?? '0'));
            $to   = Layout::escape((string) ($phase['to_days_before'] ?? '0'));
            $displayOrder = Layout::escape((string) ($phase['display_order'] ?? '0'));
            $rowsHtml .= ''
                . '<div style="display:flex;align-items:center;gap:8px;margin-top:8px;">'
                . '<span style="min-width:76px;font-weight:700;">' . Layout::escape($meta['label']) . '</span>'
                . '<input type="number" min="0" name="phases[' . $id . '][from_days_before]" value="' . $from . '" style="width:70px;padding:4px 8px;">'
                . '<span>日前　〜</span>'
                . '<input type="number" min="0" name="phases[' . $id . '][to_days_before]" value="' . $to . '" style="width:70px;padding:4px 8px;">'
                . '<span>日前</span>'
                . '<input type="hidden" name="phases[' . $id . '][display_order]" value="' . $displayOrder . '">'
                . '</div>';
        }

        if ($rowsHtml === '') {
            return '';
        }

        return ''
            . '<div style="margin-top:16px;">'
            . '<h4 style="margin:0 0 8px;">通知タイミング</h4>'
            . $rowsHtml
            . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $phases
     * @return array<int, array<string, mixed>>
     */
    private static function orderPhasesForDisplay(array $phases): array
    {
        usort($phases, static function (array $a, array $b): int {
            $priority = [
                'PH5' => 10,
                'PH6' => 20,
            ];

            $aCode = (string) ($a['phase_code'] ?? '');
            $bCode = (string) ($b['phase_code'] ?? '');

            $aPriority = $priority[$aCode] ?? 100;
            $bPriority = $priority[$bCode] ?? 100;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            return ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));
        });

        return $phases;
    }

    /**
     * @param array<string, mixed> $phase
     * @return array{label:string,description:string}
     */
    private static function phaseUiMeta(array $phase): array
    {
        $phaseCode = (string) ($phase['phase_code'] ?? '');

        return match ($phaseCode) {
            'PH5' => [
                'label' => '早期通知',
                'description' => '満期日の90日前から30日前まで通知',
            ],
            'PH6' => [
                'label' => '直前通知',
                'description' => '満期日の30日前から当日まで通知',
            ],
            default => [
                'label' => '通知タイミング',
                'description' => '満期通知の送信タイミングを設定',
            ],
        };
    }

    /**
     * @param array<int, string> $providers
     */
    private static function providerOptions(array $providers, string $current): string
    {
        $html = '';
        foreach ($providers as $provider) {
            $selected = $provider === $current ? ' selected' : '';
            $html .= '<option value="' . Layout::escape($provider) . '"' . $selected . '>' . Layout::escape($provider) . '</option>';
        }

        return $html;
    }

    /**
     * @return array<int, string>
     */
    private static function availableProviders(): array
    {
        return ['lineworks'];
    }

    /**
     * @param array<int, string> $providers
     */
    private static function renderProviderField(string $fieldName, string $current, string $optionsHtml, array $providers): string
    {
        $provider = $current !== '' ? $current : 'lineworks';

        if (count($providers) > 1) {
            return '<label style="display:block;margin-top:8px;">通知先種別<select name="' . Layout::escape($fieldName) . '">' . $optionsHtml . '</select></label>';
        }

        return '<div style="display:block;margin-top:8px;">通知先: ' . self::providerLabel($provider) . '</div>'
            . '<input type="hidden" name="' . Layout::escape($fieldName) . '" value="' . Layout::escape($provider) . '">';
    }

    private static function renderDestinationNameField(string $fieldName, string $current): string
    {
        return '<input type="hidden" name="' . Layout::escape($fieldName) . '" value="' . Layout::escape($current) . '">';
    }

    private static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'lineworks' => 'LINE WORKS',
            'google_chat' => 'Google Chat',
            'slack' => 'Slack',
            'teams' => 'Microsoft Teams',
            default => strtoupper(str_replace('_', ' ', $provider)),
        };
    }
}
