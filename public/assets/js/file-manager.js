(() => {
  function bootFileManagerPage() {
    if ((window.__ADMIN_PAGE__ || '') !== 'files') return;
    if (typeof window.destroyFileManagerPage === 'function') {
      window.destroyFileManagerPage();
    }

    const root = document.querySelector('#fileManagerApp');
    if (!root) return;

  const entry = window.__ENTRY__ || '/index.php';
  const origin = window.__ORIGIN__ || location.origin;
  const LANG_KEY = 'mungsos_lang';
  const chunkSize = 30 * 1024 * 1024;
  const previewBlobLimit = 128 * 1024 * 1024;
  const previewTextLimit = 2 * 1024 * 1024;
  const previewConcurrency = 2;

  const routeUrl = (path) => `${entry}?r=${String(path || '').replace(/^\/+/, '')}`;
  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => [...context.querySelectorAll(selector)];

  const I18N = {
    'zh-CN': {
      storage_location: '存储位置',
      folder_tree: '目录树',
      folder_navigation: '快速跳转',
      root_directory: '根目录',
      current_directory: '路径',
      search_current_folder: '搜索当前目录中的文件或文件夹',
      sort_by: '排序方式',
      sort_name_asc: '名称 A-Z',
      sort_name_desc: '名称 Z-A',
      sort_created_desc: '最新上传',
      sort_created_asc: '最早上传',
      sort_size_desc: '体积从大到小',
      sort_size_asc: '体积从小到大',
      upload_action: '上传',
      download: '下载',
      download_selected: '下载选中文件',
      new_folder: '新建文件夹',
      copy: '复制',
      cut: '剪切',
      paste_here: '粘贴到当前目录',
      rename: '重命名',
      delete: '删除',
      create_share: '创建分享',
      share_settings: '分享设置',
      share_row_action: '分享',
      go_parent: '返回上级',
      refresh_list: '刷新',
      current_items: '当前项目',
      selected_items: '已选项目',
      current_files: '文件',
      current_folders: '文件夹',
      item_name: '名称',
      item_type: '类型',
      item_size: '体积',
      item_created_at: '创建时间',
      item_path: '所在目录',
      item_actions: '操作',
      loading: '加载中...',
      selection_panel: '选择信息',
      selection_summary: '当前选择',
      clipboard_panel: '剪贴板',
      clipboard_status: '复制/剪切状态',
      upload_panel: '上传面板',
      upload_to_current: '上传到当前目录',
      pick_files: '选择文件',
      pick_folder: '选择文件夹',
      upload_queue_empty: '上传队列为空',
      upload_waiting: '等待中',
      upload_progress: '进度',
      upload_speed: '网速',
      upload_status: '状态',
      upload_target_hint: '当前上传目标：{path}',
      upload_busy: '已有上传任务在执行，请等待当前队列完成。',
      upload_started: '开始上传，共 {count} 个项目。',
      upload_finished: '上传完成：成功 {success}，失败 {failed}。',
      upload_refreshing: '上传已完成，正在刷新目录视图。',
      upload_task_center_queued: '已加入右下角任务中心，共 {count} 个上传任务。',
      upload_drop_here: '拖入文件到当前目录可直接上传。',
      share_panel: '分享面板',
      share_create_title: '批量创建分享',
      share_title: '分享标题',
      share_title_placeholder: '分享标题（可选）',
      share_password: '提取码',
      share_password_placeholder: '提取码（可选）',
      share_expiration: '过期时间',
      operation_panel: '操作提示',
      operation_tips: '常用操作',
      tip_copy: '先选中文件或文件夹，再点击复制。',
      tip_cut: '剪切后进入目标目录，再点击粘贴即可完成移动。',
      tip_shortcut: '支持快捷键：Ctrl/Cmd + C、X、V，Delete，F2。也支持拖拽到文件夹进行移动，按住 Ctrl/Cmd 可复制。',
      open: '打开',
      preview: '预览',
      paste_to_here: '粘贴到此处',
      type_folder: '文件夹',
      type_file: '文件',
      selection_none: '当前没有选中任何项目。',
      clipboard_empty: '剪贴板为空。先执行复制或剪切，再切换到目标目录粘贴。',
      clipboard_mode_copy: '复制',
      clipboard_mode_cut: '剪切',
      status_ready: '文件管理器已就绪。',
      status_loaded: '目录已加载：{path}',
      status_copied: '已加入复制队列：{files} 个文件，{folders} 个文件夹。',
      status_cut: '已加入剪切队列：{files} 个文件，{folders} 个文件夹。',
      status_pasted_copy: '粘贴完成：复制 {count} 项，跳过 {skipped} 项。',
      status_pasted_move: '移动完成：移动 {files} 个文件，{folders} 个文件夹。',
      status_drag_move: '拖拽移动完成：移动 {files} 个文件，{folders} 个文件夹。',
      status_drag_copy: '拖拽复制完成：复制 {count} 项，跳过 {skipped} 项。',
      status_deleted: '删除完成。',
      status_renamed: '重命名完成。',
      status_created_folder: '文件夹已创建。',
      status_share_created: '分享已创建。',
      status_tree_loaded: '目录树已刷新。',
      status_preview_ready: '预览已准备好。',
      no_storage: '请先创建并启用至少一个存储位置。',
      empty_folder: '当前目录为空。',
      empty_search: '没有匹配当前搜索条件的项目。',
      empty_tree: '这个存储位置还没有目录。',
      folder_count_line: '文件夹：{count}',
      file_count_line: '文件：{count}',
      selected_size_line: '已选文件体积：{size}',
      current_path_line: '当前位置：{path}',
      storage_name_line: '当前存储：{name}',
      clipboard_summary_line: '{mode} {files} 个文件，{folders} 个文件夹',
      clipboard_idle_short: '剪贴板：空',
      share_selection_hint: '将分享 {files} 个文件，{folders} 个文件夹。',
      share_selection_none: '将为当前选择创建分享。',
      share_created_prefix: '分享已创建',
      copy_link: '复制链接',
      copy_link_done: '分享链接已复制。',
      confirm_delete: '删除后不可恢复，确认继续吗？',
      prompt_new_folder: '请输入新建文件夹名称',
      prompt_rename_file: '请输入新的文件名',
      prompt_rename_folder: '请输入新的文件夹名称',
      invalid_folder_name: '文件夹名称不合法。',
      no_selection: '请先选择文件或文件夹。',
      rename_single_only: '重命名一次只能选择一个文件或文件夹。',
      share_selection_empty: '当前选择内没有可分享的文件。',
      download_selection_empty: '请先选择至少一个文件。',
      download_task_center_queued: '已加入右下角任务中心，共 {count} 个下载任务。',
      task_center_unavailable: '任务中心未加载，请刷新页面后重试。',
      clipboard_cross_storage: '复制和粘贴必须在同一存储位置内进行。',
      shortcut_copy: '复制',
      shortcut_cut: '剪切',
      shortcut_paste: '粘贴',
      shortcut_delete: '删除',
      shortcut_rename: '重命名',
      action_more: '更多',
      detail_single_file: '文件：{name}',
      detail_single_folder: '文件夹：{name}',
      row_current_folder: '当前目录',
      row_root_folder: '根目录',
      preview_title: '文件预览',
      preview_loading: '正在载入预览：{name} {percent}%',
      preview_not_supported: '这个文件类型暂不支持网页预览，请直接下载。',
      preview_too_large: '该文件体积较大，当前网页预览已限制在 {size} 以内，请直接下载。',
      preview_text_too_large: '文本文件过大，当前网页仅预览 {size} 以内的文本。',
      preview_failed: '预览失败：{msg}',
      preview_meta: '{type} · {size} · {path}',
      preview_image: '图片预览',
      preview_video: '视频预览',
      preview_audio: '音频预览',
      preview_pdf: 'PDF 预览',
      preview_text: '文本预览',
      drop_move_hint: '拖到文件夹即可移动，按住 Ctrl/Cmd 可复制。',
      drop_copy_hint: '松开后将复制到目标目录。',
      upload_not_supported: '当前上传能力未加载，请刷新页面后重试。',
    },
    en: {
      storage_location: 'Storage',
      folder_tree: 'Folders',
      folder_navigation: 'Quick Jump',
      root_directory: 'Root',
      current_directory: 'Path',
      search_current_folder: 'Search files and folders in the current folder',
      sort_by: 'Sort By',
      sort_name_asc: 'Name A-Z',
      sort_name_desc: 'Name Z-A',
      sort_created_desc: 'Newest First',
      sort_created_asc: 'Oldest First',
      sort_size_desc: 'Largest First',
      sort_size_asc: 'Smallest First',
      upload_action: 'Upload',
      download: 'Download',
      download_selected: 'Download Selected',
      new_folder: 'New Folder',
      copy: 'Copy',
      cut: 'Cut',
      paste_here: 'Paste Here',
      rename: 'Rename',
      delete: 'Delete',
      create_share: 'Create Share',
      share_settings: 'Share Settings',
      share_row_action: 'Share',
      go_parent: 'Up',
      refresh_list: 'Refresh',
      current_items: 'Visible Items',
      selected_items: 'Selected',
      current_files: 'Files',
      current_folders: 'Folders',
      item_name: 'Name',
      item_type: 'Type',
      item_size: 'Size',
      item_created_at: 'Created At',
      item_path: 'Folder',
      item_actions: 'Actions',
      loading: 'Loading...',
      selection_panel: 'Selection',
      selection_summary: 'Current Selection',
      clipboard_panel: 'Clipboard',
      clipboard_status: 'Copy/Cut State',
      upload_panel: 'Upload',
      upload_to_current: 'Upload to Current Folder',
      pick_files: 'Choose Files',
      pick_folder: 'Choose Folder',
      upload_queue_empty: 'Upload queue is empty',
      upload_waiting: 'Waiting',
      upload_progress: 'Progress',
      upload_speed: 'Speed',
      upload_status: 'Status',
      upload_target_hint: 'Current upload target: {path}',
      upload_busy: 'An upload queue is already running. Please wait for it to finish.',
      upload_started: 'Upload started: {count} item(s).',
      upload_finished: 'Upload finished: {success} succeeded, {failed} failed.',
      upload_refreshing: 'Uploads finished. Refreshing folder view.',
      upload_task_center_queued: 'Added to the task center: {count} upload task(s).',
      upload_drop_here: 'Drop files into the current folder to upload them.',
      share_panel: 'Share',
      share_create_title: 'Create Share in Batch',
      share_title: 'Share Title',
      share_title_placeholder: 'Share title (optional)',
      share_password: 'Password',
      share_password_placeholder: 'Password (optional)',
      share_expiration: 'Expires At',
      operation_panel: 'Tips',
      operation_tips: 'Common Actions',
      tip_copy: 'Select files or folders first, then click Copy.',
      tip_cut: 'Use Cut, open the target folder, then Paste to move items.',
      tip_shortcut: 'Keyboard shortcuts: Ctrl/Cmd + C, X, V, Delete, F2. You can also drag items into folders; hold Ctrl/Cmd to copy.',
      open: 'Open',
      preview: 'Preview',
      paste_to_here: 'Paste Here',
      type_folder: 'Folder',
      type_file: 'File',
      selection_none: 'No items are currently selected.',
      clipboard_empty: 'Clipboard is empty. Copy or cut items first, then paste them into the target folder.',
      clipboard_mode_copy: 'Copy',
      clipboard_mode_cut: 'Cut',
      status_ready: 'File manager is ready.',
      status_loaded: 'Folder loaded: {path}',
      status_copied: 'Copied to clipboard: {files} files, {folders} folders.',
      status_cut: 'Cut to clipboard: {files} files, {folders} folders.',
      status_pasted_copy: 'Paste complete: copied {count} items, skipped {skipped}.',
      status_pasted_move: 'Move complete: moved {files} files, {folders} folders.',
      status_drag_move: 'Drag move complete: moved {files} files, {folders} folders.',
      status_drag_copy: 'Drag copy complete: copied {count} items, skipped {skipped}.',
      status_deleted: 'Delete complete.',
      status_renamed: 'Rename complete.',
      status_created_folder: 'Folder created.',
      status_share_created: 'Share created.',
      status_tree_loaded: 'Folder tree refreshed.',
      status_preview_ready: 'Preview is ready.',
      no_storage: 'Create and enable at least one storage location first.',
      empty_folder: 'This folder is empty.',
      empty_search: 'No items match the current search.',
      empty_tree: 'No folders exist in this storage yet.',
      folder_count_line: 'Folders: {count}',
      file_count_line: 'Files: {count}',
      selected_size_line: 'Selected file size: {size}',
      current_path_line: 'Current path: {path}',
      storage_name_line: 'Storage: {name}',
      clipboard_summary_line: '{mode} {files} files, {folders} folders',
      clipboard_idle_short: 'Clipboard: empty',
      share_selection_hint: 'Sharing {files} file(s) and {folders} folder(s).',
      share_selection_none: 'A share will be created for the current selection.',
      share_created_prefix: 'Share created',
      copy_link: 'Copy Link',
      copy_link_done: 'Share link copied.',
      confirm_delete: 'Delete cannot be undone. Continue?',
      prompt_new_folder: 'Enter the new folder name',
      prompt_rename_file: 'Enter the new file name',
      prompt_rename_folder: 'Enter the new folder name',
      invalid_folder_name: 'Invalid folder name.',
      no_selection: 'Select files or folders first.',
      rename_single_only: 'You can rename only one file or folder at a time.',
      share_selection_empty: 'There are no shareable files in the current selection.',
      download_selection_empty: 'Select at least one file first.',
      download_task_center_queued: 'Added to the task center: {count} download task(s).',
      task_center_unavailable: 'Task center is not available. Please refresh and try again.',
      clipboard_cross_storage: 'Copy and paste must stay within the same storage.',
      shortcut_copy: 'Copy',
      shortcut_cut: 'Cut',
      shortcut_paste: 'Paste',
      shortcut_delete: 'Delete',
      shortcut_rename: 'Rename',
      action_more: 'More',
      detail_single_file: 'File: {name}',
      detail_single_folder: 'Folder: {name}',
      row_current_folder: 'Current folder',
      row_root_folder: 'Root',
      preview_title: 'File Preview',
      preview_loading: 'Loading preview: {name} {percent}%',
      preview_not_supported: 'This file type is not supported for in-browser preview yet. Please download it instead.',
      preview_too_large: 'This file is large. In-browser preview is limited to {size}. Please download it instead.',
      preview_text_too_large: 'This text file is too large. In-browser text preview is limited to {size}.',
      preview_failed: 'Preview failed: {msg}',
      preview_meta: '{type} · {size} · {path}',
      preview_image: 'Image Preview',
      preview_video: 'Video Preview',
      preview_audio: 'Audio Preview',
      preview_pdf: 'PDF Preview',
      preview_text: 'Text Preview',
      drop_move_hint: 'Drop into a folder to move. Hold Ctrl/Cmd to copy.',
      drop_copy_hint: 'Release to copy into the target folder.',
      upload_not_supported: 'Upload support is not ready. Please refresh and try again.',
    },
  };

    const cleanupFns = [];
    const addGlobalListener = (target, type, handler, options) => {
      if (!target?.addEventListener) return;
      target.addEventListener(type, handler, options);
      cleanupFns.push(() => target.removeEventListener(type, handler, options));
    };

  const state = {
    storageId: 0,
    storageName: '',
    currentPath: '',
    folders: [],
    files: [],
    treeItems: [],
    search: '',
    sort: 'name_asc',
    selectedFiles: new Set(),
    selectedFolders: new Set(),
    clipboard: {
      mode: 'copy',
      storageId: 0,
      storageName: '',
      file_ids: [],
      folder_paths: [],
    },
    uploadBusy: false,
    dragPayload: null,
    dragHoverElement: null,
    previewModal: null,
    uploadModal: null,
    shareModal: null,
    rowMenuEl: null,
    rowMenuTrigger: null,
    previewObjectUrl: '',
  };

  function currentLang() {
    return localStorage.getItem(LANG_KEY) === 'en' ? 'en' : 'zh-CN';
  }

  function t(key, params = {}) {
    const dict = I18N[currentLang()] || I18N['zh-CN'];
    let text = dict[key] || I18N['zh-CN'][key] || key;
    Object.keys(params).forEach((name) => {
      text = text.replace(new RegExp(`\\{${name}\\}`, 'g'), String(params[name]));
    });
    return text;
  }

  function normalizeFolderPath(path) {
    const raw = String(path || '').replace(/\\/g, '/').trim();
    if (!raw) return '';
    const parts = raw.split('/').map((part) => part.trim()).filter((part) => part && part !== '.' && part !== '..');
    return parts.join('/');
  }

  function splitFolderParentAndName(path) {
    const normalized = normalizeFolderPath(path);
    if (!normalized) return ['', ''];
    const pos = normalized.lastIndexOf('/');
    if (pos < 0) return ['', normalized];
    return [normalized.slice(0, pos), normalized.slice(pos + 1)];
  }

  function joinFolderPath(parentPath, name) {
    const parent = normalizeFolderPath(parentPath);
    const child = normalizeFolderPath(name);
    if (!child) return parent;
    return parent ? `${parent}/${child}` : child;
  }

  function displayPath(path) {
    const normalized = normalizeFolderPath(path);
    return normalized ? `/${normalized}` : '/';
  }

  function escapeHtml(input) {
    return String(input || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatBytes(size) {
    const n = Number(size || 0);
    if (!Number.isFinite(n) || n <= 0) return '0 B';
    if (n < 1024) return `${n.toFixed(0)} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(2)} KB`;
    if (n < 1024 * 1024 * 1024) return `${(n / 1024 / 1024).toFixed(2)} MB`;
    return `${(n / 1024 / 1024 / 1024).toFixed(2)} GB`;
  }

  function formatDateTime(value) {
    const raw = String(value || '').trim();
    if (!raw) return '-';
    const parsed = new Date(raw.replace(' ', 'T'));
    if (!Number.isFinite(parsed.getTime())) return raw;
    return parsed.toLocaleString(currentLang() === 'en' ? 'en-US' : 'zh-CN', { hour12: false });
  }

  function createdAtToMs(value) {
    const raw = String(value || '').trim();
    if (!raw) return 0;
    const ms = Date.parse(raw.replace(' ', 'T'));
    return Number.isFinite(ms) ? ms : 0;
  }

  function compareText(a, b) {
    return String(a || '').localeCompare(String(b || ''), currentLang() === 'en' ? 'en' : 'zh-CN', {
      numeric: true,
      sensitivity: 'base',
    });
  }

  function extOf(name) {
    const idx = String(name || '').lastIndexOf('.');
    if (idx < 0) return '';
    return String(name || '').slice(idx + 1).toLowerCase();
  }

  function fileTypeLabel(file) {
    const name = String(file?.original_name || '').trim();
    if (name.includes('.')) {
      const ext = name.split('.').pop() || '';
      if (ext) return ext.slice(0, 8).toUpperCase();
    }
    const mime = String(file?.mime_type || '').toLowerCase();
    if (mime.startsWith('image/')) return 'IMG';
    if (mime.startsWith('video/')) return 'VIDEO';
    if (mime.startsWith('audio/')) return 'AUDIO';
    if (mime.includes('pdf')) return 'PDF';
    if (mime.includes('zip') || mime.includes('compressed')) return 'ZIP';
    return 'FILE';
  }

  function previewKind(file) {
    const name = String(file?.original_name || '');
    const ext = extOf(name);
    const mime = String(file?.mime_type || '').toLowerCase();
    if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) return 'image';
    if (mime.startsWith('video/') || ['mp4', 'webm', 'mov', 'mkv', 'avi'].includes(ext)) return 'video';
    if (mime.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a'].includes(ext)) return 'audio';
    if (mime.includes('pdf') || ext === 'pdf') return 'pdf';
    if (mime.startsWith('text/') || ['txt', 'md', 'json', 'log', 'csv', 'xml', 'yaml', 'yml'].includes(ext)) return 'text';
    return '';
  }

  function previewTitleForKind(kind) {
    if (kind === 'image') return t('preview_image');
    if (kind === 'video') return t('preview_video');
    if (kind === 'audio') return t('preview_audio');
    if (kind === 'pdf') return t('preview_pdf');
    if (kind === 'text') return t('preview_text');
    return t('preview_title');
  }

  async function requestWithFallback(candidates, options = {}) {
    let lastResponse = null;
    for (const url of candidates) {
      const response = await fetch(url, options);
      if (response.ok) return response;
      lastResponse = response;
      if (response.status !== 404) return response;
    }
    return lastResponse;
  }

  function api(path, options = {}) {
    const requestOptions = {
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
      },
      ...options,
    };
    let apiPath = `/api/${String(path || '').replace(/^\/+/, '')}`;
    let query = '';
    const queryPos = apiPath.indexOf('?');
    if (queryPos >= 0) {
      query = apiPath.slice(queryPos + 1);
      apiPath = apiPath.slice(0, queryPos);
    }
    const queryPrefix = query ? `?${query}` : '';
    const candidates = [
      routeUrl(apiPath) + (query ? `&${query}` : ''),
      `/public${apiPath}${queryPrefix}`,
      `${apiPath}${queryPrefix}`,
    ];
    return requestWithFallback(candidates, requestOptions).then(async (response) => {
      const raw = response ? await response.text() : '';
      const parseJsonSafely = (input) => {
        const source = String(input || '').replace(/^\uFEFF/, '').trim();
        if (!source) return {};
        try {
          return JSON.parse(source);
        } catch (error) {
          const first = source.indexOf('{');
          const last = source.lastIndexOf('}');
          if (first >= 0 && last > first) {
            return JSON.parse(source.slice(first, last + 1));
          }
          throw error;
        }
      };

      let data = null;
      try {
        data = parseJsonSafely(raw);
      } catch (error) {
        const plain = String(raw || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        if (!response || !response.ok) {
          throw new Error(plain || `HTTP ${response ? response.status : 500}`);
        }
        throw new Error(plain || '响应不是合法 JSON');
      }

      if (!response || !response.ok || !data || data.ok === false) {
        const message = data?.message || `HTTP ${response ? response.status : 500}`;
        const error = new Error(message);
        if (response) error.status = response.status;
        error.data = data;
        throw error;
      }
      return data;
    });
  }

  async function copyText(text) {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(String(text || ''));
      return;
    }
    const input = document.createElement('textarea');
    input.value = String(text || '');
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.focus();
    input.select();
    document.execCommand('copy');
    input.remove();
  }

  function selectedSnapshot() {
    return {
      file_ids: [...state.selectedFiles].filter((id) => Number.isFinite(id) && id > 0),
      folder_paths: [...state.selectedFolders].map((path) => normalizeFolderPath(path)).filter(Boolean),
    };
  }

  function clipboardHasItems() {
    return state.clipboard.file_ids.length > 0 || state.clipboard.folder_paths.length > 0;
  }

  function setStatus(message, tone = 'muted') {
    const box = $('#fmStatus');
    if (!box) return;
    box.textContent = message || '';
    box.classList.remove('fm-log-success', 'fm-log-danger');
    if (tone === 'success') box.classList.add('fm-log-success');
    if (tone === 'danger') box.classList.add('fm-log-danger');
  }

  function showRowsMessage(message) {
    const tbody = $('#fmRows');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-secondary py-4">${escapeHtml(message)}</td></tr>`;
  }

  function showTreeMessage(message) {
    const tree = $('#fmFolderTree');
    if (!tree) return;
    tree.innerHTML = `<div class="fm-empty-block">${escapeHtml(message)}</div>`;
  }

  function applyStaticI18n() {
    $$('[data-fm-i18n]', root).forEach((el) => {
      const key = el.getAttribute('data-fm-i18n') || '';
      el.textContent = t(key);
    });
    $$('[data-fm-placeholder]', root).forEach((el) => {
      const key = el.getAttribute('data-fm-placeholder') || '';
      el.setAttribute('placeholder', t(key));
    });
  }

  function fillStorageOptions(select, items, selectedValue) {
    if (!select) return 0;
    select.innerHTML = '';
    items.forEach((item) => {
      const option = document.createElement('option');
      option.value = String(item.id);
      option.textContent = `${item.name} (${item.driver})`;
      select.appendChild(option);
    });
    if (!select.options.length) {
      return 0;
    }
    if ([...select.options].some((option) => Number(option.value) === selectedValue)) {
      select.value = String(selectedValue);
    } else {
      select.value = select.options[0].value;
    }
    return Number(select.value || 0);
  }

  function fillStorageSelect(items) {
    const select = $('#fmStorageSelect');
    if (!select) return;
    const selectedValue = Number(select.value || state.storageId || 0);
    const activeValue = fillStorageOptions(select, items, selectedValue);
    fillStorageOptions($('#fmUploadStorageSelect'), items, activeValue || selectedValue);
    if (!activeValue) {
      state.storageId = 0;
      state.storageName = '';
      return;
    }
    state.storageId = activeValue;
    state.storageName = select.options[select.selectedIndex]?.textContent || '';
    syncUploadTargetFields(true);
  }

  function getUploadTargetStorageId() {
    return Number($('#fmUploadStorageSelect')?.value || state.storageId || 0);
  }

  function getUploadTargetPath() {
    return normalizeFolderPath($('#fmUploadFolderPath')?.value || state.currentPath);
  }

  function syncUploadTargetFields(force = false) {
    const uploadSelect = $('#fmUploadStorageSelect');
    if (uploadSelect && (force || !uploadSelect.value)) {
      const wanted = String(state.storageId || '');
      if (wanted && [...uploadSelect.options].some((option) => option.value === wanted)) {
        uploadSelect.value = wanted;
      }
    }
    const folderInput = $('#fmUploadFolderPath');
    if (folderInput && (force || !folderInput.value.trim())) {
      folderInput.value = displayPath(state.currentPath);
    }
  }

  function buildVisibleData() {
    const keyword = state.search.trim().toLowerCase();
    const folderMatches = (folder) => {
      if (!keyword) return true;
      const name = String(folder?.name || '').toLowerCase();
      const path = String(folder?.path || '').toLowerCase();
      return name.includes(keyword) || path.includes(keyword);
    };
    const fileMatches = (file) => {
      if (!keyword) return true;
      const name = String(file?.original_name || '').toLowerCase();
      const path = String(file?.folder_path || '').toLowerCase();
      const type = String(file?.mime_type || '').toLowerCase();
      return name.includes(keyword) || path.includes(keyword) || type.includes(keyword);
    };

    const folders = [...state.folders].filter(folderMatches).sort((a, b) => {
      const direction = state.sort === 'name_desc' ? -1 : 1;
      return compareText(a?.name || '', b?.name || '') * direction;
    });

    const files = [...state.files].filter(fileMatches).sort((a, b) => {
      switch (state.sort) {
        case 'name_desc':
          return compareText(b?.original_name || '', a?.original_name || '');
        case 'created_desc':
          return createdAtToMs(b?.created_at) - createdAtToMs(a?.created_at) || compareText(a?.original_name || '', b?.original_name || '');
        case 'created_asc':
          return createdAtToMs(a?.created_at) - createdAtToMs(b?.created_at) || compareText(a?.original_name || '', b?.original_name || '');
        case 'size_desc':
          return Number(b?.size || 0) - Number(a?.size || 0) || compareText(a?.original_name || '', b?.original_name || '');
        case 'size_asc':
          return Number(a?.size || 0) - Number(b?.size || 0) || compareText(a?.original_name || '', b?.original_name || '');
        case 'name_asc':
        default:
          return compareText(a?.original_name || '', b?.original_name || '');
      }
    });

    return { folders, files };
  }

  function updateUploadTargetHint() {
    const hint = $('#fmUploadTargetHint');
    if (!hint) return;
    const storageSelect = $('#fmUploadStorageSelect');
    const storageName = storageSelect?.selectedOptions?.[0]?.textContent || state.storageName || '-';
    const path = displayPath(getUploadTargetPath());
    hint.textContent = `${storageName} · ${t('upload_target_hint', { path })}`;
  }

  function renderBreadcrumbs() {
    const box = $('#fmBreadcrumbs');
    if (!box) return;
    const rootButton = $('#fmRootButton');
    if (rootButton) {
      rootButton.classList.toggle('btn-primary', state.currentPath === '');
      rootButton.classList.toggle('btn-outline-primary', state.currentPath !== '');
      rootButton.dataset.dropPath = '';
    }
    const parts = normalizeFolderPath(state.currentPath).split('/').filter(Boolean);
    const buttons = [
      `<button class="btn btn-sm ${parts.length ? 'btn-outline-secondary' : 'btn-success'} fm-crumb-btn" type="button" data-path="" data-drop-path="">${escapeHtml(t('root_directory'))}</button>`,
    ];
    let accum = '';
    parts.forEach((part, index) => {
      accum = accum ? `${accum}/${part}` : part;
      const active = index === parts.length - 1;
      buttons.push('<span class="text-secondary">/</span>');
      buttons.push(`<button class="btn btn-sm ${active ? 'btn-success' : 'btn-outline-secondary'} fm-crumb-btn" type="button" data-path="${escapeHtml(accum)}" data-drop-path="${escapeHtml(accum)}">${escapeHtml(part)}</button>`);
    });
    box.innerHTML = buttons.join('');
  }

  function renderTree() {
    const tree = $('#fmFolderTree');
    if (!tree) return;
    if (!state.treeItems.length) {
      showTreeMessage(t('empty_tree'));
      return;
    }

    const childrenMap = new Map();
    state.treeItems.forEach((item) => {
      const parent = normalizeFolderPath(item.parent_path || '');
      if (!childrenMap.has(parent)) childrenMap.set(parent, []);
      childrenMap.get(parent).push(item);
    });
    childrenMap.forEach((items) => items.sort((a, b) => compareText(a?.name || '', b?.name || '')));

    const renderBranch = (parentPath) => {
      const children = childrenMap.get(parentPath) || [];
      if (!children.length) return '';
      return `<div class="fm-tree-children">${children.map((item) => {
        const itemPath = normalizeFolderPath(item.path || '');
        const active = itemPath === state.currentPath;
        const ancestor = !active && state.currentPath && state.currentPath.startsWith(`${itemPath}/`);
        return `
          <div class="fm-tree-node">
            <button class="fm-tree-button ${active ? 'is-active' : ''} ${ancestor ? 'is-ancestor' : ''}" type="button" data-path="${escapeHtml(itemPath)}" data-drop-path="${escapeHtml(itemPath)}">
              <span class="fm-tree-dot"></span>
              <span class="fm-tree-label">${escapeHtml(item.name || itemPath)}</span>
            </button>
            ${renderBranch(itemPath)}
          </div>
        `;
      }).join('')}</div>`;
    };

    tree.innerHTML = renderBranch('');
  }

  function renderStats() {
    const visible = buildVisibleData();
    const itemCount = $('#fmItemCount');
    const fileCount = $('#fmFileCount');
    const folderCount = $('#fmFolderCount');
    const selectedCount = $('#fmSelectedCount');
    if (itemCount) itemCount.textContent = String(visible.folders.length + visible.files.length);
    if (fileCount) fileCount.textContent = String(visible.files.length);
    if (folderCount) folderCount.textContent = String(visible.folders.length);
    const selection = selectedSnapshot();
    if (selectedCount) selectedCount.textContent = String(selection.file_ids.length + selection.folder_paths.length);
    renderClipboardPill();
  }

  function renderSelectionSummary() {
    const box = $('#fmSelectionSummary');
    if (!box) return;

    const selection = selectedSnapshot();
    if (!selection.file_ids.length && !selection.folder_paths.length) {
      box.innerHTML = `<div class="fm-detail-empty">${escapeHtml(t('selection_none'))}</div>`;
      return;
    }

    let selectedSize = 0;
    state.files.forEach((file) => {
      const fileId = Number(file?.id || 0);
      if (selection.file_ids.includes(fileId)) {
        selectedSize += Number(file?.size || 0);
      }
    });

    const lines = [
      `<div class="fm-detail-line">${escapeHtml(t('storage_name_line', { name: state.storageName || '-' }))}</div>`,
      `<div class="fm-detail-line">${escapeHtml(t('current_path_line', { path: displayPath(state.currentPath) }))}</div>`,
      `<div class="fm-detail-line">${escapeHtml(t('folder_count_line', { count: selection.folder_paths.length }))}</div>`,
      `<div class="fm-detail-line">${escapeHtml(t('file_count_line', { count: selection.file_ids.length }))}</div>`,
      `<div class="fm-detail-line">${escapeHtml(t('selected_size_line', { size: formatBytes(selectedSize) }))}</div>`,
    ];

    if (selection.file_ids.length === 1 && selection.folder_paths.length === 0) {
      const file = state.files.find((item) => Number(item?.id || 0) === selection.file_ids[0]);
      if (file) {
        lines.push(`<div class="fm-detail-callout">${escapeHtml(t('detail_single_file', { name: String(file.original_name || '-') }))}</div>`);
      }
    }
    if (selection.folder_paths.length === 1 && selection.file_ids.length === 0) {
      const [, name] = splitFolderParentAndName(selection.folder_paths[0]);
      lines.push(`<div class="fm-detail-callout">${escapeHtml(t('detail_single_folder', { name: name || selection.folder_paths[0] }))}</div>`);
    }

    box.innerHTML = lines.join('');
  }

  function renderClipboardSummary() {
    const box = $('#fmClipboardSummary');
    if (!box) return;
    if (!clipboardHasItems()) {
      box.innerHTML = `<div class="fm-detail-empty">${escapeHtml(t('clipboard_empty'))}</div>`;
      return;
    }
    const modeKey = state.clipboard.mode === 'cut' ? 'clipboard_mode_cut' : 'clipboard_mode_copy';
    box.innerHTML = [
      `<div class="fm-detail-line">${escapeHtml(t('storage_name_line', { name: state.clipboard.storageName || '-' }))}</div>`,
      `<div class="fm-detail-line">${escapeHtml(t('clipboard_summary_line', {
        mode: t(modeKey),
        files: state.clipboard.file_ids.length,
        folders: state.clipboard.folder_paths.length,
      }))}</div>`,
    ].join('');
  }

  function renderClipboardPill() {
    const box = $('#fmClipboardPill');
    if (!box) return;
    if (!clipboardHasItems()) {
      box.textContent = t('clipboard_idle_short');
      return;
    }
    const modeKey = state.clipboard.mode === 'cut' ? 'clipboard_mode_cut' : 'clipboard_mode_copy';
    box.textContent = t('clipboard_summary_line', {
      mode: t(modeKey),
      files: state.clipboard.file_ids.length,
      folders: state.clipboard.folder_paths.length,
    });
  }

  function setUploadLog(message, tone = 'muted') {
    const box = $('#fmUploadLog');
    if (!box) return;
    box.textContent = message || '';
    box.classList.remove('fm-log-success', 'fm-log-danger');
    if (tone === 'success') box.classList.add('fm-log-success');
    if (tone === 'danger') box.classList.add('fm-log-danger');
  }

  function ensureUploadTableReady() {
    const tbody = $('#fmUploadTasks');
    if (!tbody) return null;
    const empty = $('[data-fm-i18n="upload_queue_empty"]', tbody);
    if (empty) {
      tbody.innerHTML = '';
    }
    return tbody;
  }

  function appendUploadTaskRow(fileName, folderPath) {
    const tbody = ensureUploadTableReady();
    if (!tbody) return null;
    const tr = document.createElement('tr');
    const folderLine = folderPath ? `<div class="small text-secondary text-truncate">${escapeHtml(displayPath(folderPath))}</div>` : '';
    tr.innerHTML = `
      <td class="min-w-0">
        <div class="fw-semibold text-truncate">${escapeHtml(fileName)}</div>
        ${folderLine}
      </td>
      <td class="task-progress-text">0.0%</td>
      <td class="task-speed-text">-</td>
      <td class="task-status-text">${escapeHtml(t('upload_waiting'))}</td>
    `;
    tbody.appendChild(tr);
    return tr;
  }

  function clearUploadInputs() {
    const filesInput = $('#fmUploadFilesInput');
    const folderInput = $('#fmUploadFolderInput');
    if (filesInput) filesInput.value = '';
    if (folderInput) folderInput.value = '';
  }

  function isTransientUploadError(message) {
    const msg = String(message || '');
    return /HTTP (502|503|504|520|522|524)/i.test(msg)
      || /bad gateway/i.test(msg)
      || /connection reset/i.test(msg)
      || /upstream/i.test(msg);
  }

  function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function buildUploadEntriesFromInput(files, asFolderSelection, targetBasePath) {
    const basePath = normalizeFolderPath(targetBasePath);
    const out = [];
    for (const file of Array.from(files || [])) {
      const relative = String(file.webkitRelativePath || '');
      if (asFolderSelection && relative.includes('/')) {
        const relParent = relative.split('/').slice(0, -1).join('/');
        out.push({ file, folderPath: joinFolderPath(basePath, relParent) });
      } else {
        out.push({ file, folderPath: basePath });
      }
    }
    return out;
  }

  async function runUploadQueue(entries, options = {}) {
    if (!entries.length) return;
    const uploadStorageId = Number(options.storageId || getUploadTargetStorageId() || state.storageId || 0);
    if (!uploadStorageId) {
      throw new Error(t('no_storage'));
    }

    const taskCenter = window.__TASK_CENTER__;
    if (taskCenter && typeof taskCenter.enqueueUploads === 'function') {
      const batch = taskCenter.enqueueUploads(entries, {
        storageId: uploadStorageId,
        targetPath: options.targetPath || '',
        source: 'file-manager',
      });
      clearUploadInputs();
      setUploadLog(t('upload_task_center_queued', { count: batch.length }), 'success');
      state.uploadModal?.hide();
      await batch.promise;
      const success = batch.filter((task) => task.status === 'completed').length;
      const failed = batch.length - success;
      setUploadLog(t('upload_finished', { success, failed }), failed > 0 ? 'danger' : 'success');
      setStatus(t('upload_refreshing'), 'success');
      const currentApi = window.__FILE_MANAGER_API__;
      if (currentApi && typeof currentApi.refreshCurrent === 'function' && Number(currentApi.currentStorageId?.() || 0) === uploadStorageId) {
        await currentApi.refreshCurrent();
      }
      return;
    }

    if (state.uploadBusy) {
      throw new Error(t('upload_busy'));
    }
    if (typeof window.uploadFile !== 'function') {
      throw new Error(t('upload_not_supported'));
    }

    state.uploadBusy = true;
    let success = 0;
    let failed = 0;
    setUploadLog(t('upload_started', { count: entries.length }));

    try {
      for (let index = 0; index < entries.length; index++) {
        const entry = entries[index];
        const row = appendUploadTaskRow(entry.file.name, entry.folderPath);
        let attempt = 0;
        let done = false;
        while (!done) {
          try {
            await window.uploadFile(entry.file, uploadStorageId, entry.folderPath || '', row);
            success += 1;
            done = true;
            setUploadLog(`${t('upload_started', { count: entries.length })}\n${t('upload_finished', { success, failed })}`);
          } catch (error) {
            attempt += 1;
            const message = error instanceof Error ? error.message : String(error);
            if (/Upload cancelled by user/i.test(message) || /用户取消上传/.test(message)) {
              done = true;
              failed += 1;
              continue;
            }
            if (attempt <= 2 && isTransientUploadError(message)) {
              const taskStatus = row?.querySelector('.task-status-text');
              if (taskStatus) taskStatus.textContent = `网络波动，自动重试 ${attempt}/2`;
              await wait(2000 * attempt);
              continue;
            }
            done = true;
            failed += 1;
            if (row?.querySelector('.task-status-text')) {
              row.querySelector('.task-status-text').textContent = `失败: ${message}`;
            }
          }
        }
      }
    } finally {
      state.uploadBusy = false;
      clearUploadInputs();
    }

    setUploadLog(t('upload_finished', { success, failed }), failed > 0 ? 'danger' : 'success');
    setStatus(t('upload_refreshing'), 'success');
    if (uploadStorageId === state.storageId) {
      await refreshManager({ tree: true, path: state.currentPath, silent: true });
    }
  }

  function selectionFromRow(kind, value) {
    if (kind === 'file') {
      const fileId = Number(value || 0);
      if (!state.selectedFiles.has(fileId) || state.selectedFiles.size + state.selectedFolders.size !== 1) {
        state.selectedFiles = new Set([fileId]);
        state.selectedFolders = new Set();
      }
    } else {
      const folderPath = normalizeFolderPath(value || '');
      if (!state.selectedFolders.has(folderPath) || state.selectedFiles.size + state.selectedFolders.size !== 1) {
        state.selectedFolders = new Set([folderPath]);
        state.selectedFiles = new Set();
      }
    }
    return selectedSnapshot();
  }

  function clearDragHover() {
    if (state.dragHoverElement instanceof HTMLElement) {
      state.dragHoverElement.classList.remove('is-drag-over');
    }
    state.dragHoverElement = null;
  }

  function setDragHover(element) {
    if (!(element instanceof HTMLElement)) return;
    if (state.dragHoverElement === element) return;
    clearDragHover();
    element.classList.add('is-drag-over');
    state.dragHoverElement = element;
  }

  function findDropTargetElement(start) {
    if (!(start instanceof Element)) return null;
    return start.closest('[data-drop-path]');
  }

  function extractDropTargetPath(element, fallbackPath = state.currentPath) {
    if (element instanceof HTMLElement && element.dataset.dropPath !== undefined) {
      return normalizeFolderPath(element.dataset.dropPath || '');
    }
    return normalizeFolderPath(fallbackPath);
  }

  function buildTransferPayload(selection) {
    return {
      storage_id: state.storageId,
      file_ids: [...selection.file_ids],
      folder_paths: [...selection.folder_paths],
    };
  }

  async function transferPayload(payload, mode, targetFolderPath, statusPrefix = '') {
    const endpoint = mode === 'copy' ? 'files/copy' : 'files/move';
    const result = await api(endpoint, {
      method: 'POST',
      body: JSON.stringify({
        storage_id: Number(payload.storage_id || 0),
        file_ids: payload.file_ids || [],
        folder_paths: payload.folder_paths || [],
        target_folder_path: normalizeFolderPath(targetFolderPath),
      }),
    });
    await refreshManager({ tree: true, path: normalizeFolderPath(targetFolderPath), silent: true });
    if (mode === 'copy') {
      setStatus(
        statusPrefix || t('status_pasted_copy', { count: Number(result.count || 0), skipped: Number(result.skipped || 0) }),
        'success',
      );
    } else {
      setStatus(
        statusPrefix || t('status_pasted_move', { files: Number(result.moved_files || 0), folders: Number(result.moved_folders || 0) }),
        'success',
      );
    }
    return result;
  }

  function setClipboard(mode) {
    const selection = selectedSnapshot();
    if (!selection.file_ids.length && !selection.folder_paths.length) {
      throw new Error(t('no_selection'));
    }
    state.clipboard = {
      mode,
      storageId: state.storageId,
      storageName: state.storageName,
      file_ids: selection.file_ids,
      folder_paths: selection.folder_paths,
    };
    renderClipboardSummary();
    renderClipboardPill();
    updateActionStates();
    setStatus(t(mode === 'cut' ? 'status_cut' : 'status_copied', {
      files: selection.file_ids.length,
      folders: selection.folder_paths.length,
    }), 'success');
  }

  async function pasteClipboard(targetFolderPath = state.currentPath) {
    if (!clipboardHasItems()) {
      throw new Error(t('clipboard_empty'));
    }
    if (Number(state.clipboard.storageId || 0) !== Number(state.storageId || 0)) {
      throw new Error(t('clipboard_cross_storage'));
    }
    const targetFolder = normalizeFolderPath(targetFolderPath);
    const clipboardMode = state.clipboard.mode;
    const payload = {
      storage_id: state.storageId,
      file_ids: state.clipboard.file_ids,
      folder_paths: state.clipboard.folder_paths,
    };
    const result = await transferPayload(payload, clipboardMode === 'cut' ? 'move' : 'copy', targetFolder);
    if (clipboardMode === 'cut') {
      state.clipboard = { mode: 'copy', storageId: 0, storageName: '', file_ids: [], folder_paths: [] };
    }
    renderClipboardSummary();
    renderClipboardPill();
    updateActionStates();
    return result;
  }

  function cleanupPreviewUrl() {
    if (state.previewObjectUrl) {
      URL.revokeObjectURL(state.previewObjectUrl);
      state.previewObjectUrl = '';
    }
  }

  function ensurePreviewModal() {
    const modalEl = $('#fmPreviewModal');
    if (!modalEl) return null;
    if (!state.previewModal && window.bootstrap?.Modal) {
      state.previewModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
      modalEl.addEventListener('hidden.bs.modal', () => {
        cleanupPreviewUrl();
        const body = $('#fmPreviewBody');
        const status = $('#fmPreviewStatus');
        if (body) body.innerHTML = '';
        if (status) status.textContent = '';
      });
    }
    return modalEl;
  }

  function ensureSecondaryModals() {
    if (!window.bootstrap?.Modal) return;
    const uploadEl = $('#fmUploadModal');
    const shareEl = $('#fmShareModal');
    if (uploadEl && !state.uploadModal) {
      state.uploadModal = window.bootstrap.Modal.getOrCreateInstance(uploadEl);
    }
    if (shareEl && !state.shareModal) {
      state.shareModal = window.bootstrap.Modal.getOrCreateInstance(shareEl);
    }
  }

  function setPreviewStatus(message, tone = 'muted') {
    const box = $('#fmPreviewStatus');
    if (!box) return;
    box.textContent = message || '';
    box.classList.remove('fm-log-success', 'fm-log-danger');
    if (tone === 'success') box.classList.add('fm-log-success');
    if (tone === 'danger') box.classList.add('fm-log-danger');
  }

  async function fetchFileBlob(file, onPercent) {
    const totalChunks = Math.max(1, Math.ceil(Number(file.size || 0) / chunkSize));
    const parts = new Array(totalChunks);
    const pending = Array.from({ length: totalChunks }, (_, index) => index);
    let done = 0;
    let cursor = 0;
    let inFlight = 0;

    onPercent?.(0);

    await new Promise((resolve, reject) => {
      const run = () => {
        if (done >= totalChunks) {
          resolve();
          return;
        }
        while (inFlight < previewConcurrency && cursor < pending.length) {
          const idx = pending[cursor++];
          inFlight++;
          fetchChunk(idx).then((buffer) => {
            parts[idx] = buffer;
            done++;
            inFlight--;
            onPercent?.((done / totalChunks) * 100);
            run();
          }).catch(reject);
        }
      };

      const fetchChunk = async (idx, retry = 0) => {
        const url = `${routeUrl('/api/file/chunk')}&file_id=${Number(file.id || 0)}&chunk=${idx}&chunk_size=${chunkSize}`;
        try {
          const res = await fetch(url, { credentials: 'same-origin' });
          if (!res.ok) throw new Error(`HTTP ${res.status}`);
          return await res.arrayBuffer();
        } catch (error) {
          if (retry < 2) return fetchChunk(idx, retry + 1);
          throw error;
        }
      };

      run();
    });

    return new Blob(parts, { type: String(file.mime_type || 'application/octet-stream') });
  }

  async function openPreview(fileId) {
    const file = state.files.find((item) => Number(item?.id || 0) === Number(fileId || 0));
    if (!file) return;

    const kind = previewKind(file);
    if (!kind) throw new Error(t('preview_not_supported'));
    if (kind === 'text' && Number(file.size || 0) > previewTextLimit) {
      throw new Error(t('preview_text_too_large', { size: formatBytes(previewTextLimit) }));
    }
    if (kind !== 'text' && Number(file.size || 0) > previewBlobLimit) {
      throw new Error(t('preview_too_large', { size: formatBytes(previewBlobLimit) }));
    }

    ensurePreviewModal();
    cleanupPreviewUrl();
    const body = $('#fmPreviewBody');
    const title = $('#fmPreviewTitle');
    const meta = $('#fmPreviewMeta');
    if (!body || !title || !meta) return;

    title.textContent = `${previewTitleForKind(kind)} · ${file.original_name || '-'}`;
    meta.textContent = t('preview_meta', {
      type: fileTypeLabel(file),
      size: formatBytes(file.size || 0),
      path: displayPath(file.folder_path || ''),
    });
    body.innerHTML = '';
    setPreviewStatus(t('preview_loading', { name: file.original_name || '-', percent: '0.0' }));
    state.previewModal?.show();

    try {
      const blob = await fetchFileBlob(file, (percent) => {
        setPreviewStatus(t('preview_loading', { name: file.original_name || '-', percent: percent.toFixed(1) }));
      });

      if (kind === 'text') {
        const text = await blob.text();
        body.innerHTML = `<pre class="fm-preview-text">${escapeHtml(text)}</pre>`;
        setPreviewStatus(t('status_preview_ready'), 'success');
        return;
      }

      const objectUrl = URL.createObjectURL(blob);
      state.previewObjectUrl = objectUrl;
      if (kind === 'image') {
        body.innerHTML = `<img class="img-fluid rounded-4 shadow-sm d-block mx-auto" src="${objectUrl}" alt="${escapeHtml(file.original_name || '')}">`;
      } else if (kind === 'video') {
        body.innerHTML = `<video class="w-100 rounded-4" controls src="${objectUrl}" style="max-height:70vh;background:#000"></video>`;
      } else if (kind === 'audio') {
        body.innerHTML = `<audio class="w-100" controls src="${objectUrl}"></audio>`;
      } else if (kind === 'pdf') {
        body.innerHTML = `<iframe class="fm-preview-frame" src="${objectUrl}" title="${escapeHtml(file.original_name || '')}"></iframe>`;
      } else {
        throw new Error(t('preview_not_supported'));
      }
      setPreviewStatus(t('status_preview_ready'), 'success');
    } catch (error) {
      body.innerHTML = `<div class="fm-empty-block">${escapeHtml(t('preview_failed', { msg: error instanceof Error ? error.message : String(error) }))}</div>`;
      setPreviewStatus(t('preview_failed', { msg: error instanceof Error ? error.message : String(error) }), 'danger');
    }
  }

  function actionMenuHtml(kind, item) {
    const folderPath = kind === 'folder' ? normalizeFolderPath(item.path || '') : '';
    const fileId = kind === 'file' ? Number(item.id || 0) : 0;
    return `
      <button
        class="btn btn-sm btn-outline-secondary fm-row-menu-trigger"
        type="button"
        data-action="toggle-row-menu"
        data-kind="${escapeHtml(kind)}"
        data-folder="${escapeHtml(folderPath)}"
        data-file-id="${fileId}"
        aria-expanded="false"
      >${escapeHtml(t('action_more'))}</button>
    `;
  }

  function getRowMenuContext(button) {
    const kind = button.dataset.kind || '';
    if (kind === 'folder') {
      const folderPath = normalizeFolderPath(button.dataset.folder || '');
      return {
        kind,
        folderPath,
        item: state.folders.find((folder) => normalizeFolderPath(folder?.path || '') === folderPath) || { path: folderPath, name: folderPath },
      };
    }
    const fileId = Number(button.dataset.fileId || 0);
    return {
      kind: 'file',
      fileId,
      item: state.files.find((file) => Number(file?.id || 0) === fileId) || { id: fileId },
    };
  }

  function buildRowMenuMarkup(context) {
    const actions = [];
    if (context.kind === 'folder') {
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="open-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('open'))}</button>`);
      if (clipboardHasItems()) {
        actions.push(`<button class="fm-row-menu-item" type="button" data-action="paste-into-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('paste_to_here'))}</button>`);
      }
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="share-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('share_row_action'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="copy-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('copy'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="cut-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('cut'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="rename-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('rename'))}</button>`);
      actions.push(`<button class="fm-row-menu-item is-danger" type="button" data-action="delete-folder" data-folder="${escapeHtml(context.folderPath || '')}">${escapeHtml(t('delete'))}</button>`);
    } else {
      if (previewKind(context.item)) {
        actions.push(`<button class="fm-row-menu-item" type="button" data-action="preview-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('preview'))}</button>`);
      }
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="download-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('download'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="share-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('share_row_action'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="copy-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('copy'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="cut-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('cut'))}</button>`);
      actions.push(`<button class="fm-row-menu-item" type="button" data-action="rename-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('rename'))}</button>`);
      actions.push(`<button class="fm-row-menu-item is-danger" type="button" data-action="delete-file" data-file-id="${Number(context.fileId || 0)}">${escapeHtml(t('delete'))}</button>`);
    }
    return actions.join('');
  }

  function ensureRowMenu() {
    if (state.rowMenuEl) return state.rowMenuEl;
    const menu = document.createElement('div');
    menu.className = 'fm-row-menu-popover hidden';
    menu.innerHTML = '<div class="fm-row-menu-panel"></div>';
    document.body.appendChild(menu);
    menu.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const button = target.closest('[data-action]');
      if (!(button instanceof HTMLButtonElement)) return;
      const action = button.dataset.action || '';
      closeRowMenu();
      runTask(() => handleRowAction(action, button));
    });
    state.rowMenuEl = menu;
    return menu;
  }

  function closeRowMenu() {
    if (!(state.rowMenuEl instanceof HTMLElement)) return;
    state.rowMenuEl.classList.add('hidden');
    state.rowMenuEl.innerHTML = '<div class="fm-row-menu-panel"></div>';
    state.rowMenuTrigger?.setAttribute('aria-expanded', 'false');
    state.rowMenuTrigger = null;
  }

  function openRowMenu(trigger) {
    const context = getRowMenuContext(trigger);
    const menu = ensureRowMenu();
    const panel = menu.firstElementChild;
    if (!(panel instanceof HTMLElement)) return;
    panel.innerHTML = buildRowMenuMarkup(context);
    menu.classList.remove('hidden');
    menu.style.left = '0px';
    menu.style.top = '0px';
    const rect = trigger.getBoundingClientRect();
    const menuRect = panel.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    let left = rect.right - menuRect.width;
    if (left < 12) left = 12;
    if (left + menuRect.width > viewportWidth - 12) {
      left = viewportWidth - menuRect.width - 12;
    }
    let top = rect.bottom + 8;
    if (top + menuRect.height > viewportHeight - 12) {
      top = rect.top - menuRect.height - 8;
    }
    if (top < 12) top = 12;
    menu.style.left = `${Math.round(left)}px`;
    menu.style.top = `${Math.round(top)}px`;
    trigger.setAttribute('aria-expanded', 'true');
    state.rowMenuTrigger = trigger;
  }

  function toggleRowMenu(trigger) {
    if (state.rowMenuTrigger === trigger && state.rowMenuEl && !state.rowMenuEl.classList.contains('hidden')) {
      closeRowMenu();
      return;
    }
    closeRowMenu();
    openRowMenu(trigger);
  }

  function renderRows() {
    const tbody = $('#fmRows');
    if (!tbody) return;
    closeRowMenu();
    const visible = buildVisibleData();
    const rows = [];
    const currentFolderLabel = displayPath(state.currentPath);

    visible.folders.forEach((folder) => {
      const path = normalizeFolderPath(folder?.path || '');
      const isSelected = state.selectedFolders.has(path);
      rows.push(`
        <tr class="fm-row ${isSelected ? 'is-selected' : ''}" data-kind="folder" data-folder="${escapeHtml(path)}" data-drop-path="${escapeHtml(path)}" draggable="true">
          <td class="fm-check-col"><input class="form-check-input fm-folder-check" type="checkbox" value="${escapeHtml(path)}" ${isSelected ? 'checked' : ''}></td>
          <td>
            <button class="btn btn-link text-decoration-none px-0 fm-name-button" type="button" data-action="open-folder" data-folder="${escapeHtml(path)}">
              <span class="fm-icon-pill fm-icon-folder">DIR</span>
              <span class="fw-semibold text-truncate">${escapeHtml(folder?.name || path || '-')}</span>
            </button>
          </td>
          <td><span class="badge rounded-pill fm-soft-badge">${escapeHtml(t('type_folder'))}</span></td>
          <td>-</td>
          <td>-</td>
          <td>${escapeHtml(currentFolderLabel)}</td>
          <td class="text-end">${actionMenuHtml('folder', folder)}</td>
        </tr>
      `);
    });

    visible.files.forEach((file) => {
      const fileId = Number(file?.id || 0);
      const isSelected = state.selectedFiles.has(fileId);
      const previewable = previewKind(file);
      const nameInner = `
        <span class="fm-icon-pill fm-icon-file">${escapeHtml(fileTypeLabel(file))}</span>
        <div class="min-w-0">
          <div class="fw-semibold text-truncate">${escapeHtml(file?.original_name || '-')}</div>
          <div class="small text-secondary text-truncate">${escapeHtml(String(file?.mime_type || t('type_file')))}</div>
        </div>
      `;
      const nameCell = previewable
        ? `<button class="btn btn-link text-decoration-none px-0 fm-name-button" type="button" data-action="preview-file" data-file-id="${fileId}">${nameInner}</button>`
        : `<div class="fm-name-stack">${nameInner}</div>`;
      rows.push(`
        <tr class="fm-row ${isSelected ? 'is-selected' : ''}" data-kind="file" data-file-id="${fileId}" draggable="true">
          <td class="fm-check-col"><input class="form-check-input fm-file-check" type="checkbox" value="${fileId}" ${isSelected ? 'checked' : ''}></td>
          <td>${nameCell}</td>
          <td><span class="badge rounded-pill text-bg-light border">${escapeHtml(fileTypeLabel(file))}</span></td>
          <td>${escapeHtml(formatBytes(file?.size || 0))}</td>
          <td>${escapeHtml(formatDateTime(file?.created_at || ''))}</td>
          <td>${escapeHtml(displayPath(file?.folder_path || ''))}</td>
          <td class="text-end">${actionMenuHtml('file', file)}</td>
        </tr>
      `);
    });

    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-secondary py-4">${escapeHtml(state.search.trim() ? t('empty_search') : t('empty_folder'))}</td></tr>`;
    } else {
      tbody.innerHTML = rows.join('');
    }

    tbody.dataset.dropPath = state.currentPath;

    const selectAll = $('#fmSelectAll');
    if (selectAll) {
      const total = visible.folders.length + visible.files.length;
      const selectedVisibleFolders = visible.folders.filter((folder) => state.selectedFolders.has(normalizeFolderPath(folder?.path || ''))).length;
      const selectedVisibleFiles = visible.files.filter((file) => state.selectedFiles.has(Number(file?.id || 0))).length;
      const selectedVisible = selectedVisibleFolders + selectedVisibleFiles;
      selectAll.checked = total > 0 && selectedVisible === total;
      selectAll.indeterminate = selectedVisible > 0 && selectedVisible < total;
    }

    renderStats();
    renderSelectionSummary();
    renderClipboardSummary();
    updateShareSelectionHint();
    updateActionStates();
  }

  function updateActionStates() {
    const setDisabled = (selector, disabled) => {
      const element = $(selector);
      if (element) element.disabled = disabled;
    };
    const selection = selectedSnapshot();
    const selectionCount = selection.file_ids.length + selection.folder_paths.length;
    const selectedDownloadCount = selectedFilesForDownload().length;
    const pasteAvailable = clipboardHasItems() && Number(state.clipboard.storageId || 0) === Number(state.storageId || 0);
    setDisabled('#fmCopy', selectionCount === 0);
    setDisabled('#fmCut', selectionCount === 0);
    setDisabled('#fmDownload', selectedDownloadCount === 0);
    setDisabled('#fmRename', selectionCount !== 1);
    setDisabled('#fmDelete', selectionCount === 0);
    setDisabled('#fmCreateShare', selectionCount === 0);
    setDisabled('#fmCreateShareSide', selectionCount === 0);
    setDisabled('#fmPaste', !pasteAvailable);
    setDisabled('#fmGoParent', !state.currentPath);
  }

  function clearSelection() {
    state.selectedFiles = new Set();
    state.selectedFolders = new Set();
  }

  async function loadTree() {
    if (!state.storageId) {
      state.treeItems = [];
      showTreeMessage(t('no_storage'));
      return;
    }
    const data = await api(`files/tree?storage_id=${state.storageId}`);
    state.treeItems = Array.isArray(data.items) ? data.items : [];
    renderTree();
  }

  async function loadFolder(path = state.currentPath) {
    if (!state.storageId) {
      state.currentPath = '';
      state.folders = [];
      state.files = [];
      clearSelection();
      showRowsMessage(t('no_storage'));
      renderBreadcrumbs();
      renderTree();
      renderStats();
      renderSelectionSummary();
      renderClipboardSummary();
      updateUploadTargetHint();
      updateActionStates();
      return;
    }

    const normalizedPath = normalizeFolderPath(path);
    const data = await api(`files/list?storage_id=${state.storageId}&folder_path=${encodeURIComponent(normalizedPath)}`);
    state.storageName = String(data.storage_name || state.storageName || '');
    state.currentPath = normalizeFolderPath(data.current_path || normalizedPath);
    state.folders = Array.isArray(data.folders) ? data.folders : [];
    state.files = Array.isArray(data.files) ? data.files : [];
    clearSelection();
    renderBreadcrumbs();
    renderTree();
    renderRows();
    updateUploadTargetHint();
    setStatus(t('status_loaded', { path: displayPath(state.currentPath) }), 'success');
  }

  async function refreshManager({ tree = false, path = state.currentPath, silent = false } = {}) {
    if (!silent) {
      showRowsMessage(t('loading'));
    }
    if (tree && $('#fmFolderTree')) {
      await Promise.all([loadTree(), loadFolder(path)]);
    } else {
      await loadFolder(path);
    }
  }

  function selectOnlyFile(fileId) {
    state.selectedFiles = new Set([Number(fileId)]);
    state.selectedFolders = new Set();
    renderRows();
  }

  function selectOnlyFolder(folderPath) {
    state.selectedFolders = new Set([normalizeFolderPath(folderPath)]);
    state.selectedFiles = new Set();
    renderRows();
  }

  async function collectFileIdsForFolder(storageId, folderPath, sink) {
    const normalized = normalizeFolderPath(folderPath);
    const data = await api(`files/list?storage_id=${storageId}&folder_path=${encodeURIComponent(normalized)}`);
    (data.files || []).forEach((file) => {
      const fileId = Number(file?.id || 0);
      if (fileId > 0) sink.add(fileId);
    });
    for (const folder of (data.folders || [])) {
      const childPath = normalizeFolderPath(folder?.path || '');
      if (!childPath) continue;
      await collectFileIdsForFolder(storageId, childPath, sink);
    }
  }

  async function buildShareFileIds() {
    const selection = selectedSnapshot();
    const fileIds = new Set(selection.file_ids);
    for (const folderPath of selection.folder_paths) {
      await collectFileIdsForFolder(state.storageId, folderPath, fileIds);
    }
    return [...fileIds].filter((id) => Number.isFinite(id) && id > 0);
  }

  async function createFolder() {
    const name = window.prompt(t('prompt_new_folder'), '');
    if (!name) return;
    const normalizedName = normalizeFolderPath(name);
    if (!normalizedName || normalizedName.includes('/')) {
      throw new Error(t('invalid_folder_name'));
    }
    await api('files/folder/create', {
      method: 'POST',
      body: JSON.stringify({
        storage_id: state.storageId,
        folder_path: joinFolderPath(state.currentPath, normalizedName),
      }),
    });
    await refreshManager({ tree: true, path: state.currentPath, silent: true });
    setStatus(t('status_created_folder'), 'success');
  }

  async function renameSelection() {
    const selection = selectedSnapshot();
    if (selection.file_ids.length + selection.folder_paths.length !== 1) {
      throw new Error(t('rename_single_only'));
    }
    if (selection.file_ids.length === 1) {
      const fileId = selection.file_ids[0];
      const file = state.files.find((item) => Number(item?.id || 0) === fileId);
      const oldName = String(file?.original_name || '');
      const newName = window.prompt(t('prompt_rename_file'), oldName);
      if (!newName || newName === oldName) return;
      await api('files/rename', {
        method: 'POST',
        body: JSON.stringify({ file_id: fileId, new_name: newName.trim() }),
      });
      await refreshManager({ path: state.currentPath, silent: true });
      setStatus(t('status_renamed'), 'success');
      return;
    }

    const oldFolderPath = selection.folder_paths[0];
    const [parentFolder, folderName] = splitFolderParentAndName(oldFolderPath);
    const newFolderName = window.prompt(t('prompt_rename_folder'), folderName);
    if (!newFolderName || newFolderName === folderName) return;
    const newFolderPath = joinFolderPath(parentFolder, newFolderName.trim());
    await api('files/rename', {
      method: 'POST',
      body: JSON.stringify({
        storage_id: state.storageId,
        folder_path: oldFolderPath,
        new_folder_path: newFolderPath,
      }),
    });
    await refreshManager({ tree: true, path: state.currentPath, silent: true });
    setStatus(t('status_renamed'), 'success');
  }

  async function deleteSelection() {
    const selection = selectedSnapshot();
    if (!selection.file_ids.length && !selection.folder_paths.length) {
      throw new Error(t('no_selection'));
    }
    if (!window.confirm(t('confirm_delete'))) return;
    await api('files/delete', {
      method: 'POST',
      body: JSON.stringify({
        storage_id: state.storageId,
        file_ids: selection.file_ids,
        folder_paths: selection.folder_paths,
      }),
    });
    await refreshManager({ tree: true, path: state.currentPath, silent: true });
    setStatus(t('status_deleted'), 'success');
  }

  function updateShareSelectionHint() {
    const box = $('#fmShareSelectionHint');
    if (!box) return;
    const selection = selectedSnapshot();
    if (!selection.file_ids.length && !selection.folder_paths.length) {
      box.textContent = t('share_selection_none');
      return;
    }
    box.textContent = t('share_selection_hint', {
      files: selection.file_ids.length,
      folders: selection.folder_paths.length,
    });
  }

  function updateShareModalState() {
    const usePassword = $('#fmShareUsePassword')?.checked ?? false;
    const noExpiry = $('#fmShareNoExpiry')?.checked ?? true;
    const pwd = $('#fmSharePwd');
    const exp = $('#fmShareExp');
    if (pwd) {
      pwd.disabled = !usePassword;
      if (!usePassword) pwd.value = '';
    }
    if (exp) {
      exp.disabled = noExpiry;
      if (noExpiry) exp.value = '';
    }
  }

  function openShareModalForSelection() {
    const selection = selectedSnapshot();
    if (!selection.file_ids.length && !selection.folder_paths.length) {
      throw new Error(t('no_selection'));
    }
    ensureSecondaryModals();
    updateShareSelectionHint();
    updateShareModalState();
    state.shareModal?.show();
  }

  async function createShare() {
    const ids = await buildShareFileIds();
    if (!ids.length) {
      throw new Error(t('share_selection_empty'));
    }
    const usePassword = $('#fmShareUsePassword')?.checked ?? false;
    const noExpiry = $('#fmShareNoExpiry')?.checked ?? true;
    const data = await api('share/create', {
      method: 'POST',
      body: JSON.stringify({
        title: ($('#fmShareTitle')?.value || '').trim(),
        password: usePassword ? ($('#fmSharePwd')?.value || '').trim() : '',
        expires_at: !noExpiry && $('#fmShareExp')?.value
          ? new Date($('#fmShareExp').value).toISOString().slice(0, 19).replace('T', ' ')
          : '',
        file_ids: ids,
      }),
    });
    const fullLink = `${origin}${data.link}`;
    const box = $('#fmShareResult');
    if (box) {
      box.innerHTML = `
        <div>${escapeHtml(t('share_created_prefix'))}</div>
        <a href="${escapeHtml(fullLink)}" target="_blank" rel="noopener noreferrer">${escapeHtml(fullLink)}</a>
        <div class="mt-2">
          <button class="btn btn-sm btn-success" type="button" data-action="copy-share-link" data-link="${escapeHtml(fullLink)}">${escapeHtml(t('copy_link'))}</button>
        </div>
      `;
    }
    setStatus(t('status_share_created'), 'success');
  }

  function selectedFilesForDownload() {
    return state.files.filter((file) => state.selectedFiles.has(Number(file?.id || 0)));
  }

  async function queueDownloadTasks(files) {
    const items = Array.isArray(files) ? files.filter((file) => Number(file?.id || 0) > 0) : selectedFilesForDownload();
    if (!items.length) {
      throw new Error(t('download_selection_empty'));
    }
    const taskCenter = window.__TASK_CENTER__;
    if (!taskCenter || typeof taskCenter.enqueueDownloads !== 'function') {
      throw new Error(t('task_center_unavailable'));
    }
    const batch = taskCenter.enqueueDownloads(items, { source: 'file-manager' });
    taskCenter.open?.();
    setStatus(t('download_task_center_queued', { count: batch.length }), 'success');
    await batch.promise;
  }

  function toggleSelectAll(checked) {
    const visible = buildVisibleData();
    if (checked) {
      visible.files.forEach((file) => state.selectedFiles.add(Number(file?.id || 0)));
      visible.folders.forEach((folder) => state.selectedFolders.add(normalizeFolderPath(folder?.path || '')));
    } else {
      visible.files.forEach((file) => state.selectedFiles.delete(Number(file?.id || 0)));
      visible.folders.forEach((folder) => state.selectedFolders.delete(normalizeFolderPath(folder?.path || '')));
    }
    renderRows();
  }

  function isInteractiveTarget(target) {
    return Boolean(target.closest('button, a, input, select, textarea, label, .fm-row-menu-popover, .fm-row-menu-trigger'));
  }

  function handleRowAction(action, button) {
    switch (action) {
      case 'open-folder':
        return refreshManager({ path: normalizeFolderPath(button.dataset.folder || '') });
      case 'preview-file':
        return openPreview(Number(button.dataset.fileId || 0));
      case 'download-file': {
        const fileId = Number(button.dataset.fileId || 0);
        const file = state.files.find((item) => Number(item?.id || 0) === fileId);
        return queueDownloadTasks(file ? [file] : []);
      }
      case 'paste-into-folder':
        return pasteClipboard(normalizeFolderPath(button.dataset.folder || ''));
      case 'share-file':
        selectOnlyFile(Number(button.dataset.fileId || 0));
        openShareModalForSelection();
        return Promise.resolve();
      case 'share-folder':
        selectOnlyFolder(button.dataset.folder || '');
        openShareModalForSelection();
        return Promise.resolve();
      case 'copy-file':
        selectOnlyFile(Number(button.dataset.fileId || 0));
        setClipboard('copy');
        return Promise.resolve();
      case 'cut-file':
        selectOnlyFile(Number(button.dataset.fileId || 0));
        setClipboard('cut');
        return Promise.resolve();
      case 'rename-file':
        selectOnlyFile(Number(button.dataset.fileId || 0));
        return renameSelection();
      case 'delete-file':
        selectOnlyFile(Number(button.dataset.fileId || 0));
        return deleteSelection();
      case 'copy-folder':
        selectOnlyFolder(button.dataset.folder || '');
        setClipboard('copy');
        return Promise.resolve();
      case 'cut-folder':
        selectOnlyFolder(button.dataset.folder || '');
        setClipboard('cut');
        return Promise.resolve();
      case 'rename-folder':
        selectOnlyFolder(button.dataset.folder || '');
        return renameSelection();
      case 'delete-folder':
        selectOnlyFolder(button.dataset.folder || '');
        return deleteSelection();
      default:
        return Promise.resolve();
    }
  }

  function runTask(task) {
    Promise.resolve()
      .then(task)
      .catch((error) => {
        console.error(error);
        const message = error instanceof Error ? error.message : String(error);
        setStatus(message, 'danger');
      });
  }

  function handleRowsChange(event) {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.classList.contains('fm-file-check')) {
      const fileId = Number(target.value || 0);
      if (target.checked) state.selectedFiles.add(fileId);
      else state.selectedFiles.delete(fileId);
      renderRows();
      return;
    }
    if (target.classList.contains('fm-folder-check')) {
      const folderPath = normalizeFolderPath(target.value || '');
      if (target.checked) state.selectedFolders.add(folderPath);
      else state.selectedFolders.delete(folderPath);
      renderRows();
    }
  }

  function handleRowsClick(event) {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const actionButton = target.closest('[data-action]');
    if (actionButton instanceof HTMLElement) {
      const action = actionButton.dataset.action || '';
      if (!action) return;
      event.preventDefault();
      if (action === 'toggle-row-menu') {
        event.stopPropagation();
        toggleRowMenu(actionButton);
        return;
      }
      closeRowMenu();
      runTask(() => handleRowAction(action, actionButton));
      return;
    }
    if (isInteractiveTarget(target)) return;
    const row = target.closest('.fm-row');
    if (!(row instanceof HTMLElement)) return;
    const kind = row.dataset.kind || '';
    if (kind === 'file') {
      const fileId = Number(row.dataset.fileId || 0);
      if (state.selectedFiles.has(fileId)) state.selectedFiles.delete(fileId);
      else state.selectedFiles.add(fileId);
      renderRows();
      return;
    }
    if (kind === 'folder') {
      const folderPath = normalizeFolderPath(row.dataset.folder || '');
      if (state.selectedFolders.has(folderPath)) state.selectedFolders.delete(folderPath);
      else state.selectedFolders.add(folderPath);
      renderRows();
    }
  }

  function handleRowsDoubleClick(event) {
    const target = event.target;
    if (!(target instanceof Element) || isInteractiveTarget(target)) return;
    const row = target.closest('.fm-row');
    if (!(row instanceof HTMLElement)) return;
    if ((row.dataset.kind || '') === 'folder') {
      runTask(() => refreshManager({ path: row.dataset.folder || '' }));
      return;
    }
    if ((row.dataset.kind || '') === 'file') {
      const fileId = Number(row.dataset.fileId || 0);
      const file = state.files.find((item) => Number(item?.id || 0) === fileId);
      if (file && previewKind(file)) {
        runTask(() => openPreview(fileId));
      }
    }
  }

  function handleDragStart(event) {
    const row = event.target instanceof Element ? event.target.closest('.fm-row') : null;
    if (!(row instanceof HTMLElement) || !event.dataTransfer) return;
    const kind = row.dataset.kind || '';
    const selection = selectionFromRow(kind, kind === 'file' ? Number(row.dataset.fileId || 0) : row.dataset.folder || '');
    state.dragPayload = buildTransferPayload(selection);
    event.dataTransfer.effectAllowed = 'copyMove';
    event.dataTransfer.setData('text/plain', JSON.stringify(state.dragPayload));
    row.classList.add('is-dragging');
    setStatus(t('drop_move_hint'));
  }

  function handleDragEnd(event) {
    const row = event.target instanceof Element ? event.target.closest('.fm-row') : null;
    row?.classList.remove('is-dragging');
    state.dragPayload = null;
    clearDragHover();
    renderRows();
  }

  function handleDragOver(event) {
    const hasExternalFiles = Boolean(event.dataTransfer?.files && event.dataTransfer.files.length > 0);
    const hasInternalPayload = !!state.dragPayload;
    if (!hasExternalFiles && !hasInternalPayload) return;
    const targetEl = findDropTargetElement(event.target);
    if (!targetEl && !$('#fmRows')) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = hasInternalPayload
      ? ((event.ctrlKey || event.metaKey) ? 'copy' : 'move')
      : 'copy';
    if (targetEl instanceof HTMLElement) {
      setDragHover(targetEl);
    }
    if (hasInternalPayload) {
      setStatus((event.ctrlKey || event.metaKey) ? t('drop_copy_hint') : t('drop_move_hint'));
    } else {
      setStatus(t('upload_drop_here'));
    }
  }

  async function handleDrop(event) {
    const targetEl = findDropTargetElement(event.target);
    const targetFolder = extractDropTargetPath(targetEl, state.currentPath);
    const hasExternalFiles = Boolean(event.dataTransfer?.files && event.dataTransfer.files.length > 0);
    const hasInternalPayload = !!state.dragPayload;
    if (!hasExternalFiles && !hasInternalPayload) return;
    event.preventDefault();
    clearDragHover();

    if (hasInternalPayload) {
      const mode = (event.ctrlKey || event.metaKey) ? 'copy' : 'move';
      const result = await transferPayload(state.dragPayload, mode, targetFolder);
      state.dragPayload = null;
      if (mode === 'copy') {
        setStatus(t('status_drag_copy', { count: Number(result.count || 0), skipped: Number(result.skipped || 0) }), 'success');
      } else {
        setStatus(t('status_drag_move', { files: Number(result.moved_files || 0), folders: Number(result.moved_folders || 0) }), 'success');
      }
      return;
    }

    const entries = buildUploadEntriesFromInput(event.dataTransfer.files, false, targetFolder);
    await runUploadQueue(entries);
  }

  function handleKeyboard(event) {
    if (event.key === 'Escape') {
      closeRowMenu();
    }
    const target = event.target;
    if (target instanceof HTMLElement) {
      const tagName = target.tagName.toLowerCase();
      if (target.isContentEditable || ['input', 'textarea', 'select'].includes(tagName)) {
        return;
      }
    }
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform);
    const modifier = isMac ? event.metaKey : event.ctrlKey;
    const key = event.key.toLowerCase();
    if (modifier && key === 'c') {
      event.preventDefault();
      runTask(() => setClipboard('copy'));
      return;
    }
    if (modifier && key === 'x') {
      event.preventDefault();
      runTask(() => setClipboard('cut'));
      return;
    }
    if (modifier && key === 'v') {
      event.preventDefault();
      runTask(() => pasteClipboard(state.currentPath));
      return;
    }
    if (event.key === 'Delete') {
      event.preventDefault();
      runTask(() => deleteSelection());
      return;
    }
    if (event.key === 'F2') {
      event.preventDefault();
      runTask(() => renameSelection());
    }
  }

  function bindDropSurface(element) {
    if (!element) return;
    element.addEventListener('dragover', handleDragOver);
    element.addEventListener('drop', (event) => runTask(() => handleDrop(event)));
    element.addEventListener('dragleave', (event) => {
      if (event.target === state.dragHoverElement) {
        clearDragHover();
      }
    });
  }

  function bindEvents() {
    $('#fmStorageSelect')?.addEventListener('change', () => {
      state.storageId = Number($('#fmStorageSelect')?.value || 0);
      state.storageName = $('#fmStorageSelect')?.selectedOptions?.[0]?.textContent || '';
      state.search = '';
      const searchInput = $('#fmSearchInput');
      if (searchInput) searchInput.value = '';
      syncUploadTargetFields(true);
      updateUploadTargetHint();
      runTask(() => refreshManager({ tree: true, path: '' }));
    });

    $('#fmSortSelect')?.addEventListener('change', () => {
      state.sort = $('#fmSortSelect')?.value || 'name_asc';
      renderRows();
    });

    $('#fmSearchInput')?.addEventListener('input', () => {
      state.search = $('#fmSearchInput')?.value || '';
      renderRows();
    });

    $('#fmRootButton')?.addEventListener('click', () => runTask(() => refreshManager({ path: '' })));
    $('#fmReloadTree')?.addEventListener('click', () => runTask(async () => {
      await loadTree();
      setStatus(t('status_tree_loaded'), 'success');
    }));
    $('#fmOpenUpload')?.addEventListener('click', () => {
      ensureSecondaryModals();
      syncUploadTargetFields(true);
      updateUploadTargetHint();
      state.uploadModal?.show();
    });
    $('#fmDownload')?.addEventListener('click', () => runTask(() => queueDownloadTasks()));
    $('#fmCreateFolder')?.addEventListener('click', () => runTask(() => createFolder()));
    $('#fmCopy')?.addEventListener('click', () => runTask(() => setClipboard('copy')));
    $('#fmCut')?.addEventListener('click', () => runTask(() => setClipboard('cut')));
    $('#fmPaste')?.addEventListener('click', () => runTask(() => pasteClipboard(state.currentPath)));
    $('#fmRename')?.addEventListener('click', () => runTask(() => renameSelection()));
    $('#fmDelete')?.addEventListener('click', () => runTask(() => deleteSelection()));
    $('#fmRefresh')?.addEventListener('click', () => runTask(() => refreshManager({ tree: true, path: state.currentPath })));
    $('#fmGoParent')?.addEventListener('click', () => {
      const [parent] = splitFolderParentAndName(state.currentPath);
      runTask(() => refreshManager({ path: parent || '' }));
    });
    $('#fmCreateShare')?.addEventListener('click', () => {
      runTask(() => openShareModalForSelection());
    });
    $('#fmCreateShareSide')?.addEventListener('click', () => runTask(() => createShare()));
    $('#fmSelectAll')?.addEventListener('change', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement)) return;
      toggleSelectAll(target.checked);
    });

    $('#fmUploadStorageSelect')?.addEventListener('change', () => updateUploadTargetHint());
    $('#fmUploadFolderPath')?.addEventListener('input', () => updateUploadTargetHint());
    $('#fmShareUsePassword')?.addEventListener('change', updateShareModalState);
    $('#fmShareNoExpiry')?.addEventListener('change', updateShareModalState);

    $('#fmPickFiles')?.addEventListener('click', () => $('#fmUploadFilesInput')?.click());
    $('#fmPickFolder')?.addEventListener('click', () => $('#fmUploadFolderInput')?.click());
    $('#fmUploadFilesInput')?.addEventListener('change', () => {
      const input = $('#fmUploadFilesInput');
      if (!input?.files?.length) return;
      const targetPath = getUploadTargetPath();
      const targetStorageId = getUploadTargetStorageId();
      const entries = buildUploadEntriesFromInput(input.files, false, targetPath);
      runTask(() => runUploadQueue(entries, { storageId: targetStorageId, targetPath }));
    });
    $('#fmUploadFolderInput')?.addEventListener('change', () => {
      const input = $('#fmUploadFolderInput');
      if (!input?.files?.length) return;
      const targetPath = getUploadTargetPath();
      const targetStorageId = getUploadTargetStorageId();
      const entries = buildUploadEntriesFromInput(input.files, true, targetPath);
      runTask(() => runUploadQueue(entries, { storageId: targetStorageId, targetPath }));
    });

    $('#fmRows')?.addEventListener('change', handleRowsChange);
    $('#fmRows')?.addEventListener('click', handleRowsClick);
    $('#fmRows')?.addEventListener('dblclick', handleRowsDoubleClick);
    $('#fmRows')?.addEventListener('dragstart', handleDragStart);
    $('#fmRows')?.addEventListener('dragend', handleDragEnd);

    $('#fmBreadcrumbs')?.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const button = target.closest('.fm-crumb-btn');
      if (!(button instanceof HTMLButtonElement)) return;
      runTask(() => refreshManager({ path: button.dataset.path || '' }));
    });

    $('#fmFolderTree')?.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const button = target.closest('.fm-tree-button');
      if (!(button instanceof HTMLButtonElement)) return;
      runTask(() => refreshManager({ path: button.dataset.path || '' }));
    });

    $('#fmShareResult')?.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const button = target.closest('[data-action="copy-share-link"]');
      if (!(button instanceof HTMLButtonElement)) return;
      runTask(async () => {
        await copyText(button.dataset.link || '');
        setStatus(t('copy_link_done'), 'success');
      });
    });

    $('#langSwitchAdmin')?.addEventListener('click', () => {
      window.setTimeout(() => {
        applyStaticI18n();
        renderBreadcrumbs();
        renderTree();
        renderRows();
        updateShareSelectionHint();
        updateShareModalState();
        syncUploadTargetFields();
        updateUploadTargetHint();
        renderClipboardPill();
      }, 0);
    });

    bindDropSurface($('#fmRows'));
    bindDropSurface($('#fmFolderTree'));
    bindDropSurface($('#fmBreadcrumbs'));
    bindDropSurface($('#fmRootButton'));

    addGlobalListener(document, 'keydown', handleKeyboard);
    addGlobalListener(document, 'click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        closeRowMenu();
        return;
      }
      if (target.closest('.fm-row-menu-popover, .fm-row-menu-trigger')) return;
      closeRowMenu();
    });
    addGlobalListener(document, 'scroll', () => closeRowMenu(), true);
    addGlobalListener(window, 'resize', closeRowMenu);
  }

  async function init() {
    applyStaticI18n();
    ensurePreviewModal();
    ensureSecondaryModals();
    fillStorageSelect(Array.isArray(window.__INITIAL_STORAGE__) ? window.__INITIAL_STORAGE__ : []);
    state.sort = $('#fmSortSelect')?.value || 'name_asc';
    bindEvents();
    syncUploadTargetFields(true);
    updateUploadTargetHint();
    updateShareSelectionHint();
    updateShareModalState();
    renderClipboardPill();

    if (!state.storageId) {
      showTreeMessage(t('no_storage'));
      showRowsMessage(t('no_storage'));
      renderBreadcrumbs();
      renderStats();
      renderSelectionSummary();
      renderClipboardSummary();
      updateActionStates();
      setStatus(t('no_storage'), 'danger');
      return;
    }

    setStatus(t('status_ready'));
    setUploadLog(t('upload_drop_here'));
    await refreshManager({ tree: true, path: '', silent: true });
  }

    window.destroyFileManagerPage = () => {
      cleanupFns.splice(0).forEach((dispose) => {
        try {
          dispose();
        } catch (_) {}
      });
      closeRowMenu();
      cleanupPreviewUrl();
      if (state.previewModal?.hide) state.previewModal.hide();
      if (state.uploadModal?.hide) state.uploadModal.hide();
      if (state.shareModal?.hide) state.shareModal.hide();
      if (state.previewModal?.dispose) state.previewModal.dispose();
      if (state.uploadModal?.dispose) state.uploadModal.dispose();
      if (state.shareModal?.dispose) state.shareModal.dispose();
      state.previewModal = null;
      state.uploadModal = null;
      state.shareModal = null;
      if (state.rowMenuEl instanceof HTMLElement) {
        state.rowMenuEl.remove();
        state.rowMenuEl = null;
      }
      $$('.modal-backdrop').forEach((backdrop) => backdrop.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      window.__FILE_MANAGER_API__ = null;
      window.destroyFileManagerPage = () => {};
    };

    window.__FILE_MANAGER_API__ = {
      refreshCurrent() {
        return refreshManager({ tree: true, path: state.currentPath, silent: true });
      },
      currentStorageId() {
        return Number(state.storageId || 0);
      },
      currentPath() {
        return state.currentPath || '';
      },
    };

    init().catch((error) => {
      console.error(error);
      const message = error instanceof Error ? error.message : String(error);
      setStatus(message, 'danger');
      showRowsMessage(message);
    });
  }

  window.initFileManagerPage = bootFileManagerPage;
  window.destroyFileManagerPage = window.destroyFileManagerPage || (() => {});

  if ((window.__ADMIN_PAGE__ || '') === 'files') {
    bootFileManagerPage();
  }
})();
