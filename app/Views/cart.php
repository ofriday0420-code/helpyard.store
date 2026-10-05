<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content" class="wrap page-main cart-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><span>Your cart</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Ready when you are</p>
        <h1>Your cart</h1>
        <p>Review your selected products. Prices and availability are checked on the server.</p>
    </section>

    <?php if ($error !== ''): ?>
        <p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
        <p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($cart['items'] === []): ?>
        <section class="cart-empty">
            <h2>Your cart is empty</h2>
            <p>Explore the catalog and add products you’re interested in.</p>
            <a class="button button-primary" href="/products">Browse products <span aria-hidden="true">→</span></a>
        </section>
    <?php else: ?>
        <div class="cart-layout">
            <section class="cart-items" aria-label="Cart items">
                <?php foreach ($cart['items'] as $item): ?>
                    <article class="cart-item">
                        <div class="cart-item-copy">
                            <p class="product-category"><?= htmlspecialchars($item['product_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <h2><a href="/product/<?= rawurlencode($item['slug']) ?>"><?= htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></h2>
                            <?php if ($item['variant_name'] !== null): ?>
                                <p>Option: <?= htmlspecialchars($item['variant_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <p><?= htmlspecialchars((string) $item['current_price'], ENT_QUOTES, 'UTF-8') ?> BDT each</p>
                        </div>
                        <?php if ((int) $item['is_available'] === 1): ?>
                            <form class="cart-quantity-form" action="/cart/items/<?= (int) $item['cart_item_id'] ?>/update" method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <label for="quantity-<?= (int) $item['cart_item_id'] ?>">Quantity</label>
                                <input id="quantity-<?= (int) $item['cart_item_id'] ?>" name="quantity" type="number" min="1" max="99" value="<?= (int) $item['quantity'] ?>" required>
                                <button class="button button-secondary" type="submit">Update</button>
                            </form>
                        <?php else: ?>
                            <p class="cart-feedback is-error" role="alert">This product option is no longer available. Remove it from your cart.</p>
                        <?php endif; ?>
                        <div class="cart-item-total">
                            <strong><?= (int) $item['is_available'] === 1
                                ? htmlspecialchars((string) $item['line_total'], ENT_QUOTES, 'UTF-8') . ' BDT'
                                : 'Unavailable' ?></strong>
                            <form action="/cart/items/<?= (int) $item['cart_item_id'] ?>/remove" method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button class="text-link" type="submit">Remove</button>
                            </form>
                        </div>
                        <?php if ((int) $item['quantity'] > (int) $item['stock_quantity']): ?>
                            <p class="cart-feedback is-error" role="alert">Only <?= (int) $item['stock_quantity'] ?> currently available. Update this quantity before continuing.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
            <aside class="cart-summary" aria-label="Order summary">
                <h2>Summary</h2>
                <p><span>Subtotal</span><strong><?= htmlspecialchars((string) $cart['subtotal'], ENT_QUOTES, 'UTF-8') ?> BDT</strong></p>
                <p class="cart-checkout-note">Sign in and confirm a saved delivery address to place an order. Payment is not configured yet.</p>
                <a class="button button-primary" href="/checkout">Continue to checkout</a>
                <a class="button button-secondary" href="/products">Continue shopping</a>
            </aside>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
