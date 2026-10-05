<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main order-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/admin/orders">Order administration</a><span aria-hidden="true">/</span><span><?= htmlspecialchars($order['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Order status: <?= htmlspecialchars(str_replace('_', ' ', $order['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars($order['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <p>Created <?= htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8') ?> UTC</p>
        <div class="admin-actions">
            <a class="button button-secondary" href="/admin/orders">Back to orders</a>
            <a class="button button-secondary" href="/admin/fulfillment">Physical fulfillment</a>
        </div>
    </section>
    <?php if ($order['status'] === 'payment_review'): ?>
        <p class="cart-feedback is-error" role="status">Payment requires manual review. This screen does not change order, payment, inventory, or refund state.</p>
    <?php endif; ?>
    <div class="cart-layout order-layout">
        <section class="order-items" aria-label="Order items and payment records">
            <h2>Items</h2>
            <?php foreach ($order['items'] as $item): ?>
                <article class="order-item">
                    <div>
                        <h3><?= htmlspecialchars($item['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                        <?php if ($item['variant_name'] !== null): ?><p><?= htmlspecialchars($item['variant_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
                        <p><?= htmlspecialchars(str_replace('_', ' ', $item['product_type']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · Quantity <?= (int) $item['quantity'] ?> · <?= htmlspecialchars((string) $item['unit_price'], ENT_QUOTES, 'UTF-8') ?> BDT each</p>
                    </div>
                    <strong><?= htmlspecialchars((string) $item['line_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong>
                </article>
            <?php endforeach; ?>
            <h2>Payment attempts</h2>
            <?php if ($order['payments'] === []): ?>
                <p>No payment attempts are recorded.</p>
            <?php else: ?>
                <?php foreach ($order['payments'] as $payment): ?>
                    <article class="order-item">
                        <div>
                            <h3><?= htmlspecialchars(strtoupper($payment['provider']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars(str_replace('_', ' ', $payment['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                            <p>Transaction: <?= htmlspecialchars($payment['transaction_id'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p>Validation reference: <?= htmlspecialchars($payment['validation_id'] ?? 'Not validated', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <p><?= htmlspecialchars((string) $payment['amount'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($payment['currency'], ENT_QUOTES, 'UTF-8') ?> · Updated <?= htmlspecialchars($payment['updated_at'], ENT_QUOTES, 'UTF-8') ?> UTC</p>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <aside class="cart-summary order-summary">
            <h2>Customer</h2>
            <p><?= htmlspecialchars($order['customer_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <p><a href="mailto:<?= htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($order['customer_email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></p>
            <h2>Order totals</h2>
            <p><span>Subtotal</span><strong><?= htmlspecialchars((string) $order['subtotal'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
            <p><span>Shipping</span><strong><?= htmlspecialchars((string) $order['shipping_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
            <p><span>Discount</span><strong><?= htmlspecialchars((string) $order['discount_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
            <p><span>Total</span><strong><?= htmlspecialchars((string) $order['final_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
            <h2>Shipping address</h2>
            <address>
                <?= htmlspecialchars($order['shipping_full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?= htmlspecialchars($order['shipping_phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?= htmlspecialchars($order['shipping_address_line_1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?php if ($order['shipping_address_line_2'] !== null && $order['shipping_address_line_2'] !== ''): ?><?= htmlspecialchars($order['shipping_address_line_2'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br><?php endif; ?>
                <?= htmlspecialchars($order['shipping_city'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= htmlspecialchars((string) $order['shipping_postal_code'], ENT_QUOTES, 'UTF-8') ?><br>
                <?= htmlspecialchars($order['shipping_country'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </address>
            <?php if ($order['reservation_expires_at'] !== null): ?>
                <p>Reservation deadline: <?= htmlspecialchars($order['reservation_expires_at'], ENT_QUOTES, 'UTF-8') ?> UTC</p>
            <?php endif; ?>
            <?php if ($order['shipment'] !== null): ?>
                <h2>Shipment</h2>
                <p><?= htmlspecialchars($order['shipment']['carrier'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars($order['shipment']['tracking_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p>Status: <?= htmlspecialchars(str_replace('_', ' ', $order['shipment']['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
