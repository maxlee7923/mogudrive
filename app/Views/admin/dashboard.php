<?php

ob_start();

$siteName = \App\Core\Settings::siteName();
$brandInitial = function_exists('mb_substr') ? mb_substr($siteName, 0, 1) : substr($siteName, 0, 1);
$menu = [
    'upload' => ['label' => '上传中心', 'route' => \App\Core\Url::route('/admin/upload')],
    'files' => ['label' => '文件列表', 'route' => \App\Core\Url::route('/admin/files')],
    'shares' => ['label' => '分享管理', 'route' => \App\Core\Url::route('/admin/shares')],
    'system' => ['label' => '系统信息', 'route' => \App\Core\Url::route('/admin/system')],
    'custom' => ['label' => '按钮设置', 'route' => \App\Core\Url::route('/admin/custom')],
    'storage' => ['label' => '存储设置', 'route' => \App\Core\Url::route('/admin/storage')],
];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$origin = $host ? ($scheme . '://' . $host) : '';

?>
<div class="admin-shell" id="adminShell">
  <aside id="sidebar">
    <div class="card border-0 shadow-sm sidebar-card">
      <div class="card-body p-3 p-xl-4">
        <div class="brand-stack mb-3">
          <div class="brand-mark"><?= htmlspecialchars($brandInitial, ENT_QUOTES, 'UTF-8') ?></div>
          <div class="brand-copy min-w-0">
            <div class="text-secondary text-uppercase small fw-semibold brand">Mogu Drive</div>
            <div class="fw-semibold fs-5 site-name text-truncate"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
        <p class="sidebar-meta text-secondary small mb-3">Bootstrap 管理控制台</p>
        <button class="btn btn-outline-secondary btn-sm w-100 mb-3" id="toggleSidebar" type="button" data-i18n="toggle_sidebar">展开/收起侧栏</button>
        <nav class="side-nav">
          <?php foreach ($menu as $key => $item): ?>
          <a class="side-link <?= $active === $key ? 'active' : '' ?>" href="<?= htmlspecialchars($item['route'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="side-dot"></span>
            <span class="side-text" data-menu-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <?php endforeach; ?>
        </nav>
      </div>
    </div>
  </aside>

  <section class="admin-main">
    <header class="card border-0 shadow-sm page-hero">
      <div class="card-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
        <div>
          <p class="text-secondary text-uppercase small fw-semibold mb-2">Bootstrap Dashboard</p>
          <h1 class="h2 mb-1" id="pageTitle"><?= htmlspecialchars($menu[$active]['label'] ?? '控制台', ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="text-secondary mb-0"><span data-i18n="current_user">当前用户</span>: <?= htmlspecialchars($user['username'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-outline-secondary" id="langSwitchAdmin" type="button" data-i18n="lang_toggle">English</button>
          <a class="btn btn-outline-danger" href="<?= htmlspecialchars(\App\Core\Url::route('/logout'), ENT_QUOTES, 'UTF-8') ?>" data-i18n="logout">退出登录</a>
        </div>
      </div>
    </header>

    <section id="adminContent" class="admin-content" data-admin-page="<?= htmlspecialchars($active, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($active === 'upload'): ?>
    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
          <div>
            <h2 class="h4 mb-1" data-i18n="upload_tasks">上传任务</h2>
            <p class="text-secondary mb-0">选择文件或文件夹后上传到指定存储位置。</p>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label" for="fileInput">选择文件</label>
            <input class="form-control" type="file" id="fileInput" multiple>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="folderInput">选择文件夹</label>
            <input class="form-control" type="file" id="folderInput" webkitdirectory directory multiple>
          </div>
        </div>

        <div class="row g-3 align-items-end mb-4">
          <div class="col-lg-8">
            <label class="form-label" for="storageSelect">存储位置</label>
            <select class="form-select" id="storageSelect"></select>
          </div>
          <div class="col-lg-4 d-grid">
            <button class="btn btn-success btn-lg" id="uploadBtn" data-i18n="start_upload">开始上传</button>
          </div>
        </div>

        <div class="table-responsive border rounded-4 mb-3">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th data-i18n="col_filename">文件名</th>
                <th data-i18n="col_progress">进度</th>
                <th data-i18n="col_speed">网速</th>
                <th data-i18n="col_status">状态</th>
              </tr>
            </thead>
            <tbody id="uploadTasks"></tbody>
          </table>
        </div>

        <div id="uploadLog" class="log small"></div>
      </div>
    </article>
    <?php endif; ?>

    <?php if ($active === 'storage'): ?>
    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 mb-3" data-i18n="add_storage">新增存储位置</h2>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="stName">名称</label>
            <input class="form-control" id="stName" data-i18n-placeholder="name" placeholder="名称">
          </div>
          <div class="col-12">
            <label class="form-label" for="stDriver">驱动类型</label>
            <select class="form-select" id="stDriver">
              <option value="local" data-i18n="local_storage">本地存储</option>
              <option value="s3">S3 存储</option>
            </select>
          </div>
        </div>
        <div id="stFields" class="mt-3"></div>
        <div class="mt-4 d-grid d-sm-flex gap-2">
          <button class="btn btn-success" id="saveStorage" data-i18n="save_storage">保存存储</button>
        </div>
      </div>
    </article>

    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 mb-3" data-i18n="storage_list">存储列表</h2>
        <div class="table-responsive border rounded-4">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th data-i18n="col_name">名称</th>
                <th data-i18n="col_driver">驱动</th>
                <th data-i18n="col_enabled">启用</th>
                <th data-i18n="col_created_at">创建时间</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($storage as $s): ?>
              <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($s['driver'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge <?= (int)$s['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int)$s['is_active'] === 1 ? '是' : '否' ?></span></td>
                <td><?= htmlspecialchars($s['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </article>
    <?php endif; ?>

    <?php if ($active === 'files'): ?>
    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="mb-4">
          <h2 class="h4 mb-1">文件列表</h2>
          <p class="text-secondary mb-0">参考谷歌网盘重新整理，保留核心工具条，把文件浏览器做成主舞台。</p>
        </div>

        <div id="fileManagerApp" class="file-manager-shell">
          <section class="fm-top-stage">
            <div class="fm-panel fm-command-board">
              <div class="fm-drive-head">
                <div class="fm-drive-copy">
                  <div class="fm-panel-kicker">Drive Workspace</div>
                  <h3 class="h5 mb-1">资源工作区</h3>
                  <p class="text-secondary small mb-0">保留最常用的搜索、排序和批量操作，把主视野还给文件浏览器。</p>
                </div>
                <div class="files-storage-picker fm-storage-picker-inline">
                  <label class="form-label mb-0" for="fmStorageSelect" data-fm-i18n="storage_location">存储位置</label>
                  <select class="form-select" id="fmStorageSelect"></select>
                </div>
              </div>

              <div class="fm-drive-controls">
                <div class="fm-toolbar-actions">
                  <div class="fm-search-wrap">
                    <label class="visually-hidden" for="fmSearchInput" data-fm-i18n="search_current_folder">搜索当前目录</label>
                    <input class="form-control" id="fmSearchInput" data-fm-placeholder="search_current_folder" placeholder="搜索当前目录中的文件或文件夹">
                  </div>
                  <div class="fm-sort-wrap">
                    <label class="visually-hidden" for="fmSortSelect" data-fm-i18n="sort_by">排序方式</label>
                    <select class="form-select" id="fmSortSelect">
                      <option value="name_asc" data-fm-i18n="sort_name_asc">名称 A-Z</option>
                      <option value="name_desc" data-fm-i18n="sort_name_desc">名称 Z-A</option>
                      <option value="created_desc" data-fm-i18n="sort_created_desc">最新上传</option>
                      <option value="created_asc" data-fm-i18n="sort_created_asc">最早上传</option>
                      <option value="size_desc" data-fm-i18n="sort_size_desc">体积从大到小</option>
                      <option value="size_asc" data-fm-i18n="sort_size_asc">体积从小到大</option>
                    </select>
                  </div>
                </div>

                <div class="fm-drive-toolbar">
                <div class="btn-group flex-wrap" role="group" aria-label="File manager actions">
                    <button class="btn btn-primary fm-toolbar-btn" id="fmOpenUpload" type="button" data-fm-i18n="upload_action">上传</button>
                    <button class="btn btn-outline-primary fm-toolbar-btn" id="fmDownload" type="button" data-fm-i18n="download_selected">下载选中文件</button>
                    <button class="btn btn-outline-primary fm-toolbar-btn" id="fmCreateFolder" type="button" data-fm-i18n="new_folder">新建文件夹</button>
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmCopy" type="button" data-fm-i18n="copy">复制</button>
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmCut" type="button" data-fm-i18n="cut">剪切</button>
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmPaste" type="button" data-fm-i18n="paste_here">粘贴到当前目录</button>
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmRename" type="button" data-fm-i18n="rename">重命名</button>
                    <button class="btn btn-outline-danger fm-toolbar-btn" id="fmDelete" type="button" data-fm-i18n="delete">删除</button>
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmCreateShare" type="button" data-fm-i18n="share_settings">分享设置</button>
                  </div>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmGoParent" type="button" data-fm-i18n="go_parent">返回上级</button>
                    <button class="btn btn-outline-secondary fm-toolbar-btn" id="fmRefresh" type="button" data-fm-i18n="refresh_list">刷新</button>
                  </div>
                </div>
              </div>

              <div class="fm-drive-meta">
                <div class="fm-meta-chip">
                  <span data-fm-i18n="current_items">当前项目</span>
                  <strong id="fmItemCount">0</strong>
                </div>
                <div class="fm-meta-chip">
                  <span data-fm-i18n="selected_items">已选项目</span>
                  <strong id="fmSelectedCount">0</strong>
                </div>
                <div class="fm-meta-chip">
                  <span data-fm-i18n="current_files">文件</span>
                  <strong id="fmFileCount">0</strong>
                </div>
                <div class="fm-meta-chip">
                  <span data-fm-i18n="current_folders">文件夹</span>
                  <strong id="fmFolderCount">0</strong>
                </div>
                <div class="fm-meta-chip fm-meta-chip-wide" id="fmClipboardPill" data-fm-i18n="clipboard_idle_short">剪贴板：空</div>
              </div>

              <div class="fm-status-inline">
                <div class="fm-panel-kicker mb-1" data-fm-i18n="operation_panel">操作提示</div>
                <div id="fmStatus" class="log small"></div>
              </div>
            </div>
          </section>

          <section class="fm-explorer-stage">
            <section class="fm-explorer-main fm-explorer-main-full">
              <div class="fm-panel fm-explorer-list-panel">
                <div class="fm-explorer-head">
                  <div>
                    <div class="fm-panel-kicker" data-fm-i18n="current_items">当前项目</div>
                    <h3 class="h6 mb-1">资源浏览器</h3>
                    <div class="fm-explorer-path">
                      <div class="fm-explorer-path-label" data-fm-i18n="current_directory">路径</div>
                      <div id="fmBreadcrumbs" class="fm-breadcrumbs"></div>
                    </div>
                  </div>
                  <div class="small text-secondary fm-explorer-note">双击文件夹进入，支持拖拽到目录里移动；按住 Ctrl/Cmd 可复制。</div>
                </div>

                <div class="table-responsive border rounded-4">
                  <table class="table table-hover align-middle mb-0 fm-table">
                    <thead class="table-light">
                      <tr>
                        <th class="fm-check-col">
                          <input class="form-check-input" id="fmSelectAll" type="checkbox" aria-label="select all">
                        </th>
                        <th data-fm-i18n="item_name">名称</th>
                        <th data-fm-i18n="item_type">类型</th>
                        <th data-fm-i18n="item_size">体积</th>
                        <th data-fm-i18n="item_created_at">创建时间</th>
                        <th data-fm-i18n="item_path">所在目录</th>
                        <th class="text-end" data-fm-i18n="item_actions">操作</th>
                      </tr>
                    </thead>
                    <tbody id="fmRows">
                      <tr>
                        <td colspan="7" class="text-center text-secondary py-4" data-fm-i18n="loading">加载中...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          </section>

          <input class="d-none" id="fmUploadFilesInput" type="file" multiple>
          <input class="d-none" id="fmUploadFolderInput" type="file" webkitdirectory directory multiple>
        </div>
      </div>
    </article>

    <div class="modal fade" id="fmUploadModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h2 class="modal-title fs-5 mb-1" data-fm-i18n="upload_panel">上传面板</h2>
              <div class="small text-secondary" data-fm-i18n="upload_to_current">上传到当前目录</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label" for="fmUploadStorageSelect">存放位置</label>
                <select class="form-select" id="fmUploadStorageSelect"></select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="fmUploadFolderPath">存放目录</label>
                <input class="form-control" id="fmUploadFolderPath" placeholder="例如：项目资料/合同">
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <button class="btn btn-primary" id="fmPickFiles" type="button" data-fm-i18n="pick_files">选择文件</button>
              <button class="btn btn-outline-primary" id="fmPickFolder" type="button" data-fm-i18n="pick_folder">选择文件夹</button>
            </div>
            <div class="small text-secondary mb-3" id="fmUploadTargetHint">/</div>
            <div class="table-responsive border rounded-4">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th data-fm-i18n="item_name">名称</th>
                    <th data-fm-i18n="upload_progress">进度</th>
                    <th data-fm-i18n="upload_speed">网速</th>
                    <th data-fm-i18n="upload_status">状态</th>
                  </tr>
                </thead>
                <tbody id="fmUploadTasks">
                  <tr>
                    <td colspan="4" class="text-center text-secondary py-3" data-fm-i18n="upload_queue_empty">上传队列为空</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div id="fmUploadLog" class="log small mt-3"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="fmShareModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h2 class="modal-title fs-5 mb-1" data-fm-i18n="share_panel">分享面板</h2>
              <div class="small text-secondary" data-fm-i18n="share_create_title">批量创建分享</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="border rounded-4 bg-light-subtle px-3 py-2 small text-secondary mb-3" id="fmShareSelectionHint">将为当前选择创建分享。</div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="fmShareTitle" data-fm-i18n="share_title">分享标题</label>
                <input class="form-control" id="fmShareTitle" data-fm-placeholder="share_title_placeholder" placeholder="分享标题（可选）">
              </div>
              <div class="col-md-6">
                <label class="form-label d-block">访问设置</label>
                <div class="form-check mb-2">
                  <input class="form-check-input" id="fmShareUsePassword" type="checkbox">
                  <label class="form-check-label" for="fmShareUsePassword">启用访问密码</label>
                </div>
                <input class="form-control" id="fmSharePwd" data-fm-placeholder="share_password_placeholder" placeholder="提取码（可选）">
              </div>
              <div class="col-md-6">
                <label class="form-label d-block">有效期</label>
                <div class="form-check mb-2">
                  <input class="form-check-input" id="fmShareNoExpiry" type="checkbox" checked>
                  <label class="form-check-label" for="fmShareNoExpiry">永久有效</label>
                </div>
                <input class="form-control" id="fmShareExp" type="datetime-local">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <button class="btn btn-primary w-100" id="fmCreateShareSide" type="button" data-fm-i18n="create_share">创建分享</button>
              </div>
            </div>
            <div id="fmShareResult" class="log small mt-3"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="fmPreviewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h2 class="modal-title fs-5 mb-1" id="fmPreviewTitle">文件预览</h2>
              <div class="small text-secondary" id="fmPreviewMeta"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="fmPreviewStatus" class="log small mb-3"></div>
            <div id="fmPreviewBody" class="fm-preview-body"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($active === 'shares'): ?>
    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 mb-3" data-i18n="share_history">历史分享记录</h2>
        <div class="table-responsive border rounded-4">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th data-i18n="col_title">标题</th>
                <th data-i18n="col_file_count">文件数</th>
                <th data-i18n="col_share_link">分享链接</th>
                <th data-i18n="col_created_at">创建时间</th>
                <th data-i18n="col_action">操作</th>
              </tr>
            </thead>
            <tbody id="shareHistoryRows">
            <?php foreach ($shares as $s): ?>
            <?php $fullLink = $origin . \App\Core\Url::route('/share') . '&code=' . rawurlencode((string)$s['code']); ?>
              <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= htmlspecialchars($s['title'] ?: '未命名', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int)$s['item_count'] ?></td>
                <td>
                  <div class="d-flex flex-column flex-xl-row gap-2">
                    <input class="form-control form-control-sm share-link-input" readonly value="<?= htmlspecialchars($fullLink, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-secondary btn-sm copy-share" data-link="<?= htmlspecialchars($fullLink, ENT_QUOTES, 'UTF-8') ?>" data-i18n="one_click_copy">一键复制</button>
                  </div>
                </td>
                <td><?= htmlspecialchars($s['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><button class="btn btn-outline-danger btn-sm cancel-share" data-id="<?= (int)$s['id'] ?>" data-code="<?= htmlspecialchars((string)$s['code'], ENT_QUOTES, 'UTF-8') ?>" data-i18n="cancel_share">取消分享</button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="shareManageLog" class="log small mt-3"></div>
      </div>
    </article>
    <?php endif; ?>

    <?php if ($active === 'system'): ?>
    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">
          <h2 class="h4 mb-0" data-i18n="system_info">系统信息</h2>
          <button class="btn btn-outline-secondary" id="loadSystemInfo" data-i18n="refresh_info">刷新信息</button>
        </div>
        <pre id="systemInfo" class="log mb-0"></pre>
      </div>
    </article>
    <?php endif; ?>

    <?php if ($active === 'custom'): ?>
    <article class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h4 mb-3" data-i18n="share_buttons">分享页右侧按钮设置</h2>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label" for="customBtn1Text">按钮 1 文本</label>
            <input class="form-control" id="customBtn1Text" data-i18n-placeholder="btn1_text" placeholder="按钮1文本">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="customBtn1Url">按钮 1 链接</label>
            <input class="form-control" id="customBtn1Url" data-i18n-placeholder="btn1_url" placeholder="按钮1链接 (https://...)">
          </div>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label" for="customBtn2Text">按钮 2 文本</label>
            <input class="form-control" id="customBtn2Text" data-i18n-placeholder="btn2_text" placeholder="按钮2文本">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="customBtn2Url">按钮 2 链接</label>
            <input class="form-control" id="customBtn2Url" data-i18n-placeholder="btn2_url" placeholder="按钮2链接 (https://...)">
          </div>
        </div>
        <div class="d-grid d-sm-flex gap-2">
          <button class="btn btn-success" id="saveCustomButtons" data-i18n="save_button_settings">保存按钮设置</button>
        </div>
        <div id="customButtonsLog" class="log small mt-3"></div>
      </div>
    </article>
    <?php endif; ?>
    </section>
  </section>

  <aside class="task-center-dock" id="adminTaskCenter" aria-live="polite">
    <div class="task-center-panel is-collapsed">
      <button class="task-center-fab" id="taskCenterToggle" type="button" aria-expanded="false" aria-controls="taskCenterSheet">
        <span class="task-center-fab-label">任务中心</span>
        <span class="task-center-fab-count" id="taskCenterFabCount">0</span>
      </button>
      <section class="task-center-sheet card border-0 shadow-sm" id="taskCenterSheet" aria-label="任务中心">
        <div class="task-center-head">
          <div>
            <div class="task-center-kicker">Task Center</div>
            <h2 class="h6 mb-1">上传与下载任务</h2>
            <p class="text-secondary small mb-0">后台切换栏目时，任务会继续在这里执行和展示。</p>
          </div>
          <div class="task-center-actions">
            <button class="btn btn-outline-secondary btn-sm" id="taskCenterClearCompleted" type="button">清理已完成</button>
            <button class="btn btn-outline-secondary btn-sm" id="taskCenterCollapse" type="button">收起</button>
          </div>
        </div>
        <div class="task-center-summary" id="taskCenterSummary">
          <span class="task-center-summary-chip">进行中 0</span>
          <span class="task-center-summary-chip">等待中 0</span>
          <span class="task-center-summary-chip">已完成 0</span>
        </div>
        <div class="task-center-list" id="taskCenterList">
          <div class="task-center-empty">当前还没有任务。</div>
        </div>
      </section>
    </div>
  </aside>
</div>

<script>
window.__INITIAL_STORAGE__ = <?= json_encode($storage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__ADMIN_PAGE__ = <?= json_encode($active, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__FILES_DATA__ = <?= json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.__ORIGIN__ = <?= json_encode($origin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script id="adminPagePayload" type="application/json"><?= json_encode([
    'page' => $active,
    'label' => $menu[$active]['label'] ?? '控制台',
    'storage' => $storage,
    'files' => $files,
    'origin' => $origin,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= htmlspecialchars(\App\Core\Url::asset('assets/js/admin.js') . '?v=20260430-4', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Url::asset('assets/js/task-center.js') . '?v=20260430-2', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Url::asset('assets/js/file-manager.js') . '?v=20260430-3', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php
$content = ob_get_clean();
$title = '控制台';
$bodyClass = 'app-body admin-page';
$mainClass = 'container-fluid px-3 px-lg-4 py-3 py-lg-4';
require __DIR__ . '/../layout/base.php';
