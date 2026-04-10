/**
 * dashboard.js
 * ホーム画面 担当者選択ドロップダウン / 年度切り替えの部分更新処理。
 *
 * 依存: window.DASHBOARD_CONFIG = { apiBase: '...', fiscalYear: YYYY, loginUserId: N }
 * window.DASHBOARD_CONFIG は DashboardView.php がインライン <script> で設定する。
 *
 * ポリシー:
 * - ドロップダウン変更時に fetch でカードの JSON だけ取得し DOM を書き換える
 * - location.href / form.submit() / location.reload() は一切使わない
 * - history.replaceState で URL クエリを更新する（pushState は使わない）
 * - innerHTML は使わない。textContent / className のみ書き換える
 */

(function () {
  'use strict';

  var cfg = window.DASHBOARD_CONFIG || {};
  var API_BASE    = (cfg.apiBase    || '').replace(/\/$/, '');
  var FISCAL_YEAR = cfg.fiscalYear  || 0;

  // ─── 初期化 ──────────────────────────────────────────────────────

  initDropdown('renewal-user',   'renewal-summary',          renderRenewalCard);
  initDropdown('accident-user',  'accident-summary',         renderAccidentCard);
  initDropdown('sales-case-user','sales-case-summary',       renderSalesCaseCard);
  initDropdown('sales-user',     'sales-performance-summary',renderSalesPerformanceCard);
  initFiscalYearDropdown();

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

  // ─── API 呼び出し ─────────────────────────────────────────────────

  function fetchSummary(apiSlug, userValue, fiscalYear) {
    var url = API_BASE + '/' + apiSlug + '&user=' + encodeURIComponent(userValue);
    if (fiscalYear) {
      url += '&fiscal_year=' + encodeURIComponent(fiscalYear);
    }
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

  function renderSalesPerformanceCard(data) {
    if (data.error) return;

    var yt = data.year_total || {};
    var tg = data.target     || {};

    // 期間ラベル
    var labelEl = document.querySelector('#card-sales .year-summary-label');
    if (labelEl && yt.period_label) {
      labelEl.textContent = yt.period_label;
    }

    // 年度累計
    setCardText('card-sales', '.year-total', formatNum(yt.amount_thousand_yen));

    // サブ行
    setCardText('card-sales', '.perf-prev-annual',
      yt.previous_year_same_period != null
        ? formatNum(yt.previous_year_same_period) + ' 千円'
        : '—');

    // 前年比
    var yoyEl = document.querySelector('#card-sales .perf-yoy');
    if (yoyEl) {
      yoyEl.textContent = yt.year_over_year_pct != null ? yt.year_over_year_pct + '%' : '—';
      yoyEl.className = yoyEl.className
        .replace(/\bup\b|\bdown\b/g, '').trim();
      if (yt.year_over_year_pct != null) {
        yoyEl.className += ' ' + (yt.year_over_year_pct >= 100 ? 'up' : 'down');
      }
    }

    setCardText('card-sales', '.perf-annual-target',
      tg.yearly != null ? formatNum(tg.yearly) + ' 千円' : '目標未設定');

    // 達成率
    var achRateEl = document.querySelector('#card-sales .perf-achievement-rate');
    if (achRateEl) {
      var ytAchRate = (yt.achievement_rate_pct != null) ? yt.achievement_rate_pct : null;
      if (ytAchRate != null) {
        achRateEl.textContent = Number(ytAchRate).toFixed(1) + '%';
        achRateEl.className = achRateEl.className.replace(/\bachievement-over\b/g, '').trim();
        if (ytAchRate >= 100) { achRateEl.className += ' achievement-over'; }
      } else {
        achRateEl.textContent = '目標未設定';
        achRateEl.className = achRateEl.className.replace(/\bachievement-over\b/g, '').trim();
      }
    }

    // 月次推移テーブル
    if (Array.isArray(data.monthly_trend)) {
      renderMonthlyTrendTable(data.monthly_trend, data.annual_total || null);
    }
  }

  function renderMonthlyTrendTable(trend, annualTotal) {
    var tbl = document.getElementById('monthly-trend');
    if (!tbl) return;

    trend.forEach(function (m) {
      var cur  = tbl.querySelector('td[data-month="' + m.month + '"].current');
      var prev = tbl.querySelector('td[data-month="' + m.month + '"].previous');
      var diff = tbl.querySelector('td[data-month="' + m.month + '"].diff');
      var ach  = tbl.querySelector('td[data-month="' + m.month + '"].achievement-rate');

      if (m.is_future) {
        if (cur)  cur.textContent  = '—';
        if (prev) prev.textContent = '—';
        if (diff) {
          diff.textContent = '—';
          diff.className   = diff.className.replace(/\bup\b|\bdown\b/g, '').trim();
        }
        if (ach) {
          ach.textContent = '—';
          ach.className   = ach.className.replace(/\bachievement-over\b/g, '').trim();
        }
      } else {
        if (cur) {
          cur.textContent = m.current != null ? formatNum(m.current) : '—';
        }
        if (prev) {
          prev.textContent = m.previous != null ? formatNum(m.previous) : '—';
        }
        if (diff && m.diff != null) {
          diff.textContent = (m.diff >= 0 ? '+' : '') + formatNum(m.diff);
          diff.className   = diff.className.replace(/\bup\b|\bdown\b/g, '').trim();
          diff.className  += ' ' + (m.diff >= 0 ? 'up' : 'down');
        } else if (diff) {
          diff.textContent = '—';
          diff.className   = diff.className.replace(/\bup\b|\bdown\b/g, '').trim();
        }
        if (ach) {
          var rateVal = m.cumulative_achievement_rate_pct;
          ach.textContent = rateVal != null ? Number(rateVal).toFixed(1) + '%' : '—';
          ach.className   = ach.className.replace(/\bachievement-over\b/g, '').trim();
          if (rateVal != null && rateVal >= 100) { ach.className += ' achievement-over'; }
        }
      }
    });

    // 年間列（YTD 同士の比較）
    if (annualTotal) {
      var aCur  = tbl.querySelector('td.annual-current');
      var aPrev = tbl.querySelector('td.annual-previous');
      var aDiff = tbl.querySelector('td.annual-diff');

      if (aCur) {
        aCur.textContent = annualTotal.current != null ? formatNum(annualTotal.current) : '—';
      }
      if (aPrev) {
        aPrev.textContent = annualTotal.previous != null ? formatNum(annualTotal.previous) : '—';
      }
      if (aDiff) {
        if (annualTotal.diff != null) {
          aDiff.textContent = (annualTotal.diff >= 0 ? '+' : '') + formatNum(annualTotal.diff);
          aDiff.className   = aDiff.className.replace(/\bup\b|\bdown\b/g, '').trim();
          aDiff.className  += ' ' + (annualTotal.diff >= 0 ? 'up' : 'down');
        } else {
          aDiff.textContent = '—';
          aDiff.className   = aDiff.className.replace(/\bup\b|\bdown\b/g, '').trim();
        }
      }
    }

    // 年間累積達成率
    var aAch = tbl.querySelector('td.achievement-annual');
    if (aAch) {
      var lastAch = null;
      trend.forEach(function (m) {
        if (m.cumulative_achievement_rate_pct != null) { lastAch = m.cumulative_achievement_rate_pct; }
      });
      aAch.textContent = lastAch != null ? Number(lastAch).toFixed(1) + '%' : '—';
      aAch.className   = aAch.className.replace(/\bachievement-over\b/g, '').trim();
      if (lastAch != null && lastAch >= 100) { aAch.className += ' achievement-over'; }
    }
  }

  // ─── 年度プルダウン ───────────────────────────────────────────────

  function initFiscalYearDropdown() {
    var el = document.getElementById('fiscal-year');
    if (!el) return;

    el.addEventListener('change', function () {
      var fiscalYear = parseInt(this.value, 10);
      if (!fiscalYear) return;
      FISCAL_YEAR = fiscalYear;

      var userEl = document.getElementById('sales-user');
      var userValue = userEl ? userEl.value : 'self';

      fetchSummary('sales-performance-summary', userValue, fiscalYear)
        .then(function (data) {
          renderSalesPerformanceCard(data);
          updateUrlQuery('fiscal_year', fiscalYear);
        })
        .catch(function (err) {
          console.error('Fiscal year update failed:', err);
        });
    });
  }

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
      'sales-case-user': 'sales_case_user',
      'sales-user':      'sales_user'
    };
    return map[dropdownId] || dropdownId;
  }

  function showCardError(dropdownId) {
    var cardId = {
      'renewal-user':    'card-renewal',
      'accident-user':   'card-accident',
      'sales-case-user': 'card-sales-case',
      'sales-user':      'card-sales'
    }[dropdownId];
    if (!cardId) return;
    var card = document.getElementById(cardId);
    if (card) card.classList.add('card-error');
  }

  function formatNum(n) {
    if (n == null) return '—';
    return Number(n).toLocaleString('ja-JP');
  }

})();
