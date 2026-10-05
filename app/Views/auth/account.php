<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><span>My account</span>
    </nav>
    <section class="account-panel">
        <div>
            <p class="eyebrow">Customer account</p>
            <h1>Welcome, <?= htmlspecialchars($user['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
            <p class="auth-intro">Manage your profile, saved addresses, and recent orders.</p>
        </div>
        <?php if ($notice !== ''): ?>
            <p class="auth-success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="auth-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="account-details">
            <span>Email address</span>
            <strong><?= htmlspecialchars($user['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
            <span>Account type</span>
            <strong>Customer</strong>
        </div>
        <form class="auth-form" method="post" action="/account/profile">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="profile-name">Full name</label>
            <input id="profile-name" name="name" type="text" maxlength="120" autocomplete="name" required value="<?= htmlspecialchars($user['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button class="button button-primary" type="submit">Update profile</button>
        </form>
        <a class="button button-primary" href="/account/addresses">Manage addresses <span aria-hidden="true">→</span></a>
        <a class="button button-secondary" href="/account/courses">My courses <span aria-hidden="true">→</span></a>
        <a class="button button-secondary" href="/account/downloads">My downloads <span aria-hidden="true">→</span></a>
        <section class="account-orders" aria-labelledby="account-orders-title">
            <h2 id="account-orders-title">Recent orders</h2>
            <?php if ($orders === []): ?>
                <p class="auth-intro">You have not placed an order yet.</p>
            <?php else: ?>
                <div class="account-order-list">
                    <?php foreach ($orders as $order): ?>
                        <a class="account-order" href="/orders/<?= (int) $order['id'] ?>">
                            <span>
                                <strong><?= htmlspecialchars($order['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars((new DateTimeImmutable($order['created_at'], new DateTimeZone('UTC')))->format('M j, Y'), ENT_QUOTES, 'UTF-8') ?></small>
                            </span>
                            <span class="account-order-state"><?= htmlspecialchars(str_replace('_', ' ', $order['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars((string) $order['final_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <form method="post" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button button-primary" type="submit">Sign out</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
