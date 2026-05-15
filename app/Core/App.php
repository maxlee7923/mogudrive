<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\ShareController;
use Throwable;

final class App
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
    }

    public function run(): void
    {
        $path = '/';
        try {
            if (!is_file($this->basePath . '/storage/config.php')) {
                $installInDocRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/install.php';
                if (is_file($installInDocRoot)) {
                    header('Location: ' . Url::scriptDir() . '/install.php');
                } else {
                    header('Location: /install.php');
                }
                return;
            }
            Config::load($this->basePath);
            session_name((string)Config::get('app.session_name', 'mogudrive_sid'));
            session_start();

            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $routeFromQuery = trim((string)($_GET['r'] ?? ''));
            if ($routeFromQuery !== '') {
                $decodedRoute = rawurldecode($routeFromQuery);
                $routePath = $decodedRoute;
                $routeQuery = '';
                $qPos = strpos($decodedRoute, '?');
                if ($qPos !== false) {
                    $routePath = substr($decodedRoute, 0, $qPos);
                    $routeQuery = substr($decodedRoute, $qPos + 1);
                }
                $path = '/' . ltrim((string)$routePath, '/');
                if ($routeQuery !== '') {
                    parse_str($routeQuery, $extraParams);
                    if (is_array($extraParams)) {
                        $_GET = array_merge($_GET, $extraParams);
                    }
                }
            } else {
                $scriptDir = Url::scriptDir();
                if ($scriptDir !== '' && str_starts_with($path, $scriptDir . '/')) {
                    $path = substr($path, strlen($scriptDir));
                }
            }

            if ($path === '/' || $path === '/index.php') {
                (new AdminController())->home();
                return;
            }
            if ($path === '/login') {
                (new AdminController())->login();
                return;
            }
            if ($path === '/logout') {
                (new AdminController())->logout();
                return;
            }
            if ($path === '/admin/upload') {
                (new AdminController())->page('upload');
                return;
            }
            if ($path === '/admin') {
                (new AdminController())->page('upload');
                return;
            }
            if ($path === '/admin/storage') {
                (new AdminController())->page('storage');
                return;
            }
            if ($path === '/admin/files') {
                (new AdminController())->page('files');
                return;
            }
            if ($path === '/admin/shares') {
                (new AdminController())->page('shares');
                return;
            }
            if ($path === '/admin/system') {
                (new AdminController())->page('system');
                return;
            }
            if ($path === '/admin/custom') {
                (new AdminController())->page('custom');
                return;
            }
            if ($path === '/share') {
                (new ShareController())->view();
                return;
            }

            if (str_starts_with($path, '/api/')) {
                (new ApiController())->dispatch(substr($path, strlen('/api/')));
                return;
            }

            http_response_code(404);
            echo 'Not Found';
        } catch (Throwable $e) {
            if (str_starts_with($path, '/api/')) {
                Response::json(['ok' => false, 'message' => $e->getMessage()], 500);
                return;
            }
            http_response_code(500);
            echo 'Server Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
