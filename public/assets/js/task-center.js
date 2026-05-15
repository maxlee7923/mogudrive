(() => {
  const shell = document.querySelector('#adminShell');
  if (!shell) return;

  const chunkSize = 30 * 1024 * 1024;
  const downloadConcurrency = 3;
  const panelStateKey = 'mogu_admin_task_center_open';

  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => [...context.querySelectorAll(selector)];

  const state = {
    tasks: [],
    nextId: 1,
    uploadLoopRunning: false,
    downloadLoopRunning: false,
    navBusy: false,
    panelOpen: localStorage.getItem(panelStateKey) === '1',
  };

  function routeUrl(path) {
    const entry = window.__ENTRY__ || '/index.php';
    return `${entry}?r=${String(path || '').replace(/^\/+/, '')}`;
  }

  function normalizeFolderPath(path) {
    const raw = String(path || '').replace(/\\/g, '/').trim();
    if (!raw) return '';
    return raw
      .split('/')
      .map((part) => part.trim())
      .filter((part) => part && part !== '.' && part !== '..')
      .join('/');
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

  function formatBytesSafe(size) {
    if (typeof window.formatBytes === 'function') {
      return window.formatBytes(size);
    }
    const value = Number(size || 0);
    if (value < 1024) return `${value.toFixed(0)} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(2)} KB`;
    if (value < 1024 * 1024 * 1024) return `${(value / 1024 / 1024).toFixed(2)} MB`;
    return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`;
  }

  function formatSpeed(bytesPerSec) {
    const speed = Number(bytesPerSec || 0);
    if (!Number.isFinite(speed) || speed <= 0) return '-';
    if (speed < 1024) return `${speed.toFixed(0)} B/s`;
    if (speed < 1024 * 1024) return `${(speed / 1024).toFixed(1)} KB/s`;
    return `${(speed / 1024 / 1024).toFixed(2)} MB/s`;
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

  function storageLabel(storageId) {
    const list = Array.isArray(window.__INITIAL_STORAGE__) ? window.__INITIAL_STORAGE__ : [];
    const hit = list.find((item) => Number(item?.id || 0) === Number(storageId || 0));
    if (!hit) return storageId ? `存储 #${storageId}` : '未指定存储';
    return `${hit.name} (${hit.driver})`;
  }

  function statusLabel(task) {
    switch (task.status) {
      case 'queued':
        return '等待中';
      case 'running':
        return task.type === 'upload' ? '上传中' : '下载中';
      case 'completed':
        return task.type === 'upload' ? '上传完成' : '下载完成';
      case 'failed':
        return '失败';
      case 'cancelled':
        return '已取消';
      default:
        return task.status || '-';
    }
  }

  function taskToneClass(task) {
    if (task.status === 'failed') return 'is-failed';
    if (task.status === 'completed') return 'is-completed';
    if (task.status === 'running') return 'is-running';
    return '';
  }

  function saveBlob(blob, name) {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 60_000);
  }

  function plainTask(task) {
    return {
      id: task.id,
      type: task.type,
      name: task.name,
      status: task.status,
      progress: task.progress,
      speed: task.speed,
      detail: task.detail,
      storageId: task.storageId,
      folderPath: task.folderPath,
      size: task.size,
      createdAt: task.createdAt,
      completedAt: task.completedAt || 0,
      errorMessage: task.errorMessage || '',
    };
  }

  function emitTaskEvent(name, task) {
    document.dispatchEvent(new CustomEvent(name, {
      detail: { task: plainTask(task) },
    }));
  }

  function notifyTaskUpdate() {
    document.dispatchEvent(new CustomEvent('mogu:tasks-updated', {
      detail: { tasks: state.tasks.map(plainTask) },
    }));
  }

  function setPanelOpen(open) {
    state.panelOpen = !!open;
    localStorage.setItem(panelStateKey, state.panelOpen ? '1' : '0');
    renderTaskCenter();
  }

  function taskCounts() {
    let running = 0;
    let queued = 0;
    let completed = 0;
    state.tasks.forEach((task) => {
      if (task.status === 'running') running += 1;
      else if (task.status === 'queued') queued += 1;
      else if (task.status === 'completed') completed += 1;
    });
    return { running, queued, completed };
  }

  function taskSortWeight(task) {
    if (task.status === 'running') return 0;
    if (task.status === 'queued') return 1;
    if (task.status === 'failed') return 2;
    return 3;
  }

  function taskMetaLine(task) {
    const size = formatBytesSafe(task.size || 0);
    if (task.type === 'upload') {
      return `${escapeHtml(storageLabel(task.storageId))} · ${escapeHtml(displayPath(task.folderPath))} · ${escapeHtml(size)}`;
    }
    return `${escapeHtml(displayPath(task.folderPath))} · ${escapeHtml(size)}`;
  }

  function renderTaskCenter() {
    const dock = $('#adminTaskCenter');
    if (!dock) return;

    const panel = dock.querySelector('.task-center-panel');
    const toggle = $('#taskCenterToggle', dock);
    const fabCount = $('#taskCenterFabCount', dock);
    const summary = $('#taskCenterSummary', dock);
    const list = $('#taskCenterList', dock);
    if (!(panel instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement) || !(fabCount instanceof HTMLElement) || !(summary instanceof HTMLElement) || !(list instanceof HTMLElement)) {
      return;
    }

    panel.classList.toggle('is-collapsed', !state.panelOpen);
    toggle.setAttribute('aria-expanded', state.panelOpen ? 'true' : 'false');

    const counts = taskCounts();
    fabCount.textContent = String(counts.running + counts.queued);
    summary.innerHTML = [
      `<span class="task-center-summary-chip">进行中 ${counts.running}</span>`,
      `<span class="task-center-summary-chip">等待中 ${counts.queued}</span>`,
      `<span class="task-center-summary-chip">已完成 ${counts.completed}</span>`,
    ].join('');

    if (!state.tasks.length) {
      list.innerHTML = '<div class="task-center-empty">当前还没有任务。</div>';
      notifyTaskUpdate();
      return;
    }

    const sorted = [...state.tasks].sort((left, right) => {
      const weightDiff = taskSortWeight(left) - taskSortWeight(right);
      if (weightDiff !== 0) return weightDiff;
      return (right.createdAt || 0) - (left.createdAt || 0);
    });

    list.innerHTML = sorted.map((task) => {
      const progress = Math.max(0, Math.min(100, Number(task.progress || 0)));
      const actions = [];
      if (task.status === 'failed') {
        actions.push(`<button class="btn btn-outline-secondary btn-sm" type="button" data-task-action="retry" data-task-id="${task.id}">重试</button>`);
      }
      if (task.status !== 'running' && task.status !== 'queued') {
        actions.push(`<button class="btn btn-outline-secondary btn-sm" type="button" data-task-action="remove" data-task-id="${task.id}">移除</button>`);
      }
      return `
        <article class="task-center-item ${taskToneClass(task)}">
          <div class="task-center-item-top">
            <div class="task-center-item-copy min-w-0">
              <div class="task-center-item-badges">
                <span class="task-center-type ${task.type === 'upload' ? 'is-upload' : 'is-download'}">${task.type === 'upload' ? '上传' : '下载'}</span>
                <span class="task-center-state">${escapeHtml(statusLabel(task))}</span>
              </div>
              <div class="task-center-item-title text-truncate">${escapeHtml(task.name || '未命名任务')}</div>
              <div class="task-center-item-meta">${taskMetaLine(task)}</div>
            </div>
            <div class="task-center-item-percent">${progress.toFixed(1)}%</div>
          </div>
          <div class="progress task-center-progress" role="progressbar" aria-valuenow="${progress.toFixed(1)}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width:${progress.toFixed(1)}%"></div>
          </div>
          <div class="task-center-item-bottom">
            <div class="task-center-item-detail">${escapeHtml(task.detail || '-')}</div>
            <div class="task-center-item-speed">实时网速 ${escapeHtml(task.speed || '-')}</div>
          </div>
          ${actions.length ? `<div class="task-center-item-actions">${actions.join('')}</div>` : ''}
        </article>
      `;
    }).join('');

    notifyTaskUpdate();
  }

  function makeTask(base) {
    let resolveDone = () => {};
    let rejectDone = () => {};
    const done = new Promise((resolve, reject) => {
      resolveDone = resolve;
      rejectDone = reject;
    });
    return {
      id: state.nextId++,
      status: 'queued',
      progress: 0,
      speed: '-',
      detail: base.type === 'upload' ? '等待上传' : '等待下载',
      createdAt: Date.now(),
      completedAt: 0,
      errorMessage: '',
      ...base,
      done,
      resolveDone,
      rejectDone,
    };
  }

  function setTaskPatch(task, patch) {
    Object.assign(task, patch);
    if (patch.status === 'completed' || patch.status === 'failed' || patch.status === 'cancelled') {
      task.completedAt = Date.now();
    }
    renderTaskCenter();
  }

  function finishTask(task, status, detail, error) {
    const message = detail || (error instanceof Error ? error.message : String(error || ''));
    setTaskPatch(task, {
      status,
      detail: message || task.detail,
      speed: '-',
      errorMessage: status === 'failed' ? message : '',
      progress: status === 'completed' ? 100 : task.progress,
    });
    if (status === 'completed') {
      task.resolveDone(task);
      emitTaskEvent('mogu:task-finished', task);
      return;
    }
    task.rejectDone(error || new Error(message || 'task failed'));
    emitTaskEvent('mogu:task-finished', task);
  }

  function enqueueUploads(items, options = {}) {
    const list = Array.from(items || []);
    if (!list.length) return [];

    const storageId = Number(options.storageId || 0);
    const created = list.map((entry) => makeTask({
      type: 'upload',
      name: entry?.file?.name || '未命名文件',
      fileName: entry?.file?.name || '未命名文件',
      file: entry?.file || null,
      size: Number(entry?.file?.size || 0),
      storageId,
      folderPath: normalizeFolderPath(entry?.folderPath || options.targetPath || ''),
      source: options.source || '',
    }));

    created.promise = Promise.allSettled(created.map((task) => task.done));
    state.tasks.push(...created);
    if (!state.panelOpen) setPanelOpen(true);
    renderTaskCenter();
    pumpUploads();
    created.forEach((task) => emitTaskEvent('mogu:task-queued', task));
    return created;
  }

  function enqueueDownloads(items, options = {}) {
    const list = Array.from(items || []).filter((item) => Number(item?.id || 0) > 0);
    if (!list.length) return [];

    const created = list.map((item) => makeTask({
      type: 'download',
      name: String(item?.original_name || item?.name || `文件 #${item?.id || 0}`),
      fileId: Number(item?.id || 0),
      fileMeta: { ...item },
      size: Number(item?.size || 0),
      folderPath: normalizeFolderPath(item?.folder_path || options.folderPath || ''),
      mimeType: String(item?.mime_type || 'application/octet-stream'),
      storageId: Number(item?.storage_id || options.storageId || 0),
      source: options.source || '',
    }));

    created.promise = Promise.allSettled(created.map((task) => task.done));
    state.tasks.push(...created);
    if (!state.panelOpen) setPanelOpen(true);
    renderTaskCenter();
    pumpDownloads();
    created.forEach((task) => emitTaskEvent('mogu:task-queued', task));
    return created;
  }

  async function runUploadTask(task) {
    if (typeof window.uploadFile !== 'function' || !task.file) {
      finishTask(task, 'failed', '上传能力未加载，无法开始任务。', new Error('upload unavailable'));
      return;
    }

    setTaskPatch(task, {
      status: 'running',
      progress: 0,
      speed: '-',
      detail: '准备上传',
      errorMessage: '',
    });

    const virtualRow = {
      __taskCenterUpdate(progress, speed, detail) {
        setTaskPatch(task, {
          status: 'running',
          progress: Number.isFinite(progress) ? Math.max(0, Math.min(100, progress)) : 0,
          speed: speed || '-',
          detail: detail || task.detail,
        });
      },
    };

    try {
      await window.uploadFile(task.file, task.storageId, task.folderPath || '', virtualRow);
      finishTask(task, 'completed', '上传完成');
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      finishTask(task, 'failed', `上传失败: ${message}`, error instanceof Error ? error : new Error(message));
    }
  }

  async function pumpUploads() {
    if (state.uploadLoopRunning) return;
    state.uploadLoopRunning = true;
    try {
      while (true) {
        const nextTask = state.tasks.find((task) => task.type === 'upload' && task.status === 'queued');
        if (!nextTask) break;
        await runUploadTask(nextTask);
      }
    } finally {
      state.uploadLoopRunning = false;
      renderTaskCenter();
    }
  }

  async function fetchDownloadBlob(task) {
    const file = task.fileMeta || {};
    const fileSize = Number(file.size || 0);
    if (fileSize <= 0) {
      setTaskPatch(task, {
        progress: 100,
        speed: '-',
        detail: '文件为空，已准备完成',
      });
      return new Blob([], { type: task.mimeType || 'application/octet-stream' });
    }

    const totalChunks = Math.max(1, Math.ceil(fileSize / chunkSize));
    const parts = new Array(totalChunks);
    let pointer = 0;
    let inFlight = 0;
    let completedChunks = 0;
    let committedBytes = 0;
    const activeChunkBytes = new Map();
    const speedTracker = createRealtimeSpeedTracker();
    speedTracker.reset(0);

    const totalReceivedBytes = () => {
      let activeBytes = 0;
      activeChunkBytes.forEach((value) => {
        activeBytes += Number(value || 0);
      });
      return Math.min(fileSize, committedBytes + activeBytes);
    };

    const fetchChunk = async (idx, retry = 0) => {
      const url = `${routeUrl('/api/file/chunk')}&file_id=${Number(task.fileId || 0)}&chunk=${idx}&chunk_size=${chunkSize}`;
      try {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        if (!res.body || typeof res.body.getReader !== 'function') {
          const buffer = await res.arrayBuffer();
          activeChunkBytes.set(idx, buffer.byteLength);
          const currentBytes = totalReceivedBytes();
          setTaskPatch(task, {
            status: 'running',
            progress: (currentBytes / Math.max(1, fileSize)) * 100,
            speed: speedTracker.sample(currentBytes),
            detail: `下载分片 ${completedChunks}/${totalChunks}`,
          });
          return buffer;
        }

        const reader = res.body.getReader();
        const chunks = [];
        let loaded = 0;
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          if (!(value instanceof Uint8Array)) continue;
          chunks.push(value);
          loaded += value.byteLength;
          activeChunkBytes.set(idx, loaded);
          const currentBytes = totalReceivedBytes();
          setTaskPatch(task, {
            status: 'running',
            progress: (currentBytes / Math.max(1, fileSize)) * 100,
            speed: speedTracker.sample(currentBytes),
            detail: `下载分片 ${completedChunks}/${totalChunks}`,
          });
        }

        const buffer = new Uint8Array(loaded);
        let offset = 0;
        chunks.forEach((chunk) => {
          buffer.set(chunk, offset);
          offset += chunk.byteLength;
        });
        return buffer.buffer;
      } catch (error) {
        if (retry < 3) {
          return fetchChunk(idx, retry + 1);
        }
        throw error;
      }
    };

    await new Promise((resolve, reject) => {
      const run = () => {
        if (completedChunks >= totalChunks) {
          resolve();
          return;
        }

        while (inFlight < downloadConcurrency && pointer < totalChunks) {
          const chunkIndex = pointer++;
          inFlight += 1;
          fetchChunk(chunkIndex)
            .then((buffer) => {
              parts[chunkIndex] = buffer;
              inFlight -= 1;
              completedChunks += 1;
              activeChunkBytes.delete(chunkIndex);
              committedBytes += buffer.byteLength;
              const currentBytes = totalReceivedBytes();
              setTaskPatch(task, {
                status: 'running',
                progress: (currentBytes / Math.max(1, fileSize)) * 100,
                speed: speedTracker.sample(currentBytes),
                detail: `下载分片 ${completedChunks}/${totalChunks}`,
              });
              run();
            })
            .catch((error) => {
              reject(error);
            });
        }

        if (inFlight === 0 && pointer >= totalChunks && completedChunks < totalChunks) {
          reject(new Error('download interrupted'));
        }
      };

      run();
    });

    return new Blob(parts, { type: task.mimeType || 'application/octet-stream' });
  }

  async function runDownloadTask(task) {
    setTaskPatch(task, {
      status: 'running',
      progress: 0,
      speed: '-',
      detail: '准备下载',
      errorMessage: '',
    });

    try {
      const blob = await fetchDownloadBlob(task);
      saveBlob(blob, task.name || `file-${task.fileId || task.id}`);
      finishTask(task, 'completed', '下载完成');
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      finishTask(task, 'failed', `下载失败: ${message}`, error instanceof Error ? error : new Error(message));
    }
  }

  async function pumpDownloads() {
    if (state.downloadLoopRunning) return;
    state.downloadLoopRunning = true;
    try {
      while (true) {
        const nextTask = state.tasks.find((task) => task.type === 'download' && task.status === 'queued');
        if (!nextTask) break;
        await runDownloadTask(nextTask);
      }
    } finally {
      state.downloadLoopRunning = false;
      renderTaskCenter();
    }
  }

  function retryTask(taskId) {
    const task = state.tasks.find((item) => item.id === taskId);
    if (!task || task.status !== 'failed') return;
    setTaskPatch(task, {
      status: 'queued',
      progress: 0,
      speed: '-',
      detail: task.type === 'upload' ? '等待重新上传' : '等待重新下载',
      errorMessage: '',
      completedAt: 0,
    });
    if (task.type === 'upload') {
      pumpUploads();
      return;
    }
    pumpDownloads();
  }

  function removeTask(taskId) {
    const index = state.tasks.findIndex((item) => item.id === taskId);
    if (index < 0) return;
    const task = state.tasks[index];
    if (task.status === 'running' || task.status === 'queued') return;
    state.tasks.splice(index, 1);
    renderTaskCenter();
  }

  function clearCompletedTasks() {
    state.tasks = state.tasks.filter((task) => !['completed', 'failed', 'cancelled'].includes(task.status));
    renderTaskCenter();
  }

  function parsePayload(doc) {
    const raw = doc.querySelector('#adminPagePayload')?.textContent || '{}';
    try {
      return JSON.parse(raw);
    } catch (_) {
      return {};
    }
  }

  function markActiveSidebar(url) {
    const targetUrl = new URL(url, location.href);
    $$('.side-link').forEach((link) => {
      const linkUrl = new URL(link.href, location.href);
      const active = linkUrl.pathname === targetUrl.pathname && linkUrl.search === targetUrl.search;
      link.classList.toggle('active', active);
    });
  }

  async function loadAdminSection(url, { pushState = true } = {}) {
    if (state.navBusy) return;

    const content = $('#adminContent');
    if (!(content instanceof HTMLElement)) {
      location.href = url;
      return;
    }

    const targetUrl = new URL(url, location.href);
    state.navBusy = true;
    content.classList.add('admin-content-loading');

    try {
      if (typeof window.destroyFileManagerPage === 'function') {
        window.destroyFileManagerPage();
      }

      const res = await fetch(targetUrl.href, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const nextContent = doc.querySelector('#adminContent');
      if (!(nextContent instanceof HTMLElement)) {
        location.href = targetUrl.href;
        return;
      }

      const payload = parsePayload(doc);
      window.__ADMIN_PAGE__ = String(payload.page || nextContent.dataset.adminPage || 'upload');
      window.__INITIAL_STORAGE__ = Array.isArray(payload.storage) ? payload.storage : [];
      window.__FILES_DATA__ = Array.isArray(payload.files) ? payload.files : [];
      window.__ORIGIN__ = String(payload.origin || location.origin);

      content.innerHTML = nextContent.innerHTML;
      content.dataset.adminPage = window.__ADMIN_PAGE__;

      const pageTitle = $('#pageTitle');
      if (pageTitle) {
        pageTitle.textContent = String(payload.label || doc.querySelector('#pageTitle')?.textContent || pageTitle.textContent || '控制台');
      }

      const docTitle = doc.querySelector('title')?.textContent;
      if (docTitle) {
        document.title = docTitle;
      }

      markActiveSidebar(targetUrl.href);

      if (typeof window.__ADMIN_INIT_PAGE__ === 'function') {
        window.__ADMIN_INIT_PAGE__(window.__ADMIN_PAGE__);
      }
      if (window.__ADMIN_PAGE__ === 'files' && typeof window.initFileManagerPage === 'function') {
        window.initFileManagerPage();
      }

      if (pushState) {
        history.pushState({ adminUrl: targetUrl.href }, '', targetUrl.href);
      }
      window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    } catch (error) {
      console.error(error);
      location.href = targetUrl.href;
      return;
    } finally {
      state.navBusy = false;
      content.classList.remove('admin-content-loading');
    }
  }

  function bindSidebarNavigation() {
    document.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const link = target.closest('.side-link');
      if (!(link instanceof HTMLAnchorElement)) return;
      if (event.defaultPrevented) return;
      if (event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const url = new URL(link.href, location.href);
      if (url.origin !== location.origin) return;
      if (url.href === location.href) {
        event.preventDefault();
        return;
      }
      event.preventDefault();
      loadAdminSection(url.href, { pushState: true });
    });

    window.addEventListener('popstate', () => {
      const url = location.href;
      if (!/\/admin\//.test(url)) return;
      loadAdminSection(url, { pushState: false });
    });
  }

  function bindTaskCenterEvents() {
    const dock = $('#adminTaskCenter');
    if (!dock) return;

    $('#taskCenterToggle', dock)?.addEventListener('click', () => setPanelOpen(!state.panelOpen));
    $('#taskCenterCollapse', dock)?.addEventListener('click', () => setPanelOpen(false));
    $('#taskCenterClearCompleted', dock)?.addEventListener('click', clearCompletedTasks);

    dock.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element)) return;
      const button = target.closest('[data-task-action]');
      if (!(button instanceof HTMLButtonElement)) return;
      const taskId = Number(button.dataset.taskId || 0);
      const action = button.dataset.taskAction || '';
      if (!taskId || !action) return;
      if (action === 'retry') {
        retryTask(taskId);
        return;
      }
      if (action === 'remove') {
        removeTask(taskId);
      }
    });
  }

  window.__TASK_CENTER__ = {
    enqueueUploads,
    enqueueDownloads,
    open() {
      setPanelOpen(true);
    },
    getTasks() {
      return state.tasks.map(plainTask);
    },
    clearCompleted() {
      clearCompletedTasks();
    },
  };

  bindSidebarNavigation();
  bindTaskCenterEvents();
  renderTaskCenter();
})();
