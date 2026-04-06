<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class TotpView
{
    public static function render(
        string $csrfToken,
        string $verifyUrl,
        ?string $errorMessage
    ): string {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="alert alert-error" role="alert">'
                . '<div class="alert-icon">&#x26A0;</div>'
                . '<div class="alert-content">'
                . '<div class="alert-title">認証できませんでした</div>'
                . '<div class="alert-message">' . Layout::escape($errorMessage) . '</div>'
                . '</div>'
                . '</div>';
        }

        $actionUrl = Layout::escape($verifyUrl);
        $csrf      = Layout::escape($csrfToken);

        $content = ''
            . '<div class="card login-card">'
            . '<div class="login-header">'
            . '<h1 class="login-title">保険代理店業務システム</h1>'
            . '<h2 class="login-subtitle">2段階認証</h2>'
            . '</div>'
            . $errorHtml
            . '<p style="font-size:13px;color:var(--text-secondary);margin:0 0 16px;">Google Authenticator などの認証アプリに表示されている6桁のコードを入力してください。</p>'
            . '<form method="post" action="' . $actionUrl . '" id="totp-form">'
            . '<input type="hidden" name="_csrf_token" value="' . $csrf . '">'
            . '<div style="margin-bottom:16px;">'
            . '<label for="totp_code" style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">認証コード</label>'
            . '<input id="totp_code" type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required'
            . ' style="width:100%;padding:10px 12px;font-size:18px;letter-spacing:4px;border:1px solid var(--border-medium);border-radius:var(--radius-md);text-align:center;"'
            . ' placeholder="000000">'
            . '</div>'
            . '<button type="submit" class="btn btn-primary btn-large">確認</button>'
            . '</form>'
            . '<p style="margin-top:16px;font-size:12px;color:var(--text-secondary);text-align:center;">コードはアプリ上で30秒ごとに更新されます。</p>'
            . '</div>'
            . '<script>'
            . '(function(){'
            . 'var inp=document.getElementById("totp_code");'
            . 'if(inp){inp.addEventListener("input",function(){this.value=this.value.replace(/\D/g,"");if(this.value.length===6){document.getElementById("totp-form").submit();}});}'
            . '})();'
            . '</script>';

        return Layout::render('2段階認証', $content, ['showHeader' => false]);
    }
}
