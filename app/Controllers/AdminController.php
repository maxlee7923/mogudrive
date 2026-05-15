<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\ShareStore;
use App\Core\Url;
use App\Core\View;
use PDO;

final class AdminController
{
    public function home(): void
    {
        if (!Auth::check()) {
            echo View::render('admin/login');
            return;
        }
        $this->page('upload');
    }

    public function page(string $section): void
    {
        if (!Auth::check()) {
            echo View::render('admin/login');
            return;
        }

        $allowed = ['upload', 'files', 'shares', 'system', 'custom', 'storage'];
        if (!in_array($section, $allowed, true)) {
            $section = 'upload';
        }

        $pdo = Db::pdo();
        $storage = $pdo->query('SELECT id, name, driver, is_active, created_at FROM storage_locations ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $files = [];
        $shares = ShareStore::listSummary(300);

        echo View::render('admin/dashboard', [
            'user' => Auth::user(),
            'storage' => $storage,
            'files' => $files,
            'shares' => $shares,
            'active' => $section,
        ]);
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . Url::entry());
            return;
        }
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo View::render('admin/login', ['error' => '账号或密码错误']);
            return;
        }

        Auth::login($user);
        header('Location: ' . Url::route('/admin/upload'));
    }

    public function logout(): void
    {
        Auth::logout();
        session_destroy();
        header('Location: ' . Url::entry());
    }
}
