<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content" class="wrap page-main checkout-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/cart">Your cart</a><span aria-hidden="true">/</span><span>Checkout</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Final review</p>
        <h1>Checkout</h1>
        <p>Confirm your delivery details and the current order total.</p>
    </section>

    <?php if ($error !== ''): ?>
        <p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($cart['items'] === []): ?>
        <section class="cart-empty">
            <h2>Your cart is empty</h2>
            <p>Add a product before continuing to checkout.</p>
            <a class="button button-primary" href="/products">Browse products</a>
        </section>
    <?php else: ?>
        <div class="cart-layout">
            <section class="checkout-addresses">
                <h2>Delivery address</h2>
                <?php if ($addresses === []): ?>
                    <p class="cart-feedback is-error">Add a saved address to your account before checkout.</p>
                    <a class="button button-secondary" href="/account/addresses">Manage addresses</a>
                <?php else: ?>
                    <form action="/checkout" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <fieldset class="checkout-address-list">
                            <legend class="visually-hidden">Choose a saved delivery address</legend>
                            <?php foreach ($addresses as $index => $address): ?>
                                <label class="checkout-address">
                                    <input type="radio" name="address_id" value="<?= (int) $address['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                    <span>
                                        <strong><?= htmlspecialchars($address['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                        <?php if ((int) $address['is_default'] === 1): ?><span class="default-badge">Default</span><?php endif; ?>
                                        <span><?= htmlspecialchars($address['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                        <span><?= htmlspecialchars($address['address_line_1'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $address['address_line_2'] !== null && $address['address_line_2'] !== '' ? ', ' . htmlspecialchars($address['address_line_2'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></span>
                                        <span><?= htmlspecialchars($address['city'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $address['postal_code'] !== null && $address['postal_code'] !== '' ? ' ' . htmlspecialchars($address['postal_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?>, <?= htmlspecialchars($address['country'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <button class="button button-primary" type="submit" <?= $canSubmit ? '' : 'disabled' ?>>Place order</button>
                    </form>
                    <p class="checkout-note">
                        Placing the order reserves the shown stock for 30 minutes.
                        <?php if ($paymentEnabled): ?>You can then pay securely through SSLCOMMERZ.<?php else: ?>Online payment is not configured; no online charge can be made.<?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>

            <aside class="cart-summary" aria-label="Order summary">
                <h2>Order summary</h2>
                <?php foreach ($cart['items'] as $item): ?>
                    <p class="checkout-line">
                        <span><?= htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $item['variant_name'] !== null ? ' — ' . htmlspecialchars($item['variant_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?> × <?= (int) $item['quantity'] ?></span>
                        <strong><?= htmlspecialchars((string) $item['line_total'], ENT_QUOTES, 'UTF-8') ?> BDT</strong>
                    </p>
                <?php endforeach; ?>
                <p class="checkout-total"><span>Subtotal</span><strong><?= htmlspecialchars((string) $cart['subtotal'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
                <p class="cart-checkout-note">
                    The server checks current prices and stock again when you place the order. Shipping charges are not included in this subtotal.
                    <?php if ($paymentEnabled): ?>Online payment through SSLCOMMERZ is configured.<?php else: ?>Online payment is not configured yet.<?php endif; ?>
                </p>
                <?php if (!$canSubmit && $addresses !== []): ?>
                    <p class="cart-feedback is-error">One or more cart items are unavailable or exceed current stock. <a href="/cart">Review your cart</a>.</p>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
