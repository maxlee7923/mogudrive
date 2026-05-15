const chunkSize = 30 * 1024 * 1024;
const md5ReadChunkSize = 8 * 1024 * 1024;
const uploadConcurrency = 1;
const completeRetryLimit = 1800;
const entry = window.__ENTRY__ || '/index.php';
const siteName = window.__SITE_NAME__ || '蘑菇网盘';
let currentAdminPage = window.__ADMIN_PAGE__ || 'upload';
const filesData = Array.isArray(window.__FILES_DATA__) ? window.__FILES_DATA__ : [];
const origin = window.__ORIGIN__ || location.origin;
const routeUrl = (path) => `${entry}?r=${String(path || '').replace(/^\/+/, '')}`;

const $ = (s) => document.querySelector(s);
const $$ = (s) => [...document.querySelectorAll(s)];
let clipboardData = { file_ids: [], folder_paths: [] };
const explorerState = {
  storageId: 0,
  currentPath: '',
  folders: [],
  files: [],
  selectedFiles: new Set(),
  selectedFolders: new Set(),
  createdAtSort: 'desc',
};
const fileMd5PromiseCache = new WeakMap();
const LANG_KEY = 'mungsos_lang';
let currentLang = localStorage.getItem(LANG_KEY) || 'zh-CN';
const I18N = {
  'zh-CN': {
    brand: siteName,
    toggle_sidebar: '展开/收起侧栏',
    current_user: '当前用户',
    logout: '退出登录',
    lang_toggle: 'English',
    upload: '上传中心',
    files: '文件列表',
    shares: '分享管理',
    system: '系统信息',
    custom: '按钮设置',
    storage: '存储设置',
    upload_tasks: '上传任务',
    start_upload: '开始上传',
    col_filename: '文件名',
    col_progress: '进度',
    col_speed: '网速',
    col_status: '状态',
    add_storage: '新增存储位置',
    name: '名称',
    local_storage: '本地存储',
    save_storage: '保存存储',
    storage_list: '存储列表',
    col_name: '名称',
    col_driver: '驱动',
    col_enabled: '启用',
    col_created_at: '创建时间',
    resource_manager: '资源管理器',
    copy: '复制',
    paste: '粘贴',
    rename: '重命名',
    delete: '删除',
    create_share: '创建分享',
    target_folder: '粘贴目标目录（可选）',
    share_title: '分享标题（可选）',
    share_pwd: '提取码（可选）',
    share_exp: '过期时间',
    folders: '文件夹',
    col_folder: '目录',
    col_file_count: '文件数',
    col_total_size: '总大小',
    files_title: '文件',
    col_size: '体积',
    col_storage: '存储位置',
    share_history: '历史分享记录',
    col_title: '标题',
    col_share_link: '分享链接',
    col_action: '操作',
    one_click_copy: '一键复制',
    cancel_share: '取消分享',
    system_info: '系统信息',
    refresh_info: '刷新信息',
    share_buttons: '分享页右侧按钮设置',
    btn1_text: '按钮1文本',
    btn1_url: '按钮1链接 (https://...)',
    btn2_text: '按钮2文本',
    btn2_url: '按钮2链接 (https://...)',
    save_button_settings: '保存按钮设置',
    waiting: '等待中',
    uploading: '上传中',
    completed: '已完成',
    merging_wait: '服务器正在合并，等待中',
    reuploading: '检测到缺失分片，自动重传中',
    calculating_md5: '计算MD5中',
    md5_failed: 'MD5计算失败，改为普通上传',
  },
  en: {
    brand: siteName,
    toggle_sidebar: 'Toggle Sidebar',
    current_user: 'Current User',
    logout: 'Logout',
    lang_toggle: '简体中文',
    upload: 'Upload',
    files: 'Files',
    shares: 'Shares',
    system: 'System',
    custom: 'Buttons',
    storage: 'Storage',
    upload_tasks: 'Upload Tasks',
    start_upload: 'Start Upload',
    col_filename: 'File Name',
    col_progress: 'Progress',
    col_speed: 'Speed',
    col_status: 'Status',
    add_storage: 'Add Storage',
    name: 'Name',
    local_storage: 'Local Storage',
    save_storage: 'Save Storage',
    storage_list: 'Storage List',
    col_name: 'Name',
    col_driver: 'Driver',
    col_enabled: 'Enabled',
    col_created_at: 'Created At',
    resource_manager: 'Resource Manager',
    copy: 'Copy',
    paste: 'Paste',
    rename: 'Rename',
    delete: 'Delete',
    create_share: 'Create Share',
    target_folder: 'Target folder (optional)',
    share_title: 'Share title (optional)',
    share_pwd: 'Share password (optional)',
    share_exp: 'Expiration time',
    folders: 'Folders',
    col_folder: 'Folder',
    col_file_count: 'Files',
    col_total_size: 'Total Size',
    files_title: 'Files',
    col_size: 'Size',
    col_storage: 'Storage',
    share_history: 'Share History',
    col_title: 'Title',
    col_share_link: 'Share Link',
    col_action: 'Action',
    one_click_copy: 'Copy Link',
    cancel_share: 'Cancel Share',
    system_info: 'System Info',
    refresh_info: 'Refresh',
    share_buttons: 'Share Page Buttons',
    btn1_text: 'Button 1 text',
    btn1_url: 'Button 1 URL (https://...)',
    btn2_text: 'Button 2 text',
    btn2_url: 'Button 2 URL (https://...)',
    save_button_settings: 'Save Button Settings',
    waiting: 'Waiting',
    uploading: 'Uploading',
    completed: 'Completed',
    merging_wait: 'Server is merging, please wait',
    reuploading: 'Missing chunk detected, retrying upload',
    calculating_md5: 'Calculating MD5',
    md5_failed: 'MD5 calculation failed, fallback to normal upload',
  },
};

function t(key) {
  const dict = I18N[currentLang] || I18N['zh-CN'];
  return dict[key] || I18N['zh-CN'][key] || key;
}

function applyI18n() {
  $$('[data-i18n]').forEach((el) => {
    el.textContent = t(el.getAttribute('data-i18n') || '');
  });
  $$('[data-i18n-placeholder]').forEach((el) => {
    el.setAttribute('placeholder', t(el.getAttribute('data-i18n-placeholder') || ''));
  });
  $$('.side-text[data-menu-key]').forEach((el) => {
    const key = el.getAttribute('data-menu-key') || '';
    el.textContent = t(key);
  });
  const activeLink = $('.side-link.active .side-text[data-menu-key]');
  if (activeLink) $('#pageTitle').textContent = activeLink.textContent;
  updateCreatedAtSortButtonLabel();
}

function initLangSwitch() {
  if (currentLang !== 'zh-CN' && currentLang !== 'en') currentLang = 'zh-CN';
  applyI18n();
  $('#langSwitchAdmin')?.addEventListener('click', () => {
    currentLang = currentLang === 'zh-CN' ? 'en' : 'zh-CN';
    localStorage.setItem(LANG_KEY, currentLang);
    applyI18n();
  });
}

