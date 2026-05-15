<?php

$entryUrl = \App\Core\Url::entry();
$cssUrl = \App\Core\Url::asset('assets/css/app.css');
$bootstrapCssUrl = \App\Core\Url::asset('assets/vendor/bootstrap/css/bootstrap.min.css');
$bootstrapJsUrl = \App\Core\Url::asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js');
$cssVer = '20260430-5';
$siteName = \App\Core\Settings::siteName();
$pageTitle = trim((string)($title ?? ''));
$fullTitle = $pageTitle === ''
    ? $siteName
    : (str_contains($pageTitle, $siteName) ? $pageTitle : $pageTitle . ' - ' . $siteName);
$mainClass = trim((string)($mainClass ?? 'container py-4 py-lg-5'));
$bodyClass = trim((string)($bodyClass ?? 'app-body'));
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?= htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($bootstrapCssUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl . '?v=' . $cssVer, ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.href='/public/assets/css/app.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES, 'UTF-8') ?>';">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="app-bg"></div>
  <script>
  window.__ENTRY__ = <?= json_encode($entryUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  window.__SITE_NAME__ = <?= json_encode($siteName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <main class="<?= htmlspecialchars($mainClass, ENT_QUOTES, 'UTF-8') ?>"><?= $content ?? '' ?></main>
  <script src="<?= htmlspecialchars($bootstrapJsUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
