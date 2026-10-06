<?php require __DIR__ . '/partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/products">Products</a><span aria-hidden="true">/</span><span><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </nav>
    <section class="product-detail" data-product-detail data-server-rendered="1" data-api="/api/v1/products/<?= htmlspecialchars($product['slug'], ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="detail-gallery">
            <div class="detail-main-image">
                <?php if ($product['images'] !== []): ?>
                    <img data-main-image src="<?= htmlspecialchars($product['images'][0]['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['images'][0]['alt_text'] ?: $product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <?php else: ?>
                    <?php preg_match('/^./us', $product['name'], $initialMatch); ?>
                    <span class="product-image-mark" aria-hidden="true"><?= htmlspecialchars(strtoupper($initialMatch[0] ?? 'H'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <?php if (count($product['images']) > 1): ?>
                <div class="detail-thumbnails" aria-label="More product images">
                    <?php foreach ($product['images'] as $index => $image): ?>
                        <button class="detail-thumb" type="button" data-image="<?= htmlspecialchars($image['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-alt="<?= htmlspecialchars($image['alt_text'] ?: $product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Show image <?= $index + 1 ?>: <?= htmlspecialchars($image['alt_text'] ?: $product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                            <img src="<?= htmlspecialchars($image['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="detail-copy">
            <p class="detail-category"><?= htmlspecialchars($product['category_name'] ?? $product['product_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <h1 class="detail-title"><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
            <p class="detail-price" data-base-price="<?= htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8') ?>">৳<?= htmlspecialchars(number_format((float) $product['price'], 2), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="detail-description"><?= nl2br(htmlspecialchars((string) ($product['description'] ?: $product['short_description'] ?: 'Product details will be available soon.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></div>
            <ul class="detail-meta">
                <li>Product type:<strong><?= htmlspecialchars($product['product_type'], ENT_QUOTES, 'UTF-8') ?></strong></li>
                <li>Availability:<strong><?= $hasAvailableStock ? 'In stock' : 'Currently unavailable' ?></strong></li>
            </ul>
            <?php if ($product['variants'] !== []): ?>
                <section aria-label="Product options">
                    <h2 class="detail-category">Available options</h2>
                    <ul class="variant-list">
                        <?php foreach ($product['variants'] as $variant): ?>
                            <li class="variant-option">
                                <strong><?= htmlspecialchars($variant['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                <?php if (is_array($variant['attributes']) && $variant['attributes'] !== []): ?>
                                    <span><?= htmlspecialchars(implode(' · ', array_map(
                                        static fn (string $key, mixed $value): string => $key . ': ' . (is_scalar($value) ? (string) $value : ''),
                                        array_keys($variant['attributes']),
                                        array_values($variant['attributes'])
                                    )), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
            <?php if ($hasAvailableStock): ?>
                <form id="add-product-form" class="detail-add-form" action="/cart/items" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <?php if ($product['variants'] !== []): ?>
                        <label for="product-variant">Choose an option</label>
                        <select id="product-variant" name="variant_id" required>
                            <option value="">Select an option</option>
                            <?php foreach ($product['variants'] as $variant): ?>
                                <option value="<?= (int) $variant['id'] ?>" data-stock="<?= (int) $variant['stock_quantity'] ?>" data-price="<?= htmlspecialchars((string) ($variant['price_override'] ?? $product['price']), ENT_QUOTES, 'UTF-8') ?>" <?= (int) $variant['stock_quantity'] < 1 ? 'disabled' : '' ?>><?= htmlspecialchars($variant['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> — ৳<?= htmlspecialchars(number_format((float) ($variant['price_override'] ?? $product['price']), 2), ENT_QUOTES, 'UTF-8') ?><?= (int) $variant['stock_quantity'] < 1 ? ' (out of stock)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <label for="product-quantity">Quantity</label>
                    <input id="product-quantity" name="quantity" type="number" min="1" max="<?= $product['variants'] === [] ? min(99, (int) $product['stock_quantity']) : 99 ?>" value="1" required>
                    <button class="button button-primary" type="submit">Add to cart</button>
                </form>
            <?php else: ?>
                <p class="detail-note">This product is currently unavailable.</p>
            <?php endif; ?>
            <p class="detail-note">Checkout and payment are the next step. Adding a product does not create an order or charge you.</p>
        </div>
    </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
