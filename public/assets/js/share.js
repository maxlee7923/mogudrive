const code = window.__SHARE_CODE__;
const shareIconBase = (window.__SHARE_ICON_BASE__ || '/public/assets/icons/share').replace(/\/+$/, '');
const shareIconVersion = '20260307-10';
const chunkSize = 30 * 1024 * 1024;
const chunkConcurrency = 3;
const entry = window.__ENTRY__ || '/index.php';
const siteName = window.__SITE_NAME__ || '蘑菇网盘';
const routeUrl = (path) => `${entry}?r=${String(path || '').replace(/^\/+/, '')}`;
const dlDbName = 'megaish_share_downloads';
const dlStore = 'chunks';
const dlMetaPrefix = 'dlmeta:';

const $ = (s) => document.querySelector(s);
const $$ = (s) => [...document.querySelectorAll(s)];
const LANG_KEY = 'mungsos_lang';
let currentLang = localStorage.getItem(LANG_KEY) || 'zh-CN';
const I18N = {
  'zh-CN': {
    brand: siteName,
    lang_toggle: 'English',
    share_subtitle: '文件分享与下载',
    share_heading: '分享标题',
    share_created_at: '创建时间：{time}',
    locked_tip: '该链接已加密，请输入提取码。',
    extract_code: '提取码',
    unlock: '解锁',
    col_filename: '文件名',
    col_size: '大小',
    col_action: '操作',
    download_zip: '一键下载 ZIP',
    invalid_share: '分享已取消或文件已过期。',
    btn_default: '按钮 {n}',
    resuming: '检测到断点，继续下载 {name}: {percent}%',
    downloading_start: '正在下载 {name}: 0.0%',
    downloading: '正在下载 {name}: {percent}%',
    download_done: '下载完成: {name} (100%)',
    zip_load_fail: 'ZIP 组件加载失败，请刷新后重试',
    no_files: '当前没有可下载文件',
    zip_file_progress: '({i}/{n}) 下载 {name}: {percent}%',
    zipping_start: '正在打包 ZIP: 0.0%',
    zipping: '正在打包 ZIP: {percent}%',
    zip_done: 'ZIP 打包完成: {name}',
    load_fail: '加载失败',
    download: '下载',
    download_fail: '下载失败: {name} ({msg})',
    unlock_fail: '提取码错误',
    zip_download_fail: '打包下载失败: {msg}',
  },
  en: {
    brand: siteName,
    lang_toggle: '简体中文',
    share_subtitle: 'File sharing and downloads',
    share_heading: 'Share title',
    share_created_at: 'Created at: {time}',
    locked_tip: 'This link is protected. Please enter extraction code.',
    extract_code: 'Extraction code',
    unlock: 'Unlock',
    col_filename: 'File Name',
    col_size: 'Size',
    col_action: 'Action',
    download_zip: 'Download ZIP',
    invalid_share: 'Share was canceled or expired.',
    btn_default: 'Button {n}',
    resuming: 'Resume {name}: {percent}%',
    downloading_start: 'Downloading {name}: 0.0%',
    downloading: 'Downloading {name}: {percent}%',
    download_done: 'Download completed: {name} (100%)',
    zip_load_fail: 'ZIP component failed to load. Please refresh.',
    no_files: 'No files available for download',
    zip_file_progress: '({i}/{n}) Downloading {name}: {percent}%',
    zipping_start: 'Zipping: 0.0%',
    zipping: 'Zipping: {percent}%',
    zip_done: 'ZIP completed: {name}',
    load_fail: 'Load failed',
    download: 'Download',
    download_fail: 'Download failed: {name} ({msg})',
    unlock_fail: 'Invalid extraction code',
    zip_download_fail: 'ZIP download failed: {msg}',
  },
};

function t(key, vars = {}) {
  const dict = I18N[currentLang] || I18N['zh-CN'];
  let out = dict[key] || I18N['zh-CN'][key] || key;
  Object.keys(vars).forEach((k) => {
    out = out.replaceAll(`{${k}}`, String(vars[k]));
  });
  return out;
}

function applyI18n() {
  $$('[data-i18n]').forEach((el) => {
    el.textContent = t(el.getAttribute('data-i18n') || '');
  });
  $$('[data-i18n-placeholder]').forEach((el) => {
    el.setAttribute('placeholder', t(el.getAttribute('data-i18n-placeholder') || ''));
  });
}

