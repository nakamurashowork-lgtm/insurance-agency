<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class DashboardView
{
    /**
     * @param array<string, mixed> $auth
     */
    public static function render(array $auth, bool $showAdminHelpers, ?string $tenantDbName, ?string $warning, string $logoutCsrfToken, ?string $flashError, string $renewalListUrl, string $customerListUrl, string $salesListUrl, string $accidentListUrl, string $tenantSettingsUrl, string $logoutActionUrl): string
    {
        $displayName = Layout::escape((string) ($auth['display_name'] ?? ''));
        $tenantName = Layout::escape((string) ($auth['tenant_name'] ?? ''));
        $tenantCode = Layout::escape((string) ($auth['tenant_code'] ?? ''));
        $tenantDb = Layout::escape((string) ($tenantDbName ?? '未確認'));
        $csrfToken = Layout::escape($logoutCsrfToken);
        $renewalListLink = Layout::escape($renewalListUrl);
        $customerListLink = Layout::escape($customerListUrl);
        $salesListLink = Layout::escape($salesListUrl);
        $accidentListLink = Layout::escape($accidentListUrl);
        $tenantSettingsLink = Layout::escape($tenantSettingsUrl);
        $logoutAction = Layout::escape($logoutActionUrl);

        $warningHtml = '';
        if (is_string($warning) && $warning !== '') {
            $warningHtml = '<div class="notice">' . Layout::escape($warning) . '</div>';
        }

        $errorHtml = '';
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $adminHelpers = '';
        if ($showAdminHelpers) {
            $adminHelpers = ''
                . '<div class="card helper">'
                . '<h2>管理者向け補助導線</h2>'
                . '<p class="muted">通常業務の主要導線ではなく、管理者向け補助導線として表示しています。</p>'
                . '<div class="grid">'
                . '<div class="nav-item"><strong><a href="' . $accidentListLink . '">事故案件一覧</a></strong><br><span class="muted">管理者向け補助導線</span></div>'
                . '<div class="nav-item"><strong><a href="' . $tenantSettingsLink . '">テナント設定</a></strong><br><span class="muted">管理者向け補助導線</span></div>'
                . '</div>'
                . '</div>';
        }

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">ダッシュボード</h1>'
            . '<p class="muted">入口画面です。実処理は各業務画面で行います。</p>'
            . $errorHtml
            . $warningHtml
            . '<p>ログインユーザー: ' . $displayName . '</p>'
            . '<p>テナント: ' . $tenantName . ' (' . $tenantCode . ')</p>'
            . '<p>接続先 tenant DB: ' . $tenantDb . '</p>'
            . '<form method="post" action="' . $logoutAction . '">'
            . '<input type="hidden" name="_csrf_token" value="' . $csrfToken . '">'
            . '<button type="submit" class="btn btn-secondary">ログアウト</button>'
            . '</form>'
            . '</div>'
            . '<div class="card">'
            . '<h2>主要導線</h2>'
            . '<div class="grid">'
            . '<div class="nav-item"><strong><a href="' . $renewalListLink . '">満期一覧</a></strong><br><span class="muted">契約一覧を兼ねる画面</span></div>'
            . '<div class="nav-item"><strong><a href="' . $customerListLink . '">顧客一覧</a></strong><br><span class="muted">顧客単位の確認画面</span></div>'
            . '<div class="nav-item"><strong><a href="' . $salesListLink . '">実績管理一覧</a></strong><br><span class="muted">実績の検索・登録・編集・削除</span></div>'
            . '</div>'
            . '</div>'
            . $adminHelpers;

        return Layout::render('ダッシュボード', $content);
    }
}
