<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class TotpSetupView
{
    public static function render(
        string $base32Secret,
        string $otpAuthUri,
        string $csrfToken,
        string $verifyUrl,
        ?string $errorMessage
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="alert alert-error" role="alert">'
                . '<div class="alert-icon">&#x26A0;</div>'
                . '<div class="alert-content">'
                . '<div class="alert-title">確認できませんでした</div>'
                . '<div class="alert-message">' . Layout::escape($errorMessage) . '</div>'
                . '</div>'
                . '</div>';
        }

        $actionUrl   = Layout::escape($verifyUrl);
        $csrf        = Layout::escape($csrfToken);
        $safeSecret  = Layout::escape($base32Secret);
        $qrUrl       = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=M&data=' . rawurlencode($otpAuthUri);
        $safeQrUrl   = Layout::escape($qrUrl);

        // Display secret in groups of 4 for readability.
        $displaySecret = implode(' ', str_split($base32Secret, 4));

        $content = ''
            . '<div class="card login-card" style="max-width:480px;">'
            . '<div class="login-header">'
            . '<h1 class="login-title">保険代理店業務システム</h1>'
            . '<h2 class="login-subtitle">2段階認証の初期設定</h2>'
            . '</div>'
            . $errorHtml
            . '<div class="notice" style="margin-bottom:16px;">'
            . '<strong>初回設定が必要です</strong><br>'
            . '<span style="font-size:12px;">このシステムでは2段階認証（TOTP）が必須です。Google Authenticator などの認証アプリで下記QRコードをスキャンしてください。</span>'
            . '</div>'
            . '<div style="text-align:center;margin-bottom:16px;">'
            . '<img src="' . $safeQrUrl . '" alt="TOTP QRコード" width="200" height="200" style="border:1px solid var(--border-light);border-radius:8px;">'
            . '</div>'
            . '<div style="margin-bottom:16px;">'
            . '<p style="font-size:12px;color:var(--text-secondary);margin:0 0 6px;">QRコードが読み取れない場合はこのキーを手動で入力してください：</p>'
            . '<div style="font-family:monospace;font-size:13px;background:var(--bg-tertiary);padding:8px 12px;border-radius:6px;word-break:break-all;text-align:center;">'
            . Layout::escape($displaySecret)
            . '</div>'
            . '</div>'
            . '<hr style="border:none;border-top:0.5px solid var(--border-light);margin:16px 0;">'
            . '<p style="font-size:13px;margin:0 0 12px;">アプリに登録したら、表示されている<strong>6桁のコード</strong>を入力して設定を完了してください。</p>'
            . '<form method="post" action="' . $actionUrl . '" id="totp-setup-form">'
            . '<input type="hidden" name="_csrf_token" value="' . $csrf . '">'
            . '<div style="margin-bottom:16px;">'
            . '<label for="totp_code" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">認証コード</label>'
            . '<input id="totp_code" type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required'
            . ' style="width:100%;padding:10px 12px;font-size:18px;letter-spacing:4px;border:1px solid var(--border-medium);border-radius:var(--radius-md);text-align:center;"'
            . ' placeholder="000000">'
            . '</div>'
            . '<button type="submit" class="btn btn-primary btn-large">設定を完了する</button>'
            . '</form>'
            . '</div>'
            . '<script>'
            . '(function(){'
            . 'var inp=document.getElementById("totp_code");'
            . 'if(inp){inp.addEventListener("input",function(){this.value=this.value.replace(/\D/g,"");if(this.value.length===6){document.getElementById("totp-setup-form").submit();}});}'
            . '})();'
            . '</script>';

        return Layout::render('2段階認証の初期設定', $content, ['showHeader' => false]);
    }
}
