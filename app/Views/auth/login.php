<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <section class="auth-panel">
        <p class="eyebrow">Welcome back</p>
        <h1>Sign in</h1>
        <p class="auth-intro">Sign in to view your Helpyard account.</p>
        <?php if ($notice !== ''): ?>
            <p class="auth-success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="auth-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <form class="auth-form" method="post" action="/login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="login-email">Email address</label>
            <input id="login-email" name="email" type="email" maxlength="180" autocomplete="email" required value="<?= htmlspecialchars($oldEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <label for="login-password">Password</label>
            <input id="login-password" name="password" type="password" maxlength="72" autocomplete="current-password" required>
            <button class="button button-primary auth-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">New to Helpyard? <a href="/register">Create an account</a></p>
        <p class="auth-notice">Password recovery will be available after email delivery is configured.</p>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
