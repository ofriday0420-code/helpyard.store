<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content" class="wrap page-main order-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/account">My account</a><span aria-hidden="true">/</span><span>Order</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Order status: <?= htmlspecialchars(str_replace('_', ' ', $order['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars($order['order_number'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <?php if ($order['status'] === 'payment_pending'): ?>
            <p>Your order is awaiting payment. Reserved stock will be released after the reservation deadline shown below. Payment is not available yet; you have not been charged.</p>
        <?php elseif ($order['status'] === 'cancelled'): ?>
            <p>This order reservation expired and the reserved stock has been released. Create a new order to try again.</p>
        <?php else: ?>
            <p>This order is <?= htmlspecialchars(str_replace('_', ' ', $order['status']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</p>
        <?php endif; ?>
    </section>

    <div class="cart-layout order-layout">
        <section class="order-items" aria-label="Ordered products">
            <h2>Items</h2>
            <?php foreach ($order['items'] as $item): ?>
                <article class="order-item">
                    <div>
                        <h3><?= htmlspecialchars($item['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                        <?php if ($item['variant_name'] !== null): ?><p><?= htmlspecialchars($item['variant_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
                        <p>Quantity: <?= (int) $item['quantity'] ?> · <?= htmlspecialchars((string) $item['unit_price'], ENT_QUOTES, 'UTF-8') ?> BDT each</p>
                    </div>
                    <strong><?= htmlspecialchars(number_format((float) $item['unit_price'] * (int) $item['quantity'], 2), ENT_QUOTES, 'UTF-8') ?> BDT</strong>
                </article>
            <?php endforeach; ?>
        </section>
        <aside class="cart-summary order-summary">
            <h2>Summary</h2>
            <p><span>Total</span><strong><?= htmlspecialchars((string) $order['final_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
            <h3>Delivery address</h3>
            <address>
                <?= htmlspecialchars($order['shipping_full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?= htmlspecialchars($order['shipping_phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?= htmlspecialchars($order['shipping_address_line_1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?php if ($order['shipping_address_line_2'] !== null && $order['shipping_address_line_2'] !== ''): ?><?= htmlspecialchars($order['shipping_address_line_2'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br><?php endif; ?>
                <?= htmlspecialchars($order['shipping_city'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= htmlspecialchars((string) $order['shipping_postal_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                <?= htmlspecialchars($order['shipping_country'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </address>
            <?php if ($order['status'] === 'payment_pending'): ?>
                <p class="checkout-note">Reservation deadline: <?= htmlspecialchars((string) $order['reservation_expires_at'], ENT_QUOTES, 'UTF-8') ?> UTC.</p>
            <?php endif; ?>
            <a class="button button-secondary" href="/products">Continue shopping</a>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
