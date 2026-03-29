<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Presentation\View\Layout;

final class TenantSettingsView
{
    /**
     * @param array<string, mixed> $auth
     * @param array<string, array<string, mixed>> $notifySettings
     * @param array<int, array<string, mixed>> $phases
     */
    public static function render(
        array $auth,
        array $notifySettings,
        array $phases,
        string $activeTab,
        string $settingsUrl,
        string $notifySaveUrl,
        string $phaseSaveUrl,
        string $dashboardUrl,
        string $notifyCsrfToken,
        string $phaseCsrfToken,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }
        if (is_string($flashError) && $flashError !== '') {
            $errorHtml .= '<div class="error">' . Layout::escape($flashError) . '</div>';
        }

        $successHtml = '';
        if (is_string($flashSuccess) && $flashSuccess !== '') {
            $successHtml = '<div class="notice">' . Layout::escape($flashSuccess) . '</div>';
        }

        $notifyTabClass = $activeTab === 'notify' ? '' : ' btn-secondary';
        $masterTabClass = $activeTab === 'master' ? '' : ' btn-secondary';

        $renewal = $notifySettings['renewal'] ?? [];
        $accident = $notifySettings['accident'] ?? [];

        $notifyContent = self::renderNotifyForm($renewal, $accident, $notifySaveUrl, $notifyCsrfToken);
        $masterContent = self::renderPhaseForms($phases, $phaseSaveUrl, $phaseCsrfToken);

        $tenantName = Layout::escape((string) ($auth['tenant_name'] ?? ''));
        $tenantCode = Layout::escape((string) ($auth['tenant_code'] ?? ''));

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">テナント設定</h1>'
            . '<p class="muted">管理者向け補助導線です。通知設定とマスタ管理を同一画面で扱います。</p>'
            . '<p>対象テナント: ' . $tenantName . ' (' . $tenantCode . ')</p>'
            . '<p><a class="btn btn-secondary" href="' . Layout::escape($dashboardUrl) . '">ダッシュボードへ戻る</a></p>'
            . $errorHtml
            . $successHtml
            . '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
            . '<a class="btn' . $notifyTabClass . '" href="' . Layout::escape($settingsUrl) . '&tab=notify">通知設定</a>'
            . '<a class="btn' . $masterTabClass . '" href="' . Layout::escape($settingsUrl) . '&tab=master">マスタ管理</a>'
            . '</div>'
            . '</div>';

        if ($activeTab === 'master') {
            $content .= $masterContent;
        } else {
            $content .= $notifyContent;
        }

