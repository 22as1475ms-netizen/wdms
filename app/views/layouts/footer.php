<?php
$chatUser = $_SESSION['user'] ?? null;
$chatEnabled = !empty($chatUser) && strtoupper((string)($chatUser['role'] ?? '')) !== 'ADMIN';
$chatInitials = $chatUser ? avatar_initials((string)($chatUser['name'] ?? '')) : '';
$chatAvatarPhoto = $chatUser ? avatar_photo_url($chatUser) : null;
$chatAvatarPreset = $chatUser ? avatar_preset_key($chatUser) : 'preset-ocean';
$walkthroughRole = strtoupper((string)($chatUser['role'] ?? ''));
$walkthroughVersion = match ($walkthroughRole) {
  'ADMIN' => 'admin-v1',
  'DIVISION_CHIEF' => 'division-chief-v1',
  default => 'employee-v1',
};
$walkthroughSeenVersion = (string)($chatUser['onboarding_guide_version'] ?? '');
$walkthroughAutoOpen = !empty($chatUser) && $walkthroughSeenVersion !== $walkthroughVersion;
$walkthroughGuide = match ($walkthroughRole) {
  'ADMIN' => [
    ['title' => 'Manage the workspace structure', 'body' => 'Create accounts, assign roles, and map employees or section chiefs to the right division from Manage Users.', 'tip' => 'Admins oversee the system. They do not work inside document approval like normal staff.'],
    ['title' => 'Inspect user workspaces', 'body' => 'Open a selected user workspace and switch between Routed Files and Trash to inspect their records without mixing accounts together.', 'tip' => 'Use the top search to jump to pages, folders, files, settings, and users faster.'],
    ['title' => 'Handle access changes carefully', 'body' => 'Use password reset, role updates, disable account, and delete account only when needed because they directly affect user access and owned content.', 'tip' => 'Disable keeps the account intact. Delete removes the account and its owned files.'],
  ],
  'DIVISION_CHIEF' => [
    ['title' => 'Use your own workspace first', 'body' => 'Your Routed Files workspace is where uploads, folders, and document routing all happen. Keep tracking fields current so the next handler can follow the file easily.', 'tip' => 'The routed workspace keeps current location, last touch, and review state together.'],
    ['title' => 'Review through Division Queue', 'body' => 'Open Division Queue to see each employee in your division as a folder. Open an employee folder to review their routed submissions.', 'tip' => 'Division Queue is for viewing and reviewing employee records, not for adding or deleting their files.'],
    ['title' => 'Approve or reject clearly', 'body' => 'Preview a submitted routed file, then approve or reject it with a note so the employee knows what to keep or fix.', 'tip' => 'Use the status filters in Routed Files to check Draft, Approved, and Rejected records quickly.'],
  ],
  default => [
    ['title' => 'Start in Routed Files', 'body' => 'Upload files into Routed Files and keep the tracking fields complete from the beginning so every handoff is visible.', 'tip' => 'Current location and route status help everyone see where the file is now.'],
    ['title' => 'Route and share clearly', 'body' => 'Use routing updates and sharing to send a file to the next person or destination without moving it between separate storage areas.', 'tip' => 'Each route entry records who handled the file and where it went next.'],
    ['title' => 'Submit and track review', 'body' => 'Submit a routed file to your section chief when it is ready for review. Use the Draft, Approved, and Rejected filters to track status.', 'tip' => 'Trash holds deleted items. Permanent delete cannot be undone.'],
  ],
};
?>
</div>
<?php if($chatEnabled): ?>
<div class="global-chat-launcher-stack">
  <button type="button" class="global-chat-launcher global-chat-launcher--compose" id="global-chat-launcher" aria-label="Create message" title="Create message">
    <i class="bi bi-pencil-square"></i>
    <span id="global-chat-unread-dot" class="chat-unread-dot d-none" aria-hidden="true"></span>
    <span id="global-chat-unread" class="visually-hidden">0</span>
  </button>
