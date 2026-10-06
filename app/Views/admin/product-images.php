<?php require __DIR__ . '/../partials/head.php'; ?>
<main id="main-content" class="wrap page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span aria-hidden="true">/</span><a href="/admin/catalog">Catalog management</a><span aria-hidden="true">/</span><span>Product images</span>
    </nav>
    <section class="catalog-heading">
        <p class="eyebrow">Catalog administration</p>
        <h1>Product images</h1>
        <p><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <a href="/product/<?= htmlspecialchars($product['slug'], ENT_QUOTES, 'UTF-8') ?>">View product page</a></p>
    </section>
    <?php if ($notice !== ''): ?><p class="cart-feedback" role="status"><?= htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="cart-feedback is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($remainingImageSlots > 0): ?>
        <section class="account-panel" aria-labelledby="image-upload-title">
            <h2 id="image-upload-title">Add an image</h2>
            <p>JPEG, PNG, or WebP up to 5 MB. Add a short, useful image description for screen readers. <?= $remainingImageSlots ?> of 12 image slots remain.</p>
            <form class="admin-edit-form" method="post" enctype="multipart/form-data" action="/admin/catalog/products/<?= (int) $product['id'] ?>/images">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label>Image file <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
                <label>Alternative text <input name="alt_text" maxlength="255" required></label>
                <button class="button button-primary" type="submit">Upload image</button>
            </form>
        </section>
    <?php else: ?>
        <p>This product has the maximum of 12 images. Remove an image to upload another.</p>
    <?php endif; ?>

    <section class="account-orders" aria-labelledby="product-image-list-title">
        <h2 id="product-image-list-title">Current images</h2>
        <?php if ($product['images'] === []): ?>
            <p>No product images have been added yet.</p>
        <?php else: ?>
            <div class="admin-image-list">
                <?php foreach ($product['images'] as $image): ?>
                    <article class="admin-image-card">
                        <img src="<?= htmlspecialchars($image['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($image['alt_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" loading="lazy" referrerpolicy="no-referrer">
                        <div>
                            <p>Display position <?= (int) $image['sort_order'] ?></p>
                            <form class="admin-edit-form" method="post" action="/admin/catalog/products/<?= (int) $product['id'] ?>/images/<?= (int) $image['id'] ?>/alt">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <label>Alternative text <input name="alt_text" maxlength="255" required value="<?= htmlspecialchars($image['alt_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></label>
                                <button class="button button-secondary" type="submit">Save description</button>
                            </form>
                            <form method="post" action="/admin/catalog/products/<?= (int) $product['id'] ?>/images/<?= (int) $image['id'] ?>/delete">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button class="button button-secondary" type="submit">Remove image</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
