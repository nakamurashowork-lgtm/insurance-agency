<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class LoginView
{
    public static function render(?string $errorMessage, string $googleLoginUrl): string
    {
        $errorHtml = '';
        if (is_string($errorMessage) && $errorMessage !== '') {
            $errorHtml = '<div class="error">' . Layout::escape($errorMessage) . '</div>';
        }

        $googleLoginLink = Layout::escape($googleLoginUrl);

        $content = ''
            . '<div class="card">'
            . '<h1 class="title">ログイン</h1>'
            . '<p class="muted">Google認証でログインしてください。ID / パスワードの直接入力は行いません。</p>'
            . '</div>'
            . '<div class="card">'
            . $errorHtml
            . '<a class="btn" href="' . $googleLoginLink . '">Googleでログイン</a>'
            . '</div>';

        return Layout::render('ログイン', $content);
    }
}
