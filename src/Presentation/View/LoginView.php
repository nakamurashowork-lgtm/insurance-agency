<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class LoginView
{
    public static function render(?string $errorMessage, string $googleLoginUrl, ?string $devLoginUrl = null): string
    {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="alert alert-error" role="alert">'
                . '<div class="alert-icon">⚠</div>'
                . '<div class="alert-content">'
                . '<div class="alert-title">ログインできませんでした</div>'
                . '<div class="alert-message">' . Layout::escape($errorMessage) . '</div>'
                . '</div>'
                . '</div>';
        }

        $googleLoginLink = Layout::escape($googleLoginUrl);

        $content = ''
            . '<div class="card login-card">'
            . '<div class="login-header">'
            . '<h1 class="login-title">保険代理店業務システム</h1>'
            . '<h2 class="login-subtitle">Google認証でログイン</h2>'
            . '</div>'
            . $errorHtml
            . '<div class="login-actions">'
            . '<a id="login-btn" class="btn btn-primary btn-large" href="' . $googleLoginLink . '" data-loading="Googleへ移動中...">Googleでログイン</a>'
            . '</div>'
            . '<p class="login-helper-text">ログインできない場合は管理者へご連絡ください。</p>'
            . ($devLoginUrl !== null
                ? '<div class="dev-login-section">'
                    . '<hr style="margin:1.5rem 0;border:none;border-top:1px dashed #ccc;">'
                    . '<p style="font-size:0.75rem;color:#999;margin:0 0 0.5rem;">▼ ローカル開発専用 (APP_ENV=local)</p>'
                    . '<a class="btn btn-secondary" href="' . Layout::escape($devLoginUrl) . '">開発用ログイン (dev@local.test)</a>'
                    . '</div>'
                : '')
            . '</div>'
            . '<script>'
            . '(function(){const btn=document.getElementById("login-btn");if(!btn)return;let isSubmitting=false;btn.addEventListener("click",function(e){if(isSubmitting){e.preventDefault();return;}isSubmitting=true;const loadingText=btn.getAttribute("data-loading")||"移動中...";const originalText=btn.textContent;btn.textContent=loadingText;btn.style.opacity="0.6";btn.style.pointerEvents="none";setTimeout(function(){window.location.href=btn.href;},300);});})();'
            . '</script>';

        return Layout::render('ログイン', $content, [
            'showHeader' => false,
        ]);
    }
}
