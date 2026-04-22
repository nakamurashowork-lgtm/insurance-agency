/**
 * dashboard.js
 * ホーム画面 担当者選択ドロップダウン / 年度切り替えの更新処理。
 *
 * 依存: window.DASHBOARD_CONFIG = { apiBase: '...', fiscalYear: YYYY, loginUserId: N }
 * window.DASHBOARD_CONFIG は DashboardView.php がインライン <script> で設定する。
 *
 * ポリシー:
 * - 要確認エリア系 3 ドロップダウン（renewal/accident/sales-case）は fetch で
 *   JSON を取得し DOM テキストのみ書き換える（部分更新）
 * - 成績サマリ（sales-user）と年度プルダウン（fiscal-year）は、
 *   3 タイル + 3 タブ + 月次表の整合を保つため URL 再読込でフル遷移する。
 *   再読込時に画面上部へ戻らないよう、URL に `#perf-summary` アンカーを付与し、
 *   ブラウザネイティブのアンカージャンプで成績サマリまで自動スクロールさせる。
 * - innerHTML は使わない。textContent / className のみ書き換える
 */

(function () {
  'use strict';

  var cfg = window.DASHBOARD_CONFIG || {};
  var API_BASE    = (cfg.apiBase    || '').replace(/\/$/, '');

  // ─── 初期化 ──────────────────────────────────────────────────────

  // 成績サマリ系ドロップダウン操作後の再読込は URL に `#perf-summary` を付けて
  // ブラウザネイティブのアンカージャンプで成績サマリまで自動スクロールさせる方式。
  // （sessionStorage + scrollTo 方式は race condition が多く廃止）

  initDropdown('renewal-user',   'renewal-summary',    renderRenewalCard);
  initDropdown('accident-user',  'accident-summary',   renderAccidentCard);
  initDropdown('sales-case-user','sales-case-summary', renderSalesCaseCard);
  initReloadDropdown('sales-user',   'sales_user');
  initReloadDropdown('fiscal-year',  'fiscal_year');

  // ─── ドロップダウン初期化 ─────────────────────────────────────────

  function initDropdown(dropdownId, apiSlug, renderFn) {
    var el = document.getElementById(dropdownId);
    if (!el) return;

    el.addEventListener('change', function () {
      var userValue = this.value;
      var paramName = dropdownIdToQueryParam(dropdownId);
      fetchSummary(apiSlug, userValue)
        .then(function (data) {
          renderFn(data);
          updateUrlQuery(paramName, userValue);
        })
        .catch(function (err) {
          console.error('Dashboard update failed [' + dropdownId + ']:', err);
          showCardError(dropdownId);
        });
    });
  }

  /**
   * URL 再読込で同期するドロップダウン。
   * 3 タイル + 3 タブ + 月次表の整合が必要な箇所（sales-user / fiscal-year）で使う。
   * 再読込時に画面上部へ飛ばないよう、URL に `#perf-summary` アンカーを付けて
   * ブラウザネイティブのアンカージャンプで成績サマリまで自動スクロールさせる。
   */
  function initReloadDropdown(dropdownId, paramName) {
    var el = document.getElementById(dropdownId);
    if (!el) return;
    el.addEventListener('change', function () {
      var value = this.value;
      try {
        var url = new URL(window.location.href);
        url.searchParams.set(paramName, value);
        url.hash = 'perf-summary';
        window.location.href = url.toString();
      } catch (e) {
        // URL API 非対応ブラウザ向けフォールバック
        var base = window.location.href.split('#')[0];
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        window.location.href = base + sep
          + encodeURIComponent(paramName) + '=' + encodeURIComponent(value)
          + '#perf-summary';
      }
    });
  }

  // ─── API 呼び出し ─────────────────────────────────────────────────

  function fetchSummary(apiSlug, userValue) {
    var url = API_BASE + '/' + apiSlug + '&user=' + encodeURIComponent(userValue);
    return fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    });
  }

  // ─── カードレンダラー ─────────────────────────────────────────────

  function renderRenewalCard(data) {
    if (data.error) {
      setCardText('card-renewal', '.within-14', '—');
      setCardText('card-renewal', '.within-28', '—');
      setCardText('card-renewal', '.within-60', '—');
      return;
    }
    setCardText('card-renewal', '.within-14', data.within_14_days + ' 件');
    setCardText('card-renewal', '.within-28', data.within_28_days + ' 件');
    setCardText('card-renewal', '.within-60', data.within_60_days + ' 件');
  }

  function renderAccidentCard(data) {
    if (data.error) {
      setCardText('card-accident', '.high',   '—');
      setCardText('card-accident', '.normal', '—');
      setCardText('card-accident', '.low',    '—');
      return;
    }
    var highEl = document.querySelector('#card-accident .high');
    if (highEl) {
      highEl.textContent = data.high_priority + ' 件';
      // danger クラスは高優先度 > 0 のときのみ付与
      var base = 'biz-metric-value high';
      highEl.className = data.high_priority > 0 ? base + ' danger' : base;
    }
    setCardText('card-accident', '.normal', data.normal_priority + ' 件');
    setCardText('card-accident', '.low',    data.low_priority    + ' 件');
  }

  function renderSalesCaseCard(data) {
    if (data.error) {
      setCardText('card-sales-case', '.prospect-a', '—');
      setCardText('card-sales-case', '.prospect-b', '—');
      setCardText('card-sales-case', '.expected',   '—');
      return;
    }
    setCardText('card-sales-case', '.prospect-a', data.prospect_a          + ' 件');
    setCardText('card-sales-case', '.prospect-b', data.prospect_b          + ' 件');
    setCardText('card-sales-case', '.expected',   data.expected_this_month + ' 件');
  }

  // 成績サマリ（sales-user）および年度プルダウン（fiscal-year）は
  // initReloadDropdown() による URL 再読込に統一した。
  // 3 タイル・3 タブ（全体/損保/生保）・月次推移表の整合を SSR で保証するため、
  // 旧 renderSalesPerformanceCard() / renderMonthlyTrendTable() / initFiscalYearDropdown()
  // は不要となり撤去した。

  // ─── ユーティリティ ──────────────────────────────────────────────

  function setCardText(cardId, selector, text) {
    var el = document.querySelector('#' + cardId + ' ' + selector);
    if (el) el.textContent = text;
  }

  function updateUrlQuery(paramName, value) {
    try {
      var url = new URL(window.location.href);
      url.searchParams.set(paramName, value);
      window.history.replaceState({}, '', url.toString());
    } catch (e) {
      // URL API 非対応ブラウザでは無視
    }
  }

  function dropdownIdToQueryParam(dropdownId) {
    var map = {
      'renewal-user':    'renewal_user',
      'accident-user':   'accident_user',
      'sales-case-user': 'sales_case_user'
    };
    return map[dropdownId] || dropdownId;
  }

  function showCardError(dropdownId) {
    var cardId = {
      'renewal-user':    'card-renewal',
      'accident-user':   'card-accident',
      'sales-case-user': 'card-sales-case'
    }[dropdownId];
    if (!cardId) return;
    var card = document.getElementById(cardId);
    if (card) card.classList.add('card-error');
  }

})();