function initLangSwitch() {
  if (currentLang !== 'zh-CN' && currentLang !== 'en') currentLang = 'zh-CN';
  applyI18n();
  updateShareSubtitle();
  updateShareInfoCard();
  $('#langSwitchShare')?.addEventListener('click', () => {
    currentLang = currentLang === 'zh-CN' ? 'en' : 'zh-CN';
    localStorage.setItem(LANG_KEY, currentLang);
    applyI18n();
    updateShareSubtitle();
    updateShareInfoCard();
  });
}

const state = { items: [], byId: new Map(), title: siteName, shareTitle: '', shareCreatedAt: '', customButtons: [] };
let downloadTaskQueue = Promise.resolve();

function updateShareSubtitle() {
  const el = $('#shareSubtitle');
  if (!el) return;
  el.textContent = t('share_subtitle');
}

function updateShareInfoCard() {
  const card = $('#shareInfoCard');
  const title = $('#shareDisplayTitle');
  const meta = $('#shareDisplayMeta');
  const shareTitle = String(state.shareTitle || '').trim();
  if (!(card instanceof HTMLElement) || !(title instanceof HTMLElement) || !(meta instanceof HTMLElement)) return;
  if (!shareTitle) {
    card.classList.add('hidden');
    title.textContent = '';
    meta.textContent = '';
    return;
  }
  card.classList.remove('hidden');
  title.textContent = shareTitle;
  meta.textContent = state.shareCreatedAt ? t('share_created_at', { time: state.shareCreatedAt }) : '';
}

function queueDownload(task) {
  const run = () => Promise.resolve().then(task);
  const queued = downloadTaskQueue.then(run, run);
  downloadTaskQueue = queued.catch(() => {});
  return queued;
}

function setLog(msg) {
  const el = $('#downloadLog');
  if (!el) return;
  el.textContent = msg;
}

function showShareNotice(message) {
  const box = $('#shareNotice');
  if (!box) return;
  box.textContent = message || t('invalid_share');
  box.classList.remove('hidden');
  $('#lockedBox')?.classList.add('hidden');
  $('#filesBox')?.classList.add('hidden');
}