function formatSpeed(bytesPerSec) {
  if (!Number.isFinite(bytesPerSec) || bytesPerSec <= 0) return '-';
  if (bytesPerSec < 1024) return `${bytesPerSec.toFixed(0)} B/s`;
  if (bytesPerSec < 1024 * 1024) return `${(bytesPerSec / 1024).toFixed(1)} KB/s`;
  return `${(bytesPerSec / 1024 / 1024).toFixed(2)} MB/s`;
}

function createRealtimeSpeedTracker() {
  let lastBytes = 0;
  let pendingBytes = 0;
  let lastAt = performance.now();
  let currentSpeed = 0;

  return {
    reset(bytes = 0) {
      lastBytes = Number(bytes || 0);
      pendingBytes = 0;
      lastAt = performance.now();
      currentSpeed = 0;
      return '-';
    },
    sample(totalBytes) {
      const normalized = Math.max(0, Number(totalBytes || 0));
      const deltaBytes = Math.max(0, normalized - lastBytes);
      const now = performance.now();
      pendingBytes += deltaBytes;
      lastBytes = normalized;
      const deltaSec = Math.max(0, (now - lastAt) / 1000);
      if (pendingBytes > 0 && deltaSec >= 0.12) {
        const instantSpeed = pendingBytes / deltaSec;
        currentSpeed = currentSpeed > 0 ? (currentSpeed * 0.35) + (instantSpeed * 0.65) : instantSpeed;
        pendingBytes = 0;
        lastAt = now;
      }
      return currentSpeed > 0 ? formatSpeed(currentSpeed) : '-';
    },
    finish() {
      pendingBytes = 0;
      lastAt = performance.now();
      return currentSpeed > 0 ? formatSpeed(currentSpeed) : '-';
    },
  };
}

function formatBytes(size) {
  const n = Number(size || 0);
  if (n < 1024) return `${n.toFixed(0)} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(2)} KB`;
  if (n < 1024 * 1024 * 1024) return `${(n / 1024 / 1024).toFixed(2)} MB`;
  return `${(n / 1024 / 1024 / 1024).toFixed(2)} GB`;
}

const md5HexTable = Array.from({ length: 256 }, (_, i) => i.toString(16).padStart(2, '0'));

function readBlobAsArrayBuffer(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(reader.error || new Error('Failed to read blob'));
    reader.readAsArrayBuffer(blob);
  });
}

function md5Add(x, y) {
  return (x + y) | 0;
}

function md5RotateLeft(x, n) {
  return (x << n) | (x >>> (32 - n));
}

function md5Common(q, a, b, x, s, t) {
  return md5Add(md5RotateLeft(md5Add(md5Add(a, q), md5Add(x, t)), s), b);
}

function md5Ff(a, b, c, d, x, s, t) {
  return md5Common((b & c) | (~b & d), a, b, x, s, t);
}

function md5Gg(a, b, c, d, x, s, t) {
  return md5Common((b & d) | (c & ~d), a, b, x, s, t);
}

function md5Hh(a, b, c, d, x, s, t) {
  return md5Common(b ^ c ^ d, a, b, x, s, t);
}

function md5Ii(a, b, c, d, x, s, t) {
  return md5Common(c ^ (b | ~d), a, b, x, s, t);
}

function md5ReadWords(bytes, offset) {
  const words = new Array(16);
  for (let i = 0; i < 16; i++) {
    const j = offset + i * 4;
    words[i] = (bytes[j]) | (bytes[j + 1] << 8) | (bytes[j + 2] << 16) | (bytes[j + 3] << 24);
  }
  return words;
}

function md5Cycle(state, words) {
  let a = state[0];
  let b = state[1];
  let c = state[2];
  let d = state[3];

  a = md5Ff(a, b, c, d, words[0], 7, -680876936);
  d = md5Ff(d, a, b, c, words[1], 12, -389564586);
  c = md5Ff(c, d, a, b, words[2], 17, 606105819);
  b = md5Ff(b, c, d, a, words[3], 22, -1044525330);
  a = md5Ff(a, b, c, d, words[4], 7, -176418897);
  d = md5Ff(d, a, b, c, words[5], 12, 1200080426);
  c = md5Ff(c, d, a, b, words[6], 17, -1473231341);
  b = md5Ff(b, c, d, a, words[7], 22, -45705983);
  a = md5Ff(a, b, c, d, words[8], 7, 1770035416);
  d = md5Ff(d, a, b, c, words[9], 12, -1958414417);
  c = md5Ff(c, d, a, b, words[10], 17, -42063);
  b = md5Ff(b, c, d, a, words[11], 22, -1990404162);
  a = md5Ff(a, b, c, d, words[12], 7, 1804603682);
  d = md5Ff(d, a, b, c, words[13], 12, -40341101);
  c = md5Ff(c, d, a, b, words[14], 17, -1502002290);
  b = md5Ff(b, c, d, a, words[15], 22, 1236535329);

  a = md5Gg(a, b, c, d, words[1], 5, -165796510);
  d = md5Gg(d, a, b, c, words[6], 9, -1069501632);
  c = md5Gg(c, d, a, b, words[11], 14, 643717713);
  b = md5Gg(b, c, d, a, words[0], 20, -373897302);
  a = md5Gg(a, b, c, d, words[5], 5, -701558691);
  d = md5Gg(d, a, b, c, words[10], 9, 38016083);
  c = md5Gg(c, d, a, b, words[15], 14, -660478335);
  b = md5Gg(b, c, d, a, words[4], 20, -405537848);
  a = md5Gg(a, b, c, d, words[9], 5, 568446438);
  d = md5Gg(d, a, b, c, words[14], 9, -1019803690);
  c = md5Gg(c, d, a, b, words[3], 14, -187363961);
  b = md5Gg(b, c, d, a, words[8], 20, 1163531501);
  a = md5Gg(a, b, c, d, words[13], 5, -1444681467);
  d = md5Gg(d, a, b, c, words[2], 9, -51403784);
  c = md5Gg(c, d, a, b, words[7], 14, 1735328473);
  b = md5Gg(b, c, d, a, words[12], 20, -1926607734);

  a = md5Hh(a, b, c, d, words[5], 4, -378558);
  d = md5Hh(d, a, b, c, words[8], 11, -2022574463);
  c = md5Hh(c, d, a, b, words[11], 16, 1839030562);
  b = md5Hh(b, c, d, a, words[14], 23, -35309556);
  a = md5Hh(a, b, c, d, words[1], 4, -1530992060);
  d = md5Hh(d, a, b, c, words[4], 11, 1272893353);
  c = md5Hh(c, d, a, b, words[7], 16, -155497632);
  b = md5Hh(b, c, d, a, words[10], 23, -1094730640);
  a = md5Hh(a, b, c, d, words[13], 4, 681279174);
  d = md5Hh(d, a, b, c, words[0], 11, -358537222);
  c = md5Hh(c, d, a, b, words[3], 16, -722521979);
  b = md5Hh(b, c, d, a, words[6], 23, 76029189);
  a = md5Hh(a, b, c, d, words[9], 4, -640364487);
  d = md5Hh(d, a, b, c, words[12], 11, -421815835);
  c = md5Hh(c, d, a, b, words[15], 16, 530742520);
  b = md5Hh(b, c, d, a, words[2], 23, -995338651);

  a = md5Ii(a, b, c, d, words[0], 6, -198630844);
  d = md5Ii(d, a, b, c, words[7], 10, 1126891415);
  c = md5Ii(c, d, a, b, words[14], 15, -1416354905);
  b = md5Ii(b, c, d, a, words[5], 21, -57434055);
  a = md5Ii(a, b, c, d, words[12], 6, 1700485571);
  d = md5Ii(d, a, b, c, words[3], 10, -1894986606);
  c = md5Ii(c, d, a, b, words[10], 15, -1051523);
  b = md5Ii(b, c, d, a, words[1], 21, -2054922799);
  a = md5Ii(a, b, c, d, words[8], 6, 1873313359);
  d = md5Ii(d, a, b, c, words[15], 10, -30611744);
  c = md5Ii(c, d, a, b, words[6], 15, -1560198380);
  b = md5Ii(b, c, d, a, words[13], 21, 1309151649);
  a = md5Ii(a, b, c, d, words[4], 6, -145523070);
  d = md5Ii(d, a, b, c, words[11], 10, -1120210379);
  c = md5Ii(c, d, a, b, words[2], 15, 718787259);
  b = md5Ii(b, c, d, a, words[9], 21, -343485551);

  state[0] = md5Add(state[0], a);
  state[1] = md5Add(state[1], b);
  state[2] = md5Add(state[2], c);
  state[3] = md5Add(state[3], d);
}

