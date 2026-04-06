<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class TenantSettingsView
{
    /**
     * @param array<string, mixed>                $auth
     * @param array<string, array<string, mixed>> $notifySettings
     * @param array<int, array<string, mixed>>    $phases
     * @param array<string, mixed>                $layoutOptions
     * @param array<int, array<string, mixed>>    $purposeTypes
     * @param array<int, array<string, mixed>>    $staffMappings
     * @param array<int, array<string, mixed>>    $renewalCaseStatuses
     * @param array<int, array<string, mixed>>    $productCategories
     * @param array<string, string>               $masterCsrfs
     * @param array<string, string>               $masterUrls
     * @param array<int, array<string, mixed>>    $accidentCaseStatuses
     * @param array<int, array<string, mixed>>    $procedureMethods
     */
    public static function render(
        array $auth,
        array $notifySettings,
        array $phases,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess,
        array $layoutOptions,
        array $purposeTypes = [],
        array $staffMappings = [],
        array $renewalCaseStatuses = [],
        array $productCategories = [],
        array $masterCsrfs = [],
        array $masterUrls = [],
        array $accidentCaseStatuses = [],
        array $tenantUsers = [],
        array $procedureMethods = []
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

        $renewal  = $notifySettings['renewal']  ?? [];
        $accident = $notifySettings['accident'] ?? [];

        $tenantName = Layout::escape((string) ($auth['tenant_name'] ?? ''));
        $tenantCode = Layout::escape((string) ($auth['tenant_code'] ?? ''));

        // Tab bar
        $tabBar = ''
            . '<div class="tab-bar">'
            . '<div class="tab active" data-tab="staff" onclick="showSettingsTab(\'staff\',this)">担当者マスタ</div>'
            . '<div class="tab" data-tab="category" onclick="showSettingsTab(\'category\',this)">種目マスタ</div>'
            . '<div class="tab" data-tab="status" onclick="showSettingsTab(\'status\',this)">対応状況マスタ</div>'
            . '<div class="tab" data-tab="procedure" onclick="showSettingsTab(\'procedure\',this)">手続方法マスタ</div>'
            . '<div class="tab" data-tab="purpose" onclick="showSettingsTab(\'purpose\',this)">用件区分</div>'
            . '<div class="tab" data-tab="notify" onclick="showSettingsTab(\'notify\',this)">通知設定</div>'
            . '<div class="tab" data-tab="users" onclick="showSettingsTab(\'users\',this)">ユーザー管理</div>'
            . '</div>';

        // Tab panels
        $panelStaff    = '<div id="settings-panel-staff" class="settings-panel">'
            . self::renderStaffSection($staffMappings, $masterCsrfs, $masterUrls, $tenantUsers)
            . '</div>';
        $panelCategory = '<div id="settings-panel-category" class="settings-panel" style="display:none;">'
            . self::renderCategorySection($productCategories, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelStatus   = '<div id="settings-panel-status" class="settings-panel" style="display:none;">'
            . self::renderStatusSection($renewalCaseStatuses, $accidentCaseStatuses, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelProcedure = '<div id="settings-panel-procedure" class="settings-panel" style="display:none;">'
            . self::renderProcedureMethodSection($procedureMethods, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelPurpose  = '<div id="settings-panel-purpose" class="settings-panel" style="display:none;">'
            . self::renderPurposeTypeSection($purposeTypes, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelNotify   = '<div id="settings-panel-notify" class="settings-panel" style="display:none;">'
            . self::renderNotifyForm($renewal, $accident, $phases, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelUsers    = '<div id="settings-panel-users" class="settings-panel" style="display:none;">'
            . self::renderUserSection($tenantUsers, $masterCsrfs, $masterUrls)
            . '</div>';

        // Tab JS — classList approach; call initCategoryFilter when switching to category
        $tabJs = ''
            . '<script>'
            . 'function showSettingsTab(name,tabEl){'
            . 'document.querySelectorAll(".settings-panel").forEach(function(p){p.style.display="none";});'
            . 'var panel=document.getElementById("settings-panel-"+name);'
            . 'if(panel){panel.style.display="";}'
            . 'document.querySelectorAll(".tab").forEach(function(t){t.classList.remove("active");});'
            . 'if(tabEl){tabEl.classList.add("active");}'
            . 'if(name==="category"&&typeof initCategoryFilter==="function"){initCategoryFilter();}'
            . '}'
            . '(function(){'
            . 'var tab=new URLSearchParams(location.search).get("tab");'
            . 'if(!tab)return;'
            . 'var tabEl=document.querySelector(".tab[data-tab=\'"+tab+"\']");'
            . 'if(tabEl){showSettingsTab(tab,tabEl);}'
            . '})();'
            . '</script>';

        $content = ''
            . '<div class="page-header">'
            . '<h1 class="title">テナント設定</h1>'
            . '<span class="badge badge-warn">管理者のみ</span>'
            . '</div>'
            . '<p class="muted" style="margin:0 0 12px;">対象代理店: ' . $tenantName . ' (' . $tenantCode . ')</p>'
            . $errorHtml
            . $successHtml
            . $tabBar
            . $panelStaff
            . $panelCategory
            . $panelStatus
            . $panelProcedure
            . $panelPurpose
            . $panelNotify
            . $panelUsers
            . $tabJs;

        return Layout::render('テナント設定', $content, $layoutOptions);
    }

    // ---- 担当者マスタ ----

    /**
     * @param array<int, array<string, mixed>> $staffMappings
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     * @param array<int, array<string, mixed>> $tenantUsers
     */
    private static function renderStaffSection(
        array $staffMappings,
        array $masterCsrfs,
        array $masterUrls,
        array $tenantUsers = []
    ): string {
        $createUrl = $masterUrls['staff_create'] ?? '';
        $updateUrl = $masterUrls['staff_update'] ?? '';
        $deleteUrl = $masterUrls['staff_delete'] ?? '';

        $csrfCreate = $masterCsrfs['staff_create'] ?? '';
        $csrfUpdate = $masterCsrfs['staff_update'] ?? '';
        $csrfDelete = $masterCsrfs['staff_delete'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($staffMappings as $row) {
            $id        = (int) ($row['id'] ?? 0);
            $sjnetCode = Layout::escape((string) ($row['sjnet_code'] ?? ''));
            $staffName = Layout::escape((string) ($row['staff_name'] ?? ''));
            $isActive  = (int) ($row['is_active'] ?? 1);
            $isSales   = (int) ($row['is_sales'] ?? 1);
            $isOffice  = (int) ($row['is_office'] ?? 0);
            $sortOrder = (int) ($row['sort_order'] ?? 0);
            $linkedUserId = (int) ($row['user_id'] ?? 0);
            $dlgId     = 'dlg-staff-' . $id;

            $activeBadge = $isActive ? '<span class="badge badge-success">有効</span>' : '<span class="badge badge-gray">無効</span>';
            $roleLabel   = ($isSales ? '営業' : '') . ($isSales && $isOffice ? '/' : '') . ($isOffice ? '事務' : '');

            $dialogs .= ''
                . '<dialog id="' . $dlgId . '">'
                . '<div class="dlg-title">担当者を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="staff">'
                . '<div class="form-row"><div class="form-label">担当者名 *</div>'
                . '<input type="text" name="staff_name" value="' . $staffName . '" required class="form-input"></div>'
                . '<div class="form-row"><div class="form-label">代理店コード</div>'
                . '<input type="text" name="sjnet_code" value="' . $sjnetCode . '" class="form-input" placeholder="未設定の場合は空欄"></div>'
                . '<div class="form-row"><div class="form-label">ロール</div>'
                . '<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><input type="checkbox" name="is_sales" value="1"' . ($isSales ? ' checked' : '') . '> 営業担当</label>'
                . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_office" value="1"' . ($isOffice ? ' checked' : '') . '> 事務担当</label></div>'
                . '<div class="form-row"><div class="form-label">有効</div>'
                . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1"' . ($isActive ? ' checked' : '') . '> 有効にする</label></div>'
                . '<div class="form-row"><div class="form-label">表示順</div>'
                . '<input type="number" name="sort_order" value="' . $sortOrder . '" class="form-input" min="0" style="width:80px;"></div>'
                . '<div class="form-row"><div class="form-label">アカウント連携</div>'
                . '<select name="user_id" class="form-input">'
                . self::buildUserOptions($tenantUsers, $linkedUserId)
                . '</select></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            $rows .= '<tr>'
                . '<td>' . $staffName . '</td>'
                . '<td>' . $sjnetCode . '</td>'
                . '<td>' . Layout::escape($roleLabel !== '' ? $roleLabel : '-') . '</td>'
                . '<td>' . $activeBadge . '</td>'
                . '<td><div style="display:flex;gap:4px;">'
                . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                . '<form method="post" action="' . Layout::escape($deleteUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDelete) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="staff">'
                . '<button type="submit" class="btn btn-danger" style="padding:3px 10px;font-size:11px;" onclick="return confirm(\'削除しますか？\')">削除</button>'
                . '</form>'
                . '</div></td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $table = ''
            . '<div class="tbl-wrap"><table class="list-table">'
            . '<thead><tr>'
            . '<th>担当者名</th>'
            . '<th>代理店コード</th>'
            . '<th>ロール</th>'
            . '<th>有効</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" id="staff-add-btn" style="margin-top:12px;"'
            . ' onclick="this.style.display=\'none\';document.getElementById(\'staff-add-form\').style.display=\'block\';">+ 担当者を追加</button>';

        $addForm = ''
            . '<div id="staff-add-form" style="display:none;margin-top:12px;background:#f9f9f9;padding:12px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="staff">'
            . '<div class="form-row"><div class="form-label">担当者名 *</div>'
            . '<input type="text" name="staff_name" required class="form-input"></div>'
            . '<div class="form-row"><div class="form-label">代理店コード</div>'
            . '<input type="text" name="sjnet_code" class="form-input" placeholder="未設定の場合は空欄"></div>'
            . '<div class="form-row"><div class="form-label">ロール</div>'
            . '<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><input type="checkbox" name="is_sales" value="1" checked> 営業担当</label>'
            . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_office" value="1"> 事務担当</label></div>'
            . '<div class="form-row"><div class="form-label">有効</div>'
            . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" checked> 有効にする</label></div>'
            . '<div class="form-row"><div class="form-label">表示順</div>'
            . '<input type="number" name="sort_order" value="0" class="form-input" min="0" style="width:80px;"></div>'
            . '<div class="form-row"><div class="form-label">アカウント連携</div>'
            . '<select name="user_id" class="form-input">'
            . self::buildUserOptions($tenantUsers, 0)
            . '</select></div>'
            . '<div style="margin-top:8px;">'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . ' <button type="button" class="btn btn-sm" style="margin-left:4px;"'
            . ' onclick="document.getElementById(\'staff-add-form\').style.display=\'none\';document.getElementById(\'staff-add-btn\').style.display=\'inline-block\';">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</div>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">担当者マスタ</div>'
            . '<p class="muted" style="margin-bottom:12px;font-size:12.5px;">担当者マスタを管理します。代理店コードを設定すると満期一覧CSV取り込み時に自動マッピングされます。</p>'
            . $table
            . $addBtn
            . $addForm
            . '</div>'
            . $dialogs;
    }

    /**
     * @param array<int, array<string, mixed>> $tenantUsers
     */
    private static function buildUserOptions(array $tenantUsers, int $selectedId): string
    {
        $html = '<option value="">紐づけなし</option>';
        foreach ($tenantUsers as $u) {
            $uid      = (int) ($u['id'] ?? 0);
            $uName    = Layout::escape((string) ($u['display_name'] ?? $u['name'] ?? ''));
            $selected = ($uid === $selectedId && $uid > 0) ? ' selected' : '';
            $html    .= '<option value="' . $uid . '"' . $selected . '>' . $uName . '</option>';
        }
        return $html;
    }

    // ---- 種目マスタ ----

    /**
     * @param array<int, array<string, mixed>> $productCategories
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderCategorySection(
        array $productCategories,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl      = $masterUrls['category_create'] ?? '';
        $updateUrl      = $masterUrls['category_update'] ?? '';
        $deactivateUrl  = $masterUrls['category_deactivate'] ?? '';
        $activateUrl    = $masterUrls['category_activate'] ?? '';

        $csrfCreate     = $masterCsrfs['category_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['category_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['category_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['category_activate'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($productCategories as $row) {
            $id          = (int) ($row['id'] ?? 0);
            $csvValue    = Layout::escape((string) ($row['csv_value'] ?? ''));
            $displayName = Layout::escape((string) ($row['display_name'] ?? ''));
            $isActive    = (int) ($row['is_active'] ?? 1);
            $dlgId       = 'dlg-cat-' . $id;
            $rowStyle    = $isActive === 0 ? ' style="opacity:0.5;"' : '';

            if ($isActive === 1) {
                $dialogs .= ''
                    . '<dialog id="' . $dlgId . '">'
                    . '<div class="dlg-title">種目を編集</div>'
                    . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="category">'
                    . '<div class="form-row"><div class="form-label">種目種類値（CSV）</div>'
                    . '<input type="text" name="csv_value" value="' . $csvValue . '" required class="form-input"></div>'
                    . '<div class="form-row"><div class="form-label">表示名</div>'
                    . '<input type="text" name="display_name" value="' . $displayName . '" required class="form-input"></div>'
                    . '<div class="dlg-footer">'
                    . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                    . '<button type="submit" class="btn btn-primary">更新</button>'
                    . '</div>'
                    . '</form>'
                    . '</dialog>';

                $actionBtns = '<div style="display:flex;gap:4px;">'
                    . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="category">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="return confirm(\'この値を無効化します。既存データの表示には影響しませんが、新規選択はできなくなります。よろしいですか？\')">無効化</button>'
                    . '</form>'
                    . '</div>';
            } else {
                $actionBtns = '<form method="post" action="' . Layout::escape($activateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="category">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">有効化</button>'
                    . '</form>';
            }

            $rows .= '<tr' . $rowStyle . '>'
                . '<td>' . $csvValue . '</td>'
                . '<td>' . $displayName . '</td>'
                . '<td>' . $actionBtns . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $searchUi = ''
            . '<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">'
            . '<input id="cat-search" class="form-input" placeholder="種目種類・表示名で検索..." style="max-width:260px;">'
            . '<select id="cat-filter" class="form-select" style="max-width:140px;">'
            . '<option value="">全カテゴリ</option>'
            . '<option>自動車</option><option>自賠責</option><option>火災</option>'
            . '<option>積立</option><option>傷害</option><option>新種</option>'
            . '<option>マリン</option><option>運賠</option><option>Lpack</option>'
            . '<option>生保</option><option>長期ローン</option><option>ビジマス</option>'
            . '</select>'
            . '<span id="cat-count" style="font-size:12px;color:var(--text-secondary);"></span>'
            . '</div>';

        $table = ''
            . '<div class="tbl-wrap" style="max-height:480px;overflow-y:auto;">'
            . '<table class="list-table" id="cat-table">'
            . '<thead><tr>'
            . '<th>種目種類値（CSV）</th>'
            . '<th>表示名</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" id="cat-add-btn" style="margin-top:12px;"'
            . ' onclick="this.style.display=\'none\';document.getElementById(\'cat-add-form\').style.display=\'block\';">+ 種目を追加</button>';

        $addForm = ''
            . '<div id="cat-add-form" style="display:none;margin-top:12px;background:#f9f9f9;padding:12px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="category">'
            . '<label style="display:block;margin-bottom:6px;">種目種類値（SJ-NET出力値）<input type="text" name="csv_value" required class="form-input" style="max-width:200px;margin-left:8px;"></label>'
            . '<label style="display:block;margin-bottom:8px;">表示名 <input type="text" name="display_name" required class="form-input" style="max-width:200px;margin-left:8px;"></label>'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . ' <button type="button" class="btn btn-sm" style="margin-left:4px;"'
            . ' onclick="document.getElementById(\'cat-add-form\').style.display=\'none\';document.getElementById(\'cat-add-btn\').style.display=\'inline-block\';">キャンセル</button>'
            . '</form>'
            . '</div>';

        $filterJs = '<script>'
            . 'function initCategoryFilter(){'
            . 'var search=document.getElementById("cat-search");'
            . 'var filter=document.getElementById("cat-filter");'
            . 'var countEl=document.getElementById("cat-count");'
            . 'function apply(){'
            . 'var q=search?search.value.toLowerCase():"";'
            . 'var cat=filter?filter.value:"";'
            . 'var visible=0;'
            . 'document.querySelectorAll("#cat-table tbody tr").forEach(function(tr){'
            . 'var matchText=!q||tr.textContent.toLowerCase().includes(q);'
            . 'var matchCat=!cat||(tr.cells[1]&&tr.cells[1].textContent.trim()===cat);'
            . 'tr.style.display=(matchText&&matchCat)?"":"none";'
            . 'if(matchText&&matchCat)visible++;'
            . '});'
            . 'if(countEl)countEl.textContent=visible+"件表示中";'
            . '}'
            . 'if(search)search.addEventListener("input",apply);'
            . 'if(filter)filter.addEventListener("change",apply);'
            . 'apply();'
            . '}'
            . '</script>';

        return ''
            . '<div class="card" style="max-width:700px;">'
            . '<div class="detail-section-title">種目マスタ</div>'
            . '<p style="font-size:12px;color:var(--text-secondary);margin:0 0 8px;">種目マスタを管理します。無効化した種目は新規選択できなくなりますが、既存データの表示には影響しません。</p>'
            . $searchUi
            . $table
            . $addBtn
            . $addForm
            . '</div>'
            . $filterJs
            . $dialogs;
    }

    // ---- 対応状況マスタ ----

    /**
     * @param array<int, array<string, mixed>> $renewalCaseStatuses
     * @param array<int, array<string, mixed>> $accidentCaseStatuses
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderStatusSection(
        array $renewalCaseStatuses,
        array $accidentCaseStatuses,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl      = $masterUrls['status_create'] ?? '';
        $updateNameUrl  = $masterUrls['status_update_name'] ?? '';
        $deactivateUrl  = $masterUrls['status_deactivate'] ?? '';
        $activateUrl    = $masterUrls['status_activate'] ?? '';

        $csrfCreate     = $masterCsrfs['status_create'] ?? '';
        $csrfUpdateName = $masterCsrfs['status_update_name'] ?? '';
        $csrfDeactivate = $masterCsrfs['status_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['status_activate'] ?? '';

        $html    = '';
        $dialogs = '';

        // Helper to build a status table + add form for one case_type
        $buildSection = function (
            string $sectionTitle,
            string $caseType,
            string $addBtnId,
            string $addFormId,
            array $statuses
        ) use ($createUrl, $updateNameUrl, $deactivateUrl, $activateUrl, $csrfCreate, $csrfUpdateName, $csrfDeactivate, $csrfActivate, &$dialogs): string {
            $rows = '';
            foreach ($statuses as $row) {
                $id          = (int) ($row['id'] ?? 0);
                $displayName = Layout::escape((string) ($row['display_name'] ?? ''));
                $displayOrder = (int) ($row['display_order'] ?? 0);
                $isSystem    = (int) ($row['is_system'] ?? 0);
                $isActive    = (int) ($row['is_active'] ?? 1);
                $dlgId       = 'dlg-status-' . $id;
                $rowStyle    = ($isSystem === 0 && $isActive === 0) ? ' style="opacity:0.5;"' : '';

                if ($isSystem === 1) {
                    // System-fixed: display only
                    $actionBtns = '<span class="badge badge-gray" style="font-size:11px;">固定</span>';
                } elseif ($isActive === 1) {
                    // Non-system, active: show edit + deactivate
                    $dialogs .= ''
                        . '<dialog id="' . $dlgId . '">'
                        . '<div class="dlg-title">対応状況を編集</div>'
                        . '<form method="post" action="' . Layout::escape($updateNameUrl) . '">'
                        . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdateName) . '">'
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<input type="hidden" name="_tab" value="status">'
                        . '<div class="form-row"><div class="form-label">表示名</div>'
                        . '<input type="text" name="display_name" value="' . $displayName . '" required class="form-input"></div>'
                        . '<div class="dlg-footer">'
                        . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                        . '<button type="submit" class="btn btn-primary">更新</button>'
                        . '</div>'
                        . '</form>'
                        . '</dialog>';

                    $actionBtns = '<div style="display:flex;gap:4px;">'
                        . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                        . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                        . '<form method="post" action="' . Layout::escape($deactivateUrl) . '">'
                        . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<input type="hidden" name="_tab" value="status">'
                        . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;"'
                        . ' onclick="return confirm(\'この値を無効化します。既存データの表示には影響しませんが、新規選択はできなくなります。よろしいですか？\')">無効化</button>'
                        . '</form>'
                        . '</div>';
                } else {
                    // Non-system, inactive: show activate only
                    $actionBtns = '<form method="post" action="' . Layout::escape($activateUrl) . '">'
                        . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<input type="hidden" name="_tab" value="status">'
                        . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">有効化</button>'
                        . '</form>';
                }

                $rows .= '<tr' . $rowStyle . '>'
                    . '<td>' . $displayName . '</td>'
                    . '<td style="text-align:center;">' . $displayOrder . '</td>'
                    . '<td>' . $actionBtns . '</td>'
                    . '</tr>';
            }

            if ($rows === '') {
                $rows = '<tr><td colspan="3" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
            }

            $table = ''
                . '<div class="tbl-wrap"><table class="list-table">'
                . '<thead><tr>'
                . '<th>表示名</th>'
                . '<th style="text-align:center;">表示順</th>'
                . '<th></th>'
                . '</tr></thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table></div>';

            $addBtn = '<button class="btn btn-primary" id="' . $addBtnId . '" style="margin-top:12px;"'
                . ' onclick="this.style.display=\'none\';document.getElementById(\'' . $addFormId . '\').style.display=\'block\';">+ 対応状況を追加</button>';

            $addForm = ''
                . '<div id="' . $addFormId . '" style="display:none;margin-top:12px;background:#f9f9f9;padding:12px;border:1px solid #ddd;">'
                . '<form method="post" action="' . Layout::escape($createUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
                . '<input type="hidden" name="_tab" value="status">'
                . '<input type="hidden" name="case_type" value="' . Layout::escape($caseType) . '">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="display_name" required class="form-input"></div>'
                . '<div style="margin-top:8px;">'
                . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
                . ' <button type="button" class="btn btn-sm" style="margin-left:4px;"'
                . ' onclick="document.getElementById(\'' . $addFormId . '\').style.display=\'none\';document.getElementById(\'' . $addBtnId . '\').style.display=\'inline-block\';">キャンセル</button>'
                . '</div>'
                . '</form>'
                . '</div>';

            return ''
                . '<div class="card" style="max-width:500px;margin-bottom:16px;">'
                . '<div class="detail-section-title">' . Layout::escape($sectionTitle) . '</div>'
                . $table
                . $addBtn
                . $addForm
                . '</div>';
        };

        $statusNote = '<p style="font-size:12px;color:var(--text-secondary);margin:0 0 12px;">対応状況マスタを管理します。「固定」マークの項目はシステムで使用するため変更できません。無効化した項目は新規選択できなくなりますが、既存データの表示には影響しません。</p>';
        $html .= $statusNote;
        $html .= $buildSection('満期 対応状況', 'renewal', 'status-renewal-add-btn', 'status-renewal-add-form', $renewalCaseStatuses);
        $html .= $buildSection('事故 対応状況', 'accident', 'status-accident-add-btn', 'status-accident-add-form', $accidentCaseStatuses);

        return $html . $dialogs;
    }

    // ---- 用件区分マスタ ----

    /**
     * @param array<int, array<string, mixed>> $purposeTypes
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderProcedureMethodSection(
        array $procedureMethods,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl      = $masterUrls['procedure_method_create'] ?? '';
        $updateUrl      = $masterUrls['procedure_method_update'] ?? '';
        $deactivateUrl  = $masterUrls['procedure_method_deactivate'] ?? '';
        $activateUrl    = $masterUrls['procedure_method_activate'] ?? '';

        $csrfCreate     = $masterCsrfs['procedure_method_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['procedure_method_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['procedure_method_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['procedure_method_activate'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($procedureMethods as $row) {
            $id       = (int) ($row['id'] ?? 0);
            $label    = Layout::escape((string) ($row['label'] ?? ''));
            $isActive = (int) ($row['is_active'] ?? 1);
            $dlgId    = 'dlg-procedure-' . $id;
            $rowStyle = $isActive === 0 ? ' style="opacity:0.5;"' : '';

            if ($isActive === 1) {
                $dialogs .= ''
                    . '<dialog id="' . $dlgId . '">'
                    . '<div class="dlg-title">手続方法を編集</div>'
                    . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="procedure">'
                    . '<div class="form-row"><div class="form-label">表示名</div>'
                    . '<input type="text" name="label" value="' . $label . '" required class="form-input"></div>'
                    . '<div class="dlg-footer">'
                    . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                    . '<button type="submit" class="btn btn-primary">更新</button>'
                    . '</div>'
                    . '</form>'
                    . '</dialog>';

                $actionBtns = '<div style="display:flex;gap:4px;">'
                    . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="procedure">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="return confirm(\'この値を無効化します。既存データの表示には影響しませんが、新規選択はできなくなります。よろしいですか？\')">無効化</button>'
                    . '</form>'
                    . '</div>';
            } else {
                $actionBtns = '<form method="post" action="' . Layout::escape($activateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="procedure">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">有効化</button>'
                    . '</form>';
            }

            $rows .= '<tr' . $rowStyle . '>'
                . '<td>' . $label . '</td>'
                . '<td>' . $actionBtns . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="2" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $table = ''
            . '<div class="tbl-wrap"><table class="list-table">'
            . '<thead><tr>'
            . '<th>表示名</th>'
            . '<th style="width:130px;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" id="procedure-add-btn" style="margin-top:12px;"'
            . ' onclick="this.style.display=\'none\';document.getElementById(\'procedure-add-form\').style.display=\'block\';">+ 手続方法を追加</button>';

        $addForm = ''
            . '<div id="procedure-add-form" style="display:none;margin-top:12px;background:#f9f9f9;padding:12px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="procedure">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="label" required class="form-input"></div>'
            . '<div style="margin-top:8px;">'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . ' <button type="button" class="btn btn-sm" style="margin-left:4px;"'
            . ' onclick="document.getElementById(\'procedure-add-form\').style.display=\'none\';document.getElementById(\'procedure-add-btn\').style.display=\'inline-block\';">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</div>';

        return ''
            . '<div class="card" style="max-width:400px;">'
            . '<div class="detail-section-title">手続方法マスタ</div>'
            . '<p style="font-size:12px;color:var(--text-secondary);margin:0 0 8px;">満期詳細の「手続方法」プルダウンに表示される選択肢を管理します。無効化した項目は新規選択できなくなりますが、既存データの表示には影響しません。</p>'
            . $table
            . $addBtn
            . $addForm
            . '</div>'
            . $dialogs;
    }

    /**
     * @param array<string, string>            $masterUrls
     */
    private static function renderPurposeTypeSection(
        array $purposeTypes,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl      = $masterUrls['purpose_type_create'] ?? '';
        $updateUrl      = $masterUrls['purpose_type_update'] ?? '';
        $deactivateUrl  = $masterUrls['purpose_type_deactivate'] ?? '';
        $activateUrl    = $masterUrls['purpose_type_activate'] ?? '';

        $csrfCreate     = $masterCsrfs['purpose_type_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['purpose_type_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['purpose_type_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['purpose_type_activate'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($purposeTypes as $row) {
            $code     = Layout::escape((string) ($row['code'] ?? ''));
            $label    = Layout::escape((string) ($row['label'] ?? ''));
            $isActive = (int) ($row['is_active'] ?? 1);
            $dlgId    = 'dlg-purpose-' . $code;
            $rowStyle = $isActive === 0 ? ' style="opacity:0.5;"' : '';

            if ($isActive === 1) {
                $dialogs .= ''
                    . '<dialog id="' . $dlgId . '">'
                    . '<div class="dlg-title">用件区分を編集</div>'
                    . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                    . '<input type="hidden" name="code" value="' . $code . '">'
                    . '<input type="hidden" name="_tab" value="purpose">'
                    . '<div class="form-row"><div class="form-label">表示名</div>'
                    . '<input type="text" name="label" value="' . $label . '" required class="form-input"></div>'
                    . '<div class="dlg-footer">'
                    . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                    . '<button type="submit" class="btn btn-primary">更新</button>'
                    . '</div>'
                    . '</form>'
                    . '</dialog>';

                $actionBtns = '<div style="display:flex;gap:4px;">'
                    . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="code" value="' . $code . '">'
                    . '<input type="hidden" name="_tab" value="purpose">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="return confirm(\'この値を無効化します。既存データの表示には影響しませんが、新規選択はできなくなります。よろしいですか？\')">無効化</button>'
                    . '</form>'
                    . '</div>';
            } else {
                $actionBtns = '<form method="post" action="' . Layout::escape($activateUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="code" value="' . $code . '">'
                    . '<input type="hidden" name="_tab" value="purpose">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">有効化</button>'
                    . '</form>';
            }

            $rows .= '<tr' . $rowStyle . '>'
                . '<td>' . $label . '</td>'
                . '<td>' . $actionBtns . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="2" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $table = ''
            . '<div class="tbl-wrap"><table class="list-table">'
            . '<thead><tr>'
            . '<th>表示名</th>'
            . '<th style="width:130px;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" id="purpose-add-btn" style="margin-top:12px;"'
            . ' onclick="this.style.display=\'none\';document.getElementById(\'purpose-add-form\').style.display=\'block\';">+ 用件区分を追加</button>';

        $addForm = ''
            . '<div id="purpose-add-form" style="display:none;margin-top:12px;background:#f9f9f9;padding:12px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="purpose">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="label" required class="form-input"></div>'
            . '<div style="margin-top:8px;">'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . ' <button type="button" class="btn btn-sm" style="margin-left:4px;"'
            . ' onclick="document.getElementById(\'purpose-add-form\').style.display=\'none\';document.getElementById(\'purpose-add-btn\').style.display=\'inline-block\';">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</div>';

        return ''
            . '<div class="card" style="max-width:400px;">'
            . '<div class="detail-section-title">用件区分</div>'
            . '<p style="font-size:12px;color:var(--text-secondary);margin:0 0 8px;">活動登録の「用件区分」プルダウンに表示される選択肢を管理します。無効化した項目は新規選択できなくなりますが、既存データの表示には影響しません。</p>'
            . $table
            . $addBtn
            . $addForm
            . '</div>'
            . $dialogs;
    }

    // ---- 通知設定 ----

    /**
     * @param array<string, mixed>             $renewal
     * @param array<string, mixed>             $accident
     * @param array<int, array<string, mixed>> $phases
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderNotifyForm(
        array $renewal,
        array $accident,
        array $phases,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $providers = self::availableProviders();

        $renewalSaveUrl    = $masterUrls['notify_renewal'] ?? '';
        $renewalCsrfToken  = $masterCsrfs['notify_renewal'] ?? '';
        $accidentSaveUrl   = $masterUrls['notify_accident'] ?? '';
        $accidentCsrfToken = $masterCsrfs['notify_accident'] ?? '';

        $renewalWebhook  = Layout::escape((string) ($renewal['webhook_url'] ?? ''));
        $accidentWebhook = Layout::escape((string) ($accident['webhook_url'] ?? ''));

        $renewalProvider  = (string) ($renewal['provider_type'] ?? 'lineworks');
        $accidentProvider = (string) ($accident['provider_type'] ?? 'lineworks');

        $renewalDestName  = Layout::escape((string) ($renewal['destination_name'] ?? 'renewal_default'));
        $accidentDestName = Layout::escape((string) ($accident['destination_name'] ?? 'accident_default'));

        $renewalEnabled     = ((int) ($renewal['is_enabled'] ?? 0) === 1);
        $renewalEnabledAttr = $renewalEnabled ? ' checked' : '';
        $renewalBadgeClass  = $renewalEnabled ? 'badge badge-success' : 'badge badge-gray';
        $renewalBadgeText   = $renewalEnabled ? '有効' : '無効';

        $accidentEnabled     = ((int) ($accident['is_enabled'] ?? 0) === 1);
        $accidentEnabledAttr = $accidentEnabled ? ' checked' : '';
        $accidentBadgeClass  = $accidentEnabled ? 'badge badge-success' : 'badge badge-gray';
        $accidentBadgeText   = $accidentEnabled ? '有効' : '無効';

        $renewalProviderOptions  = self::buildProviderOptions($providers, $renewalProvider);
        $accidentProviderOptions = self::buildProviderOptions($providers, $accidentProvider);

        $timingRows = self::renderTimingRows($phases);

        // Badge toggle JS — event delegation on .form-row
        $badgeToggleJs = '<script>'
            . 'document.addEventListener("change",function(e){'
            . 'if(!e.target.matches("input[type=\'checkbox\']"))return;'
            . 'var row=e.target.closest(".form-row");'
            . 'if(!row)return;'
            . 'var badge=row.querySelector(".badge");'
            . 'if(!badge)return;'
            . 'if(e.target.checked){badge.textContent="有効";badge.className="badge badge-success";}'
            . 'else{badge.textContent="無効";badge.className="badge badge-gray";}'
            . '});'
            . '</script>';

        // Renewal card
        $renewalCard = ''
            . '<form method="post" action="' . Layout::escape($renewalSaveUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($renewalCsrfToken) . '">'
            . '<input type="hidden" name="renewal_destination_name" value="' . $renewalDestName . '">'
            . '<input type="hidden" name="_tab" value="notify">'
            . '<div class="card" style="max-width:560px;">'
            . '<div class="detail-section-title">満期通知</div>'
            . '<div class="form-row">'
            . '<div style="display:flex;align-items:center;gap:12px;">'
            . '<label style="display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:500;cursor:pointer;">'
            . '<input type="checkbox" name="renewal_is_enabled" value="1"' . $renewalEnabledAttr . '>'
            . '満期通知'
            . '</label>'
            . '<span class="' . $renewalBadgeClass . '">' . $renewalBadgeText . '</span>'
            . '</div>'
            . '<div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">満期通知を送信する場合は有効にしてください。</div>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">通知先</div>'
            . '<select name="renewal_provider_type" class="form-select" style="max-width:240px;">' . $renewalProviderOptions . '</select>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">Webhook URL</div>'
            . '<input class="form-input" type="url" name="renewal_webhook_url" value="' . $renewalWebhook . '" placeholder="https://hooks.worksmobile.com/r/...">'
            . '<div style="font-size:11.5px;color:var(--text-secondary);margin-top:4px;">LINE WORKS の Webhook URL を入力してください。通知先を変更した場合は対応する URL に更新してください。</div>'
            . '</div>'
            . '<div class="divider"></div>'
            . $timingRows
            . '<button type="submit" class="btn btn-primary" style="margin-top:8px;">保存</button>'
            . '</div>'
            . '</form>';

        // Accident card
        $accidentCard = ''
            . '<form method="post" action="' . Layout::escape($accidentSaveUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($accidentCsrfToken) . '">'
            . '<input type="hidden" name="accident_destination_name" value="' . $accidentDestName . '">'
            . '<input type="hidden" name="_tab" value="notify">'
            . '<div class="card" style="max-width:560px;">'
            . '<div class="detail-section-title">事故通知</div>'
            . '<div class="form-row">'
            . '<div class="form-label">通知先</div>'
            . '<select name="accident_provider_type" class="form-select" style="max-width:240px;">' . $accidentProviderOptions . '</select>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">Webhook URL</div>'
            . '<input class="form-input" type="url" name="accident_webhook_url" value="' . $accidentWebhook . '" placeholder="https://hooks.worksmobile.com/r/...">'
            . '<div style="font-size:11.5px;color:var(--text-secondary);margin-top:4px;">満期通知と別のチャンネルに送信する場合は個別に設定してください。空欄の場合は満期通知と同じ Webhook URL を使用します。</div>'
            . '</div>'
            . '<div class="divider"></div>'
            . '<div class="form-row">'
            . '<div style="display:flex;align-items:center;gap:12px;">'
            . '<label style="display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:500;cursor:pointer;">'
            . '<input type="checkbox" name="accident_is_enabled" value="1"' . $accidentEnabledAttr . '>'
            . '事故受付通知'
            . '</label>'
            . '<span class="' . $accidentBadgeClass . '">' . $accidentBadgeText . '</span>'
            . '</div>'
            . '<div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">新規事故案件が受付されたときに通知します。</div>'
            . '</div>'
            . '<button type="submit" class="btn btn-primary" style="margin-top:8px;">保存</button>'
            . '</div>'
            . '</form>';

        return $renewalCard . $accidentCard . $badgeToggleJs;
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
        $html = '';
        foreach ($orderedPhases as $phase) {
            $id = (int) ($phase['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (($phase['phase_code'] ?? '') === 'NORMAL') {
                continue;
            }
            $meta         = self::phaseUiMeta($phase);
            $phaseCode    = (string) ($phase['phase_code'] ?? '');
            $isEnabled    = ((int) ($phase['is_enabled'] ?? 0) === 1);
            $days         = (int) ($phase['from_days_before'] ?? 28);
            $displayOrder = (int) ($phase['display_order'] ?? 0);

            $fieldPrefix = match ($phaseCode) {
                'EARLY'  => 'renewal_early',
                'URGENT' => 'renewal_near',
                default  => 'renewal_ph' . $id,
            };

            $checkedAttr = $isEnabled ? ' checked' : '';
            $badgeClass  = $isEnabled ? 'badge badge-success' : 'badge badge-gray';
            $badgeText   = $isEnabled ? '有効' : '無効';

            $html .= ''
                . '<div class="form-row">'
                . '<input type="hidden" name="' . $fieldPrefix . '_id" value="' . $id . '">'
                . '<input type="hidden" name="' . $fieldPrefix . '_order" value="' . $displayOrder . '">'
                . '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">'
                . '<label style="display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:500;cursor:pointer;">'
                . '<input type="checkbox" name="' . $fieldPrefix . '_enabled" value="1"' . $checkedAttr . '>'
                . Layout::escape($meta['label'])
                . '</label>'
                . '<span class="' . $badgeClass . '">' . $badgeText . '</span>'
                . '</div>'
                . '<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;">'
                . '<span style="color:var(--text-secondary);">満期</span>'
                . self::daySelectHtml($fieldPrefix . '_days', $days)
                . '<span style="color:var(--text-secondary);">日前に通知</span>'
                . '</div>'
                . '</div>';
        }

        return $html;
    }

    private static function daySelectHtml(string $name, int $current): string
    {
        $options = [7, 14, 21, 28, 30, 45, 60];
        if (!in_array($current, $options, true) && $current > 0) {
            $options[] = $current;
            sort($options);
        }
        $html = '';
        foreach ($options as $val) {
            $selected = ($val === $current) ? ' selected' : '';
            $html .= '<option value="' . $val . '"' . $selected . '>' . $val . '</option>';
        }

        return '<select name="' . Layout::escape($name) . '" class="form-select" style="max-width:100px;">' . $html . '</select>';
    }

    /**
     * @param array<int, array<string, mixed>> $phases
     * @return array<int, array<string, mixed>>
     */
    private static function orderPhasesForDisplay(array $phases): array
    {
        usort($phases, static function (array $a, array $b): int {
            $priority = [
                'EARLY'  => 10,
                'NORMAL' => 20,
                'URGENT' => 30,
            ];

            $aCode     = (string) ($a['phase_code'] ?? '');
            $bCode     = (string) ($b['phase_code'] ?? '');
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
            'EARLY' => [
                'label'       => '早期通知',
                'description' => '満期日の90日前から61日前まで通知',
            ],
            'NORMAL' => [
                'label'       => '通常通知',
                'description' => '満期日の60日前から31日前まで通知',
            ],
            'URGENT' => [
                'label'       => '直前通知',
                'description' => '満期日の30日前から当日まで通知',
            ],
            default => [
                'label'       => '通知タイミング',
                'description' => '満期通知の送信タイミングを設定',
            ],
        };
    }

    /**
     * @param array<int, string> $providers
     */
    private static function buildProviderOptions(array $providers, string $current): string
    {
        $html = '';
        foreach ($providers as $provider) {
            $selected = $provider === $current ? ' selected' : '';
            $html .= '<option value="' . Layout::escape($provider) . '"' . $selected . '>'
                . Layout::escape(self::providerLabel($provider))
                . '</option>';
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

    private static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'lineworks'   => 'LINE WORKS',
            'google_chat' => 'Google Chat',
            'slack'       => 'Slack',
            'teams'       => 'Microsoft Teams',
            default       => strtoupper(str_replace('_', ' ', $provider)),
        };
    }

    // ---- ユーザー管理 ----

    /**
     * @param array<int, array<string, mixed>> $tenantUsers
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderUserSection(
        array $tenantUsers,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $updateUrl  = $masterUrls['user_update_display_name'] ?? '';
        $csrfUpdate = $masterCsrfs['user_update_display_name'] ?? '';

        $rows    = '';
        $dialogs = '';

        foreach ($tenantUsers as $user) {
            $userId      = (int) ($user['id'] ?? 0);
            $name        = Layout::escape((string) ($user['name'] ?? ''));
            $displayName = Layout::escape((string) ($user['display_name'] ?? ''));
            $email       = Layout::escape((string) ($user['email'] ?? ''));
            $role        = (string) ($user['role'] ?? '');
            $roleLabel   = match ($role) {
                'admin'  => '管理者',
                'member' => 'メンバー',
                default  => Layout::escape($role),
            };
            $effectiveName = $displayName !== '' ? $displayName : $name;
            $dlgId = 'dlg-user-' . $userId;

            $dialogs .= ''
                . '<dialog id="' . $dlgId . '">'
                . '<div class="dlg-title">業務表示名を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . $csrfUpdate . '">'
                . '<input type="hidden" name="user_id" value="' . $userId . '">'
                . '<input type="hidden" name="_tab" value="users">'
                . '<div class="form-row"><div class="form-label">Googleアカウント名</div>'
                . '<div style="padding:6px 0;font-size:14px;color:#334e68;">' . $name . '</div></div>'
                . '<div class="form-row"><div class="form-label">業務表示名</div>'
                . '<input type="text" name="display_name" value="' . $displayName . '" class="form-input" placeholder="空欄にするとGoogleアカウント名を使用" maxlength="100"></div>'
                . '<div class="form-row"><div class="form-label">メールアドレス</div>'
                . '<div style="padding:6px 0;font-size:14px;color:#334e68;">' . $email . '</div></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">保存</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            $rows .= '<tr>'
                . '<td>' . $effectiveName . ($displayName !== '' ? '' : ' <span class="muted" style="font-size:11px;">（Google名）</span>') . '</td>'
                . '<td>' . $name . '</td>'
                . '<td>' . $email . '</td>'
                . '<td>' . $roleLabel . '</td>'
                . '<td>'
                . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="muted" style="text-align:center;padding:8px;">ユーザーなし</td></tr>';
        }

        $table = ''
            . '<div class="tbl-wrap"><table class="list-table">'
            . '<thead><tr>'
            . '<th>業務表示名</th><th>Googleアカウント名</th><th>メールアドレス</th><th>ロール</th><th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">ユーザー管理</div>'
            . '<p class="muted" style="margin-bottom:12px;font-size:12.5px;">テナント所属ユーザーの業務表示名を管理します。空欄にするとGoogleアカウント名にフォールバックします。</p>'
            . $table
            . '</div>'
            . $dialogs;
    }
}
