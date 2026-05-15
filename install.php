<?php

declare(strict_types=1);

$configPath = __DIR__ . '/storage/config.php';
$lockPath = __DIR__ . '/storage/runtime/installed.lock';

function installAssetPath(string $path): string
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

if (is_file($lockPath)) {
    http_response_code(403);
    echo 'Already installed. Delete storage/runtime/installed.lock to reinstall.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim($_POST['site_name'] ?? '蘑菇网盘');
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');

    if ($siteName === '' || !$dbName || !$dbUser || !$adminUser || strlen($adminPass) < 8 || !$appUrl) {
        $error = '请填写完整，管理员密码至少8位。';
    } else {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            $pdo->exec($schema);

            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$adminUser, $hash, 'admin']);

            $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)');
            $stmt->execute(['chunk_size_bytes', (string)(30 * 1024 * 1024)]);
            $stmt->execute(['app_url', $appUrl]);
            $stmt->execute(['site_name', $siteName]);

            $storagePath = __DIR__ . '/storage/uploads';
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0775, true);
            }
            $stmt = $pdo->prepare('INSERT INTO storage_locations (name, driver, config_json, is_active, created_at) VALUES (?, ?, ?, 1, NOW())');
            $stmt->execute([
                'Local Default',
                'local',
                json_encode(['base_path' => realpath($storagePath) ?: $storagePath], JSON_UNESCAPED_UNICODE),
            ]);

            $signingKey = bin2hex(random_bytes(32));
            $config = "<?php\nreturn " . var_export([
                'db' => [
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                ],
                'app' => [
                    'name' => $siteName,
                    'url' => $appUrl,
                    'chunk_size' => 31457280,
                    'session_name' => 'mogudrive_sid',
                    'signing_key' => $signingKey,
                ],
            ], true) . ";\n";
            file_put_contents($configPath, $config);
            if (!is_dir(__DIR__ . '/storage/runtime')) {
                mkdir(__DIR__ . '/storage/runtime', 0775, true);
            }
            file_put_contents($lockPath, (string)time());
            $entry = '/public/index.php';
            if (is_file((string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/index.php')) {
                $entry = '/index.php';
            }
            header('Location: ' . $entry);
            exit;
        } catch (Throwable $e) {
            $error = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>蘑菇网盘安装向导</title>
<link rel="stylesheet" href="<?= htmlspecialchars(installAssetPath('assets/vendor/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="bg-body-tertiary">
<div class="container py-4 py-lg-5">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <div class="card border-0 shadow-sm overflow-hidden">
        <div class="row g-0">
          <div class="col-lg-5 bg-success-subtle border-end">
            <div class="p-4 p-lg-5 h-100 d-flex flex-column justify-content-between">
              <div>
                <span class="badge text-bg-success mb-3">Bootstrap 安装向导</span>
                <h1 class="display-6 fw-semibold mb-3">欢迎部署蘑菇网盘</h1>
                <p class="text-secondary mb-0">首次启动时可以直接设置站点名称、数据库连接和管理员账号。安装完成后，系统会自动进入网盘后台。</p>
              </div>
              <div class="small text-secondary mt-4">
                安装成功后会写入 <code>storage/runtime/installed.lock</code>，此页面自动失效。
              </div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="p-4 p-lg-5">
              <h2 class="h4 mb-4">首次初始化</h2>
              <?php if (!empty($error)): ?>
              <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <form method="post" class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="siteName">网盘名称</label>
                  <input class="form-control" id="siteName" name="site_name" placeholder="例如：蘑菇网盘" value="<?= htmlspecialchars($_POST['site_name'] ?? '蘑菇网盘', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="appUrl">站点地址</label>
                  <input class="form-control" id="appUrl" name="app_url" placeholder="如 https://files.example.com" value="<?= htmlspecialchars($_POST['app_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="dbHost">MySQL Host</label>
                  <input class="form-control" id="dbHost" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="dbPort">MySQL Port</label>
                  <input class="form-control" id="dbPort" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="dbName">MySQL 数据库名</label>
                  <input class="form-control" id="dbName" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="dbUser">MySQL 用户名</label>
                  <input class="form-control" id="dbUser" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="dbPass">MySQL 密码</label>
                  <input class="form-control" type="password" id="dbPass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="adminUser">管理员账号</label>
                  <input class="form-control" id="adminUser" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="adminPass">管理员密码</label>
                  <input class="form-control" type="password" id="adminPass" name="admin_pass" placeholder="至少 8 位" required>
                </div>
                <div class="col-12 d-grid d-sm-flex gap-2">
                  <button type="submit" class="btn btn-success btn-lg">执行安装</button>
                  <a href="setup.php" class="btn btn-outline-secondary btn-lg">返回环境检查</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= htmlspecialchars(installAssetPath('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