</div>
<div class="global-chat-incoming-preview d-none" id="global-chat-incoming-preview" aria-label="Recent chats"></div>
<section class="global-chat-hub d-none" id="global-chat-hub">
  <div class="global-chat-hub__head">
    <strong>Messages</strong>
    <div class="d-flex gap-1">
      <button type="button" class="btn btn-sm btn-light" id="global-chat-settings" aria-label="Open chat settings"><i class="bi bi-sliders2"></i></button>
      <button type="button" class="btn btn-sm btn-light" id="global-chat-close">Close</button>
    </div>
  </div>
  <div class="global-chat-compose mb-2">
    <input type="email" class="form-control form-control-sm" id="global-chat-new-email" placeholder="Send to email">
    <div class="global-chat-message-row">
      <input type="text" class="form-control form-control-sm global-chat-message-row__input" id="global-chat-new-message" placeholder="Type message...">
      <button type="button" class="btn btn-sm btn-primary global-chat-message-row__send" id="global-chat-new-send" aria-label="Send message" title="Send message">
        <i class="bi bi-send-fill"></i>
      </button>
    </div>
    <div class="global-chat-attach-row">
      <input type="file" class="global-chat-attach-input" id="global-chat-new-image" accept="image/*">
      <input type="file" class="global-chat-attach-input" id="global-chat-new-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar">
      <label class="global-chat-attach-trigger" for="global-chat-new-image" title="Upload image" aria-label="Upload image"><i class="bi bi-image"></i></label>
      <label class="global-chat-attach-trigger" for="global-chat-new-file" title="Attach file" aria-label="Attach file"><i class="bi bi-paperclip"></i></label>
      <span class="global-chat-attach-name" id="global-chat-new-attachment-name">No attachment selected</span>
    </div>
  </div>
  <div class="global-chat-hub__list" id="global-chat-conversations">
    <div class="text-muted small">No conversations yet.</div>
  </div>
</section>
<div class="global-chat-dock" id="global-chat-dock"></div>
<?php endif; ?>
<div class="modal fade" id="wdmsConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content wdms-confirm-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="wdmsConfirmModalTitle">Confirm action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="mb-3" id="wdmsConfirmModalMessage"></p>
        <div id="wdmsConfirmPasswordWrap" class="d-none">
          <label class="form-label small text-muted" for="wdmsConfirmPasswordInput" id="wdmsConfirmPasswordLabel">Confirm password</label>
          <input type="password" class="form-control" id="wdmsConfirmPasswordInput" autocomplete="current-password">
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-action="cancel">Cancel</button>
        <button type="button" class="btn btn-primary" data-action="confirm" id="wdmsConfirmOkBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>
<div class="wdms-chat-image-viewer d-none" id="wdmsChatImageViewer" aria-hidden="true">
  <div class="wdms-chat-image-viewer__backdrop" data-action="close-image-viewer"></div>
  <section class="wdms-chat-image-viewer__dialog" role="dialog" aria-modal="true" aria-labelledby="wdmsChatImageViewerTitle">
    <div class="wdms-chat-image-viewer__head">
      <h5 class="wdms-chat-image-viewer__title" id="wdmsChatImageViewerTitle">Image preview</h5>
      <button type="button" class="wdms-chat-image-viewer__close" data-action="close-image-viewer" aria-label="Close image preview">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="wdms-chat-image-viewer__body">
      <img class="wdms-chat-image-viewer__image" id="wdmsChatImageViewerImage" src="" alt="Chat attachment preview">
    </div>
  </section>
