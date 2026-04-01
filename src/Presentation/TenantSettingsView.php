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
     * @param array<string, mixed> $layoutOptions
     */
    public static function render(
        array $auth,
        array $notifySettings,
        array $phases,
        string $saveUrl,
        string $csrfToken,
        ?string $errorMessage,
        ?string $flashError,
        ?string $flashSuccess,
        array $layoutOptions
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

        $renewal = $notifySettings['renewal'] ?? [];
        $accident = $notifySettings['accident'] ?? [];

        $notifyContent = self::renderNotifyForm(
            $renewal,
            $accident,
            $phases,
            $saveUrl,
            $csrfToken
        );

        $tenantName = Layout::escape((string) ($auth['tenant_name'] ?? ''));
        $tenantCode = Layout::escape((string) ($auth['tenant_code'] ?? ''));

        $content = ''
            . '<div style="margin-bottom:16px;">'
            . '<h1 class="title">管理・設定</h1>'
            . '<p class="muted" style="margin:0;">対象代理店: ' . $tenantName . ' (' . $tenantCode . ')</p>'
            . '</div>'
            . $errorHtml
            . $successHtml;

        $content .= $notifyContent;

        return Layout::render('管理・設定', $content, $layoutOptions);
    }

    /**
     * @param array<string, mixed> $renewal
     * @param array<string, mixed> $accident
     */
    private static function renderNotifyForm(
        array $renewal,
        array $accident,
        array $phases,
        string $saveUrl,
        string $csrfToken
    ): string
    {
        $providers = self::availableProviders();

        $renewalEnabled = ((int) ($renewal['is_enabled'] ?? 0) === 1) ? ' checked' : '';
        $accidentEnabled = ((int) ($accident['is_enabled'] ?? 0) === 1) ? ' checked' : '';

        $renewalProviderOptions = self::providerOptions($providers, (string) ($renewal['provider_type'] ?? 'lineworks'));
        $accidentProviderOptions = self::providerOptions($providers, (string) ($accident['provider_type'] ?? 'lineworks'));

        return ''
            . '<form method="post" action="' . Layout::escape($saveUrl) . '">'
            . '<input type="hidden" name="_csrf_token" value="' . Layout::escape($csrfToken) . '">'
            . '<div class="card">'
            . '<h2>通知設定</h2>'
            . '<div class="nav-item" style="margin-bottom:12px;">'
            . '<h3>満期通知</h3>'
            . '<p class="muted" style="margin:0 0 10px;">満期案件の通知先と通知タイミングを設定します。</p>'
            . '<label><input type="checkbox" name="renewal_is_enabled" value="1"' . $renewalEnabled . '> 有効</label>'
            . self::renderProviderField('renewal_provider_type', (string) ($renewal['provider_type'] ?? 'lineworks'), $renewalProviderOptions, $providers)
            . self::renderDestinationNameField('renewal_destination_name', (string) ($renewal['destination_name'] ?? 'renewal_default'))
            . '<label style="display:block;margin-top:8px;">Webhook URL<textarea name="renewal_webhook_url" rows="3" style="width:100%;">' . Layout::escape((string) ($renewal['webhook_url'] ?? '')) . '</textarea></label>'
            . self::renderTimingRows($phases)
            . '</div>'
            . '<div class="nav-item">'
            . '<h3>事故通知</h3>'
            . '<p class="muted" style="margin:0 0 10px;">事故通知の通知先を設定します。</p>'
            . '<label><input type="checkbox" name="accident_is_enabled" value="1"' . $accidentEnabled . '> 有効</label>'
            . self::renderProviderField('accident_provider_type', (string) ($accident['provider_type'] ?? 'lineworks'), $accidentProviderOptions, $providers)
            . self::renderDestinationNameField('accident_destination_name', (string) ($accident['destination_name'] ?? 'accident_default'))
            . '<label style="display:block;margin-top:8px;">Webhook URL<textarea name="accident_webhook_url" rows="3" style="width:100%;">' . Layout::escape((string) ($accident['webhook_url'] ?? '')) . '</textarea></label>'
            . '</div>'
            . '</div>'
            . '<div style="margin-top:20px;"><button class="btn btn-primary" type="submit">保存</button></div>'
            . '</form>';
    }

    /**
     * @param array<int, array<string, mixed>> $phases
     */
    private static function renderTimingRows(array $phases): string
    {
        if ($phases === []) {
            return '';
        }

        $orderedPhases = self::orderPhasesForDisplay($phases);
        $rowsHtml = '';
        foreach ($orderedPhases as $phase) {
            $meta = self::phaseUiMeta($phase);
            $id = (int) ($phase['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $from = Layout::escape((string) ($phase['from_days_before'] ?? '0'));
            $to   = Layout::escape((string) ($phase['to_days_before'] ?? '0'));
            $displayOrder = Layout::escape((string) ($phase['display_order'] ?? '0'));
            $rowsHtml .= ''
                . '<div style="display:flex;align-items:center;gap:8px;margin-top:8px;">'
                . '<span style="min-width:76px;font-weight:700;">' . Layout::escape($meta['label']) . '</span>'
                . '<input type="number" min="0" name="phases[' . $id . '][from_days_before]" value="' . $from . '" style="width:70px;padding:4px 8px;">'
                . '<span>日前　〜</span>'
                . '<input type="number" min="0" name="phases[' . $id . '][to_days_before]" value="' . $to . '" style="width:70px;padding:4px 8px;">'
                . '<span>日前</span>'
                . '<input type="hidden" name="phases[' . $id . '][display_order]" value="' . $displayOrder . '">'
                . '</div>';
        }

        if ($rowsHtml === '') {
            return '';
        }

        return ''
            . '<div style="margin-top:16px;">'
            . '<h4 style="margin:0 0 8px;">通知タイミング</h4>'
            . $rowsHtml
            . '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $phases
     * @return array<int, array<string, mixed>>
     */
    private static function orderPhasesForDisplay(array $phases): array
    {
        usort($phases, static function (array $a, array $b): int {
            $priority = [
                'PH5' => 10,
                'PH6' => 20,
            ];

            $aCode = (string) ($a['phase_code'] ?? '');
            $bCode = (string) ($b['phase_code'] ?? '');

            $aPriority = $priority[$aCode] ?? 100;
            $bPriority = $priority[$bCode] ?? 100;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            return ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));
        });

        return $phases;
    }

    /**
     * @param array<string, mixed> $phase
     * @return array{label:string,description:string}
     */
    private static function phaseUiMeta(array $phase): array
    {
        $phaseCode = (string) ($phase['phase_code'] ?? '');

        return match ($phaseCode) {
            'PH5' => [
                'label' => '早期通知',
                'description' => '満期日の90日前から30日前まで通知',
            ],
            'PH6' => [
                'label' => '直前通知',
                'description' => '満期日の30日前から当日まで通知',
            ],
            default => [
                'label' => '通知タイミング',
                'description' => '満期通知の送信タイミングを設定',
            ],
        };
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

    /**
     * @return array<int, string>
     */
    private static function availableProviders(): array
    {
        return ['lineworks'];
    }

    /**
     * @param array<int, string> $providers
     */
    private static function renderProviderField(string $fieldName, string $current, string $optionsHtml, array $providers): string
    {
        $provider = $current !== '' ? $current : 'lineworks';

        if (count($providers) > 1) {
            return '<label style="display:block;margin-top:8px;">通知先種別<select name="' . Layout::escape($fieldName) . '">' . $optionsHtml . '</select></label>';
        }

        return '<div style="display:block;margin-top:8px;">通知先: ' . self::providerLabel($provider) . '</div>'
            . '<input type="hidden" name="' . Layout::escape($fieldName) . '" value="' . Layout::escape($provider) . '">';
    }

    private static function renderDestinationNameField(string $fieldName, string $current): string
    {
        return '<input type="hidden" name="' . Layout::escape($fieldName) . '" value="' . Layout::escape($current) . '">';
    }

    private static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'lineworks' => 'LINE WORKS',
            'google_chat' => 'Google Chat',
            'slack' => 'Slack',
            'teams' => 'Microsoft Teams',
            default => strtoupper(str_replace('_', ' ', $provider)),
        };
    }
}
