<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

/**
 * 成績管理：月次一括入力画面。
 *
 * 上段に指定精算月の既登録行（読み取り専用）、下段に新規登録フォーム行を並べる。
 * 行ごとの「保存」ボタンで Ajax POST し、成功時に行を読み取り専用化する。
 */
final class SalesPerformanceBulkView
{
    /**
     * @param array<int, array<string, mixed>> $existingRows  既登録（読み取り専用）行
     * @param array<int, array<string, mixed>> $customers     顧客 datalist 用
     * @param array<int, array<string, mixed>> $staffUsers    担当者 select 用
     * @param array<int, array<string, mixed>> $renewalCases  満期案件（損保新規登録行の先頭列で選択可）
     * @param array<int, string>               $months        精算月ドロップダウン候補
     * @param array<string, mixed>             $layoutOptions Layout::render に渡すオプション
     */
    public static function render(
        string $settlementMonth,
        string $formType,
        array $existingRows,
        array $customers,
        array $staffUsers,
        array $renewalCases,
        array $months,
        string $bulkUrl,
        string $rowSaveUrl,
        string $listUrl,
        string $csrfToken,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $fatalError,
        array $layoutOptions
    ): string {
        $settlementMonthEsc = Layout::escape($settlementMonth);
        $formTypeEsc        = $formType === 'life' ? 'life' : 'non_life';

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

        // 精算月ドロップダウン。現在月が一覧に無ければ先頭に追加する
        $monthSet = $months;
        if ($settlementMonth !== '' && !in_array($settlementMonth, $monthSet, true)) {
            array_unshift($monthSet, $settlementMonth);
        }
        if ($monthSet === []) {
            $monthSet[] = $settlementMonth !== '' ? $settlementMonth : date('Y-m');
        }
        $monthOptions = '';
        foreach ($monthSet as $m) {
            $sel = $m === $settlementMonth ? ' selected' : '';
            $monthOptions .= '<option value="' . Layout::escape($m) . '"' . $sel . '>' . Layout::escape($m) . '</option>';
        }

        // 業務区分切替
        $nonlifeChecked = $formTypeEsc === 'non_life' ? ' checked' : '';
        $lifeChecked    = $formTypeEsc === 'life' ? ' checked' : '';

        // 顧客 datalist
        $customerDlId = 'bulk-customers-dl';
        $customerDl   = '<datalist id="' . $customerDlId . '">';
        foreach ($customers as $row) {
            $cid   = (int) ($row['id'] ?? 0);
            $cname = (string) ($row['customer_name'] ?? '');
            if ($cid <= 0 || $cname === '') {
                continue;
            }
            $customerDl .= '<option value="' . Layout::escape($cname) . '" data-id="' . $cid . '">';
        }
        $customerDl .= '</datalist>';

        // 担当者 select 選択肢（行ごとの select で使い回すので文字列化）
        $staffOptionsHtml = '<option value="">未設定</option>';
        foreach ($staffUsers as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $staffOptionsHtml .= '<option value="' . $id . '">' . Layout::escape($name) . '</option>';
        }

        // 満期案件 datalist（損保のみで使用）
        $renewalDlId = 'bulk-renewal-dl';
        $renewalDl = '';
        if ($formTypeEsc === 'non_life') {
            $renewalDl = '<datalist id="' . $renewalDlId . '">';
            foreach ($renewalCases as $row) {
                $id             = (int) ($row['id'] ?? 0);
                $policyNoText   = (string) ($row['policy_no'] ?? '');
                $maturityDate   = (string) ($row['maturity_date'] ?? '');
                $customerNameRc = (string) ($row['customer_name'] ?? '');
                if ($id <= 0) {
                    continue;
                }
                $displayText = $customerNameRc . ' / ' . $maturityDate . ' / ' . $policyNoText;
                $renewalDl .= '<option value="' . Layout::escape($displayText) . '"'
                    . ' data-id="' . $id . '"'
                    . ' data-contract-id="' . (int) ($row['contract_id'] ?? 0) . '"'
                    . ' data-customer-id="' . (int) ($row['customer_id'] ?? 0) . '"'
                    . ' data-customer-name="' . Layout::escape($customerNameRc) . '"'
                    . ' data-assigned-staff-id="' . (int) ($row['assigned_staff_id'] ?? 0) . '"'
                    . ' data-policy-no="' . Layout::escape($policyNoText) . '"'
                    . ' data-product-type="' . Layout::escape((string) ($row['product_type'] ?? '')) . '"'
                    . ' data-insurance-category="' . Layout::escape((string) ($row['insurance_category'] ?? '')) . '"'
                    . ' data-policy-start-date="' . Layout::escape((string) ($row['policy_start_date'] ?? '')) . '"'
                    . ' data-prev-premium-amount="' . (int) ($row['prev_premium_amount'] ?? 0) . '"'
                    . '>';
            }
            $renewalDl .= '</datalist>';
        }

        // 上段：既登録行
        $existingHtml = self::renderExistingSection($existingRows, $formTypeEsc);

        // 下段：新規登録行（HTML は template として出力し JS で複製する）
        $newRowsHtml = self::renderNewSection($formTypeEsc, $staffOptionsHtml, $customerDlId, $renewalDlId, $settlementMonth);

        $header = '<div class="page-header">'
            . '<h1 class="page-title">成績管理 / 一括入力</h1>'
            . '<div class="page-actions"><a class="btn btn-ghost" href="' . Layout::escape($listUrl) . '">← 成績管理一覧に戻る</a></div>'
            . '</div>';

        $toolbar = '<form method="get" action="' . Layout::escape(explode('?', $bulkUrl, 2)[0]) . '" class="bulk-toolbar" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin:12px 0 16px;">'
            . self::routeHiddenFromUrl($bulkUrl)
            . '<label style="display:flex;flex-direction:column;gap:4px;"><span class="form-label">精算月</span>'
            . '<select name="settlement_month" class="compact-input w-sm" onchange="this.form.submit();">' . $monthOptions . '</select>'
            . '</label>'
            . '<div style="display:flex;flex-direction:column;gap:4px;">'
            . '<span class="form-label">業務区分</span>'
            . '<div style="display:flex;gap:12px;align-items:center;">'
            . '<label class="radio-inline"><input type="radio" name="form_type" value="non_life"' . $nonlifeChecked . ' onchange="this.form.submit();"> 損保</label>'
            . '<label class="radio-inline"><input type="radio" name="form_type" value="life"' . $lifeChecked . ' onchange="this.form.submit();"> 生保</label>'
            . '</div>'
            . '</div>'
            . '<noscript><button type="submit" class="btn btn-small">表示</button></noscript>'
            . '</form>';

        $content = '<div class="bulk-page-frame">'
            . $header
            . $noticeHtml
            . $toolbar
            . $customerDl
            . $renewalDl
            . $existingHtml
            . $newRowsHtml
            . '</div>'
            . self::renderStyles()
            . self::renderScript($rowSaveUrl, $csrfToken, $settlementMonth, $formTypeEsc);

        return Layout::render('成績管理 / 一括入力', $content, $layoutOptions);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function renderExistingSection(array $rows, string $formType): string
    {
        $bodyHtml = '';
        if ($rows === []) {
            $bodyHtml = '<tr><td colspan="5" class="muted" style="text-align:center;padding:12px;">この精算月の登録済み成績はまだありません。</td></tr>';
        } else {
            foreach ($rows as $row) {
                // 業務区分の絞り込み（source_type で簡易フィルタ）
                $sourceType = (string) ($row['source_type'] ?? '');
                if ($formType === 'life' && $sourceType !== 'life') {
                    continue;
                }
                if ($formType === 'non_life' && $sourceType !== 'non_life') {
                    continue;
                }

                $perfDate       = Layout::escape((string) ($row['performance_date'] ?? ''));
                $customerName   = Layout::escape((string) ($row['display_customer'] ?? $row['customer_name'] ?? ''));
                $productType    = Layout::escape((string) ($row['product_type'] ?? ''));
                $policyNo       = Layout::escape((string) ($row['policy_no_display'] ?? $row['policy_no'] ?? ''));
                $premium        = (int) ($row['premium_amount'] ?? 0);

                $bodyHtml .= '<tr>'
                    . '<td data-label="計上日">' . $perfDate . '</td>'
                    . '<td data-label="契約者名">' . $customerName . '</td>'
                    . '<td data-label="種目">' . $productType . '</td>'
                    . '<td data-label="証券番号">' . $policyNo . '</td>'
                    . '<td data-label="保険料" style="text-align:right;">' . number_format($premium) . '</td>'
                    . '</tr>';
            }
            if ($bodyHtml === '') {
                $bodyHtml = '<tr><td colspan="5" class="muted" style="text-align:center;padding:12px;">該当する登録済み成績はありません（業務区分で絞り込まれている可能性があります）。</td></tr>';
            }
        }

        return '<section class="card" style="margin-bottom:16px;">'
            . '<h2 class="card-title" style="margin:0 0 8px;">登録済み（読み取り専用）</h2>'
            . '<p class="muted" style="margin:0 0 8px;font-size:.9em;">編集・削除は成績管理一覧から行ってください。</p>'
            . '<div class="table-wrap">'
            . '<table class="table-fixed table-card list-table">'
            . '<colgroup>'
            . '<col style="width:100px;">'
            . '<col style="width:auto;">'
            . '<col style="width:130px;">'
            . '<col style="width:224px;">'
            . '<col style="width:112px;">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>計上日</th><th>契約者名</th><th>種目</th><th>証券番号</th><th style="text-align:right;">保険料</th>'
            . '</tr></thead>'
            . '<tbody>' . $bodyHtml . '</tbody>'
            . '</table>'
            . '</div>'
            . '</section>';
    }

    private static function renderNewSection(string $formType, string $staffOptionsHtml, string $customerDlId, string $renewalDlId, string $settlementMonth): string
    {
        // 損保フォームと生保フォームで列構成が異なる
        if ($formType === 'life') {
            $colgroup = '<colgroup>'
                . '<col style="width:150px;">'   // 申込日
                . '<col style="width:140px;">'   // 担当者
                . '<col style="width:220px;">'   // 契約者名
                . '<col style="width:160px;">'   // 種目
                . '<col style="width:120px;">'   // 保険料
                . '<col style="width:110px;">'   // 操作
                . '</colgroup>';
            $headerCols = '<th>申込日</th><th>担当者</th><th>契約者名</th><th>種目</th><th style="text-align:right;">保険料</th><th></th>';
            $emptyRow = self::renderLifeRow($staffOptionsHtml, $customerDlId);
        } else {
            $colgroup = '<colgroup>'
                . '<col style="width:220px;">'   // 満期案件
                . '<col style="width:150px;">'   // 計上日
                . '<col style="width:140px;">'   // 担当者
                . '<col style="width:220px;">'   // 契約者名
                . '<col style="width:160px;">'   // 種目
                . '<col style="width:170px;">'   // 証券番号
                . '<col style="width:150px;">'   // 始期日
                . '<col style="width:120px;">'   // 保険料
                . '<col style="width:150px;">'   // 精算月
                . '<col style="width:100px;">'   // 分割回数
                . '<col style="width:170px;">'   // 領収証番号
                . '<col style="width:110px;">'   // 操作
                . '</colgroup>';
            $headerCols = '<th>満期案件</th><th>計上日</th><th>担当者</th><th>契約者名</th><th>種目</th><th>証券番号</th><th>始期日</th><th style="text-align:right;">保険料</th><th>精算月</th><th>分割回数</th><th>領収証番号</th><th></th>';
            $emptyRow = self::renderNonlifeRow($staffOptionsHtml, $customerDlId, $renewalDlId, $settlementMonth);
        }

        $initialRows = '';
        for ($i = 0; $i < 3; $i++) {
            $initialRows .= $emptyRow;
        }

        return '<section class="card">'
            . '<h2 class="card-title" style="margin:0 0 8px;">新規登録</h2>'
            . '<p class="muted" style="margin:0 0 8px;">各行の保存ボタンで1件ずつ登録します。Tab：右へ / Enter：下行へ。</p>'
            . '<div class="table-wrap bulk-new-wrap">'
            . '<table class="bulk-new-table list-table">'
            . $colgroup
            . '<thead><tr>' . $headerCols . '</tr></thead>'
            . '<tbody id="bulk-new-tbody">' . $initialRows . '</tbody>'
            . '</table>'
            . '</div>'
            . '<div style="margin-top:8px;">'
            . '<button type="button" class="btn btn-small btn-secondary" id="bulk-add-row">+ 行を追加</button>'
            . '</div>'
            . '<template id="bulk-new-row-template">' . $emptyRow . '</template>'
            . '</section>';
    }

    private static function renderNonlifeRow(string $staffOptionsHtml, string $customerDlId, string $renewalDlId, string $settlementMonth): string
    {
        $settlementMonthVal = Layout::escape($settlementMonth);
        return '<tr class="bulk-row" data-form-type="non_life">'
            . '<td data-label="満期案件">'
            . '<input type="text" name="renewal_case_text" class="bulk-cell bulk-cell-renewal" list="' . $renewalDlId . '" autocomplete="off" placeholder="選択しない場合は新規">'
            . '<input type="hidden" name="renewal_case_id" value="">'
            . '<input type="hidden" name="contract_id" value="">'
            . '</td>'
            . '<td data-label="計上日"><input type="date" name="performance_date" class="bulk-cell"></td>'
            . '<td data-label="担当者"><select name="staff_id" class="bulk-cell">' . $staffOptionsHtml . '</select></td>'
            . '<td data-label="契約者名">'
            . '<input type="text" name="customer_name" class="bulk-cell bulk-cell-customer" list="' . $customerDlId . '" autocomplete="off">'
            . '<input type="hidden" name="customer_id" value="">'
            . '</td>'
            . '<td data-label="種目"><input type="text" name="product_type" class="bulk-cell"></td>'
            . '<td data-label="証券番号"><input type="text" name="policy_no" class="bulk-cell"></td>'
            . '<td data-label="始期日"><input type="date" name="policy_start_date" class="bulk-cell"></td>'
            . '<td data-label="保険料"><input type="number" name="premium_amount" class="bulk-cell" min="0" step="1" style="text-align:right;"></td>'
            . '<td data-label="精算月"><input type="month" name="settlement_month" class="bulk-cell" value="' . $settlementMonthVal . '"></td>'
            . '<td data-label="分割回数"><input type="number" name="installment_count" class="bulk-cell" min="1" max="255" step="1" style="text-align:right;"></td>'
            . '<td data-label="領収証番号"><input type="text" name="receipt_no" class="bulk-cell"></td>'
            . '<td data-label="" style="text-align:right;">'
            . '<button type="button" class="btn btn-small bulk-save">保存</button>'
            . '<div class="bulk-row-msg muted" style="display:none;font-size:.85em;margin-top:4px;"></div>'
            . '</td>'
            . '</tr>';
    }

    private static function renderLifeRow(string $staffOptionsHtml, string $customerDlId): string
    {
        return '<tr class="bulk-row" data-form-type="life">'
            . '<td data-label="申込日"><input type="date" name="application_date" class="bulk-cell"></td>'
            . '<td data-label="担当者"><select name="staff_id" class="bulk-cell">' . $staffOptionsHtml . '</select></td>'
            . '<td data-label="契約者名">'
            . '<input type="text" name="customer_name" class="bulk-cell bulk-cell-customer" list="' . $customerDlId . '" autocomplete="off">'
            . '<input type="hidden" name="customer_id" value="">'
            . '</td>'
            . '<td data-label="種目"><input type="text" name="product_type" class="bulk-cell"></td>'
            . '<td data-label="保険料"><input type="number" name="premium_amount" class="bulk-cell" min="0" step="1" style="text-align:right;"></td>'
            . '<td data-label="" style="text-align:right;">'
            . '<button type="button" class="btn btn-small bulk-save">保存</button>'
            . '<div class="bulk-row-msg muted" style="display:none;font-size:.85em;margin-top:4px;"></div>'
            . '</td>'
            . '</tr>';
    }

    private static function renderStyles(): string
    {
        return '<style>'
            . '.bulk-new-wrap{overflow-x:auto;}'
            . '.bulk-new-table{table-layout:fixed;width:max-content;min-width:100%;border-collapse:collapse;}'
            . '.bulk-new-table th,.bulk-new-table td{border:1px solid var(--border-light);padding:4px;vertical-align:top;}'
            . '.bulk-new-table input.bulk-cell,.bulk-new-table select.bulk-cell{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid transparent;background:transparent;}'
            . '.bulk-new-table input.bulk-cell:focus,.bulk-new-table select.bulk-cell:focus{outline:2px solid var(--border-info);background:var(--bg-primary);}'
            . '.bulk-row.bulk-row-saved{background:var(--bg-success);}'
            . '.bulk-row.bulk-row-saved input.bulk-cell,.bulk-row.bulk-row-saved select.bulk-cell{pointer-events:none;color:var(--text-secondary);}'
            . '.bulk-row.bulk-row-error .bulk-row-msg{color:var(--text-danger);}'
            . '.bulk-row-msg{display:block;}'
            . '</style>';
    }

    private static function renderScript(string $rowSaveUrl, string $csrfToken, string $settlementMonth, string $formType): string
    {
        $data = [
            'rowSaveUrl' => $rowSaveUrl,
            'csrfToken' => $csrfToken,
            'settlementMonth' => $settlementMonth,
            'formType' => $formType,
        ];
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{}';
        }

        return '<script>'
            . '(function(){'
            . 'var CFG=' . $json . ';'
            . 'var tbody=document.getElementById("bulk-new-tbody");'
            . 'var tpl=document.getElementById("bulk-new-row-template");'
            . 'var addBtn=document.getElementById("bulk-add-row");'
            . 'if(!tbody||!tpl||!addBtn){return;}'

            . 'function addRow(){var frag=tpl.content.cloneNode(true);tbody.appendChild(frag);bindLastRow();}'
            . 'addBtn.addEventListener("click",addRow);'

            // Enter で下行同カラムにフォーカス
            . 'tbody.addEventListener("keydown",function(e){'
            . 'if(e.key!=="Enter"){return;}'
            . 'var target=e.target;if(!target||!target.classList||!target.classList.contains("bulk-cell")){return;}'
            . 'if(target.tagName==="TEXTAREA"){return;}'
            . 'e.preventDefault();'
            . 'var td=target.closest("td");var tr=target.closest("tr");if(!td||!tr){return;}'
            . 'var cellIndex=Array.prototype.indexOf.call(tr.children,td);'
            . 'var nextTr=tr.nextElementSibling;'
            . 'if(!nextTr){addRow();nextTr=tr.nextElementSibling;}'
            . 'if(!nextTr){return;}'
            . 'var nextTd=nextTr.children[cellIndex];'
            . 'if(!nextTd){return;}'
            . 'var input=nextTd.querySelector("input.bulk-cell,select.bulk-cell");'
            . 'if(input){input.focus();if(input.select){try{input.select();}catch(ex){}}}'
            . '});'

            // 顧客 datalist 同期
            . 'function syncCustomer(input){'
            . 'var listId=input.getAttribute("list");if(!listId){return;}'
            . 'var dl=document.getElementById(listId);if(!dl){return;}'
            . 'var hidden=input.parentElement.querySelector("input[name=customer_id]");'
            . 'if(!hidden){return;}'
            . 'var v=input.value;var opts=dl.querySelectorAll("option");var id="";'
            . 'for(var i=0;i<opts.length;i++){if(opts[i].value===v){id=opts[i].getAttribute("data-id")||"";break;}}'
            . 'hidden.value=id;'
            . '}'

            // 満期案件 datalist 選択時の自動入力（損保のみ）
            . 'function applyRenewal(input){'
            . 'var tr=input.closest("tr");if(!tr){return;}'
            . 'var listId=input.getAttribute("list");if(!listId){return;}'
            . 'var dl=document.getElementById(listId);if(!dl){return;}'
            . 'var v=input.value;var opts=dl.querySelectorAll("option");var matched=null;'
            . 'for(var i=0;i<opts.length;i++){if(opts[i].value===v){matched=opts[i];break;}}'
            . 'var rid=tr.querySelector("input[name=renewal_case_id]");'
            . 'var cid=tr.querySelector("input[name=contract_id]");'
            . 'if(rid){rid.value=matched?(matched.getAttribute("data-id")||""):"";}'
            . 'if(cid){cid.value=matched?(matched.getAttribute("data-contract-id")||""):"";}'
            . 'if(!matched){return;}'
            . 'var custText=tr.querySelector(".bulk-cell-customer");'
            . 'var custId=tr.querySelector("input[name=customer_id]");'
            . 'var cn=matched.getAttribute("data-customer-name")||"";'
            . 'var cidV=matched.getAttribute("data-customer-id")||"";'
            . 'if(custText&&cn){custText.value=cn;}'
            . 'if(custId&&cidV){custId.value=cidV;}'
            . 'var fill=function(sel,attr){var el=tr.querySelector(sel);if(!el){return;}var vv=matched.getAttribute(attr)||"";if(vv){el.value=vv;}};'
            . 'fill("input[name=policy_no]","data-policy-no");'
            . 'fill("input[name=product_type]","data-product-type");'
            . 'fill("input[name=policy_start_date]","data-policy-start-date");'
            . 'var staff=tr.querySelector("select[name=staff_id]");'
            . 'var sid=matched.getAttribute("data-assigned-staff-id")||"";'
            . 'if(staff&&sid){staff.value=sid;}'
            . 'var pp=matched.getAttribute("data-prev-premium-amount")||"";'
            . 'var premium=tr.querySelector("input[name=premium_amount]");'
            . 'if(premium&&pp&&pp!=="0"){premium.value=pp;}'
            . '}'

            // 保存
            . 'function saveRow(tr){'
            . 'var msg=tr.querySelector(".bulk-row-msg");'
            . 'var btn=tr.querySelector(".bulk-save");'
            . 'if(btn.disabled){return;}'
            . 'tr.classList.remove("bulk-row-error");'
            . 'if(msg){msg.style.display="none";msg.textContent="";}'
            . 'btn.disabled=true;btn.textContent="保存中...";'

            . 'var data=new URLSearchParams();'
            . 'data.append("_csrf_token",CFG.csrfToken);'
            . 'data.append("settlement_month",CFG.settlementMonth);'
            . 'data.append("form_type",CFG.formType);'
            . 'tr.querySelectorAll("input[name],select[name]").forEach(function(el){data.append(el.name,el.value);});'

            . 'fetch(CFG.rowSaveUrl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded","Accept":"application/json"},body:data.toString(),credentials:"same-origin"})'
            . '.then(function(r){return r.json().then(function(j){return{status:r.status,body:j};});})'
            . '.then(function(res){'
            . 'if(res.status===200&&res.body&&res.body.ok){'
            . 'tr.classList.add("bulk-row-saved");'
            . 'if(msg){msg.style.color="var(--text-success)";msg.textContent="保存しました";msg.style.display="block";}'
            . 'btn.textContent="保存済み";'
            . 'tr.querySelectorAll("input.bulk-cell,select.bulk-cell").forEach(function(el){el.readOnly=true;el.disabled=true;});'
            . '}else{'
            . 'tr.classList.add("bulk-row-error");'
            . 'var errs=(res.body&&res.body.errors)?res.body.errors:["保存に失敗しました"];'
            . 'if(msg){msg.style.color="var(--text-danger)";msg.textContent=errs.join(" / ");msg.style.display="block";}'
            . 'btn.disabled=false;btn.textContent="保存";'
            . '}'
            . '})'
            . '.catch(function(){'
            . 'tr.classList.add("bulk-row-error");'
            . 'if(msg){msg.style.color="var(--text-danger)";msg.textContent="通信エラー";msg.style.display="block";}'
            . 'btn.disabled=false;btn.textContent="保存";'
            . '});'
            . '}'

            . 'function bindRow(tr){'
            . 'var custInput=tr.querySelector(".bulk-cell-customer");'
            . 'if(custInput){custInput.addEventListener("change",function(){syncCustomer(custInput);});custInput.addEventListener("input",function(){syncCustomer(custInput);});}'
            . 'var renewalInput=tr.querySelector(".bulk-cell-renewal");'
            . 'if(renewalInput){renewalInput.addEventListener("change",function(){applyRenewal(renewalInput);});renewalInput.addEventListener("input",function(){applyRenewal(renewalInput);});}'
            . 'var saveBtn=tr.querySelector(".bulk-save");'
            . 'if(saveBtn){saveBtn.addEventListener("click",function(){saveRow(tr);});}'
            . '}'
            . 'function bindLastRow(){var rows=tbody.querySelectorAll(".bulk-row");if(rows.length>0){bindRow(rows[rows.length-1]);}}'
            . 'tbody.querySelectorAll(".bulk-row").forEach(bindRow);'
            . '})();'
            . '</script>';
    }

    private static function routeHiddenFromUrl(string $url): string
    {
        // bulkUrl 形式：xxx?route=sales/bulk&... 。<form method=get> で route= を維持する隠し input
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['query'])) {
            return '';
        }
        parse_str((string) $parts['query'], $q);
        $route = isset($q['route']) ? (string) $q['route'] : '';
        if ($route === '') {
            return '';
        }
        return '<input type="hidden" name="route" value="' . Layout::escape($route) . '">';
    }
}