</div>
<?php if(!empty($chatUser)): ?>
<div class="modal fade" id="wdmsChatSettingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content wdms-chat-settings-modal">
      <div class="modal-header border-0 pb-0">
        <div>
          <div class="wdms-chat-settings-modal__eyebrow">Conversation style</div>
          <h5 class="modal-title mb-1">Chat theme</h5>
          <p class="wdms-chat-settings-modal__intro mb-0">Choose the chat palette that feels best inside your conversation windows.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if($chatEnabled): ?>
        <section class="wdms-settings-section">
          <div class="wdms-settings-section__head">
            <strong>Chat theme</strong>
            <span>Applies to chat launcher, hub, and message windows</span>
          </div>
          <div class="wdms-option-grid wdms-option-grid--chat" id="wdmsChatThemeOptions">
            <button type="button" class="wdms-option-card" data-chat-theme-option="ocean">
              <span class="wdms-theme-swatch wdms-theme-swatch--ocean"></span>
              <strong>Ocean</strong>
              <span>Cool blue with crisp message contrast.</span>
            </button>
            <button type="button" class="wdms-option-card" data-chat-theme-option="forest">
              <span class="wdms-theme-swatch wdms-theme-swatch--forest"></span>
              <strong>Forest</strong>
              <span>Calm green tones with grounded accents.</span>
            </button>
            <button type="button" class="wdms-option-card" data-chat-theme-option="sunset">
              <span class="wdms-theme-swatch wdms-theme-swatch--sunset"></span>
              <strong>Sunset</strong>
              <span>Warm coral and amber with balanced depth.</span>
            </button>
            <button type="button" class="wdms-option-card" data-chat-theme-option="midnight">
              <span class="wdms-theme-swatch wdms-theme-swatch--midnight"></span>
              <strong>Midnight</strong>
              <span>Ink blue accents for a sharper workspace feel.</span>
            </button>
          </div>
        </section>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" id="wdmsChatSettingsReset">Reset chat theme</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
      if (skipPrefixes.some(function (prefix) { return link.pathname.startsWith('<?= BASE_URL ?>' + prefix) || link.pathname.endsWith(prefix); })) return;

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

    setColorMode(getStoredColorMode());
    setChatTheme(getStoredChatTheme());

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
      const baseUrl = '<?= BASE_URL ?>';
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
    const chatIncomingPreview = document.getElementById('global-chat-incoming-preview');
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
      const baseUrl = '<?= BASE_URL ?>';
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
      const launcherAvatarHtml = function (peer, fallbackClass) {
        const resolved = peer || {};
        const preset = presetClass(resolved.peer_avatar_preset);
        const photo = String(resolved.peer_avatar_photo || '').trim();
        const src = photo ? (photo.startsWith('/') ? (baseUrl + photo) : photo) : '';
        const label = escapeHtml(resolved.peer_name || resolved.peer_email || 'User');
        const initials = escapeHtml(initialsOf(resolved.peer_name, resolved.peer_email));
        if (src) {
          return '<img src="' + escapeHtml(src) + '" alt="' + label + '">';
        }
        return '<span class="chat-peer-avatar ' + preset + ' ' + fallbackClass + '">' + initials + '</span>';
      };
      const hideIncomingPreview = function () {
        if (!chatIncomingPreview) return;
        chatIncomingPreview.classList.add('d-none');
        chatIncomingPreview.innerHTML = '';
      };
      const showIncomingPreview = function (items) {
        if (!chatIncomingPreview) {
          return;
        }
        const peers = Array.isArray(items)
          ? items.filter(function (peer) {
              const key = String(peer && peer.peer_id ? peer.peer_id : '');
              return key !== '' && !minimizedWindows[key] && !openWindows[key];
            }).slice(0, 3)
          : [];
        if (!peers.length) {
          hideIncomingPreview();
          return;
        }
        chatIncomingPreview.innerHTML = peers.map(function (peer) {
          return '<button type="button" class="global-chat-incoming-preview__person" data-peer-id="' + escapeHtml(peer.peer_id) + '" aria-label="Open chat with ' + escapeHtml(peer.peer_name || peer.peer_email || 'user') + '">'
            + '<span class="global-chat-incoming-preview__avatar">' + launcherAvatarHtml(peer, 'global-chat-incoming-preview__avatar-fallback') + '</span>'
            + '</button>';
        }).join('');
        chatIncomingPreview.querySelectorAll('.global-chat-incoming-preview__person').forEach(function (btn) {
          btn.addEventListener('click', function () {
            const key = this.getAttribute('data-peer-id') || '';
            const peer = peerCache[key];
            if (peer && peer.peer_id) {
              openChatWindow(peer);
              chatHub.classList.add('d-none');
            }
          });
        });
        chatIncomingPreview.classList.remove('d-none');
      };

      const getWindow = function (peerId) {
        return openWindows[String(peerId)] || null;
      };
      const minimizeWindow = function (win, onHidden) {
        if (!win || win.classList.contains('d-none')) return;
        win.classList.add('is-minimizing');
        window.setTimeout(function () {
          if (!win) return;
          win.classList.add('d-none');
          win.classList.remove('is-minimizing');
          if (typeof onHidden === 'function') {
            onHidden();
          }
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
          minimizeWindow(win, function () {
            minimizedWindows[key] = peer;
            renderDock();
          });
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
        hideIncomingPreview();
        loadThread(peer.peer_id);
      };

      const renderDock = function () {
        const chips = Object.keys(minimizedWindows).map(function (key) {
          const peer = minimizedWindows[key];
          return '<button type="button" class="btn btn-sm btn-light global-chat-chip" data-peer-id="' + escapeHtml(key) + '">'
            + avatarHtml(peer, 'global-chat-chip__avatar')
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
          hideIncomingPreview();
          chatConversations.innerHTML = '<div class="text-muted small">No conversations yet.</div>';
          return;
        }
        if (chatHub.classList.contains('d-none')) {
          showIncomingPreview(items.filter(function (item) {
            return Number(item.unread_count || 0) > 0;
          }));
        } else {
          hideIncomingPreview();
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
        const isHidden = chatHub.classList.contains('d-none');
        chatHub.classList.toggle('d-none');
        if (isHidden) {
          hideIncomingPreview();
        }
      });
      chatClose.addEventListener('click', function () {
        chatHub.classList.add('d-none');
      });
      if (chatIncomingPreview) {
        chatIncomingPreview.addEventListener('click', function (event) {
          if (event.target.closest('.global-chat-incoming-preview__person')) {
            return;
          }
          chatHub.classList.remove('d-none');
          hideIncomingPreview();
        });
      }

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

  })();
</script>
</body>
</html>
