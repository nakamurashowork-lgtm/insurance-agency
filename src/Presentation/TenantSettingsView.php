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
     * @param array<int, array<string, mixed>>    $activityTypes
     * @param array<int, array<string, mixed>>    $renewalMethods
     * @param array<int, array<string, mixed>>    $salesCaseStatuses
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
        array $procedureMethods = [],
        array $yearlyTargets = [],
        int $selectedTargetFy = 0,
        array $fiscalYearOptions = [],
        array $assignableUsers = [],
        array $activityTypes = [],
        array $renewalMethods = [],
        array $salesCaseStatuses = []
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

        // Left sidebar navigation (案A: カテゴリ階層)
        $navItem = static function (string $tab, string $label, bool $active = false): string {
            $cls = 'settings-nav-item' . ($active ? ' active' : '');
            return '<li class="' . $cls . '" data-tab="' . Layout::escape($tab) . '"'
                . ' onclick="showSettingsTab(\'' . Layout::escape($tab) . '\',this)">'
                . Layout::escape($label)
                . '</li>';
        };

        $sidebar = ''
            . '<aside class="settings-nav" aria-label="設定メニュー">'
            . '<div class="settings-nav-group">'
            . '<div class="settings-nav-group-title">組織</div>'
            . '<ul class="settings-nav-list">'
            . $navItem('users', 'ユーザー管理', true)
            . '</ul>'
            . '</div>'
            . '<div class="settings-nav-group">'
            . '<div class="settings-nav-group-title">満期管理</div>'
            . '<ul class="settings-nav-list">'
            . $navItem('category', '種目')
            . $navItem('procedure', '手続き方法')
            . $navItem('renewal-method', '更改方法')
            . $navItem('status-renewal', '対応状況')
            . '</ul>'
            . '</div>'
            . '<div class="settings-nav-group">'
            . '<div class="settings-nav-group-title">事故管理</div>'
            . '<ul class="settings-nav-list">'
            . $navItem('status-accident', '対応状況')
            . '</ul>'
            . '</div>'
            . '<div class="settings-nav-group">'
            . '<div class="settings-nav-group-title">見込案件</div>'
            . '<ul class="settings-nav-list">'
            . $navItem('sales-case-status', '対応状況')
            . '</ul>'
            . '</div>'
            . '<div class="settings-nav-group">'
            . '<div class="settings-nav-group-title">営業活動</div>'
            . '<ul class="settings-nav-list">'
            . $navItem('activity-type', '活動種別')
            . $navItem('purpose', '用件区分')
            . '</ul>'
            . '</div>'
            . '<div class="settings-nav-group">'
            . '<div class="settings-nav-group-title">運用</div>'
            . '<ul class="settings-nav-list">'
            . $navItem('notify', '通知設定')
            . $navItem('target', '目標管理')
            . '</ul>'
            . '</div>'
            . '</aside>';

        // Inline styles for sidebar layout
        $navStyles = '<style>'
            . '.settings-layout{display:flex;gap:20px;align-items:flex-start;}'
            . '.settings-nav{flex:0 0 220px;background:#fff;border:1px solid #e4e7eb;border-radius:8px;padding:12px 0;position:sticky;top:16px;}'
            . '.settings-nav-group + .settings-nav-group{margin-top:12px;border-top:1px solid #f0f2f4;padding-top:12px;}'
            . '.settings-nav-group-title{font-size:11px;font-weight:600;letter-spacing:0.08em;color:#8899a6;padding:4px 16px;text-transform:uppercase;}'
            . '.settings-nav-list{list-style:none;padding:0;margin:4px 0 0;}'
            . '.settings-nav-item{display:block;padding:8px 16px;font-size:13px;color:#334e68;cursor:pointer;border-left:3px solid transparent;transition:background-color .12s,color .12s;user-select:none;}'
            . '.settings-nav-item:hover{background:#f4f7f9;}'
            . '.settings-nav-item.active{background:#eef4f6;color:#0b5394;border-left-color:#0b5394;font-weight:600;}'
            . '.settings-content{flex:1;min-width:0;}'
            . '@media (max-width: 900px){'
            . '.settings-layout{flex-direction:column;}'
            . '.settings-nav{position:static;flex:none;width:100%;}'
            . '.settings-nav-list{display:flex;flex-wrap:wrap;}'
            . '.settings-nav-item{flex:0 0 auto;border-left:none;border-bottom:3px solid transparent;padding:8px 12px;}'
            . '.settings-nav-item.active{border-left-color:transparent;border-bottom-color:#0b5394;}'
            . '}'
            . '</style>';

        // Tab panels
        $panelCategory = '<div id="settings-panel-category" class="settings-panel" style="display:none;">'
            . self::renderCategorySection($productCategories, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelStatusRenewal = '<div id="settings-panel-status-renewal" class="settings-panel" style="display:none;">'
            . self::renderStatusSection('renewal', $renewalCaseStatuses, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelStatusAccident = '<div id="settings-panel-status-accident" class="settings-panel" style="display:none;">'
            . self::renderStatusSection('accident', $accidentCaseStatuses, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelSalesCaseStatus = '<div id="settings-panel-sales-case-status" class="settings-panel" style="display:none;">'
            . self::renderSalesCaseStatusSection($salesCaseStatuses, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelProcedure = '<div id="settings-panel-procedure" class="settings-panel" style="display:none;">'
            . self::renderProcedureMethodSection($procedureMethods, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelPurpose  = '<div id="settings-panel-purpose" class="settings-panel" style="display:none;">'
            . self::renderPurposeTypeSection($purposeTypes, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelActivityType = '<div id="settings-panel-activity-type" class="settings-panel" style="display:none;">'
            . self::renderActivityTypeSection($activityTypes, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelRenewalMethod = '<div id="settings-panel-renewal-method" class="settings-panel" style="display:none;">'
            . self::renderRenewalMethodSection($renewalMethods, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelNotify   = '<div id="settings-panel-notify" class="settings-panel" style="display:none;">'
            . self::renderNotifyForm($renewal, $accident, $phases, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelUsers    = '<div id="settings-panel-users" class="settings-panel">'
            . self::renderUserSection($tenantUsers, $masterCsrfs, $masterUrls)
            . '</div>';
        $panelTarget   = '<div id="settings-panel-target" class="settings-panel" style="display:none;">'
            . self::renderSalesTargetSection(
                $yearlyTargets,
                $assignableUsers,
                $selectedTargetFy,
                $fiscalYearOptions,
                $masterCsrfs,
                $masterUrls
            )
            . '</div>';

        // Tab JS — classList approach; call initCategoryFilter when switching to category
        $tabJs = ''
            . '<script>'
            . 'function showSettingsTab(name,tabEl){'
            . 'document.querySelectorAll(".settings-panel").forEach(function(p){p.style.display="none";});'
            . 'var panel=document.getElementById("settings-panel-"+name);'
            . 'if(panel){panel.style.display="";}'
            . 'document.querySelectorAll(".settings-nav-item").forEach(function(t){t.classList.remove("active");});'
            . 'if(tabEl){tabEl.classList.add("active");}'
            . 'else{var el=document.querySelector(".settings-nav-item[data-tab=\'"+name+"\']");if(el){el.classList.add("active");}}'
            . 'if(name==="category"&&typeof initCategoryFilter==="function"){initCategoryFilter();}'
            . '}'
            . '(function(){'
            . 'var tab=new URLSearchParams(location.search).get("tab");'
            . 'if(!tab)return;'
            . 'var tabEl=document.querySelector(".settings-nav-item[data-tab=\'"+tab+"\']");'
            . 'if(tabEl){showSettingsTab(tab,tabEl);}'
            . '})();'
            . 'var _deleteForm=null;'
            . 'function statusDeleteConfirm(form){'
            . '_deleteForm=form;'
            . 'document.getElementById("dlg-status-delete-confirm").showModal();'
            . '}'
            . 'function statusDeleteExecute(){'
            . 'document.getElementById("dlg-status-delete-confirm").close();'
            . 'if(_deleteForm){_deleteForm.submit();}'
            . '}'
            . '</script>';

        $content = ''
            . $navStyles
            . '<div class="page-header">'
            . '<h1 class="title">テナント設定</h1>'
            . '<span class="badge badge-warn">管理者のみ</span>'
            . '</div>'
            . '<p class="muted" style="margin:0 0 12px;">対象代理店: ' . $tenantName . ' (' . $tenantCode . ')</p>'
            . $errorHtml
            . $successHtml
            . '<div class="settings-layout">'
            . $sidebar
            . '<div class="settings-content">'
            . $panelCategory
            . $panelStatusRenewal
            . $panelStatusAccident
            . $panelSalesCaseStatus
            . $panelProcedure
            . $panelPurpose
            . $panelActivityType
            . $panelRenewalMethod
            . $panelNotify
            . $panelUsers
            . $panelTarget
            . '</div>'
            . '</div>'
            . $tabJs;

        return Layout::render('テナント設定', $content, $layoutOptions);
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
        $createUrl     = $masterUrls['category_create'] ?? '';
        $updateUrl     = $masterUrls['category_update'] ?? '';
        $deactivateUrl = $masterUrls['category_deactivate'] ?? '';
        $activateUrl   = $masterUrls['category_activate'] ?? '';
        $deleteUrl     = $masterUrls['category_delete'] ?? '';

        $csrfCreate     = $masterCsrfs['category_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['category_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['category_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['category_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['category_delete'] ?? '';

        $rows = '';
        foreach ($productCategories as $row) {
            $id          = (int) ($row['id'] ?? 0);
            $csvValue    = Layout::escape((string) ($row['csv_value'] ?? ''));
            $displayName = Layout::escape((string) ($row['name'] ?? ''));
            $isActive    = (int) ($row['is_active'] ?? 1);
            $rowStyle    = $isActive === 0 ? ' style="opacity:0.55;"' : '';

            if ($isActive === 1) {
                $actionBtns = '<div style="display:flex;gap:4px;">'
                    . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="catEdit(this)">編集</button>'
                    . '<button type="button" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;color:var(--text-danger);"'
                    . ' onclick="catDelete(this)">削除</button>'
                    . '</div>';
            } else {
                $actionBtns = '<button type="button" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="catActivate(this)">有効化</button>';
            }

            $rows .= '<tr' . $rowStyle
                . ' data-id="' . $id . '"'
                . ' data-csv="' . $csvValue . '"'
                . ' data-display="' . $displayName . '"'
                . ' data-active="' . $isActive . '"'
                . '>'
                . '<td>' . $csvValue . '</td>'
                . '<td>' . $displayName . '</td>'
                . '<td>' . $actionBtns . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="muted" style="text-align:center;padding:8px;">登録なし</td></tr>';
        }

        $searchUi = ''
            . '<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">'
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
            . '<div class="tbl-wrap">'
            . '<table class="list-table" id="cat-table">'
            . '<thead><tr>'
            . '<th>種目種類値（CSV）</th>'
            . '<th>表示名</th>'
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" data-open-dialog="dlg-cat-create" style="margin-top:12px;">+ 種目を追加</button>';

        $addDialog = ''
            . '<dialog class="modal-dialog" id="dlg-cat-create" class="modal-dialog">'
            . '<div class="modal-head"><h2>種目を追加</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="dlg-cat-create">×</button></div>'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="category">'
            . '<div class="form-row"><div class="form-label">種目種類値（SJ-NET出力値）</div>'
            . '<input type="text" name="csv_value" required class="form-input"></div>'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" required class="form-input"></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" data-close-dialog="dlg-cat-create">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">追加</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-cat-create");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'document.querySelectorAll("[data-open-dialog=\"dlg-cat-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\"dlg-cat-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . '})();</script>';

        $editDialog = ''
            . '<dialog class="modal-dialog" id="dlg-cat-edit">'
            . '<div class="dlg-title">種目を編集</div>'
            . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
            . '<input type="hidden" name="id" id="cat-edit-id">'
            . '<input type="hidden" name="_tab" value="category">'
            . '<div class="form-row"><div class="form-label">種目種類値（CSV）</div>'
            . '<input type="text" name="csv_value" id="cat-edit-csv" required class="form-input"></div>'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" id="cat-edit-display" required class="form-input"></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-cat-edit\').close()">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">更新</button>'
            . '</div>'
            . '</form>'
            . '</dialog>';

        $deactivateForm = ''
            . '<form id="cat-deactivate-form" method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:none;">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
            . '<input type="hidden" name="id" id="cat-deactivate-id">'
            . '<input type="hidden" name="_tab" value="category">'
            . '</form>';

        $activateForm = ''
            . '<form id="cat-activate-form" method="post" action="' . Layout::escape($activateUrl) . '" style="display:none;">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
            . '<input type="hidden" name="id" id="cat-activate-id">'
            . '<input type="hidden" name="_tab" value="category">'
            . '</form>';

        $deleteForm = ''
            . '<form id="cat-delete-form" method="post" action="' . Layout::escape($deleteUrl) . '" style="display:none;">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDelete) . '">'
            . '<input type="hidden" name="id" id="cat-delete-id">'
            . '<input type="hidden" name="_tab" value="category">'
            . '</form>';

        $deleteDlg = ''
            . '<dialog class="modal-dialog" id="dlg-cat-delete-confirm">'
            . '<div class="dlg-title">種目の削除</div>'
            . '<p style="margin:8px 0 16px;">この種目を削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-cat-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="catDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>';

        $js = '<script>'
            . 'function catEdit(btn){'
            . 'var tr=btn.closest("tr");'
            . 'document.getElementById("cat-edit-id").value=tr.dataset.id;'
            . 'document.getElementById("cat-edit-csv").value=tr.dataset.csv;'
            . 'document.getElementById("cat-edit-display").value=tr.dataset.display;'
            . 'document.getElementById("dlg-cat-edit").showModal();'
            . '}'
            . 'function catDeactivate(btn){'
            . 'document.getElementById("cat-deactivate-id").value=btn.closest("tr").dataset.id;'
            . 'document.getElementById("cat-deactivate-form").submit();'
            . '}'
            . 'function catActivate(btn){'
            . 'document.getElementById("cat-activate-id").value=btn.closest("tr").dataset.id;'
            . 'document.getElementById("cat-activate-form").submit();'
            . '}'
            . 'function catDelete(btn){'
            . 'document.getElementById("cat-delete-id").value=btn.closest("tr").dataset.id;'
            . 'document.getElementById("dlg-cat-delete-confirm").showModal();'
            . '}'
            . 'function catDeleteExecute(){'
            . 'document.getElementById("dlg-cat-delete-confirm").close();'
            . 'document.getElementById("cat-delete-form").submit();'
            . '}'
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
            . 'var base=tr.dataset.active==="0"?"opacity:0.55;":"";'
            . 'tr.style.cssText=(matchText&&matchCat)?base:"display:none;";'
            . 'if(matchText&&matchCat)visible++;'
            . '});'
            . 'if(countEl)countEl.textContent=visible+"件表示中";'
            . '}'
            . 'window.catApplyFilter=apply;'
            . 'if(search)search.addEventListener("input",apply);'
            . 'if(filter)filter.addEventListener("change",apply);'
            . 'apply();'
            . '}'
            . '</script>';

        $note = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . '契約・案件で使用する保険種目を管理します。<br>'
            . '・非表示にした項目は新規登録時の選択肢に表示されませんが、既存案件の表示には影響しません。'
            . '</p>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">種目</div>'
            . $note
            . $searchUi
            . $table
            . $addBtn
            . '</div>'
            . $editDialog
            . $deactivateForm
            . $activateForm
            . $deleteForm
            . $deleteDlg
            . $addDialog
            . $js;
    }

    // ---- 対応状況マスタ ----

    /**
     * @param string                           $caseType 'renewal' | 'accident'
     * @param array<int, array<string, mixed>> $statuses 指定 caseType の対応状況一覧
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderStatusSection(
        string $caseType,
        array $statuses,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $tabValue = 'status-' . $caseType;
        $sectionTitle = '対応状況';
        $addBtnId     = 'status-' . $caseType . '-add-btn';
        $addFormId    = 'status-' . $caseType . '-add-form';
        $createUrl      = $masterUrls['status_create'] ?? '';
        $updateNameUrl  = $masterUrls['status_update_name'] ?? '';
        $deactivateUrl  = $masterUrls['status_deactivate'] ?? '';
        $activateUrl    = $masterUrls['status_activate'] ?? '';
        $deleteUrl      = $masterUrls['status_delete'] ?? '';
        $reorderUrl     = $masterUrls['status_reorder'] ?? '';

        $csrfCreate     = $masterCsrfs['status_create'] ?? '';
        $csrfUpdateName = $masterCsrfs['status_update_name'] ?? '';
        $csrfDeactivate = $masterCsrfs['status_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['status_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['status_delete'] ?? '';
        $csrfReorder    = $masterCsrfs['status_reorder'] ?? '';

        $html    = '';
        $dialogs = '';

        $buildSection = function (
            string $sectionTitle,
            string $caseType,
            string $addBtnId,
            string $addFormId,
            array $statuses,
            string $noteHtml
        ) use (
            $createUrl, $updateNameUrl, $deactivateUrl, $activateUrl, $deleteUrl, $reorderUrl,
            $csrfCreate, $csrfUpdateName, $csrfDeactivate, $csrfActivate, $csrfDelete, $csrfReorder,
            $tabValue,
            &$dialogs
        ): string {
            $rows = '';
            foreach ($statuses as $row) {
                $id           = (int) ($row['id'] ?? 0);
                $name         = (string) ($row['name'] ?? '');
                $nameEsc      = Layout::escape($name);
                $isActive     = (int) ($row['is_active'] ?? 1);
                $isCompleted  = (int) ($row['is_completed'] ?? 0);
                $completedChk = $isCompleted === 1 ? ' checked' : '';
                $dlgId        = 'dlg-status-' . $id;
                $rowOpacity   = ($isActive === 0) ? ' style="opacity:0.55;"' : '';

                // ── 並び順ボタン（全行共通） ──
                $reorderBtns = ''
                    . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="direction" value="up">'
                    . '<input type="hidden" name="_tab" value="' . Layout::escape($tabValue) . '">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="上へ">↑</button>'
                    . '</form>'
                    . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="direction" value="down">'
                    . '<input type="hidden" name="_tab" value="' . Layout::escape($tabValue) . '">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="下へ">↓</button>'
                    . '</form>';

                // ── バッジ ──
                $badges = '';
                if ($isCompleted === 1) {
                    $badges .= '<span class="badge badge-gray" style="font-size:10px;padding:2px 6px;">完了</span> ';
                }

                // ── 編集ダイアログ（表示名 + 完了扱い） ──
                $nameInputHtml = '<input type="text" name="name" value="' . $nameEsc . '" required maxlength="50" class="form-input">';
                $dialogs .= ''
                    . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                    . '<div class="dlg-title">対応状況を編集</div>'
                    . '<form method="post" action="' . Layout::escape($updateNameUrl) . '">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdateName) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="' . Layout::escape($tabValue) . '">'
                    . '<div class="form-row"><div class="form-label">表示名</div>' . $nameInputHtml . '</div>'
                    . '<div class="form-row"><div class="form-label">完了扱い</div>'
                    . '<label><input type="checkbox" name="is_completed" value="1"' . $completedChk . '> 集計・ホーム除外対象にする</label></div>'
                    . '<div class="dlg-footer">'
                    . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                    . '<button type="submit" class="btn btn-primary">更新</button>'
                    . '</div>'
                    . '</form>'
                    . '</dialog>';

                // ── アクションボタン群 ──
                $actionBtns = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">';
                $actionBtns .= $reorderBtns;
                // 編集ボタンは保護有無を問わず表示（保護でも完了扱いトグル可）
                $actionBtns .= '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>';

                if ($isActive === 1) {
                    $actionBtns .= '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                        . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<input type="hidden" name="_tab" value="' . Layout::escape($tabValue) . '">'
                        . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">非表示</button>'
                        . '</form>';
                } else {
                    $actionBtns .= '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                        . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<input type="hidden" name="_tab" value="' . Layout::escape($tabValue) . '">'
                        . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">表示</button>'
                        . '</form>';
                }

                $actionBtns .= '</div>';

                $rows .= '<tr' . $rowOpacity . '>'
                    . '<td>' . $nameEsc . $badges . '</td>'
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
                . '<th></th>'
                . '</tr></thead>'
                . '<tbody>' . $rows . '</tbody>'
                . '</table></div>';

            $dlgCreateId = 'dlg-status-' . $caseType . '-create';
            $addBtn = '<button class="btn btn-primary" data-open-dialog="' . $dlgCreateId . '" style="margin-top:12px;">+ 対応状況を追加</button>';

            $addDialog = ''
                . '<dialog class="modal-dialog" id="' . $dlgCreateId . '" class="modal-dialog">'
                . '<div class="modal-head"><h2>対応状況を追加</h2>'
                . '<button type="button" class="modal-close" data-close-dialog="' . $dlgCreateId . '">×</button></div>'
                . '<form method="post" action="' . Layout::escape($createUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
                . '<input type="hidden" name="_tab" value="' . Layout::escape($tabValue) . '">'
                . '<input type="hidden" name="case_type" value="' . Layout::escape($caseType) . '">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="name" required maxlength="50" class="form-input"></div>'
                . '<div class="form-row"><div class="form-label">完了扱い</div>'
                . '<label><input type="checkbox" name="is_completed" value="1"> 集計・ホーム除外対象にする</label></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" data-close-dialog="' . $dlgCreateId . '">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">追加</button>'
                . '</div>'
                . '</form>'
                . '</dialog>'
                . '<script>(function(){'
                . 'var dlg=document.getElementById("' . $dlgCreateId . '");'
                . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
                . 'document.querySelectorAll("[data-open-dialog=\"' . $dlgCreateId . '\"]").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
                . 'dlg.querySelectorAll("[data-close-dialog=\"' . $dlgCreateId . '\"]").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
                    . '})();</script>';

            return ''
                . '<div class="card" style="margin-bottom:16px;">'
                . '<div class="detail-section-title">' . Layout::escape($sectionTitle) . '</div>'
                . $noteHtml
                . $table
                . $addBtn
                . '</div>'
                . $addDialog;
        };

        $targetLabel = $caseType === 'renewal' ? '満期案件' : '事故案件';
        $statusNote = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . $targetLabel . '詳細の「対応状況」プルダウンに表示される選択肢を管理します。<br>'
            . '・「完了扱い」にチェックすると、その状態はダッシュボード集計・通知バッチ・リマインダーから除外されます。<br>'
            . '・「非表示」にした項目は新規登録時の選択肢から除外されますが、既存データの表示には影響しません。'
            . '</p>';
        $html .= $buildSection($sectionTitle, $caseType, $addBtnId, $addFormId, $statuses, $statusNote);

        $dialogs .= ''
            . '<dialog class="modal-dialog" id="dlg-status-delete-confirm">'
            . '<div class="dlg-title">対応状況の削除</div>'
            . '<p style="margin:8px 0 16px;">この対応状況を削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-status-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="statusDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>';

        return $html . $dialogs;
    }

    // ---- 見込案件ステータスマスタ ----

    /**
     * 見込案件ステータス管理セクション。
     * コードはシステム固定（open/negotiating/won/lost/on_hold）のため、
     * 新規追加・削除は提供せず、表示名変更・有効無効切替・並び順変更のみ。
     *
     * @param array<int, array<string, mixed>> $salesCaseStatuses
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderSalesCaseStatusSection(
        array $salesCaseStatuses,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl     = $masterUrls['sales_case_status_create'] ?? '';
        $updateUrl     = $masterUrls['sales_case_status_update'] ?? '';
        $deactivateUrl = $masterUrls['sales_case_status_deactivate'] ?? '';
        $activateUrl   = $masterUrls['sales_case_status_activate'] ?? '';
        $deleteUrl     = $masterUrls['sales_case_status_delete'] ?? '';
        $reorderUrl    = $masterUrls['sales_case_status_reorder'] ?? '';

        $csrfCreate     = $masterCsrfs['sales_case_status_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['sales_case_status_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['sales_case_status_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['sales_case_status_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['sales_case_status_delete'] ?? '';
        $csrfReorder    = $masterCsrfs['sales_case_status_reorder'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($salesCaseStatuses as $row) {
            $id          = (int) ($row['id'] ?? 0);
            $name        = (string) ($row['name'] ?? '');
            $nameEsc     = Layout::escape($name);
            $isActive    = (int) ($row['is_active'] ?? 1);
            $isCompleted = (int) ($row['is_completed'] ?? 0);
            $isProtected = (int) ($row['is_protected'] ?? 0);
            $dlgId       = 'dlg-sc-status-' . $id;
            $rowStyle    = $isActive === 0 ? ' style="opacity:0.55;"' : '';
            $completedChk = $isCompleted === 1 ? ' checked' : '';

            // 並び順ボタン
            $reorderBtns = ''
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="up">'
                . '<input type="hidden" name="_tab" value="sales-case-status">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="上へ">↑</button>'
                . '</form>'
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="down">'
                . '<input type="hidden" name="_tab" value="sales-case-status">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="下へ">↓</button>'
                . '</form>';

            // 編集ダイアログ（表示名 + 完了扱い。保護フラグは固定）
            $dialogs .= ''
                . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                . '<div class="dlg-title">対応状況を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="sales-case-status">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="name" value="' . $nameEsc . '" required maxlength="50" class="form-input"></div>'
                . '<div class="form-row"><div class="form-label">完了扱い</div>'
                . '<label><input type="checkbox" name="is_completed" value="1"' . $completedChk . '> 集計・ホーム除外対象にする</label></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            // アクションボタン群
            $actionBtns = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">';
            $actionBtns .= $reorderBtns;
            $actionBtns .= '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>';

            if ($isActive === 1) {
                $actionBtns .= '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="sales-case-status">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">非表示</button>'
                    . '</form>';
            } else {
                $actionBtns .= '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="sales-case-status">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">表示</button>'
                    . '</form>';
            }
            $actionBtns .= '</div>';

            $completedBadge = $isCompleted === 1 ? ' <span class="badge badge-gray" style="font-size:10px;">完了</span>' : '';

            $rows .= '<tr' . $rowStyle . '>'
                . '<td>' . $nameEsc . $completedBadge . '</td>'
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
            . '<th style="width:340px;"></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" data-open-dialog="dlg-sc-status-create" style="margin-top:12px;">+ 対応状況を追加</button>';

        $addDialog = ''
            . '<dialog class="modal-dialog" id="dlg-sc-status-create">'
            . '<div class="modal-head"><h2>対応状況を追加</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="dlg-sc-status-create">×</button></div>'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="sales-case-status">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" required maxlength="50" class="form-input"></div>'
            . '<div class="form-row"><div class="form-label">完了扱い</div>'
            . '<label><input type="checkbox" name="is_completed" value="1"> 集計・ホーム除外対象にする</label></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" data-close-dialog="dlg-sc-status-create">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">追加</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-sc-status-create");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'document.querySelectorAll("[data-open-dialog=\"dlg-sc-status-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\"dlg-sc-status-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . '})();</script>';

        $deleteDlg = ''
            . '<dialog class="modal-dialog" id="dlg-sc-status-delete-confirm">'
            . '<div class="dlg-title">対応状況の削除</div>'
            . '<p style="margin:8px 0 16px;">このステータスを削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-sc-status-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="salesCaseStatusDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>'
            . '<script>'
            . 'var _salesCaseStatusDeleteForm=null;'
            . 'function salesCaseStatusDeleteConfirm(form){_salesCaseStatusDeleteForm=form;document.getElementById("dlg-sc-status-delete-confirm").showModal();}'
            . 'function salesCaseStatusDeleteExecute(){document.getElementById("dlg-sc-status-delete-confirm").close();if(_salesCaseStatusDeleteForm){_salesCaseStatusDeleteForm.submit();}}'
            . '</script>';

        $note = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . '見込案件詳細の「対応状況」プルダウンに表示される選択肢を管理します。<br>'
            . '・「完了扱い」にチェックすると、そのステータスはダッシュボード集計から除外されます。<br>'
            . '・「非表示」にした項目は新規登録時の選択肢から除外されますが、既存データの表示には影響しません。'
            . '</p>';

        return ''
            . '<div class="card" style="margin-bottom:16px;">'
            . '<div class="detail-section-title">対応状況</div>'
            . $note
            . $table
            . $addBtn
            . '</div>'
            . $dialogs
            . $addDialog
            . $deleteDlg;
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
        $deleteUrl      = $masterUrls['procedure_method_delete'] ?? '';
        $reorderUrl     = $masterUrls['procedure_method_reorder'] ?? '';

        $csrfCreate     = $masterCsrfs['procedure_method_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['procedure_method_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['procedure_method_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['procedure_method_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['procedure_method_delete'] ?? '';
        $csrfReorder    = $masterCsrfs['procedure_method_reorder'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($procedureMethods as $row) {
            $id       = (int) ($row['id'] ?? 0);
            $label    = Layout::escape((string) ($row['name'] ?? ''));
            $isActive = (int) ($row['is_active'] ?? 1);
            $dlgId    = 'dlg-procedure-' . $id;
            $rowStyle = $isActive === 0 ? ' style="opacity:0.55;"' : '';

            // 編集ダイアログ
            $dialogs .= ''
                . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                . '<div class="dlg-title">手続き方法を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="procedure">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="name" value="' . $label . '" required class="form-input"></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            // ↑↓ ボタン
            $reorderBtns = ''
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="up">'
                . '<input type="hidden" name="_tab" value="procedure">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="上へ">↑</button>'
                . '</form>'
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="down">'
                . '<input type="hidden" name="_tab" value="procedure">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="下へ">↓</button>'
                . '</form>';

            $actionBtns = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">'
                . $reorderBtns;

            if ($isActive === 1) {
                $actionBtns .= '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="procedure">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">非表示</button>'
                    . '</form>';
            } else {
                $actionBtns .= '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="procedure">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">表示</button>'
                    . '</form>';
            }

            $actionBtns .= '</div>';

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
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" data-open-dialog="dlg-procedure-create" style="margin-top:12px;">+ 手続き方法を追加</button>';

        $addDialog = ''
            . '<dialog class="modal-dialog" id="dlg-procedure-create" class="modal-dialog">'
            . '<div class="modal-head"><h2>手続き方法を追加</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="dlg-procedure-create">×</button></div>'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="procedure">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" required class="form-input"></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" data-close-dialog="dlg-procedure-create">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">追加</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-procedure-create");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'document.querySelectorAll("[data-open-dialog=\"dlg-procedure-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\"dlg-procedure-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . '})();</script>';

        $deleteDlg = ''
            . '<dialog class="modal-dialog" id="dlg-procedure-delete-confirm">'
            . '<div class="dlg-title">手続き方法の削除</div>'
            . '<p style="margin:8px 0 16px;">この手続き方法を削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-procedure-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="procedureDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>'
            . '<script>'
            . 'var _procedureDeleteForm=null;'
            . 'function procedureDeleteConfirm(form){_procedureDeleteForm=form;document.getElementById("dlg-procedure-delete-confirm").showModal();}'
            . 'function procedureDeleteExecute(){document.getElementById("dlg-procedure-delete-confirm").close();if(_procedureDeleteForm){_procedureDeleteForm.submit();}}'
            . '</script>';

        $note = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . '満期案件詳細の「手続き方法」プルダウンに表示される選択肢を管理します。<br>'
            . '・非表示にした項目は新規登録時の選択肢に表示されませんが、既存案件の表示には影響しません。'
            . '</p>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">手続き方法</div>'
            . $note
            . $table
            . $addBtn
            . '</div>'
            . $dialogs
            . $addDialog
            . $deleteDlg;
    }

    // ---- 活動種別マスタ ----

    /**
     * @param array<int, array<string, mixed>> $activityTypes
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderActivityTypeSection(
        array $activityTypes,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl      = $masterUrls['activity_type_create'] ?? '';
        $updateUrl      = $masterUrls['activity_type_update'] ?? '';
        $deactivateUrl  = $masterUrls['activity_type_deactivate'] ?? '';
        $activateUrl    = $masterUrls['activity_type_activate'] ?? '';
        $deleteUrl      = $masterUrls['activity_type_delete'] ?? '';
        $reorderUrl     = $masterUrls['activity_type_reorder'] ?? '';

        $csrfCreate     = $masterCsrfs['activity_type_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['activity_type_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['activity_type_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['activity_type_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['activity_type_delete'] ?? '';
        $csrfReorder    = $masterCsrfs['activity_type_reorder'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($activityTypes as $row) {
            $id       = (int) ($row['id'] ?? 0);
            $label    = Layout::escape((string) ($row['name'] ?? ''));
            $isActive = (int) ($row['is_active'] ?? 1);
            $dlgId    = 'dlg-activity-type-' . $id;
            $rowStyle = $isActive === 0 ? ' style="opacity:0.55;"' : '';

            $dialogs .= ''
                . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                . '<div class="dlg-title">活動種別を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="activity-type">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="name" value="' . $label . '" required class="form-input"></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            $reorderBtns = ''
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="up">'
                . '<input type="hidden" name="_tab" value="activity-type">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="上へ">↑</button>'
                . '</form>'
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="down">'
                . '<input type="hidden" name="_tab" value="activity-type">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="下へ">↓</button>'
                . '</form>';

            $actionBtns = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">'
                . $reorderBtns;

            if ($isActive === 1) {
                $actionBtns .= '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="activity-type">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">非表示</button>'
                    . '</form>';
            } else {
                $actionBtns .= '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="activity-type">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">表示</button>'
                    . '</form>';
            }

            $actionBtns .= '</div>';

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
            . '<thead><tr><th>表示名</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" data-open-dialog="dlg-activity-type-create" style="margin-top:12px;">+ 活動種別を追加</button>';

        $addDialog = ''
            . '<dialog class="modal-dialog" id="dlg-activity-type-create">'
            . '<div class="modal-head"><h2>活動種別を追加</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="dlg-activity-type-create">×</button></div>'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="activity-type">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" required class="form-input"></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" data-close-dialog="dlg-activity-type-create">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">追加</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-activity-type-create");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'document.querySelectorAll("[data-open-dialog=\'dlg-activity-type-create\']").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\'dlg-activity-type-create\']").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . '})();</script>';

        $deleteDlg = ''
            . '<dialog class="modal-dialog" id="dlg-activity-type-delete-confirm">'
            . '<div class="dlg-title">活動種別の削除</div>'
            . '<p style="margin:8px 0 16px;">この活動種別を削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-activity-type-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="activityTypeDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>'
            . '<script>'
            . 'var _activityTypeDeleteForm=null;'
            . 'function activityTypeDeleteConfirm(form){_activityTypeDeleteForm=form;document.getElementById("dlg-activity-type-delete-confirm").showModal();}'
            . 'function activityTypeDeleteExecute(){document.getElementById("dlg-activity-type-delete-confirm").close();if(_activityTypeDeleteForm){_activityTypeDeleteForm.submit();}}'
            . '</script>';

        $note = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . '活動登録・活動詳細の「活動種別」プルダウンに表示される選択肢を管理します。<br>'
            . '・非表示にした項目は新規登録時の選択肢に表示されませんが、既存案件の表示には影響しません。'
            . '</p>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">活動種別</div>'
            . $note
            . $table
            . $addBtn
            . '</div>'
            . $dialogs
            . $addDialog
            . $deleteDlg;
    }

    // ---- 更改方法マスタ ----

    /**
     * @param array<int, array<string, mixed>> $renewalMethods
     * @param array<string, string>            $masterCsrfs
     * @param array<string, string>            $masterUrls
     */
    private static function renderRenewalMethodSection(
        array $renewalMethods,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $createUrl      = $masterUrls['renewal_method_create'] ?? '';
        $updateUrl      = $masterUrls['renewal_method_update'] ?? '';
        $deactivateUrl  = $masterUrls['renewal_method_deactivate'] ?? '';
        $activateUrl    = $masterUrls['renewal_method_activate'] ?? '';
        $deleteUrl      = $masterUrls['renewal_method_delete'] ?? '';
        $reorderUrl     = $masterUrls['renewal_method_reorder'] ?? '';

        $csrfCreate     = $masterCsrfs['renewal_method_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['renewal_method_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['renewal_method_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['renewal_method_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['renewal_method_delete'] ?? '';
        $csrfReorder    = $masterCsrfs['renewal_method_reorder'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($renewalMethods as $row) {
            $id       = (int) ($row['id'] ?? 0);
            $label    = Layout::escape((string) ($row['name'] ?? ''));
            $isActive = (int) ($row['is_active'] ?? 1);
            $dlgId    = 'dlg-renewal-method-' . $id;
            $rowStyle = $isActive === 0 ? ' style="opacity:0.55;"' : '';

            $dialogs .= ''
                . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                . '<div class="dlg-title">更改方法を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="renewal-method">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="name" value="' . $label . '" required class="form-input"></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            $reorderBtns = ''
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="up">'
                . '<input type="hidden" name="_tab" value="renewal-method">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="上へ">↑</button>'
                . '</form>'
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="down">'
                . '<input type="hidden" name="_tab" value="renewal-method">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="下へ">↓</button>'
                . '</form>';

            $actionBtns = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">'
                . $reorderBtns;

            if ($isActive === 1) {
                $actionBtns .= '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="renewal-method">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">非表示</button>'
                    . '</form>';
            } else {
                $actionBtns .= '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="renewal-method">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">表示</button>'
                    . '</form>';
            }

            $actionBtns .= '</div>';

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
            . '<thead><tr><th>表示名</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" data-open-dialog="dlg-renewal-method-create" style="margin-top:12px;">+ 更改方法を追加</button>';

        $addDialog = ''
            . '<dialog class="modal-dialog" id="dlg-renewal-method-create">'
            . '<div class="modal-head"><h2>更改方法を追加</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="dlg-renewal-method-create">×</button></div>'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="renewal-method">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" required class="form-input"></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" data-close-dialog="dlg-renewal-method-create">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">追加</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-renewal-method-create");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'document.querySelectorAll("[data-open-dialog=\'dlg-renewal-method-create\']").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\'dlg-renewal-method-create\']").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . '})();</script>';

        $deleteDlg = ''
            . '<dialog class="modal-dialog" id="dlg-renewal-method-delete-confirm">'
            . '<div class="dlg-title">更改方法の削除</div>'
            . '<p style="margin:8px 0 16px;">この更改方法を削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-renewal-method-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="renewalMethodDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>'
            . '<script>'
            . 'var _renewalMethodDeleteForm=null;'
            . 'function renewalMethodDeleteConfirm(form){_renewalMethodDeleteForm=form;document.getElementById("dlg-renewal-method-delete-confirm").showModal();}'
            . 'function renewalMethodDeleteExecute(){document.getElementById("dlg-renewal-method-delete-confirm").close();if(_renewalMethodDeleteForm){_renewalMethodDeleteForm.submit();}}'
            . '</script>';

        $note = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . '満期案件詳細の「更改方法」プルダウンに表示される選択肢を管理します。<br>'
            . '・非表示にした項目は新規登録時の選択肢に表示されませんが、既存案件の表示には影響しません。'
            . '</p>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">更改方法</div>'
            . $note
            . $table
            . $addBtn
            . '</div>'
            . $dialogs
            . $addDialog
            . $deleteDlg;
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
        $deleteUrl      = $masterUrls['purpose_type_delete'] ?? '';
        $reorderUrl     = $masterUrls['purpose_type_reorder'] ?? '';

        $csrfCreate     = $masterCsrfs['purpose_type_create'] ?? '';
        $csrfUpdate     = $masterCsrfs['purpose_type_update'] ?? '';
        $csrfDeactivate = $masterCsrfs['purpose_type_deactivate'] ?? '';
        $csrfActivate   = $masterCsrfs['purpose_type_activate'] ?? '';
        $csrfDelete     = $masterCsrfs['purpose_type_delete'] ?? '';
        $csrfReorder    = $masterCsrfs['purpose_type_reorder'] ?? '';

        $rows    = '';
        $dialogs = '';
        foreach ($purposeTypes as $row) {
            $id       = (int) ($row['id'] ?? 0);
            $label    = Layout::escape((string) ($row['name'] ?? ''));
            $isActive = (int) ($row['is_active'] ?? 1);
            $dlgId    = 'dlg-purpose-' . $id;
            $rowStyle = $isActive === 0 ? ' style="opacity:0.55;"' : '';

            // 編集ダイアログ
            $dialogs .= ''
                . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                . '<div class="dlg-title">用件区分を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="_tab" value="purpose">'
                . '<div class="form-row"><div class="form-label">表示名</div>'
                . '<input type="text" name="name" value="' . $label . '" required class="form-input"></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">更新</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            // ↑↓ ボタン
            $reorderBtns = ''
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="up">'
                . '<input type="hidden" name="_tab" value="purpose">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="上へ">↑</button>'
                . '</form>'
                . '<form method="post" action="' . Layout::escape($reorderUrl) . '" style="display:inline;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfReorder) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="direction" value="down">'
                . '<input type="hidden" name="_tab" value="purpose">'
                . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:12px;" title="下へ">↓</button>'
                . '</form>';

            $actionBtns = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">'
                . $reorderBtns;

            if ($isActive === 1) {
                $actionBtns .= '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                    . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                    . '<form method="post" action="' . Layout::escape($deactivateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfDeactivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="purpose">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">非表示</button>'
                    . '</form>';
            } else {
                $actionBtns .= '<form method="post" action="' . Layout::escape($activateUrl) . '" style="display:inline;">'
                    . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfActivate) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="_tab" value="purpose">'
                    . '<button type="submit" class="btn btn-ghost btn-sm" style="padding:3px 10px;font-size:11px;">表示</button>'
                    . '</form>';
            }

            $actionBtns .= '</div>';

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
            . '<th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        $addBtn = '<button class="btn btn-primary" data-open-dialog="dlg-purpose-create" style="margin-top:12px;">+ 用件区分を追加</button>';

        $addDialog = ''
            . '<dialog class="modal-dialog" id="dlg-purpose-create" class="modal-dialog">'
            . '<div class="modal-head"><h2>用件区分を追加</h2>'
            . '<button type="button" class="modal-close" data-close-dialog="dlg-purpose-create">×</button></div>'
            . '<form method="post" action="' . Layout::escape($createUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfCreate) . '">'
            . '<input type="hidden" name="_tab" value="purpose">'
            . '<div class="form-row"><div class="form-label">表示名</div>'
            . '<input type="text" name="name" required class="form-input"></div>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" data-close-dialog="dlg-purpose-create">キャンセル</button>'
            . '<button type="submit" class="btn btn-primary">追加</button>'
            . '</div>'
            . '</form>'
            . '</dialog>'
            . '<script>(function(){'
            . 'var dlg=document.getElementById("dlg-purpose-create");'
            . 'if(!dlg||typeof dlg.showModal!=="function"){return;}'
            . 'document.querySelectorAll("[data-open-dialog=\"dlg-purpose-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(!dlg.open)dlg.showModal();});});'
            . 'dlg.querySelectorAll("[data-close-dialog=\"dlg-purpose-create\"]").forEach(function(btn){btn.addEventListener("click",function(){if(dlg.open)dlg.close();});});'
            . '})();</script>';

        $deleteDlg = ''
            . '<dialog class="modal-dialog" id="dlg-purpose-delete-confirm">'
            . '<div class="dlg-title">用件区分の削除</div>'
            . '<p style="margin:8px 0 16px;">この用件区分を削除しますか？<br>削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-purpose-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="purposeDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>'
            . '<script>'
            . 'var _purposeDeleteForm=null;'
            . 'function purposeDeleteConfirm(form){_purposeDeleteForm=form;document.getElementById("dlg-purpose-delete-confirm").showModal();}'
            . 'function purposeDeleteExecute(){document.getElementById("dlg-purpose-delete-confirm").close();if(_purposeDeleteForm){_purposeDeleteForm.submit();}}'
            . '</script>';

        $note = '<p class="muted" style="margin:0 0 12px;font-size:13px;">'
            . '活動登録・活動詳細の「用件区分」プルダウンに表示される選択肢を管理します。<br>'
            . '・非表示にした項目は新規登録時の選択肢に表示されませんが、既存案件の表示には影響しません。'
            . '</p>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">用件区分</div>'
            . $note
            . $table
            . $addBtn
            . '</div>'
            . $dialogs
            . $addDialog
            . $deleteDlg;
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
            . '<div class="card">'
            . '<div class="detail-section-title">満期通知</div>'
            . '<div class="form-row">'
            . '<div style="display:flex;align-items:center;gap:12px;">'
            . '<label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;cursor:pointer;">'
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
            . '<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">LINE WORKS の Webhook URL を入力してください。通知先を変更した場合は対応する URL に更新してください。</div>'
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
            . '<div class="card">'
            . '<div class="detail-section-title">事故通知</div>'
            . '<div class="form-row">'
            . '<div style="display:flex;align-items:center;gap:12px;">'
            . '<label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;cursor:pointer;">'
            . '<input type="checkbox" name="accident_is_enabled" value="1"' . $accidentEnabledAttr . '>'
            . '事故受付通知'
            . '</label>'
            . '<span class="' . $accidentBadgeClass . '">' . $accidentBadgeText . '</span>'
            . '</div>'
            . '<div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">新規事故案件が受付されたときに通知します。</div>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">通知先</div>'
            . '<select name="accident_provider_type" class="form-select" style="max-width:240px;">' . $accidentProviderOptions . '</select>'
            . '</div>'
            . '<div class="form-row">'
            . '<div class="form-label">Webhook URL</div>'
            . '<input class="form-input" type="url" name="accident_webhook_url" value="' . $accidentWebhook . '" placeholder="https://hooks.worksmobile.com/r/...">'
            . '<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">満期通知と別のチャンネルに送信する場合は個別に設定してください。空欄の場合は満期通知と同じ Webhook URL を使用します。</div>'
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
                . '<label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;cursor:pointer;">'
                . '<input type="checkbox" name="' . $fieldPrefix . '_enabled" value="1"' . $checkedAttr . '>'
                . Layout::escape($meta['label'])
                . '</label>'
                . '<span class="' . $badgeClass . '">' . $badgeText . '</span>'
                . '</div>'
                . '<div style="display:flex;align-items:center;gap:8px;font-size:13px;">'
                . '<span style="color:var(--text-secondary);">満期</span>'
                . self::daySelectHtml($fieldPrefix . '_days', $days)
                . '<span style="color:var(--text-secondary);">日前から通知</span>'
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
        $updateUrl  = $masterUrls['user_update'] ?? '';
        $csrfUpdate = $masterCsrfs['user_update'] ?? '';

        $rows    = '';
        $dialogs = '';

        foreach ($tenantUsers as $user) {
            $userId      = (int) ($user['id'] ?? 0);
            $name        = Layout::escape((string) ($user['name'] ?? ''));
            $displayName = Layout::escape((string) ($user['display_name'] ?? ''));
            $email       = Layout::escape((string) ($user['email'] ?? ''));
            $role        = (string) ($user['role'] ?? '');
            $sjnetCode   = Layout::escape((string) ($user['sjnet_code'] ?? ''));
            $isSales     = (int) ($user['is_sales']  ?? 0);
            $isOffice    = (int) ($user['is_office'] ?? 0);

            $roleLabel   = match ($role) {
                'admin'  => '管理者',
                'member' => 'メンバー',
                default  => Layout::escape($role),
            };
            $bizRoleLabel = match (true) {
                $isSales === 1 && $isOffice === 1 => '営業・事務',
                $isSales === 1                    => '営業',
                $isOffice === 1                   => '事務',
                default                           => '—',
            };
            $effectiveName = $displayName !== '' ? $displayName : $name;
            $dlgId = 'dlg-user-' . $userId;

            $roleAdminSelected  = $role === 'admin'  ? ' selected' : '';
            $roleMemberSelected = $role === 'member' ? ' selected' : '';

            $dialogs .= ''
                . '<dialog class="modal-dialog" id="' . $dlgId . '">'
                . '<div class="dlg-title">ユーザー設定を編集</div>'
                . '<form method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfUpdate) . '">'
                . '<input type="hidden" name="user_id" value="' . $userId . '">'
                . '<input type="hidden" name="_tab" value="users">'
                . '<div class="form-row"><div class="form-label">業務表示名</div>'
                . '<input type="text" name="display_name" value="' . $displayName . '" class="form-input" placeholder="空欄にするとアカウント名を使用" maxlength="100"></div>'
                . '<div class="form-row"><div class="form-label">システムロール</div>'
                . '<select name="role" class="form-input">'
                . '<option value="member"' . $roleMemberSelected . '>メンバー</option>'
                . '<option value="admin"' . $roleAdminSelected . '>管理者</option>'
                . '</select></div>'
                . '<div class="form-row"><div class="form-label">代理店コード</div>'
                . '<input type="text" name="sjnet_code" value="' . $sjnetCode . '" class="form-input" placeholder="未設定の場合は空欄" maxlength="20"></div>'
                . '<div class="form-row"><div class="form-label">業務ロール</div>'
                . '<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><input type="checkbox" name="is_sales" value="1"' . ($isSales ? ' checked' : '') . '> 営業</label>'
                . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_office" value="1"' . ($isOffice ? ' checked' : '') . '> 事務</label></div>'
                . '<div class="dlg-footer">'
                . '<button type="button" class="btn" onclick="this.closest(\'dialog\').close()">キャンセル</button>'
                . '<button type="submit" class="btn btn-primary">保存</button>'
                . '</div>'
                . '</form>'
                . '</dialog>';

            $rows .= '<tr>'
                . '<td>' . $effectiveName . '</td>'
                . '<td>' . $email . '</td>'
                . '<td>' . $roleLabel . '</td>'
                . '<td>' . ($sjnetCode !== '' ? $sjnetCode : '—') . '</td>'
                . '<td>' . $bizRoleLabel . '</td>'
                . '<td>'
                . '<button type="button" class="btn btn-sm" style="padding:3px 10px;font-size:11px;"'
                . ' onclick="document.getElementById(\'' . $dlgId . '\').showModal()">編集</button>'
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="muted" style="text-align:center;padding:8px;">ユーザーなし</td></tr>';
        }

        $table = ''
            . '<div class="tbl-wrap"><table class="list-table">'
            . '<thead><tr>'
            . '<th>業務表示名</th><th>メールアドレス</th><th>システムロール</th><th>代理店コード</th><th>業務ロール</th><th></th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table></div>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">ユーザー管理</div>'
            . '<p class="muted" style="margin-bottom:12px;font-size:13px;">テナント所属ユーザー（＝担当者）の業務表示名・ロール・代理店コードを管理します。代理店コードは満期一覧 CSV 取り込み時のマッピングに使用されます。</p>'
            . $table
            . '</div>'
            . $dialogs;
    }

    // ---- 目標管理セクション ----

    /**
     * @param array<int, array{staff_user_id: int|null, display_name: string, non_life_amount: int|null, life_amount: int|null}> $yearlyTargets
     * @param array<int, array{user_id: int, display_name: string}>                                                    $assignableUsers
     * @param array<int, int>                                                                                           $fiscalYearOptions
     * @param array<string, string>                                                                                     $masterCsrfs
     * @param array<string, string>                                                                                     $masterUrls
     */
    private static function renderSalesTargetSection(
        array $yearlyTargets,
        array $assignableUsers,
        int $selectedFy,
        array $fiscalYearOptions,
        array $masterCsrfs,
        array $masterUrls
    ): string {
        $saveUrl      = $masterUrls['sales_target_save']      ?? '';
        $bulkSaveUrl  = $masterUrls['sales_target_bulk_save'] ?? '';
        $deleteUrl    = $masterUrls['sales_target_delete']    ?? '';
        $settingsBase = rtrim($masterUrls['settings_base'] ?? '', '&');
        $csrfSave     = $masterCsrfs['sales_target_save']      ?? '';
        $csrfBulkSave = $masterCsrfs['sales_target_bulk_save'] ?? '';
        $csrfDelete   = $masterCsrfs['sales_target_delete']    ?? '';

        // 既存目標を staff_user_id をキーにしたマップに変換
        $targetMap = [];
        foreach ($yearlyTargets as $t) {
            $key = $t['staff_user_id'] === null ? 'team' : (string) $t['staff_user_id'];
            $targetMap[$key] = $t;
        }

        // --- 年度セレクター ---
        $fyOptions = '';
        foreach ($fiscalYearOptions as $fy) {
            $sel = $fy === $selectedFy ? ' selected' : '';
            $fyOptions .= '<option value="' . $fy . '"' . $sel . '>' . $fy . '年度</option>';
        }
        $fySelector = ''
            . '<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">'
            . '<label style="font-size:13px;font-weight:500;color:#334e68;">対象年度</label>'
            . '<select style="padding:4px 8px;border:1px solid #d9e2ec;border-radius:4px;font-size:13px;"'
            . ' onchange="location.href=' . "'" . Layout::escape($settingsBase) . "&tab=target&target_fy='+this.value" . '">'
            . $fyOptions
            . '</select>'
            . '</div>';

        // --- チーム全体目標 ---
        $teamTarget    = $targetMap['team'] ?? null;
        $teamNonLifeRaw = $teamTarget !== null ? $teamTarget['non_life_amount'] : null;
        $teamLifeRaw    = $teamTarget !== null ? $teamTarget['life_amount']     : null;
        $teamNlVal      = $teamNonLifeRaw !== null ? (string) $teamNonLifeRaw : '';
        $teamLfVal      = $teamLifeRaw    !== null ? (string) $teamLifeRaw    : '';
        $teamHasRecord  = $teamNlVal !== '' || $teamLfVal !== '';
        $teamTotalInit  = $teamHasRecord ? number_format(($teamNonLifeRaw ?? 0) + ($teamLifeRaw ?? 0)) . ' 円' : '';

        $teamCard = ''
            . '<div class="card" style="margin-bottom:16px;">'
            . '<div class="detail-section-title" style="margin-bottom:12px;">チーム全体目標</div>'
            . '<form method="post" action="' . Layout::escape($saveUrl) . '" class="target-form" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfSave) . '">'
            . '<input type="hidden" name="fiscal_year" value="' . $selectedFy . '">'
            . '<input type="hidden" name="staff_user_id" value="">'
            . '<div style="display:flex;align-items:center;gap:6px;">'
            . '<label style="font-size:13px;">損保目標（円）</label>'
            . '<input type="number" name="target_amount_nonlife" value="' . Layout::escape($teamNlVal) . '"'
            . ' min="0" step="1" placeholder="未設定（空欄可）" class="target-input-nonlife"'
            . ' style="width:140px;padding:5px 8px;border:1px solid #d9e2ec;border-radius:4px;font-size:13px;"'
            . ' oninput="targetSumUpdate(this.form)">'
            . '</div>'
            . '<div style="display:flex;align-items:center;gap:6px;">'
            . '<label style="font-size:13px;">生保目標（円）</label>'
            . '<input type="number" name="target_amount_life" value="' . Layout::escape($teamLfVal) . '"'
            . ' min="0" step="1" placeholder="未設定（空欄可）" class="target-input-life"'
            . ' style="width:140px;padding:5px 8px;border:1px solid #d9e2ec;border-radius:4px;font-size:13px;"'
            . ' oninput="targetSumUpdate(this.form)">'
            . '</div>'
            . '<div style="font-size:13px;color:#334e68;">'
            . '合計 <span class="target-sum-display" style="font-weight:600;">' . Layout::escape($teamTotalInit) . '</span>'
            . '</div>'
            . '<button type="submit" class="btn btn-primary btn-sm" style="padding:4px 14px;">保存</button>'
            . '</form>'
            . '</div>';

        // --- 担当者別目標テーブル（単一フォームで一括保存） ---
        // 入力欄は staff_targets[{uid}][nonlife] / staff_targets[{uid}][life] の形式で送信される。
        // 空欄は「目標未設定」を意味し、該当 target_type の既存レコードは論理削除される。
        $staffRows = '';
        foreach ($assignableUsers as $user) {
            $uid         = (int) $user['user_id'];
            $displayName = Layout::escape((string) $user['display_name']);
            $existing    = $targetMap[(string) $uid] ?? null;
            $nlVal       = '';
            $lfVal       = '';
            $totalInit   = '';
            if ($existing !== null) {
                $nl = $existing['non_life_amount'];
                $lf = $existing['life_amount'];
                $nlVal = $nl !== null ? (string) $nl : '';
                $lfVal = $lf !== null ? (string) $lf : '';
                $total = ($nl ?? 0) + ($lf ?? 0);
                if ($nlVal !== '' || $lfVal !== '') {
                    $totalInit = number_format($total) . ' 円';
                }
            }

            $nlName = 'staff_targets[' . $uid . '][nonlife]';
            $lfName = 'staff_targets[' . $uid . '][life]';

            $staffRows .= ''
                . '<tr class="target-row" style="border-bottom:1px solid #f0f4f8;">'
                . '<td style="padding:6px 8px;font-size:13px;">' . $displayName . '</td>'
                . '<td style="padding:6px 8px;">'
                . '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">'
                . '<span style="font-size:12px;color:#627d98;">損保</span>'
                . '<input type="number" name="' . $nlName . '" value="' . Layout::escape($nlVal) . '"'
                . ' min="0" step="1" placeholder="未設定（空欄可）" class="target-input-nonlife"'
                . ' style="width:120px;padding:4px 7px;border:1px solid #d9e2ec;border-radius:4px;font-size:13px;"'
                . ' oninput="targetRowSumUpdate(this)">'
                . '<span style="font-size:12px;color:#627d98;">生保</span>'
                . '<input type="number" name="' . $lfName . '" value="' . Layout::escape($lfVal) . '"'
                . ' min="0" step="1" placeholder="未設定（空欄可）" class="target-input-life"'
                . ' style="width:120px;padding:4px 7px;border:1px solid #d9e2ec;border-radius:4px;font-size:13px;"'
                . ' oninput="targetRowSumUpdate(this)">'
                . '<span style="font-size:11px;color:#334e68;min-width:110px;">合計 <span class="target-sum-display" style="font-weight:600;">' . Layout::escape($totalInit) . '</span></span>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        $hasStaffRows = $staffRows !== '';
        if (!$hasStaffRows) {
            $staffRows = '<tr><td colspan="2" class="muted" style="text-align:center;padding:8px;">担当者なし</td></tr>';
        }

        $bulkSaveBtn = $hasStaffRows
            ? '<div style="margin-bottom:12px;display:flex;justify-content:flex-end;">'
              . '<button type="submit" class="btn btn-primary btn-sm" style="padding:4px 18px;">保存</button>'
              . '</div>'
            : '';

        $staffCard = ''
            . '<div class="card">'
            . '<div class="detail-section-title" style="margin-bottom:4px;">担当者別目標</div>'
            . '<p class="muted" style="margin-bottom:12px;font-size:13px;">損保・生保の目標を個別に設定します。合計は自動計算されます。空欄にして保存すると、その項目は「目標未設定」になります。</p>'
            . '<form method="post" action="' . Layout::escape($bulkSaveUrl) . '" class="target-bulk-form">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfBulkSave) . '">'
            . '<input type="hidden" name="fiscal_year" value="' . $selectedFy . '">'
            . $bulkSaveBtn
            . '<div style="max-height:400px;overflow-y:auto;">'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th style="text-align:left;padding:6px 8px;font-size:12px;color:#627d98;border-bottom:1px solid #e3eaf2;width:140px;">担当者</th>'
            . '<th style="text-align:left;padding:6px 8px;font-size:12px;color:#627d98;border-bottom:1px solid #e3eaf2;">損保 / 生保 目標額（円）</th>'
            . '</tr></thead>'
            . '<tbody>' . $staffRows . '</tbody>'
            . '</table>'
            . '</div>'
            . '</form>'
            . '</div>';

        // 削除確認ダイアログ
        $deleteDialog = ''
            . '<dialog class="modal-dialog" id="dlg-target-delete-confirm">'
            . '<div class="dlg-title">チーム全体目標（損保・生保）を削除</div>'
            . '<p style="margin:0 0 16px;font-size:14px;">この年度のチーム全体目標（損保・生保）を削除しますか？削除すると戻せません。</p>'
            . '<div class="dlg-footer">'
            . '<button type="button" class="btn" onclick="document.getElementById(\'dlg-target-delete-confirm\').close()">キャンセル</button>'
            . '<button type="button" class="btn btn-danger" onclick="targetDeleteExecute()">削除する</button>'
            . '</div>'
            . '</dialog>';

        $deleteJs = ''
            . '<script>'
            . 'var _targetDeleteForm=null;'
            . 'function targetDeleteConfirm(form){_targetDeleteForm=form;document.getElementById(\'dlg-target-delete-confirm\').showModal();}'
            . 'function targetDeleteExecute(){document.getElementById(\'dlg-target-delete-confirm\').close();if(_targetDeleteForm){_targetDeleteForm.submit();}}'
            . 'function targetSumUpdate(form){'
            . 'var nl=form.querySelector(\'.target-input-nonlife\');'
            . 'var lf=form.querySelector(\'.target-input-life\');'
            . 'var out=form.querySelector(\'.target-sum-display\');'
            . 'if(!out)return;'
            . 'var n=nl&&nl.value!==\'\'?parseInt(nl.value,10):0;'
            . 'var l=lf&&lf.value!==\'\'?parseInt(lf.value,10):0;'
            . 'if(isNaN(n))n=0;if(isNaN(l))l=0;'
            . 'var sum=n+l;'
            . 'out.textContent=(sum>0||(nl&&nl.value!==\'\')||(lf&&lf.value!==\'\'))?sum.toLocaleString()+\' 円\':\'\';'
            . '}'
            // 担当者別目標（一括保存フォーム）用: 行スコープで合計を更新する。
            . 'function targetRowSumUpdate(el){'
            . 'var row=el.closest(\'tr\');'
            . 'if(!row)return;'
            . 'var nl=row.querySelector(\'.target-input-nonlife\');'
            . 'var lf=row.querySelector(\'.target-input-life\');'
            . 'var out=row.querySelector(\'.target-sum-display\');'
            . 'if(!out)return;'
            . 'var n=nl&&nl.value!==\'\'?parseInt(nl.value,10):0;'
            . 'var l=lf&&lf.value!==\'\'?parseInt(lf.value,10):0;'
            . 'if(isNaN(n))n=0;if(isNaN(l))l=0;'
            . 'var sum=n+l;'
            . 'out.textContent=(sum>0||(nl&&nl.value!==\'\')||(lf&&lf.value!==\'\'))?sum.toLocaleString()+\' 円\':\'\';'
            . '}'
            . '</script>';

        return ''
            . '<div class="card">'
            . '<div class="detail-section-title">目標管理</div>'
            . '<p class="muted" style="margin-bottom:16px;font-size:13px;">損保・生保それぞれの年度目標を設定します。合計 = 損保 + 生保 として自動算出されます。月次目標・件数目標は本画面では管理しません。</p>'
            . $fySelector
            . '</div>'
            . $teamCard
            . $staffCard
            . $deleteDialog
            . $deleteJs;
    }
}