function md5StateToHex(state) {
  let out = '';
  for (let i = 0; i < state.length; i++) {
    const n = state[i] >>> 0;
    out += md5HexTable[n & 0xff];
    out += md5HexTable[(n >>> 8) & 0xff];
    out += md5HexTable[(n >>> 16) & 0xff];
    out += md5HexTable[(n >>> 24) & 0xff];
  }
  return out;
}

class IncrementalMd5 {
  constructor() {
    this.state = [1732584193, -271733879, -1732584194, 271733878];
    this.tail = new Uint8Array(0);
    this.length = 0;
  }

  append(input) {
    if (!input) return this;
    const bytes = input instanceof Uint8Array ? input : new Uint8Array(input);
    let data = bytes;
    this.length += data.length;

    if (this.tail.length) {
      const merged = new Uint8Array(this.tail.length + data.length);
      merged.set(this.tail, 0);
      merged.set(data, this.tail.length);
      data = merged;
      this.tail = new Uint8Array(0);
    }

    const limit = data.length - (data.length % 64);
    for (let i = 0; i < limit; i += 64) {
      md5Cycle(this.state, md5ReadWords(data, i));
    }

    if (limit < data.length) {
      this.tail = data.slice(limit);
    }
    return this;
  }

  end() {
    const tailLength = this.tail.length;
    const padLength = tailLength < 56 ? (56 - tailLength) : (120 - tailLength);
    const finalBytes = new Uint8Array(tailLength + padLength + 8);
    finalBytes.set(this.tail, 0);
    finalBytes[tailLength] = 0x80;

    const bitLengthLow = (this.length << 3) >>> 0;
    const bitLengthHigh = Math.floor(this.length / 0x20000000) >>> 0;
    finalBytes[finalBytes.length - 8] = bitLengthLow & 0xff;
    finalBytes[finalBytes.length - 7] = (bitLengthLow >>> 8) & 0xff;
    finalBytes[finalBytes.length - 6] = (bitLengthLow >>> 16) & 0xff;
    finalBytes[finalBytes.length - 5] = (bitLengthLow >>> 24) & 0xff;
    finalBytes[finalBytes.length - 4] = bitLengthHigh & 0xff;
    finalBytes[finalBytes.length - 3] = (bitLengthHigh >>> 8) & 0xff;
    finalBytes[finalBytes.length - 2] = (bitLengthHigh >>> 16) & 0xff;
    finalBytes[finalBytes.length - 1] = (bitLengthHigh >>> 24) & 0xff;

    for (let i = 0; i < finalBytes.length; i += 64) {
      md5Cycle(this.state, md5ReadWords(finalBytes, i));
    }

    return md5StateToHex(this.state);
  }
}

async function computeFileMd5(file, row) {
  const cached = fileMd5PromiseCache.get(file);
  if (cached) {
    return cached;
  }

  const promise = (async () => {
    const hasher = new IncrementalMd5();
    const totalParts = Math.max(1, Math.ceil(file.size / md5ReadChunkSize));

    for (let i = 0; i < totalParts; i++) {
      const start = i * md5ReadChunkSize;
      const end = Math.min(file.size, start + md5ReadChunkSize);
      const chunk = file.slice(start, end);
      const buf = await readBlobAsArrayBuffer(chunk);
      hasher.append(buf);
      updateTaskRow(row, ((i + 1) / totalParts) * 100, '-', `${t('calculating_md5')} ${(100 * (i + 1) / totalParts).toFixed(1)}%`);
    }

    return hasher.end();
  })();

  fileMd5PromiseCache.set(file, promise);
  return promise;
}

async function requestWithFallback(candidates, options = {}) {
  let lastRes = null;
  for (const url of candidates) {
    const res = await fetch(url, options);
    if (res.ok) return res;
    lastRes = res;
    if (res.status !== 404) return res;
  }
  return lastRes;
}

