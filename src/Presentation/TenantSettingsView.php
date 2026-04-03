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
     * @param array<int, string>                  $allUsers
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
        array $allUsers = []
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
            . '<div class="tab" data-tab="purpose" onclick="showSettingsTab(\'purpose\',this)">用件区分</div>'
            . '<div class="tab" data-tab="notify" onclick="showSettingsTab(\'notify\',this)">通知設定</div>'
            . '</div>';

        // Tab panels
        $panelStaff    = '<div id="settings-panel-staff" class="settings-panel">'
            . self::renderStaffSection($staffMappings, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelCategory = '<div id="settings-panel-category" class="settings-panel" style="display:none;">'
            . self::renderCategorySection($productCategories, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelStatus   = '<div id="settings-panel-status" class="settings-panel" style="display:none;">'
            . self::renderStatusSection($renewalCaseStatuses, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelPurpose  = '<div id="settings-panel-purpose" class="settings-panel" style="display:none;">'
            . self::renderPurposeTypeSection($purposeTypes, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelNotify   = '<div id="settings-panel-notify" class="settings-panel" style="display:none;">'
            . self::renderNotifyForm($renewal, $accident, $phases, $masterCsrfs, $masterUrls)
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
            . $panelPurpose
            . $panelNotify
            . $tabJs;

        return Layout::render('テナント設定', $content, $layoutOptions);
    }

    // ---- 担当者マスタ ----

    /**
     * @param array<int, array<string, mixed>> $staffMappings
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderStaffSection(
        array $staffMappings,
        array $masterCsrfs,
        array $masterUrls
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
            $noteVal   = Layout::escape((string) ($row['note'] ?? ''));
            $dlgId     = 'dlg-staff-' . $id;

            $activeBadge = $isActive ? '<span class="badge badge-success">有効</span>' : '<span class="badge badge-gray">無効</span>';

            $dialogs .= ''
                . '<dialog id="' . $dlgId . '">'
                . '<div class="dlg-title">担当者を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="staff">'
                . '<div class="form-row"><div class="form-label">代理店コード</div>'
                . '<input type="text" name="sjnet_code" value="' . $sjnetCode . '" required class="form-input"></div>'
                . '<div class="form-row"><div class="form-label">担当者名</div>'
                . '<input type="text" name="staff_name" value="' . $staffName . '" required class="form-input"></div>'
                . '<div class="form-row"><div class="form-label">有効</div>'
                . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1"' . ($isActive ? ' checked' : '') . '> 有効にする</label></div>'
                . '<div class="form-row"><div class="form-label">備考</div>'
                . '<input type="text" name="note" value="' . $noteVal . '" class="form-input" maxlength="255"></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            $rows .= '<tr>'
                . '<td>' . $sjnetCode . '</td>'
                . '<td>' . $staffName . '</td>'
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
            . '<th>代理店コード</th>'
            . '<th>担当者名</th>'
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
            . '<div class="form-row"><div class="form-label">代理店コード</div>'
            . '<input type="text" name="sjnet_code" required class="form-input"></div>'
            . '<div class="form-row"><div class="form-label">担当者名</div>'
            . '<input type="text" name="staff_name" required class="form-input"></div>'
            . '<div class="form-row"><div class="form-label">有効</div>'
            . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" checked> 有効にする</label></div>'
            . '<div class="form-row"><div class="form-label">備考</div>'
            . '<input type="text" name="note" class="form-input" maxlength="255"></div>'
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
            . '<p class="muted" style="margin-bottom:12px;font-size:12.5px;">満期一覧CSVの「代理店コード」（列44）を担当者名に変換します。</p>'
            . $table
            . $addBtn
            . $addForm
            . '</div>'
            . $dialogs;
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
        $createUrl = $masterUrls['category_create'] ?? '';
        $updateUrl = $masterUrls['category_update'] ?? '';
        $deleteUrl = $masterUrls['category_delete'] ?? '';

        $csrfCreate = $masterCsrfs['category_create'] ?? '';
        $csrfUpdate = $masterCsrfs['category_update'] ?? '';
        $csrfDelete = $masterCsrfs['category_delete'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($productCategories as $row) {
            $id          = (int) ($row['id'] ?? 0);
            $csvValue    = Layout::escape((string) ($row['csv_value'] ?? ''));
            $displayName = Layout::escape((string) ($row['display_name'] ?? ''));
            $dlgId       = 'dlg-cat-' . $id;

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

            $rows .= '<tr>'
                . '<td>' . $csvValue . '</td>'
                . '<td>' . $displayName . '</td>'
                . '<td><div style="display:flex;gap:4px;">'
                . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                . '<form method="post" action="' . Layout::escape($deleteUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDelete) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="category">'
                . '<button type="submit" class="btn btn-danger" style="padding:3px 10px;font-size:11px;" onclick="return confirm(\'削除しますか？\')">削除</button>'
                . '</form>'
                . '</div></td>'
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
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderStatusSection(
        array $renewalCaseStatuses,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl     = $masterUrls['status_create'] ?? '';
        $updateNameUrl = $masterUrls['status_update_name'] ?? '';
        $deleteUrl     = $masterUrls['status_delete'] ?? '';

        $csrfCreate     = $masterCsrfs['status_create'] ?? '';
        $csrfUpdateName = $masterCsrfs['status_update_name'] ?? '';
        $csrfDelete     = $masterCsrfs['status_delete'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($renewalCaseStatuses as $row) {
            $id           = (int) ($row['id'] ?? 0);
            $displayName  = Layout::escape((string) ($row['display_name'] ?? ''));
            $displayOrder = (int) ($row['display_order'] ?? 0);
            $dlgId        = 'dlg-status-' . $id;

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
                . '<form method="post" action="' . Layout::escape($deleteUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDelete) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="status">'
                . '<button type="submit" class="btn btn-danger" style="padding:3px 10px;font-size:11px;" onclick="return confirm(\'削除しますか？\')">削除</button>'
                . '</form>'
                . '</div>';

            $rows .= '<tr>'
                . '<td>' . $displayName . '</td>'
                . '<td style="text-align:center;">' . $displayOrder . '</td>'
                . '<td><span class="badge badge-success">有効</span></td>'
                . '<td>' . $actionBtns . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $table = ''
            . '<div class="tbl-wrap"><table class="list-table">'
            . '<thead><tr>'
            . '<th>表示名</th>'
            . '<th style="text-align:center;">表示順</th>'
            . '<th>有効</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" id="status-add-btn" style="margin-top:12px;"'
            . ' onclick="this.style.display=\'none\';document.getElementById(\'status-add-form\').style.display=\'block\';">+ 対応状況を追加</button>';

        $addForm = ''
            . '<div id="status-add-form" style="display:none;margin-top:12px;background:#f9f9f9;padding:12px;border:1px solid #ddd;">'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="status">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="display_name" required class="form-input"></div>'
            . '<div style="margin-top:8px;">'
            . '<button type="submit" class="btn btn-primary btn-sm">追加</button>'
            . ' <button type="button" class="btn btn-sm" style="margin-left:4px;"'
            . ' onclick="document.getElementById(\'status-add-form\').style.display=\'none\';document.getElementById(\'status-add-btn\').style.display=\'inline-block\';">キャンセル</button>'
            . '</div>'
            . '</form>'
            . '</div>';

        return ''
            . '<div class="card" style="max-width:500px;">'
            . '<div class="detail-section-title">対応状況マスタ</div>'
            . $table
            . $addBtn
            . $addForm
            . '</div>'
            . $dialogs;
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
        $createUrl = $masterUrls['purpose_type_create'] ?? '';
        $updateUrl = $masterUrls['purpose_type_update'] ?? '';
        $deleteUrl = $masterUrls['purpose_type_delete'] ?? '';

        $csrfCreate = $masterCsrfs['purpose_type_create'] ?? '';
        $csrfUpdate = $masterCsrfs['purpose_type_update'] ?? '';
        $csrfDelete = $masterCsrfs['purpose_type_delete'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($purposeTypes as $row) {
            $code  = Layout::escape((string) ($row['code'] ?? ''));
            $label = Layout::escape((string) ($row['label'] ?? ''));
            $dlgId = 'dlg-purpose-' . $code;

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

            $rows .= '<tr>'
                . '<td>' . $label . '</td>'
                . '<td><div style="display:flex;gap:4px;">'
                . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                . '<form method="post" action="' . Layout::escape($deleteUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDelete) . '">'
                . '<input type="hidden" name="code" value="' . $code . '">'
                . '<input type="hidden" name="_tab" value="purpose">'
                . '<button type="submit" class="btn btn-danger" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="return confirm(\'「' . $label . '」を削除しますか？この操作は取り消せません。\')">削除</button>'
                . '</form>'
                . '</div></td>'
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
}
