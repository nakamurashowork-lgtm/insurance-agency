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
            . ':root{--bg:#f3f6f8;--surface:#ffffff;--surface-soft:#eef4f6;--line:#d4dee6;--text:#163042;--muted:#52606d;--primary:#0f6a78;--primary-deep:#0b4f59;--accent:#b9482f;--warn-bg:#fff0eb;--warn-text:#a33f2c;}'
            . '*{box-sizing:border-box;}'
            . 'body{margin:0;font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;background:radial-gradient(circle at top left,#f9fcfd 0,#eef4f6 42%,#f5f7fa 100%);color:var(--text);}'
            . '.app-shell{max-width:1280px;margin:0 auto;padding:20px 16px 32px;}'
            . '.page-container{max-width:1280px;margin:0 auto;width:100%;min-width:0;}'
            . '.page-main{margin-top:18px;}'
            . '.site-header{position:sticky;top:0;z-index:20;backdrop-filter:blur(12px);background:rgba(243,246,248,0.92);border-bottom:1px solid rgba(212,222,230,0.9);}'
            . '.site-header-inner{max-width:1280px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;}'
            . '.brand{display:flex;align-items:center;gap:12px;color:var(--text);text-decoration:none;min-width:0;}'
            . '.brand-mark{width:40px;height:40px;border-radius:14px;background:linear-gradient(135deg,#0f6a78,#3f8c92);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;letter-spacing:0.08em;box-shadow:0 8px 18px rgba(15,106,120,0.18);}'
            . '.brand-copy{display:flex;flex-direction:column;min-width:0;}'
            . '.brand-title{font-size:15px;font-weight:800;line-height:1.1;}'
            . '.brand-subtitle{font-size:12px;color:var(--muted);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}'
            . '.header-primary{display:flex;align-items:center;gap:18px;min-width:0;flex:1 1 auto;flex-wrap:wrap;}'
            . '.main-nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0;}'
            . '.main-nav a{display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;color:var(--text);text-decoration:none;font-weight:700;font-size:14px;border:1px solid transparent;}'
            . '.main-nav a:hover{background:#e8f1f3;border-color:#c9d9df;}'
            . '.main-nav a.is-active{background:#dfeff2;border-color:#97bec5;color:var(--primary-deep);}'
            . '.header-tools{display:flex;align-items:center;gap:12px;margin-left:auto;flex:0 0 auto;}'
            . '.user-meta{display:flex;flex-direction:column;align-items:flex-end;min-width:0;}'
            . '.user-meta strong,.user-meta span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;}'
                . '.mobile-menu{position:relative;}'
                . '.mobile-menu summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-weight:700;border:1px solid var(--line);border-radius:999px;background:#fff;padding:10px 14px;color:var(--text);}'
                . '.mobile-menu summary::-webkit-details-marker{display:none;}'
                . '.mobile-menu[open] summary{background:#eef4f6;border-color:#a7c1c8;}'
            . '.breadcrumbs{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 14px;list-style:none;padding:0;color:var(--muted);font-size:13px;}'
            . '.breadcrumbs a{color:var(--muted);text-decoration:none;}'
            . '.breadcrumbs a:hover{color:var(--primary-deep);text-decoration:underline;}'
            . '.breadcrumbs li+li::before{content:"/";margin-right:8px;color:#8aa0b0;}'
            . '.card{background:#fff;border:1px solid #d9e2ec;border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 2px 6px rgba(0,0,0,0.04);}'
            . '.title{font-size:24px;font-weight:700;margin:0 0 12px;}'
            . '.muted{color:#52606d;font-size:14px;}'
            . '.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}'
            . '.btn{display:inline-block;background:#0b7285;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;border:none;cursor:pointer;transition:all 0.2s ease;}'
            . '.btn:hover{opacity:0.9;}'
            . '.btn:disabled,.btn[aria-busy="true"]{opacity:0.6;cursor:not-allowed;}'
            . '.btn-primary{background:#0f6a78;}'
            . '.btn-large{padding:12px 24px;font-size:16px;width:100%;}'
            . '.btn-secondary{background:#334e68;}'
            . '.btn-aux{background:#627d98;}'
            . '.btn-danger{background:#b42318;}'
            . '.btn-danger:hover{background:#912018;}'
            . '.btn-ghost{background:#fff;color:var(--text);border:1px solid var(--line);}'
            . '.btn-small{padding:8px 12px;font-size:13px;}'
            . '.text-link{color:#0b7285;text-decoration:underline;font-weight:600;}'
            . '.text-link:hover{color:#095c6b;}'
            . '.notice{padding:10px 12px;background:#fff4e5;border:1px solid #ffd8a8;border-radius:8px;margin-bottom:12px;}'
            . '.error{padding:10px 12px;background:#ffe3e3;border:1px solid #ffa8a8;border-radius:8px;margin-bottom:12px;}'
            . '.alert{padding:14px;border-radius:8px;margin-bottom:16px;display:flex;gap:12px;align-items:flex-start;}'
            . '.alert-error{background:#fff0eb;border:1px solid #ffccbc;color:#a33f2c;}'
            . '.alert-icon{font-weight:700;font-size:16px;flex-shrink:0;margin-top:2px;}'
            . '.alert-content{flex:1;min-width:0;}'
            . '.alert-title{font-weight:700;margin-bottom:4px;}'
            . '.alert-message{font-size:14px;line-height:1.5;}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}'
            . '.nav-item{border:1px solid #d9e2ec;border-radius:8px;padding:12px;background:#f8fafc;}'
            . '.nav-item-primary{background:#f7fbfd;border-color:#b9d7de;}'
            . '.helper{border-left:4px solid #334e68;padding-left:12px;}'
            . '.helper-soft{background:#f8fafc;border-color:#bcccdc;}'
            . '.nav-item-helper{background:#f2f5f8;border-color:#c5d0db;}'
            . '.details-panel summary,.helper-details summary{cursor:pointer;font-weight:700;list-style:none;}'
            . '.details-panel summary::-webkit-details-marker,.helper-details summary::-webkit-details-marker{display:none;}'
            . '.details-panel summary{margin-bottom:12px;color:#102a43;}'
            . '.helper-details summary{color:#334e68;}'
            . '.helper-details[open] summary{margin-bottom:12px;}'
            . '.modal-dialog{width:min(640px,calc(100vw - 24px));border:none;border-radius:14px;padding:18px 18px 16px;background:#fff;box-shadow:0 20px 48px rgba(17,33,49,0.24);}'
            . '.modal-dialog-wide{width:min(920px,calc(100vw - 24px));}'
            . '.modal-dialog::backdrop{background:rgba(16,42,67,0.46);}'
            . '.modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;}'
            . '.modal-head h2{margin:0;font-size:20px;}'
            . '.modal-close-form{margin:0;display:flex;justify-content:flex-end;}'
            . '.modal-close{appearance:none;border:1px solid var(--line);background:#fff;color:var(--text);border-radius:999px;width:32px;height:32px;font-size:20px;line-height:1;cursor:pointer;}'
            . '.modal-close:hover{background:#eef4f6;border-color:#a7c1c8;}'
            . '.customer-create-form{display:grid;gap:16px;margin-top:8px;}'
            . '.customer-create-form .modal-title{margin:0 40px 2px 0;font-size:24px;font-weight:700;line-height:1.25;}'
            . '.customer-create-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;align-items:start;}'
            . '.customer-create-grid .form-field{display:grid;gap:6px;min-width:0;}'
            . '.customer-create-grid .form-field-label{font-size:13px;font-weight:700;color:#334e68;line-height:1.35;}'
            . '.customer-create-grid .form-field--required .form-field-label::after{content:" *";color:#c92a2a;font-weight:700;}'
            . '.customer-create-grid .form-field--full{grid-column:1 / -1;}'
            . '.customer-create-grid .form-field--spacer{grid-column:span 1;}'
            . '.customer-create-grid input,.customer-create-grid select,.customer-create-grid textarea{width:100%;min-width:0;padding:10px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;line-height:1.4;background:#fff;color:#102a43;}'
            . '.customer-create-grid input:focus,.customer-create-grid select:focus,.customer-create-grid textarea:focus{outline:none;border-color:#0b7285;box-shadow:0 0 0 3px rgba(11,114,133,0.1);}'
            . '.customer-create-grid textarea{min-height:108px;max-height:220px;resize:vertical;}'
            . '.dialog-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding-top:10px;border-top:1px solid #e3e9ef;background:#fff;}'
            . '.dialog-actions .btn{min-width:112px;min-height:42px;border-radius:10px;}'
            . '.modal-dialog .list-filter-field{margin-top:8px;}'
            . '.modal-help{margin-top:10px;border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#f8fbfc;}'
            . '.modal-help summary{margin:0;font-size:13px;}'
            . '.modal-help p{margin:10px 0 0;}'
            . '.modal-result{margin:10px 0 12px;padding:10px 12px;border-radius:10px;background:#f8fbfc;border:1px solid var(--line);}'
            . '.modal-result p{margin:0;}'
            . '.modal-result p + p{margin-top:4px;}'
            . '.required-mark{color:#c92a2a;}'
            . '.modal-form-section{padding-top:10px;margin-top:10px;border-top:1px solid var(--line);}'
            . '.modal-form-section:first-of-type{border-top:none;padding-top:0;margin-top:0;}'
            . '.modal-form-title{margin:0 0 8px;font-size:15px;color:#243b53;}'
            . '.modal-form-grid .list-filter-field{grid-column:span 6;}'
            . '.modal-form-wide,.modal-form-grid .modal-form-wide{grid-column:span 12;}'
            . '.modal-form-actions{margin-top:16px;justify-content:flex-end;}'
            . '.list-page-frame{display:grid;gap:16px;}'
            . '.list-page-frame > .card,.list-page-frame > .list-page-header{margin-bottom:0;}'
            . '.list-page-header{margin-bottom:0;display:flex;align-items:center;justify-content:space-between;gap:12px;}'
            . '.list-page-header .title{margin-bottom:0;}'
            . '.list-page-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-left:auto;flex-wrap:wrap;min-width:0;max-width:100%;}'
            . '.list-filter-card{padding:0;overflow:hidden;}'
            . '.list-filter-toggle{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;margin:0;background:#f8fbfc;border-bottom:1px solid transparent;}'
            . '.list-filter-card[open] .list-filter-toggle{border-bottom-color:var(--line);}'
            . '.list-filter-toggle-label.is-open{display:none;}'
            . '.list-filter-card[open] .list-filter-toggle-label.is-open{display:inline;}'
            . '.list-filter-card[open] .list-filter-toggle-label.is-closed{display:none;}'
            . '.list-filter-card form{padding:18px 20px 20px;}'
            . '.list-filter-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px 14px;}'
            . '.list-filter-field{grid-column:span 4;display:grid;gap:6px;font-size:13px;font-weight:700;color:#334e68;}'
            . '.list-filter-field.is-date{grid-column:span 6;}'
            . '.list-filter-field input,.list-filter-field select,.list-filter-field textarea{width:100%;min-width:0;padding:9px 12px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;background:#fff;color:#102a43;}'
            . '.list-filter-field input:focus,.list-filter-field select:focus,.list-filter-field textarea:focus{outline:none;border-color:#0b7285;box-shadow:0 0 0 3px rgba(11,114,133,0.1);}'
            . '.list-filter-field textarea{resize:vertical;line-height:1.5;}'
            . '.list-filter-actions{margin-top:16px;}'
            . '.list-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px;}'
            . '.list-toolbar-bottom{margin-top:12px;margin-bottom:0;padding-top:12px;border-top:1px solid var(--line);}'
            . '.list-summary{display:grid;gap:4px;}'
            . '.list-summary p{margin:0;}'
            . '.list-toolbar-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;margin-left:auto;min-width:0;max-width:100%;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-toolbar-actions{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,auto);align-items:center;column-gap:12px;row-gap:8px;width:min(100%,680px);min-width:0;}'
            . '.list-toolbar-bottom .list-toolbar-actions{width:100%;min-width:0;}'
            . '.list-sort-summary{margin:0;white-space:nowrap;min-width:142px;text-align:right;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-sort-summary{justify-self:end;}'
            . '.list-per-page-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0;justify-content:flex-start;min-width:132px;max-width:100%;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-per-page-form{justify-self:start;}'
            . '.list-select-inline{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#334e68;justify-content:flex-start;min-width:0;max-width:100%;flex-wrap:wrap;}'
            . '.list-select-inline > span{display:inline-block;min-width:56px;text-align:left;}'
            . '.list-select-inline select{min-width:80px;width:80px;}'
            . '.list-pager{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end;min-width:0;max-width:100%;}'
            . '.list-toolbar:not(.list-toolbar-bottom) .list-pager{justify-self:end;}'
            . '.list-toolbar-bottom .list-pager{min-width:0;}'
            . '.list-pager-link{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:36px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--text);text-decoration:none;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;}'
            . '.list-pager-link:hover{background:#eef4f6;border-color:#a7c1c8;}'
            . '.list-pager-link.is-current{background:#dfeff2;border-color:#97bec5;color:var(--primary-deep);}'
            . '.list-table th,.list-table td{padding:8px 10px;line-height:1.3;}'
            . '.list-table thead th{font-size:13px;color:#334e68;background:#f8fbfc;vertical-align:middle;}'
            . '.list-table tbody tr:hover td{background:#f8fbfc;}'
            . '.list-table tbody tr.is-completed-row td{background:#f3f5f7;color:#6b7c89;}'
            . '.list-table tbody tr.is-completed-row:hover td{background:#edf1f4;}'
            . '.list-table tbody tr.is-completed-row .muted,.list-table tbody tr.is-completed-row .list-row-tertiary{color:#748694;}'
            . '.list-table tbody tr.is-completed-row .text-link{color:#486581;}'
            . '.list-table tbody tr.is-completed-row .status-badge{background:#e3e8ec;color:#627381;}'
            . '.list-row-stack{gap:2px;}'
            . '.list-row-primary{font-size:14px;line-height:1.35;min-width:0;overflow-wrap:anywhere;word-break:break-word;}'
            . '.list-row-secondary{font-size:13px;color:#334e68;line-height:1.3;}'
            . '.list-policy-text{display:block;max-width:100%;min-width:0;font-size:13px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}'
            . '.list-row-tertiary{font-size:12px;color:#6b7c89;line-height:1.3;min-width:0;overflow-wrap:anywhere;word-break:break-word;}'
            . '.list-table-renewal col.list-col-customer{width:28%;}'
            . '.list-table-renewal col.list-col-policy{width:200px;}'
            . '.list-table-renewal col.list-col-date{width:132px;}'
            . '.list-table-renewal col.list-col-status{width:150px;}'
            . '.list-table-renewal col.list-col-next{width:152px;}'
            . '.list-table-renewal col.list-col-action{width:110px;}'
            . '.list-table-accident col.list-col-customer{width:30%;}'
            . '.list-table-accident col.list-col-policy{width:240px;}'
            . '.list-table-accident col.list-col-product{width:120px;}'
            . '.list-table-accident col.list-col-date{width:126px;}'
            . '.list-table-accident col.list-col-status{width:124px;}'
            . '.list-table-accident col.list-col-priority{width:92px;}'
            . '.list-table-accident col.list-col-action{width:110px;}'
            . '.list-sort-link{display:inline-flex;align-items:center;gap:6px;color:inherit;text-decoration:none;font-weight:700;}'
            . '.list-sort-link:hover{color:var(--primary-deep);}'
            . '.list-sort-link.is-active{color:var(--primary-deep);}'
            . '.list-sort-indicator{font-size:11px;line-height:1;}'
            . '.status-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.3;}'
            . '.status-open{background:#ffe3e3;color:#c92a2a;}'
            . '.status-progress{background:#fff3cd;color:#7a5600;}'
            . '.status-done{background:#d3f9d8;color:#2b8a3e;}'
            . '.status-inactive{background:#eef2f6;color:#486581;}'
            . '.priority-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.3;}'
            . '.priority-high{background:#ffe3e3;color:#c92a2a;}'
            . '.priority-medium{background:#fff3cd;color:#7a5600;}'
            . '.priority-low{background:#e7f5ff;color:#0c45cc;}'
            . '.priority-none{background:#eef2f6;color:#486581;}'
            . '.table-wrap{overflow-x:auto;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{text-align:left;border-bottom:1px solid #d9e2ec;padding:8px;vertical-align:top;}'
            . '.table-fixed{table-layout:fixed;}'
            . '.table-spacious th,.table-spacious td{padding:12px 10px;}'
            . '.truncate{display:block;max-width:100%;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
            . '.cell-stack{display:flex;flex-direction:column;gap:4px;min-width:0;}'
            . '.cell-action{white-space:nowrap;text-align:right;}'
            . '.align-right{text-align:right;}'
            . '.summary-line{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}'
            . '.summary-line p{margin:0;}'
            . '.summary-count{font-weight:700;color:#102a43;}'
            . '.tag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#edf5f7;color:var(--primary-deep);font-size:12px;font-weight:700;border:1px solid #c3dadd;}'
            . '.warning-text{color:var(--warn-text);font-weight:700;}'
            . '.detail-top{display:grid;grid-template-columns:minmax(0,0.95fr) minmax(320px,1.05fr);gap:16px;align-items:start;margin-bottom:16px;}'
            . '.detail-side{display:grid;gap:16px;}'
            . '.section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:8px;}'
            . '.section-head h1,.section-head h2,.section-head h3{margin:0;}'
            . '.meta-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:8px;}'
            . '.kv-list{display:grid;grid-template-columns:160px minmax(0,1fr);gap:10px 16px;margin:0;}'
            . '.kv-list dt{font-weight:700;color:#486581;}'
            . '.kv-list dd{margin:0;min-width:0;word-break:break-word;}'
            . '.form-hint{margin:0 0 10px;color:#486581;font-size:12px;line-height:1.45;}'
            . '.field-error{display:block;margin-top:4px;color:#b42318;font-size:12px;font-weight:700;line-height:1.4;}'
            . '.input-error{border-color:#d64545 !important;background:#fff5f5;}'
            . '.renewal-update-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:16px;}'
            . '.update-field{display:grid;gap:8px;}'
            . '.update-field label{display:block;font-size:13px;font-weight:700;color:#334e68;}'
            . '.update-field input[type="text"],.update-field input[type="date"],.update-field select,.update-field textarea{width:100%;min-width:0;padding:8px 10px;border:1px solid #d9e2ec;border-radius:8px;font-size:14px;font-family:inherit;background:#fff;color:#102a43;}'
            . '.update-field input[type="text"]:focus,.update-field input[type="date"]:focus,.update-field select:focus,.update-field textarea:focus{outline:none;border-color:#0b7285;box-shadow:0 0 0 3px rgba(11,114,133,0.1);}'
            . '.update-field textarea{resize:vertical;line-height:1.5;}'
            . '.update-field-full{grid-column:span 2;}'
            . '.renewal-update-actions{display:flex;justify-content:flex-end;gap:8px;}'
            . '.btn-primary{background:#0f6a78;}'
            . '.btn-primary:hover{background:#095c6b;}'
            . '.section-stack{display:grid;gap:16px;}'
            . '.split-columns{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;}'
            . '.panel-list{margin:0;padding-left:18px;display:grid;gap:8px;}'
            . '.comment-item{list-style:none;margin:0;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#f9fbfc;display:grid;gap:8px;}'
            . '.comment-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-size:12px;color:#486581;}'
            . '.comment-meta-text{font-weight:700;color:#334e68;overflow-wrap:anywhere;word-break:break-word;}'
            . '.comment-body{white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;line-height:1.5;color:#102a43;}'
            . '.history-item{list-style:none;margin:0;padding:12px;border:1px solid var(--line);border-radius:10px;background:#f9fbfc;display:grid;gap:10px;}'
            . '.history-meta{display:flex;flex-wrap:wrap;gap:8px 14px;font-size:12px;color:#486581;}'
            . '.history-summary{font-size:13px;color:#102a43;line-height:1.5;overflow-wrap:anywhere;word-break:break-word;}'
            . '.history-detail-table-wrap{overflow-x:auto;}'
            . '.history-detail-table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.history-detail-table th,.history-detail-table td{padding:7px 8px;border-bottom:1px solid #d9e2ec;vertical-align:top;font-size:13px;overflow-wrap:anywhere;word-break:break-word;}'
            . '.history-detail-table th{font-weight:700;color:#334e68;background:#eef6f7;}'
            . '.contact-card{border:1px solid var(--line);border-radius:12px;padding:14px;background:#f9fbfc;display:grid;gap:6px;}'
            . '.contact-card-primary{background:#eef6f7;border-color:#bfd8dd;}'
            . '.section-note{margin:0;color:var(--muted);font-size:13px;}'
            . '.details-compact{padding:0;overflow:hidden;}'
            . '.details-compact summary{padding:18px 20px;margin:0;display:flex;justify-content:space-between;align-items:center;}'
            . '.details-compact[open] summary{border-bottom:1px solid var(--line);margin-bottom:0;}'
            . '.details-compact-body{padding:18px 20px;}'
            . '.mobile-only{display:none;}'
            . '.login-card{max-width:480px;margin:48px auto 0;}'
            . '.login-header{margin-bottom:20px;text-align:center;}'
            . '.login-title{font-size:22px;font-weight:700;margin:0;color:var(--text);}'
            . '.login-subtitle{font-size:16px;font-weight:700;margin:8px 0 0;color:var(--primary);}'
            . '.login-description{color:var(--muted);font-size:14px;line-height:1.6;text-align:center;margin:16px 0 24px;}'
            . '.login-actions{display:flex;flex-direction:column;gap:12px;margin-bottom:20px;}'
            . '.login-helper-text{color:var(--muted);font-size:12px;line-height:1.6;text-align:center;margin:0;border-top:1px solid var(--line);padding-top:16px;}'
            . '@media (max-width: 900px){.site-header-inner{padding:12px;}.main-nav,.header-tools > .user-meta,.header-tools > form{display:none;}.mobile-only{display:block;}.brand-subtitle{white-space:normal;}.mobile-menu-panel{margin-top:10px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px;display:grid;gap:10px;box-shadow:0 18px 34px rgba(17,33,49,0.12);}.mobile-menu-link{display:block;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--text);font-weight:700;background:#f8fbfc;}.mobile-menu-link.is-active{background:#dfeff2;color:var(--primary-deep);}.mobile-meta{padding:10px 12px;border-radius:10px;background:#f8fbfc;color:var(--muted);display:grid;gap:2px;}.detail-top,.split-columns{grid-template-columns:1fr;}.kv-list{grid-template-columns:1fr;gap:6px;}.list-filter-field,.list-filter-field.is-date{grid-column:span 6;}.list-toolbar-actions{justify-content:flex-start;margin-left:0;width:100%;}.list-page-header-actions{margin-left:0;justify-content:flex-start;}}'
            . '@media (max-width: 768px){.modal-dialog-wide{width:min(760px,calc(100vw - 20px));max-height:calc(100vh - 20px);overflow:auto;padding:14px 14px 12px;}.modal-close-form{position:sticky;top:0;z-index:2;background:#fff;padding-bottom:6px;}.customer-create-form{gap:12px;}.customer-create-form .modal-title{font-size:22px;}.customer-create-grid{grid-template-columns:1fr;gap:12px;}.customer-create-grid .form-field--full,.customer-create-grid .form-field--spacer{grid-column:span 1;}.customer-create-grid textarea{min-height:92px;}.dialog-actions{position:sticky;bottom:0;z-index:2;justify-content:stretch;padding-top:10px;padding-bottom:2px;}.dialog-actions .btn{flex:1 1 160px;}}'
            . '@media (max-width: 640px){.app-shell,.page-container{padding:16px 12px 28px;}.card{padding:16px;}.title{font-size:21px;}.grid{grid-template-columns:1fr;}.actions{align-items:flex-start;}.summary-line{display:block;}.summary-line p + p,.summary-line span + span{margin-top:6px;display:inline-flex;}.list-page-header{display:grid;gap:10px;align-items:flex-start;}.list-page-header-actions{margin-left:0;width:100%;justify-content:flex-start;}.list-page-header-actions .btn{max-width:100%;}.list-filter-card{padding:0;}.list-filter-toggle{padding:14px 16px;}.list-filter-card form{padding:16px;}.list-filter-field,.list-filter-field.is-date,.modal-form-grid .list-filter-field,.modal-form-wide{grid-column:span 12;}.list-toolbar{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;}.list-toolbar-actions{display:grid;justify-content:stretch;gap:10px;min-width:0;width:100%;}.list-toolbar:not(.list-toolbar-bottom) .list-toolbar-actions{grid-template-columns:minmax(0,1fr);width:100%;row-gap:8px;}.list-sort-summary{text-align:left;min-width:0;white-space:normal;overflow-wrap:anywhere;word-break:break-word;}.list-per-page-form{justify-content:flex-start;min-width:0;width:100%;}.list-select-inline > span{min-width:0;text-align:left;}.list-pager{justify-content:flex-start;flex-wrap:wrap;min-width:0;width:100%;}.list-pager-link{min-width:40px;padding:0 10px;}.table-card thead{display:none;}.table-card,.table-card tbody,.table-card tr,.table-card td{display:block;width:100%;}.table-card tr{border:1px solid #d9e2ec;border-radius:10px;padding:10px;margin-bottom:12px;background:#fff;}.list-table tbody tr.is-completed-row{background:#f3f5f7;}.table-card td{border-bottom:none;padding:4px 0;text-align:left;background:transparent !important;min-width:0;}.table-card td::before{content:attr(data-label);display:block;font-size:12px;font-weight:700;color:#486581;margin-bottom:2px;}.table-card td .truncate,.table-card td .list-policy-text,.table-card td .list-row-primary,.table-card td .list-row-secondary,.table-card td .list-row-tertiary{white-space:normal;overflow:visible;text-overflow:clip;overflow-wrap:anywhere;word-break:break-word;max-width:100%;}.table-wrap{overflow-x:visible;}th,td{padding:7px;font-size:14px;}.cell-action{text-align:left;}}'
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
            'dashboard' => 'ホーム',
            'renewal'   => '満期管理',
            'sales'     => '実績管理',
            'customer'  => '顧客管理',
            'activity'  => '営業活動',
            'accident'  => '事故管理',
        ];

        if (self::isAdmin($auth) && isset($navLinks['settings'])) {
            $mainItems['settings'] = '管理・設定';
        }

        $mainNavHtml = '';
        $mobileLinks = '';
        foreach ($mainItems as $key => $label) {
            if (!isset($navLinks[$key])) {
                continue;
            }
            if ($key === 'accident' && !self::isAdmin($auth)) {
                continue;
            }

            $isActive = $activeNav === $key || ($key === 'settings' && $activeAdmin === 'settings');
            $class = $isActive ? ' class="is-active"' : '';
            $mainNavHtml .= '<a' . $class . ' href="' . self::escape((string) $navLinks[$key]) . '">' . self::escape($label) . '</a>';
            $class = $isActive ? ' is-active' : '';
            $mobileLinks .= '<a class="mobile-menu-link' . $class . '" href="' . self::escape((string) $navLinks[$key]) . '">' . self::escape($label) . '</a>';
        }

        return '<header class="site-header">'
            . '<div class="site-header-inner">'
            . '<div class="header-primary">'
            . '<a class="brand" href="' . $dashboardUrl . '">'
            . '<span class="brand-mark">IA</span>'
            . '<span class="brand-copy"><span class="brand-title">保険代理店業務</span></span>'
            . '</a>'
            . '<nav class="main-nav">' . $mainNavHtml . '</nav>'
            . '</div>'
            . '<div class="header-tools">'
            . '<div class="user-meta"><strong>' . $displayName . '</strong><span>' . $tenantName . '</span></div>'
            . '<form method="post" action="' . $logoutAction . '"><input type="hidden" name="_csrf_token" value="' . $logoutToken . '"><button class="btn btn-ghost btn-small" type="submit">ログアウト</button></form>'
            . '<details class="mobile-menu mobile-only">'
            . '<summary>メニュー</summary>'
            . '<div class="mobile-menu-panel">'
            . $mobileLinks
            . '<div class="mobile-meta"><strong>' . $displayName . '</strong><span>' . $tenantName . '</span></div>'
            . '<form method="post" action="' . $logoutAction . '"><input type="hidden" name="_csrf_token" value="' . $logoutToken . '"><button class="btn btn-ghost btn-small" type="submit">ログアウト</button></form>'
            . '</div>'
            . '</details>'
            . '</div>'
            . '</div>'
            . '</header>';
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
}