async function api(url, options = {}) {
  let apiPath = `/api/${url}`;
  let query = '';
  const qPos = apiPath.indexOf('?');
  if (qPos >= 0) {
    query = apiPath.slice(qPos + 1);
    apiPath = apiPath.slice(0, qPos);
  }
  const queryPrefix = query ? `?${query}` : '';
  const candidates = [
    routeUrl(apiPath) + (query ? `&${query}` : ''),
    `/public${apiPath}${queryPrefix}`,
    `${apiPath}${queryPrefix}`,
  ];
  const res = await requestWithFallback(candidates, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  if (!res) throw new Error('HTTP 500');
  const raw = await res.text();
  const parseJsonSafely = (input) => {
    const src = String(input || '').replace(/^\uFEFF/, '').trim();
    if (!src) return {};
    try {
      return JSON.parse(src);
    } catch (_) {
      const first = src.indexOf('{');
      const last = src.lastIndexOf('}');
      if (first >= 0 && last > first) {
        return JSON.parse(src.slice(first, last + 1));
      }
      throw _;
    }
  };
  let data = null;
  try {
    data = parseJsonSafely(raw);
  } catch (_) {
    const plain = (raw || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (!res.ok) throw new Error(plain || `HTTP ${res.status}`);
    throw new Error(plain || '响应不是合法 JSON');
  }
  if (!res.ok) {
    const err = new Error(data?.message || `HTTP ${res.status}`);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

function uploadChunkWithProgress(url, formData, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.responseType = 'text';
    xhr.upload.onprogress = (event) => {
      if (!event.lengthComputable) return;
      onProgress?.(event.loaded, event.total);
    };
    xhr.onload = () => resolve({
      ok: xhr.status >= 200 && xhr.status < 300,
      status: xhr.status,
      text: typeof xhr.responseText === 'string' ? xhr.responseText : String(xhr.response || ''),
    });
    xhr.onerror = () => reject(new Error('Network request failed'));
    xhr.onabort = () => reject(new Error('Upload aborted'));
    xhr.send(formData);
  });
}

async function postChunkForm(formData, onProgress) {
  const candidates = [routeUrl('/api/upload/chunk'), '/public/api/upload/chunk', '/api/upload/chunk'];
  let response = null;
  for (const url of candidates) {
    const result = await uploadChunkWithProgress(url, formData, onProgress);
    if (result.ok) {
      response = result;
      break;
    }
    if (result.status !== 404) {
      response = result;
      break;
    }
  }
  const raw = response ? response.text : '';
  let data = { ok: false, message: 'request failed' };
  if (raw) {
    try {
      data = JSON.parse(String(raw).replace(/^\uFEFF/, '').trim());
    } catch (_) {
      const plain = String(raw).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      throw new Error(`${plain || 'invalid response body'} (HTTP ${response ? response.status : 500})`);
    }
  }
  if (!response || !response.ok) {
    const msg = data && data.message ? data.message : 'request failed';
    throw new Error(`${msg} (HTTP ${response ? response.status : 500})`);
  }
  return data;
}

async function copyText(text) {
  if (!text) return;
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
    return;
  }
  const input = document.createElement('textarea');
  input.value = text;
  document.body.appendChild(input);
  input.select();
  document.execCommand('copy');
  document.body.removeChild(input);
}

function setupSidebarToggle() {
  const btn = $('#toggleSidebar');
  const shell = $('#adminShell');
  if (!btn || !shell) return;
  const deskKey = 'mungsos_sidebar_collapsed';
  const mobileKey = 'mungsos_sidebar_mobile_collapsed';
  const mq = window.matchMedia('(max-width: 980px)');

  const applySidebarState = () => {
    if (mq.matches) {
      shell.classList.remove('collapsed');
      const mobileSaved = localStorage.getItem(mobileKey);
      shell.classList.toggle('mobile-collapsed', mobileSaved === '1');
      return;
    }
    shell.classList.remove('mobile-collapsed');
    const deskSaved = localStorage.getItem(deskKey);
    shell.classList.toggle('collapsed', deskSaved === '1');
  };

  applySidebarState();
  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', applySidebarState);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(applySidebarState);
  }

  btn.addEventListener('click', () => {
    if (mq.matches) {
      const now = !shell.classList.contains('mobile-collapsed');
      shell.classList.toggle('mobile-collapsed', now);
      localStorage.setItem(mobileKey, now ? '1' : '0');
      return;
    }
    const now = !shell.classList.contains('collapsed');
    shell.classList.toggle('collapsed', now);
    localStorage.setItem(deskKey, now ? '1' : '0');
  });
}

function fillStorage(items) {
  const fillSelect = (sel) => {
    if (!sel) return;
    const prev = sel.value;
    sel.innerHTML = '';
    items.forEach((s) => {
      const op = document.createElement('option');
      op.value = s.id;
      op.textContent = `${s.name} (${s.driver})`;
      sel.appendChild(op);
    });
    if (prev && [...sel.options].some((op) => op.value === prev)) {
      sel.value = prev;
    }
  };
  fillSelect($('#storageSelect'));
  fillSelect($('#filesStorageSelect'));
}

function storageFields() {
  const driverEl = $('#stDriver');
  if (!driverEl) return;
  const drv = driverEl.value;
  if (drv === 'local') {
    $('#stFields').innerHTML = `
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="stBase">本地路径</label>
          <input class="form-control" id="stBase" placeholder="如 /www/wwwroot/storage">
        </div>
      </div>
    `;
    return;
  }
  $('#stFields').innerHTML = `
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label" for="s3Endpoint">Endpoint</label>
        <input class="form-control" id="s3Endpoint" placeholder="endpoint（可空）">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="s3Region">Region</label>
        <input class="form-control" id="s3Region" placeholder="region">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="s3Bucket">Bucket</label>
        <input class="form-control" id="s3Bucket" placeholder="bucket">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="s3Key">Access Key</label>
        <input class="form-control" id="s3Key" placeholder="access_key">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="s3Secret">Secret Key</label>
        <input class="form-control" id="s3Secret" placeholder="secret_key">
      </div>
      <div class="col-12">
        <label class="form-label" for="s3PathStyle">访问模式</label>
        <select class="form-select" id="s3PathStyle"><option value="0">虚拟主机风格</option><option value="1">Path Style</option></select>
      </div>
    </div>
  `;
}

async function loadStorage() {
  const data = await api('storage/list');
  fillStorage(data.items || []);
  if (currentAdminPage === 'files' && Number($('#filesStorageSelect')?.value || 0) > 0 && explorerState.storageId === 0) {
    await loadExplorer('');
  }
}

function appendUploadTask(fileName) {
  const tbody = $('#uploadTasks');
  if (!tbody) return null;
  const tr = document.createElement('tr');
  tr.innerHTML = `<td>${escapeHtml(fileName)}</td><td class="task-progress-text">0.0%</td><td class="task-speed-text">-</td><td class="task-status-text">${t('waiting')}</td>`;
  tbody.appendChild(tr);
  return tr;
}

function updateTaskRow(row, progress, speed, status) {
  if (!row) return;
  if (typeof row.__taskCenterUpdate === 'function') {
    row.__taskCenterUpdate(progress, speed, status);
    return;
  }
  const p = Number.isFinite(progress) ? Math.max(0, Math.min(100, progress)) : 0;
  row.querySelector('.task-progress-text').textContent = `${p.toFixed(1)}%`;
  row.querySelector('.task-speed-text').textContent = speed;
  row.querySelector('.task-status-text').textContent = status;
}

