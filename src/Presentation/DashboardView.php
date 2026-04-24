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
        // 方針: `docs/screens/wireframe/insurance_wireframe.html` のトーンに寄せた
        // CSS 磨き込み。HTML 構造と JS 接点 (ID / 状態クラス) は不変。
        $css = '<style>'
            // ページタイトル（wireframe .page-title 基準。ホーム画面スコープで上書き）
            . '.page-header .title{font-size:22px;font-weight:700;color:var(--text-heading);letter-spacing:-0.3px;line-height:1.25;}'
            // 要確認エリア（wireframe .alert-card: icon + body + chev）--------
            . '.alert-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;}'
            . '.alert-card{display:flex;align-items:center;gap:14px;border-radius:var(--radius-lg);padding:16px 18px;border:1px solid transparent;cursor:pointer;text-decoration:none;color:inherit;box-shadow:var(--shadow-card);transition:box-shadow .15s,transform .15s,border-color .15s;}'
            . '.alert-card:hover{box-shadow:var(--shadow-card-hover);transform:translateY(-1px);}'
            . '.alert-card:active{transform:translateY(0);box-shadow:var(--shadow-card);}'
            . '.alert-card-danger{background:var(--bg-danger);border-color:var(--border-danger);}'
            . '.alert-card-warning{background:var(--bg-warning);border-color:var(--border-warning);}'
            . '.alert-icon{width:var(--icon-size-md);height:var(--icon-size-md);border-radius:var(--radius-md);display:grid;place-items:center;flex:0 0 var(--icon-size-md);}'
            . '.alert-card-danger .alert-icon{background:var(--bg-icon-danger);color:var(--text-danger);}'
            . '.alert-card-warning .alert-icon{background:var(--bg-icon-warning);color:var(--text-warning);}'
            . '.alert-icon svg{width:24px;height:24px;}'
            . '.alert-body{flex:1;min-width:0;}'
            . '.alert-label{font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:2px;letter-spacing:0.2px;}'
            . '.alert-card-danger .alert-label{color:var(--text-danger);}'
            . '.alert-card-warning .alert-label{color:var(--text-warning);}'
            . '.alert-count{font-size:26px;font-weight:700;letter-spacing:-0.5px;line-height:1.1;font-variant-numeric:tabular-nums;}'
            . '.alert-card-danger .alert-count{color:var(--text-danger);}'
            . '.alert-card-warning .alert-count{color:var(--text-warning);}'
            . '.alert-sub{font-size:13px;margin-top:6px;font-weight:500;}'
            . '.alert-card-danger .alert-sub{color:var(--text-danger);opacity:0.85;}'
            . '.alert-card-warning .alert-sub{color:var(--text-warning);opacity:0.85;}'
            . '.alert-chev{flex:0 0 auto;color:var(--text-muted-cool);font-size:22px;line-height:1;font-weight:700;}'
            . '.alert-card-danger .alert-chev{color:var(--text-danger);opacity:0.7;}'
            . '.alert-card-warning .alert-chev{color:var(--text-warning);opacity:0.7;}'
            // 要確認: 数値ペア（対応遅れ / 7日以内）
            . '.alert-metric-pair{display:flex;gap:20px;align-items:baseline;flex-wrap:wrap;}'
            . '.alert-metric-title{font-size:12px;margin-right:5px;color:inherit;opacity:0.9;font-weight:600;}'
            . '.alert-metric-num{font-size:24px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;letter-spacing:-0.3px;}'
            . '.alert-metric-unit{font-size:13px;font-weight:500;margin-left:3px;}'
            // ユーザー選択セレクト --------------------------------------------
            . '.user-select{font-size:13px;padding:5px 10px;min-height:32px;border:1px solid var(--border-medium);border-radius:var(--radius-md);background:var(--bg-primary);color:var(--text-heading);font-weight:500;cursor:pointer;max-width:140px;transition:border-color .12s,box-shadow .12s;}'
            . '.user-select:hover{border-color:var(--border-cool-accent);}'
            // 業務入口（wireframe .entry-card: head + metric-row + foot）-------
            . '.entry-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}'
            . '.entry-card{background:var(--bg-primary);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px;box-shadow:var(--shadow-card);transition:box-shadow .15s,transform .15s,border-color .15s;color:inherit;cursor:pointer;}'
            . '.entry-card:hover{box-shadow:var(--shadow-card-hover);border-color:var(--border-cool-accent);}'
            . '.entry-card:focus-visible{outline:2px solid var(--border-info);outline-offset:2px;}'
            . '.entry-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;}'
            . '.entry-name{display:flex;align-items:center;gap:10px;min-width:0;}'
            . '.entry-icon{width:var(--icon-size-sm);height:var(--icon-size-sm);border-radius:var(--radius-md);display:grid;place-items:center;flex:0 0 var(--icon-size-sm);}'
            . '.entry-icon svg{width:18px;height:18px;}'
            . '.entry-icon-info{background:var(--bg-icon-info);color:var(--text-info);}'
            . '.entry-icon-warning{background:var(--bg-icon-warning);color:var(--text-warning);}'
            . '.entry-icon-success{background:var(--bg-icon-success);color:var(--text-success);}'
            . '.entry-title-text{font-size:15px;font-weight:700;color:var(--text-heading);letter-spacing:-0.1px;min-width:0;}'
            . '.entry-title-hint{font-size:12px;font-weight:500;color:var(--text-muted-cool);margin-left:5px;}'
            . '.entry-right{display:flex;align-items:center;gap:8px;flex-shrink:0;}'
            // metric-row（3 カラムグリッド）------------------------------------
            . '.metric-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px;border-radius:var(--radius-md);overflow:hidden;background:var(--bg-subtle);}'
            . '.metric-cell{background:var(--bg-primary);padding:12px 10px;text-align:center;min-width:0;}'
            . '.metric-cell-label{font-size:11px;color:var(--text-secondary);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;}'
            . '.metric-cell-value{font-size:20px;font-weight:700;color:var(--text-heading);letter-spacing:-0.4px;line-height:1.15;font-variant-numeric:tabular-nums;}'
            . '.metric-cell-value.accent{color:var(--text-info);}'
            . '.metric-cell-value.danger{color:var(--text-danger);}'
            . '.metric-cell-value.up{color:var(--text-success);}'
            . '.metric-cell-value.down{color:var(--text-danger);}'
            // 成績サマリ（カード外枠）------------------------------------------
            . '.perf-card{background:var(--bg-primary);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px 22px;margin-bottom:14px;box-shadow:var(--shadow-card);}'
            . '.year-summary-label{font-size:13px;font-weight:600;color:var(--text-label);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.4px;}'
            . '.year-summary-main{display:flex;align-items:baseline;gap:6px;margin-bottom:16px;}'
            . '.year-summary-amount{font-size:52px;font-weight:600;color:var(--text-heading);line-height:1;font-variant-numeric:tabular-nums;letter-spacing:-1px;}'
            . '.year-summary-unit{font-size:16px;color:var(--text-label);font-weight:500;}'
            . '.year-summary-compare{display:flex;align-items:stretch;border-top:1px solid var(--border-light);padding-top:12px;gap:0;}'
            . '.year-summary-compare-item{flex:1;display:flex;flex-direction:column;gap:4px;padding:0 14px;}'
            . '.year-summary-compare-item:first-child{padding-left:0;}'
            . '.year-summary-compare-item:last-child{padding-right:0;}'
            . '.year-summary-compare-divider{width:1px;background:var(--border-light);flex-shrink:0;}'
            . '.year-summary-compare-label{font-size:12px;color:var(--text-label);font-weight:500;}'
            . '.year-summary-compare-value{font-size:14px;font-weight:600;color:var(--text-heading);}'
            . '.year-summary-compare-value.up{color:var(--text-success);}'
            . '.year-summary-compare-value.down{color:var(--text-danger);}'
            // 成績タイル（全体 / 損保 / 生保）-----------------------------------
            . '.perf-tile-grid{display:flex;gap:12px;flex-wrap:wrap;}'
            . '.perf-tile{flex:1 1 200px;min-width:200px;padding:14px 16px;border:1px solid var(--border-light);border-radius:var(--radius-md);background:var(--bg-subtle);display:flex;flex-direction:column;gap:7px;}'
            . '.perf-tile-head{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:22px;margin-bottom:1px;}'
            . '.perf-tile-title{font-size:12px;font-weight:700;color:var(--text-label);}'
            . '.perf-tile-amount{display:flex;align-items:baseline;justify-content:space-between;gap:8px;flex-wrap:wrap;}'
            . '.perf-tile-amount-main{display:flex;align-items:baseline;gap:5px;min-width:0;}'
            . '.perf-tile-num{font-size:26px;font-weight:700;color:var(--text-heading);line-height:1.1;font-variant-numeric:tabular-nums;letter-spacing:-0.5px;}'
            . '.perf-tile-unit{font-size:12px;color:var(--text-secondary);font-weight:500;}'
            . '.perf-tile-count{font-size:12px;color:var(--text-secondary);font-variant-numeric:tabular-nums;font-weight:500;}'
            // 達成率プログレスバー ----------------------------------------------
            . '.perf-tile-bar{display:flex;flex-direction:column;gap:4px;margin-top:2px;}'
            . '.perf-tile-bar-track{height:6px;border-radius:999px;background:var(--bg-tertiary);overflow:hidden;}'
            . '.perf-tile-bar-fill{height:100%;border-radius:999px;transition:width .3s ease;background:linear-gradient(90deg,var(--chart-bar-current),var(--border-info));}'
            . '.perf-tile-life .perf-tile-bar-fill{background:linear-gradient(90deg,var(--chart-bar-life),#e0a74f);}'
            . '.perf-tile-bar-caption{display:flex;justify-content:space-between;align-items:baseline;font-size:12px;color:var(--text-secondary);font-weight:500;}'
            . '.perf-tile-bar-value{font-size:12px;font-weight:700;color:var(--text-heading);font-variant-numeric:tabular-nums;}'
            . '.perf-tile-bar-value.achievement-over{color:var(--text-success);}'
            . '.perf-tile-bar-value.is-unset{color:var(--text-muted-cool);font-weight:500;}'
            // metrics（前年比 + 年度目標 の 2 カラム）----------------------------
            . '.perf-tile-metrics{display:flex;gap:10px;border-top:1px solid var(--border-light);padding-top:8px;margin-top:3px;}'
            . '.perf-tile-metric{flex:1;display:flex;flex-direction:column;gap:2px;min-width:0;}'
            . '.perf-tile-metric-label{font-size:12px;color:var(--text-secondary);font-weight:500;}'
            . '.perf-tile-metric-value{font-size:13px;font-weight:600;color:var(--text-heading);font-variant-numeric:tabular-nums;}'
            . '.perf-tile-metric-value.up{color:var(--text-success);}'
            . '.perf-tile-metric-value.down{color:var(--text-danger);}'
            . '.perf-tile-metric-value.achievement-over{color:var(--text-success);}'
            . '@media (max-width: 1024px){.perf-tile{flex:1 1 calc(50% - 6px);min-width:0;}}'
            . '@media (max-width: 480px){.perf-tile{flex:1 1 100%;}}'
            // 年度タブ（wireframe tab-bar 準拠）---------------------------------
            . '.year-tabs{display:inline-flex;gap:0;border-radius:var(--radius-md);background:var(--bg-subtle);border:1px solid var(--border-cool);padding:3px;}'
            . '.year-tab{appearance:none;font-size:13px;font-weight:600;padding:5px 12px;min-height:30px;border-radius:var(--radius-sm);border:none;background:transparent;color:var(--text-label);cursor:pointer;font-family:inherit;transition:background .12s,color .12s;line-height:1.4;}'
            . '.year-tab:hover{color:var(--text-heading);}'
            . '.year-tab.is-active{background:var(--bg-primary);color:var(--text-info);font-weight:700;box-shadow:var(--shadow-card);}'
            // 月次推移（チャート + トグル）--------------------------------------
            . '.perf-chart{margin-top:20px;padding-top:16px;border-top:1px solid var(--border-light);}'
            . '.perf-chart-title{font-size:13px;color:var(--text-heading);margin-bottom:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;}'
            . '.perf-chart-pane{display:none;}'
            . '.perf-chart-pane.active{display:block;}'
            . '.chart-toggle{display:inline-flex;gap:4px;text-transform:none;letter-spacing:0;}'
            . '.chart-toggle-btn{font-size:13px;padding:5px 14px;min-height:30px;border-radius:var(--radius-md);border:1px solid var(--border-medium);background:var(--bg-primary);color:var(--text-label);cursor:pointer;font-weight:600;line-height:1.4;transition:background .12s,color .12s,border-color .12s;}'
            . '.chart-toggle-btn:hover{border-color:var(--border-cool-accent);color:var(--text-heading);}'
            . '.chart-toggle-btn.active{background:var(--bg-info);color:var(--text-info);border-color:var(--border-info);font-weight:700;}'
            . '.chart-toggle-btn[data-target="life"].active{background:var(--bg-warning);color:var(--text-warning);border-color:var(--border-warning);}'
            . '.chart-toggle-btn[data-target="all"].active{background:var(--bg-secondary);color:var(--text-heading);border-color:var(--border-medium);}'
            // SVG 棒グラフ本体 -------------------------------------------------
            . '.chart-svg-wrap{width:100%;margin:8px 0 10px;overflow-x:auto;-webkit-overflow-scrolling:touch;}'
            . '.chart-svg{width:100%;min-width:680px;height:220px;display:block;overflow:visible;}'
            . '.chart-axis{stroke:var(--border-cool);stroke-width:1;fill:none;}'
            . '.chart-grid{stroke:var(--border-light);stroke-width:1;stroke-dasharray:2 3;fill:none;}'
            . '.chart-grid-label{font-size:10px;fill:var(--text-secondary);font-variant-numeric:tabular-nums;}'
            . '.chart-month{font-size:10px;fill:var(--text-secondary);text-anchor:middle;}'
            . '.chart-month.is-current{fill:var(--text-info);font-weight:700;}'
            . '.chart-bar-current{fill:var(--chart-bar-current);}'
            . '.chart-bar-previous{fill:var(--chart-bar-previous);}'
            . '.perf-chart-pane[data-src="life"] .chart-bar-current{fill:var(--chart-bar-life);}'
            . '.perf-chart-pane[data-src="life"] .chart-bar-previous{fill:var(--chart-bar-life-previous);}'
            . '.perf-chart-pane[data-src="life"] .legend-swatch-current{background:var(--chart-bar-life);}'
            . '.perf-chart-pane[data-src="life"] .legend-swatch-previous{background:var(--chart-bar-life-previous);}'
            . '.chart-target-line{stroke:var(--border-info);stroke-width:2;stroke-dasharray:5 3;fill:none;}'
            . '.chart-target-label{font-size:11px;fill:var(--text-info);font-weight:700;font-variant-numeric:tabular-nums;}'
            // グラフ凡例 -------------------------------------------------------
            . '.chart-legend{display:flex;gap:14px;font-size:12px;color:var(--text-label);margin-bottom:10px;align-items:center;flex-wrap:wrap;}'
            . '.legend-item{display:inline-flex;align-items:center;gap:6px;}'
            . '.legend-swatch{display:inline-block;width:12px;height:12px;border-radius:3px;}'
            . '.legend-swatch-current{background:var(--chart-bar-current);}'
            . '.legend-swatch-previous{background:var(--chart-bar-previous);}'
            . '.legend-swatch-life-current{background:var(--chart-bar-life);}'
            . '.legend-swatch-life-previous{background:var(--chart-bar-life-previous);}'
            . '.legend-swatch-target{background:transparent;border-top:2px dashed var(--border-info);width:18px;height:0;border-radius:0;}'
            // 数値テーブル（常時表示、グラフの下）---------------------------
            . '.perf-chart-table-block{margin-top:16px;}'
            . '.perf-chart-table-caption{font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:8px;letter-spacing:0.2px;}'
            . '.chart-container{width:100%;overflow-x:auto;border:1px solid var(--border-light);border-radius:var(--radius-md);}'
            . '.chart-table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums;}'
            . '.chart-table th{font-size:12px;color:var(--text-label);font-weight:600;padding:8px 8px;text-align:center;white-space:nowrap;border-bottom:1px solid var(--border-light);background:var(--bg-subtle);}'
            . '.chart-table th.current{color:var(--text-info);font-weight:700;}'
            . '.chart-table td{font-size:13px;padding:8px 8px;text-align:right;white-space:nowrap;border-bottom:1px solid var(--border-light);color:var(--text-primary);}'
            . '.chart-table td.row-header{text-align:left;color:var(--text-label);font-size:12px;font-weight:600;background:var(--bg-subtle);}'
            . '.chart-table td.current-month{background:rgba(55,138,221,0.08);font-weight:600;}'
            . '.chart-table td.current{background:rgba(55,138,221,0.08);font-weight:600;}'
            . '.chart-table td.up{color:var(--text-success);font-weight:600;}'
            . '.chart-table td.down{color:var(--text-danger);font-weight:600;}'
            . '.chart-table td.achievement-rate{font-weight:700;}'
            . '.achievement-over{color:var(--text-success);}'
            . '.perf-legend{display:flex;justify-content:flex-end;margin-top:8px;font-size:12px;color:var(--text-label);font-weight:500;}'
            . '.perf-legend-link{color:var(--text-info);text-decoration:none;font-weight:600;}'
            . '.perf-legend-link:hover{text-decoration:underline;}'
            . '.perf-error{color:var(--text-label);font-size:13px;padding:10px 0;font-weight:500;}'
            . '.perf-chart-title-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}'
            . '.chart-col-annual{color:var(--text-label)!important;font-size:12px;font-weight:600;background:var(--bg-subtle);}'
            . '.activity-card-foot{margin-top:12px;text-align:right;}'
            // 旧 activity-card 系（後方互換で保持）-------------------------------
            . '.activity-card{background:var(--bg-primary);border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:18px 20px;box-shadow:var(--shadow-card);}'
            . '.activity-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}'
            . '.activity-title{font-size:14px;font-weight:700;color:var(--text-heading);}'
            . '.activity-link{font-size:13px;color:var(--text-info);font-weight:600;cursor:pointer;text-decoration:none;}'
            . '.activity-link:hover{text-decoration:underline;}'
            . '.activity-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;}'
            . '.activity-row:not(:last-child){border-bottom:1px solid var(--border-light);}'
            . '.activity-label{font-size:13px;color:var(--text-label);font-weight:500;}'
            . '.activity-value{font-size:14px;font-weight:600;color:var(--text-heading);font-variant-numeric:tabular-nums;}'
            // 共通（セクション見出し / 下部グリッド）-----------------------------
            . '.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}'
            . '.section-label{font-size:13px;font-weight:700;color:var(--text-label);margin-bottom:12px;padding-bottom:5px;text-transform:uppercase;letter-spacing:0.6px;}'
            // 成績サマリ カード内ヘッダー（年度 + 担当者）-----------------------
            . '.perf-card-header{display:flex;align-items:center;justify-content:flex-start;gap:10px;margin-bottom:14px;flex-wrap:wrap;}'
            . '@media(max-width:480px){.perf-card-header{gap:8px;margin-bottom:12px;}}'
            // アンカージャンプ時の sticky header 分オフセット（site-header: 50px + 余白）
            . '#perf-summary{scroll-margin-top:70px;}'
            // モバイル（768px 以下）---------------------------------------------
            . '@media(max-width:768px){'
            . '.alert-grid{grid-template-columns:1fr;gap:8px;margin-bottom:14px;}'
            . '.entry-grid{grid-template-columns:1fr;gap:8px;margin-bottom:14px;}'
            . '.bottom-grid{grid-template-columns:1fr;gap:10px;}'
            . '.chart-container{overflow-x:scroll;-webkit-overflow-scrolling:touch;}'
            . '.chart-table{min-width:620px;}'
            . '.perf-card{padding:14px 16px;}'
            . '.alert-card{padding:12px 14px;gap:12px;}'
            . '.entry-card{padding:14px;}'
            . '.entry-head{margin-bottom:10px;}'
            . '.metric-cell{padding:10px 8px;}'
            . '.section-label{margin-bottom:10px;padding-bottom:4px;}'
            . '.year-tabs{overflow-x:auto;max-width:100%;}'
            . '.chart-svg{height:200px;min-width:700px;}'
            . '.chart-svg-wrap{border:1px solid var(--border-light);border-radius:var(--radius-md);background:var(--bg-subtle);padding:4px;}'
            . '.perf-chart{margin-top:14px;padding-top:12px;}'
            . '.perf-tile{padding:14px;gap:6px;}'
            . '}'
            // 小型スマホ（480px 以下）-----------------------------------------
            . '@media(max-width:480px){'
            . '.alert-card{padding:11px 12px;gap:10px;}'
            . '.alert-metric-pair{gap:12px;}'
            . '.alert-icon{width:36px;height:36px;flex:0 0 36px;}'
            . '.alert-icon svg{width:20px;height:20px;}'
            . '.alert-count{font-size:26px;}'
            . '.alert-metric-num{font-size:22px;}'
            . '.entry-card{padding:12px 14px;}'
            . '.entry-head{margin-bottom:8px;}'
            . '.entry-title-text{font-size:14px;}'
            . '.metric-cell{padding:8px 6px;}'
            . '.metric-cell-value{font-size:18px;}'
            . '.metric-cell-label{font-size:11px;}'
            . '.year-summary-amount{font-size:40px;}'
            . '.perf-tile{padding:12px 14px;}'
            . '.chart-svg{min-width:640px;height:200px;}'
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
        $accidentHighVal    = $accidentHighCnt !== null ? $accidentHighCnt . '<span class="alert-metric-unit">件</span>' : '—';

        // インライン SVG アイコン（アクセシビリティ用 aria-hidden）
        $iconCalendar = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        $iconAlertTriangle = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        $iconTarget = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>';
        $iconChev = '<span class="alert-chev" aria-hidden="true">›</span>';

        // カード→一覧遷移時に渡すフィルタ条件を組み立てる。
        // - 要確認エリア: quick_filter のみ（満期=w7 / 事故=high_open）
        // - 業務入口エリア: カードで選択中の担当者で絞り込み（all/空 は付与しない）
        $resolvedRenewalStaff   = self::resolveStaffIdFromUserParam($renewalUserParam,   $loginUserId);
        $resolvedAccidentStaff  = self::resolveStaffIdFromUserParam($accidentUserParam,  $loginUserId);
        $resolvedSalesCaseStaff = self::resolveStaffIdFromUserParam($salesCaseUserParam, $loginUserId);

        $alertRenewalHref   = self::appendQuery($renewalListUrl,   ['quick_filter' => 'w7']);
        $alertAccidentHref  = self::appendQuery($accidentListUrl,  ['quick_filter' => 'high_open']);
        $entryRenewalHref   = self::appendQuery($renewalListUrl,   $resolvedRenewalStaff   !== '' ? ['assigned_staff_id' => $resolvedRenewalStaff]   : []);
        $entryAccidentHref  = self::appendQuery($accidentListUrl,  $resolvedAccidentStaff  !== '' ? ['assigned_staff_id' => $resolvedAccidentStaff]  : []);
        $entrySalesCaseHref = self::appendQuery($salesCaseListUrl, $resolvedSalesCaseStaff !== '' ? ['staff_id'          => $resolvedSalesCaseStaff] : []);

        $alertGrid = '<div class="section-label">要確認</div>'
            . '<div class="alert-grid">'
            // 満期（対応遅れ＋7日以内）を1枚にまとめる
            . '<a href="' . Layout::escape($alertRenewalHref) . '" class="alert-card alert-card-danger" aria-label="満期一覧へ">'
            . '<div class="alert-icon">' . $iconCalendar . '</div>'
            . '<div class="alert-body">'
            . '<div class="alert-label">満期</div>'
            . '<div class="alert-metric-pair">'
            . '<span><span class="alert-metric-title">対応遅れ</span><span class="alert-metric-num">' . ($renewalOverdue !== null ? (int) $renewalOverdue : '—') . '</span><span class="alert-metric-unit">件</span></span>'
            . '<span><span class="alert-metric-title">7日以内</span><span class="alert-metric-num">' . ($renewalWithin7 !== null ? (int) $renewalWithin7 : '—') . '</span><span class="alert-metric-unit">件</span></span>'
            . '</div>'
            . '</div>'
            . $iconChev
            . '</a>'
            // 事故 — 高優先度未完了
            . '<a href="' . Layout::escape($alertAccidentHref) . '" class="alert-card alert-card-warning" aria-label="事故案件一覧へ">'
            . '<div class="alert-icon">' . $iconAlertTriangle . '</div>'
            . '<div class="alert-body">'
            . '<div class="alert-label">事故 — 高優先度未完了</div>'
            . '<div class="alert-count">' . ($accidentHighCnt !== null ? (int) $accidentHighCnt : '—') . '<span class="alert-metric-unit">件</span></div>'
            . '</div>'
            . $iconChev
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

        // 業務入口用アイコン（entry-icon 内に埋め込む 18px SVG）
        $iconRenewal = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>';
        $iconAccident = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L2 7v5c0 5 3.8 9.5 10 11 6.2-1.5 10-6 10-11V7l-10-5z"/></svg>';
        $iconProspect = $iconTarget; // 見込は target アイコン流用

        // entry-card は全面タップ可能（dashboard.js の initCardLinks が data-href を扱う）
        // select 等の内部操作要素はクリックを停止伝播させて遷移させない

        $bizGrid = '<div class="section-label">業務入口</div>'
            . '<div class="entry-grid">'
            // 満期業務
            . '<div class="entry-card" id="card-renewal" data-href="' . Layout::escape($entryRenewalHref) . '" data-href-base="' . Layout::escape($renewalListUrl) . '" data-staff-param="assigned_staff_id" role="link" tabindex="0" aria-label="満期業務を開く">'
            . '<div class="entry-head">'
            . '<div class="entry-name">'
            . '<div class="entry-icon entry-icon-info">' . $iconRenewal . '</div>'
            . '<div class="entry-title-text">満期業務<span class="entry-title-hint">(未完了)</span></div>'
            . '</div>'
            . '<div class="entry-right">' . $renewalDropdown . '</div>'
            . '</div>'
            . '<div class="metric-row">'
            . '<div class="metric-cell"><div class="metric-cell-label">14日以内</div><div class="metric-cell-value accent within-14">' . Layout::escape($renewalWithin14Str) . '</div></div>'
            . '<div class="metric-cell"><div class="metric-cell-label">28日以内</div><div class="metric-cell-value within-28">' . Layout::escape($renewalWithin28Str) . '</div></div>'
            . '<div class="metric-cell"><div class="metric-cell-label">60日以内</div><div class="metric-cell-value within-60">' . Layout::escape($renewalWithin60Str) . '</div></div>'
            . '</div>'
            . '</div>'
            // 事故案件
            . '<div class="entry-card" id="card-accident" data-href="' . Layout::escape($entryAccidentHref) . '" data-href-base="' . Layout::escape($accidentListUrl) . '" data-staff-param="assigned_staff_id" role="link" tabindex="0" aria-label="事故案件を開く">'
            . '<div class="entry-head">'
            . '<div class="entry-name">'
            . '<div class="entry-icon entry-icon-warning">' . $iconAccident . '</div>'
            . '<div class="entry-title-text">事故案件<span class="entry-title-hint">(未完了)</span></div>'
            . '</div>'
            . '<div class="entry-right">' . $accidentDropdown . '</div>'
            . '</div>'
            . '<div class="metric-row">'
            . '<div class="metric-cell"><div class="metric-cell-label">高優先度</div><div class="metric-cell-value high' . $accidentHighCls . '">' . Layout::escape($accidentHighStr) . '</div></div>'
            . '<div class="metric-cell"><div class="metric-cell-label">中優先度</div><div class="metric-cell-value normal">' . Layout::escape($accidentMidStr) . '</div></div>'
            . '<div class="metric-cell"><div class="metric-cell-label">低優先度</div><div class="metric-cell-value low">' . Layout::escape($accidentLowStr) . '</div></div>'
            . '</div>'
            . '</div>'
            // 見込管理
            . '<div class="entry-card" id="card-sales-case" data-href="' . Layout::escape($entrySalesCaseHref) . '" data-href-base="' . Layout::escape($salesCaseListUrl) . '" data-staff-param="staff_id" role="link" tabindex="0" aria-label="見込管理を開く">'
            . '<div class="entry-head">'
            . '<div class="entry-name">'
            . '<div class="entry-icon entry-icon-success">' . $iconProspect . '</div>'
            . '<div class="entry-title-text">見込管理<span class="entry-title-hint">(未完了)</span></div>'
            . '</div>'
            . '<div class="entry-right">' . $salesCaseDropdown . '</div>'
            . '</div>'
            . '<div class="metric-row">'
            . '<div class="metric-cell"><div class="metric-cell-label">見込A</div><div class="metric-cell-value accent prospect-a">' . Layout::escape($scRankAStr) . '</div></div>'
            . '<div class="metric-cell"><div class="metric-cell-label">見込B</div><div class="metric-cell-value prospect-b">' . Layout::escape($scRankBStr) . '</div></div>'
            . '<div class="metric-cell"><div class="metric-cell-label">今月成約</div><div class="metric-cell-value expected">' . Layout::escape($scClosingStr) . '</div></div>'
            . '</div>'
            . '</div>'
            . '</div>';

        // ─── 成績サマリカード ───────────────────────────────────────────
        // ─── 成績サマリ セクションヘッダー（年度プルダウン + 担当者ドロップダウン）─────

        // 年度タブ（wireframe tab-bar 準拠。ID `fiscal-year` はコンテナに移譲）
        // dashboard.js initYearTabs() が [data-year] を拾って URL 再読込する。
        $yearTabButtons = '';
        foreach ($availableYears as $yr) {
            $yr = (int) $yr;
            $active = ($yr === $fiscalYear) ? ' is-active' : '';
            $selected = ($yr === $fiscalYear) ? 'true' : 'false';
            $yearTabButtons .= '<button type="button" class="year-tab' . $active . '" role="tab" aria-selected="' . $selected . '" data-year="' . $yr . '">' . $yr . '年度</button>';
        }
        $yearToggle = '<div id="fiscal-year" class="year-tabs" role="tablist" aria-label="年度切替">'
            . $yearTabButtons
            . '</div>';

        // 成績サマリ 担当者ドロップダウン
        $salesUserDropdown = self::buildUserDropdown('sales-user', $salesUserParam, $loginUserId, $loginDisplayName, $tenantUsers);

        // #perf-summary はスクロールアンカー。カード内にツール群があっても、
        // 画面遷移時は section-label の位置に戻ってくるようここにアンカーを置く。
        $perfSectionHeader = '<div id="perf-summary" class="section-label">成績サマリ</div>';

        // カード内ヘッダー（年度タブ + 担当者プルダウン）
        $perfCardHeader = '<div class="perf-card-header">'
            . $yearToggle
            . $salesUserDropdown
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
            $perfTab,
            $perfCardHeader
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
        string $activeTab = 'all',
        string $cardHeaderHtml = ''
    ): string {
        $monthNames = [1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
                       7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月'];

        if ($perfError) {
            $perfBody = '<div class="perf-error">取得できませんでした</div>';
        } else {
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
                    $achRate   = round($premium / $target * 100, 1);
                    $achStr    = number_format($achRate, 1) . '%';
                    $achCls    = $achRate >= 100 ? ' achievement-over' : '';
                    $barWidth  = number_format(min($achRate, 100.0), 1, '.', '');
                    $barUnset  = false;
                } else {
                    $achStr    = '目標未設定';
                    $achCls    = ' is-unset';
                    $barWidth  = '0';
                    $barUnset  = true;
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

                // 達成率プログレスバー（バー本体のみ。値はヘッダ右に表示）
                $barBlock = '<div class="perf-tile-bar">'
                    . '<div class="perf-tile-bar-track">'
                    . '<div class="perf-tile-bar-fill" style="width:' . $barWidth . '%"></div>'
                    . '</div>'
                    . '</div>';

                $achInlineHtml = '<span class="perf-tile-bar-value' . $achCls . '" title="達成率">' . Layout::escape($achStr) . '</span>';

                $tilesHtml .= '<div class="perf-tile perf-tile-' . Layout::escape($tile['key']) . '">'
                    . '<div class="perf-tile-head">' . $labelHtml . $achInlineHtml . '</div>'
                    . '<div class="perf-tile-amount">'
                    . '<span class="perf-tile-amount-main"><span class="perf-tile-num">' . Layout::escape(number_format((int) floor($premium / 1000))) . '</span><span class="perf-tile-unit">千円</span></span>'
                    . '<span class="perf-tile-count">' . Layout::escape(number_format($count)) . ' 件</span>'
                    . '</div>'
                    . $barBlock
                    . '<div class="perf-tile-metrics">'
                    . '<div class="perf-tile-metric"><span class="perf-tile-metric-label">前年比</span><span class="perf-tile-metric-value' . $yoyCls . '">' . Layout::escape($yoyStr) . '</span></div>'
                    . '<div class="perf-tile-metric"><span class="perf-tile-metric-label">年度目標</span><span class="perf-tile-metric-value">' . Layout::escape($targetStr) . '</span></div>'
                    . '</div>'
                    . '</div>';
            }

            $perfBody = '<div class="perf-tile-grid">'
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

        $sharedLegend = '<div class="perf-legend">'
            . '<span>単位: 千円 ｜ <a href="' . Layout::escape($salesListUrl) . '" class="perf-legend-link">成績管理一覧 →</a></span>'
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
            . '<div class="perf-chart-title perf-chart-title-row">'
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
            . $cardHeaderHtml
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
        $thCells .= '<th class="chart-col-annual">年間</th>';

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
        $currentRow .= '<td class="annual-current chart-col-annual">' . Layout::escape($annualCyStr) . '</td></tr>';

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
        $prevRow .= '<td class="annual-previous chart-col-annual">' . Layout::escape($annualPyYTDStr) . '</td></tr>';

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
        $achievementRow .= '<td class="achievement-annual chart-col-annual' . $annualAchColCls . '">'
            . Layout::escape($annualAchColStr) . '</td></tr>';

        $legend = $showFooterLegend
            ? '<div class="perf-legend">'
                . '<span>単位: 千円 ｜ <a href="' . Layout::escape($salesListUrl) . '" class="perf-legend-link">成績管理一覧 →</a></span>'
                . '</div>'
            : '';

        $tableIdAttr = ($tableId !== null && $tableId !== '') ? ' id="' . Layout::escape($tableId) . '"' : '';

        if ($perfError) {
            $tableContent = '<div class="perf-error">取得できませんでした</div>';
        } else {
            $tableContent = '<div class="chart-container">'
                . '<table' . $tableIdAttr . ' class="chart-table">'
                . '<thead><tr>' . $thCells . '</tr></thead>'
                . '<tbody>' . $currentRow . $prevRow . $yoyRow . $achievementRow . '</tbody>'
                . '</table>'
                . '</div>';
        }

        // pane モード: グラフ凡例 + SVG 棒グラフ + details 折りたたみテーブル
        if ($paneKey !== null) {
            $activeCls = $paneActive ? ' active' : '';
            // 凡例（life pane は CSS で swatch 色が上書きされる）
            $chartLegend = '<div class="chart-legend">'
                . '<span class="legend-item"><i class="legend-swatch legend-swatch-current"></i>今年</span>'
                . '<span class="legend-item"><i class="legend-swatch legend-swatch-previous"></i>前年</span>'
                . ($targetAnnual !== null && $targetAnnual > 0
                    ? '<span class="legend-item"><i class="legend-swatch legend-swatch-target"></i>月次目標</span>'
                    : '')
                . '</div>';
            // SVG 棒グラフ
            $svgChart = $perfError
                ? '<div class="perf-error">取得できませんでした</div>'
                : self::renderBarChart(
                    $fiscalYear, $currentMonth, $currentFiscalYear,
                    $perfCurrent, $perfPrev, $targetAnnual, $paneKey
                );
            // 数値テーブルは常時表示（#monthly-trend は all pane のみ）
            $tableBlock = $perfError
                ? ''
                : '<div class="perf-chart-table-block">'
                    . '<div class="perf-chart-table-caption">月別成績（千円）</div>'
                    . $tableContent
                    . '</div>';
            return '<div class="perf-chart-pane' . $activeCls . '" data-src="' . Layout::escape($paneKey) . '">'
                . $chartLegend
                . $svgChart
                . $tableBlock
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
     * 月次棒グラフ（SVG）をレンダリングする。
     * 各月 2 本のグループバー（今年 + 前年）を描画し、年度目標ラインも引く。
     * 値は千円単位に切り捨てて正規化し、Y 軸はデータ最大値から近い切りの良い値へ拡張。
     *
     * @param array<int, array{premium: int, count: int}> $perfCurrent 今年度の月次成績
     * @param array<int, array{premium: int, count: int}> $perfPrev    前年度の月次成績
     */
    private static function renderBarChart(
        int $fiscalYear,
        int $currentMonth,
        int $currentFiscalYear,
        array $perfCurrent,
        array $perfPrev,
        ?int $targetAnnual,
        string $paneKey
    ): string {
        $monthLabels = [4 => '4月', 5 => '5月', 6 => '6月', 7 => '7月', 8 => '8月', 9 => '9月',
                        10 => '10月', 11 => '11月', 12 => '12月', 1 => '1月', 2 => '2月', 3 => '3月'];
        $fiscalOrder = [4=>0,5=>1,6=>2,7=>3,8=>4,9=>5,10=>6,11=>7,12=>8,1=>9,2=>10,3=>11];

        // 千円単位への正規化
        $toThousands = static function ($v): int { return (int) floor(((int) $v) / 1000); };
        $targetMonthlyK = ($targetAnnual !== null && $targetAnnual > 0)
            ? (int) floor($targetAnnual / 12 / 1000)
            : null;

        // Y 軸スケール決定
        $maxValue = 0;
        foreach (self::FISCAL_MONTHS as $m) {
            $cy = $toThousands($perfCurrent[$m]['premium'] ?? 0);
            $py = $toThousands($perfPrev[$m]['premium'] ?? 0);
            $maxValue = max($maxValue, $cy, $py);
        }
        if ($targetMonthlyK !== null) {
            $maxValue = max($maxValue, $targetMonthlyK);
        }
        if ($maxValue === 0) {
            $maxValue = 100;
        }
        $niceScale = self::niceCeil($maxValue * 1.15);

        // SVG 座標系
        $vbW = 720;  $vbH = 220;
        $padL = 44;  $padR = 16;  $padT = 20;  $padB = 32;
        $plotW = $vbW - $padL - $padR;         // 660
        $plotH = $vbH - $padT - $padB;         // 168
        $plotBottom = $padT + $plotH;          // 188
        $slotW = $plotW / 12;                  // 55
        $barW  = 16;  $barGap = 3;
        $groupW = $barW * 2 + $barGap;         // 35
        $groupOffset = ($slotW - $groupW) / 2; // 10

        $yAt = static function (float $v) use ($plotH, $plotBottom, $niceScale): float {
            if ($niceScale <= 0) {
                return (float) $plotBottom;
            }
            return $plotBottom - ($v / $niceScale) * $plotH;
        };
        $fmt = static function (float $v): string {
            return number_format($v, 2, '.', '');
        };

        // グリッド線 + Y ラベル（5 段階）
        $grids = '';
        $steps = 4;
        for ($i = 0; $i <= $steps; $i++) {
            $v = ($niceScale / $steps) * $i;
            $y = $yAt($v);
            $grids .= '<line class="chart-grid" x1="' . $padL . '" y1="' . $fmt($y)
                   . '" x2="' . ($padL + $plotW) . '" y2="' . $fmt($y) . '"/>';
            $grids .= '<text class="chart-grid-label" x="' . ($padL - 6) . '" y="' . $fmt($y + 3)
                   . '" text-anchor="end">' . Layout::escape(number_format((int) round($v))) . '</text>';
        }

        // X 軸ベース
        $axis = '<line class="chart-axis" x1="' . $padL . '" y1="' . $plotBottom
              . '" x2="' . ($padL + $plotW) . '" y2="' . $plotBottom . '"/>';

        // バー + 月ラベル
        $bars = '';
        $labels = '';
        $idx = 0;
        foreach (self::FISCAL_MONTHS as $m) {
            $isFuture = ($fiscalYear === $currentFiscalYear)
                && (($fiscalOrder[$m] ?? 0) > ($fiscalOrder[$currentMonth] ?? 0));
            $isCurrent = ($m === $currentMonth);
            $cy = $toThousands($perfCurrent[$m]['premium'] ?? 0);
            $py = $toThousands($perfPrev[$m]['premium']    ?? 0);

            $slotX = $padL + $slotW * $idx;
            $bar1X = $slotX + $groupOffset;             // 今年
            $bar2X = $bar1X + $barW + $barGap;          // 前年

            if (!$isFuture && $cy > 0) {
                $y = $yAt((float) $cy);
                $h = $plotBottom - $y;
                $bars .= '<rect class="chart-bar-current" x="' . $fmt($bar1X)
                      . '" y="' . $fmt($y) . '" width="' . $barW . '" height="' . $fmt($h) . '" rx="2">'
                      . '<title>' . Layout::escape($monthLabels[$m] . ' 今年 ' . number_format($cy) . ' 千円') . '</title>'
                      . '</rect>';
            }
            if ($py > 0) {
                $y = $yAt((float) $py);
                $h = $plotBottom - $y;
                $bars .= '<rect class="chart-bar-previous" x="' . $fmt($bar2X)
                      . '" y="' . $fmt($y) . '" width="' . $barW . '" height="' . $fmt($h) . '" rx="2">'
                      . '<title>' . Layout::escape($monthLabels[$m] . ' 前年 ' . number_format($py) . ' 千円') . '</title>'
                      . '</rect>';
            }

            $centerX = $slotX + $slotW / 2;
            $labelCls = 'chart-month' . ($isCurrent ? ' is-current' : '');
            $labels .= '<text class="' . $labelCls . '" x="' . $fmt($centerX)
                    . '" y="' . ($plotBottom + 16) . '">' . Layout::escape($monthLabels[$m] ?? '') . '</text>';
            $idx++;
        }

        // 目標ライン
        $targetLine = '';
        if ($targetMonthlyK !== null && $targetMonthlyK > 0) {
            $y = $yAt((float) $targetMonthlyK);
            $targetLine = '<line class="chart-target-line" x1="' . $padL . '" y1="' . $fmt($y)
                        . '" x2="' . ($padL + $plotW) . '" y2="' . $fmt($y) . '"/>'
                        . '<text class="chart-target-label" x="' . ($padL + $plotW - 4) . '" y="' . $fmt($y - 4)
                        . '" text-anchor="end">目標 ' . Layout::escape(number_format($targetMonthlyK)) . '</text>';
        }

        return '<div class="chart-svg-wrap">'
            . '<svg class="chart-svg" viewBox="0 0 ' . $vbW . ' ' . $vbH
            . '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="月次推移棒グラフ（' . Layout::escape($paneKey) . '）">'
            . $grids . $axis . $bars . $targetLine . $labels
            . '</svg>'
            . '</div>';
    }

    /**
     * Y 軸スケールを見やすい切りの良い値へ丸める。
     * 例: 372 → 500、1230 → 2000、89 → 100。
     */
    private static function niceCeil(float $value): float
    {
        if ($value <= 0) {
            return 100.0;
        }
        $magnitude = (float) pow(10, (int) floor(log10($value)));
        $normalized = $value / $magnitude;
        foreach ([1.0, 2.0, 2.5, 5.0, 10.0] as $step) {
            if ($normalized <= $step) {
                return $step * $magnitude;
            }
        }
        return 10.0 * $magnitude;
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
     * 担当者ドロップダウンの値（'self' / 'all' / 数値）を
     * 一覧画面で使う staff_id 文字列に解決する。
     * 'all' / '' は空文字（＝フィルタを付けない意）を返す。
     */
    private static function resolveStaffIdFromUserParam(string $userParam, int $loginUserId): string
    {
        if ($userParam === 'self') {
            return (string) $loginUserId;
        }
        if ($userParam === 'all' || $userParam === '') {
            return '';
        }
        return $userParam;
    }

    /**
     * URL にクエリパラメータを追記する。AppConfig::routeUrl() は "?route=..." を返す前提。
     *
     * @param array<string, string> $params
     */
    private static function appendQuery(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        return $url . $sep . implode('&', $parts);
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
            . '<div class="activity-card-foot"><a href="' . Layout::escape($activityDailyUrl) . '" class="activity-link">日報ビューへ</a></div>'
            . '</div>';
    }
}
