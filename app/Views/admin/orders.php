<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><span>Administration</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Administration</p>
        <h1>Order administration</h1>
        <p>Payment-review orders are shown first. Open an order to inspect its customer, item snapshot, payment attempts, and any shipment record. This console is read-only.</p>
        <div class="admin-actions">
            <a class="button button-secondary" href="/admin/fulfillment">Physical fulfillment</a>
            <a class="button button-secondary" href="/admin/catalog">Manage catalog</a>
            <a class="button button-secondary" href="/admin/files">Manage private files</a>
        </div>
    </section>
    <?php if ($orders === []): ?>
        <p>No orders are available.</p>
    <?php else: ?>
        <div class="order-items" aria-label="Customer orders">
            <?php foreach ($orders as $order): ?>
                <article class="order-item">
                    <div>
                        <h2><a href="/admin/orders/<?= (int) $order['id'] ?>"><?= htmlspecialchars($order['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></h2>
                        <p><?= htmlspecialchars($order['customer_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars($order['customer_email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <p>
                            Order: <?= htmlspecialchars(str_replace('_', ' ', $order['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php if ($order['payment_status'] !== null): ?>
                                · Payment: <?= htmlspecialchars(str_replace('_', ' ', $order['payment_status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php endif; ?>
                            · <?= htmlspecialchars((string) $order['final_total'], ENT_QUOTES, 'UTF-8') ?> BDT
                        </p>
                        <p><?= htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8') ?> UTC</p>
                    </div>
                    <a class="button button-secondary" href="/admin/orders/<?= (int) $order['id'] ?>">Review order</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
