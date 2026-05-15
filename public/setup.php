<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$storageDir = $baseDir . '/storage';
$vendorAutoload = $baseDir . '/vendor/autoload.php';

function setupAssetPath(string $path): string
{
    $path = ltrim($path, '/');
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    if ($docRoot !== '' && is_file($docRoot . '/' . $path)) {
        return '/' . $path;
    }
    if ($docRoot !== '' && is_file($docRoot . '/public/' . $path)) {
        return '/public/' . $path;
    }

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }
    return ($scriptDir ? rtrim($scriptDir, '/') : '') . '/' . $path;
}

function canRunShell(): bool
{
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    $disabled = array_filter($disabled);
    foreach (['proc_open', 'shell_exec', 'exec', 'system', 'passthru'] as $fn) {
        if (function_exists($fn) && !in_array($fn, $disabled, true)) {
            return true;
        }
    }
    return false;
}

function runCommand(string $command, string $cwd): array
{
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    $disabled = array_filter($disabled);

    if (function_exists('proc_open') && !in_array('proc_open', $disabled, true)) {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($command, $descriptors, $pipes, $cwd);
        if (!is_resource($proc)) {
            return ['ok' => false, 'code' => 1, 'output' => '无法启动进程'];
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return ['ok' => $code === 0, 'code' => $code, 'output' => trim($stdout . "\n" . $stderr)];
    }

    if (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
        $out = shell_exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1');
        $out = (string)$out;
        $ok = str_contains($out, 'Generating autoload files') || is_file($cwd . '/vendor/autoload.php');
        return ['ok' => $ok, 'code' => $ok ? 0 : 1, 'output' => trim($out)];
    }

    return ['ok' => false, 'code' => 1, 'output' => '服务器禁用了命令执行函数'];
}

$actionMessage = '';
$actionOutput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install_vendor') {
    if (!canRunShell()) {
        $actionMessage = '当前 PHP 环境禁用了命令执行，无法自动安装依赖。';
    } elseif (!is_file($baseDir . '/composer.json')) {
        $actionMessage = '未找到 composer.json。';
    } else {
        $commands = [
            'COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-security-blocking',
            '/usr/bin/env COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-security-blocking',
            '/usr/bin/php /usr/bin/composer install --no-dev --no-security-blocking',
        ];

        $result = ['ok' => false, 'code' => 1, 'output' => ''];
        foreach ($commands as $cmd) {
            $result = runCommand($cmd, $baseDir);
            $actionOutput .= "$ $cmd\n" . $result['output'] . "\n\n";
            if ($result['ok'] || is_file($vendorAutoload)) {
                $result['ok'] = true;
                break;
            }
        }

        if ($result['ok'] && is_file($vendorAutoload)) {
            $actionMessage = '依赖安装成功。';
        } else {
            $actionMessage = '自动安装失败，请在 SSH 手动执行 composer install。';
        }
    }
}

$checks = [
    'php_version' => ['label' => 'PHP >= 8.0', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
    'pdo_mysql' => ['label' => 'pdo_mysql', 'ok' => extension_loaded('pdo_mysql')],
    'json' => ['label' => 'json', 'ok' => extension_loaded('json')],
    'storage_writable' => ['label' => 'storage 可写', 'ok' => is_dir($storageDir) && is_writable($storageDir)],
    'vendor_autoload' => ['label' => 'vendor/autoload.php', 'ok' => is_file($vendorAutoload)],
    'shell_exec' => ['label' => '自动安装能力', 'ok' => canRunShell()],
];

$allOk = true;
foreach ($checks as $item) {
    if (!$item['ok'] && $item['label'] !== '自动安装能力') {
        $allOk = false;
        break;
    }
}

$entry = '/index.php';
$install = '/install.php';
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>蘑菇网盘部署向导</title>
<link rel="stylesheet" href="<?= htmlspecialchars(setupAssetPath('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="bg-body-tertiary">
<div class="container py-4 py-lg-5">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-lg-5">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
            <div>
              <span class="badge text-bg-success mb-3">一键部署引导</span>
              <h1 class="h2 mb-2">蘑菇网盘环境检查</h1>
              <p class="text-secondary mb-0">该页面用于自动检查环境。程序支持通过 <code>index.php?r=...</code> 运行，不依赖伪静态。</p>
            </div>
            <div class="d-grid d-sm-flex gap-2 align-self-start">
              <a class="btn btn-success" href="<?= $install ?>">打开安装页</a>
              <a class="btn btn-outline-secondary" href="<?= $entry ?>">打开程序入口</a>
            </div>
          </div>

          <div class="list-group mb-4">
            <?php foreach ($checks as $c): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <span><?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="badge <?= $c['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $c['ok'] ? '通过' : '失败' ?></span>
            </div>
            <?php endforeach; ?>
          </div>

          <form method="post" class="mb-3">
            <input type="hidden" name="action" value="install_vendor">
            <button type="submit" class="btn btn-primary">自动安装 vendor 依赖</button>
          </form>

          <?php if ($actionMessage !== ''): ?>
          <div class="alert <?= str_contains($actionMessage, '成功') ? 'alert-success' : 'alert-warning' ?>" role="alert">
            <?= htmlspecialchars($actionMessage, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif; ?>

          <?php if ($actionOutput !== ''): ?>
          <pre class="border rounded-4 bg-dark text-light p-3 small mb-3"><?= htmlspecialchars($actionOutput, ENT_QUOTES, 'UTF-8') ?></pre>
          <?php endif; ?>

          <?php if (!$allOk): ?>
          <div class="alert alert-danger mb-0" role="alert">存在未通过项，请先修复后再安装。</div>
          <?php else: ?>
          <div class="alert alert-success mb-0" role="alert">环境检查通过，可以继续安装。</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= htmlspecialchars(setupAssetPath('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