async function fetchJson(url, opt = {}) {
  const res = await fetch(url, opt);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function fmt(size) {
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(2)} KB`;
  if (size < 1024 * 1024 * 1024) return `${(size / 1024 / 1024).toFixed(2)} MB`;
  return `${(size / 1024 / 1024 / 1024).toFixed(2)} GB`;
}

function saveBlob(blob, name) {
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = name;
  link.click();
  setTimeout(() => URL.revokeObjectURL(link.href), 60_000);
}

function safeZipName(input) {
  return (input || 'share').replace(/[\\/:*?"<>|]/g, '_');
}

function dlSessionKey(item) {
  return `${code}:${Number(item.id)}:${Number(item.size || 0)}:${chunkSize}`;
}

function metaKey(sessionKey) {
  return `${dlMetaPrefix}${sessionKey}`;
}

function loadMeta(sessionKey) {
  try {
    const raw = localStorage.getItem(metaKey(sessionKey));
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || !Array.isArray(parsed.done)) return null;
    return parsed;
  } catch (_) {
    return null;
  }
}

function saveMeta(sessionKey, doneSet, totalChunks) {
  localStorage.setItem(metaKey(sessionKey), JSON.stringify({
    total_chunks: totalChunks,
    done: [...doneSet],
    updated_at: Date.now(),
  }));
}

function clearMeta(sessionKey) {
  localStorage.removeItem(metaKey(sessionKey));
}

function idbRequest(req) {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error || new Error('IDB request failed'));
  });
}

let dbPromise = null;
function openDlDb() {
  if (dbPromise) return dbPromise;
  dbPromise = new Promise((resolve, reject) => {
    const req = indexedDB.open(dlDbName, 1);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(dlStore)) {
        const store = db.createObjectStore(dlStore, { keyPath: 'key' });
        store.createIndex('session', 'session', { unique: false });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error || new Error('open indexedDB failed'));
  });
  return dbPromise;
}

function chunkKey(sessionKey, idx) {
  return `${sessionKey}:${idx}`;
}

async function putChunkCache(sessionKey, idx, buf) {
  const db = await openDlDb();
  const tx = db.transaction(dlStore, 'readwrite');
  const store = tx.objectStore(dlStore);
  await idbRequest(store.put({
    key: chunkKey(sessionKey, idx),
    session: sessionKey,
    idx,
    data: buf,
  }));
  await new Promise((resolve, reject) => {
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error || new Error('idb tx failed'));
    tx.onabort = () => reject(tx.error || new Error('idb tx aborted'));
  });
}

async function getChunkCache(sessionKey, idx) {
  const db = await openDlDb();
  const tx = db.transaction(dlStore, 'readonly');
  const store = tx.objectStore(dlStore);
  const row = await idbRequest(store.get(chunkKey(sessionKey, idx)));
  return row?.data || null;
}

async function clearChunkCache(sessionKey) {
  const db = await openDlDb();
  const tx = db.transaction(dlStore, 'readwrite');
  const store = tx.objectStore(dlStore);
  const bySession = store.index('session');
  const range = IDBKeyRange.only(sessionKey);
  await new Promise((resolve, reject) => {
    const req = bySession.openCursor(range);
    req.onsuccess = () => {
      const cursor = req.result;
      if (!cursor) {
        resolve();
        return;
      }
      cursor.delete();
      cursor.continue();
    };
    req.onerror = () => reject(req.error || new Error('idb cursor failed'));
  });
  await new Promise((resolve, reject) => {
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error || new Error('idb tx failed'));
    tx.onabort = () => reject(tx.error || new Error('idb tx aborted'));
  });
}

function extOf(name) {
  const idx = (name || '').lastIndexOf('.');
  if (idx < 0) return '';
  return (name || '').slice(idx + 1).toLowerCase();
}

function iconUrl(kind) {
  const map = {
    folder: 'folder.svg',
    android: 'brand-android.svg',
    archive: 'archive.svg',
    image: 'photo.svg',
    video: 'video.svg',
    audio: 'music.svg',
    text: 'file-description.svg',
    file: 'file.svg',
  };
  return `${map[kind] || map.file}?v=${shareIconVersion}`;
}

function iconSvg(kind) {
  const file = iconUrl(kind);
  const primary = `${shareIconBase}/${file}`;
  const fallback = `/public/assets/icons/share/${file}`;
  if (primary === fallback) {
    return `<img src="${primary}" alt="" loading="lazy" />`;
  }
  return `<img src="${primary}" alt="" loading="lazy" onerror="if(this.dataset.fbk!=='1'){this.dataset.fbk='1';this.src='${fallback}';}" />`;
}

function fileKind(item) {
  const name = String(item.original_name || '');
  const ext = extOf(name);
  const mime = String(item.mime_type || '').toLowerCase();

  if (mime === 'inode/directory' || name.endsWith('/')) return 'folder';
  if (ext === 'apk') return 'android';
  if (['zip', '7z', 'rar', 'tar', 'gz', 'bz2', 'xz'].includes(ext)) return 'archive';
  if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) return 'image';
  if (mime.startsWith('video/') || ['mp4', 'mkv', 'avi', 'mov', 'webm'].includes(ext)) return 'video';
  if (mime.startsWith('audio/') || ['mp3', 'wav', 'flac', 'aac', 'ogg'].includes(ext)) return 'audio';
  if (['txt', 'md', 'json', 'xml', 'log', 'csv', 'yaml', 'yml'].includes(ext)) return 'text';
  return 'file';
}

function renderCustomButtons() {
  const box = $('#customButtons');
  if (!box) return;
  box.innerHTML = '';
  const list = Array.isArray(state.customButtons) ? state.customButtons : [];
  for (let i = 0; i < 2; i++) {
    const row = list[i] || {};
    const text = (row.text || '').trim() || t('btn_default', { n: i + 1 });
    const url = (row.url || '').trim();
    const a = document.createElement('a');
    a.className = 'btn btn-outline-primary btn-sm';
    a.textContent = text;
    a.href = url || 'javascript:void(0)';
    if (url) {
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    } else {
      a.setAttribute('aria-disabled', 'true');
    }
    box.appendChild(a);
  }
}

async function fetchFileBlob(item, onPercent) {
  const fileSize = Number(item.size || 0);
  const totalChunks = Math.ceil(fileSize / chunkSize);
  const parts = new Array(totalChunks);
  let cacheEnabled = true;
  try {
    await openDlDb();
  } catch (_) {
    cacheEnabled = false;
  }
  const sessionKey = dlSessionKey(item);
  const loaded = cacheEnabled ? loadMeta(sessionKey) : null;
  const doneSet = new Set(
    loaded && Number(loaded.total_chunks) === totalChunks
      ? loaded.done.map((n) => Number(n)).filter((n) => Number.isInteger(n) && n >= 0 && n < totalChunks)
      : []
  );
  const pending = [];
  for (let i = 0; i < totalChunks; i++) {
    if (!doneSet.has(i)) pending.push(i);
  }

  let done = doneSet.size;
  let cursor = 0;
  let inFlight = 0;
  onPercent?.((done / Math.max(1, totalChunks)) * 100);

  await new Promise((resolve, reject) => {
    const run = () => {
      if (done >= totalChunks) return resolve();
      while (inFlight < chunkConcurrency && cursor < pending.length) {
        const idx = pending[cursor++];
        inFlight++;
        fetchChunk(idx).then((buf) => {
          if (!cacheEnabled) {
            parts[idx] = buf;
            inFlight--;
            done++;
            onPercent?.((done / totalChunks) * 100);
            run();
            return;
          }
          putChunkCache(sessionKey, idx, buf).then(() => {
            doneSet.add(idx);
            saveMeta(sessionKey, doneSet, totalChunks);
            inFlight--;
            done++;
            onPercent?.((done / totalChunks) * 100);
            run();
          }).catch(reject);
        }).catch(reject);
      }
      if (inFlight === 0 && cursor >= pending.length && done < totalChunks) {
        reject(new Error('download interrupted'));
      }
    };

    const fetchChunk = async (idx, retry = 0) => {
      const base = item.chunk_url_base || `${routeUrl('/api/file/chunk')}&file_id=${item.id}&code=${encodeURIComponent(code)}`;
      const url = `${base}&chunk=${idx}&chunk_size=${chunkSize}`;
      try {
        const res = await fetch(url);
        if (!res.ok) throw new Error(`chunk ${idx} http ${res.status}`);
        return await res.arrayBuffer();
      } catch (e) {
        if (retry < 3) return fetchChunk(idx, retry + 1);
        throw e;
      }
    };

    run();
  });

  if (!cacheEnabled) {
    return { blob: new Blob(parts, { type: 'application/octet-stream' }), sessionKey: '' };
  }

  for (let i = 0; i < totalChunks; i++) {
    const cached = await getChunkCache(sessionKey, i);
    if (!cached) throw new Error(`chunk ${i} missing in cache`);
    parts[i] = cached;
  }

  return { blob: new Blob(parts, { type: 'application/octet-stream' }), sessionKey };
}

async function downloadSingle(item) {
  const sessionKey = dlSessionKey(item);
  const oldMeta = loadMeta(sessionKey);
  if (oldMeta && Array.isArray(oldMeta.done) && oldMeta.done.length > 0) {
    const startPct = (oldMeta.done.length / Math.max(1, Number(oldMeta.total_chunks) || 1)) * 100;
    setLog(t('resuming', { name: item.original_name, percent: startPct.toFixed(1) }));
  } else {
    setLog(t('downloading_start', { name: item.original_name }));
  }
  const result = await fetchFileBlob(item, (percent) => {
    setLog(t('downloading', { name: item.original_name, percent: percent.toFixed(1) }));
  });
  saveBlob(result.blob, item.original_name);
  if (result.sessionKey) {
    await clearChunkCache(result.sessionKey);
    clearMeta(result.sessionKey);
  }
  setLog(t('download_done', { name: item.original_name }));
}

async function downloadAllZip() {
  if (!window.JSZip) {
    alert(t('zip_load_fail'));
    return;
  }
  if (!state.items.length) {
    alert(t('no_files'));
    return;
  }

  const zip = new window.JSZip();
  for (let i = 0; i < state.items.length; i++) {
    const item = state.items[i];
    setLog(t('zip_file_progress', { i: i + 1, n: state.items.length, name: item.original_name, percent: '0.0' }));
    const result = await fetchFileBlob(item, (percent) => {
      setLog(t('zip_file_progress', { i: i + 1, n: state.items.length, name: item.original_name, percent: percent.toFixed(1) }));
    });
    zip.file(item.original_name, result.blob);
    if (result.sessionKey) {
      await clearChunkCache(result.sessionKey);
      clearMeta(result.sessionKey);
    }
  }

  setLog(t('zipping_start'));
  const zipBlob = await zip.generateAsync({ type: 'blob' }, (meta) => {
    setLog(t('zipping', { percent: meta.percent.toFixed(1) }));
  });

  const zipName = `${safeZipName(state.title)}_${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.zip`;
  saveBlob(zipBlob, zipName);
  setLog(t('zip_done', { name: zipName }));
}

async function refreshShare() {
  const data = await fetchJson(`${routeUrl('/api/share/meta')}&code=${encodeURIComponent(code)}`);
  if (!data.ok) throw new Error(data.message || t('load_fail'));
  state.customButtons = Array.isArray(data.custom_buttons) ? data.custom_buttons : [];
  renderCustomButtons();

  if (data.locked) {
    state.title = siteName;
    state.shareTitle = data.share?.title || '';
    state.shareCreatedAt = data.share?.created_at || '';
    updateShareSubtitle();
    updateShareInfoCard();
    $('#lockedBox').classList.remove('hidden');
    $('#filesBox').classList.add('hidden');
    $('#shareTitle').textContent = t('brand');
    return;
  }

  state.items = Array.isArray(data.items) ? data.items : [];
  state.byId = new Map(state.items.map((x) => [Number(x.id), x]));
  state.title = data.share?.title || siteName;
  state.shareTitle = data.share?.title || '';
  state.shareCreatedAt = data.share?.created_at || '';
  updateShareSubtitle();
  updateShareInfoCard();

  $('#lockedBox').classList.add('hidden');
  $('#filesBox').classList.remove('hidden');
  $('#shareTitle').textContent = t('brand');

  const tbody = $('#shareFiles');
  tbody.innerHTML = '';
  state.items.forEach((f) => {
    const tr = document.createElement('tr');

    const iconTd = document.createElement('td');
    iconTd.className = 'file-icon-col';
    const iconWrap = document.createElement('span');
    iconWrap.className = 'file-icon';
    iconWrap.innerHTML = iconSvg(fileKind(f));
    iconTd.appendChild(iconWrap);

    const nameTd = document.createElement('td');
    nameTd.textContent = f.original_name;

    const sizeTd = document.createElement('td');
    sizeTd.textContent = fmt(f.size);

    const actionTd = document.createElement('td');
    actionTd.className = 'action-col';
    actionTd.innerHTML = `<button class="btn btn-success btn-sm" data-id="${f.id}">${t('download')}</button>`;

    tr.appendChild(iconTd);
    tr.appendChild(nameTd);
    tr.appendChild(sizeTd);
    tr.appendChild(actionTd);
    tbody.appendChild(tr);
  });

  tbody.querySelectorAll('button').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.id);
      const item = state.byId.get(id);
      if (!item) return;
      try {
        await queueDownload(() => downloadSingle(item));
      } catch (e) {
        setLog(t('download_fail', { name: item.original_name, msg: e.message }));
      }
    });
  });
}

async function unlock() {
  const pwd = ($('#unlockPwd')?.value || '').trim();
  const qpwd = new URLSearchParams(location.search).get('pwd');
  const password = pwd || qpwd || '';
  const data = await fetchJson(routeUrl('/api/share/unlock'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ code, password }),
  });
  if (!data.ok) throw new Error(data.message || t('unlock_fail'));
  await refreshShare();
}

$('#unlockBtn')?.addEventListener('click', () => unlock().catch((e) => {
  const err = $('#unlockErr');
  if (err) err.textContent = e.message;
}));

$('#downloadAllBtn')?.addEventListener('click', () => {
  queueDownload(() => downloadAllZip()).catch((e) => setLog(t('zip_download_fail', { msg: e.message })));
});

initLangSwitch();
refreshShare().catch(async () => {
  const qpwd = new URLSearchParams(location.search).get('pwd');
  if (qpwd) {
    try {
      await unlock();
      return;
    } catch {}
  }
  showShareNotice(t('invalid_share'));
});
