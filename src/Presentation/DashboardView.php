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
        string $activityDailyUrl,
        string $dashboardUrl,
        string $appPublicUrl,
        array $layoutOptions
    ): string {
        /** @var array<string, mixed> $data */
        $data = is_array($layoutOptions['dashboardData'] ?? null) ? $layoutOptions['dashboardData'] : [];

        $fiscalYear         = (int) ($data['fiscal_year']   ?? date('Y'));
        $availableYears     = is_array($data['available_years'] ?? null) ? (array) $data['available_years'] : [$fiscalYear];
        $currentMonth       = (int) ($data['current_month'] ?? (int) date('n'));
        $currentCalYear     = (int) date('Y');
        $currentFiscalYear  = ((int) date('n') >= 4) ? $currentCalYear : $currentCalYear - 1;
        $role            = (string) ($data['role']       ?? 'member');
        $todayLabel      = (string) ($data['today']      ?? '');

        // 担当者選択ドロップダウン
        $renewalUserParam   = (string) ($data['renewal_user']    ?? 'self');
        $accidentUserParam  = (string) ($data['accident_user']   ?? 'self');
        $salesCaseUserParam = (string) ($data['sales_case_user'] ?? 'self');
        $salesUserParam     = (string) ($data['sales_user']      ?? 'self');
        $perfTab            = (string) ($data['perf_tab']         ?? 'all');
        /** @var array<int, array{id: int, display_name: string}> $tenantUsers */
        $tenantUsers        = is_array($data['tenant_users'] ?? null) ? (array) $data['tenant_users'] : [];
        $loginUserId        = (int) ($auth['user_id']       ?? 0);
        $loginDisplayName   = (string) ($auth['display_name'] ?? $auth['name'] ?? '');

        // JS 用 API ベース URL（末尾スラッシュなし）
        $publicBase = rtrim($appPublicUrl, '/');

        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        // ─── 成績データ準備 ──────────────────────────────────────────────
        $perfCurrentRaw = $data['perf_current'] ?? [];
        $perfPrevRaw    = $data['perf_prev']    ?? [];
        $targetsRaw     = $data['targets']      ?? [];
        $perfError      = isset($perfCurrentRaw['error']);

        // 損保/生保 別サマリ + 業務区分別年度目標
        $perfCurrentBySrcRaw = $data['perf_current_by_source'] ?? [];
        $perfPrevBySrcRaw    = $data['perf_prev_by_source']    ?? [];
        $targetTotalsRaw     = $data['target_totals']          ?? [];

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

        // 年度累計計算
        // 現在年度: 4月〜今月。過去年度: 4月〜3月（全12ヶ月）
        $annualPremium     = 0;
        $annualCount       = 0;
        $prevAnnualPremium = 0;
        $prevAnnualCount   = 0;
        $cutoffMonth       = ($fiscalYear < $currentFiscalYear) ? 3 : $currentMonth;

        if (!$perfError) {
            foreach (self::FISCAL_MONTHS as $m) {
                $annualPremium     += $perfCurrent[$m]['premium'] ?? 0;
                $annualCount       += $perfCurrent[$m]['count']   ?? 0;
                $prevAnnualPremium += $perfPrev[$m]['premium']    ?? 0;
                $prevAnnualCount   += $perfPrev[$m]['count']      ?? 0;
                if ($m === $cutoffMonth) {
                    break;
                }
            }
        }

        // 損保/生保 別 YTD 累計（cutoffMonth まで）
        $bySrcErr = isset($perfCurrentBySrcRaw['error']) || isset($perfPrevBySrcRaw['error']);
        $srcTotals = [
            'non_life' => ['premium' => 0, 'count' => 0, 'prev_premium' => 0, 'prev_count' => 0],
            'life'     => ['premium' => 0, 'count' => 0, 'prev_premium' => 0, 'prev_count' => 0],
        ];
        if (!$bySrcErr) {
            foreach (['non_life', 'life'] as $src) {
                $curArr  = is_array($perfCurrentBySrcRaw[$src] ?? null) ? $perfCurrentBySrcRaw[$src] : [];
                $prevArr = is_array($perfPrevBySrcRaw[$src]    ?? null) ? $perfPrevBySrcRaw[$src]    : [];
                foreach (self::FISCAL_MONTHS as $m) {
                    $srcTotals[$src]['premium']      += (int) ($curArr[$m]['premium']  ?? 0);
                    $srcTotals[$src]['count']        += (int) ($curArr[$m]['count']    ?? 0);
                    $srcTotals[$src]['prev_premium'] += (int) ($prevArr[$m]['premium'] ?? 0);
                    $srcTotals[$src]['prev_count']   += (int) ($prevArr[$m]['count']   ?? 0);
                    if ($m === $cutoffMonth) {
                        break;
                    }
                }
            }
        }

        // 業務区分別年度目標（0 は「目標未設定」扱い）
        $targetNonLife = is_array($targetTotalsRaw) ? (int) ($targetTotalsRaw['non_life'] ?? 0) : 0;
        $targetLife    = is_array($targetTotalsRaw) ? (int) ($targetTotalsRaw['life']     ?? 0) : 0;
        $targetTotal   = $targetNonLife + $targetLife;

        // 損保/生保 の月次データを FISCAL_MONTHS で正規化（全体テーブルと同一形式）
        /** @var array<int, array{premium:int,count:int}> $perfCurrentNonLife */
        $perfCurrentNonLife = [];
        /** @var array<int, array{premium:int,count:int}> $perfPrevNonLife */
        $perfPrevNonLife = [];
        /** @var array<int, array{premium:int,count:int}> $perfCurrentLife */
        $perfCurrentLife = [];
        /** @var array<int, array{premium:int,count:int}> $perfPrevLife */
        $perfPrevLife = [];
        if (!$bySrcErr) {
            $curNl  = is_array($perfCurrentBySrcRaw['non_life'] ?? null) ? $perfCurrentBySrcRaw['non_life'] : [];
            $prevNl = is_array($perfPrevBySrcRaw['non_life']    ?? null) ? $perfPrevBySrcRaw['non_life']    : [];
            $curLf  = is_array($perfCurrentBySrcRaw['life']     ?? null) ? $perfCurrentBySrcRaw['life']     : [];
            $prevLf = is_array($perfPrevBySrcRaw['life']        ?? null) ? $perfPrevBySrcRaw['life']        : [];
            foreach (self::FISCAL_MONTHS as $m) {
                $perfCurrentNonLife[$m] = [
                    'premium' => (int) ($curNl[$m]['premium']  ?? 0),
                    'count'   => (int) ($curNl[$m]['count']    ?? 0),
                ];
                $perfPrevNonLife[$m] = [
                    'premium' => (int) ($prevNl[$m]['premium'] ?? 0),
                    'count'   => (int) ($prevNl[$m]['count']   ?? 0),
                ];
                $perfCurrentLife[$m] = [
                    'premium' => (int) ($curLf[$m]['premium']  ?? 0),
                    'count'   => (int) ($curLf[$m]['count']    ?? 0),
                ];
                $perfPrevLife[$m] = [
                    'premium' => (int) ($prevLf[$m]['premium'] ?? 0),
                    'count'   => (int) ($prevLf[$m]['count']   ?? 0),
                ];
            }
        }

        // 3 タイル用データ構造
        $perfTiles = [
            [
                'key'      => 'all',
                'label'    => '全体',
                'badge'    => '',
                'premium'  => $annualPremium,
                'count'    => $annualCount,
                'prev'     => $prevAnnualPremium,
                'target'   => $targetTotal > 0 ? $targetTotal : null,
            ],
            [
                'key'      => 'non_life',
                'label'    => '損保',
                'badge'    => 'badge-info',
                'premium'  => $srcTotals['non_life']['premium'],
                'count'    => $srcTotals['non_life']['count'],
                'prev'     => $srcTotals['non_life']['prev_premium'],
                'target'   => $targetNonLife > 0 ? $targetNonLife : null,
            ],
            [
                'key'      => 'life',
                'label'    => '生保',
                'badge'    => 'badge-warn',
                'premium'  => $srcTotals['life']['premium'],
                'count'    => $srcTotals['life']['count'],
                'prev'     => $srcTotals['life']['prev_premium'],
                'target'   => $targetLife > 0 ? $targetLife : null,
            ],
        ];

        // ─── CSS ────────────────────────────────────────────────────────
        $css = '<style>'
            . '.alert-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}'
            . '.alert-card{border-radius:var(--radius-md);padding:14px 18px;cursor:pointer;transition:box-shadow 0.15s;text-decoration:none;display:block;}'
            . '.alert-card:hover{box-shadow:0 2px 8px rgba(0,0,0,0.08);}'
            . '.alert-card-danger{background:var(--bg-danger);border:0.5px solid var(--border-danger);}'
            . '.alert-card-warning{background:var(--bg-warning);border:0.5px solid var(--border-warning);}'
            . '.alert-card-label{font-size:12px;color:var(--text-secondary);margin-bottom:4px;}'
            . '.alert-card-danger .alert-card-label{color:var(--text-danger);}'
            . '.alert-card-warning .alert-card-label{color:var(--text-warning);}'
            . '.alert-card-value{font-size:28px;font-weight:500;line-height:1.2;}'
            . '.alert-card-danger .alert-card-value{color:var(--text-danger);}'
            . '.alert-card-warning .alert-card-value{color:var(--text-warning);}'
            . '.alert-card-sub{font-size:12px;margin-top:4px;}'
            . '.alert-card-danger .alert-card-sub{color:var(--text-danger);opacity:0.8;}'
            . '.alert-card-warning .alert-card-sub{color:var(--text-warning);opacity:0.8;}'
            . '.user-select{font-size:12px;padding:3px 6px;border:0.5px solid var(--border-medium);border-radius:var(--radius-md);background:var(--bg-primary);color:var(--text-primary);cursor:pointer;max-width:140px;}'
            . '.biz-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}'
            . '.biz-card{background:var(--bg-primary);border:0.5px solid var(--border-light);border-radius:var(--radius-lg);padding:16px 18px;transition:box-shadow 0.15s,border-color 0.15s;text-decoration:none;display:block;color:inherit;}'
            . '.biz-card-link{cursor:pointer;}.biz-card-link:hover{box-shadow:0 2px 8px rgba(0,0,0,0.07);border-color:var(--border-medium);}'
            . '.biz-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}'
            . '.biz-card-title{font-size:13px;font-weight:500;color:var(--text-primary);}'
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
            . '.year-summary-label{font-size:12px;font-weight:500;color:var(--text-secondary);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.4px;}'
            . '.year-summary-main{display:flex;align-items:baseline;gap:6px;margin-bottom:16px;}'
            . '.year-summary-amount{font-size:52px;font-weight:500;color:var(--text-primary);line-height:1;}'
            . '.year-summary-unit{font-size:16px;color:var(--text-secondary);}'
            . '.year-summary-compare{display:flex;align-items:stretch;border-top:0.5px solid var(--border-light);padding-top:12px;gap:0;}'
            . '.year-summary-compare-item{flex:1;display:flex;flex-direction:column;gap:4px;padding:0 14px;}'
            . '.year-summary-compare-item:first-child{padding-left:0;}'
            . '.year-summary-compare-item:last-child{padding-right:0;}'
            . '.year-summary-compare-divider{width:0.5px;background:var(--border-light);flex-shrink:0;}'
            . '.year-summary-compare-label{font-size:12px;color:var(--text-secondary);}'
            . '.year-summary-compare-value{font-size:13px;font-weight:500;color:var(--text-primary);}'
            . '.year-summary-compare-value.up{color:var(--text-success);}'
            . '.year-summary-compare-value.down{color:var(--text-danger);}'
            . '.perf-tile-grid{display:flex;gap:12px;flex-wrap:wrap;}'
            . '.perf-tile{flex:1 1 200px;min-width:200px;padding:12px 14px;border:0.5px solid var(--border-light);border-radius:var(--radius-md);background:var(--bg-primary);display:flex;flex-direction:column;gap:6px;}'
            . '.perf-tile-head{display:flex;align-items:center;min-height:22px;}'
            . '.perf-tile-title{font-size:12px;font-weight:600;color:var(--text-primary);}'
            . '.perf-tile-amount{display:flex;align-items:baseline;gap:4px;}'
            . '.perf-tile-num{font-size:28px;font-weight:500;color:var(--text-primary);line-height:1;}'
            . '.perf-tile-unit{font-size:12px;color:var(--text-secondary);}'
            . '.perf-tile-count{font-size:12px;color:var(--text-secondary);}'
            . '.perf-tile-metrics{display:flex;gap:12px;border-top:0.5px solid var(--border-light);padding-top:6px;margin-top:2px;}'
            . '.perf-tile-metric{flex:1;display:flex;flex-direction:column;gap:2px;}'
            . '.perf-tile-metric-label{font-size:12px;color:var(--text-secondary);}'
            . '.perf-tile-metric-value{font-size:13px;font-weight:500;color:var(--text-primary);}'
            . '.perf-tile-metric-value.up{color:var(--text-success);}'
            . '.perf-tile-metric-value.down{color:var(--text-danger);}'
            . '.perf-tile-metric-value.achievement-over{color:var(--text-success);}'
            . '@media (max-width: 1024px){.perf-tile{flex:1 1 calc(50% - 6px);min-width:0;}}'
            . '@media (max-width: 480px){.perf-tile{flex:1 1 100%;}}'
            . '.perf-chart{margin-top:16px;padding-top:14px;border-top:0.5px solid var(--border-light);}'
            . '.perf-chart-title{font-size:12px;color:var(--text-secondary);margin-bottom:10px;font-weight:500;text-transform:uppercase;letter-spacing:0.4px;}'
            . '.perf-chart-pane{display:none;}'
            . '.perf-chart-pane.active{display:block;}'
            . '.chart-toggle{display:inline-flex;gap:4px;text-transform:none;letter-spacing:0;}'
            . '.chart-toggle-btn{font-size:12px;padding:2px 10px;border-radius:var(--radius-md);border:0.5px solid var(--border-medium);background:var(--bg-primary);color:var(--text-secondary);cursor:pointer;font-weight:500;line-height:1.6;}'
            . '.chart-toggle-btn:hover{border-color:var(--border-dark,#627d98);color:var(--text-primary);}'
            . '.chart-toggle-btn.active{background:var(--bg-info,#ebf8ff);color:var(--text-info);border-color:var(--border-info,#90cdf4);font-weight:600;}'
            . '.chart-toggle-btn[data-target="life"].active{background:var(--bg-warning,#fffaf0);color:var(--text-warning);border-color:var(--border-warning,#f6ad55);}'
            . '.chart-toggle-btn[data-target="all"].active{background:var(--bg-secondary,#f7fafc);color:var(--text-primary);border-color:var(--border-medium);}'
            . '.chart-container{width:100%;overflow-x:auto;}'
            . '.chart-table{width:100%;border-collapse:collapse;}'
            . '.chart-table th{font-size:12px;color:var(--text-secondary);font-weight:500;padding:4px 6px;text-align:center;white-space:nowrap;border-bottom:0.5px solid var(--border-light);}'
            . '.chart-table th.current{color:var(--text-info);font-weight:600;}'
            . '.chart-table td{font-size:12px;padding:5px 6px;text-align:right;white-space:nowrap;border-bottom:0.5px solid var(--border-light);}'
            . '.chart-table td.row-header{text-align:left;color:var(--text-secondary);font-size:12px;}'
            . '.chart-table td.current-month{background:rgba(55,138,221,0.04);font-weight:500;}'
            . '.chart-table td.current{background:rgba(55,138,221,0.04);font-weight:500;}'
            . '.chart-table td.up{color:var(--text-success);}'
            . '.chart-table td.down{color:var(--text-danger);}'
            . '.chart-table td.achievement-rate{font-weight:600;}'
            . '.achievement-over{color:var(--text-success);}'
            . '.activity-card{background:var(--bg-primary);border:0.5px solid var(--border-light);border-radius:var(--radius-lg);padding:16px 18px;}'
            . '.activity-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}'
            . '.activity-title{font-size:13px;font-weight:500;color:var(--text-primary);}'
            . '.activity-link{font-size:12px;color:var(--text-info);cursor:pointer;text-decoration:none;}'
            . '.activity-link:hover{text-decoration:underline;}'
            . '.activity-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;}'
            . '.activity-row:not(:last-child){border-bottom:0.5px solid var(--border-light);}'
            . '.activity-label{font-size:13px;color:var(--text-secondary);}'
            . '.activity-value{font-size:13px;font-weight:500;color:var(--text-primary);}'

            . '.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}'
            . '.section-label{font-size:12px;font-weight:500;color:var(--text-secondary);margin-bottom:12px;padding-bottom:5px;text-transform:uppercase;letter-spacing:0.4px;}'
            . '@media(max-width:768px){'
            . '.alert-grid{grid-template-columns:1fr;}'
            . '.biz-grid{grid-template-columns:1fr;}'
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
            . '<div><span style="font-size:12px;margin-right:4px;">対応遅れ</span><span style="font-size:22px;font-weight:700;line-height:1;">' . ($renewalOverdue !== null ? $renewalOverdue : '—') . '</span> <span style="font-size:13px;font-weight:400;">件</span></div>'
            . '<div><span style="font-size:12px;margin-right:4px;">7日以内</span><span style="font-size:22px;font-weight:700;line-height:1;">' . ($renewalWithin7 !== null ? $renewalWithin7 : '—') . '</span> <span style="font-size:13px;font-weight:400;">件</span></div>'
            . '</div>'
            . '<div class="alert-card-sub">満期一覧へ</div>'
            . '</a>'
            // 事故 — 高優先度未完了
            . '<a href="' . Layout::escape($accidentListUrl) . '" class="alert-card alert-card-warning">'
            . '<div class="alert-card-label">事故 — 高優先度未完了</div>'
            . '<div class="alert-card-value">' . $accidentHighVal . '</div>'
            . '<div class="alert-card-sub">事故案件一覧へ</div>'
            . '</a>'
            . '</div>';

        // ─── 中段: 業務入口 ─────────────────────────────────────────────
        $activityData   = is_array($data['activity'] ?? null) ? (array) $data['activity'] : [];

        $renewalBizData   = is_array($data['renewal_biz']    ?? null) ? (array) $data['renewal_biz']    : [];
        $accidentBizData  = is_array($data['accident_biz']   ?? null) ? (array) $data['accident_biz']   : [];
        $salesCaseBizData = is_array($data['sales_case_biz'] ?? null) ? (array) $data['sales_case_biz'] : [];

        // 担当者選択ドロップダウン（ID は JS の initDropdown() と一致させる）
        $renewalDropdown   = self::buildUserDropdown('renewal-user',    $renewalUserParam,   $loginUserId, $loginDisplayName, $tenantUsers);
        $accidentDropdown  = self::buildUserDropdown('accident-user',   $accidentUserParam,  $loginUserId, $loginDisplayName, $tenantUsers);
        $salesCaseDropdown = self::buildUserDropdown('sales-case-user', $salesCaseUserParam, $loginUserId, $loginDisplayName, $tenantUsers);

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
            // 満期業務
            . '<div class="biz-card" id="card-renewal">'
            . '<div class="biz-card-header">'
            . '<span class="biz-card-title">満期業務 <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">(未完了)</span></span>'
            . $renewalDropdown
            . '</div>'
            . '<div class="biz-card-metrics">'
            . '<div class="biz-metric"><span class="biz-metric-label">14日以内</span><span class="biz-metric-value accent within-14">' . Layout::escape($renewalWithin14Str) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">28日以内</span><span class="biz-metric-value within-28">' . Layout::escape($renewalWithin28Str) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">60日以内</span><span class="biz-metric-value within-60">' . Layout::escape($renewalWithin60Str) . '</span></div>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($renewalListUrl) . '" style="font-size:12px;color:var(--text-info);text-decoration:none;">満期一覧へ</a></div>'
            . '</div>'
            // 事故案件
            . '<div class="biz-card" id="card-accident">'
            . '<div class="biz-card-header">'
            . '<span class="biz-card-title">事故案件 <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">(未完了)</span></span>'
            . $accidentDropdown
            . '</div>'
            . '<div class="biz-card-metrics">'
            . '<div class="biz-metric"><span class="biz-metric-label">高優先度</span><span class="biz-metric-value high' . $accidentHighCls . '">' . Layout::escape($accidentHighStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">中優先度</span><span class="biz-metric-value normal">' . Layout::escape($accidentMidStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">低優先度</span><span class="biz-metric-value low">' . Layout::escape($accidentLowStr) . '</span></div>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($accidentListUrl) . '" style="font-size:12px;color:var(--text-info);text-decoration:none;">事故案件一覧へ</a></div>'
            . '</div>'
            // 見込管理
            . '<div class="biz-card" id="card-sales-case">'
            . '<div class="biz-card-header">'
            . '<span class="biz-card-title">見込管理 <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">(未完了)</span></span>'
            . $salesCaseDropdown
            . '</div>'
            . '<div class="biz-card-metrics">'
            . '<div class="biz-metric"><span class="biz-metric-label">見込A</span><span class="biz-metric-value accent prospect-a">' . Layout::escape($scRankAStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">見込B</span><span class="biz-metric-value prospect-b">' . Layout::escape($scRankBStr) . '</span></div>'
            . '<div class="biz-metric"><span class="biz-metric-label">今月成約予定</span><span class="biz-metric-value expected">' . Layout::escape($scClosingStr) . '</span></div>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($salesCaseListUrl) . '" style="font-size:12px;color:var(--text-info);text-decoration:none;">見込案件一覧へ</a></div>'
            . '</div>'
            . '</div>';

        // ─── 成績サマリカード ───────────────────────────────────────────
        // ─── 成績サマリ セクションヘッダー（年度プルダウン + 担当者ドロップダウン）─────

        // 年度プルダウン（JS で部分更新。値は年度数値のみ）
        $yearOptions = '';
        foreach ($availableYears as $yr) {
            $yr = (int) $yr;
            $selected = ($yr === $fiscalYear) ? ' selected' : '';
            $yearOptions .= '<option value="' . $yr . '"' . $selected . '>' . $yr . '年度</option>';
        }
        $yearToggle = '<select id="fiscal-year" class="user-select">'
            . $yearOptions
            . '</select>';

        // 成績サマリ 担当者ドロップダウン
        $salesUserDropdown = self::buildUserDropdown('sales-user', $salesUserParam, $loginUserId, $loginDisplayName, $tenantUsers);

        $perfSectionHeader = '<div id="perf-summary" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
            . '<div class="section-label" style="margin-bottom:0;">成績サマリ</div>'
            . '<div style="display:flex;align-items:center;gap:8px;">' . $yearToggle . $salesUserDropdown . '</div>'
            . '</div>';

        $perfCard = $perfSectionHeader . self::renderPerfCard(
            $fiscalYear,
            $currentMonth,
            $currentFiscalYear,
            $perfError,
            $perfCurrent,
            $perfPrev,
            $perfTiles,
            $targetAnnual,
            $targetMonthly,
            $salesListUrl,
            $bySrcErr,
            $perfCurrentNonLife,
            $perfPrevNonLife,
            $perfCurrentLife,
            $perfPrevLife,
            $targetNonLife,
            $targetLife,
            $perfTab
        );

        $bottomGrid = '';


        // JS 設定（API ベース URL と初期状態を埋め込む）
        // apiBase はパス相対 URL（ホスト名なし）にする。
        // APP_PUBLIC_URL と APP_URL のホストが異なる環境で fetch() が CORS でブロックされないようにするため。
        $apiBasePath = rtrim((string) (parse_url($publicBase, PHP_URL_PATH) ?? ''), '/') . '/?route=api/dashboard';
        $jsConfig  = '<script>'
            . 'window.DASHBOARD_CONFIG={'
            . 'apiBase:'            . json_encode($apiBasePath, JSON_UNESCAPED_UNICODE) . ','
            . 'fiscalYear:'         . $fiscalYear . ','
            . 'currentFiscalYear:'  . $currentFiscalYear . ','
            . 'currentMonth:'       . $currentMonth . ','
            . 'loginUserId:'        . $loginUserId
            . '};'
            . '</script>';
        // dashboard.js にキャッシュバスター（ファイル更新時刻）を付ける。
        // 旧 JS がブラウザキャッシュに残っているとドロップダウン挙動（再読込 vs AJAX）が
        // 食い違ってタイル更新漏れなどが発生するため。
        $jsLocalPath = dirname(__DIR__, 2) . '/public/assets/js/dashboard.js';
        $jsMtime     = @filemtime($jsLocalPath);
        $jsVer       = $jsMtime !== false ? (string) $jsMtime : '1';
        $jsScript    = '<script src="' . Layout::escape($publicBase . '/assets/js/dashboard.js?v=' . $jsVer) . '" defer></script>';

        $content = $css
            . $errorHtml
            . $pageHeader
            . $alertGrid
            . $bizGrid
            . $perfCard
            . $bottomGrid
            . $jsConfig
            . $jsScript;

        return Layout::render('ホーム', $content, $layoutOptions);
    }

    /**
     * @param array<int, array{premium: int, count: int}> $perfCurrent
     * @param array<int, array{premium: int, count: int}> $perfPrev
     * @param array<int, array{key:string,label:string,badge:string,premium:int,count:int,prev:int,target:?int}> $perfTiles
     * @param array<int, int> $targetMonthly
     * @param array<int, array{premium: int, count: int}> $perfCurrentNonLife
     * @param array<int, array{premium: int, count: int}> $perfPrevNonLife
     * @param array<int, array{premium: int, count: int}> $perfCurrentLife
     * @param array<int, array{premium: int, count: int}> $perfPrevLife
     */
    private static function renderPerfCard(
        int $fiscalYear,
        int $currentMonth,
        int $currentFiscalYear,
        bool $perfError,
        array $perfCurrent,
        array $perfPrev,
        array $perfTiles,
        ?int $targetAnnual,
        array $targetMonthly,
        string $salesListUrl,
        bool $bySrcErr = true,
        array $perfCurrentNonLife = [],
        array $perfPrevNonLife = [],
        array $perfCurrentLife = [],
        array $perfPrevLife = [],
        int $targetNonLife = 0,
        int $targetLife = 0,
        string $activeTab = 'all'
    ): string {
        $monthNames = [1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
                       7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月'];

        if ($perfError) {
            $perfBody = '<div style="color:var(--text-secondary);font-size:12px;padding:8px 0;">取得できませんでした</div>';
        } else {
            $currentMonthLabel = $monthNames[$currentMonth] ?? '';
            $periodLabel = ($fiscalYear < $currentFiscalYear)
                ? $fiscalYear . '年度（4月〜3月）'
                : $fiscalYear . '年度 4月〜' . $currentMonthLabel;

            $tilesHtml = '';
            foreach ($perfTiles as $tile) {
                $premium = (int) $tile['premium'];
                $count   = (int) $tile['count'];
                $prev    = (int) $tile['prev'];
                $target  = $tile['target'] !== null ? (int) $tile['target'] : null;

                // 前年比
                if ($prev === 0) {
                    $yoyCls = '';
                    $yoyStr = '—';
                } else {
                    $yoyPct = floor($premium / $prev * 100);
                    $yoyCls = $yoyPct >= 100 ? ' up' : ' down';
                    $yoyStr = $yoyPct . '%';
                }

                // 達成率
                if ($target !== null && $target > 0) {
                    $achRate = round($premium / $target * 100, 1);
                    $achStr  = number_format($achRate, 1) . '%';
                    $achCls  = $achRate >= 100 ? ' achievement-over' : '';
                } else {
                    $achStr = '目標未設定';
                    $achCls = '';
                }

                // 年度目標（円 → 千円、切り捨て。未設定は「未設定」。達成率側と文言を被らせないため短縮表記）
                if ($target !== null && $target > 0) {
                    $targetStr = number_format((int) floor($target / 1000)) . ' 千円';
                } else {
                    $targetStr = '未設定';
                }

                $badgeHtml = $tile['badge'] !== ''
                    ? '<span class="badge ' . Layout::escape($tile['badge']) . '" style="margin-left:6px;font-size:11px;padding:1px 6px;">' . Layout::escape($tile['label']) . '</span>'
                    : '';
                $labelHtml = $tile['badge'] !== ''
                    ? $badgeHtml
                    : '<span class="perf-tile-title">' . Layout::escape($tile['label']) . '</span>';

                $tilesHtml .= '<div class="perf-tile perf-tile-' . Layout::escape($tile['key']) . '">'
                    . '<div class="perf-tile-head">' . $labelHtml . '</div>'
                    . '<div class="perf-tile-amount"><span class="perf-tile-num">' . Layout::escape(number_format((int) floor($premium / 1000))) . '</span><span class="perf-tile-unit">千円</span></div>'
                    . '<div class="perf-tile-count">' . Layout::escape(number_format($count)) . ' 件</div>'
                    . '<div class="perf-tile-metrics">'
                    . '<div class="perf-tile-metric"><span class="perf-tile-metric-label">前年比</span><span class="perf-tile-metric-value' . $yoyCls . '">' . Layout::escape($yoyStr) . '</span></div>'
                    . '<div class="perf-tile-metric"><span class="perf-tile-metric-label">年度目標</span><span class="perf-tile-metric-value">' . Layout::escape($targetStr) . '</span></div>'
                    . '<div class="perf-tile-metric"><span class="perf-tile-metric-label">達成率</span><span class="perf-tile-metric-value' . $achCls . '">' . Layout::escape($achStr) . '</span></div>'
                    . '</div>'
                    . '</div>';
            }

            $perfBody = '<div class="year-summary-label">'
                . Layout::escape($periodLabel)
                . '</div>'
                . '<div class="perf-tile-grid">'
                . $tilesHtml
                . '</div>';
        }

        // 月次推移エリア（全体 / 損保 / 生保 を 1 テーブルで切り替え表示）
        // タブ切替 UI: タイトル右側に 3 つの chip（全体 / 損保 / 生保）を置き、
        // クリックで対応する pane のみ active にする。初期は「全体」active。
        // 全体の <table> は #monthly-trend を保持し dashboard.js の部分更新と互換。

        // 全体チャートの年度目標は「損保+生保合算」を参照する（docs/policies/12_sales-target-spec.md §4-1）。
        // 廃止された target_type='premium_total' 由来の $targetAnnual は参照しない。
        $targetTotal = $targetNonLife + $targetLife;
        $paneAll = self::renderChartSection(
            $fiscalYear, $currentMonth, $currentFiscalYear,
            $perfError, $perfCurrent, $perfPrev, $targetMonthly,
            $targetTotal > 0 ? $targetTotal : null,
            $salesListUrl, '全体', '', false, 'monthly-trend',
            'all', $activeTab === 'all'
        );
        $paneNonLife = self::renderChartSection(
            $fiscalYear, $currentMonth, $currentFiscalYear,
            $bySrcErr, $perfCurrentNonLife, $perfPrevNonLife, [],
            $targetNonLife > 0 ? $targetNonLife : null,
            $salesListUrl, '損保', 'badge-info', false, null,
            'non_life', $activeTab === 'non_life'
        );
        $paneLife = self::renderChartSection(
            $fiscalYear, $currentMonth, $currentFiscalYear,
            $bySrcErr, $perfCurrentLife, $perfPrevLife, [],
            $targetLife > 0 ? $targetLife : null,
            $salesListUrl, '生保', 'badge-warn', false, null,
            'life', $activeTab === 'life'
        );

        $tabAll     = $activeTab === 'all'      ? ' active" aria-selected="true"'  : '" aria-selected="false"';
        $tabNonLife = $activeTab === 'non_life' ? ' active" aria-selected="true"'  : '" aria-selected="false"';
        $tabLife    = $activeTab === 'life'     ? ' active" aria-selected="true"'  : '" aria-selected="false"';
        $toggleChips = '<span class="chart-toggle" role="tablist">'
            . '<button type="button" class="chart-toggle-btn' . $tabAll     . ' data-target="all">全体</button>'
            . '<button type="button" class="chart-toggle-btn' . $tabNonLife . ' data-target="non_life">損保</button>'
            . '<button type="button" class="chart-toggle-btn' . $tabLife    . ' data-target="life">生保</button>'
            . '</span>';

        $sharedLegend = '<div style="display:flex;justify-content:flex-end;margin-top:6px;font-size:12px;color:var(--text-secondary);">'
            . '<span>単位: 千円 ｜ <a href="' . Layout::escape($salesListUrl) . '" style="color:var(--text-info);text-decoration:none;">成績管理一覧 →</a></span>'
            . '</div>';

        // 切替用 JS（初期表示は .active pane のみ。CSS で非 active を display:none にしている）
        $toggleScript = '<script>(function(){'
            . 'var root=document.getElementById("card-sales");if(!root)return;'
            . 'var btns=root.querySelectorAll(".chart-toggle-btn");'
            . 'var panes=root.querySelectorAll(".perf-chart-pane");'
            . 'btns.forEach(function(btn){btn.addEventListener("click",function(){'
            . 'var t=btn.getAttribute("data-target");'
            . 'btns.forEach(function(b){var on=(b===btn);b.classList.toggle("active",on);b.setAttribute("aria-selected",on?"true":"false");});'
            . 'panes.forEach(function(p){p.classList.toggle("active",p.getAttribute("data-src")===t);});'
            . 'try{var u=new URL(window.location.href);u.searchParams.set("perf_tab",t);history.replaceState(null,"",u.toString());}catch(e){}'
            . '});});'
            . '})();</script>';

        $chartBlock = '<div class="perf-chart">'
            . '<div class="perf-chart-title" style="display:flex;align-items:center;gap:8px;">'
            . '<span>月次推移（前年対比）</span>'
            . $toggleChips
            . '</div>'
            . $paneAll
            . $paneNonLife
            . $paneLife
            . $sharedLegend
            . '</div>'
            . $toggleScript;

        return '<div class="perf-card" id="card-sales">'
            . $perfBody
            . $chartBlock
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
        int $currentFiscalYear,
        bool $perfError,
        array $perfCurrent,
        array $perfPrev,
        array $targetMonthly,
        ?int $targetAnnual,
        string $salesListUrl,
        string $label = '全体',
        string $badgeClass = '',
        bool $showFooterLegend = true,
        ?string $tableId = 'monthly-trend',
        ?string $paneKey = null,
        bool $paneActive = false
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
        $thCells .= '<th style="color:var(--text-secondary);font-weight:400;">年間</th>';

        // 未来月判定用（年度内月順: 4,5,...,12,1,2,3）
        $cutoffMonth = ($fiscalYear < $currentFiscalYear) ? 3 : $currentMonth;
        $fiscalOrder = [4=>0,5=>1,6=>2,7=>3,8=>4,9=>5,10=>6,11=>7,12=>8,1=>9,2=>10,3=>11];

        // R7成績行（JS 更新用: class="current" data-month="{m}"）
        // 未来月は DB に成績ゼロ → '—' 表示
        $annualCy = 0;
        $currentRow = '<tr><td class="row-header">' . Layout::escape($ryLabel) . ' 成績</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $cy = $perfCurrent[$m]['premium'] ?? 0;
            $annualCy += $cy;
            $classes = 'current' . ($isCurrent ? ' current-month' : '');
            $val = $cy !== 0 ? number_format((int) floor($cy / 1000)) : '—';
            $currentRow .= '<td class="' . $classes . '" data-month="' . $m . '">' . Layout::escape($val) . '</td>';
        }
        $annualCyStr = $annualCy > 0 ? number_format((int) floor($annualCy / 1000)) : '—';
        $currentRow .= '<td class="annual-current" style="color:var(--text-secondary);font-size:12px;">' . Layout::escape($annualCyStr) . '</td></tr>';

        // 前年行（JS 更新用: class="previous" data-month="{m}"）
        // 未来月は「—」。年間列は YTD 範囲（cutoffMonth まで）の前年合計
        $annualPyYTD = 0;
        $prevRow = '<tr><td class="row-header">前年</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $isFuture  = ($fiscalYear === $currentFiscalYear)
                && (($fiscalOrder[$m] ?? 0) > ($fiscalOrder[$currentMonth] ?? 0));
            $py = $perfPrev[$m]['premium'] ?? 0;
            if (!$isFuture) {
                $annualPyYTD += $py;
            }
            $classes = 'previous' . ($isCurrent ? ' current-month' : '');
            $val = (!$isFuture && $py > 0) ? number_format((int) floor($py / 1000)) : '—';
            $prevRow .= '<td class="' . $classes . '" data-month="' . $m . '">' . Layout::escape($val) . '</td>';
        }
        $annualPyYTDStr = $annualPyYTD > 0 ? number_format((int) floor($annualPyYTD / 1000)) : '—';
        $prevRow .= '<td class="annual-previous" style="color:var(--text-secondary);font-size:12px;">' . Layout::escape($annualPyYTDStr) . '</td></tr>';

        // 前年差行（JS 更新用: class="diff ..." data-month="{m}"）
        // 年間列は YTD 同士の比較（annualCy vs annualPyYTD）
        $annualDiff = $annualCy - $annualPyYTD;
        $yoyRow = '<tr><td class="row-header">前年差</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $isFuture  = ($fiscalYear === $currentFiscalYear)
                && (($fiscalOrder[$m] ?? 0) > ($fiscalOrder[$currentMonth] ?? 0));
            $cy = $perfCurrent[$m]['premium'] ?? 0;
            $py = $perfPrev[$m]['premium']    ?? 0;
            if ($isFuture || ($py === 0 && $cy === 0)) {
                $val = '—';
                $classes = 'diff' . ($isCurrent ? ' current-month' : '');
            } else {
                $diff = $cy - $py;
                $diffStr = ($diff >= 0 ? '+' : '') . number_format((int) floor($diff / 1000));
                $diffCls = $diff >= 0 ? 'up' : 'down';
                $classes = 'diff ' . $diffCls . ($isCurrent ? ' current-month' : '');
                $val = $diffStr;
            }
            $yoyRow .= '<td class="' . $classes . '" data-month="' . $m . '">' . Layout::escape($val) . '</td>';
        }
        // 年間前年差（YTD 同士）
        if ($annualPyYTD === 0 && $annualCy === 0) {
            $annualDiffStr = '—'; $annualDiffCls = 'color:var(--text-secondary);font-size:12px;';
        } else {
            $annualDiffStr = ($annualDiff >= 0 ? '+' : '') . number_format((int) floor($annualDiff / 1000));
            $annualDiffCls = 'color:var(--text-' . ($annualDiff >= 0 ? 'success' : 'danger') . ');font-size:12px;';
        }
        $yoyRow .= '<td class="annual-diff" style="' . $annualDiffCls . '">' . Layout::escape($annualDiffStr) . '</td></tr>';

        // 累積達成率行（JS 更新用: class="achievement-rate" data-month="{m}"）
        // 未来月は「—」。年間列は当月時点の累積達成率
        $achTargetThousand = ($targetAnnual !== null && $targetAnnual > 0)
            ? (int) floor($targetAnnual / 1000) : null;
        $achCumulative      = 0;
        $lastAchRate        = null;

        $achievementRow = '<tr><td class="row-header">累積達成率</td>';
        foreach (self::FISCAL_MONTHS as $m) {
            $isCurrent = ($m === $currentMonth);
            $isFuture  = ($fiscalYear === $currentFiscalYear)
                && (($fiscalOrder[$m] ?? 0) > ($fiscalOrder[$currentMonth] ?? 0));
            $achRate = null;
            if (!$isFuture) {
                $cyVal = (int) floor(($perfCurrent[$m]['premium'] ?? 0) / 1000);
                $achCumulative += $cyVal;
                if ($achTargetThousand !== null && $achTargetThousand > 0) {
                    $achRate = round($achCumulative / $achTargetThousand * 100, 1);
                    $lastAchRate = $achRate;
                }
            }
            $cls = 'achievement-rate' . ($isCurrent ? ' current-month' : '');
            if ($achRate !== null && $achRate >= 100) {
                $cls .= ' achievement-over';
            }
            $val = $achRate !== null ? number_format($achRate, 1) . '%' : '—';
            $achievementRow .= '<td class="' . $cls . '" data-month="' . $m . '">' . Layout::escape($val) . '</td>';
        }
        $annualAchColStr = $lastAchRate !== null ? number_format($lastAchRate, 1) . '%' : '—';
        $annualAchColCls  = ($lastAchRate !== null && $lastAchRate >= 100) ? ' achievement-over' : '';
        $achievementRow .= '<td class="achievement-annual' . $annualAchColCls . '" style="color:var(--text-secondary);font-size:12px;">'
            . Layout::escape($annualAchColStr) . '</td></tr>';

        $legend = $showFooterLegend
            ? '<div style="display:flex;justify-content:flex-end;margin-top:6px;font-size:12px;color:var(--text-secondary);">'
                . '<span>単位: 千円 ｜ <a href="' . Layout::escape($salesListUrl) . '" style="color:var(--text-info);text-decoration:none;">成績管理一覧 →</a></span>'
                . '</div>'
            : '';

        $tableIdAttr = ($tableId !== null && $tableId !== '') ? ' id="' . Layout::escape($tableId) . '"' : '';

        if ($perfError) {
            $tableContent = '<div style="color:var(--text-secondary);font-size:12px;padding:8px 0;">取得できませんでした</div>';
        } else {
            $tableContent = '<div class="chart-container">'
                . '<table' . $tableIdAttr . ' class="chart-table">'
                . '<thead><tr>' . $thCells . '</tr></thead>'
                . '<tbody>' . $currentRow . $prevRow . $yoyRow . $achievementRow . '</tbody>'
                . '</table>'
                . '</div>';
        }

        // pane モード: 外側 .perf-chart とタイトル・凡例は呼び出し側が共有で描画する
        if ($paneKey !== null) {
            $activeCls = $paneActive ? ' active' : '';
            return '<div class="perf-chart-pane' . $activeCls . '" data-src="' . Layout::escape($paneKey) . '">'
                . $tableContent
                . '</div>';
        }

        // スタンドアロンモード（後方互換）
        $titleBadge = $badgeClass !== ''
            ? '<span class="badge ' . Layout::escape($badgeClass) . '" style="margin-left:8px;font-size:11px;padding:1px 6px;">' . Layout::escape($label) . '</span>'
            : '<span style="margin-left:8px;font-size:12px;color:var(--text-secondary);font-weight:400;">' . Layout::escape($label) . '</span>';

        return '<div class="perf-chart">'
            . '<div class="perf-chart-title">月次推移（前年対比）' . $titleBadge . '</div>'
            . $tableContent
            . $legend
            . '</div>';
    }

    /**
     * 担当者選択ドロップダウン HTML を生成する。
     * 先頭は常に「全体」、以降はテナントユーザーをフラットに列挙する。
     *
     * @param array<int, array{id: int, display_name: string}> $tenantUsers
     */
    private static function buildUserDropdown(
        string $id,
        string $currentParam,
        int $loginUserId,
        string $loginDisplayName,
        array $tenantUsers
    ): string {
        // 'self' は後方互換のため user_id に解決する
        if ($currentParam === 'self') {
            $currentParam = (string) $loginUserId;
        }
        $isAll = $currentParam === 'all' || $currentParam === '';

        $html = '<select id="' . Layout::escape($id) . '" class="user-select">';

        // 先頭: 全体（デフォルト）
        $html .= '<option value="all"' . ($isAll ? ' selected' : '') . '>全体</option>';

        // テナントユーザー一覧をフラットに列挙
        foreach ($tenantUsers as $u) {
            $selected = (!$isAll && (int) $currentParam === $u['id']) ? ' selected' : '';
            $html .= '<option value="' . (int) $u['id'] . '"' . $selected . '>'
                . Layout::escape($u['display_name'])
                . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * @param array<string, mixed> $activityData
     */
    private static function renderActivityCard(array $activityData, string $activityDailyUrl, string $activityListUrl): string
    {
        $isError    = isset($activityData['error']);
        $todayCount = $isError ? null : (int) ($activityData['today_count'] ?? 0);

        $countStr = $todayCount !== null ? (string) $todayCount . ' 件' : '取得できませんでした';

        return '<div class="activity-card">'
            . '<div class="activity-header">'
            . '<span class="activity-title">営業活動</span>'
            . '<a href="' . Layout::escape($activityListUrl) . '" class="activity-link">活動を検索 →</a>'
            . '</div>'
            . '<div class="activity-row">'
            . '<span class="activity-label">今日の活動</span>'
            . '<span class="activity-value">' . Layout::escape($countStr) . '</span>'
            . '</div>'
            . '<div style="margin-top:10px;text-align:right;"><a href="' . Layout::escape($activityDailyUrl) . '" style="font-size:12px;color:var(--text-info);text-decoration:none;">日報ビューへ</a></div>'
            . '</div>';
    }
}
