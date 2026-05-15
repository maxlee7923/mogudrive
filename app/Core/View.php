<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $tpl, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/../Views/' . $tpl . '.php';
        return (string)ob_get_clean();
    }
}
