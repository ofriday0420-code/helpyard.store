<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><span>Order fulfillment</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Administration</p>
        <h1>Physical order fulfillment</h1>
        <p>Only paid orders containing physical products or books appear here.</p>
        <div class="admin-actions">
            <a class="button button-secondary" href="/admin/orders">Order administration</a>
            <a class="button button-secondary" href="/admin/catalog">Manage catalog</a>
            <a class="button button-secondary" href="/admin/files">Manage private product files</a>
            <a class="button button-secondary" href="/admin/courses">Author course lessons</a>
        </div>
    </section>
    <?php if ($notice !== ''): ?><p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($orders === []): ?>
        <p>No eligible orders need fulfillment right now.</p>
    <?php else: ?>
        <div class="order-items" aria-label="Orders requiring fulfillment">
            <?php foreach ($orders as $order): ?>
                <article class="order-item">
                    <div>
                        <h2><?= htmlspecialchars($order['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars($order['shipping_full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars($order['shipping_city'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <p>Status: <?= htmlspecialchars(str_replace('_', ' ', $order['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= (int) $order['item_count'] ?> item(s) · <?= htmlspecialchars((string) $order['final_total'], ENT_QUOTES, 'UTF-8') ?> BDT</p>
                        <?php if ($order['status'] === 'paid'): ?>
                            <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/fulfillment">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="start_processing">
                                <button class="button button-primary" type="submit">Start processing</button>
                            </form>
                        <?php elseif ($order['status'] === 'processing'): ?>
                            <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/fulfillment">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="mark_shipped">
                                <label>Carrier <input name="carrier" maxlength="80" required></label>
                                <label>Tracking number <input name="tracking_number" maxlength="120" required></label>
                                <button class="button button-primary" type="submit">Mark shipped</button>
                            </form>
                        <?php elseif ($order['status'] === 'shipped'): ?>
                            <form method="post" action="/admin/orders/<?= (int) $order['id'] ?>/fulfillment">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="mark_delivered">
                                <button class="button button-primary" type="submit">Mark delivered</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
