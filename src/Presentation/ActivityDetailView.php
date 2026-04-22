<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;
use App\Presentation\View\ListViewHelper;

final class ActivityDetailView
{
    /**
     * 活動詳細（既存の確認・編集専用）
     *
     * @param array<string, mixed>|null $record
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * @param array<string, string> $allowedActivityTypes
     * @param array<string, mixed> $layoutOptions
     */
    public static function renderDetail(
        ?array $record,
        array $customers,
        array $staffUsers,
        array $salesCases,
        string $backUrl,
        string $detailUrl,
        string $updateUrl,
        string $customerDetailBaseUrl,
        string $updateCsrf,
        ?string $flashError,
        ?string $flashSuccess,
        ?string $errorMessage,
        array $allowedActivityTypes,
        array $layoutOptions,
        array $purposeTypes = [],
        bool $canEdit = true
    ): string {
        $noticeHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $noticeHtml .= '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }
        if (is_string($errorMessage) && $errorMessage !== '') {
            $noticeHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        if ($record === null) {
            $content =
                $noticeHtml
                . '<div class="card"><p>活動が見つかりません。</p>'
                . '<button type="button" class="btn btn-ghost" onclick="history.back()">戻る</button></div>';
            return Layout::render('活動詳細', $content, $layoutOptions);
        }

        $id        = (int) ($record['id'] ?? 0);
        $custId    = (int) ($record['customer_id'] ?? 0);
        $custName  = (string) ($record['customer_name'] ?? '');
        $custUrl   = $custId > 0 ? Layout::escape(ListViewHelper::buildUrl($customerDetailBaseUrl, ['id' => (string) $custId])) : '';
        $createdAt = (string) ($record['created_at'] ?? '');
        $updatedAt = (string) ($record['updated_at'] ?? '');

        $isNullCustomer = ($record['customer_id'] === null || $record['customer_id'] === '' || $custId === 0);
        $custLinkHtml = $isNullCustomer
            ? '<span style="color:#888;">（顧客なし）</span>'
            : ($custUrl !== ''
                ? '<a href="' . $custUrl . '" class="text-link">' . Layout::escape($custName) . '</a>'
                : Layout::escape($custName));

        $editButtons = $canEdit
            ? '<button type="submit" class="btn" form="activity-update-form">保存</button>'
            : '';

        if ($canEdit) {
            $formSection =
                '<form id="activity-update-form" method="post" action="' . Layout::escape($updateUrl) . '">'
                . '<input type="hidden" name="route" value="activity/update">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($updateCsrf) . '">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="return_to" value="' . Layout::escape($detailUrl) . '">'
                . self::buildForm($record, $customers, $staffUsers, $allowedActivityTypes, $id, $purposeTypes, $salesCases)
                . '<div class="actions" style="margin-top:4px;">'
                . '<button type="submit" class="btn btn-primary">保存</button>'
                . '</div>'
                . '</form>';
        } else {
            $formSection = self::buildReadOnlyView($record, $customers, $allowedActivityTypes, $purposeTypes);
        }

        $content =
            '<div class="card">'
            . '<div class="section-head">'
            . '<div>'
            . '<h1 class="title">活動詳細</h1>'
            . '<div class="meta-row">'
            . '<span class="muted" style="font-size:13px;">顧客：' . $custLinkHtml . '</span>'
            . ($createdAt !== '' ? '<span class="muted" style="font-size:13px;">登録：' . Layout::escape($createdAt) . '</span>' : '')
            . ($updatedAt !== '' ? '<span class="muted" style="font-size:13px;">更新：' . Layout::escape($updatedAt) . '</span>' : '')
            . '</div>'
            . '</div>'
            . '<div class="actions">'
            . '<button type="button" class="btn btn-secondary" onclick="history.back()">戻る</button>'
            . $editButtons
            . '</div>'
            . '</div>'
            . $noticeHtml
            . '</div>'
            . $formSection;

        return Layout::render('活動詳細', $content, $layoutOptions);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $customers
     * @param array<int, array<string, mixed>> $staffUsers
     * 活動登録ダイアログ用フォーム（4項目のみ）
     * 活動日は hidden で受け取り非表示。顧客（検索可能ドロップダウン）・活動種別・用件区分・内容要約のみ表示。
     *
     * @param array<string, mixed> $data  プレフィル値（activity_date 必須）
     * @param array<int, array<string, mixed>> $customers
     * @param array<string, string> $allowedActivityTypes
     * @param array<int, array<string, mixed>> $purposeTypes  is_active=1 のみ
     * @param array<int, array<string, mixed>> $salesCases    fetchForDropdown 結果
     */
    public static function buildDialogForm(
        array $data,
        array $customers,
        array $allowedActivityTypes,
        array $purposeTypes = [],
        array $salesCases = []
    ): string {
        $customerIdVal   = (string) ($data['customer_id'] ?? '');
        $activityDateVal = (string) ($data['activity_date'] ?? '');
        $staffIdVal      = (string) ($data['staff_id'] ?? '');
        $activityTypeVal = (string) ($data['activity_type'] ?? '');
        $purposeTypeVal  = (string) ($data['purpose_type'] ?? '');
        $startTimeVal    = (string) ($data['start_time'] ?? '');
        $endTimeVal      = (string) ($data['end_time'] ?? '');
        $visitPlaceVal   = (string) ($data['visit_place'] ?? '');
        $subjectVal      = (string) ($data['subject'] ?? '');
        $summaryVal      = (string) ($data['content_summary'] ?? '');
        $nextDateVal     = (string) ($data['next_action_date'] ?? '');
        $salesCaseIdVal  = (string) ($data['sales_case_id'] ?? '');

        // 顧客データを JSON に変換（検索可能ドロップダウン用）
        $customerList = [];
        foreach ($customers as $cust) {
            $customerList[] = ['id' => (int) ($cust['id'] ?? 0), 'name' => (string) ($cust['customer_name'] ?? '')];
        }
        $customerJson = json_encode($customerList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        // 見込案件データを JSON に変換（検索可能コンボボックス用）
        $salesCaseList = [];
        foreach ($salesCases as $sc) {
            $scId     = (int) ($sc['id'] ?? 0);
            $scName   = (string) ($sc['case_name'] ?? '');
            $dispCust = (string) ($sc['display_customer'] ?? '');
            $salesCaseList[] = ['id' => $scId, 'name' => $scName];
        }
        $salesCaseJson = json_encode($salesCaseList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        // プレフィルされた顧客名を検索
        $prefilledName = '';
        foreach ($customers as $cust) {
            if ((string) ($cust['id'] ?? '') === $customerIdVal && $customerIdVal !== '') {
                $prefilledName = (string) ($cust['customer_name'] ?? '');
                break;
            }
        }

        // プレフィルされた見込案件名を検索
        $prefilledCaseName = '';
        foreach ($salesCaseList as $sc) {
            if ($salesCaseIdVal !== '' && (string) $sc['id'] === $salesCaseIdVal) {
                $prefilledCaseName = $sc['name'];
                break;
            }
        }

        $typeOptionsHtml = '<option value="">-- 選択 --</option>';
        foreach ($allowedActivityTypes as $val => $label) {
            $sel = $activityTypeVal === $val ? ' selected' : '';
            $typeOptionsHtml .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        $purposeOptionsHtml = '<option value="">— 選択してください —</option>';
        foreach ($purposeTypes as $pt) {
            $ptName = (string) ($pt['name'] ?? '');
            if ($ptName === '') { continue; }
            $sel    = $purposeTypeVal === $ptName ? ' selected' : '';
            $purposeOptionsHtml .= '<option value="' . Layout::escape($ptName) . '"' . $sel . '>' . Layout::escape($ptName) . '</option>';
        }

        $req = '<strong class="required-mark"> *</strong>';

        // JS は heredoc で書いてエスケープ問題を回避
        $js = <<<'JSCODE'
<script>
(function() {
  // 汎用コンボボックス（顧客・見込案件共通）
  function makeCombo(opts) {
    // opts: { dataKey, textId, hiddenId, listId, max }
    function getAll() { return window[opts.dataKey] || []; }

    function renderList(q) {
      var list = document.getElementById(opts.listId);
      if (!list) return;
      var q2 = q.trim().toLowerCase();
      var all = getAll();
      var matched = q2 === '' ? all : all.filter(function(c) {
        return c.name.toLowerCase().indexOf(q2) >= 0;
      });
      if (matched.length === 0) {
        list.innerHTML = '<div style="padding:8px 12px;color:#888;font-size:13px;">該当なし</div>';
      } else {
        var html = '';
        var items = matched.slice(0, opts.max || 50);
        for (var i = 0; i < items.length; i++) {
          html += '<div class="act-dlg-item" data-id="' + items[i].id + '" style="padding:8px 12px;cursor:pointer;font-size:13px;">'
            + items[i].name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            + '</div>';
        }
        list.innerHTML = html;
        list.querySelectorAll('.act-dlg-item').forEach(function(el) {
          el.addEventListener('mousedown', function(e) {
            e.preventDefault();
            document.getElementById(opts.hiddenId).value = el.dataset.id;
            document.getElementById(opts.textId).value = el.textContent;
            list.style.display = 'none';
          });
          el.addEventListener('mouseover', function() { el.style.background = '#f0f4f8'; });
          el.addEventListener('mouseout',  function() { el.style.background = ''; });
        });
      }
      list.style.display = '';
    }

    function hideList() {
      var list = document.getElementById(opts.listId);
      if (list) list.style.display = 'none';
      var txt = document.getElementById(opts.textId);
      var hid = document.getElementById(opts.hiddenId);
      if (txt && hid && txt.value.trim() === '') hid.value = '';
    }

    document.addEventListener('DOMContentLoaded', function() {
      var txt = document.getElementById(opts.textId);
      if (!txt) return;
      txt.addEventListener('input',  function() { renderList(txt.value); });
      txt.addEventListener('focus',  function() { renderList(txt.value); });
      txt.addEventListener('keydown', function(e) {
        var list = document.getElementById(opts.listId);
        if (!list || list.style.display === 'none') return;
        var items = list.querySelectorAll('.act-dlg-item');
        var focused = list.querySelector('.act-dlg-item.focused');
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          var next = focused ? (focused.nextElementSibling || items[0]) : items[0];
          if (focused) { focused.classList.remove('focused'); focused.style.background = ''; }
          if (next) { next.classList.add('focused'); next.style.background = '#f0f4f8'; next.scrollIntoView({block:'nearest'}); }
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          var prev = focused ? (focused.previousElementSibling || items[items.length-1]) : items[items.length-1];
          if (focused) { focused.classList.remove('focused'); focused.style.background = ''; }
          if (prev) { prev.classList.add('focused'); prev.style.background = '#f0f4f8'; prev.scrollIntoView({block:'nearest'}); }
        } else if (e.key === 'Enter') {
          if (focused) {
            e.preventDefault();
            document.getElementById(opts.hiddenId).value = focused.dataset.id;
            txt.value = focused.textContent;
            list.style.display = 'none';
          }
        } else if (e.key === 'Escape') {
          list.style.display = 'none';
        }
      });
      txt.addEventListener('blur', function() { setTimeout(hideList, 150); });
    });
  }

  makeCombo({ dataKey:'actDlgCustomers', textId:'act-dlg-customer-text', hiddenId:'act-dlg-customer-id', listId:'act-dlg-customer-list' });
  makeCombo({ dataKey:'actDlgSCases',    textId:'act-dlg-scase-text',    hiddenId:'act-dlg-scase-id',    listId:'act-dlg-scase-list' });
})();
</script>
JSCODE;

        return
            '<input type="hidden" name="activity_date" value="' . Layout::escape($activityDateVal) . '">'
            . ($staffIdVal !== '' ? '<input type="hidden" name="staff_id" value="' . Layout::escape($staffIdVal) . '">' : '')
            . '<script>window.actDlgCustomers=' . $customerJson . ';window.actDlgSCases=' . $salesCaseJson . ';</script>'
            . '<div class="card">'
            . '<div class="list-filter-grid modal-form-grid">'

            . '<div class="list-filter-field modal-form-wide" id="act-dlg-customer-wrap">'
            . '<span>既存顧客</span>'
            . '<div style="position:relative;">'
            . '<input type="hidden" name="customer_id" id="act-dlg-customer-id" value="' . Layout::escape($customerIdVal) . '">'
            . '<input type="text" id="act-dlg-customer-text" autocomplete="off"'
            . ' placeholder="顧客名を入力して検索..."'
            . ' value="' . Layout::escape($prefilledName) . '"'
            . ' style="width:100%;box-sizing:border-box;">'
            . '<div id="act-dlg-customer-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#fff;border:1px solid var(--border-medium);border-top:none;border-radius:0 0 var(--radius-md) var(--radius-md);z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.12);"></div>'
            . '</div>'
            . '</div>'

            . '<div class="list-filter-field modal-form-wide" id="act-dlg-scase-wrap">'
            . '<span>見込案件</span>'
            . '<div style="position:relative;">'
            . '<input type="hidden" name="sales_case_id" id="act-dlg-scase-id" value="' . Layout::escape($salesCaseIdVal) . '">'
            . '<input type="text" id="act-dlg-scase-text" autocomplete="off"'
            . ' placeholder="案件名を入力して検索..."'
            . ' value="' . Layout::escape($prefilledCaseName) . '"'
            . ' style="width:100%;box-sizing:border-box;">'
            . '<div id="act-dlg-scase-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#fff;border:1px solid var(--border-medium);border-top:none;border-radius:0 0 var(--radius-md) var(--radius-md);z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.12);"></div>'
            . '</div>'
            . '</div>'

            . '<div class="list-filter-field modal-form-wide" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">'
            . '<div style="display:flex;align-items:center;gap:8px;"><span style="white-space:nowrap;">開始時刻</span>' . self::buildTimePicker('start_time', $startTimeVal) . '</div>'
            . '<div style="display:flex;align-items:center;gap:8px;"><span style="white-space:nowrap;">終了時刻</span>' . self::buildTimePicker('end_time', $endTimeVal) . '</div>'
            . '</div>'

            . '<label class="list-filter-field"><span>活動種別' . $req . '</span>'
            . '<select name="activity_type" required>' . $typeOptionsHtml . '</select></label>'

            . '<label class="list-filter-field"><span>用件区分</span>'
            . '<select name="purpose_type">' . $purposeOptionsHtml . '</select></label>'

            . '<label class="list-filter-field modal-form-wide"><span>訪問先</span>'
            . '<input type="text" name="visit_place" value="' . Layout::escape($visitPlaceVal) . '" maxlength="200"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>活動概要' . $req . '</span>'
            . '<input type="text" name="subject" required maxlength="200" value="' . Layout::escape($subjectVal) . '"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>活動詳細</span>'
            . '<textarea name="content_summary" maxlength="500" rows="4" style="width:100%;resize:vertical;">'
            . Layout::escape($summaryVal) . '</textarea></label>'

            . '<label class="list-filter-field modal-form-wide"><span>次回予定日</span>'
            . '<input type="date" name="next_action_date" value="' . Layout::escape($nextDateVal) . '"></label>'

            . '</div>'
            . '</div>'
            . $js
            . self::buildTimePickerJs();
    }

    // ---- 時刻ピッカー（ポップアップ方式）----

    /**
     * ポップアップ方式の時刻ピッカーを生成する。
     * [HH]:[MM] ボタンをタップするとボタン群がポップアップ表示される。
     * hidden input (name=$name) に HH:MM 形式でセットする。
     */
    private static function buildTimePicker(string $name, string $value): string
    {
        $hSel = '--';
        $mSel = '--';
        if (preg_match('/^(\d{2}):(\d{2})$/', $value, $mat)) {
            $hSel = $mat[1];
            $mSel = $mat[2];
        }
        $id = 'tp-' . preg_replace('/[^a-z0-9]+/', '-', $name);

        return
            '<span class="tp-widget" id="' . $id . '" style="position:relative;display:inline-flex;align-items:center;gap:1px;">'
            . '<button type="button" class="tp-seg tp-seg-h" data-widget="' . $id . '" data-type="h">' . Layout::escape($hSel) . '</button>'
            . '<span style="font-size:16px;font-weight:700;color:#627d98;padding:0 1px;">:</span>'
            . '<button type="button" class="tp-seg tp-seg-m" data-widget="' . $id . '" data-type="m">' . Layout::escape($mSel) . '</button>'
            . '<a href="#" class="tp-clear" data-widget="' . $id . '" style="font-size:11px;color:#9aa5b4;margin-left:6px;">クリア</a>'
            . '<div class="tp-popup" style="display:none;position:absolute;top:calc(100% + 4px);left:0;z-index:9999;background:#fff;border:1px solid #d9e2ec;border-radius:8px;padding:10px;box-shadow:0 6px 20px rgba(0,0,0,.15);white-space:normal;"></div>'
            . '<input type="hidden" name="' . Layout::escape($name) . '" id="' . $id . '-v" value="' . Layout::escape($value) . '">'
            . '</span>';
    }

    /** 時刻ピッカー用 JS（1ページに1回だけ出力される）。 */
    private static function buildTimePickerJs(): string
    {
        return <<<'TPJS'
<script>
(function() {
  if (window._tpReady) return;
  window._tpReady = true;
  if (!document.getElementById('tp-css')) {
    var s = document.createElement('style');
    s.id = 'tp-css';
    s.textContent =
      '.tp-seg{min-width:40px;padding:5px 8px;border:1px solid #d9e2ec;border-radius:4px;background:#fff;cursor:pointer;font-size:15px;font-weight:700;text-align:center;color:#243b55;font-family:inherit;line-height:1;box-sizing:border-box;letter-spacing:.5px}' +
      '.tp-seg:hover{background:#f0f4f8;border-color:#b3c5d3}' +
      '.tp-seg.tp-seg-open{border-color:#1a56db;background:#eff4ff;color:#1a56db}' +
      '.tp-popup .tp-grid{display:flex;flex-wrap:wrap;gap:4px;max-width:260px}' +
      '.tp-pbtn{min-width:36px;min-height:32px;padding:3px 4px;border:1px solid #d9e2ec;border-radius:4px;background:#fff;cursor:pointer;font-size:13px;font-family:inherit;line-height:1;box-sizing:border-box;text-align:center}' +
      '.tp-pbtn:hover{background:#f0f4f8;border-color:#b3c5d3}' +
      '.tp-pbtn.tp-on{background:#1a56db;color:#fff;border-color:#1a56db;font-weight:700}' +
      '.tp-clear:hover{color:#e53e3e !important;text-decoration:none}';
    document.head.appendChild(s);
  }

  var _open = null; // {id, type}

  function closeAll() {
    document.querySelectorAll('.tp-popup').forEach(function(p) { p.style.display = 'none'; });
    document.querySelectorAll('.tp-seg').forEach(function(s) { s.classList.remove('tp-seg-open'); });
    _open = null;
  }

  function openPopup(widgetId, type) {
    closeAll();
    var w = document.getElementById(widgetId);
    if (!w) return;
    var popup = w.querySelector('.tp-popup');
    if (!popup) return;
    var hid = document.getElementById(widgetId + '-v');
    var val = hid ? hid.value : '';
    var m = val.match(/^(\d{2}):(\d{2})$/);
    var hSel = m ? m[1] : '';
    var mSel = m ? m[2] : '';
    var html = '<div class="tp-grid">';
    if (type === 'h') {
      for (var h = 0; h <= 23; h++) {
        var hStr = (h < 10 ? '0' : '') + h;
        html += '<button type="button" class="tp-pbtn' + (hSel === hStr ? ' tp-on' : '') + '" data-widget="' + widgetId + '" data-type="h" data-v="' + hStr + '">' + hStr + '</button>';
      }
    } else {
      var mins = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];
      for (var i = 0; i < mins.length; i++) {
        var mStr = (mins[i] < 10 ? '0' : '') + mins[i];
        html += '<button type="button" class="tp-pbtn' + (mSel === mStr ? ' tp-on' : '') + '" data-widget="' + widgetId + '" data-type="m" data-v="' + mStr + '">' + mStr + '</button>';
      }
    }
    html += '</div>';
    popup.innerHTML = html;
    popup.style.display = '';
    var seg = w.querySelector('.tp-seg-' + type);
    if (seg) seg.classList.add('tp-seg-open');
    _open = {id: widgetId, type: type};
  }

  window.tpClear = function(widgetId) {
    var w = document.getElementById(widgetId);
    if (!w) return;
    var hid = document.getElementById(widgetId + '-v');
    if (hid) hid.value = '';
    var sH = w.querySelector('.tp-seg-h');
    var sM = w.querySelector('.tp-seg-m');
    if (sH) sH.textContent = '--';
    if (sM) sM.textContent = '--';
    closeAll();
  };

  document.addEventListener('click', function(e) {
    // セグメント（時 or 分ボタン）クリック
    var seg = e.target.closest && e.target.closest('.tp-seg');
    if (seg) {
      e.stopPropagation();
      var wid = seg.dataset.widget;
      var type = seg.dataset.type;
      if (_open && _open.id === wid && _open.type === type) {
        closeAll();
      } else {
        openPopup(wid, type);
      }
      return;
    }
    // ポップアップ内ボタンクリック
    var pbtn = e.target.closest && e.target.closest('.tp-pbtn');
    if (pbtn) {
      e.stopPropagation();
      var wid = pbtn.dataset.widget;
      var w = document.getElementById(wid);
      if (!w) return;
      var hid = document.getElementById(wid + '-v');
      var val = hid ? hid.value : '';
      var match = val.match(/^(\d{2}):(\d{2})$/);
      var hVal = match ? match[1] : '';
      var mVal = match ? match[2] : '00';
      if (pbtn.dataset.type === 'h') {
        hVal = pbtn.dataset.v;
        if (!mVal) mVal = '00';
      } else {
        mVal = pbtn.dataset.v;
      }
      if (hid) hid.value = hVal !== '' ? hVal + ':' + mVal : '';
      var sH = w.querySelector('.tp-seg-h');
      var sM = w.querySelector('.tp-seg-m');
      if (sH) sH.textContent = hVal !== '' ? hVal : '--';
      if (sM) sM.textContent = mVal !== '' ? mVal : '--';
      closeAll();
      return;
    }
    // クリアリンク
    var clr = e.target.closest && e.target.closest('.tp-clear');
    if (clr) {
      e.preventDefault();
      e.stopPropagation();
      var wid = clr.dataset.widget;
      if (wid) window.tpClear(wid);
      return;
    }
    // ポップアップ外クリックで閉じる
    if (!e.target.closest || (!e.target.closest('.tp-popup') && !e.target.closest('.tp-seg'))) {
      closeAll();
    }
  });
})();
</script>
TPJS;
    }

    /**
     * 閲覧専用表示（編集権限なし時）
     *
     * @param array<string, string> $allowedActivityTypes
     * @param array<int, array<string, mixed>> $purposeTypes
     */
    private static function buildReadOnlyView(
        array $data,
        array $customers,
        array $allowedActivityTypes,
        array $purposeTypes
    ): string {
        $customerIdVal   = (string) ($data['customer_id'] ?? '');
        $startTimeVal    = (string) ($data['start_time'] ?? '');
        $endTimeVal      = (string) ($data['end_time'] ?? '');
        $activityTypeVal = (string) ($data['activity_type'] ?? '');
        $purposeTypeVal  = (string) ($data['purpose_type'] ?? '');
        $visitPlaceVal   = (string) ($data['visit_place'] ?? '');
        $subjectVal      = (string) ($data['subject'] ?? '');
        $summaryVal      = (string) ($data['content_summary'] ?? '');
        $nextDateVal     = (string) ($data['next_action_date'] ?? '');

        $noCustomer = ($customerIdVal === '' || (int) $customerIdVal === 0);
        if ($noCustomer) {
            $custDisplay = '（顧客なし）';
        } else {
            $custDisplay = (string) ($data['customer_name'] ?? '');
            if ($custDisplay === '') {
                foreach ($customers as $cust) {
                    if ((string) ($cust['id'] ?? '') === $customerIdVal) {
                        $custDisplay = (string) ($cust['customer_name'] ?? '');
                        break;
                    }
                }
            }
        }

        $actTypeLabel = $allowedActivityTypes[$activityTypeVal] ?? $activityTypeVal;

        $timeDisplay = '';
        if ($startTimeVal !== '' || $endTimeVal !== '') {
            $timeDisplay = $startTimeVal !== '' ? $startTimeVal : '';
            if ($startTimeVal !== '' && $endTimeVal !== '') {
                $timeDisplay .= ' 〜 ';
            }
            $timeDisplay .= $endTimeVal !== '' ? $endTimeVal : '';
        }

        $labelStyle = 'display:block;margin-bottom:4px;font-size:13px;font-weight:500;color:#486581;';
        $valStyle   = 'font-size:14px;color:#243b55;';
        $dashHtml   = '<span style="font-size:14px;color:#9aa5b4;">—</span>';

        $field = static function (string $label, string $value, bool $wide = false) use ($labelStyle, $valStyle, $dashHtml): string {
            $cls      = 'list-filter-field' . ($wide ? ' modal-form-wide' : '');
            $valueHtml = $value !== ''
                ? '<span style="' . $valStyle . '">' . Layout::escape($value) . '</span>'
                : $dashHtml;
            return '<div class="' . $cls . '">'
                . '<span style="' . $labelStyle . '">' . Layout::escape($label) . '</span>'
                . $valueHtml
                . '</div>';
        };

        $html = '<div class="card"><div class="list-filter-grid modal-form-grid">'
            . $field('顧客', $custDisplay, true)
            . ($timeDisplay !== '' ? $field('時刻', $timeDisplay, true) : '')
            . $field('活動種別', $actTypeLabel)
            . ($purposeTypeVal !== '' ? $field('用件区分', $purposeTypeVal) : '')
            . ($visitPlaceVal !== '' ? $field('訪問先', $visitPlaceVal, true) : '')
            . $field('活動概要', $subjectVal, true);

        if ($summaryVal !== '') {
            $html .= '<div class="list-filter-field modal-form-wide">'
                . '<span style="' . $labelStyle . '">活動詳細</span>'
                . '<div style="' . $valStyle . 'white-space:pre-wrap;line-height:1.6;">' . Layout::escape($summaryVal) . '</div>'
                . '</div>';
        }

        $html .= ($nextDateVal !== '' ? $field('次回予定日', $nextDateVal) : '')
            . '</div></div>';

        return $html;
    }

    /**
     * @param array<string, string> $allowedActivityTypes
     * @param array<int, array<string, mixed>> $purposeTypes
     */
    public static function buildForm(
        array $data,
        array $customers,
        array $staffUsers,
        array $allowedActivityTypes,
        int $id = 0,
        array $purposeTypes = [],
        array $salesCases = []
    ): string {
        $customerIdVal   = (string) ($data['customer_id'] ?? '');
        $activityDateVal = (string) ($data['activity_date'] ?? '');
        $startTimeVal    = (string) ($data['start_time'] ?? '');
        $endTimeVal      = (string) ($data['end_time'] ?? '');
        $activityTypeVal = (string) ($data['activity_type'] ?? '');
        $purposeTypeVal  = (string) ($data['purpose_type'] ?? '');
        $visitPlaceVal   = (string) ($data['visit_place'] ?? '');
        $intervieweeVal  = (string) ($data['interviewee_name'] ?? '');
        $subjectVal      = (string) ($data['subject'] ?? '');
        $summaryVal      = (string) ($data['content_summary'] ?? '');
        $nextDateVal     = (string) ($data['next_action_date'] ?? '');
        $resultTypeVal   = (string) ($data['result_type'] ?? '');
        $staffUserIdVal  = (string) ($data['staff_id'] ?? '');
        $salesCaseIdVal  = (string) ($data['sales_case_id'] ?? '');

        // 顧客コンボボックス用データ
        $customerList = [];
        foreach ($customers as $cust) {
            $customerList[] = ['id' => (int) ($cust['id'] ?? 0), 'name' => (string) ($cust['customer_name'] ?? '')];
        }
        $customerJson = json_encode($customerList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        // 見込案件オプション（サーバーサイドレンダリング）
        $scIdSet = [];
        foreach ($salesCases as $sc) {
            $scIdSet[(string) ((int) ($sc['id'] ?? 0))] = true;
        }
        $detailSalesCaseOptionsHtml = '<option value="">— 選択しない —</option>';
        // Orphan: 既存値が一覧にない場合（完了済み・削除済み案件）
        if ($salesCaseIdVal !== '' && !isset($scIdSet[$salesCaseIdVal])) {
            $detailSalesCaseOptionsHtml .= '<option value="' . Layout::escape($salesCaseIdVal) . '" selected>'
                . Layout::escape('（削除または完了済み案件 #' . $salesCaseIdVal . '）') . '</option>';
        }
        foreach ($salesCases as $sc) {
            $scId     = (int) ($sc['id'] ?? 0);
            $scName   = (string) ($sc['case_name'] ?? '');
            $dispCust = (string) ($sc['display_customer'] ?? '');
            $label    = $dispCust !== '' ? $scName . '（' . $dispCust . '）' : $scName;
            $sel      = $salesCaseIdVal === (string) $scId ? ' selected' : '';
            $detailSalesCaseOptionsHtml .= '<option value="' . Layout::escape((string) $scId) . '"' . $sel . '>'
                . Layout::escape($label) . '</option>';
        }

        $prefilledCustName = '';
        foreach ($customers as $cust) {
            if ((string) ($cust['id'] ?? '') === $customerIdVal && $customerIdVal !== '') {
                $prefilledCustName = (string) ($cust['customer_name'] ?? '');
                break;
            }
        }

        $typeOptionsHtml = '<option value="">-- 選択 --</option>';
        foreach ($allowedActivityTypes as $val => $label) {
            $sel = $activityTypeVal === $val ? ' selected' : '';
            $typeOptionsHtml .= '<option value="' . Layout::escape($val) . '"' . $sel . '>' . Layout::escape($label) . '</option>';
        }

        // 用件区分（マスタ外の既存値は旧値として先頭表示）
        $purposeOptionsHtml = '<option value="">— 選択してください —</option>';
        $purposeExists      = false;
        foreach ($purposeTypes as $pt) {
            $ptName = (string) ($pt['name'] ?? '');
            if ($ptName === '') { continue; }
            $sel    = $purposeTypeVal === $ptName ? ' selected' : '';
            if ($sel !== '') {
                $purposeExists = true;
            }
            $purposeOptionsHtml .= '<option value="' . Layout::escape($ptName) . '"' . $sel . '>' . Layout::escape($ptName) . '</option>';
        }
        if ($purposeTypeVal !== '' && !$purposeExists) {
            $orphanLabel        = '（旧値: ' . $purposeTypeVal . '）';
            $purposeOptionsHtml = '<option value="">— 選択してください —</option>'
                . '<option value="' . Layout::escape($purposeTypeVal) . '" selected style="color:#999;">' . Layout::escape($orphanLabel) . '</option>'
                . substr($purposeOptionsHtml, strlen('<option value="">— 選択してください —</option>'));
        }

        $staffOptionsHtml = '<option value="">-- 選択 --</option>';
        foreach ($staffUsers as $user) {
            $uid   = (int) ($user['id'] ?? 0);
            $uname = (string) ($user['staff_name'] ?? $user['name'] ?? '');
            $sel   = $staffUserIdVal === (string) $uid ? ' selected' : '';
            $staffOptionsHtml .= '<option value="' . $uid . '"' . $sel . '>' . Layout::escape($uname) . '</option>';
        }

        $req = '<strong class="required-mark"> *</strong>';

        $detailComboJs = <<<'CBJS'
<script>
(function() {
  function filterDetailCust(q) {
    var list = document.getElementById('detail-cust-list');
    if (!list) return;
    var q2 = q.trim().toLowerCase();
    var all = window.detailCustData || [];
    var matched = q2 === '' ? all : all.filter(function(c) {
      return c.name.toLowerCase().indexOf(q2) >= 0;
    });
    if (matched.length === 0) {
      list.innerHTML = '<div style="padding:8px 12px;color:#888;font-size:13px;">該当なし</div>';
    } else {
      var html = '';
      var items = matched.slice(0, 50);
      for (var i = 0; i < items.length; i++) {
        html += '<div class="act-dlg-item" data-id="' + items[i].id + '" style="padding:8px 12px;cursor:pointer;font-size:13px;">'
          + items[i].name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
          + '</div>';
      }
      list.innerHTML = html;
      list.querySelectorAll('.act-dlg-item').forEach(function(el) {
        el.addEventListener('mousedown', function(e) {
          e.preventDefault();
          document.getElementById('detail-cust-id').value = el.dataset.id;
          document.getElementById('detail-cust-text').value = el.textContent;
          list.style.display = 'none';
        });
        el.addEventListener('mouseover', function() { el.style.background = '#f0f4f8'; });
        el.addEventListener('mouseout',  function() { el.style.background = ''; });
      });
    }
    list.style.display = '';
  }
  function hideDetailCustList() {
    var list = document.getElementById('detail-cust-list');
    if (list) list.style.display = 'none';
    var txt = document.getElementById('detail-cust-text');
    var hid = document.getElementById('detail-cust-id');
    if (txt && hid && txt.value.trim() === '') {
      hid.value = '';
    }
  }
  document.addEventListener('DOMContentLoaded', function() {
    var txt = document.getElementById('detail-cust-text');
    if (txt) {
      txt.addEventListener('input',  function() { filterDetailCust(txt.value); });
      txt.addEventListener('focus',  function() { filterDetailCust(txt.value); });
      txt.addEventListener('keydown', function(e) {
        var list = document.getElementById('detail-cust-list');
        if (!list || list.style.display === 'none') return;
        var items = list.querySelectorAll('.act-dlg-item');
        var focused = list.querySelector('.act-dlg-item.focused');
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          var next = focused ? (focused.nextElementSibling || items[0]) : items[0];
          if (focused) { focused.classList.remove('focused'); focused.style.background = ''; }
          if (next) { next.classList.add('focused'); next.style.background = '#f0f4f8'; next.scrollIntoView({block:'nearest'}); }
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          var prev = focused ? (focused.previousElementSibling || items[items.length-1]) : items[items.length-1];
          if (focused) { focused.classList.remove('focused'); focused.style.background = ''; }
          if (prev) { prev.classList.add('focused'); prev.style.background = '#f0f4f8'; prev.scrollIntoView({block:'nearest'}); }
        } else if (e.key === 'Enter') {
          if (focused) {
            e.preventDefault();
            document.getElementById('detail-cust-id').value = focused.dataset.id;
            txt.value = focused.textContent;
            list.style.display = 'none';
          }
        } else if (e.key === 'Escape') {
          list.style.display = 'none';
        }
      });
      txt.addEventListener('blur', function() { setTimeout(hideDetailCustList, 150); });
    }
  });
})();
</script>
CBJS;

        return
            // 非表示項目は hidden で保持（既存値を上書きしない）
            '<input type="hidden" name="activity_date" value="' . Layout::escape($activityDateVal) . '">'
            . '<input type="hidden" name="interviewee_name" value="' . Layout::escape($intervieweeVal) . '">'
            . '<input type="hidden" name="result_type" value="' . Layout::escape($resultTypeVal) . '">'
            . '<input type="hidden" name="staff_id" value="' . Layout::escape($staffUserIdVal) . '">'
            . '<script>window.detailCustData=' . $customerJson . ';</script>'
            . '<div class="card">'
            . '<div class="list-filter-grid modal-form-grid">'

            // 顧客コンボボックス
            . '<div class="list-filter-field modal-form-wide" id="detail-cust-wrap">'
            . '<span style="display:block;margin-bottom:4px;font-size:13px;font-weight:500;">既存顧客</span>'
            . '<div style="position:relative;">'
            . '<input type="hidden" name="customer_id" id="detail-cust-id" value="' . Layout::escape($customerIdVal) . '">'
            . '<input type="text" id="detail-cust-text" autocomplete="off"'
            . ' placeholder="顧客名を入力して検索..."'
            . ' value="' . Layout::escape($prefilledCustName) . '"'
            . ' style="width:100%;padding:7px 10px;border:1px solid #d9e2ec;border-radius:6px;font-size:14px;box-sizing:border-box;">'
            . '<div id="detail-cust-list" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#fff;border:1px solid #d9e2ec;border-top:none;border-radius:0 0 6px 6px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.12);"></div>'
            . '</div>'
            . '</div>'

            . '<div class="list-filter-field modal-form-wide" id="detail-scase-wrap">'
            . '<span style="display:block;margin-bottom:4px;font-size:13px;font-weight:500;">見込案件</span>'
            . '<select name="sales_case_id" id="detail-scase-select" style="width:100%;padding:7px 10px;border:1px solid #d9e2ec;border-radius:6px;font-size:14px;box-sizing:border-box;background:#fff;">'
            . $detailSalesCaseOptionsHtml
            . '</select>'
            . '</div>'

            . '<div class="list-filter-field modal-form-wide" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">'
            . '<div style="display:flex;align-items:center;gap:8px;"><span style="font-size:13px;font-weight:500;white-space:nowrap;">開始時刻</span>' . self::buildTimePicker('start_time', $startTimeVal) . '</div>'
            . '<div style="display:flex;align-items:center;gap:8px;"><span style="font-size:13px;font-weight:500;white-space:nowrap;">終了時刻</span>' . self::buildTimePicker('end_time', $endTimeVal) . '</div>'
            . '</div>'

            . '<label class="list-filter-field"><span>活動種別' . $req . '</span>'
            . '<select name="activity_type" required>' . $typeOptionsHtml . '</select></label>'
            . '<label class="list-filter-field"><span>用件区分</span>'
            . '<select name="purpose_type">' . $purposeOptionsHtml . '</select></label>'

            . '<label class="list-filter-field modal-form-wide"><span>訪問先</span>'
            . '<input type="text" name="visit_place" value="' . Layout::escape($visitPlaceVal) . '" maxlength="200"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>活動概要' . $req . '</span>'
            . '<input type="text" name="subject" required maxlength="200" value="' . Layout::escape($subjectVal) . '"></label>'

            . '<label class="list-filter-field modal-form-wide"><span>活動詳細</span>'
            . '<textarea name="content_summary" maxlength="500" rows="4" style="width:100%;resize:vertical;">'
            . Layout::escape($summaryVal) . '</textarea></label>'

            . '<label class="list-filter-field modal-form-wide"><span>次回予定日</span>'
            . '<input type="date" name="next_action_date" value="' . Layout::escape($nextDateVal) . '"></label>'

            . '</div>'
            . '</div>'
            . $detailComboJs
            . self::buildTimePickerJs();
    }
}