function isTransientUploadError(message) {
  const msg = String(message || '');
  return /HTTP (502|503|504|520|522|524)/i.test(msg)
    || /bad gateway/i.test(msg)
    || /connection reset/i.test(msg)
    || /upstream/i.test(msg);
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function collectUploadFiles() {
  const picked = [];
  const fileInput = $('#fileInput');
  const folderInput = $('#folderInput');
  if (fileInput?.files) {
    for (const file of fileInput.files) {
      picked.push({ file, folderPath: '' });
    }
  }
  if (folderInput?.files) {
    for (const file of folderInput.files) {
      const rel = file.webkitRelativePath || '';
      const folderPath = rel.includes('/') ? rel.split('/').slice(0, -1).join('/') : '';
      picked.push({ file, folderPath });
    }
  }
  return picked;
}

function askDuplicateTaskAction(fileName, conflict) {
  const canResume = !!(conflict && conflict.can_resume);
  const status = conflict && conflict.status ? String(conflict.status) : '-';
  const updatedAt = conflict && conflict.updated_at ? String(conflict.updated_at) : '-';
  while (true) {
    const msg = [
      `检测到同名且MD5一致的上传任务：${fileName}`,
      `状态：${status}`,
      `更新时间：${updatedAt}`,
      canResume
        ? '1) 重新上传并替换  2) 分片补充  3) 取消'
        : '1) 重新上传并替换  2) 分片补充(不可用)  3) 取消',
      '请输入 1 / 2 / 3',
    ].join('\n');
    const input = window.prompt(msg, canResume ? '2' : '1');
    if (input === null) return 'cancel';
    const choice = String(input).trim();
    if (choice === '1') return 'restart';
    if (choice === '2') {
      if (canResume) return 'resume';
      alert('当前同名任务与本地文件大小/分片数不一致，不能分片补充，请选择重新上传并替换。');
      continue;
    }
    if (choice === '3') return 'cancel';
  }
}

async function initUploadSession(file, storageId, totalChunks, expectedMd5 = '', folderPath = '') {
  const payload = {
    name: file.name,
    size: file.size,
    chunks: totalChunks,
    storage_id: Number(storageId),
    folder_path: (folderPath || '').trim(),
  };
  if (expectedMd5) {
    payload.expected_md5 = expectedMd5;
  }
  try {
    return await api('upload/init', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  } catch (e) {
    const data = e && e.data ? e.data : null;
    if (!data || data.code !== 'duplicate_upload_task') {
      throw e;
    }
    const action = askDuplicateTaskAction(file.name, data.conflict || {});
    if (action === 'cancel') {
      throw new Error('Upload cancelled by user');
    }
    return await api('upload/init', {
      method: 'POST',
      body: JSON.stringify({
        ...payload,
        duplicate_strategy: action,
        conflict_token: data.conflict && data.conflict.token ? String(data.conflict.token) : '',
      }),
    });
  }
}

async function uploadFile(file, storageId, folderPath, row, restartAttempt = 0) {
  const normalizedFolderPath = (folderPath || '').trim();
  let expectedMd5 = '';
  try {
    expectedMd5 = await computeFileMd5(file, row);
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    console.warn('[upload] md5 compute failed:', msg);
    updateTaskRow(row, 0, '-', `${t('md5_failed')}: ${msg}`);
  }

  const fileIdentity = expectedMd5 || `lm${file.lastModified}`;
  const fileKey = `up:${file.name}:${file.size}:${fileIdentity}:${storageId}:${normalizedFolderPath}:${chunkSize}`;
  const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
  let token = localStorage.getItem(fileKey);

  if (!token) {
    const init = await initUploadSession(file, storageId, totalChunks, expectedMd5, normalizedFolderPath);
    if (init && init.skip_upload) {
      localStorage.removeItem(fileKey);
      updateTaskRow(row, 100, '-', '已存在同目录同名同MD5文件，跳过上传');
      return;
    }
    token = init.token;
    localStorage.setItem(fileKey, token);
  }

  const status = await api(`upload/status?token=${encodeURIComponent(token)}`);
  const uploaded = new Set(status.uploaded || []);
  const pending = [];
  for (let i = 0; i < totalChunks; i++) {
    if (!uploaded.has(i)) pending.push(i);
  }

  let uploadedBytes = Math.min(file.size, uploaded.size * chunkSize);
  let inFlight = 0;
  let done = uploaded.size;
  let ptr = 0;
  const speedTracker = createRealtimeSpeedTracker();
  speedTracker.reset(uploadedBytes);

  updateTaskRow(row, (done / totalChunks) * 100, '-', t('uploading'));

  await new Promise((resolve, reject) => {
    const runNext = () => {
      if (done >= totalChunks) {
        resolve();
        return;
      }
      while (inFlight < uploadConcurrency && ptr < pending.length) {
        const idx = pending[ptr++];
        inFlight++;
        sendChunk(idx)
          .then((sentBytes) => {
            inFlight--;
            done++;
            uploadedBytes += sentBytes;
            updateTaskRow(row, (uploadedBytes / Math.max(1, file.size)) * 100, speedTracker.sample(uploadedBytes), t('uploading'));
            runNext();
          })
          .catch(reject);
      }
      if (inFlight === 0 && ptr >= pending.length && done < totalChunks) {
        reject(new Error('上传中断'));
      }
    };

    const sendChunk = async (idx, retry = 0) => {
      const start = idx * chunkSize;
      const end = Math.min(file.size, start + chunkSize);
      const blob = file.slice(start, end);
      const fd = new FormData();
      fd.append('token', token);
      fd.append('chunk_index', idx);
      fd.append('chunk', blob, `${file.name}.part${idx}`);
      try {
        const data = await postChunkForm(fd, (loaded) => {
          const currentBytes = Math.min(file.size, uploadedBytes + Math.min(blob.size, Number(loaded || 0)));
          const speed = speedTracker.sample(currentBytes);
          updateTaskRow(row, (currentBytes / Math.max(1, file.size)) * 100, speed, t('uploading'));
        });
        if (!data.ok) throw new Error(data.message || '上传失败');
        return blob.size;
      } catch (e) {
        if (retry < 3) return sendChunk(idx, retry + 1);
        const detail = e instanceof Error ? e.message : String(e);
        throw new Error(`分片 ${idx + 1}/${totalChunks} 失败: ${detail}`);
      }
    };

    runNext();
  });

  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const completeUpload = async (retry = 0) => {
    try {
      return await api('upload/complete', {
        method: 'POST',
        body: JSON.stringify({
          token,
          folder_path: normalizedFolderPath,
          mime_type: file.type || 'application/octet-stream',
        }),
      });
    } catch (e) {
      const msg = e instanceof Error ? e.message : String(e);
      if (/Missing chunk/i.test(msg) || /Not all chunks uploaded/i.test(msg)) {
        throw e;
      }
      try {
        const st = await api(`upload/status?token=${encodeURIComponent(token)}`);
        if ((st.status || '') === 'completed') {
          return { ok: true, recovered: true };
        }
        if ((st.status || '') === 'merging' && retry < completeRetryLimit) {
          updateTaskRow(row, 100, '-', t('merging_wait'));
          await wait(2000);
          return completeUpload(retry + 1);
        }
      } catch (_) {}
      if (retry < completeRetryLimit) {
        await wait(2000);
        return completeUpload(retry + 1);
      }
      throw e;
    }
  };
  let complete = null;
  try {
    complete = await completeUpload();
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    if (restartAttempt < 2 && /Missing chunk/i.test(msg)) {
      updateTaskRow(row, 0, '-', t('reuploading'));
      return uploadFile(file, storageId, folderPath, row, restartAttempt + 1);
    }
    if (restartAttempt < 1 && /Upload session not found/i.test(msg)) {
      localStorage.removeItem(fileKey);
      updateTaskRow(row, 0, '-', t('reuploading'));
      return uploadFile(file, storageId, folderPath, row, restartAttempt + 1);
    }
    throw e;
  }
  if (!complete.ok) throw new Error(complete.message || '合并失败');

  localStorage.removeItem(fileKey);
  updateTaskRow(row, 100, '-', t('completed'));
}

function normalizeFolderPath(path) {
  const raw = String(path || '').replace(/\\/g, '/').trim();
  if (!raw) return '';
  const parts = raw.split('/').map((x) => x.trim()).filter((x) => x && x !== '.' && x !== '..');
  return parts.join('/');
}

function splitFolderParentAndName(path) {
  const normalized = normalizeFolderPath(path);
  if (!normalized) return ['', ''];
  const pos = normalized.lastIndexOf('/');
  if (pos < 0) return ['', normalized];
  return [normalized.slice(0, pos), normalized.slice(pos + 1)];
}

function escapeHtml(input) {
  return String(input || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function selectedFilesAndFolders() {
  return {
    file_ids: [...explorerState.selectedFiles].filter((n) => Number.isFinite(n) && n > 0),
    folder_paths: [...explorerState.selectedFolders].filter(Boolean),
  };
}

function syncExplorerSelection() {
  explorerState.selectedFiles = new Set(
    $$('.explorer-file-checkbox:checked').map((x) => Number(x.value)).filter((n) => Number.isFinite(n) && n > 0),
  );
  explorerState.selectedFolders = new Set(
    $$('.explorer-folder-checkbox:checked').map((x) => normalizeFolderPath(x.dataset.folder || '')).filter(Boolean),
  );
}

function createdAtToMs(value) {
  const raw = String(value || '').trim();
  if (!raw) return 0;
  const ms = Date.parse(raw.replace(' ', 'T'));
  return Number.isFinite(ms) ? ms : 0;
}

function updateCreatedAtSortButtonLabel() {
  const btn = $('#sortCreatedAtBtn');
  if (!btn) return;
  const arrow = explorerState.createdAtSort === 'asc' ? '↑' : '↓';
  btn.textContent = `${t('col_created_at')} ${arrow}`;
}

function renderExplorerRows() {
  const tbody = $('#explorerRows');
  if (!tbody) return;
  const rows = [];

  for (const folder of explorerState.folders) {
    const path = normalizeFolderPath(folder.path || '');
    const checked = explorerState.selectedFolders.has(path) ? 'checked' : '';
    rows.push(`
      <tr>
        <td><input type="checkbox" class="explorer-folder-checkbox" data-folder="${escapeHtml(path)}" ${checked}></td>
        <td>DIR</td>
        <td><button type="button" class="btn btn-link btn-sm px-0 text-decoration-none open-folder" data-path="${escapeHtml(path)}">${escapeHtml(folder.name || '(未命名文件夹)')}</button></td>
        <td>-</td>
        <td>-</td>
        <td>${escapeHtml(path)}</td>
      </tr>
    `);
  }

  const files = [...explorerState.files].sort((a, b) => {
    const ams = createdAtToMs(a.created_at);
    const bms = createdAtToMs(b.created_at);
    if (ams === bms) {
      return Number(b.id || 0) - Number(a.id || 0);
    }
    return explorerState.createdAtSort === 'asc' ? (ams - bms) : (bms - ams);
  });

  for (const file of files) {
    const fileId = Number(file.id || 0);
    const checked = explorerState.selectedFiles.has(fileId) ? 'checked' : '';
    rows.push(`
      <tr>
        <td><input type="checkbox" class="explorer-file-checkbox" value="${fileId}" ${checked}></td>
        <td>FILE</td>
        <td>${escapeHtml(file.original_name || '-')}</td>
        <td>${formatBytes(file.size || 0)}</td>
        <td>${escapeHtml(file.created_at || '-')}</td>
        <td>${escapeHtml(normalizeFolderPath(file.folder_path || ''))}</td>
      </tr>
    `);
  }

  tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="6">当前目录为空</td></tr>';
  $$('.explorer-file-checkbox, .explorer-folder-checkbox').forEach((el) => {
    el.addEventListener('change', syncExplorerSelection);
  });
  $$('.open-folder').forEach((btn) => {
    btn.addEventListener('click', () => {
      const path = normalizeFolderPath(btn.dataset.path || '');
      loadExplorer(path).catch((e) => alert(e.message));
    });
  });
  updateCreatedAtSortButtonLabel();
}

function fillFilesStorageSelect(items) {
  const select = $('#filesStorageSelect');
  if (!select) return;
  select.innerHTML = '';
  items.forEach((s) => {
    const option = document.createElement('option');
    option.value = String(s.id);
    option.textContent = `${s.name} (${s.driver})`;
    select.appendChild(option);
  });
}

async function loadExplorer(path = explorerState.currentPath) {
  const select = $('#filesStorageSelect');
  if (!select) return;
  const storageId = Number(select.value || explorerState.storageId || 0);
  if (!storageId) {
    const tbody = $('#explorerRows');
    if (tbody) tbody.innerHTML = '<tr><td colspan="6">请先创建并选择存储位置</td></tr>';
    return;
  }
  const normalizedPath = normalizeFolderPath(path);
  const data = await api(`files/list?storage_id=${storageId}&folder_path=${encodeURIComponent(normalizedPath)}`);
  explorerState.storageId = storageId;
  explorerState.currentPath = normalizeFolderPath(data.current_path || normalizedPath);
  explorerState.folders = Array.isArray(data.folders) ? data.folders : [];
  explorerState.files = Array.isArray(data.files) ? data.files : [];
  explorerState.selectedFiles = new Set();
  explorerState.selectedFolders = new Set();
  const pathInput = $('#currentFolderPath');
  if (pathInput) pathInput.value = explorerState.currentPath || '/';
  const targetFolder = $('#targetFolder');
  if (targetFolder) targetFolder.value = explorerState.currentPath;
  renderExplorerRows();
}

async function collectFileIdsForFolder(storageId, folderPath, sink) {
  const path = normalizeFolderPath(folderPath);
  const data = await api(`files/list?storage_id=${storageId}&folder_path=${encodeURIComponent(path)}`);
  (data.files || []).forEach((file) => {
    const id = Number(file.id || 0);
    if (id > 0) sink.add(id);
  });
  for (const folder of (data.folders || [])) {
    const childPath = normalizeFolderPath(folder.path || '');
    if (!childPath) continue;
    await collectFileIdsForFolder(storageId, childPath, sink);
  }
}

async function buildShareSelectionFromFilesPage() {
  const sel = selectedFilesAndFolders();
  const fileIds = new Set(sel.file_ids);
  for (const folderPath of sel.folder_paths) {
    await collectFileIdsForFolder(explorerState.storageId, folderPath, fileIds);
  }
  return [...fileIds].filter((n) => Number.isFinite(n) && n > 0);
}

async function createShareFromFiles() {
  const ids = await buildShareSelectionFromFilesPage();
  if (!ids.length) return alert('请先选择文件或文件夹');
  const payload = {
    title: ($('#shareTitle')?.value || '').trim(),
    password: ($('#sharePwd')?.value || '').trim(),
    expires_at: $('#shareExp')?.value ? new Date($('#shareExp').value).toISOString().slice(0, 19).replace('T', ' ') : '',
    file_ids: ids,
  };
  const data = await api('share/create', { method: 'POST', body: JSON.stringify(payload) });
  const fullLink = `${origin}${data.link}`;
  const box = $('#shareResult');
  if (box) {
    box.innerHTML = `分享已创建<br><a href="${escapeHtml(fullLink)}" target="_blank" rel="noopener noreferrer">${escapeHtml(fullLink)}</a><div class="mt-2"><button id="copyNewShare" class="btn btn-sm btn-success">一键复制</button></div>`;
    $('#copyNewShare')?.addEventListener('click', async () => {
      await copyText(fullLink);
      alert('已复制分享链接');
    });
  }
}

async function filesDeleteSelected() {
  const sel = selectedFilesAndFolders();
  if (!sel.file_ids.length && !sel.folder_paths.length) return alert('请先选择要删除的文件或文件夹');
  if (!confirm('删除后不可恢复，确认继续？')) return;
  await api('files/delete', {
    method: 'POST',
    body: JSON.stringify({ ...sel, storage_id: explorerState.storageId }),
  });
  await loadExplorer(explorerState.currentPath);
}

async function filesRenameSelected() {
  const sel = selectedFilesAndFolders();
  if (sel.file_ids.length + sel.folder_paths.length !== 1) {
    alert('重命名一次只能选择一个文件或一个文件夹');
    return;
  }
  if (sel.file_ids.length === 1) {
    const id = sel.file_ids[0];
    const row = explorerState.files.find((x) => Number(x.id) === id);
    const oldName = row?.original_name || '';
    const newName = prompt('请输入新的文件名', oldName);
    if (!newName || newName === oldName) return;
    await api('files/rename', { method: 'POST', body: JSON.stringify({ file_id: id, new_name: newName.trim() }) });
    await loadExplorer(explorerState.currentPath);
    return;
  }

  const oldFolder = normalizeFolderPath(sel.folder_paths[0]);
  const [, oldFolderName] = splitFolderParentAndName(oldFolder);
  const newFolderName = prompt('请输入新的文件夹名称', oldFolderName);
  if (!newFolderName || newFolderName === oldFolderName) return;
  const [parentFolder] = splitFolderParentAndName(oldFolder);
  const newFolderPath = normalizeFolderPath(parentFolder ? `${parentFolder}/${newFolderName.trim()}` : newFolderName.trim());
  await api('files/rename', {
    method: 'POST',
    body: JSON.stringify({
      storage_id: explorerState.storageId,
      folder_path: oldFolder,
      new_folder_path: newFolderPath,
    }),
  });
  await loadExplorer(parentFolder || '');
}

function filesCopySelected() {
  const sel = selectedFilesAndFolders();
  if (!sel.file_ids.length && !sel.folder_paths.length) return alert('请先选择要复制的文件或文件夹');
  clipboardData = { ...sel, storage_id: explorerState.storageId };
  alert(`已复制 ${sel.file_ids.length} 个文件，${sel.folder_paths.length} 个文件夹`);
}

async function filesPasteClipboard() {
  if (!clipboardData.file_ids.length && !clipboardData.folder_paths.length) return alert('剪贴板为空，请先复制');
  if (Number(clipboardData.storage_id || 0) !== explorerState.storageId) {
    alert('复制和粘贴必须在同一存储位置内进行');
    return;
  }
  const target_folder_path = normalizeFolderPath(($('#targetFolder')?.value || '').trim()) || explorerState.currentPath;
  const result = await api('files/copy', {
    method: 'POST',
    body: JSON.stringify({ ...clipboardData, target_folder_path, storage_id: explorerState.storageId }),
  });
  alert(`粘贴完成：复制 ${Number(result.count || 0)}，跳过 ${Number(result.skipped || 0)}`);
  await loadExplorer(target_folder_path);
}

async function filesCreateFolder() {
  const name = prompt('请输入新建文件夹名称');
  if (!name) return;
  const normalizedName = normalizeFolderPath(name);
  if (!normalizedName || normalizedName.includes('/')) {
    alert('文件夹名称不合法');
    return;
  }
  const folder_path = normalizeFolderPath(explorerState.currentPath ? `${explorerState.currentPath}/${normalizedName}` : normalizedName);
  await api('files/folder/create', {
    method: 'POST',
    body: JSON.stringify({ storage_id: explorerState.storageId, folder_path }),
  });
  await loadExplorer(explorerState.currentPath);
}

async function openParentFolder() {
  if (!explorerState.currentPath) return;
  const [parent] = splitFolderParentAndName(explorerState.currentPath);
  await loadExplorer(parent || '');
}

async function cancelShare(shareId, shareCode) {
  await api('share/delete', { method: 'POST', body: JSON.stringify({ id: shareId, code: shareCode || '' }) });
}

function renderSystemInfo(data) {
  const lines = [];
  lines.push(`网站上传文件总数: ${Number(data.file_total_count || 0).toLocaleString('zh-CN')}`);
  lines.push(`网站上传文件总体积: ${formatBytes(data.file_total_size || 0)}`);
  lines.push(`当前系统时间（东八区）: ${data.current_time_utc8 || '-'}`);

  return lines.join('\n');
}

async function loadSystemInfo() {
  const data = await api('system/info');
  const box = $('#systemInfo');
  if (box) box.textContent = renderSystemInfo(data);
}

function initStoragePage() {
  const stDriver = $('#stDriver');
  const saveStorage = $('#saveStorage');
  if (!stDriver || !saveStorage) return;
  stDriver.addEventListener('change', storageFields);
  storageFields();
  saveStorage.addEventListener('click', async () => {
    const driver = $('#stDriver').value;
    const name = ($('#stName').value || '').trim();
    let config = {};
    if (driver === 'local') {
      config.base_path = ($('#stBase').value || '').trim();
    } else {
      config = {
        endpoint: ($('#s3Endpoint').value || '').trim(),
        region: ($('#s3Region').value || '').trim(),
        bucket: ($('#s3Bucket').value || '').trim(),
        access_key: ($('#s3Key').value || '').trim(),
        secret_key: ($('#s3Secret').value || '').trim(),
        path_style: $('#s3PathStyle').value === '1',
      };
    }
    const res = await api('storage/create', { method: 'POST', body: JSON.stringify({ name, driver, config }) });
    if (res.ok) {
      alert('存储已保存');
      location.reload();
    }
  });
}

function initUploadPage() {
  const uploadBtn = $('#uploadBtn');
  const log = $('#uploadLog');
  if (!uploadBtn) return;
  if (uploadBtn.dataset.boundUploadClick === '1') return;
  uploadBtn.dataset.boundUploadClick = '1';

  uploadBtn.addEventListener('click', async () => {
    const taskCenter = window.__TASK_CENTER__;
    const items = collectUploadFiles();
    if (!items.length) {
      alert('请选择文件或文件夹');
      return;
    }

    const storageId = Number($('#storageSelect')?.value || 0);
    if (!storageId) {
      alert('请选择存储位置');
      return;
    }

    if (taskCenter && typeof taskCenter.enqueueUploads === 'function') {
      const queued = taskCenter.enqueueUploads(items, {
        storageId,
        source: 'upload-page',
      });
      taskCenter.open?.();
      const tbody = $('#uploadTasks');
      if (tbody) {
        tbody.innerHTML = '';
        queued.forEach((task) => {
          const row = appendUploadTask(task.fileName || task.name || '未命名文件');
          updateTaskRow(row, 0, '-', '已加入右下角任务中心');
        });
      }
      if (log) log.textContent = `已加入右下角任务中心：${queued.length} 个文件`;
      if ($('#fileInput')) $('#fileInput').value = '';
      if ($('#folderInput')) $('#folderInput').value = '';
      return;
    }

    if (window.__UPLOAD_RUNNING__) {
      if (log) log.textContent = '已有上传任务在进行，请等待完成';
      return;
    }
    window.__UPLOAD_RUNNING__ = true;
    uploadBtn.disabled = true;

    const tbody = $('#uploadTasks');
    if (tbody) tbody.innerHTML = '';
    const taskRows = items.map((it) => appendUploadTask(it.file.name));
    if (log) log.textContent = `已加入队列: ${items.length} 个文件`;

    let success = 0;
    let failed = 0;

    try {
      for (let i = 0; i < items.length; i++) {
        const it = items[i];
        const row = taskRows[i] || null;
        let attempt = 0;
        let done = false;
        while (!done) {
          try {
            await uploadFile(it.file, storageId, it.folderPath || '', row);
            success += 1;
            done = true;
            if (log) log.textContent = `上传中... 成功 ${success} / 失败 ${failed} / 总数 ${items.length}`;
          } catch (e) {
            attempt += 1;
            const msg = e instanceof Error ? e.message : String(e);
            if (/Upload cancelled by user/i.test(msg) || /用户取消上传/.test(msg)) {
              done = true;
              updateTaskRow(row, 0, '-', '已取消');
              if (log) log.textContent = `上传中... 成功 ${success} / 失败 ${failed} / 总数 ${items.length}`;
              continue;
            }
            if (attempt <= 2 && isTransientUploadError(msg)) {
              updateTaskRow(row, 0, '-', `网络波动，自动重试 ${attempt}/2`);
              await delay(2000 * attempt);
              continue;
            }
            failed += 1;
            done = true;
            updateTaskRow(row, 0, '-', `失败: ${msg}`);
            if (log) log.textContent = `上传中... 成功 ${success} / 失败 ${failed} / 总数 ${items.length}`;
          }
        }
      }

      if (failed > 0) {
        alert(`上传完成：成功 ${success}，失败 ${failed}`);
      } else {
        alert(`上传完成：共 ${success} 个文件`);
      }
    } finally {
      window.__UPLOAD_RUNNING__ = false;
      uploadBtn.disabled = false;
    }
  });
}

function initFilesPage() {
  fillFilesStorageSelect(window.__INITIAL_STORAGE__ || []);
  const filesStorageSelect = $('#filesStorageSelect');
  if (filesStorageSelect && !filesStorageSelect.value && filesStorageSelect.options.length) {
    filesStorageSelect.value = filesStorageSelect.options[0].value;
  }
  filesStorageSelect?.addEventListener('change', () => {
    loadExplorer('').catch((e) => alert(e.message));
  });

  $('#createShareFromFiles')?.addEventListener('click', () => createShareFromFiles().catch((e) => alert(e.message)));
  $('#opNewFolder')?.addEventListener('click', () => filesCreateFolder().catch((e) => alert(e.message)));
  $('#opCopy')?.addEventListener('click', filesCopySelected);
  $('#opPaste')?.addEventListener('click', () => filesPasteClipboard().catch((e) => alert(e.message)));
  $('#opRename')?.addEventListener('click', () => filesRenameSelected().catch((e) => alert(e.message)));
  $('#opDelete')?.addEventListener('click', () => filesDeleteSelected().catch((e) => alert(e.message)));
  $('#goParentFolder')?.addEventListener('click', () => openParentFolder().catch((e) => alert(e.message)));
  $('#sortCreatedAtBtn')?.addEventListener('click', () => {
    explorerState.createdAtSort = explorerState.createdAtSort === 'desc' ? 'asc' : 'desc';
    renderExplorerRows();
  });

  loadExplorer('').catch((e) => {
    const tbody = $('#explorerRows');
    if (tbody) tbody.innerHTML = `<tr><td colspan="6">加载失败: ${escapeHtml(e.message)}</td></tr>`;
  });
}

function initSharesPage() {
  const log = $('#shareManageLog');
  $$('.copy-share').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await copyText(btn.dataset.link || '');
        if (log) log.textContent = '已复制分享链接';
      } catch (e) {
        if (log) log.textContent = e.message;
      }
    });
  });
  $$('.cancel-share').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (!confirm('确定取消该分享吗？')) return;
      try {
        await cancelShare(Number(btn.dataset.id), btn.dataset.code || '');
        btn.closest('tr')?.remove();
        if (log) log.textContent = '已取消分享';
      } catch (e) {
        if (log) log.textContent = e.message;
      }
    });
  });
}