        return Layout::render('テナント設定', $content);
    }

    /**
     * @param array<string, mixed> $renewal
     * @param array<string, mixed> $accident
     */
    private static function renderNotifyForm(array $renewal, array $accident, string $notifySaveUrl, string $notifyCsrfToken): string
    {
        $providers = ['lineworks', 'slack', 'teams', 'google_chat'];

        $renewalEnabled = ((int) ($renewal['is_enabled'] ?? 0) === 1) ? ' checked' : '';
        $accidentEnabled = ((int) ($accident['is_enabled'] ?? 0) === 1) ? ' checked' : '';

        $renewalProviderOptions = self::providerOptions($providers, (string) ($renewal['provider_type'] ?? 'lineworks'));
        $accidentProviderOptions = self::providerOptions($providers, (string) ($accident['provider_type'] ?? 'lineworks'));

        return ''
            . '<div class="card">'
            . '<h2>通知設定</h2>'
            . '<form method="post" action="' . Layout::escape($notifySaveUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($notifyCsrfToken) . '">'
            . '<div class="grid">'
            . '<div class="nav-item">'
            . '<h3>満期通知</h3>'
            . '<label><input type="checkbox" name="renewal_is_enabled" value="1"' . $renewalEnabled . '> 有効</label>'
            . '<label style="display:block;margin-top:8px;">通知先種別<select name="renewal_provider_type">' . $renewalProviderOptions . '</select></label>'
            . '<label style="display:block;margin-top:8px;">通知先名<input type="text" name="renewal_destination_name" value="' . Layout::escape((string) ($renewal['destination_name'] ?? 'renewal_default')) . '" maxlength="100"></label>'
            . '<label style="display:block;margin-top:8px;">Webhook URL<textarea name="renewal_webhook_url" rows="3" style="width:100%;">' . Layout::escape((string) ($renewal['webhook_url'] ?? '')) . '</textarea></label>'
            . '</div>'
            . '<div class="nav-item">'
            . '<h3>事故リマインド</h3>'
            . '<label><input type="checkbox" name="accident_is_enabled" value="1"' . $accidentEnabled . '> 有効</label>'
            . '<label style="display:block;margin-top:8px;">通知先種別<select name="accident_provider_type">' . $accidentProviderOptions . '</select></label>'
            . '<label style="display:block;margin-top:8px;">通知先名<input type="text" name="accident_destination_name" value="' . Layout::escape((string) ($accident['destination_name'] ?? 'accident_default')) . '" maxlength="100"></label>'
            . '<label style="display:block;margin-top:8px;">Webhook URL<textarea name="accident_webhook_url" rows="3" style="width:100%;">' . Layout::escape((string) ($accident['webhook_url'] ?? '')) . '</textarea></label>'
            . '</div>'
            . '</div>'
            . '<div style="margin-top:12px;"><button class="btn" type="submit">通知設定を保存</button></div>'
            . '</form>'
            . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $phases
     */
    private static function renderPhaseForms(array $phases, string $phaseSaveUrl, string $phaseCsrfToken): string
    {
        $rowsHtml = '';
        foreach ($phases as $phase) {
            $enabledChecked = ((int) ($phase['is_enabled'] ?? 0) === 1) ? ' checked' : '';
            $rowsHtml .= ''
                . '<form method="post" action="' . Layout::escape($phaseSaveUrl) . '" class="nav-item" style="margin-bottom:12px;">'
                . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($phaseCsrfToken) . '">'
                . '<input type="hidden" name="id" value="' . Layout::escape((string) ($phase['id'] ?? '0')) . '">'
                . '<p><strong>' . Layout::escape((string) ($phase['phase_code'] ?? '')) . '</strong> / ' . Layout::escape((string) ($phase['phase_name'] ?? '')) . '</p>'
                . '<div class="grid">'
                . '<label>開始残日数<input type="number" min="0" name="from_days_before" value="' . Layout::escape((string) ($phase['from_days_before'] ?? '0')) . '"></label>'
                . '<label>終了残日数<input type="number" min="0" name="to_days_before" value="' . Layout::escape((string) ($phase['to_days_before'] ?? '0')) . '"></label>'
                . '<label>表示順<input type="number" min="0" name="display_order" value="' . Layout::escape((string) ($phase['display_order'] ?? '0')) . '"></label>'
                . '<label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_enabled" value="1"' . $enabledChecked . '>有効</label>'
                . '</div>'
                . '<p class="muted">最終更新: ' . Layout::escape((string) ($phase['updated_at'] ?? '')) . '</p>'
                . '<button class="btn" type="submit">マスタ値を更新</button>'
                . '</form>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<p>登録済みの満期通知フェーズはありません。</p>';
        }

        return ''
            . '<div class="card">'
            . '<h2>マスタ管理</h2>'
            . '<p class="muted">満期通知フェーズをテナント単位で更新します。</p>'
            . $rowsHtml
            . '</div>';
    }

    /**
     * @param array<int, string> $providers
     */
    private static function providerOptions(array $providers, string $current): string
    {
        $html = '';
        foreach ($providers as $provider) {
            $selected = $provider === $current ? ' selected' : '';
            $html .= '<option value="' . Layout::escape($provider) . '"' . $selected . '>' . Layout::escape($provider) . '</option>';
        }

        return $html;
    }
}
