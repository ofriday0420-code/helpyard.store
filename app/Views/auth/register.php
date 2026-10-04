<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <section class="auth-panel">
        <p class="eyebrow">Create your Helpyard account</p>
        <h1>Create account</h1>
        <p class="auth-intro">Set up an account to keep your store activity together.</p>
        <?php if ($error !== ''): ?>
            <p class="auth-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <form class="auth-form" method="post" action="/register">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="register-name">Full name</label>
            <input id="register-name" name="name" type="text" maxlength="120" autocomplete="name" required value="<?= htmlspecialchars($oldName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <label for="register-email">Email address</label>
            <input id="register-email" name="email" type="email" maxlength="180" autocomplete="email" required value="<?= htmlspecialchars($oldEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <label for="register-password">Password</label>
            <input id="register-password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
            <p class="field-hint">Use at least 12 characters. Passwords are never stored in plain text.</p>
            <button class="button button-primary auth-submit" type="submit">Create account <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Already have an account? <a href="/login">Sign in</a></p>
        <p class="auth-notice">Email verification and password reset will be enabled after email delivery is configured.</p>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
