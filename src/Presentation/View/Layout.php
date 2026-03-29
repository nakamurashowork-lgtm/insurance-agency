<?php
declare(strict_types=1);

namespace App\Presentation\View;

final class Layout
{
    public static function render(string $title, string $content): string
    {
        $safeTitle = self::escape($title);

        return '<!doctype html>'
            . '<html lang="ja">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $safeTitle . '</title>'
            . '<style>'
            . 'body{margin:0;font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;background:#f4f7fb;color:#1f2937;}'
            . '.container{max-width:900px;margin:32px auto;padding:0 16px;}'
            . '.card{background:#fff;border:1px solid #d9e2ec;border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 2px 6px rgba(0,0,0,0.04);}'
            . '.title{font-size:24px;font-weight:700;margin:0 0 12px;}'
            . '.muted{color:#52606d;font-size:14px;}'
            . '.btn{display:inline-block;background:#0b7285;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;border:none;cursor:pointer;}'
            . '.btn-secondary{background:#334e68;}'
            . '.notice{padding:10px 12px;background:#fff4e5;border:1px solid #ffd8a8;border-radius:8px;margin-bottom:12px;}'
            . '.error{padding:10px 12px;background:#ffe3e3;border:1px solid #ffa8a8;border-radius:8px;margin-bottom:12px;}'
            . '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}'
            . '.nav-item{border:1px solid #d9e2ec;border-radius:8px;padding:12px;background:#f8fafc;}'
            . '.helper{border-left:4px solid #334e68;padding-left:12px;}'
            . '</style>'
            . '</head>'
            . '<body>'
            . '<div class="container">'
            . $content
            . '</div>'
            . '</body>'
            . '</html>';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
