<?php

$siteName = \App\Core\Settings::siteName();

ob_start();
?>
<div class="row justify-content-center align-items-center min-vh-100 py-4">
  <div class="col-12 col-md-10 col-lg-7 col-xl-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <div class="text-center mb-4">
          <span class="badge rounded-pill bg-success-subtle border border-success-subtle text-success-emphasis px-3 py-2">Bootstrap 后台</span>
          <h1 class="h2 mt-3 mb-2"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="text-secondary mb-0">管理员登录</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars(\App\Core\Url::route('/login'), ENT_QUOTES, 'UTF-8') ?>" class="row g-3">
          <div class="col-12">
            <label class="form-label" for="username">账号</label>
            <input class="form-control form-control-lg" id="username" name="username" autocomplete="username" required>
          </div>
          <div class="col-12">
            <label class="form-label" for="password">密码</label>
            <input class="form-control form-control-lg" type="password" id="password" name="password" autocomplete="current-password" required>
          </div>
          <div class="col-12 d-grid">
            <button type="submit" class="btn btn-success btn-lg">登录后台</button>
          </div>
        </form>

        <div class="small text-secondary mt-4">
          首次部署请先访问 <code>/install.php</code>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$title = '登录';
$bodyClass = 'app-body auth-page';
$mainClass = 'container py-0';
require __DIR__ . '/../layout/base.php';
