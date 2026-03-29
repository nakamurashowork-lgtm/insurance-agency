<?php
declare(strict_types=1);

namespace App\Http;

final class Responses
{
    public static function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    public static function html(string $html): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }
}
