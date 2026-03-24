<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/csrf.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>

<div class="app-auth-shell">
  <section class="auth-panel">
    <div class="auth-panel__brand">
      <img class="auth-panel__logo" src="<?= BASE_URL ?>/assets/images/logo.png" alt="WDMS logo">
      <span class="auth-panel__eyebrow">Web-Based Document Management System</span>
    </div>
    <h1 class="auth-panel__title">WDMS document workflow hub.</h1>
    <p class="auth-panel__copy">
      Sign in to access assigned records, organize folders and files, upload revised versions,
      and continue document actions based on your role.
    </p>

    <div class="auth-grid">
      <div class="auth-grid__card">
        <strong>Assigned document access</strong>
        <span>Open the folders and files available to your account and continue work from one secure workspace.</span>
      </div>
      <div class="auth-grid__card">
        <strong>Revision workflow</strong>
        <span>Download documents, edit them in desktop tools, then upload updated versions with tracked history.</span>
      </div>
      <div class="auth-grid__card">
        <strong>Role-based control</strong>
        <span>Users, reviewers, and administrators each continue the actions allowed in the current system flow.</span>
      </div>
    </div>
  </section>

  <section class="auth-card">
    <div class="auth-card__brand">
      <img class="auth-card__logo" src="<?= BASE_URL ?>/assets/images/logo.png" alt="WDMS logo">
      <div class="section-eyebrow">System Access</div>
    </div>
    <h2 class="auth-card__title">Sign in to continue your document tasks</h2>

    <?php if(!empty($error)): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <label class="auth-form__label">
        <span>Work email</span>
        <input class="form-control" name="email" placeholder="Enter your registered email" required>
      </label>

      <label class="auth-form__label">
        <span>Account password</span>
        <span class="auth-password-field">
          <input
            type="password"
            class="form-control auth-password-field__input"
            name="password"
            placeholder="Enter your password"
            required
            data-password-input
          >
          <button
            type="button"
            class="auth-password-field__toggle"
            data-password-toggle
            aria-label="Show password"
            aria-pressed="false"
          >
            <span>Show</span>
          </button>
        </span>
      </label>

      <button class="btn btn-primary">Sign in to WDMS</button>
    </form>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.querySelector('[data-password-input]');
    const toggleButton = document.querySelector('[data-password-toggle]');

    if (!passwordInput || !toggleButton) {
      return;
    }

    toggleButton.addEventListener('click', function () {
      const showing = passwordInput.type === 'text';
      passwordInput.type = showing ? 'password' : 'text';
      toggleButton.setAttribute('aria-pressed', String(!showing));
      toggleButton.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      toggleButton.textContent = showing ? 'Show' : 'Hide';
    });
  });
</script>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
