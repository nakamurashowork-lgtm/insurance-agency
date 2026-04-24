<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class Layout
{
    /**
     * @param array<string, mixed> $options
     */
    public static function render(string $title, string $content, array $options = []): string
    {
        $safeTitle = self::escape($title);
        $headerHtml = self::renderHeader($options);
        $breadcrumbsHtml = self::renderBreadcrumbs($options);

        return '<!doctype html>'
            . '<html lang="ja">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $safeTitle . '</title>'
            . '<style>'
            . ':root{'
            . '--bg-primary:#ffffff;--bg-secondary:#f4f3ee;--bg-tertiary:#eceae3;'
            . '--bg-info:#e6f1fb;--bg-success:#eaf3de;--bg-warning:#faeeda;--bg-danger:#fcebeb;'
            . '--text-primary:#1a1a18;--text-secondary:#6b6b67;--text-info:#185fa5;'
            . '--text-success:#3b6d11;--text-warning:#854f0b;--text-danger:#a32d2d;'
            . '--border-light:rgba(0,0,0,0.10);--border-medium:rgba(0,0,0,0.18);'
            . '--border-info:#378add;--border-success:#639922;--border-warning:#ba7517;--border-danger:#e24b4a;'
            . '--text-heading:#102a43;--text-label:#334e68;--text-hint:#486581;--text-muted-cool:#6b7c89;'
            . '--bg-subtle:#f8fbfc;--bg-hover-cool:#eef6f7;--bg-active-cool:#dfeff2;'
            . '--border-cool:#d9e2ec;--border-cool-accent:#a7c1c8;--border-cool-muted:#bfd8dd;'
            . '--bg-info-hover:#d3e8f8;--bg-danger-hover:#f9d9d9;--bg-hover-warm:#f8f7f3;'
            . '--bg-info-solid:#2a6db7;--bg-info-solid-hover:#1a5b9e;'
            . '--space-xs:4px;--space-sm:8px;--space-md:12px;--space-base:16px;--space-lg:20px;--space-xl:24px;--space-2xl:32px;'
            . '--radius-md:8px;--radius-lg:12px;'
            . '--radius-sm:6px;--radius-xl:18px;'
            . '--shadow-card:0 1px 2px rgba(16,42,67,0.04);--shadow-card-hover:0 4px 10px rgba(16,42,67,0.08);'
            . '--icon-size-sm:32px;--icon-size-md:40px;'
            . '--bg-icon-danger:rgba(226,75,74,0.18);--bg-icon-warning:rgba(186,117,23,0.18);'
            . '--bg-icon-info:rgba(42,115,184,0.15);--bg-icon-success:rgba(99,153,34,0.18);'
            . '--chart-bar-current:#2a73b8;--chart-bar-previous:#bfd4e6;'
            . '--chart-bar-life:#c28a2a;--chart-bar-life-previous:#e8d2a1;'
            . '--brand-grad-from:#1f5b9e;--brand-grad-to:#2a73b8;'
            . '--drawer-width:84%;--drawer-max-width:320px;'
            . '}'
            . '*{box-sizing:border-box;}'
            . 'body{margin:0;font-size:14px;font-family:\'Noto Sans JP\',\'Hiragino Kaku Gothic ProN\',Meiryo,sans-serif;background:var(--bg-secondary);color:var(--text-primary);}'
            . '.app-shell{max-width:1280px;margin:0 auto;padding:20px 16px 32px;}'
            . '.page-container{max-width:1280px;margin:0 auto;width:100%;min-width:0;}'
            . '.page-main{margin-top:18px;}'
            . '.site-header{position:sticky;top:0;z-index:100;background:var(--bg-primary);border-bottom:0.5px solid var(--border-medium);box-shadow:0 1px 3px rgba(0,0,0,0.06);}'
            . '.site-header-inner{max-width:1280px;margin:0 auto;padding:0 20px;display:flex;align-items:center;height:50px;gap:0;}'
            // ハンバーガー（モバイル時のみ表示） -------------------------------
            . '.hamburger{appearance:none;border:none;background:transparent;width:40px;height:40px;display:none;align-items:center;justify-content:center;color:var(--text-heading);cursor:pointer;border-radius:8px;padding:0;margin-right:8px;flex:0 0 auto;}'
            . '.hamburger:hover{background:var(--bg-hover-cool);}'
            . '.hamburger:focus-visible{outline:2px solid var(--border-info);outline-offset:2px;}'
            . '.hamburger svg{width:22px;height:22px;display:block;}'
            // ブランド（ロゴ箱 + タイトル + テナント） ---------------------------
            . '.brand{display:flex;align-items:center;gap:10px;color:var(--text-primary);text-decoration:none;min-width:0;margin-right:24px;flex:0 0 auto;}'
            . '.brand-mark{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--brand-grad-from) 0%,var(--brand-grad-to) 100%);display:grid;place-items:center;color:#fff;font-weight:700;font-size:14px;box-shadow:inset 0 -1px 0 rgba(0,0,0,0.2),0 1px 2px rgba(31,91,158,0.25);flex:0 0 auto;}'
            . '.brand-text{display:flex;flex-direction:column;line-height:1.15;min-width:0;}'
            . '.brand-title{font-size:14px;font-weight:700;color:var(--text-heading);letter-spacing:-0.2px;white-space:nowrap;}'
            . '.brand-tenant{font-size:11px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}'
            // メインナビ（PC） ---------------------------------------------------
            . '.header-primary{display:flex;align-items:center;min-width:0;flex:1 1 auto;}'
            . '.main-nav{display:flex;align-items:center;min-width:0;}'
            . '.nav-spacer{flex:1;}'
            . '.main-nav a{display:inline-flex;align-items:center;height:50px;padding:0 14px;color:var(--text-secondary);text-decoration:none;font-size:13px;font-weight:400;border-bottom:2.5px solid transparent;white-space:nowrap;transition:color 0.15s,border-color 0.15s;}'
            . '.main-nav a:hover{color:var(--text-primary);}'
            . '.main-nav a.is-active{color:var(--text-info);border-bottom-color:var(--border-info);font-weight:500;}'
            // ヘッダー右ツール（PC） ---------------------------------------------
            . '.header-tools{display:flex;align-items:center;gap:12px;margin-left:auto;flex:0 0 auto;}'
            . '.user-meta{display:flex;flex-direction:column;align-items:flex-end;min-width:0;font-size:12px;color:var(--text-secondary);}'
            . '.user-meta strong{font-size:13px;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}'
            // ドロワー（モバイル時のみ表示） -------------------------------------
            . '.drawer-scrim{position:fixed;inset:0;background:rgba(16,42,67,0.48);opacity:0;pointer-events:none;transition:opacity .18s ease;z-index:90;}'
            . '.drawer-scrim.open{opacity:1;pointer-events:auto;}'
            . '.drawer{position:fixed;top:0;left:0;bottom:0;width:var(--drawer-width);max-width:var(--drawer-max-width);background:var(--bg-primary);transform:translateX(-102%);transition:transform .22s cubic-bezier(.2,.7,.2,1);z-index:100;box-shadow:8px 0 32px rgba(16,42,67,0.18);display:flex;flex-direction:column;}'
            . '.drawer.open{transform:translateX(0);}'
            . '.drawer-head{padding:18px 18px 14px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:10px;}'
            . '.drawer-user{flex:1;min-width:0;}'
            . '.drawer-user-name{font-weight:700;font-size:15px;color:var(--text-heading);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}'
            . '.drawer-user-tenant{font-size:12px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}'
            . '.drawer-close{appearance:none;border:none;background:transparent;width:36px;height:36px;font-size:24px;line-height:1;color:var(--text-muted-cool);cursor:pointer;border-radius:8px;flex:0 0 auto;padding:0;}'
            . '.drawer-close:hover{background:var(--bg-hover-cool);color:var(--text-heading);}'
            . '.drawer-nav{flex:1;overflow-y:auto;padding:10px 10px 16px;}'
            . '.drawer-section-label{font-size:11px;font-weight:600;color:var(--text-muted-cool);text-transform:uppercase;letter-spacing:0.6px;padding:14px 10px 6px;}'
            . '.drawer-link{display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;color:var(--text-label);font-weight:500;font-size:15px;text-decoration:none;}'
            . '.drawer-link:hover{background:var(--bg-hover-cool);}'
            . '.drawer-link.active{background:var(--bg-active-cool);color:var(--text-info);font-weight:700;}'
            . '.drawer-foot{padding:12px 16px 16px;border-top:1px solid var(--border-light);}'
            . '.drawer-foot .btn{width:100%;}'
            // PC/モバイル切替ヘルパー -------------------------------------------
            . '.desktop-only{display:flex;}'
            . '.breadcrumbs{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 14px;list-style:none;padding:0;color:var(--text-secondary);font-size:13px;}'
            . '.breadcrumbs a{color:var(--text-secondary);text-decoration:none;}'
            . '.breadcrumbs a:hover{color:var(--text-info);text-decoration:underline;}'
            . '.breadcrumbs li+li::before{content:"/";margin-right:8px;color:var(--text-muted-cool);}'
            . '.card{background:var(--bg-primary);border:0.5px solid var(--border-light);border-radius:var(--radius-lg);padding:16px 18px;margin-bottom:16px;}'
            . '.title{font-size:18px;font-weight:500;margin:0 0 12px;}'
            . '.muted{color:var(--text-hint);font-size:14px;}'
            . '.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}'
            . '.btn{display:inline-block;background:var(--bg-primary);color:var(--text-primary);text-decoration:none;padding:6px 14px;border-radius:var(--radius-md);font-size:12px;font-weight:500;border:0.5px solid var(--border-medium);cursor:pointer;transition:background 0.12s;white-space:nowrap;}'
            . '.btn:hover{background:var(--bg-secondary);}'
            . '.btn:disabled,.btn[aria-busy="true"]{opacity:0.6;cursor:not-allowed;}'
            . '.btn-primary{background:var(--bg-info-solid);color:#ffffff;border-color:var(--bg-info-solid);}'
            . '.btn-primary:hover{background:var(--bg-info-solid-hover);border-color:var(--bg-info-solid-hover);}'
            . '.btn-primary:focus-visible{outline:2px solid #ffffff;outline-offset:-2px;box-shadow:0 0 0 4px var(--bg-info-solid);}'
            . '.btn-large{padding:12px 24px;font-size:16px;width:100%;}'
            . '.btn-secondary{background:var(--bg-tertiary);color:var(--text-secondary);border-color:var(--border-medium);}'
            . '.btn-aux{background:var(--bg-tertiary);color:var(--text-secondary);}'
            . '.btn-danger{background:var(--bg-danger);color:var(--text-danger);border-color:var(--border-danger);}'
            . '.btn-danger:hover{background:var(--bg-danger-hover);}'
            . '.btn-icon-delete{background:none;border:none;cursor:pointer;font-size:14px;padding:0;width:44px;height:44px;display:inline-flex;align-items:center;justify-content:center;opacity:0.4;line-height:1;vertical-align:middle;}'
            . '.list-table .btn-icon-delete{width:28px;height:28px;}'
            . '.btn-icon-delete:hover{opacity:1;}'
            . '.btn-ghost{background:var(--bg-primary);color:var(--text-primary);border:0.5px solid var(--border-medium);}'
            . '.btn-small{padding:6px 12px;font-size:12px;}'
            . 'button:disabled,input:disabled,select:disabled,textarea:disabled,[aria-disabled="true"]{opacity:0.5;cursor:not-allowed !important;}'
            . ':focus-visible{outline:2px solid var(--border-info);outline-offset:2px;}'
            . '.btn:focus-visible,a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,summary:focus-visible,details:focus-visible{outline:2px solid var(--border-info);outline-offset:2px;box-shadow:0 0 0 4px rgba(55,138,221,0.25);}'
            . '.text-link{color:var(--text-info);text-decoration:underline;font-weight:500;}'
            . '.text-link:hover{color:var(--text-info);}'
            . '.notice{padding:10px 12px;background:var(--bg-success);border:0.5px solid var(--border-success);color:var(--text-success);border-radius:8px;margin-bottom:12px;}'
            . '.error{padding:10px 12px;background:var(--bg-danger);border:0.5px solid var(--border-danger);color:var(--text-danger);border-radius:8px;margin-bottom:12px;}'
            . '.alert{padding:14px;border-radius:8px;margin-bottom:16px;display:flex;gap:12px;align-items:flex-start;}'
            . '.alert-error{background:var(--bg-danger);border:0.5px solid var(--border-danger);color:var(--text-danger);}'
            . '.alert-icon{font-weight:700;font-size:16px;flex-shrink:0;margin-top:2px;}'
            . '.alert-content{flex:1;min-width:0;}'
            . '.alert-title{font-weight:700;margin-bottom:4px;}'
            . '.alert-message{font-size:14px;line-height:1.5;}'
            . '.alert-warn{padding:10px 16px;border-radius:var(--radius-md);font-size:13px;margin-bottom:14px;background:var(--bg-warning);color:var(--text-warning);border:0.5px solid var(--border-warning);display:block;}'
            . '.badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:500;line-height:1.4;vertical-align:middle;}'
            . '.list-table td .badge{line-height:1;padding:3px 9px;}'
            . '.badge-gray{background:var(--bg-secondary);color:var(--text-primary);}'
            . '.badge-info{background:var(--bg-info);color:var(--text-info);}'
            . '.badge-warn{background:var(--bg-warning);color:var(--text-warning);}'
            . '.badge-success{background:var(--bg-success);color:var(--text-success);}'
            . '.badge-danger{background:var(--bg-danger);color:var(--text-danger);}'
            . '.badge-pri{display:inline-flex;align-items:center;gap:5px;padding:3px 10px 3px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.4;}'
            . '.badge-pri::before{content:"";display:inline-block;width:6px;height:6px;border-radius:50%;background:currentColor;}'
            . '.badge-pri-high{background:#fde8e8;color:#c53030;}'
            . '.badge-pri-mid{background:#fff1d6;color:#c26a00;}'
            . '.badge-pri-low{background:#edf0f3;color:#6b7280;}'
            . 'dialog{border:none;padding:0;max-width:none;background:transparent;}'
            . 'dialog::backdrop{background:rgba(0,0,0,0.4);}'
            . '.dlg-title{font-size:14px;font-weight:600;margin-bottom:16px;}'
            . '.dlg-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;}'
            . '.tab-bar{display:flex;border-bottom:0.5px solid var(--border-medium);margin-bottom:16px;gap:0;}'
            . '.tab{padding:9px 18px;font-size:13px;cursor:pointer;color:var(--text-secondary);border-bottom:2px solid transparent;white-space:nowrap;transition:color 0.12s,border-color 0.12s;}'
            . '.tab:hover{color:var(--text-primary);}'
            . '.tab.active{color:var(--text-info);border-bottom-color:var(--border-info);font-weight:500;}'
            . '.divider{height:0.5px;background:var(--border-light);margin:14px 0;}'
            . '.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px;}'
            . '.metric-grid-5{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;}'
            . '.renewal-status-metrics{padding:14px 18px;margin-bottom:0;}'
            . '.metric{background:var(--bg-secondary);border-radius:var(--radius-md);padding:12px 16px;}'
            . '.metric-label{font-size:11px;color:var(--text-secondary);margin-bottom:4px;}'
            . '.metric-value{font-size:22px;font-weight:500;color:var(--text-primary);line-height:1.2;}'
            . '.metric-sub{font-size:11px;color:var(--text-secondary);margin-top:3px;}'
            . '.metric-na{font-size:16px;color:var(--text-secondary);}'
            . '.two-col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:14px;}'
            . '.page-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;}'
            . '.page-header .title{margin:0;}'
            . '.page-header-meta{font-size:12px;color:var(--text-secondary);white-space:nowrap;}'
            . '.card-footer-link{text-align:right;margin-top:12px;}'
            . '.section-title{font-size:12px;font-weight:500;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-secondary);margin:0;}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}'
            . '.nav-item{border:1px solid var(--border-cool);border-radius:8px;padding:12px;background:var(--bg-subtle);}'
            . '.nav-item-primary{background:var(--bg-subtle);border-color:var(--border-cool-muted);}'
            . '.helper{border-left:4px solid var(--text-label);padding-left:12px;}'
            . '.helper-soft{background:var(--bg-subtle);border-color:var(--border-cool);}'
            . '.nav-item-helper{background:var(--bg-subtle);border-color:var(--border-cool);}'
            . '.details-panel summary,.helper-details summary{cursor:pointer;font-weight:700;list-style:none;}'
            . '.details-panel summary::-webkit-details-marker,.helper-details summary::-webkit-details-marker{display:none;}'
            . '.details-panel summary{margin-bottom:12px;color:var(--text-heading);}'
            . '.helper-details summary{color:var(--text-label);}'
            . '.helper-details[open] summary{margin-bottom:12px;}'
            . '.modal-dialog{width:min(640px,calc(100vw - 24px));border:none;border-radius:14px;padding:18px 18px 16px;background:var(--bg-primary);box-shadow:0 20px 48px rgba(17,33,49,0.24);}'
            . '.modal-dialog-wide{width:min(1200px,calc(100vw - 24px));}'
            . '.modal-dialog-xl{width:min(1100px,calc(100vw - 24px));}'
            . '.modal-dialog::backdrop{background:rgba(16,42,67,0.46);}'
            . '.modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;}'
            . '.modal-head h2{margin:0;font-size:20px;}'
            . '.modal-close-form{margin:0;display:flex;justify-content:flex-end;}'
            . '.modal-close{appearance:none;border:1px solid var(--border-light);background:var(--bg-primary);color:var(--text-primary);border-radius:999px;width:44px;height:44px;font-size:22px;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;}'
            . '.modal-close:hover{background:var(--bg-hover-cool);border-color:var(--border-cool-accent);}'
            . '.customer-create-form{display:grid;gap:16px;margin-top:8px;}'
            . '.customer-create-form .modal-title{margin:0 40px 2px 0;font-size:24px;font-weight:700;line-height:1.25;}'
            . '.customer-create-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;align-items:start;}'
            . '.customer-create-grid .form-field{display:grid;gap:6px;min-width:0;}'
            . '.customer-create-grid .form-field-label{font-size:13px;font-weight:700;color:var(--text-label);line-height:1.35;}'
            . '.customer-create-grid .form-field--required .form-field-label::after{content:" *";color:var(--text-danger);font-weight:700;}'
            . '.customer-create-grid .form-field--full{grid-column:1 / -1;}'
            . '.customer-create-grid .form-field--spacer{grid-column:span 1;}'
            . '.customer-create-grid input,.customer-create-grid select,.customer-create-grid textarea{width:100%;min-width:0;padding:10px 12px;border:1px solid var(--border-cool);border-radius:8px;font-size:14px;font-family:inherit;line-height:1.4;background:var(--bg-primary);color:var(--text-heading);}'
            . '.customer-create-grid input:focus,.customer-create-grid select:focus,.customer-create-grid textarea:focus{outline:none;border-color:var(--border-info);box-shadow:0 0 0 2px rgba(55,138,221,0.25);}'
            . '.customer-create-grid textarea{min-height:108px;max-height:220px;resize:vertical;}'
            . '.dialog-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding-top:10px;border-top:1px solid var(--border-cool);background:var(--bg-primary);}'
            . '.dialog-actions .btn{min-width:112px;min-height:44px;border-radius:10px;}'
            . '.modal-dialog .list-filter-field{margin-top:8px;}'
            . '.modal-help{margin-top:10px;border:1px solid var(--border-light);border-radius:10px;padding:10px 12px;background:var(--bg-subtle);}'
            . '.modal-help summary{margin:0;font-size:13px;}'
            . '.modal-help p{margin:10px 0 0;}'
            . '.modal-result{margin:10px 0 12px;padding:10px 12px;border-radius:10px;background:var(--bg-subtle);border:1px solid var(--border-light);}'
            . '.modal-result p{margin:0;}'
            . '.modal-result p + p{margin-top:4px;}'
            . '.required-mark{color:var(--text-danger);}'
            . '.modal-form-section{padding-top:10px;margin-top:10px;border-top:1px solid var(--border-light);}'
            . '.modal-form-section:first-of-type{border-top:none;padding-top:0;margin-top:0;}'
            . '.modal-form-title{margin:0 0 8px;font-size:15px;color:var(--text-heading);}'
            . '.modal-form-grid .list-filter-field{grid-column:span 6;}'
            . '.modal-form-wide,.modal-form-grid .modal-form-wide{grid-column:span 12;}'
            . '.modal-form-actions{margin-top:16px;justify-content:flex-end;}'
            . '.list-page-frame{display:grid;gap:16px;}'
            . '.list-page-frame > .card,.list-page-frame > .list-page-header{margin-bottom:0;}'
            . '.list-page-header{margin-bottom:0;display:flex;align-items:center;justify-content:space-between;gap:12px;}'
            . '.list-page-header .title{margin-bottom:0;}'
            . '.list-page-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-left:auto;flex-wrap:wrap;min-width:0;max-width:100%;}'
            . '.list-filter-card{padding:0;overflow:hidden;}'
            . '.list-filter-toggle{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;margin:0;background:var(--bg-subtle);border-bottom:1px solid transparent;color:var(--text-info);font-size:13px;}'
            . '.list-filter-card[open] .list-filter-toggle{border-bottom-color:var(--border-light);}'
            . '.list-filter-toggle-label.is-open{display:none;}'
            . '.list-filter-card[open] .list-filter-toggle-label.is-open{display:inline;}'
            . '.list-filter-card[open] .list-filter-toggle-label.is-closed{display:none;}'
            . '.list-filter-card form{padding:18px 20px 20px;}'
            . '.list-filter-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px 14px;}'
            . '.list-filter-field{grid-column:span 4;display:grid;gap:6px;font-size:13px;font-weight:700;color:var(--text-label);}'
            . '.list-filter-field.is-date{grid-column:span 6;}'
            . '.list-filter-field input,.list-filter-field select,.list-filter-field textarea{width:100%;min-width:0;padding:6px 10px;border:0.5px solid var(--border-medium);border-radius:var(--radius-md);font-size:12px;font-family:inherit;background:var(--bg-primary);color:var(--text-primary);}'
            . '.list-filter-field input:focus,.list-filter-field select:focus,.list-filter-field textarea:focus{outline:none;border-color:var(--border-info);box-shadow:0 0 0 2px rgba(55,138,221,0.25);}'
            . '.list-filter-field textarea{resize:vertical;line-height:1.5;}'
            . '.list-filter-actions{margin-top:16px;}'
            . '.list-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px;}'
            . '.list-toolbar-bottom{margin-top:12px;margin-bottom:0;padding-top:12px;border-top:1px solid var(--border-light);}'
            . '.list-summary{display:grid;gap:4px;}'
            . '.list-summary p{margin:0;}'
            . '.list-toolbar-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;margin-left:auto;min-width:0;max-width:100%;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-toolbar-actions{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,auto);align-items:center;column-gap:12px;row-gap:8px;width:min(100%,680px);min-width:0;}'
            . '.list-toolbar-bottom .list-toolbar-actions{width:100%;min-width:0;}'
            . '.list-sort-summary{margin:0;white-space:nowrap;min-width:142px;text-align:right;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-sort-summary{justify-self:end;}'
            . '.list-per-page-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0;justify-content:flex-start;min-width:132px;max-width:100%;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-per-page-form{justify-self:start;}'
            . '.list-select-inline{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--text-label);justify-content:flex-start;min-width:0;max-width:100%;flex-wrap:wrap;}'
            . '.list-select-inline > span{display:inline-block;min-width:56px;text-align:left;}'
            . '.list-select-inline select{min-width:80px;width:80px;}'
            . '.list-pager{display:flex;align-items:center;gap:4px;flex-wrap:wrap;justify-content:flex-end;min-width:0;max-width:100%;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-pager{justify-self:end;}'
            . '.list-toolbar-bottom .list-pager{min-width:0;}'
            // wireframe .pager button 準拠: 32x32、6px 角丸、アクティブは bg-info-solid
            . '.list-pager-link{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;border:1px solid var(--border-cool);border-radius:6px;background:var(--bg-primary);color:var(--text-label);text-decoration:none;font-weight:600;font-size:12px;font-variant-numeric:tabular-nums;white-space:nowrap;transition:background .12s,border-color .12s,color .12s;}'
            . '.list-pager-link:hover{background:var(--bg-hover-cool);border-color:var(--border-cool-accent);color:var(--text-heading);}'
            . '.list-pager-link.is-current{background:var(--bg-info-solid);border-color:var(--bg-info-solid);color:#fff;}'
            // モバイルはタップ領域確保（WCAG AA 24px minimum を上回る 40x40）
            . '@media(max-width:767px){.list-pager-link{min-width:40px;height:40px;padding:0 12px;font-size:13px;}}'
            // 表示件数セグメント（wireframe .seg 準拠）
            . '.list-per-page{display:inline-flex;align-items:center;gap:8px;min-width:0;}'
            . '.list-per-page-label{font-size:11px;font-weight:600;color:var(--text-muted-cool);text-transform:uppercase;letter-spacing:0.5px;}'
            . '.list-per-page-seg{display:inline-flex;background:var(--bg-tertiary);border-radius:10px;padding:3px;gap:2px;}'
            . '.list-per-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:40px;padding:6px 12px;border-radius:8px;background:transparent;color:var(--text-secondary);font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background .12s,color .12s;font-variant-numeric:tabular-nums;}'
            . '.list-per-page-btn:hover{color:var(--text-heading);}'
            . '.list-per-page-btn.is-active{background:var(--bg-primary);color:var(--text-heading);box-shadow:0 1px 2px rgba(16,42,67,0.08);cursor:default;}'
            // wireframe .data-table 準拠: bg-subtle thead / border-light 行境界 / hover bg-hover-cool
            . '.list-table{font-size:13px;border-collapse:collapse;width:100%;}'
            . '.list-table th,.list-table td{padding:10px 14px;line-height:1.4;vertical-align:middle;}'
            . '.list-table thead th{font-size:12px;font-weight:700;color:var(--text-label);background:var(--bg-subtle);border-bottom:1px solid var(--border-cool);white-space:nowrap;letter-spacing:0.2px;text-align:left;}'
            . '.list-table tbody td{border-bottom:1px solid var(--border-light);color:var(--text-primary);}'
            . '.list-table tbody tr:last-child td{border-bottom:none;}'
            . '.list-table tbody tr{transition:background .08s ease;}'
            . '.list-table tbody tr:hover td{background:var(--bg-hover-cool);}'
            . '.list-table tbody tr.is-completed-row td{background:var(--bg-subtle);color:var(--text-muted-cool);}'
            . '.list-table tbody tr.is-completed-row:hover td{background:var(--bg-hover-cool);}'
            . '.list-table tbody tr.is-completed-row .muted,.list-table tbody tr.is-completed-row .list-row-tertiary{color:var(--text-muted-cool);}'
            . '.list-table tbody tr.is-completed-row .text-link{color:var(--text-hint);}'
            . '.list-table tbody tr.is-completed-row .badge{background:var(--bg-secondary);color:var(--text-secondary);}'
            . '.list-row-stack{gap:2px;}'
            . '.list-row-primary{font-size:14px;line-height:1.35;min-width:0;overflow-wrap:anywhere;word-break:break-word;}'
            . '.list-row-secondary{font-size:13px;color:var(--text-label);line-height:1.3;}'
            . '.list-policy-text{display:block;max-width:100%;min-width:0;font-size:13px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}'
            . '.list-row-tertiary{font-size:12px;color:var(--text-muted-cool);line-height:1.3;min-width:0;overflow-wrap:anywhere;word-break:break-word;}'
            . '.list-table-renewal col.list-col-policy{width:160px;}'
            . '.list-table-renewal col.list-col-customer{width:auto;}'
            . '.list-table-renewal col.list-col-product{width:160px;}'
            . '.list-table-renewal col.list-col-date{width:104px;}'
            . '.list-table-renewal col.list-col-early{width:112px;}'
            . '.list-table-renewal col.list-col-user{width:128px;}'
            . '.list-table-renewal col.list-col-status{width:128px;}'
            . '.list-table-accident col.list-col-customer{width:auto;}'
            . '.list-table-accident col.list-col-assigned{width:152px;}'
            . '.list-table-accident col.list-col-status{width:168px;}'
            . '.list-table-accident col.list-col-priority{width:76px;}'
            . '.list-table-accident col.list-col-date{width:104px;}'
            . '.list-table-accident col.list-col-reminder{width:128px;}'
            . '.list-table-accident col.list-col-action{width:44px;}'
            . '.list-table-scase col.list-col-name{width:auto;}'
            . '.list-table-scase col.list-col-rank{width:76px;}'
            . '.list-table-scase col.list-col-product{width:140px;}'
            . '.list-table-scase col.list-col-status{width:136px;}'
            . '.list-table-scase col.list-col-staff{width:128px;}'
            . '.list-table-scase col.list-col-action{width:44px;}'
            . '.list-table-activity col.list-col-date{width:100px;}'
            . '.list-table-activity col.list-col-customer{width:180px;}'
            . '.list-table-activity col.list-col-type{width:110px;}'
            . '.list-table-activity col.list-col-user{width:128px;}'
            . '.list-table-activity col.list-col-next{width:100px;}'
            . '.list-table-daily col.list-col-time{width:110px;}'
            . '.list-table-daily col.list-col-type{width:80px;}'
            . '.list-table-daily col.list-col-customer{width:18%;}'
            . '.list-table-daily col.list-col-next{width:110px;}'
            . '.list-table-daily col.list-col-status{width:60px;}'
            . '.list-table-daily col.list-col-action{width:44px;}'
            . '.list-table-sales col.list-col-date{width:96px;}'
            . '.list-table-sales col.list-col-staff{width:160px;}'
            . '.list-table-sales col.list-col-source{width:100px;}'
            . '.list-table-sales col.list-col-product{width:140px;}'
            . '.list-table-sales col.list-col-premium{width:100px;}'
            . '.list-table-sales col.list-col-action{width:40px;}'
            . '.list-table-cust-contract col.list-col-type{width:240px;}'
            . '.list-table-cust-contract col.list-col-status{width:160px;}'
            . '.list-table-cust-accident col.list-col-date{width:96px;}'
            . '.list-table-cust-accident col.list-col-product{width:240px;}'
            . '.list-table-cust-accident col.list-col-status{width:160px;}'
            . '.list-table-cust-activity col.list-col-date{width:90px;}'
            . '.list-table-cust-activity col.list-col-staff{width:240px;}'
            . '.list-table-cust-activity col.list-col-type{width:160px;}'
            . '.list-sort-link{display:inline-flex;align-items:center;gap:6px;color:inherit;text-decoration:none;font-weight:700;}'
            . '.list-sort-link:hover{color:var(--text-info);}'
            . '.list-sort-link.is-active{color:var(--text-info);}'
            . '.list-sort-indicator{font-size:11px;line-height:1;}'
            . '.priority-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.3;}'
            . '.priority-high{background:var(--bg-danger);color:var(--text-danger);}'
            . '.priority-medium{background:var(--bg-warning);color:var(--text-warning);}'
            . '.priority-low{background:var(--bg-info);color:var(--text-info);}'
            . '.priority-none{background:var(--bg-secondary);color:var(--text-hint);}'
            . '.table-wrap{overflow-x:auto;}'
            . '.tbl-wrap{overflow-x:auto;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{text-align:left;border-bottom:1px solid var(--border-cool);padding:8px;vertical-align:top;}'
            . '.table-fixed{table-layout:fixed;}'
            . '.table-spacious th,.table-spacious td{padding:12px 10px;}'
            . '.truncate{display:block;max-width:100%;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
            . '.cell-ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
            . '.cell-stack{display:flex;flex-direction:column;gap:4px;min-width:0;}'
            . '.cell-action{white-space:nowrap;text-align:right;}'
            . '.align-right{text-align:right;}'
            . '.summary-line{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}'
            . '.summary-line p{margin:0;}'
            . '.summary-count{font-weight:700;color:var(--text-heading);}'
            . '.tag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:var(--bg-hover-cool);color:var(--text-info);font-size:12px;font-weight:700;border:1px solid var(--border-cool-muted);}'
            . '.warning-text{color:var(--text-warning);font-weight:700;}'
            . '.detail-top{display:grid;grid-template-columns:minmax(0,0.95fr) minmax(320px,1.05fr);gap:16px;align-items:start;margin-bottom:16px;}'
            . '.detail-side{display:grid;gap:16px;}'
            . '.section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:8px;}'
            . '.section-head h1,.section-head h2,.section-head h3{margin:0;}'
            . '.meta-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px;}'
            . '.detail-section-title{font-size:12px;font-weight:500;color:var(--text-secondary);margin-bottom:10px;padding-bottom:5px;border-bottom:0.5px solid var(--border-light);text-transform:uppercase;letter-spacing:0.4px;}'
            . '.kv-list{display:grid;grid-template-columns:160px minmax(0,1fr);gap:10px 16px;margin:0;}'
            . '.kv-list dt{font-weight:700;color:var(--text-hint);}'
            . '.kv-list dd{margin:0;min-width:0;word-break:break-word;}'
            . '.kv{display:flex;gap:10px;margin-bottom:7px;font-size:13px;}'
            . '.kv-key{color:var(--text-secondary);min-width:96px;flex-shrink:0;}'
            . '.kv-val{color:var(--text-primary);}'
            . '.kv-link{color:var(--text-info);cursor:pointer;text-decoration:none;}'
            . '.kv-link:hover{text-decoration:underline;}'
            . '.form-hint{margin:0 0 10px;color:var(--text-hint);font-size:12px;line-height:1.45;}'
            . '.field-error{display:block;margin-top:4px;color:var(--text-danger);font-size:12px;font-weight:700;line-height:1.4;}'
            . '.input-error{border-color:var(--border-danger) !important;background:var(--bg-danger);}'
            . '.renewal-update-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:16px;}'
            . '.update-field{display:grid;gap:8px;}'
            . '.update-field label{display:block;font-size:13px;font-weight:700;color:var(--text-label);}'
            . '.update-field input[type="text"],.update-field input[type="date"],.update-field select,.update-field textarea{width:100%;min-width:0;padding:8px 10px;border:1px solid var(--border-cool);border-radius:8px;font-size:14px;font-family:inherit;background:var(--bg-primary);color:var(--text-heading);}'
            . '.update-field input[type="text"]:focus,.update-field input[type="date"]:focus,.update-field select:focus,.update-field textarea:focus{outline:none;border-color:var(--border-info);box-shadow:0 0 0 2px rgba(55,138,221,0.25);}'
            . '.update-field textarea{resize:vertical;line-height:1.5;}'
            . '.update-field-full{grid-column:span 2;}'
            . '.renewal-update-actions{display:flex;justify-content:flex-end;gap:8px;}'
            . '.status-timeline{margin-top:4px;}'
            . '.timeline-item{display:flex;gap:12px;margin-bottom:12px;font-size:13px;position:relative;}'
            . '.timeline-item:not(:last-child) .timeline-dot::after{content:"";position:absolute;left:3px;top:16px;width:1px;height:calc(100% - 4px);background:var(--border-light);}'
            . '.timeline-dot{width:8px;height:8px;border-radius:50%;background:var(--border-info);margin-top:5px;flex-shrink:0;position:relative;}'
            . '.timeline-dot.done{background:var(--border-success);}'
            . '.timeline-dot.pending{background:var(--border-light);border:1.5px solid var(--border-medium);}'
            . '.timeline-body{flex:1;}'
            . '.timeline-time{color:var(--text-secondary);font-size:11px;margin-top:1px;}'
            . '.form-row{margin-bottom:14px;}'
            . '.form-label{font-size:12px;color:var(--text-secondary);margin-bottom:5px;}'
            . '.form-input,.form-select{width:100%;padding:8px 11px;border:0.5px solid var(--border-medium);border-radius:var(--radius-md);font-size:13px;background:var(--bg-primary);color:var(--text-primary);font-family:inherit;}'
            . '.form-input:focus,.form-select:focus{outline:none;border-color:var(--border-info);box-shadow:0 0 0 2px rgba(55,138,221,0.25);}'
            . 'textarea.form-input{resize:none;}'
            . '.radio-group{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-top:2px;}'
            . '.radio-inline{display:inline-flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;user-select:none;white-space:nowrap;}'
            . '.radio-inline input[type="radio"]{cursor:pointer;}'
            . '.section-stack{display:grid;gap:16px;}'
            . '.split-columns{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;}'
            . '.panel-list{margin:0;padding:0;list-style:none;display:grid;gap:8px;}'
            . '.comment-item{list-style:none;margin:0;padding:10px 12px;border:1px solid var(--border-light);border-radius:10px;background:var(--bg-subtle);display:grid;gap:8px;}'
            . '.comment-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-size:12px;color:var(--text-hint);}'
            . '.comment-meta-text{font-weight:700;color:var(--text-label);overflow-wrap:anywhere;word-break:break-word;}'
            . '.comment-body{white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;line-height:1.5;color:var(--text-heading);}'
            . '.history-item{list-style:none;margin:0;padding:12px;border:1px solid var(--border-light);border-radius:10px;background:var(--bg-subtle);display:grid;gap:10px;}'
            . '.history-meta{display:flex;flex-wrap:wrap;gap:8px 14px;font-size:12px;color:var(--text-hint);}'
            . '.history-summary{font-size:13px;color:var(--text-heading);line-height:1.5;overflow-wrap:anywhere;word-break:break-word;}'
            . '.history-detail-table-wrap{overflow-x:auto;}'
            . '.history-detail-table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.history-detail-table th,.history-detail-table td{padding:7px 8px;border-bottom:1px solid var(--border-cool);vertical-align:top;font-size:13px;overflow-wrap:anywhere;word-break:break-word;}'
            . '.history-detail-table th{font-weight:700;color:var(--text-label);background:var(--bg-hover-cool);}'
            . '.contact-card{border:1px solid var(--border-light);border-radius:12px;padding:14px;background:var(--bg-subtle);display:grid;gap:6px;}'
            . '.contact-card-primary{background:var(--bg-hover-cool);border-color:var(--border-cool-muted);}'
            . '.section-note{margin:0;color:var(--text-secondary);font-size:13px;}'
            . '.details-compact{padding:0;overflow:hidden;}'
            . '.details-compact summary{padding:18px 20px;margin:0;display:flex;justify-content:space-between;align-items:center;}'
            . '.details-compact[open] summary{border-bottom:1px solid var(--border-light);margin-bottom:0;}'
            . '.details-compact-body{padding:18px 20px;}'
            . '.mobile-only{display:none;}'
            . '.login-card{max-width:480px;margin:48px auto 0;}'
            . '.login-header{margin-bottom:20px;text-align:center;}'
            . '.login-title{font-size:22px;font-weight:700;margin:0;color:var(--text-primary);}'
            . '.login-subtitle{font-size:16px;font-weight:700;margin:8px 0 0;color:var(--text-info);}'
            . '.login-description{color:var(--text-secondary);font-size:14px;line-height:1.6;text-align:center;margin:16px 0 24px;}'
            . '.login-actions{display:flex;flex-direction:column;gap:12px;margin-bottom:20px;}'
            . '.login-helper-text{color:var(--text-secondary);font-size:12px;line-height:1.6;text-align:center;margin:0;border-top:1px solid var(--border-light);padding-top:16px;}'
            // 一覧画面 共通コンポーネント（list-card / stripe / quick-filter-tabs）
            // wireframe `.list-card` 準拠: モバイル時のカード表示で利用
            . '.list-card-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:14px;}'
            . '.list-card{position:relative;background:var(--bg-primary);border:1px solid var(--border-light);border-radius:14px;padding:14px 14px 12px;box-shadow:var(--shadow-card);transition:border-color .15s,box-shadow .15s,transform .15s;}'
            . '.list-card:hover{border-color:var(--border-cool-accent);box-shadow:var(--shadow-card-hover);}'
            . '.list-card.completed{background:var(--bg-subtle);opacity:0.85;}'
            . '.list-card.completed .list-card-customer{color:var(--text-muted-cool);}'
            . '.list-card.with-stripe{padding-left:18px;}'
            . '.list-card-stripe{position:absolute;left:0;top:14px;bottom:14px;width:3px;border-radius:0 3px 3px 0;background:var(--border-cool-muted);}'
            . '.stripe-danger{background:var(--border-danger);}'
            . '.stripe-warning{background:var(--border-warning);}'
            . '.stripe-info{background:var(--border-info);}'
            . '.stripe-success{background:var(--border-success);}'
            . '.stripe-gray{background:var(--border-cool-muted);}'
            . '.list-card-link{display:block;text-decoration:none;color:inherit;}'
            . '.list-card-top{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px;}'
            . '.list-card-top-left{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;}'
            . '.list-card-top-right{display:inline-flex;align-items:center;gap:6px;}'
            . '.list-card-product{font-size:11px;font-weight:600;color:var(--text-muted-cool);text-transform:uppercase;letter-spacing:0.4px;}'
            . '.list-card-summary{font-size:12px;color:var(--text-secondary);line-height:1.5;margin:4px 0 8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}'
            . '.list-card-customer{font-size:16px;font-weight:700;color:var(--text-heading);line-height:1.3;margin-bottom:3px;letter-spacing:-0.2px;}'
            . '.list-card-policy{font-size:12px;color:var(--text-secondary);letter-spacing:0.2px;font-variant-numeric:tabular-nums;}'
            . '.list-card-meta{display:flex;align-items:center;gap:14px;margin-top:10px;padding-top:10px;border-top:1px dashed var(--border-cool);font-size:12px;color:var(--text-label);flex-wrap:wrap;}'
            . '.list-card-meta-item{display:inline-flex;align-items:center;gap:5px;min-height:18px;}'
            . '.list-card-meta-label{color:var(--text-muted-cool);font-size:11px;}'
            . '.list-card-meta-icon{flex:0 0 13px;color:var(--text-muted-cool);}'
            . '.list-card-meta-value{font-weight:500;font-variant-numeric:tabular-nums;}'
            . '.list-card-meta-value.is-overdue{color:var(--text-danger);font-weight:600;}'
            . '.list-card-meta-value.is-urgent{color:var(--text-warning);font-weight:600;}'
            // 一覧画面ツールバー（wireframe 準拠: 1 行レイアウト 検索バー + 絞込 + CSV取込）
            . '.list-toolbar-bar{display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;}'
            . '.list-toolbar-left{display:flex;align-items:center;gap:8px;min-width:0;flex:0 1 auto;}'
            . '.list-toolbar-right{display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap;}'
            . '.list-toolbar-search-form{flex:0 1 380px;min-width:0;max-width:420px;margin:0;}'
            . '@media(max-width:767px){.list-toolbar-left{width:100%;flex:1 1 auto;}.list-toolbar-search-form{flex:1 1 auto;max-width:none;}.list-toolbar-right{width:100%;margin-left:0;}.list-toolbar-right .btn,.list-toolbar-right .filter-btn{flex:1 1 auto;justify-content:center;}}'
            // wireframe .search-field 準拠: 検索フィールドは form の全幅で伸縮
            . '.list-toolbar-search{display:flex;align-items:center;gap:8px;background:var(--bg-primary);border:1px solid var(--border-cool);border-radius:12px;padding:10px 14px;min-height:44px;min-width:0;transition:border-color .12s,box-shadow .12s;}'
            . '.list-toolbar-search:focus-within{border-color:var(--border-info);box-shadow:0 0 0 3px rgba(55,138,221,0.22);}'
            . '.list-toolbar-search-icon{color:var(--text-muted-cool);flex:0 0 18px;display:inline-flex;}'
            . '.list-toolbar-search-icon svg{width:18px;height:18px;}'
            . '.list-toolbar-search input{flex:1;border:none;outline:none;background:transparent;font-size:14px;color:var(--text-primary);font-family:inherit;min-width:0;padding:0;}'
            . '.list-toolbar-search input::placeholder{color:var(--text-muted-cool);}'
            . '.list-toolbar-search input:focus,.list-toolbar-search input:focus-visible{outline:none !important;box-shadow:none !important;}'
            . '.list-toolbar-bar .btn{height:44px;padding:0 16px;}'
            . '.filter-btn{display:inline-flex;align-items:center;gap:6px;height:44px;padding:0 14px;border:1px solid var(--border-cool);background:var(--bg-primary);border-radius:12px;color:var(--text-label);font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;transition:border-color .12s,background .12s,color .12s;}'
            . '.filter-btn:hover{border-color:var(--border-cool-accent);color:var(--text-heading);}'
            . '.filter-btn svg{width:16px;height:16px;}'
            . '.filter-btn.has-active{border-color:var(--border-info);color:var(--text-info);background:var(--bg-info);}'
            . '.filter-btn-count{display:inline-grid;place-items:center;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--text-info);color:#fff;font-size:11px;font-weight:700;line-height:1;}'
            // CSV取込ボタン（絞込ボタンと並置、中立色）
            . '.filter-btn-csv{color:var(--text-label);}'
            . '.filter-btn-csv:hover{border-color:var(--border-cool-accent);color:var(--text-heading);background:var(--bg-subtle);}'
            // フィルタダイアログ内のフォームグリッド
            . '.filter-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;margin-top:8px;}'
            . '.filter-form-field{display:grid;gap:6px;min-width:0;}'
            . '.filter-form-field--full{grid-column:1 / -1;}'
            . '.filter-form-field-label{font-size:13px;font-weight:600;color:var(--text-label);}'
            . '.filter-form-field input,.filter-form-field select{width:100%;min-width:0;padding:10px 12px;border:1px solid var(--border-cool);border-radius:8px;font-size:14px;font-family:inherit;line-height:1.4;background:var(--bg-primary);color:var(--text-heading);min-height:44px;box-sizing:border-box;}'
            . '.filter-form-field input:focus,.filter-form-field select:focus{outline:none;border-color:var(--border-info);box-shadow:0 0 0 2px rgba(55,138,221,0.25);}'
            . '@media(max-width:480px){.filter-form-grid{grid-template-columns:1fr;}}'
            // CSV 取込モーダル: ドラッグ&ドロップエリア + 履歴 + 仕様折りたたみ -----
            . '.csv-dropzone{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:28px 20px;border:2px dashed var(--border-cool);border-radius:12px;background:var(--bg-subtle);cursor:pointer;transition:border-color .15s,background .15s;text-align:center;}'
            . '.csv-dropzone:hover{border-color:var(--border-cool-accent);background:var(--bg-hover-cool);}'
            . '.csv-dropzone.is-dragover{border-color:var(--border-info);background:var(--bg-info);border-style:solid;}'
            . '.csv-dropzone.has-file{border-color:var(--border-success);background:var(--bg-success);border-style:solid;}'
            . '.csv-dropzone input[type="file"]{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;}'
            . '.csv-dropzone-icon{color:var(--text-muted-cool);display:inline-flex;}'
            . '.csv-dropzone-icon svg{width:40px;height:40px;}'
            . '.csv-dropzone.is-dragover .csv-dropzone-icon,.csv-dropzone.has-file .csv-dropzone-icon{color:var(--text-info);}'
            . '.csv-dropzone.has-file .csv-dropzone-icon{color:var(--text-success);}'
            . '.csv-dropzone-text{display:flex;flex-direction:column;gap:4px;}'
            . '.csv-dropzone-title{font-size:14px;font-weight:700;color:var(--text-heading);}'
            . '.csv-dropzone-sub{font-size:12px;color:var(--text-secondary);}'
            . '.csv-dropzone-selected{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:var(--bg-primary);border:1px solid var(--border-success);color:var(--text-success);font-size:13px;font-weight:600;max-width:100%;}'
            . '.csv-dropzone-selected-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;}'
            // カラム仕様の折りたたみ
            . '.csv-spec-details{margin-top:14px;border:1px solid var(--border-light);border-radius:10px;background:var(--bg-subtle);}'
            . '.csv-spec-details > summary{cursor:pointer;list-style:none;padding:10px 14px;font-size:13px;font-weight:600;color:var(--text-label);display:flex;align-items:center;gap:6px;user-select:none;}'
            . '.csv-spec-details > summary::-webkit-details-marker{display:none;}'
            . '.csv-spec-details > summary::before{content:"▸";font-size:10px;color:var(--text-muted-cool);transition:transform .15s;display:inline-block;}'
            . '.csv-spec-details[open] > summary::before{transform:rotate(90deg);}'
            . '.csv-spec-details[open] > summary{border-bottom:1px solid var(--border-light);}'
            . '.csv-spec-body{padding:12px 16px 14px;font-size:13px;line-height:1.7;background:var(--bg-primary);border-radius:0 0 10px 10px;}'
            . '.csv-spec-heading{margin:8px 0 4px;font-weight:700;color:var(--text-heading);font-size:12px;letter-spacing:0.2px;}'
            . '.csv-spec-heading:first-child{margin-top:0;}'
            . '.csv-spec-text{margin:0 0 4px;padding-left:1em;}'
            . '.csv-spec-note{margin:0 0 6px;padding-left:1em;font-size:12px;color:var(--text-muted-cool);}'
            // 直近の取込履歴リスト
            . '.csv-history{margin-top:18px;}'
            . '.csv-history-head{font-size:12px;font-weight:700;color:var(--text-label);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px;}'
            . '.csv-history-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;}'
            . '.csv-history-item{margin:0;}'
            . '.csv-history-link{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 14px;border:1px solid var(--border-light);border-radius:10px;background:var(--bg-primary);text-decoration:none;color:inherit;transition:border-color .12s,background .12s;flex-wrap:wrap;}'
            . '.csv-history-link:hover{border-color:var(--border-cool-accent);background:var(--bg-hover-cool);}'
            . '.csv-history-main{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1 1 220px;}'
            . '.csv-history-time{font-size:12px;color:var(--text-muted-cool);font-variant-numeric:tabular-nums;}'
            . '.csv-history-file{font-size:13px;font-weight:600;color:var(--text-heading);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
            . '.csv-history-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:12px;color:var(--text-label);}'
            . '.csv-history-counts{font-variant-numeric:tabular-nums;color:var(--text-secondary);}'
            . '.csv-history-error{color:var(--text-danger);font-weight:600;}'
            // クイックフィルタタブ（wireframe .chip 準拠: ピル型チップ + 件数バッジ）
            . '.quick-filter-tabs{display:flex;align-items:center;gap:6px;margin:0 0 12px;overflow-x:auto;scrollbar-width:none;white-space:nowrap;padding-bottom:2px;}'
            . '.quick-filter-tabs::-webkit-scrollbar{display:none;}'
            . '.quick-filter-tab{flex:0 0 auto;display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;border:1px solid var(--border-cool);background:var(--bg-primary);border-radius:999px;color:var(--text-label);font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;line-height:1;transition:background .12s,color .12s,border-color .12s;}'
            . '.quick-filter-tab:hover{border-color:var(--border-cool-accent);color:var(--text-heading);}'
            . '.quick-filter-tab.is-active{background:var(--bg-info);color:var(--text-info);border-color:var(--border-info);}'
            . '.quick-filter-tab-count{display:inline-grid;place-items:center;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--bg-tertiary);color:var(--text-label);font-size:11px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;}'
            . '.quick-filter-tab.is-active .quick-filter-tab-count{background:rgba(24,95,165,0.15);color:var(--text-info);}'
            . '@media(max-width:480px){.quick-filter-tab{height:30px;padding:0 10px;font-size:12px;}}'
            // テーブル行のステータス可視化（list-table-renewal 系）
            . '.list-table-renewal td.cell-stripe{padding:0;width:4px;border-left:4px solid var(--border-cool-muted);}'
            . '.list-table-renewal tr[data-urgency="overdue"] td.cell-stripe{border-left-color:var(--border-danger);}'
            . '.list-table-renewal tr[data-urgency="urgent"] td.cell-stripe{border-left-color:var(--border-warning);}'
            . '.list-table-renewal tr[data-urgency="soon"] td.cell-stripe{border-left-color:var(--border-info);}'
            . '.list-table-renewal tr[data-urgency="completed"] td.cell-stripe{border-left-color:var(--border-cool-muted);}'
            . '.list-table-renewal td.cell-date{white-space:nowrap;font-variant-numeric:tabular-nums;}'
            . '.cell-date.is-overdue{color:var(--text-danger);font-weight:600;}'
            . '.cell-date.is-urgent{color:var(--text-warning);font-weight:600;}'
            // PC/モバイル切替（一覧固有、768px）
            . '.list-pc-only{display:block;}'
            . '.list-mobile-only{display:none;}'
            . '@media(max-width:767px){.list-pc-only{display:none;}.list-mobile-only{display:block;}.list-card-list.list-mobile-only{display:flex;}}'
            // ヘッダー: モバイル切替（wireframe 準拠 899px）---------------------
            . '@media(max-width:899px){.site-header-inner{padding:0 12px;}.desktop-only{display:none !important;}.mobile-only{display:inline-flex;}.hamburger{display:inline-flex;}.brand{margin-right:0;}.brand-tenant{max-width:140px;}}'
            // 全画面共通: コンテンツレイアウト（旧 1024px ブロックから header 系を除去）
            . '@media (max-width: 1024px){.detail-top,.split-columns{grid-template-columns:1fr;}.kv-list{grid-template-columns:1fr;gap:6px;}.list-filter-field,.list-filter-field.is-date{grid-column:span 6;}.list-toolbar-actions{justify-content:flex-start;margin-left:0;width:100%;}.list-page-header-actions{margin-left:0;justify-content:flex-start;}.two-col,.metric-grid,.metric-grid-5{grid-template-columns:1fr;}.page-header{flex-wrap:wrap;}.page-header-meta{white-space:normal;}}'
            . '@media (max-width: 768px){.app-shell,.page-container{padding:16px 12px 28px;}.card{padding:16px;}.title{font-size:21px;}.grid{grid-template-columns:1fr;}.actions{align-items:flex-start;}.summary-line{display:block;}.summary-line p + p,.summary-line span + span{margin-top:6px;display:inline-flex;}.btn{min-height:48px;padding:10px 16px;}.btn-small{min-height:44px;padding:8px 14px;}.btn-large{min-height:48px;}.form-input,.form-select{min-height:48px;padding:10px 12px;font-size:14px;}.update-field input[type="text"],.update-field input[type="date"],.update-field select,.update-field textarea{min-height:48px;}.customer-create-grid input,.customer-create-grid select{min-height:48px;}.list-filter-field input,.list-filter-field select,.list-filter-field textarea{min-height:48px;padding:10px 12px;font-size:14px;}.modal-dialog-wide{width:min(760px,calc(100vw - 20px));max-height:calc(100vh - 20px);overflow:auto;padding:14px 14px 12px;}.modal-close-form{position:sticky;top:0;z-index:2;background:var(--bg-primary);padding-bottom:6px;}.customer-create-form{gap:12px;}.customer-create-form .modal-title{font-size:22px;}.customer-create-grid{grid-template-columns:1fr;gap:12px;}.customer-create-grid .form-field--full,.customer-create-grid .form-field--spacer{grid-column:span 1;}.customer-create-grid textarea{min-height:92px;}.dialog-actions{position:sticky;bottom:0;z-index:2;justify-content:stretch;padding-top:10px;padding-bottom:2px;}.dialog-actions .btn{flex:1 1 160px;}.list-page-header{display:grid;gap:10px;align-items:flex-start;}.list-page-header-actions{margin-left:0;width:100%;justify-content:flex-start;}.list-page-header-actions .btn{max-width:100%;}.list-filter-card{padding:0;}.list-filter-toggle{padding:14px 16px;}.list-filter-card form{padding:16px;}.list-filter-field,.list-filter-field.is-date,.modal-form-grid .list-filter-field,.modal-form-wide{grid-column:span 12;}.list-toolbar{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;}.list-toolbar-actions{display:grid;justify-content:stretch;gap:10px;min-width:0;width:100%;}.list-toolbar:not(.list-toolbar-bottom) .list-toolbar-actions{grid-template-columns:minmax(0,1fr);width:100%;row-gap:8px;}.list-sort-summary{text-align:left;min-width:0;white-space:normal;overflow-wrap:anywhere;word-break:break-word;}.list-per-page-form{justify-content:flex-start;min-width:0;width:100%;}.list-select-inline > span{min-width:0;text-align:left;}.list-pager{justify-content:flex-start;flex-wrap:nowrap;min-width:0;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}.list-pager-link{min-width:36px;height:36px;padding:0 8px;font-size:12px;flex:0 0 auto;}.table-card thead{display:none;}.table-card,.table-card tbody,.table-card tr,.table-card td{display:block;width:100%;}.table-card tr{border:1px solid var(--border-cool);border-radius:10px;padding:10px;margin-bottom:12px;background:var(--bg-primary);}.list-table tbody tr.is-completed-row{background:var(--bg-subtle);}.table-card td{border-bottom:none;padding:4px 0;text-align:left;background:transparent !important;min-width:0;}.table-card td::before{content:attr(data-label);display:block;font-size:12px;font-weight:700;color:var(--text-hint);margin-bottom:2px;}.table-card td .truncate,.table-card td .list-policy-text,.table-card td .list-row-primary,.table-card td .list-row-secondary,.table-card td .list-row-tertiary{white-space:normal;overflow:visible;text-overflow:clip;overflow-wrap:anywhere;word-break:break-word;max-width:100%;}.table-wrap{overflow-x:visible;}th,td{padding:7px;font-size:14px;}.cell-action{text-align:left;}.btn-icon-delete{width:48px;height:48px;}.modal-close{width:48px;height:48px;}.dialog-actions .btn{min-height:48px;}.hamburger{width:48px;height:48px;}.compact-input{min-height:48px;height:auto;}.list-pager{gap:4px;}.search-actions{gap:8px;}.table-card td[style*="text-align:center"],.table-card td[style*="text-align:right"]{text-align:left !important;}.table-card td.td-pair{display:inline-block !important;width:49%;vertical-align:top;}.table-card td.td-triple{display:inline-block !important;width:32%;vertical-align:top;}}'
            . '.search-panel-compact{background:var(--bg-secondary);border:0.5px solid var(--border-light);border-radius:var(--radius-md);padding:10px 14px;}'
            . '.search-panel-compact .toggle-header{display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none;margin-bottom:8px;padding-bottom:6px;border-bottom:0.5px solid var(--border-light);}'
            . '.search-panel-compact .toggle-header-title{font-size:12px;font-weight:600;color:var(--text-secondary);}'
            . '.search-panel-compact .toggle-header-arrow{font-size:11px;color:var(--text-secondary);}'
            . '.search-panel-compact.is-collapsed .toggle-header{margin-bottom:0;padding-bottom:0;border-bottom:none;}'
            . '.search-panel-compact.is-collapsed .search-panel-body{display:none;}'
            . '.search-row{display:flex;align-items:center;flex-wrap:wrap;gap:6px 12px;margin-bottom:8px;}'
            . '.search-row:last-child{margin-bottom:0;}'
            . '.search-field{display:flex;align-items:center;gap:5px;}'
            . '.search-label{font-size:12px;color:var(--text-secondary);white-space:nowrap;font-weight:500;}'
            . '.search-sep{font-size:12px;color:var(--text-secondary);margin:0 1px;}'
            . '.compact-input{padding:3px 8px;font-size:13px;border:0.5px solid var(--border-medium);border-radius:6px;height:30px;background:var(--bg-primary);color:var(--text-primary);font-family:inherit;box-sizing:border-box;}'
            . '.compact-input:focus{outline:none;border-color:var(--border-info);box-shadow:0 0 0 2px rgba(55,138,221,0.25);}'
            . '.compact-input.w-date{width:128px;}.compact-input.w-sm{width:90px;}.compact-input.w-md{width:148px;}.compact-input.w-lg{width:190px;}'
            . 'select.compact-input{cursor:pointer;}'
            . '.search-actions{margin-left:auto;display:flex;gap:6px;align-items:center;}'
            . '@media (max-width: 480px){.search-row{flex-direction:column;align-items:stretch;gap:8px;}.search-field{gap:4px;}.compact-input,.compact-input.w-date,.compact-input.w-sm,.compact-input.w-md,.compact-input.w-lg{width:100% !important;font-size:16px;height:48px;}.search-sep{display:none;}.date-range{flex-direction:row !important;gap:4px;}.date-range .compact-input{flex:1;width:auto !important;font-size:14px;height:48px;}.search-actions{margin-left:0;margin-top:4px;}.search-actions .btn{flex:1;}}'
            . '</style>'
            . '</head>'
            . '<body>'
            . $headerHtml
            . '<div class="app-shell page-container">'
            . '<main class="page-main">'
            . $breadcrumbsHtml
            . $content
            . '</main>'
            . '</div>'
            . '<script>(function(){document.querySelectorAll(".search-panel-compact .toggle-header").forEach(function(h){h.addEventListener("click",function(){var p=h.closest(".search-panel-compact");p.classList.toggle("is-collapsed");var c=p.classList.contains("is-collapsed");var a=h.querySelector(".toggle-header-arrow");var t=h.querySelector(".toggle-header-title");if(a)a.textContent=c?"▼":"▲";if(t)t.textContent=c?"検索条件を開く":"検索条件を閉じる";});});}());</script>'
            // ドロワー開閉 JS（モバイルナビ）------------------------------------
            . '<script>(function(){var drawer=document.getElementById("app-drawer");var scrim=document.querySelector("[data-drawer-scrim]");var trigger=document.querySelector("[data-drawer-trigger]");if(!drawer||!scrim||!trigger)return;function open(){drawer.classList.add("open");scrim.classList.add("open");drawer.setAttribute("aria-hidden","false");trigger.setAttribute("aria-expanded","true");document.body.style.overflow="hidden";}function close(){drawer.classList.remove("open");scrim.classList.remove("open");drawer.setAttribute("aria-hidden","true");trigger.setAttribute("aria-expanded","false");document.body.style.overflow="";}trigger.addEventListener("click",open);scrim.addEventListener("click",close);document.querySelectorAll("[data-drawer-close]").forEach(function(el){el.addEventListener("click",close);});document.addEventListener("keydown",function(e){if(e.key==="Escape"&&drawer.classList.contains("open"))close();});drawer.querySelectorAll("a").forEach(function(a){a.addEventListener("click",close);});})();</script>'
            . '<script>document.addEventListener("submit",function(e){var f=e.target;if(!(f instanceof HTMLFormElement))return;if(f.method==="dialog")return;if(f.dataset.submitting==="1"){e.preventDefault();return;}f.dataset.submitting="1";var btns=f.querySelectorAll(\'button[type="submit"],input[type="submit"]\');btns.forEach(function(b){if(!b.dataset.origLabel){b.dataset.origLabel=(b.tagName==="BUTTON"?b.textContent:b.value)||"";}b.disabled=true;b.setAttribute("aria-busy","true");if(b.tagName==="BUTTON"){b.textContent="処理中...";}});},true);</script>'
            . '</body>'
            . '</html>';
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function renderHeader(array $options): string
    {
        if (($options['showHeader'] ?? true) === false) {
            return '';
        }

        $auth = self::currentAuth();
        $navLinks = is_array($options['navLinks'] ?? null) ? $options['navLinks'] : [];
        if ($auth === [] || $navLinks === []) {
            return '';
        }

        $activeNav = (string) ($options['activeNav'] ?? '');
        $activeAdmin = (string) ($options['activeAdmin'] ?? '');
        $displayName = self::escape((string) ($auth['display_name'] ?? ''));
        $tenantName = self::escape((string) ($auth['tenant_name'] ?? ''));
        $logoutAction = self::escape((string) ($options['logoutAction'] ?? ''));
        $logoutToken = self::escape((string) ($options['logoutCsrfToken'] ?? ''));
        $dashboardUrl = self::escape((string) ($navLinks['dashboard'] ?? '#'));

        $mainItems = [
            'dashboard'  => 'ホーム',
            'renewal'    => '満期一覧',
            'accident'   => '事故案件',
            'sales_case' => '見込案件',
            'activity'   => '営業日報',
            'sales'      => '成績管理',
            'customer'   => '顧客一覧',
        ];

        $hasSettings = self::isAdmin($auth) && isset($navLinks['settings']);

        // PC main-nav と mobile drawer-link を並列生成（同じデータから 2 種類のマークアップ）
        $mainNavHtml = '';
        $drawerLinksMain = '';
        foreach ($mainItems as $key => $label) {
            if (!isset($navLinks[$key])) {
                continue;
            }
            // accident は全ログインユーザーに表示する（docs/policies/08_design-tokens.md §4-3）

            $isActive = $activeNav === $key;
            $hrefEsc  = self::escape((string) $navLinks[$key]);
            $labelEsc = self::escape($label);

            $mainNavHtml .= '<a' . ($isActive ? ' class="is-active"' : '')
                . ' href="' . $hrefEsc . '">' . $labelEsc . '</a>';
            $drawerLinksMain .= '<a class="drawer-link' . ($isActive ? ' active' : '')
                . '" href="' . $hrefEsc . '">' . $labelEsc . '</a>';
        }

        // 設定は管理者専用。PC は nav-spacer で視覚分離、drawer は別セクションラベル下に配置
        $drawerAdminSection = '';
        if ($hasSettings) {
            $isActive = $activeNav === 'settings' || $activeAdmin === 'settings';
            $hrefEsc  = self::escape((string) $navLinks['settings']);
            $mainNavHtml .= '<span class="nav-spacer"></span>'
                . '<a' . ($isActive ? ' class="is-active"' : '')
                . ' href="' . $hrefEsc . '">設定</a>';
            $drawerAdminSection = '<div class="drawer-section-label">管理</div>'
                . '<a class="drawer-link' . ($isActive ? ' active' : '')
                . '" href="' . $hrefEsc . '">設定</a>';
        }

        // ハンバーガーアイコン (3 本線 SVG)
        $hamburgerSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<line x1="4" y1="6" x2="20" y2="6"/>'
            . '<line x1="4" y1="12" x2="20" y2="12"/>'
            . '<line x1="4" y1="18" x2="20" y2="18"/>'
            . '</svg>';

        // ログアウトフォーム（PC ヘッダーと drawer フッターで同じ form を 2 箇所に出力）
        $logoutForm = '<form method="post" action="' . $logoutAction . '">'
            . '<input type="hidden" name="_csrf_token" value="' . $logoutToken . '">'
            . '<button class="btn btn-ghost btn-small" type="submit">ログアウト</button>'
            . '</form>';

        // ─── ヘッダー本体 ────────────────────────────────────────────────
        $header = '<header class="site-header">'
            . '<div class="site-header-inner">'
            // 左: ハンバーガー（モバイル専用）+ ブランド
            . '<button type="button" class="hamburger mobile-only" aria-label="メニューを開く"'
            . ' aria-controls="app-drawer" aria-expanded="false" data-drawer-trigger>'
            . $hamburgerSvg
            . '</button>'
            . '<a class="brand" href="' . $dashboardUrl . '">'
            . '<span class="brand-mark" aria-hidden="true">保</span>'
            . '<span class="brand-text">'
            . '<span class="brand-title">保険代理店業務</span>'
            . ($tenantName !== '' ? '<span class="brand-tenant">' . $tenantName . '</span>' : '')
            . '</span>'
            . '</a>'
            // 中央: メインナビ（PC のみ）
            . '<nav class="main-nav desktop-only">' . $mainNavHtml . '</nav>'
            // 右: ユーザー名 + ログアウト（PC のみ）
            . '<div class="header-tools desktop-only">'
            . '<div class="user-meta"><strong>' . $displayName . '</strong></div>'
            . $logoutForm
            . '</div>'
            . '</div>'
            . '</header>';

        // ─── ドロワー（モバイル時のみ JS で開閉。常時 DOM 上に存在） ─────────
        $drawer = '<div class="drawer-scrim" data-drawer-scrim aria-hidden="true"></div>'
            . '<aside class="drawer" id="app-drawer" role="dialog" aria-modal="true"'
            . ' aria-label="ナビゲーションメニュー" aria-hidden="true">'
            . '<div class="drawer-head">'
            . '<div class="drawer-user">'
            . '<div class="drawer-user-name">' . $displayName . '</div>'
            . ($tenantName !== '' ? '<div class="drawer-user-tenant">' . $tenantName . '</div>' : '')
            . '</div>'
            . '<button type="button" class="drawer-close" aria-label="閉じる" data-drawer-close>×</button>'
            . '</div>'
            . '<nav class="drawer-nav">'
            . '<div class="drawer-section-label">業務</div>'
            . $drawerLinksMain
            . $drawerAdminSection
            . '</nav>'
            . '<div class="drawer-foot">'
            . $logoutForm
            . '</div>'
            . '</aside>';

        return $header . $drawer;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function renderBreadcrumbs(array $options): string
    {
        $breadcrumbs = is_array($options['breadcrumbs'] ?? null) ? $options['breadcrumbs'] : [];
        if ($breadcrumbs === []) {
            return '';
        }

        $itemsHtml = '';
        $lastIndex = count($breadcrumbs) - 1;
        foreach ($breadcrumbs as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = self::escape((string) ($item['label'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            $itemsHtml .= '<li>';
            if ($url !== '' && $index !== $lastIndex) {
                $itemsHtml .= '<a href="' . self::escape($url) . '">' . $label . '</a>';
            } else {
                $itemsHtml .= '<span>' . $label . '</span>';
            }
            $itemsHtml .= '</li>';
        }

        return $itemsHtml === '' ? '' : '<ol class="breadcrumbs">' . $itemsHtml . '</ol>';
    }

    /**
     * @return array<string, mixed>
     */
    private static function currentAuth(): array
    {
        $auth = $_SESSION['auth'] ?? [];
        return is_array($auth) ? $auth : [];
    }

    /**
     * @param array<string, mixed> $auth
     */
    private static function isAdmin(array $auth): bool
    {
        $permissions = $auth['permissions'] ?? [];
        if (!is_array($permissions)) {
            return false;
        }

        return !empty($permissions['is_system_admin']) || (($permissions['tenant_role'] ?? '') === 'admin');
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 入力要素用の aria-* 属性を生成する（WCAG 2.1 AA 対応）。
     * aria-describedby はエラー有無に関わらず常時付与し、参照切れを防ぐ。
     *
     * @param array<string, mixed> $errors
     */
    public static function fieldAria(array $errors, string $fieldName, bool $required = false): string
    {
        $attrs = ' aria-describedby="' . self::escape($fieldName) . '-err"';
        if ($required) {
            $attrs .= ' aria-required="true"';
        }
        if (isset($errors[$fieldName]) && (string) $errors[$fieldName] !== '') {
            $attrs .= ' aria-invalid="true"';
        }
        return $attrs;
    }

    /**
     * エラーメッセージ span を生成する（id 付き、aria-describedby 参照用）。
     * エラーが無い場合は hidden 属性付きの空 span を返し、参照切れを防ぐ。
     *
     * @param array<string, mixed> $errors
     */
    public static function fieldError(array $errors, string $fieldName): string
    {
        $id = self::escape($fieldName) . '-err';
        if (!isset($errors[$fieldName]) || (string) $errors[$fieldName] === '') {
            return '<span id="' . $id . '" class="field-error" hidden></span>';
        }
        return '<span id="' . $id . '" class="field-error">'
            . self::escape((string) $errors[$fieldName]) . '</span>';
    }
}
