<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class DashboardView
{
    private const FISCAL_MONTHS = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $auth,
        ?string $flashError,
        string $renewalListUrl,
        string $salesCaseListUrl,
        string $salesListUrl,
        string $accidentListUrl,
        string $tenantSettingsUrl,
        string $activityListUrl,
        string $dashboardUrl,
        array $layoutOptions
    ): string {
        /** @var array<string, mixed> $data */
        $data = is_array($layoutOptions['dashboardData'] ?? null) ? $layoutOptions['dashboardData'] : [];

        $fiscalYear      = (int) ($data['fiscal_year']   ?? date('Y'));
        $availableYears  = is_array($data['available_years'] ?? null) ? (array) $data['available_years'] : [$fiscalYear];
        $currentMonth    = (int) ($data['current_month'] ?? (int) date('n'));
        $scope           = (string) ($data['scope']      ?? 'self');
        $role            = (string) ($data['role']       ?? 'member');
        $todayLabel      = (string) ($data['today']      ?? '');

        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        // ─── 実績データ準備 ──────────────────────────────────────────────
        $perfCurrentRaw = $data['perf_current'] ?? [];
        $perfPrevRaw    = $data['perf_prev']    ?? [];
        $targetsRaw     = $data['targets']      ?? [];
        $perfError      = isset($perfCurrentRaw['error']);

        /** @var array<int, array{premium: int, count: int}> $perfCurrent */
        $perfCurrent = [];
        /** @var array<int, array{premium: int, count: int}> $perfPrev */
        $perfPrev = [];
        /** @var array<int, int> $targetMonthly */
        $targetMonthly = [];
        $targetAnnual  = null;

        if (!$perfError && is_array($perfCurrentRaw)) {
            foreach (self::FISCAL_MONTHS as $m) {
                $perfCurrent[$m] = [
                    'premium' => (int) ($perfCurrentRaw[$m]['premium'] ?? 0),
                    'count'   => (int) ($perfCurrentRaw[$m]['count']   ?? 0),
                ];
            }
        }
        if (is_array($perfPrevRaw) && !isset($perfPrevRaw['error'])) {
            foreach (self::FISCAL_MONTHS as $m) {
                $perfPrev[$m] = [
                    'premium' => (int) ($perfPrevRaw[$m]['premium'] ?? 0),
                    'count'   => (int) ($perfPrevRaw[$m]['count']   ?? 0),
                ];
            }
        }
        if (is_array($targetsRaw) && !isset($targetsRaw['error'])) {
            $targetAnnual  = isset($targetsRaw['annual']) ? (int) $targetsRaw['annual'] : null;
            $targetMonthly = is_array($targetsRaw['monthly'] ?? null) ? (array) $targetsRaw['monthly'] : [];
        }

        // 今月 & 年度累計計算
        $thisMonthPremium = 0;
        $thisMonthCount   = 0;
        $annualPremium    = 0;
        $prevAnnualPremium = 0;

        if (!$perfError) {
            $thisMonthPremium = $perfCurrent[$currentMonth]['premium'] ?? 0;
            $thisMonthCount   = $perfCurrent[$currentMonth]['count']   ?? 0;

            foreach (self::FISCAL_MONTHS as $m) {
                $annualPremium     += $perfCurrent[$m]['premium'] ?? 0;
                $prevAnnualPremium += $perfPrev[$m]['premium']    ?? 0;
                if ($m === $currentMonth) {
                    break;
                }
            }
        }

        $prevMonthPremium = $perfPrev[$currentMonth]['premium'] ?? 0;

        // バーチャート用最大値
        $maxBarValue = 1; // ゼロ除算防止
        foreach (self::FISCAL_MONTHS as $m) {
            $maxBarValue = max($maxBarValue, $perfCurrent[$m]['premium'] ?? 0, $perfPrev[$m]['premium'] ?? 0);
        }

        // ─── CSS ────────────────────────────────────────────────────────
        $css = '<style>'
            . '.alert-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}'
            . '.alert-card{border-radius:var(--radius-md);padding:14px 18px;cursor:pointer;transition:box-shadow 0.15s;text-decoration:none;display:block;}'
            . '.alert-card:hover{box-shadow:0 2px 8px rgba(0,0,0,0.08);}'
            . '.alert-card-danger{background:var(--bg-danger);border:0.5px solid var(--border-danger);}'
            . '.alert-card-warning{background:var(--bg-warning);border:0.5px solid var(--border-warning);}'
            . '.alert-card-label{font-size:11.5px;color:var(--text-secondary);margin-bottom:4px;}'
            . '.alert-card-danger .alert-card-label{color:var(--text-danger);}'
            . '.alert-card-warning .alert-card-label{color:var(--text-warning);}'
            . '.alert-card-value{font-size:28px;font-weight:500;line-height:1.2;}'
            . '.alert-card-danger .alert-card-value{color:var(--text-danger);}'
            . '.alert-card-warning .alert-card-value{color:var(--text-warning);}'
            . '.alert-card-sub{font-size:11px;margin-top:4px;}'
            . '.alert-card-danger .alert-card-sub{color:var(--text-danger);opacity:0.8;}'
            . '.alert-card-warning .alert-card-sub{color:var(--text-warning);opacity:0.8;}'
            . '.biz-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}'
            . '.biz-card{background:var(--bg-primary);border:0.5px solid var(--border-light);border-radius:var(--radius-lg);padding:16px 18px;transition:box-shadow 0.15s,border-color 0.15s;text-decoration:none;display:block;color:inherit;}'
            . '.biz-card-link{cursor:pointer;}.biz-card-link:hover{box-shadow:0 2px 8px rgba(0,0,0,0.07);border-color:var(--border-medium);}'
            . '.biz-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}'
            . '.biz-card-title{font-size:12.5px;font-weight:500;color:var(--text-primary);}'
            . '.biz-card-arrow{font-size:14px;color:var(--text-secondary);text-decoration:none;}'
            . '.biz-card-metrics{display:flex;flex-direction:column;gap:6px;}'
            . '.biz-metric{display:flex;justify-content:space-between;align-items:baseline;}'
            . '.biz-metric-label{font-size:12px;color:var(--text-secondary);}'
            . '.biz-metric-value{font-size:14px;font-weight:500;color:var(--text-primary);}'
            . '.biz-metric-value.accent{color:var(--text-info);}'
            . '.biz-metric-value.danger{color:var(--text-danger);}'
            . '.biz-card-single{display:flex;align-items:center;justify-content:center;min-height:52px;}'
            . '.biz-card-single-text{font-size:13px;color:var(--text-info);}'
            . '.perf-card{background:var(--bg-primary);border:0.5px solid var(--border-light);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:14px;}'
            . '.perf-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}'
            . '.perf-title{font-size:12.5px;font-weight:500;color:var(--text-primary);}'
            . '.perf-toggle{display:flex;gap:0;border:0.5px solid var(--border-medium);border-radius:var(--radius-md);overflow:hidden;}'
            . '.perf-toggle-btn{padding:4px 14px;font-size:11.5px;text-decoration:none;display:inline-block;background:var(--bg-primary);color:var(--text-secondary);border:none;border-right:0.5px solid var(--border-medium);transition:all 0.12s;font-family:inherit;}'
            . '.perf-toggle-btn:last-child{border-right:none;}'
            . '.perf-toggle-btn.active{background:var(--bg-info);color:var(--text-info);font-weight:500;}'
            . '.perf-toggle-btn:hover:not(.active){background:var(--bg-secondary);}'
            . '.perf-body{display:grid;grid-template-columns:1fr 1px 1fr;gap:0;}'
            . '.perf-divider{background:var(--border-light);}'
            . '.perf-section{padding:0 20px;}'
            . '.perf-section:first-child{padding-left:0;}'
            . '.perf-section:last-child{padding-right:0;}'
            . '.perf-section-label{font-size:11.5px;font-weight:500;color:var(--text-secondary);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.4px;}'
            . '.perf-main-row{display:flex;align-items:baseline;gap:8px;margin-bottom:8px;}'
            . '.perf-main-value{font-size:26px;font-weight:500;color:var(--text-primary);line-height:1;}'
            . '.perf-main-unit{font-size:12px;color:var(--text-secondary);}'
            . '.perf-sub-rows{display:flex;flex-direction:column;gap:4px;}'
            . '.perf-sub-row{display:flex;justify-content:space-between;align-items:baseline;font-size:12px;}'
            . '.perf-sub-label{color:var(--text-secondary);}'
            . '.perf-sub-value{font-weight:500;color:var(--text-primary);}'
            . '.perf-sub-value.up{color:var(--text-success);}'
            . '.perf-sub-value.down{color:var(--text-danger);}'
            . '.perf-chart{margin-top:16px;padding-top:14px;border-top:0.5px solid var(--border-light);}'
            . '.perf-chart-title{font-size:11.5px;color:var(--text-secondary);margin-bottom:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.4px;}'
            . '.chart-container{width:100%;overflow-x:auto;}'
            . '.chart-table{width:100%;border-collapse:collapse;}'
            . '.chart-table th{font-size:11px;color:var(--text-secondary);font-weight:500;padding:4px 6px;text-align:center;white-space:nowrap;border-bottom:0.5px solid var(--border-light);}'
            . '.chart-table th.current{color:var(--text-info);font-weight:600;}'
            . '.chart-table td{font-size:11.5px;padding:5px 6px;text-align:right;white-space:nowrap;border-bottom:0.5px solid var(--border-light);}'
            . '.chart-table td.row-header{text-align:left;color:var(--text-secondary);font-size:11px;}'
            . '.chart-table td.current{background:rgba(55,138,221,0.04);font-weight:500;}'
            . '.chart-table td.up{color:var(--text-success);}'
            . '.chart-table td.down{color:var(--text-danger);}'
            . '.chart-bar-cell{padding:4px 4px!important;vertical-align:bottom;}'
            . '.bar-group{display:flex;gap:2px;align-items:flex-end;justify-content:center;height:48px;}'
            . '.bar{width:12px;border-radius:2px 2px 0 0;transition:height 0.3s;}'
            . '.bar.this-year{background:var(--border-info);}'
            . '.bar.last-year{background:var(--bg-tertiary);}'
            . '.activity-card{background:var(--bg-primary);border:0.5px solid var(--border-light);border-radius:var(--radius-lg);padding:16px 18px;}'
            . '.activity-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}'
            . '.activity-title{font-size:12.5px;font-weight:500;color:var(--text-primary);}'
            . '.activity-link{font-size:12px;color:var(--text-info);cursor:pointer;text-decoration:none;}'
            . '.activity-link:hover{text-decoration:underline;}'
            . '.activity-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;}'
            . '.activity-row:not(:last-child){border-bottom:0.5px solid var(--border-light);}'
            . '.activity-label{font-size:12.5px;color:var(--text-secondary);}'
            . '.activity-value{font-size:13px;font-weight:500;color:var(--text-primary);}'
            . '.admin-section{margin-top:8px;padding-top:14px;border-top:0.5px solid var(--border-light);}'
            . '.admin-link{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:var(--text-secondary);cursor:pointer;text-decoration:none;padding:6px 0;}'
            . '.admin-link:hover{color:var(--text-primary);}'
            . '.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}'
            . '.section-label{font-size:11.5px;font-weight:500;color:var(--text-secondary);margin-bottom:12px;padding-bottom:5px;text-transform:uppercase;letter-spacing:0.4px;}'
            . '@media(max-width:760px){'
            . '.alert-grid{grid-template-columns:1fr;}'
            . '.biz-grid{grid-template-columns:1fr;}'
            . '.perf-body{grid-template-columns:1fr;gap:14px;}'
            . '.perf-divider{display:none;}'
            . '.perf-section{padding:0;}'
            . '.perf-section:last-child{padding-top:14px;border-top:0.5px solid var(--border-light);}'
            . '.bottom-grid{grid-template-columns:1fr;}'
            . '.chart-container{overflow-x:scroll;-webkit-overflow-scrolling:touch;}'
            . '.chart-table{min-width:620px;}'
            . '}'
            . '</style>';

        // ─── ページヘッダー ─────────────────────────────────────────────
        $pageHeader = '<div class="page-header">'
            . '<div><h1 class="title">ホーム</h1></div>'
            . '<div class="page-header-meta">' . Layout::escape($todayLabel) . '</div>'
            . '</div>';

        // ─── 上段: 要確認エリア ─────────────────────────────────────────
        $renewalData  = is_array($data['renewal'] ?? null)  ? (array) $data['renewal']  : [];
        $accidentData = is_array($data['accident'] ?? null) ? (array) $data['accident'] : [];

        $renewalOverdue  = isset($renewalData['error'])  ? null : (int) ($renewalData['overdue']          ?? 0);
        $renewalWithin7  = isset($renewalData['error'])  ? null : (int) ($renewalData['within_7d']        ?? 0);
        $accidentHighCnt = isset($accidentData['error']) ? null : (int) ($accidentData['high_priority']   ?? 0);

        $renewalOverdueVal  = $renewalOverdue  !== null ? $renewalOverdue  . ' <span style="font-size:13px;font-weight:400;">件</span>' : '—';
        $renewalWithin7Val  = $renewalWithin7  !== null ? $renewalWithin7  . ' <span style="font-size:13px;font-weight:400;">件</span>' : '—';
        $accidentHighVal    = $accidentHighCnt !== null ? $accidentHighCnt . ' <span style="font-size:13px;font-weight:400;">件</span>' : '—';

        $alertGrid = '<div class="section-label">要確認</div>'
            . '<div class="alert-grid">'
            // 満期（対応遅れ＋7日以内）を1枚にまとめる
            . '<a href="' . Layout::escape($renewalListUrl) . '" class="alert-card alert-card-danger">'
            . '<div class="alert-card-label">満期</div>'
            . '<div style="display:flex;gap:24px;align-items:baseline;margin:6px 0 4px;">'
            . '<div><span style="font-size:11.5px;margin-right:4px;">対応遅れ</span><span style="font-size:22px;font-weight:700;line-height:1;">' . ($renewalOverdue !== null ? $renewalOverdue : '—') . '</span> <span style="font-size:13px;font-weight:400;">件</span></div>'
            . '<div><span style="font-size:11.5px;margin-right:4px;">7日以内</span><span style="font-size:22px;font-weight:700;line-height:1;">' . ($renewalWithin7 !== null ? $renewalWithin7 : '—') . '</span> <span style="font-size:13px;font-weight:400;">件</span></div>'
            . '</div>'
            . '<div class="alert-card-sub">満期一覧へ</div>'
            . '</a>'
            // 事故 — 高優先度未完了
            . '<a href="' . Layout::escape($accidentListUrl) . '" class="alert-card alert-card-warning">'
            . '<div class="alert-card-label">事故 — 高優先度未完了</div>'
            . '<div class="alert-card-value">' . $accidentHighVal . '</div>'
            . '<div class="alert-card-sub">優先度「高」で未完了の案件 → 事故案件一覧へ</div>'
            . '</a>'
            . '</div>';

        // ─── 中段: 業務入口 ─────────────────────────────────────────────
        $activityData   = is_array($data['activity'] ?? null) ? (array) $data['activity'] : [];

        $renewalBizScope   = (string) ($data['renewal_scope']    ?? 'all');
        $accidentBizScope  = (string) ($data['accident_scope']   ?? 'all');
        $salesCaseBizScope = (string) ($data['sales_case_scope'] ?? 'all');
        $renewalBizData    = is_array($data['renewal_biz']    ?? null) ? (array) $data['renewal_biz']    : [];
        $accidentBizData   = is_array($data['accident_biz']   ?? null) ? (array) $data['accident_biz']   : [];
        $salesCaseBizData  = is_array($data['sales_case_biz'] ?? null) ? (array) $data['sales_case_biz'] : [];

        // トグル URL（perf scope と相手方 biz scope を保持して切り替え）
        $perfParam = ($scope === 'team') ? '&scope=team' : '';
        $accParam  = ($accidentBizScope  === 'self') ? '&accident_scope=self'   : '';
        $renParam  = ($renewalBizScope   === 'self') ? '&renewal_scope=self'    : '';
        $scParam   = ($salesCaseBizScope === 'self') ? '&sales_case_scope=self' : '';

        $renewalSelfUrl  = Layout::escape($dashboardUrl . $perfParam . '&renewal_scope=self' . $accParam . $scParam);
        $renewalAllUrl   = Layout::escape($dashboardUrl . $perfParam . $accParam . $scParam);
        $accidentSelfUrl = Layout::escape($dashboardUrl . $perfParam . $renParam . '&accident_scope=self' . $scParam);
        $accidentAllUrl  = Layout::escape($dashboardUrl . $perfParam . $renParam . $scParam);
        $scSelfUrl       = Layout::escape($dashboardUrl . $perfParam . $renParam . $accParam . '&sales_case_scope=self');
        $scAllUrl        = Layout::escape($dashboardUrl . $perfParam . $renParam . $accParam);

        $renewalSelfActive   = $renewalBizScope   === 'self' ? ' active' : '';
        $renewalAllActive    = $renewalBizScope   !== 'self' ? ' active' : '';
        $accidentSelfActive  = $accidentBizScope  === 'self' ? ' active' : '';
        $accidentAllActive   = $accidentBizScope  !== 'self' ? ' active' : '';
        $scSelfActive        = $salesCaseBizScope === 'self' ? ' active' : '';
        $scAllActive         = $salesCaseBizScope !== 'self' ? ' active' : '';

        $renewalToggle = '<div class="perf-toggle">'
            . '<a href="' . $renewalSelfUrl . '" class="perf-toggle-btn' . $renewalSelfActive . '">自分</a>'
            . '<a href="' . $renewalAllUrl  . '" class="perf-toggle-btn' . $renewalAllActive  . '">全体</a>'
            . '</div>';
        $accidentToggle = '<div class="perf-toggle">'
            . '<a href="' . $accidentSelfUrl . '" class="perf-toggle-btn' . $accidentSelfActive . '">自分</a>'
            . '<a href="' . $accidentAllUrl  . '" class="perf-toggle-btn' . $accidentAllActive  . '">全体</a>'
            . '</div>';
        $salesCaseToggle = '<div class="perf-toggle">'
            . '<a href="' . $scSelfUrl . '" class="perf-toggle-btn' . $scSelfActive . '">自分</a>'
            . '<a href="' . $scAllUrl  . '" class="perf-toggle-btn' . $scAllActive  . '">全体</a>'
            . '</div>';

        // biz カード用集計（スコープ連動）
        $renewalWithin14Str = isset($renewalBizData['error']) ? '—' : ((int) ($renewalBizData['within_14d'] ?? 0)) . ' 件';
        $renewalWithin28Str = isset($renewalBizData['error']) ? '—' : ((int) ($renewalBizData['within_28d'] ?? 0)) . ' 件';
        $renewalWithin60Str = isset($renewalBizData['error']) ? '—' : ((int) ($renewalBizData['within_60d'] ?? 0)) . ' 件';
        $accidentHighStr    = isset($accidentBizData['error']) ? '—' : ((int) ($accidentBizData['high_priority'] ?? 0)) . ' 件';
        $accidentMidStr     = isset($accidentBizData['error']) ? '—' : ((int) ($accidentBizData['mid_priority']  ?? 0)) . ' 件';
        $accidentLowStr     = isset($accidentBizData['error']) ? '—' : ((int) ($accidentBizData['low_priority']  ?? 0)) . ' 件';
        $scRankAStr         = isset($salesCaseBizData['error']) ? '—' : ((int) ($salesCaseBizData['rank_a']             ?? 0)) . ' 件';
        $scRankBStr         = isset($salesCaseBizData['error']) ? '—' : ((int) ($salesCaseBizData['rank_b']             ?? 0)) . ' 件';
        $scClosingStr       = isset($salesCaseBizData['error']) ? '—' : ((int) ($salesCaseBizData['closing_this_month'] ?? 0)) . ' 件';

        $accidentHighCls = (!isset($accidentBizData['error']) && (int) ($accidentBizData['high_priority'] ?? 0) > 0) ? ' danger' : '';

        $bizGrid = '<div class="section-label">業務入口</div>'
            . '<div class="biz-grid">'
            // 満期業務（div ベース — ヘッダー右にトグルと→）
            . '<div class="biz-card">'
            . '<div class="biz-card-header">'
            . '<span class="biz-card-title">満期業務 <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">(未完了)</span></span>'
            . $renewalToggle
            . '</div>'
            . '<div class="biz-card-metrics">'
            . '<div class="biz-metric"><span class="biz-metric-label">14日以内</span><span class="biz-metric-value accent">' . Layout::escape($renewalWithin14Str) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">28日以内</span><span class="biz-metric-value">' . Layout::escape($renewalWithin28Str) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">60日以内</span><span class="biz-metric-value">' . Layout::escape($renewalWithin60Str) . '</span></div>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($renewalListUrl) . '" style="font-size:11.5px;color:var(--text-info);text-decoration:none;">満期一覧へ</a></div>'
            . '</div>'
            // 事故案件（div ベース）
            . '<div class="biz-card">'
            . '<div class="biz-card-header">'
            . '<span class="biz-card-title">事故案件 <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">(未完了)</span></span>'
            . $accidentToggle
            . '</div>'
            . '<div class="biz-card-metrics">'
            . '<div class="biz-metric"><span class="biz-metric-label">高優先度</span><span class="biz-metric-value' . $accidentHighCls . '">' . Layout::escape($accidentHighStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">中優先度</span><span class="biz-metric-value">' . Layout::escape($accidentMidStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">低優先度</span><span class="biz-metric-value">' . Layout::escape($accidentLowStr) . '</span></div>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($accidentListUrl) . '" style="font-size:11.5px;color:var(--text-info);text-decoration:none;">事故案件一覧へ</a></div>'
            . '</div>'
            // 見込管理
            . '<div class="biz-card">'
            . '<div class="biz-card-header">'
            . '<span class="biz-card-title">見込管理</span>'
            . $salesCaseToggle
            . '</div>'
            . '<div class="biz-card-metrics">'
            . '<div class="biz-metric"><span class="biz-metric-label">見込A</span><span class="biz-metric-value accent">' . Layout::escape($scRankAStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">見込B</span><span class="biz-metric-value">' . Layout::escape($scRankBStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">今月成約予定</span><span class="biz-metric-value">' . Layout::escape($scClosingStr) . '</span></div>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($salesCaseListUrl) . '" style="font-size:11.5px;color:var(--text-info);text-decoration:none;">見込案件一覧へ</a></div>'
            . '</div>'
            . '</div>';

        // ─── 実績サマリカード ───────────────────────────────────────────
        // ─── 実績サマリ セクションヘッダー（年度ボタン + スコープトグル）─────
        $perfParam = ($scope === 'team') ? '&scope=team' : '';

        // 年度プルダウン
        $yearOptions = '';
        foreach ($availableYears as $yr) {
            $yr = (int) $yr;
            $yearUrl = Layout::escape($dashboardUrl . $perfParam . '&fiscal_year=' . $yr . $renParam . $accParam . $scParam);
            $selected = ($yr === $fiscalYear) ? ' selected' : '';
            $yearOptions .= '<option value="' . $yearUrl . '"' . $selected . '>' . $yr . '年度</option>';
        }
        $yearToggle = '<select onchange="location.href=this.value" style="font-size:11.5px;padding:3px 6px;border:0.5px solid var(--border-medium);border-radius:var(--radius-md);background:var(--bg-primary);color:var(--text-primary);cursor:pointer;">'
            . $yearOptions
            . '</select>';

        // スコープトグル
        $selfUrl = Layout::escape($dashboardUrl . '&scope=self' . '&fiscal_year=' . $fiscalYear . $renParam . $accParam . $scParam);
        $teamUrl = Layout::escape($dashboardUrl . '&scope=team' . '&fiscal_year=' . $fiscalYear . $renParam . $accParam . $scParam);
        $selfActive = $scope === 'self' ? ' active' : '';
        $teamActive = $scope === 'team' ? ' active' : '';
        $scopeToggle = '<div class="perf-toggle">'
            . '<a href="' . $selfUrl . '" class="perf-toggle-btn' . $selfActive . '">自分</a>';
        if ($role === 'admin') {
            $scopeToggle .= '<a href="' . $teamUrl . '" class="perf-toggle-btn' . $teamActive . '">全体</a>';
        }
        $scopeToggle .= '</div>';

        $perfSectionHeader = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">'
            . '<div class="section-label" style="margin-bottom:0;">実績サマリ</div>'
            . '<div style="display:flex;align-items:center;gap:8px;">' . $yearToggle . $scopeToggle . '</div>'
            . '</div>';

        $perfCard = $perfSectionHeader . self::renderPerfCard(
            $fiscalYear,
            $currentMonth,
            $perfError,
            $perfCurrent,
            $perfPrev,
            $thisMonthPremium,
            $thisMonthCount,
            $prevMonthPremium,
            $annualPremium,
            $prevAnnualPremium,
            $targetAnnual,
            $targetMonthly,
            $maxBarValue,
            $salesListUrl
        );

        $bottomGrid = '';

        // ─── 管理セクション（管理者のみ） ───────────────────────────────
        $adminSection = '';
        if ($role === 'admin') {
            $drData = is_array($data['daily_report_status'] ?? null) ? (array) $data['daily_report_status'] : [];
            $drError = isset($drData['error']);
            $drTotal       = $drError ? '—' : (int) ($drData['total']       ?? 0);
            $drSubmitted   = $drError ? '—' : (int) ($drData['submitted']   ?? 0);
            $drUnsubmitted = $drError ? '—' : (int) ($drData['unsubmitted'] ?? 0);
            $drStatusStr   = $drError
                ? '取得できませんでした'
                : ('提出済 ' . $drSubmitted . '名 / 未提出 ' . $drUnsubmitted . '名');

            $adminSection = '<div class="admin-section">'
                . '<div class="section-label">管理</div>'
                . '<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">'
                . '<a href="' . Layout::escape($tenantSettingsUrl) . '" class="admin-link">⚙ テナント設定</a>'
                . '<span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:8px;">'
                . '<span class="badge badge-info">管理者</span>'
                . '日報提出状況: ' . Layout::escape($drStatusStr)
                . '</span>'
                . '</div>'
                . '</div>';
        }

        $content = $css
            . $errorHtml
            . $pageHeader
            . $alertGrid
            . $bizGrid
            . $perfCard
            . $bottomGrid
            . $adminSection;

        return Layout::render('ホーム', $content, $layoutOptions);
    }

    /**
     * @param array<int, array{premium: int, count: int}> $perfCurrent
     * @param array<int, array{premium: int, count: int}> $perfPrev
     * @param array<int, int> $targetMonthly
     */
    private static function renderPerfCard(
        int $fiscalYear,
        int $currentMonth,
        bool $perfError,
        array $perfCurrent,
        array $perfPrev,
        int $thisMonthPremium,
        int $thisMonthCount,
        int $prevMonthPremium,
        int $annualPremium,
        int $prevAnnualPremium,
        ?int $targetAnnual,
        array $targetMonthly,
        int $maxBarValue,
        string $salesListUrl
    ): string {
        $monthNames = [1 => '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

        if ($perfError) {
            $perfBody = '<div style="color:var(--text-secondary);font-size:12px;padding:8px 0;">取得できませんでした</div>';
        } else {
            // 今月目標
            $monthTarget   = $targetMonthly[$currentMonth] ?? null;
            $monthTargetStr = $monthTarget !== null
                ? number_format((int) floor($monthTarget / 1000)) . ' 千円'
                : '目標未設定';

            // 目標進捗率
            if ($monthTarget === null || $monthTarget === 0) {
                $progressStr = '目標未設定';
            } else {
                $progressStr = floor($thisMonthPremium / $monthTarget * 100) . '%';
            }

            // 前年同月
            $prevMonthStr = $prevMonthPremium > 0
                ? number_format((int) floor($prevMonthPremium / 1000)) . ' 千円'
                : '—';

            // 前年比（年度累計）
            if ($prevAnnualPremium === 0) {
                $yoyClass = '';
                $yoyStr   = '—';
            } else {
                $yoyPct = floor($annualPremium / $prevAnnualPremium * 100);
                $yoyClass = $yoyPct >= 100 ? ' up' : ' down';
                $yoyStr   = $yoyPct . '%';
            }

            // 年度目標
            $annualTargetStr = $targetAnnual !== null
                ? number_format((int) floor($targetAnnual / 1000)) . ' 千円'
                : '目標未設定';

            $currentMonthLabel = $monthNames[$currentMonth] ?? '';

            $perfBody = '<div class="perf-body">'
                // 今月の速報
                . '<div class="perf-section">'
                . '<div class="perf-section-label">今月（' . Layout::escape($currentMonthLabel) . '）</div>'
                . '<div class="perf-main-row">'
                . '<span class="perf-main-value">' . Layout::escape(number_format((int) floor($thisMonthPremium / 1000))) . '</span>'
                . '<span class="perf-main-unit">千円 / ' . Layout::escape((string) $thisMonthCount) . '件</span>'
                . '</div>'
                . '<div class="perf-sub-rows">'
                . '<div class="perf-sub-row"><span class="perf-sub-label">目標</span><span class="perf-sub-value">' . Layout::escape($monthTargetStr) . '</span></div>'
                . '<div class="perf-sub-row"><span class="perf-sub-label">目標進捗</span><span class="perf-sub-value">' . Layout::escape($progressStr) . '</span></div>'
                . '<div class="perf-sub-row"><span class="perf-sub-label">前年同月</span><span class="perf-sub-value">' . Layout::escape($prevMonthStr) . '</span></div>'
                . '</div>'
                . '</div>'
                . '<div class="perf-divider"></div>'
                // 年度累計
                . '<div class="perf-section">'
                . '<div class="perf-section-label">年度累計（4月〜）</div>'
                . '<div class="perf-main-row">'
                . '<span class="perf-main-value">' . Layout::escape(number_format((int) floor($annualPremium / 1000))) . '</span>'
                . '<span class="perf-main-unit">千円</span>'
                . '</div>'
                . '<div class="perf-sub-rows">'
                . '<div class="perf-sub-row"><span class="perf-sub-label">前年同期累計</span><span class="perf-sub-value">' . ($prevAnnualPremium > 0 ? Layout::escape(number_format((int) floor($prevAnnualPremium / 1000)) . ' 千円') : '—') . '</span></div>'
                . '<div class="perf-sub-row"><span class="perf-sub-label">前年比</span><span class="perf-sub-value' . $yoyClass . '">' . Layout::escape($yoyStr) . '</span></div>'
                . '<div class="perf-sub-row"><span class="perf-sub-label">年度目標</span><span class="perf-sub-value">' . Layout::escape($annualTargetStr) . '</span></div>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        // 月次推移テーブル
        $chartSection = self::renderChartSection(
            $fiscalYear,
            $currentMonth,
            $perfError,
            $perfCurrent,
            $perfPrev,
            $targetMonthly,
            $targetAnnual,
            $maxBarValue,
            $salesListUrl
        );

        return '<div class="perf-card">'
            . $perfBody
            . $chartSection
            . '</div>';
    }

    /**
     * @param array<int, array{premium: int, count: int}> $perfCurrent
     * @param array<int, array{premium: int, count: int}> $perfPrev
     * @param array<int, int> $targetMonthly
     */
    private static function renderChartSection(
        int $fiscalYear,
        int $currentMonth,
        bool $perfError,
        array $perfCurrent,
        array $perfPrev,
        array $targetMonthly,
        ?int $targetAnnual,
        int $maxBarValue,
        string $salesListUrl
    ): string {
        $monthLabels = [4 => '4月', 5 => '5月', 6 => '6月', 7 => '7月', 8 => '8月', 9 => '9月',
                        10 => '10月', 11 => '11月', 12 => '12月', 1 => '1月', 2 => '2月', 3 => '3月'];

        // 年度ラベル（西暦）
        $ryLabel = (string) $fiscalYear;

        // thead
        $thCells = '<th></th>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $cls = $isCurrent ? ' class="current"' : '';
            $thCells .= '<th' . $cls . '>' . Layout::escape($monthLabels[$m] ?? '') . '</th>';
        }
        $thCells .= '<th>年間</th>';

        // バーチャート行
        $barRow = '<tr><td class="row-header"></td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $tdCls = $isCurrent ? ' class="chart-bar-cell current"' : ' class="chart-bar-cell"';
            $cy = $perfCurrent[$m]['premium'] ?? 0;
            $py = $perfPrev[$m]['premium']    ?? 0;
            $cyH = $cy > 0 ? max(2, (int) floor($cy / $maxBarValue * 48)) : 0;
            $pyH = $py > 0 ? max(2, (int) floor($py / $maxBarValue * 48)) : 0;
            $cyTitle = $cy > 0 ? ' title="' . Layout::escape($ryLabel . ': ' . number_format((int) floor($cy / 1000))) . '"' : '';
            $pyTitle = $py > 0 ? ' title="' . Layout::escape('前年: ' . number_format((int) floor($py / 1000))) . '"' : '';
            $barRow .= '<td' . $tdCls . '>'
                . '<div class="bar-group">'
                . '<div class="bar this-year" style="height:' . $cyH . 'px;"' . $cyTitle . '></div>'
                . '<div class="bar last-year" style="height:' . $pyH . 'px;"' . $pyTitle . '></div>'
                . '</div>'
                . '</td>';
        }
        $barRow .= '<td class="chart-bar-cell"></td></tr>';

        // R7実績行
        $annualCy = 0;
        $currentRow = '<tr><td class="row-header">' . Layout::escape($ryLabel) . ' 実績</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $cy = $perfCurrent[$m]['premium'] ?? 0;
            $annualCy += $cy;
            $cls = $isCurrent ? ' class="current"' : '';
            $val = $cy > 0 ? number_format((int) floor($cy / 1000)) : '—';
            $currentRow .= '<td' . $cls . '>' . Layout::escape($val) . '</td>';
        }
        $annualCyStr = $annualCy > 0 ? number_format((int) floor($annualCy / 1000)) : '—';
        $currentRow .= '<td>' . Layout::escape($annualCyStr) . '</td></tr>';

        // 前年行
        $annualPy = 0;
        $prevRow = '<tr><td class="row-header">前年</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $py = $perfPrev[$m]['premium'] ?? 0;
            $annualPy += $py;
            $cls = $isCurrent ? ' class="current"' : '';
            $val = $py > 0 ? number_format((int) floor($py / 1000)) : '—';
            $prevRow .= '<td' . $cls . '>' . Layout::escape($val) . '</td>';
        }
        $annualPyStr = $annualPy > 0 ? number_format((int) floor($annualPy / 1000)) : '—';
        $prevRow .= '<td>' . Layout::escape($annualPyStr) . '</td></tr>';

        // 前年比行（月別差額）
        $annualDiff = $annualCy - $annualPy;
        $yoyRow = '<tr><td class="row-header">前年比</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $cy = $perfCurrent[$m]['premium'] ?? 0;
            $py = $perfPrev[$m]['premium']    ?? 0;
            if ($py === 0 && $cy === 0) {
                $val = '—'; $cls = $isCurrent ? ' class="current"' : '';
            } else {
                $diff = $cy - $py;
                $diffStr = ($diff >= 0 ? '+' : '') . number_format((int) floor($diff / 1000));
                $diffCls = $diff >= 0 ? 'up' : 'down';
                $baseCls = $isCurrent ? 'current ' . $diffCls : $diffCls;
                $cls = ' class="' . $baseCls . '"';
                $val = $diffStr;
            }
            $yoyRow .= '<td' . $cls . '>' . Layout::escape($val) . '</td>';
        }
        // 年間前年比
        if ($annualPy === 0 && $annualCy === 0) {
            $annualDiffStr = '—'; $annualDiffCls = '';
        } else {
            $annualDiffStr = ($annualDiff >= 0 ? '+' : '') . number_format((int) floor($annualDiff / 1000));
            $annualDiffCls = ' class="' . ($annualDiff >= 0 ? 'up' : 'down') . '"';
        }
        $yoyRow .= '<td' . $annualDiffCls . '>' . Layout::escape($annualDiffStr) . '</td></tr>';

        // 目標行
        $annualTarget = $targetAnnual !== null ? number_format((int) floor($targetAnnual / 1000)) : '—';
        $targetRow = '<tr><td class="row-header">目標</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $cls = $isCurrent ? ' class="current"' : '';
            $t   = $targetMonthly[$m] ?? null;
            $val = $t !== null ? number_format((int) floor($t / 1000)) : '—';
            $targetRow .= '<td' . $cls . '>' . Layout::escape($val) . '</td>';
        }
        $targetRow .= '<td>' . Layout::escape($annualTarget) . '</td></tr>';

        $legend = '<div style="display:flex;gap:16px;margin-top:8px;font-size:11px;color:var(--text-secondary);">'
            . '<span><span class="bar this-year" style="display:inline-block;width:10px;height:10px;border-radius:2px;vertical-align:middle;"></span> ' . Layout::escape($ryLabel) . ' 実績</span>'
            . '<span><span class="bar last-year" style="display:inline-block;width:10px;height:10px;border-radius:2px;vertical-align:middle;"></span> 前年</span>'
            . '<span style="margin-left:auto;">単位: 千円 ｜ <a href="' . Layout::escape($salesListUrl) . '" style="color:var(--text-info);text-decoration:none;">実績管理一覧 →</a></span>'
            . '</div>';

        $tableContent = $perfError
            ? '<div style="color:var(--text-secondary);font-size:12px;padding:8px 0;">取得できませんでした</div>'
            : '<div class="chart-container">'
              . '<table class="chart-table">'
              . '<thead><tr>' . $thCells . '</tr></thead>'
              . '<tbody>' . $barRow . $currentRow . $prevRow . $yoyRow . $targetRow . '</tbody>'
              . '</table>'
              . '</div>'
              . $legend;

        return '<div class="perf-chart">'
            . '<div class="perf-chart-title">月次推移（前年対比）</div>'
            . $tableContent
            . '</div>';
    }

    /**
     * @param array<string, mixed> $activityData
     */
    private static function renderActivityCard(array $activityData, string $activityListUrl): string
    {
        $isError     = isset($activityData['error']);
        $todayCount  = $isError ? null : (int) ($activityData['today_count']  ?? 0);
        $isSubmitted = $isError ? null : (bool) ($activityData['is_submitted'] ?? false);

        $countStr = $todayCount !== null ? (string) $todayCount . ' 件' : '取得できませんでした';

        if ($isSubmitted === null) {
            $submittedBadge = '<span style="color:var(--text-secondary);font-size:12px;">取得できませんでした</span>';
        } elseif ($isSubmitted) {
            $submittedBadge = '<span class="badge badge-success">提出済み</span>';
        } else {
            $submittedBadge = '<span class="badge badge-warn">未提出</span>';
        }

        return '<div class="activity-card">'
            . '<div class="activity-header">'
            . '<span class="activity-title">営業活動</span>'
            . '<a href="' . Layout::escape($activityListUrl) . '" class="activity-link">活動一覧 →</a>'
            . '</div>'
            . '<div class="activity-row">'
            . '<span class="activity-label">今日の活動</span>'
            . '<span class="activity-value">' . Layout::escape($countStr) . '</span>'
            . '</div>'
            . '<div class="activity-row">'
            . '<span class="activity-label">日報</span>'
            . '<span>' . $submittedBadge . '</span>'
            . '</div>'
            . '</div>';
    }
}
