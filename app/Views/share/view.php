<?php

$siteName = \App\Core\Settings::siteName();
$brandInitial = function_exists('mb_substr') ? mb_substr($siteName, 0, 1) : substr($siteName, 0, 1);

ob_start();
?>
<section class="share-shell">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
      <div class="share-head">
        <div class="share-brand">
          <div class="share-brand-mark"><?= htmlspecialchars($brandInitial, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="share-brand-copy">
            <h1 id="shareTitle" class="h2 mb-2" data-i18n="brand"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p id="shareSubtitle" class="text-secondary mb-0" data-i18n="share_subtitle">文件分享与下载</p>
          </div>
        </div>
        <div class="share-head-actions">
          <button class="btn btn-outline-secondary" id="langSwitchShare" type="button" data-i18n="lang_toggle">English</button>
          <div id="customButtons" class="custom-buttons"></div>
        </div>
      </div>
    </div>
  </div>

  <div id="shareInfoCard" class="card border-0 shadow-sm hidden">
    <div class="card-body p-4">
      <div class="share-info-kicker" data-i18n="share_heading">分享标题</div>
      <div id="shareDisplayTitle" class="share-display-title"></div>
      <div id="shareDisplayMeta" class="share-display-meta text-secondary small"></div>
    </div>
  </div>

  <div id="shareNotice" class="alert alert-warning hidden mb-0"></div>

  <div id="lockedBox" class="card border-0 shadow-sm hidden">
    <div class="card-body p-4">
      <p class="mb-3" data-i18n="locked_tip">该链接已加密，请输入提取码。</p>
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label class="form-label" for="unlockPwd">提取码</label>
          <input class="form-control" id="unlockPwd" data-i18n-placeholder="extract_code" placeholder="提取码">
        </div>
        <div class="col-md-4 d-grid">
          <button class="btn btn-success" id="unlockBtn" data-i18n="unlock">解锁</button>
        </div>
      </div>
      <div id="unlockErr" class="log small mt-3"></div>
    </div>
  </div>

  <div id="filesBox" class="card border-0 shadow-sm hidden">
    <div class="card-body p-4">
      <div class="table-responsive border rounded-4">
        <table class="table table-hover align-middle mb-0 download-table">
          <thead class="table-light">
            <tr>
              <th class="file-icon-col"></th>
              <th data-i18n="col_filename">文件名</th>
              <th data-i18n="col_size">大小</th>
              <th class="action-col" data-i18n="col_action">操作</th>
            </tr>
          </thead>
          <tbody id="shareFiles"></tbody>
        </table>
      </div>
      <div id="downloadLog" class="log small mt-3"></div>
      <button id="downloadAllBtn" class="btn btn-success btn-lg mt-3 zip-fab" data-i18n="download_zip">一键下载 ZIP</button>
    </div>
  </div>
</section>
<script>
window.__SHARE_CODE__ = <?= json_encode($code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__SHARE_ICON_BASE__ = <?= json_encode(dirname(\App\Core\Url::asset('assets/icons/share/file.svg')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(\App\Core\Url::asset('assets/vendor/jszip/jszip.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Url::asset('assets/js/share.js') . '?v=20260430-2', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
$content = ob_get_clean();
$title = '分享';
$bodyClass = 'app-body share-page';
$mainClass = 'container py-4 py-lg-5';
require __DIR__ . '/../layout/base.php';
