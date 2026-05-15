<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class ShareController
{
    public function view(): void
    {
        $code = trim((string)($_GET['code'] ?? ''));
        if (!$code) {
            http_response_code(400);
            echo 'Missing share code';
            return;
        }

        echo View::render('share/view', ['code' => $code]);
    }
}
