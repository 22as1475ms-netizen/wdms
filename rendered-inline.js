
    (function () {
      try {
        var storedMode = localStorage.getItem('wdms-color-mode');
        var storedChatTheme = localStorage.getItem('wdms-chat-theme');
        var storedPerformanceMode = localStorage.getItem('wdms-performance-mode');
        var validPerformanceModes = ['auto', 'lite', 'full'];
        var performanceMode = validPerformanceModes.indexOf(storedPerformanceMode) >= 0 ? storedPerformanceMode : 'auto';
        var prefersReducedMotion = false;
        var saveData = false;
        var deviceMemory = 8;
        var hardwareConcurrency = 8;
        try {
          prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
          saveData = !!(navigator.connection && navigator.connection.saveData);
          deviceMemory = Number(navigator.deviceMemory || 8);
          hardwareConcurrency = Number(navigator.hardwareConcurrency || 8);
        } catch (_perfErr) {}
        var computedPerformanceTier = performanceMode === 'lite'
          ? 'lite'
          : (performanceMode === 'full'
            ? 'full'
            : ((prefersReducedMotion || saveData || deviceMemory <= 4 || hardwareConcurrency <= 4) ? 'lite' : 'full'));
        document.documentElement.setAttribute('data-color-mode', storedMode === 'dark' ? 'dark' : 'light');
        document.documentElement.setAttribute('data-chat-theme', storedChatTheme || 'ocean');
        document.documentElement.setAttribute('data-performance-mode', performanceMode);
        document.documentElement.setAttribute('data-performance-tier', computedPerformanceTier);
        window.wdmsPerformance = {
          mode: performanceMode,
          tier: computedPerformanceTier,
          prefersReducedMotion: prefersReducedMotion,
          saveData: saveData,
          deviceMemory: deviceMemory,
          hardwareConcurrency: hardwareConcurrency
        };
      } catch (_err) {
        document.documentElement.setAttribute('data-color-mode', 'light');
        document.documentElement.setAttribute('data-chat-theme', 'ocean');
        document.documentElement.setAttribute('data-performance-mode', 'auto');
        document.documentElement.setAttribute('data-performance-tier', 'full');
      }
    })();
  


  (function () {
    const librarySelectTriggers = Array.from(document.querySelectorAll('#library-select-all-records'));
    librarySelectTriggers.forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        const items = Array.from(document.querySelectorAll('.library-select-item'));
        const shouldCheck = items.some(function (input) { return !input.checked; });
        items.forEach(function (input) {
          input.checked = shouldCheck;
        });
      });
    });

    const libraryForm = document.getElementById('library-bulk-form');
    const libraryActionInput = libraryForm ? libraryForm.querySelector('input[name="action"]') : null;
    document.querySelectorAll('[data-library-action]').forEach(function (button) {
      button.addEventListener('click', async function () {
        if (!libraryForm || !libraryActionInput) return;
        const action = button.getAttribute('data-library-action') || '';
        const selected = document.querySelectorAll('.library-select-item:checked');
        if (!selected.length) return;

        const message = action === 'delete'
          ? 'Move the selected items to trash?'
          : 'This older storage move is no longer used.';

        let ok = true;
        if (typeof window.wdmsConfirmModal === 'function') {
          const result = await window.wdmsConfirmModal({
            title: 'Confirm action',
            message: message,
            confirmText: 'Continue'
          });
          ok = !!result.ok;
        } else {
          ok = window.confirm(message);
        }
        if (!ok) return;

        libraryActionInput.value = action;
        libraryForm.submit();
      });
    });

    const selectAllTrigger = document.getElementById('trash-select-all');
    if (selectAllTrigger) {
      selectAllTrigger.addEventListener('click', function () {
        const items = Array.from(document.querySelectorAll('.trash-select-item'));
        const shouldCheck = items.some(function (input) { return !input.checked; });
        items.forEach(function (input) {
          input.checked = shouldCheck;
        });
      });
    }

    document.querySelectorAll('.js-folder-rename').forEach(function (button) {
      button.addEventListener('click', async function () {
        const folderId = button.getAttribute('data-folder-id') || '';
        const currentName = button.getAttribute('data-folder-name') || '';
        const nextName = window.prompt('Rename folder', currentName);
        if (!nextName || nextName.trim() === '' || nextName.trim() === currentName) {
          return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/wdms/folders/rename?user_id=1';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_csrf';
        csrf.value = '5eb19dd7e179123b3d01f45f56ec7183';
        form.appendChild(csrf);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = folderId;
        form.appendChild(idInput);

        const nameInput = document.createElement('input');
        nameInput.type = 'hidden';
        nameInput.name = 'new_name';
        nameInput.value = nextName.trim();
        form.appendChild(nameInput);

        document.body.appendChild(form);
        form.submit();
      });
    });
  })();



  (function () {
    const form = document.querySelector('form[action="/wdms/documents/upload"]');
    if (!form) return;

    const dropzone = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('upload-file-input');
    const folderInput = document.getElementById('upload-folder-input');
    const fileTrigger = document.getElementById('workspace-upload-file-trigger');
    const folderTrigger = document.getElementById('workspace-upload-folder-trigger');
    const selectionLabel = document.getElementById('workspace-upload-selection');
    const workspaceShell = document.getElementById('workspace-shell');
    const dropSurface = document.getElementById('workspace-drop-surface');
    const uploadPanel = document.getElementById('workspace-upload-panel');
    const uploadTitle = document.getElementById('workspace-upload-title');
    const uploadSummary = document.getElementById('workspace-upload-summary');
    const uploadPercent = document.getElementById('workspace-upload-percent');
    const uploadProgressBar = document.getElementById('workspace-upload-progress-bar');
    const uploadList = document.getElementById('workspace-upload-list');
    const uploadClose = document.getElementById('workspace-upload-close');
    const uploadModalElement = document.getElementById('workspaceUploadModal');
    const conflictInput = form.querySelector('input[name="on_conflict"]');
    const uploadButton = form.querySelector('button[type="submit"]');
    const existing = ["upload-smoke.txt","upload-batch-1.txt","upload-batch-2.txt"];
    const existingSet = new Set(existing);
    const uploadQueueStorageKey = 'wdms_upload_queue_state';
    let dragDepth = 0;
    let activeRequest = null;
    let uploadQueueHidden = false;
    let pendingEntries = [];

    function formatBytes(bytes) {
      const value = Math.max(0, Number(bytes) || 0);
      if (value < 1024) return value.toFixed(0) + ' B';
      if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
      if (value < 1024 * 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
      return (value / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function updateSelectionLabel() {
      if (!selectionLabel) return;
      if (pendingEntries.length) {
        if (pendingEntries.length === 1) {
          selectionLabel.textContent = pendingEntries[0].file.name;
          return;
        }
        const folderLike = pendingEntries.some(function (entry) { return String(entry.relativePath || '').trim() !== ''; });
        selectionLabel.textContent = pendingEntries.length + (folderLike ? ' routed file(s) from dropped folder' : ' dropped file(s) selected');
        return;
      }
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      if (folderFiles.length) {
        const rootPath = String(folderFiles[0].webkitRelativePath || '').split('/')[0] || 'Selected folder';
        selectionLabel.textContent = rootPath + ' (' + folderFiles.length + ' files)';
        return;
      }
      if (files.length === 1) {
        selectionLabel.textContent = files[0].name;
        return;
      }
      if (files.length > 1) {
        selectionLabel.textContent = files.length + ' files selected';
        return;
      }
      selectionLabel.textContent = 'No file selected yet.';
    }

    function setUploadBusy(isBusy) {
      form.dataset.uploadBusy = isBusy ? '1' : '0';
      if (uploadButton) {
        uploadButton.disabled = isBusy;
        uploadButton.innerHTML = isBusy
          ? '<i class="bi bi-arrow-repeat me-1"></i>Uploading...'
          : '<i class="bi bi-upload me-1"></i>Upload';
      }
    }

    function showUploadPanel(forceOpen) {
      if (!uploadPanel) return;
      if (forceOpen) {
        uploadQueueHidden = false;
      }
      if (!uploadQueueHidden) {
        uploadPanel.classList.remove('d-none');
      }
    }

    function hideUploadPanel() {
      if (!uploadPanel) return;
      uploadQueueHidden = true;
      uploadPanel.classList.add('d-none');
      try {
        window.sessionStorage.removeItem(uploadQueueStorageKey);
      } catch (_err) {}
    }

    function clearUploadPanel() {
      activeRequest = null;
      uploadQueueHidden = false;
      setUploadBusy(false);
      if (uploadPanel) {
        uploadPanel.classList.add('d-none');
      }
      if (uploadTitle) uploadTitle.textContent = 'Preparing upload';
      if (uploadSummary) uploadSummary.textContent = 'Waiting for files';
      if (uploadPercent) uploadPercent.textContent = '0%';
      if (uploadProgressBar) uploadProgressBar.style.width = '0%';
      if (uploadList) uploadList.innerHTML = '';
      try {
        window.sessionStorage.removeItem(uploadQueueStorageKey);
      } catch (_err) {}
    }

    function persistQueue(state) {
      try {
        window.sessionStorage.setItem(uploadQueueStorageKey, JSON.stringify(state));
      } catch (_err) {}
    }

    function restoreQueue() {
      if (!uploadPanel) return;
      try {
        const raw = window.sessionStorage.getItem(uploadQueueStorageKey);
        if (!raw) return;
        const state = JSON.parse(raw);
        if (!state || !Array.isArray(state.items)) return;
        renderQueue(state);
        showUploadPanel(true);
      } catch (_err) {}
    }

    function createQueueState(entries) {
      const items = entries.map(function (entry, index) {
        const file = entry.file || {};
        const size = Math.max(0, Number(file.size) || 0);
        const relativePath = String(entry.relativePath || '').trim();
        const location = relativePath ? relativePath.replace(/\//g, ' > ') : 'Top level';
        return {
          id: 'upload-item-' + index + '-' + Date.now(),
          name: String(file.name || 'File'),
          location: location,
          size: size,
          loaded: 0,
          progress: 0,
          status: 'Queued'
        };
      });

      return {
        items: items,
        totalBytes: items.reduce(function (sum, item) { return sum + item.size; }, 0),
        totalLoaded: 0,
        overallProgress: 0,
        statusText: 'Preparing upload...',
        title: entries.length === 1 ? 'Uploading 1 item' : 'Uploading ' + entries.length + ' items'
      };
    }

    function renderQueue(state) {
      if (!uploadPanel || !uploadList || !uploadTitle || !uploadSummary || !uploadPercent || !uploadProgressBar) {
        return;
      }
      showUploadPanel();
      uploadTitle.textContent = state.title;
      uploadSummary.textContent = state.statusText;
      uploadPercent.textContent = Math.round(state.overallProgress) + '%';
      uploadProgressBar.style.width = Math.max(0, Math.min(100, state.overallProgress)) + '%';
      uploadList.innerHTML = state.items.map(function (item) {
        const progress = Math.max(0, Math.min(100, item.progress));
        const classes = ['drive-upload-item'];
        if (item.status === 'Done') classes.push('is-complete');
        if (item.status === 'Failed') classes.push('is-error');
        return ''
          + '<article class="' + classes.join(' ') + '">'
          + '  <div class="drive-upload-item__top">'
          + '    <strong class="drive-upload-item__name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</strong>'
          + '    <span class="drive-upload-item__status">' + escapeHtml(item.status) + '</span>'
          + '  </div>'
          + '  <div class="drive-upload-item__track"><span style="width: ' + progress + '%"></span></div>'
          + '  <div class="drive-upload-item__meta">'
          + '    <span title="' + escapeHtml(item.location) + '">' + escapeHtml(item.location) + '</span>'
          + '    <span>' + escapeHtml(formatBytes(item.loaded)) + ' / ' + escapeHtml(formatBytes(item.size)) + '</span>'
          + '  </div>'
          + '</article>';
      }).join('');
      persistQueue(state);
    }

    function updateQueueProgress(state, loadedBytes) {
      const totalBytes = Math.max(0, state.totalBytes);
      const clampedLoaded = Math.max(0, Math.min(totalBytes, Number(loadedBytes) || 0));
      state.totalLoaded = clampedLoaded;
      state.overallProgress = totalBytes > 0 ? (clampedLoaded / totalBytes) * 100 : 100;
      state.statusText = totalBytes > 0
        ? formatBytes(clampedLoaded) + ' of ' + formatBytes(totalBytes) + ' uploaded'
        : 'Uploading files...';

      let remaining = clampedLoaded;
      state.items.forEach(function (item) {
        const itemLoaded = Math.max(0, Math.min(item.size, remaining));
        remaining = Math.max(0, remaining - itemLoaded);
        item.loaded = itemLoaded;
        item.progress = item.size > 0 ? (itemLoaded / item.size) * 100 : (state.overallProgress > 0 ? 100 : 0);
        item.status = item.progress >= 100 ? 'Processing' : 'Uploading';
      });
    }

    function finalizeQueue(state, status) {
      if (status === 'done') {
        state.overallProgress = 100;
        state.statusText = 'Upload complete.';
        state.items.forEach(function (item) {
          item.loaded = item.size;
          item.progress = 100;
          item.status = 'Done';
        });
      } else {
        state.statusText = 'Upload failed. Please try again.';
        state.items.forEach(function (item) {
          if (item.progress <= 0) {
            item.status = 'Failed';
          }
        });
      }
      renderQueue(state);
    }

    function clearRelativePathInputs() {
      form.querySelectorAll('input[data-relative-path="1"]').forEach(function (input) {
        input.remove();
      });
    }

    function clearSelectedInputs() {
      if (fileInput) fileInput.value = '';
      if (folderInput) folderInput.value = '';
    }

    function setPendingEntries(entries) {
      pendingEntries = Array.isArray(entries) ? entries.slice() : [];
      clearSelectedInputs();
      clearRelativePathInputs();
      pendingEntries.forEach(function (entry) {
        appendRelativePath('file_relative_paths[]', entry.relativePath || '');
      });
      updateSelectionLabel();
    }

    function clearPendingEntries() {
      pendingEntries = [];
      clearRelativePathInputs();
      updateSelectionLabel();
    }

    function openUploadModal() {
      if (!uploadModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
      }
      bootstrap.Modal.getOrCreateInstance(uploadModalElement).show();
    }

    function appendRelativePath(fieldName, relativePath) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = fieldName;
      input.value = relativePath || '';
      input.setAttribute('data-relative-path', '1');
      form.appendChild(input);
    }

    function relativePathFromFile(file) {
      const fullPath = String(file && file.webkitRelativePath ? file.webkitRelativePath : '').replace(/\\/g, '/');
      if (!fullPath) return '';
      const parts = fullPath.split('/').filter(Boolean);
      if (parts.length <= 1) return '';
      parts.pop();
      return parts.join('/');
    }

    function syncRelativePathInputs() {
      clearRelativePathInputs();
      pendingEntries = [];
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      files.forEach(function () {
        appendRelativePath('file_relative_paths[]', '');
      });
      folderFiles.forEach(function (file) {
        appendRelativePath('file_relative_paths[]', relativePathFromFile(file));
      });
    }

    async function confirmOverride() {
      if (typeof window.wdmsConfirmModal === 'function') {
        const result = await window.wdmsConfirmModal({
          title: 'Name conflict',
          message: 'One or more files with the same name already exist here. Upload and override them as new versions?',
          confirmText: 'Upload as version',
          cancelText: 'Choose another file'
        });
        return !!result.ok;
      }
      return window.confirm('One or more files with the same name already exist here. Upload and override them as new versions?');
    }

    async function resolveConflict(files) {
      conflictInput.value = '';
      if (!files.length) return true;
      const hasDuplicate = files.some(function (entry) {
        const name = String(entry && entry.name ? entry.name : '').trim().toLowerCase();
        return !!name && existingSet.has(name);
      });
      if (!hasDuplicate) return true;
      const ok = await confirmOverride();
      if (ok) {
        conflictInput.value = 'override';
        return true;
      }
      return false;
    }

    async function handleSelection() {
      const combined = selectedEntriesFromInputs().map(function (entry) { return entry.file; });
      const ok = await resolveConflict(combined);
      if (!ok) {
        clearSelectedInputs();
        clearPendingEntries();
        conflictInput.value = '';
        updateSelectionLabel();
        return false;
      }
      if (!pendingEntries.length) {
        syncRelativePathInputs();
      }
      updateSelectionLabel();
      return ok;
    }

    function selectedEntriesFromInputs() {
      if (pendingEntries.length) {
        return pendingEntries.slice();
      }
      const entries = [];
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      files.forEach(function (file) {
        entries.push({ file: file, relativePath: '' });
      });
      folderFiles.forEach(function (file) {
        entries.push({ file: file, relativePath: relativePathFromFile(file) });
      });
      return entries;
    }

    function droppedFileEntries(fileList) {
      return Array.from(fileList || []).map(function (file) {
        return {
          file: file,
          relativePath: relativePathFromFile(file)
        };
      });
    }

    function collectFileEntries(items) {
      const list = Array.from(items || []);
      const entryReaders = list.map(function (item) {
        const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
        if (entry) {
          return readEntry(entry, '');
        }
        if (item.getAsFileSystemHandle) {
          return item.getAsFileSystemHandle().then(function (handle) {
            return readFileSystemHandle(handle, '');
          }).catch(function () {
            const file = item.getAsFile ? item.getAsFile() : null;
            return file ? [{ file: file, relativePath: '' }] : [];
          });
        }
        const file = item.getAsFile ? item.getAsFile() : null;
        return Promise.resolve(file ? [{ file: file, relativePath: '' }] : []);
      });
      return Promise.all(entryReaders).then(function (groups) {
        return groups.flat();
      });
    }

    function readFileSystemHandle(handle, basePath) {
      if (!handle) {
        return Promise.resolve([]);
      }
      if (handle.kind === 'file') {
        return handle.getFile().then(function (file) {
          return [{ file: file, relativePath: basePath }];
        }).catch(function () {
          return [];
        });
      }
      if (handle.kind !== 'directory') {
        return Promise.resolve([]);
      }
      const nextBase = [basePath, handle.name].filter(Boolean).join('/');
      return (async function () {
        const groups = [];
        for await (const childHandle of handle.values()) {
          groups.push(await readFileSystemHandle(childHandle, nextBase));
        }
        return groups.flat();
      })().catch(function () {
        return [];
      });
    }

    function readEntry(entry, basePath) {
      if (entry.isFile) {
        return new Promise(function (resolve) {
          entry.file(function (file) {
            resolve([{ file: file, relativePath: basePath }]);
          }, function () {
            resolve([]);
          });
        });
      }
      if (!entry.isDirectory) {
        return Promise.resolve([]);
      }
      return readDirectory(entry).then(function (children) {
        const nextBase = [basePath, entry.name].filter(Boolean).join('/');
        return Promise.all(children.map(function (child) {
          return readEntry(child, nextBase);
        })).then(function (groups) {
          return groups.flat();
        });
      });
    }

    function readDirectory(directoryEntry) {
      return new Promise(function (resolve) {
        const reader = directoryEntry.createReader();
        const entries = [];
        const readBatch = function () {
          reader.readEntries(function (batch) {
            if (!batch.length) {
              resolve(entries);
              return;
            }
            entries.push.apply(entries, batch);
            readBatch();
          }, function () {
            resolve(entries);
          });
        };
        readBatch();
      });
    }

    function queueDroppedFileList(fileList) {
      const entries = droppedFileEntries(fileList);
      if (!entries.length) return;
      setPendingEntries(entries);
      openUploadModal();
    }

    function notifyFolderDropUnsupported() {
      window.alert('This browser could not read the dropped folder contents. Use "Choose folder" instead.');
    }

    function buildUploadFormData(entries) {
      const formData = new FormData();
      const csrf = form.querySelector('input[name="_csrf"]');
      const folderIdInput = form.querySelector('input[name="folder_id"]');
      const storageAreaInput = form.querySelector('input[name="storage_area"]');
      if (csrf) formData.append('_csrf', csrf.value);
      if (folderIdInput) formData.append('folder_id', folderIdInput.value);
      if (storageAreaInput) formData.append('storage_area', storageAreaInput.value);
      formData.append('on_conflict', conflictInput.value || '');
      form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
        const name = field.getAttribute('name');
        if (!name || ['file[]', 'folder_upload[]', 'file_relative_paths[]', '_csrf', 'folder_id', 'storage_area', 'on_conflict'].indexOf(name) !== -1) {
          return;
        }
        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
          return;
        }
        formData.append(name, field.value || '');
      });

      entries.forEach(function (entry) {
        formData.append('file[]', entry.file, entry.file.name);
        formData.append('file_relative_paths[]', entry.relativePath || '');
      });

      return formData;
    }

    function sendUpload(entries) {
      if (!entries.length || activeRequest) return;
      const queueState = createQueueState(entries);
      updateQueueProgress(queueState, 0);
      renderQueue(queueState);
      showUploadPanel(true);
      setUploadBusy(true);

      const xhr = new XMLHttpRequest();
      activeRequest = xhr;
      xhr.open('POST', form.action, true);
      xhr.withCredentials = true;

      xhr.upload.addEventListener('progress', function (event) {
        if (!event.lengthComputable) return;
        updateQueueProgress(queueState, event.loaded);
        renderQueue(queueState);
      });

      xhr.addEventListener('load', function () {
        activeRequest = null;
        setUploadBusy(false);
        if (xhr.status >= 200 && xhr.status < 400) {
          finalizeQueue(queueState, 'done');
          window.setTimeout(function () {
            window.location.href = xhr.responseURL || window.location.href;
          }, 400);
          return;
        }
        finalizeQueue(queueState, 'error');
      });

      xhr.addEventListener('error', function () {
        activeRequest = null;
        setUploadBusy(false);
        finalizeQueue(queueState, 'error');
      });

      xhr.send(buildUploadFormData(entries));
    }

    async function uploadDroppedEntries(entries) {
      if (!entries.length) return;
      const files = entries.map(function (entry) { return entry.file; });
      const ok = await resolveConflict(files);
      if (!ok) return;
      sendUpload(entries);
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        pendingEntries = [];
        if (folderInput) folderInput.value = '';
        conflictInput.value = '';
        syncRelativePathInputs();
        updateSelectionLabel();
      });
    }
    if (folderInput) {
      folderInput.addEventListener('change', function () {
        pendingEntries = [];
        if (fileInput) fileInput.value = '';
        conflictInput.value = '';
        syncRelativePathInputs();
        updateSelectionLabel();
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.dataset.uploadBusy === '1') return;
      handleSelection().then(function (ok) {
        if (!ok) return;
        const entries = selectedEntriesFromInputs();
        if (!entries.length) return;
        if (pendingEntries.length) {
          sendUpload(entries);
          return;
        }
        setUploadBusy(true);
        form.submit();
      });
    });
    if (uploadClose) {
      uploadClose.addEventListener('click', function () {
        if (activeRequest) return;
        hideUploadPanel();
      });
    }
    restoreQueue();
    updateSelectionLabel();
    if (fileTrigger && fileInput) {
      fileTrigger.addEventListener('click', function () {
        clearPendingEntries();
        fileInput.value = '';
        if (folderInput) folderInput.value = '';
        updateSelectionLabel();
        fileInput.click();
      });
    }
    if (folderTrigger && folderInput) {
      folderTrigger.addEventListener('click', async function () {
        if (typeof window.wdmsConfirmModal === 'function') {
          const result = await window.wdmsConfirmModal({
            title: 'Upload a folder',
            message: 'Your browser may ask for confirmation before sending all files in the selected folder. Continue?',
            confirmText: 'Choose folder',
            cancelText: 'Cancel'
          });
          if (!result.ok) return;
        }
        clearPendingEntries();
        if (fileInput) fileInput.value = '';
        folderInput.value = '';
        updateSelectionLabel();
        folderInput.click();
      });
    }
    if (dropzone && fileInput) {
      dropzone.addEventListener('click', function () { fileInput.click(); });
      dropzone.addEventListener('dragenter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        dropzone.style.outline = '2px dashed #7b9bcf';
      });
      dropzone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        dropzone.style.outline = '2px dashed #7b9bcf';
      });
      dropzone.addEventListener('dragleave', function (e) {
        e.stopPropagation();
        dropzone.style.outline = 'none';
      });
      dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.outline = 'none';
        if (!e.dataTransfer) return;

        if (e.dataTransfer.items && e.dataTransfer.items.length) {
          collectFileEntries(e.dataTransfer.items).then(function (entries) {
            if (entries.length) {
              uploadDroppedEntries(entries);
              return;
            }

            if (!e.dataTransfer.files || !e.dataTransfer.files.length) {
              notifyFolderDropUnsupported();
              return;
            }
            queueDroppedFileList(e.dataTransfer.files);
          });
          return;
        }

        if (!e.dataTransfer.files || !e.dataTransfer.files.length) {
          notifyFolderDropUnsupported();
          return;
        }
        queueDroppedFileList(e.dataTransfer.files);
      });
    }

    if (workspaceShell && dropSurface) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        workspaceShell.addEventListener(eventName, function (e) {
          if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
          e.preventDefault();
          e.dataTransfer.dropEffect = 'copy';
          dragDepth += 1;
          dropSurface.classList.remove('d-none');
        });
      });
      workspaceShell.addEventListener('dragleave', function (e) {
        if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) {
          dropSurface.classList.add('d-none');
        }
      });
      workspaceShell.addEventListener('drop', function (e) {
        if (!e.dataTransfer) return;
        e.preventDefault();
        dragDepth = 0;
        dropSurface.classList.add('d-none');
        if (e.dataTransfer.items && e.dataTransfer.items.length) {
          collectFileEntries(e.dataTransfer.items).then(function (entries) {
            if (entries.length) {
              uploadDroppedEntries(entries);
              return;
            }
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
              queueDroppedFileList(e.dataTransfer.files);
              return;
            }
            notifyFolderDropUnsupported();
          });
          return;
        }
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
          queueDroppedFileList(e.dataTransfer.files);
          return;
        }
        notifyFolderDropUnsupported();
      });
    }
  })();



  (function () {
    const uploadModalElement = document.getElementById('workspaceUploadModal');
    const uploadModalLabel = document.getElementById('workspaceUploadModalLabel');
    document.querySelectorAll('[data-workspace-new-action]').forEach(function (button) {
      button.addEventListener('click', function () {
        const action = button.getAttribute('data-workspace-new-action');
        if (!uploadModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
          return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(uploadModalElement);
        uploadModalElement.setAttribute('data-upload-intent', action || '');
        if (uploadModalLabel) {
          uploadModalLabel.textContent = action === 'folder-upload' ? 'Upload folder to Routed Files' : 'Upload to Routed Files';
        }
        modal.show();
        window.setTimeout(function () {
          if (action === 'folder-upload') {
            const folderTrigger = document.getElementById('workspace-upload-folder-trigger');
            if (folderTrigger) folderTrigger.click();
            return;
          }
          const fileTrigger = document.getElementById('workspace-upload-file-trigger');
          if (fileTrigger) fileTrigger.focus();
        }, 120);
      });
    });
  })();



  (function () {
    const shell = document.getElementById('workspace-shell');
    const toggle = document.getElementById('workspace-sidebar-toggle');
    if (!shell || !toggle) return;
    const key = 'wdms_workspace_sidebar_collapsed';
    const applyState = function (collapsed) {
      shell.classList.toggle('drive-shell--collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.innerHTML = collapsed
        ? '<i class="bi bi-layout-sidebar me-1"></i>Show panel'
        : '<i class="bi bi-layout-sidebar-inset me-1"></i>Hide panel';
    };
    applyState(window.localStorage.getItem(key) === '1');
    toggle.addEventListener('click', function () {
      const collapsed = !shell.classList.contains('drive-shell--collapsed');
      applyState(collapsed);
      window.localStorage.setItem(key, collapsed ? '1' : '0');
    });
  })();



  (function () {
    const modal = document.getElementById('workspaceFileDetailsModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      if (!trigger) return;
      const fields = ['title', 'code', 'direction', 'location', 'routing', 'priority', 'category', 'owner', 'storage', 'signatory', 'activity', 'tags'];
      fields.forEach(function (field) {
        const target = modal.querySelector('[data-detail-field="' + field + '"]');
        if (!target) return;
        target.textContent = trigger.getAttribute('data-file-' + field) || 'Not set';
      });
    });
  })();



  (function () {
    const body = document.body;
    const skipPrefixes = [
      '/documents/download',
      '/admin/users/export',
      '/admin/logs/export'
    ];
    const isModifiedClick = function (e) {
      return e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0;
    };

    document.addEventListener('click', function (e) {
      const link = e.target.closest('a[href]');
      if (!link) return;
      if (link.target === '_blank' || link.hasAttribute('download')) return;
      if (link.matches('.global-chat-attachment--image[data-image-preview="true"]')) return;
      if (isModifiedClick(e)) return;

      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
      if (link.origin !== window.location.origin) return;
      if (skipPrefixes.some(function (prefix) { return link.pathname.startsWith('/wdms' + prefix) || link.pathname.endsWith(prefix); })) return;

      body.classList.add('is-leaving');
    }, true);

    window.addEventListener('pageshow', function () {
      body.classList.remove('is-leaving');
    });

    const modalEl = document.getElementById('wdmsConfirmModal');
    const modalTitle = document.getElementById('wdmsConfirmModalTitle');
    const modalMessage = document.getElementById('wdmsConfirmModalMessage');
    const passwordWrap = document.getElementById('wdmsConfirmPasswordWrap');
    const passwordInput = document.getElementById('wdmsConfirmPasswordInput');
    const passwordLabel = document.getElementById('wdmsConfirmPasswordLabel');
    const confirmBtn = document.getElementById('wdmsConfirmOkBtn');
    const cancelBtn = modalEl ? modalEl.querySelector('[data-action="cancel"]') : null;
    const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const chatSettingsModalEl = document.getElementById('wdmsChatSettingsModal');
    const chatSettingsModal = chatSettingsModalEl ? new bootstrap.Modal(chatSettingsModalEl) : null;
    const openChatSettingsHubBtn = document.getElementById('global-chat-settings');
    const toggleColorModeBtn = document.getElementById('toggle-color-mode');
    const toggleColorModeIcon = document.getElementById('toggle-color-mode-icon');
    const toggleColorModeLabel = document.getElementById('toggle-color-mode-label');
    const togglePerformanceModeBtn = document.getElementById('toggle-performance-mode');
    const togglePerformanceIcon = document.getElementById('toggle-performance-icon');
    const togglePerformanceLabel = document.getElementById('toggle-performance-label');
    const togglePerformanceValue = document.getElementById('toggle-performance-value');
    const resetChatSettingsBtn = document.getElementById('wdmsChatSettingsReset');
    const chatThemeOptions = Array.from(document.querySelectorAll('[data-chat-theme-option]'));
    let resolver = null;

    const getStoredColorMode = function () {
      const value = document.documentElement.getAttribute('data-color-mode');
      return value === 'dark' ? 'dark' : 'light';
    };

    const getStoredChatTheme = function () {
      const value = document.documentElement.getAttribute('data-chat-theme') || 'ocean';
      return ['ocean', 'forest', 'sunset', 'midnight'].includes(value) ? value : 'ocean';
    };

    const detectPerformanceTier = function () {
      let prefersReducedMotion = false;
      let saveData = false;
      let deviceMemory = 8;
      let hardwareConcurrency = 8;
      try {
        prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        saveData = !!(navigator.connection && navigator.connection.saveData);
        deviceMemory = Number(navigator.deviceMemory || 8);
        hardwareConcurrency = Number(navigator.hardwareConcurrency || 8);
      } catch (_err) {}
      return (prefersReducedMotion || saveData || deviceMemory <= 4 || hardwareConcurrency <= 4) ? 'lite' : 'full';
    };

    const getStoredPerformanceMode = function () {
      const value = document.documentElement.getAttribute('data-performance-mode') || 'auto';
      return ['auto', 'lite', 'full'].includes(value) ? value : 'auto';
    };

    const setColorMode = function (mode) {
      const resolved = mode === 'dark' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-color-mode', resolved);
      try { localStorage.setItem('wdms-color-mode', resolved); } catch (_err) {}
      if (toggleColorModeIcon) {
        toggleColorModeIcon.className = 'bi ' + (resolved === 'dark' ? 'bi-moon-stars-fill' : 'bi-sun');
      }
      if (toggleColorModeLabel) {
        toggleColorModeLabel.textContent = resolved === 'dark' ? 'On' : 'Off';
      }
      if (toggleColorModeBtn) {
        toggleColorModeBtn.setAttribute('aria-checked', resolved === 'dark' ? 'true' : 'false');
      }
    };

    const setChatTheme = function (theme) {
      const resolved = ['ocean', 'forest', 'sunset', 'midnight'].includes(theme) ? theme : 'ocean';
      document.documentElement.setAttribute('data-chat-theme', resolved);
      try { localStorage.setItem('wdms-chat-theme', resolved); } catch (_err) {}
      chatThemeOptions.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-chat-theme-option') === resolved);
      });
    };

    const setPerformanceMode = function (mode) {
      const resolvedMode = ['auto', 'lite', 'full'].includes(mode) ? mode : 'auto';
      const resolvedTier = resolvedMode === 'auto' ? detectPerformanceTier() : resolvedMode;
      document.documentElement.setAttribute('data-performance-mode', resolvedMode);
      document.documentElement.setAttribute('data-performance-tier', resolvedTier);
      window.wdmsPerformance = Object.assign({}, window.wdmsPerformance || {}, {
        mode: resolvedMode,
        tier: resolvedTier
      });
      try { localStorage.setItem('wdms-performance-mode', resolvedMode); } catch (_err) {}

      const labelMap = {
        auto: resolvedTier === 'lite' ? 'Auto (Lite)' : 'Auto (Full)',
        lite: 'Lite',
        full: 'Full'
      };

      if (togglePerformanceIcon) {
        togglePerformanceIcon.className = 'bi ' + (resolvedTier === 'lite' ? 'bi-battery-half' : 'bi-speedometer2');
      }
      if (togglePerformanceLabel) {
        togglePerformanceLabel.textContent = resolvedMode === 'auto'
          ? 'Uses your device capability'
          : (resolvedMode === 'lite' ? 'Lighter visuals and motion' : 'Richer visuals and previews');
      }
      if (togglePerformanceValue) {
        togglePerformanceValue.textContent = labelMap[resolvedMode];
      }
    };

    setColorMode(getStoredColorMode());
    setChatTheme(getStoredChatTheme());
    setPerformanceMode(getStoredPerformanceMode());

    chatThemeOptions.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setChatTheme(this.getAttribute('data-chat-theme-option') || 'ocean');
      });
    });

    if (resetChatSettingsBtn) {
      resetChatSettingsBtn.addEventListener('click', function () {
        setChatTheme('ocean');
      });
    }

    if (toggleColorModeBtn) {
      toggleColorModeBtn.addEventListener('click', function () {
        setColorMode(getStoredColorMode() === 'dark' ? 'light' : 'dark');
      });
    }

    if (togglePerformanceModeBtn) {
      togglePerformanceModeBtn.addEventListener('click', function () {
        const current = getStoredPerformanceMode();
        const next = current === 'auto' ? 'lite' : (current === 'lite' ? 'full' : 'auto');
        setPerformanceMode(next);
      });
    }

    const openChatSettings = function () {
      if (!chatSettingsModal) return;
      setChatTheme(getStoredChatTheme());
      chatSettingsModal.show();
    };

    if (openChatSettingsHubBtn) {
      openChatSettingsHubBtn.addEventListener('click', openChatSettings);
    }

    window.wdmsConfirmModal = function (options) {
      return new Promise(function (resolve) {
        if (!modalEl || !bsModal) {
          resolve({ ok: window.confirm(options.message || 'Proceed?'), password: '' });
          return;
        }

        resolver = resolve;
        modalTitle.textContent = options.title || 'Confirm action';
        modalMessage.textContent = options.message || 'Proceed with this action?';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        passwordWrap.classList.toggle('d-none', !options.requirePassword);
        passwordLabel.textContent = options.passwordLabel || 'Confirm password';
        passwordInput.value = '';
        bsModal.show();
        if (options.requirePassword) {
          setTimeout(function () { passwordInput.focus(); }, 120);
        }
      });
    };

    const closeWith = function (ok) {
      if (!resolver) return;
      const payload = { ok: !!ok, password: passwordInput ? passwordInput.value : '' };
      const fn = resolver;
      resolver = null;
      bsModal.hide();
      fn(payload);
    };

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () { closeWith(true); });
    }
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () { closeWith(false); });
    }
    if (modalEl) {
      modalEl.addEventListener('hidden.bs.modal', function () {
        if (resolver) {
          const fn = resolver;
          resolver = null;
          fn({ ok: false, password: '' });
        }
      });
    }

    document.addEventListener('submit', async function (e) {
      const form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.matches('.js-confirm, .admin-confirm-form')) return;
      if (form.dataset.wdmsConfirmed === '1') {
        form.dataset.wdmsConfirmed = '0';
        return;
      }

      e.preventDefault();
      const isAdminForm = form.matches('.admin-confirm-form');
      const label = form.getAttribute('data-confirm-label') || 'continue';
      const requirePassword = isAdminForm || form.getAttribute('data-confirm-password') === 'true';
      const message = form.getAttribute('data-confirm-message')
        || (isAdminForm ? ('Confirm to ' + label + '.') : 'Proceed with this action?');
      const passwordPrompt = form.getAttribute('data-confirm-password-label')
        || (isAdminForm ? ('Enter your admin password to ' + label) : 'Confirm your password');

      const result = await window.wdmsConfirmModal({
        title: isAdminForm ? 'Admin confirmation required' : 'Confirm action',
        message: message,
        confirmText: 'Continue',
        requirePassword: requirePassword,
        passwordLabel: passwordPrompt
      });

      if (!result.ok) return;
      if (requirePassword) {
        if (!result.password) return;
        let hidden = form.querySelector('input[name="confirm_password"]');
        if (!hidden) {
          hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'confirm_password';
          form.appendChild(hidden);
        }
        hidden.value = result.password;
      }

      form.dataset.wdmsConfirmed = '1';
      form.submit();
    }, true);

    document.querySelectorAll('.alert').forEach(function (alertEl) {
      window.setTimeout(function () {
        alertEl.style.transition = 'opacity 260ms ease, transform 260ms ease, max-height 260ms ease, margin 260ms ease, padding 260ms ease';
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateY(-8px)';
        alertEl.style.maxHeight = '0';
        alertEl.style.marginTop = '0';
        alertEl.style.marginBottom = '0';
        alertEl.style.paddingTop = '0';
        alertEl.style.paddingBottom = '0';
        window.setTimeout(function () {
          if (alertEl && alertEl.parentNode) {
            alertEl.parentNode.removeChild(alertEl);
          }
        }, 280);
      }, 5000);
    });

    const alertsCountEl = document.getElementById('app-alert-count');
    const alertsDotEl = document.getElementById('app-alert-dot');
    const alertsItemsEl = document.getElementById('app-alert-items');
    if (alertsCountEl && alertsDotEl && alertsItemsEl) {
      const baseUrl = '/wdms';
      const escapeHtml = function (value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      };
      const normalizeLink = function (link) {
        const href = String(link || '').trim();
        if (!href) {
          return '';
        }
        if (href === 'chat://open') {
          return '__chat_open__';
        }
        if (href === '/documents' || href.endsWith('/documents')) {
          return '';
        }
        if (href.startsWith('http://') || href.startsWith('https://')) {
          return href;
        }
        if (href.startsWith('/')) {
          return baseUrl + href;
        }
        return baseUrl + '/' + href;
      };
      const classifyNotification = function (item) {
        const text = ((item && item.title ? item.title : '') + ' ' + (item && item.body ? item.body : '')).toLowerCase();
        if (text.includes('reject') || text.includes('denied') || text.includes('failed') || text.includes('error')) {
          return { tone: 'danger', icon: 'bi-x-circle-fill' };
        }
        if (text.includes('approved') || text.includes('accepted') || text.includes('success')) {
          return { tone: 'success', icon: 'bi-check-circle-fill' };
        }
        if (text.includes('review') || text.includes('pending') || text.includes('request')) {
          return { tone: 'warning', icon: 'bi-exclamation-circle-fill' };
        }
        if (text.includes('message') || text.includes('chat')) {
          return { tone: 'info', icon: 'bi-chat-left-text-fill' };
        }
        return { tone: 'info', icon: 'bi-bell-fill' };
      };
      const applyAlerts = function (payload) {
        const count = Number(payload && payload.count ? payload.count : 0);
        const items = Array.isArray(payload && payload.items) ? payload.items : [];
        alertsCountEl.textContent = String(count);
        alertsDotEl.classList.toggle('d-none', count <= 0);

        if (!items.length) {
          alertsItemsEl.innerHTML = '<div class="text-muted small px-2 py-1">No notifications.</div>';
          return;
        }

        alertsItemsEl.innerHTML = items.map(function (item) {
          const marker = classifyNotification(item);
          const isRead = !!(item && item.is_read);
          const textBlob = ((item && item.title ? item.title : '') + ' ' + (item && item.body ? item.body : '')).toLowerCase();
          const isChatItem = textBlob.includes('chat') || textBlob.includes('message');
          const title = escapeHtml(item.title || '');
          const body = escapeHtml(item.body || '');
          const resolvedLink = normalizeLink(item.link || '');
          const rowClass = 'dropdown-item app-notification-item app-notification-item--' + marker.tone + (isRead ? ' is-read' : '');
          if (resolvedLink === '__chat_open__' || (resolvedLink === '' && isChatItem)) {
            return '<button type="button" class="' + rowClass + ' js-open-chat-from-notification">'
              + '<span class="app-notification-item__icon"><i class="bi ' + marker.icon + '"></i></span>'
              + '<span class="app-notification-item__content"><strong>' + title + '</strong><small>' + body + '</small></span>'
              + '</button>';
          }
          if (!resolvedLink) {
            return '<div class="' + rowClass + '">'
              + '<span class="app-notification-item__icon"><i class="bi ' + marker.icon + '"></i></span>'
              + '<span class="app-notification-item__content"><strong>' + title + '</strong><small>' + body + '</small></span>'
              + '</div>';
          }
          const href = escapeHtml(resolvedLink);
          return '<a class="' + rowClass + '" href="' + href + '">'
            + '<span class="app-notification-item__icon"><i class="bi ' + marker.icon + '"></i></span>'
            + '<span class="app-notification-item__content"><strong>' + title + '</strong><small>' + body + '</small></span>'
            + '</a>';
        }).join('');
      };
      const pollAlerts = async function () {
        try {
          const res = await fetch(baseUrl + '/api/notifications/unread', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
          });
          if (!res.ok) return;
          const data = await res.json();
          applyAlerts(data);
        } catch (_err) {
          // Keep current UI state on polling failure.
        }
      };

      pollAlerts();
      window.setInterval(pollAlerts, 8000);

      const markReadForm = document.getElementById('mark-all-read-form');
      if (markReadForm) {
        markReadForm.addEventListener('submit', async function (e) {
          e.preventDefault();
          const formData = new FormData(markReadForm);
          try {
            const res = await fetch(markReadForm.action, {
              method: 'POST',
              body: formData,
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              credentials: 'same-origin'
            });
            if (!res.ok) return;
            alertsCountEl.textContent = '0';
            alertsDotEl.classList.add('d-none');
            alertsItemsEl.querySelectorAll('.app-notification-item').forEach(function (el) {
              el.classList.add('is-read');
            });
          } catch (_err) {}
        });
      }

      const clearAllForm = document.getElementById('clear-all-notifications-form');
      if (clearAllForm) {
        clearAllForm.addEventListener('submit', async function (e) {
          e.preventDefault();
          const formData = new FormData(clearAllForm);
          try {
            const res = await fetch(clearAllForm.action, {
              method: 'POST',
              body: formData,
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              credentials: 'same-origin'
            });
            if (!res.ok) return;
            alertsCountEl.textContent = '0';
            alertsDotEl.classList.add('d-none');
            alertsItemsEl.innerHTML = '<div class="text-muted small px-2 py-1">No notifications.</div>';
          } catch (_err) {}
        });
      }

      alertsItemsEl.addEventListener('click', function (e) {
        const openChatBtn = e.target.closest('.js-open-chat-from-notification');
        if (!openChatBtn) return;
        e.preventDefault();
        if (chatHub) {
          chatHub.classList.remove('d-none');
        }
      });
    }

    const chatLauncher = document.getElementById('global-chat-launcher');
    const chatHub = document.getElementById('global-chat-hub');
    const chatClose = document.getElementById('global-chat-close');
    const chatConversations = document.getElementById('global-chat-conversations');
    const chatUnread = document.getElementById('global-chat-unread');
    const chatUnreadDot = document.getElementById('global-chat-unread-dot');
    const chatDock = document.getElementById('global-chat-dock');
    const chatNewEmail = document.getElementById('global-chat-new-email');
    const chatNewMessage = document.getElementById('global-chat-new-message');
      const chatNewImage = document.getElementById('global-chat-new-image');
      const chatNewFile = document.getElementById('global-chat-new-file');
      const chatNewAttachmentName = document.getElementById('global-chat-new-attachment-name');
      const chatNewSend = document.getElementById('global-chat-new-send');
      const chatImageViewer = document.getElementById('wdmsChatImageViewer');
      const chatImageViewerTitle = document.getElementById('wdmsChatImageViewerTitle');
      const chatImageViewerImage = document.getElementById('wdmsChatImageViewerImage');
      const chatImageModalEl = document.getElementById('wdmsChatImageModal');
      const chatImageModal = chatImageModalEl ? new bootstrap.Modal(chatImageModalEl) : null;
      const chatImageModalTitle = document.getElementById('wdmsChatImageModalTitle');
      const chatImageModalImage = document.getElementById('wdmsChatImageModalImage');
      if (chatLauncher && chatHub && chatClose && chatConversations && chatUnread && chatUnreadDot && chatDock) {
      const baseUrl = '/wdms';
      const openWindows = {};
      const minimizedWindows = {};
      const peerCache = {};
      const CHAT_ANIM_MS = 180;

      const escapeHtml = function (value) {
        return String(value || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      };
      const initialsOf = function (name, email) {
        const source = String(name || email || 'U').trim();
        const parts = source.split(/\s+/).filter(Boolean);
        if (!parts.length) return 'U';
        const first = parts[0].charAt(0).toUpperCase();
        const second = parts.length > 1 ? parts[1].charAt(0).toUpperCase() : '';
        return (first + second) || 'U';
      };
      const presetClass = function (preset) {
        const key = String(preset || '').trim().toLowerCase();
        const allowed = ['preset-ocean', 'preset-sunset', 'preset-forest', 'preset-plum', 'preset-slate', 'preset-amber'];
        return allowed.includes(key) ? key : 'preset-ocean';
      };
      const avatarHtml = function (peer, extraClass) {
        const cls = (extraClass ? (' ' + extraClass) : '');
        const preset = presetClass(peer && peer.peer_avatar_preset);
        const photo = String(peer && peer.peer_avatar_photo ? peer.peer_avatar_photo : '').trim();
        const src = photo ? (photo.startsWith('/') ? (baseUrl + photo) : photo) : '';
        const initials = escapeHtml(initialsOf(peer && peer.peer_name, peer && peer.peer_email));
        if (src) {
          return '<span class="chat-peer-avatar ' + preset + cls + '"><img src="' + escapeHtml(src) + '" alt="' + escapeHtml(peer && peer.peer_name ? peer.peer_name : 'User') + '"></span>';
        }
        return '<span class="chat-peer-avatar ' + preset + cls + '">' + initials + '</span>';
      };

      const getWindow = function (peerId) {
        return openWindows[String(peerId)] || null;
      };
      const minimizeWindow = function (win) {
        if (!win || win.classList.contains('d-none')) return;
        win.classList.add('is-minimizing');
        window.setTimeout(function () {
          if (!win) return;
          win.classList.add('d-none');
          win.classList.remove('is-minimizing');
        }, CHAT_ANIM_MS);
      };
      const restoreWindow = function (win) {
        if (!win || !win.classList.contains('d-none')) return;
        win.classList.add('is-minimizing');
        win.classList.remove('d-none');
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(function () {
            if (!win) return;
            win.classList.remove('is-minimizing');
          });
        });
      };

      const setUnread = function (count) {
        const n = Number(count || 0);
        chatUnread.textContent = String(n);
        chatUnreadDot.classList.toggle('d-none', n <= 0);
      };

      const attachmentHtml = function (item) {
        const url = String(item && item.attachment_url ? item.attachment_url : '').trim();
        if (!url) return '';
        const name = escapeHtml(item && item.attachment_name ? item.attachment_name : 'Attachment');
        const mime = String(item && item.attachment_mime ? item.attachment_mime : '').toLowerCase();
        if (mime.startsWith('image/')) {
          return '<a class="global-chat-attachment global-chat-attachment--image" href="' + escapeHtml(url) + '" data-image-preview="true" data-image-title="' + name + '">'
            + '<img src="' + escapeHtml(url) + '" alt="' + name + '">'
            + '</a>';
        }
        return '<a class="global-chat-attachment" href="' + escapeHtml(url) + '" target="_blank" rel="noopener">'
          + '<i class="bi bi-paperclip me-1"></i>' + name
          + '</a>';
      };

      const renderMessages = function (peerId, items) {
        const win = getWindow(peerId);
        if (!win) return;
        const body = win.querySelector('.global-chat-window__body');
        if (!body) return;
        if (!items.length) {
          body.innerHTML = '<div class="text-muted small">No messages yet.</div>';
          return;
        }
        body.innerHTML = items.map(function (item) {
          const message = String(item && item.message ? item.message : '');
          const hasText = message.trim() !== '';
          return '<div class="global-chat-bubble ' + (item.is_mine ? 'is-mine' : 'is-theirs') + '">'
            + (hasText ? ('<div>' + escapeHtml(message) + '</div>') : '')
            + attachmentHtml(item)
            + '<div class="global-chat-bubble__meta">' + escapeHtml(item.created_at) + '</div>'
            + '</div>';
        }).join('');
        body.scrollTop = body.scrollHeight;
      };

      const syncAttachmentName = function (imageInput, fileInput, target) {
        if (!target) return;
        const imageFile = imageInput && imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
        const genericFile = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        const file = genericFile || imageFile;
        target.textContent = file ? file.name : 'No attachment selected';
      };

      const bindAttachmentInputs = function (imageInput, fileInput, target) {
        if (!imageInput || !fileInput || !target) return;

        imageInput.addEventListener('change', function () {
          if (imageInput.files && imageInput.files[0]) {
            fileInput.value = '';
          }
          syncAttachmentName(imageInput, fileInput, target);
        });

        fileInput.addEventListener('change', function () {
          if (fileInput.files && fileInput.files[0]) {
            imageInput.value = '';
          }
          syncAttachmentName(imageInput, fileInput, target);
        });
      };

      const deleteThread = async function (peerId) {
        const result = await window.wdmsConfirmModal({
          title: 'Delete chat',
          message: 'Delete this chat from your view? The other participant will still keep their copy.',
          confirmText: 'Delete'
        });
        if (!result.ok) return false;
        try {
          const res = await fetch(baseUrl + '/api/chat/thread/' + encodeURIComponent(peerId), {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
          });
          if (!res.ok) return false;
          const data = await res.json();
          setUnread(data && typeof data.unread_total !== 'undefined' ? data.unread_total : 0);
          return true;
        } catch (_err) {
          return false;
        }
      };

      const loadThread = async function (peerId) {
        const win = getWindow(peerId);
        if (!win) return;
        try {
          const res = await fetch(baseUrl + '/api/chat/thread/' + encodeURIComponent(peerId), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
          });
          if (!res.ok) return;
          const data = await res.json();
          const items = Array.isArray(data.items) ? data.items : [];
          renderMessages(peerId, items);
        } catch (_err) {}
      };

      const openChatWindow = function (peer) {
        const key = String(peer.peer_id);
        peerCache[key] = peer;

        Object.keys(openWindows).forEach(function (otherKey) {
          if (otherKey === key) return;
          const otherWin = openWindows[otherKey];
          if (!otherWin || otherWin.classList.contains('d-none')) return;
          minimizeWindow(otherWin);
          minimizedWindows[otherKey] = peerCache[otherKey] || minimizedWindows[otherKey] || { peer_id: Number(otherKey) };
        });

        if (openWindows[key]) {
          restoreWindow(openWindows[key]);
          delete minimizedWindows[key];
          renderDock();
          chatHub.classList.add('d-none');
          return;
        }

        const win = document.createElement('section');
        win.className = 'global-chat-window';
        win.dataset.peerId = key;
        win.innerHTML = ''
          + '<div class="global-chat-window__head">'
          + '  <div class="global-chat-peer-head">' + avatarHtml(peer) + '<div><strong>' + escapeHtml(peer.peer_name || peer.peer_email) + '</strong><div class="small text-muted">' + escapeHtml(peer.peer_email || '') + '</div></div></div>'
          + '  <div class="d-flex gap-1">'
          + '    <button type="button" class="btn btn-sm btn-light" data-action="delete" title="Delete chat"><i class="bi bi-trash3"></i></button>'
          + '    <button type="button" class="btn btn-sm btn-light" data-action="minimize">_</button>'
          + '    <button type="button" class="btn btn-sm btn-light" data-action="close">x</button>'
          + '  </div>'
          + '</div>'
          + '<div class="global-chat-window__body"><div class="text-muted small">Loading...</div></div>'
          + '<form class="global-chat-window__form">'
          + '  <div class="global-chat-message-row global-chat-message-row--window">'
          + '    <input class="form-control form-control-sm global-chat-message-row__input" name="message" placeholder="Type message..." autocomplete="off">'
          + '    <button class="btn btn-sm btn-primary global-chat-message-row__send global-chat-window__send" type="submit" aria-label="Send message" title="Send message"><i class="bi bi-send-fill"></i></button>'
          + '  </div>'
          + '  <div class="global-chat-attach-row global-chat-attach-row--window">'
          + '    <input class="global-chat-attach-input" name="attachment_image" type="file" accept="image/*">'
          + '    <input class="global-chat-attach-input" name="attachment_file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar">'
          + '    <label class="global-chat-attach-trigger" title="Upload image" aria-label="Upload image"><i class="bi bi-image"></i></label>'
          + '    <label class="global-chat-attach-trigger" title="Attach file" aria-label="Attach file"><i class="bi bi-paperclip"></i></label>'
          + '    <span class="global-chat-attach-name">No attachment selected</span>'
          + '  </div>'
          + '</form>';

        const windowForm = win.querySelector('.global-chat-window__form');
        const windowImageInput = windowForm.querySelector('input[name="attachment_image"]');
        const windowFileInput = windowForm.querySelector('input[name="attachment_file"]');
        const windowAttachmentName = windowForm.querySelector('.global-chat-attach-name');
        const windowTriggers = windowForm.querySelectorAll('.global-chat-attach-trigger');
        if (windowImageInput) {
          windowImageInput.id = 'chat-window-image-' + key;
        }
        if (windowFileInput) {
          windowFileInput.id = 'chat-window-file-' + key;
        }
        if (windowTriggers[0]) {
          windowTriggers[0].setAttribute('for', 'chat-window-image-' + key);
        }
        if (windowTriggers[1]) {
          windowTriggers[1].setAttribute('for', 'chat-window-file-' + key);
        }
        bindAttachmentInputs(windowImageInput, windowFileInput, windowAttachmentName);

        win.querySelector('[data-action="close"]').addEventListener('click', function () {
          delete openWindows[key];
          delete minimizedWindows[key];
          delete peerCache[key];
          win.remove();
          renderDock();
        });
        win.querySelector('[data-action="delete"]').addEventListener('click', async function () {
          const ok = await deleteThread(Number(key));
          if (!ok) return;
          delete openWindows[key];
          delete minimizedWindows[key];
          delete peerCache[key];
          win.remove();
          renderDock();
          pollConversations();
        });
        win.querySelector('[data-action="minimize"]').addEventListener('click', function () {
          minimizeWindow(win);
          minimizedWindows[key] = peer;
          renderDock();
        });
        windowForm.addEventListener('submit', async function (e) {
          e.preventDefault();
          const input = this.querySelector('input[name="message"]');
          const imageAttachmentInput = this.querySelector('input[name="attachment_image"]');
          const fileAttachmentInput = this.querySelector('input[name="attachment_file"]');
          const text = String(input.value || '').trim();
          const file = fileAttachmentInput && fileAttachmentInput.files && fileAttachmentInput.files[0]
            ? fileAttachmentInput.files[0]
            : (imageAttachmentInput && imageAttachmentInput.files && imageAttachmentInput.files[0] ? imageAttachmentInput.files[0] : null);
          if (!text && !file) return;
          const payload = new FormData();
          payload.set('peer_id', String(Number(peer.peer_id)));
          payload.set('message', text);
          if (file) {
            payload.set('attachment', file);
          }
          try {
            const res = await fetch(baseUrl + '/api/chat/send', {
              method: 'POST',
              headers: { 'Accept': 'application/json' },
              credentials: 'same-origin',
              body: payload
            });
            if (!res.ok) {
              return;
            }
            input.value = '';
            if (imageAttachmentInput) imageAttachmentInput.value = '';
            if (fileAttachmentInput) fileAttachmentInput.value = '';
            syncAttachmentName(imageAttachmentInput, fileAttachmentInput, windowAttachmentName);
            loadThread(peer.peer_id);
            pollConversations();
          } catch (_err) {}
        });

        chatDock.appendChild(win);
        openWindows[key] = win;
        loadThread(peer.peer_id);
      };

      const renderDock = function () {
        const chips = Object.keys(minimizedWindows).map(function (key) {
          const peer = minimizedWindows[key];
          return '<button type="button" class="btn btn-sm btn-light global-chat-chip" data-peer-id="' + escapeHtml(key) + '">'
            + avatarHtml(peer, 'chat-peer-avatar--mini')
            + escapeHtml(peer.peer_name || peer.peer_email || ('User #' + key))
            + '</button>';
        }).join('');
        const currentWindows = Array.from(chatDock.querySelectorAll('.global-chat-window')).map(function (el) { return el; });
        chatDock.innerHTML = chips;
        currentWindows.forEach(function (el) { chatDock.appendChild(el); });
        chatDock.querySelectorAll('.global-chat-chip').forEach(function (btn) {
          btn.addEventListener('click', function () {
            const key = this.getAttribute('data-peer-id');
            const win = openWindows[key];
            if (win) {
              restoreWindow(win);
              delete minimizedWindows[key];
              renderDock();
              loadThread(Number(key));
            }
          });
        });
      };

      const renderConversations = function (items) {
        if (!items.length) {
          chatConversations.innerHTML = '<div class="text-muted small">No conversations yet.</div>';
          return;
        }
        chatConversations.innerHTML = items.map(function (item) {
          const unread = Number(item.unread_count || 0);
          return '<div class="global-chat-convo-row">'
            + '<button type="button" class="global-chat-convo" data-peer-id="' + escapeHtml(item.peer_id) + '" data-peer-name="' + escapeHtml(item.peer_name || '') + '" data-peer-email="' + escapeHtml(item.peer_email || '') + '" data-peer-photo="' + escapeHtml(item.peer_avatar_photo || '') + '" data-peer-preset="' + escapeHtml(item.peer_avatar_preset || '') + '">'
            + '<div class="global-chat-convo__head"><div class="global-chat-convo__person">' + avatarHtml(item, 'chat-peer-avatar--sm') + '<strong>' + escapeHtml(item.peer_name || item.peer_email) + '</strong></div>' + (unread > 0 ? '<span class="chat-unread-dot chat-unread-dot--inline"></span>' : '') + '</div>'
            + '<div class="global-chat-convo__meta">' + escapeHtml(item.peer_email || '') + '</div>'
            + '<div class="global-chat-convo__last">' + escapeHtml(item.last_message || '') + '</div>'
            + '</button>'
            + '<button type="button" class="btn btn-sm btn-light global-chat-convo-delete" data-peer-id="' + escapeHtml(item.peer_id) + '" aria-label="Delete chat with ' + escapeHtml(item.peer_name || item.peer_email || 'user') + '"><i class="bi bi-trash3"></i></button>'
            + '</div>';
        }).join('');
        chatConversations.querySelectorAll('.global-chat-convo').forEach(function (btn) {
          btn.addEventListener('click', function () {
              const peer = {
                peer_id: Number(this.getAttribute('data-peer-id')),
                peer_name: this.getAttribute('data-peer-name') || '',
                peer_email: this.getAttribute('data-peer-email') || '',
                peer_avatar_photo: this.getAttribute('data-peer-photo') || '',
                peer_avatar_preset: this.getAttribute('data-peer-preset') || ''
              };
              openChatWindow(peer);
              chatHub.classList.add('d-none');
            });
        });
        chatConversations.querySelectorAll('.global-chat-convo-delete').forEach(function (btn) {
          btn.addEventListener('click', async function () {
            const peerId = Number(this.getAttribute('data-peer-id') || '0');
            if (!peerId) return;
            const ok = await deleteThread(peerId);
            if (!ok) return;
            const key = String(peerId);
            const win = openWindows[key];
            if (win) {
              delete openWindows[key];
              win.remove();
            }
            delete minimizedWindows[key];
            delete peerCache[key];
            renderDock();
            pollConversations();
          });
        });
      };

      const pollConversations = async function () {
        try {
          const res = await fetch(baseUrl + '/api/chat/conversations', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
          });
          if (!res.ok) return;
          const data = await res.json();
          const items = Array.isArray(data.items) ? data.items : [];
          setUnread(data.unread_total || 0);
          renderConversations(items);

          Object.keys(openWindows).forEach(function (key) {
            const win = openWindows[key];
            if (win && !win.classList.contains('d-none')) {
              loadThread(Number(key));
            }
          });
        } catch (_err) {}
      };

      chatLauncher.addEventListener('click', function () {
        chatHub.classList.toggle('d-none');
      });
      chatClose.addEventListener('click', function () {
        chatHub.classList.add('d-none');
      });

      bindAttachmentInputs(chatNewImage, chatNewFile, chatNewAttachmentName);

      document.addEventListener('click', function (e) {
        const imageLink = e.target.closest('.global-chat-attachment--image[data-image-preview="true"]');
        if (!imageLink || !chatImageViewer || !chatImageViewerImage) {
          return;
        }
        e.preventDefault();
        const imageSrc = imageLink.getAttribute('href') || '';
        const imageTitle = imageLink.getAttribute('data-image-title') || 'Image preview';
        chatImageViewerImage.src = imageSrc;
        chatImageViewerImage.alt = imageTitle;
        if (chatImageViewerTitle) {
          chatImageViewerTitle.textContent = imageTitle;
        }
        chatImageViewer.classList.remove('d-none');
        chatImageViewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('is-image-viewer-open');
      });

      if (chatImageViewer && chatImageViewerImage) {
        const closeImageViewer = function () {
          chatImageViewer.classList.add('d-none');
          chatImageViewer.setAttribute('aria-hidden', 'true');
          chatImageViewerImage.src = '';
          document.body.classList.remove('is-image-viewer-open');
        };

        chatImageViewer.addEventListener('click', function (e) {
          const closeTrigger = e.target.closest('[data-action="close-image-viewer"]');
          if (!closeTrigger) return;
          closeImageViewer();
        });

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && !chatImageViewer.classList.contains('d-none')) {
            closeImageViewer();
          }
        });
      }

      if (chatNewSend && chatNewEmail && chatNewMessage && chatNewImage && chatNewFile) {
        chatNewSend.addEventListener('click', async function () {
          const peerEmail = String(chatNewEmail.value || '').trim();
          const message = String(chatNewMessage.value || '').trim();
          const file = chatNewFile.files && chatNewFile.files[0]
            ? chatNewFile.files[0]
            : (chatNewImage.files && chatNewImage.files[0] ? chatNewImage.files[0] : null);
          if (!peerEmail || (!message && !file)) return;
          const payload = new FormData();
          payload.set('peer_email', peerEmail);
          payload.set('message', message);
          if (file) {
            payload.set('attachment', file);
          }
          try {
            const res = await fetch(baseUrl + '/api/chat/send', {
              method: 'POST',
              headers: { 'Accept': 'application/json' },
              credentials: 'same-origin',
              body: payload
            });
            if (res.ok) {
              chatNewMessage.value = '';
              chatNewImage.value = '';
              chatNewFile.value = '';
              syncAttachmentName(chatNewImage, chatNewFile, chatNewAttachmentName);
              pollConversations();
            }
          } catch (_err) {}
        });
      }

      pollConversations();
      window.setInterval(pollConversations, 5000);
    }

    const walkthroughModalEl = document.getElementById('wdmsWalkthroughModal');
    const walkthroughOpenBtn = document.getElementById('open-onboarding-guide');
    const walkthroughForm = document.getElementById('wdmsWalkthroughStateForm');
    if (walkthroughModalEl && walkthroughForm) {
      const walkthroughGuide = [{"title":"Manage the workspace structure","body":"Create accounts, assign roles, and map employees or section chiefs to the right division from Manage Users.","tip":"Admins oversee the system. They do not work inside document approval like normal staff."},{"title":"Inspect user workspaces","body":"Open a selected user workspace and switch between Routed Files and Trash to inspect their records without mixing accounts together.","tip":"Use the top search to jump to pages, folders, files, settings, and users faster."},{"title":"Handle access changes carefully","body":"Use password reset, role updates, disable account, and delete account only when needed because they directly affect user access and owned content.","tip":"Disable keeps the account intact. Delete removes the account and its owned files."}];
      const walkthroughAutoOpen = false;
      const walkthroughModal = new bootstrap.Modal(walkthroughModalEl);
      const stepCountEl = document.getElementById('wdmsWalkthroughStepCount');
      const titleEl = document.getElementById('wdmsWalkthroughTitle');
      const bodyEl = document.getElementById('wdmsWalkthroughBody');
      const tipEl = document.getElementById('wdmsWalkthroughTip');
      const progressBarEl = document.getElementById('wdmsWalkthroughProgressBar');
      const prevBtn = document.getElementById('wdmsWalkthroughPrev');
      const nextBtn = document.getElementById('wdmsWalkthroughNext');
      const skipBtn = document.getElementById('wdmsWalkthroughSkip');
      let stepIndex = 0;
      let completionSent = false;

      const markWalkthroughSeen = async function () {
        if (completionSent) return;
        completionSent = true;
        try {
          const formData = new FormData(walkthroughForm);
          await fetch(walkthroughForm.action, {
            method: 'POST',
            body: formData,
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          });
        } catch (_err) {
          completionSent = false;
        }
      };

      const renderWalkthroughStep = function () {
        const step = walkthroughGuide[stepIndex] || walkthroughGuide[0];
        if (!step) return;
        stepCountEl.textContent = 'Step ' + (stepIndex + 1) + ' of ' + walkthroughGuide.length;
        titleEl.textContent = step.title || '';
        bodyEl.textContent = step.body || '';
        tipEl.textContent = step.tip || '';
        progressBarEl.style.width = (((stepIndex + 1) / walkthroughGuide.length) * 100) + '%';
        prevBtn.disabled = stepIndex === 0;
        nextBtn.textContent = stepIndex >= walkthroughGuide.length - 1 ? 'Finish' : 'Next';
      };

      if (walkthroughOpenBtn) {
        walkthroughOpenBtn.addEventListener('click', function () {
          stepIndex = 0;
          renderWalkthroughStep();
          walkthroughModal.show();
        });
      }

      prevBtn.addEventListener('click', function () {
        if (stepIndex <= 0) return;
        stepIndex -= 1;
        renderWalkthroughStep();
      });

      nextBtn.addEventListener('click', async function () {
        if (stepIndex >= walkthroughGuide.length - 1) {
          await markWalkthroughSeen();
          walkthroughModal.hide();
          return;
        }
        stepIndex += 1;
        renderWalkthroughStep();
      });

      skipBtn.addEventListener('click', function () {
        walkthroughModal.hide();
      });

      renderWalkthroughStep();
      if (walkthroughAutoOpen) {
        window.setTimeout(function () {
          walkthroughModal.show();
        }, 280);
      }
    }
  })();