function initSystemPage() {
  const btn = $('#loadSystemInfo');
  const box = $('#systemInfo');
  if (!btn) return;
  btn.addEventListener('click', () => loadSystemInfo().catch((e) => {
    if (box) box.textContent = `加载失败: ${e.message}`;
  }));
  loadSystemInfo().catch((e) => {
    if (box) box.textContent = `加载失败: ${e.message}`;
  });
}

async function loadCustomButtons() {
  const data = await api('custom-buttons/get');
  const list = Array.isArray(data.buttons) ? data.buttons : [];
  const b1 = list[0] || { text: '', url: '' };
  const b2 = list[1] || { text: '', url: '' };
  $('#customBtn1Text').value = b1.text || '';
  $('#customBtn1Url').value = b1.url || '';
  $('#customBtn2Text').value = b2.text || '';
  $('#customBtn2Url').value = b2.url || '';
}

function initCustomPage() {
  const saveBtn = $('#saveCustomButtons');
  const log = $('#customButtonsLog');
  if (!saveBtn) return;

  loadCustomButtons().catch((e) => {
    if (log) log.textContent = `加载失败: ${e.message}`;
  });

  saveBtn.addEventListener('click', async () => {
    const payload = {
      buttons: [
        { text: ($('#customBtn1Text')?.value || '').trim(), url: ($('#customBtn1Url')?.value || '').trim() },
        { text: ($('#customBtn2Text')?.value || '').trim(), url: ($('#customBtn2Url')?.value || '').trim() },
      ],
    };
    try {
      await api('custom-buttons/save', { method: 'POST', body: JSON.stringify(payload) });
      if (log) log.textContent = '保存成功';
    } catch (e) {
      if (log) log.textContent = `保存失败: ${e.message}`;
    }
  });
}

function initAdminPage(page = window.__ADMIN_PAGE__ || 'upload') {
  currentAdminPage = page || 'upload';
  window.__ADMIN_PAGE__ = currentAdminPage;
  applyI18n();
  fillStorage(window.__INITIAL_STORAGE__ || []);

  if (currentAdminPage === 'upload') initUploadPage();
  if (currentAdminPage === 'storage') initStoragePage();
  if (currentAdminPage === 'files' && !document.querySelector('#fileManagerApp')) initFilesPage();
  if (currentAdminPage === 'shares') initSharesPage();
  if (currentAdminPage === 'system') initSystemPage();
  if (currentAdminPage === 'custom') initCustomPage();
}

setupSidebarToggle();
initLangSwitch();
window.__ADMIN_INIT_PAGE__ = initAdminPage;
fillStorage(window.__INITIAL_STORAGE__ || []);
loadStorage().catch(() => {});
initAdminPage(currentAdminPage);

